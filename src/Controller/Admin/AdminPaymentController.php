<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PaymentTransaction;
use App\Enum\PaymentStatus;
use App\Enum\TransactionEvent;
use App\Event\Payment\PaymentRefundedEvent;
use App\Repository\PaymentRepository;
use App\Service\Payment\MercadoPagoService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/payments')]
#[IsGranted('ROLE_SCHOOL_ADMIN')]
class AdminPaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/{id}/refund', name: 'api_admin_payment_refund', methods: ['POST'])]
    public function refund(int $id, Request $request): JsonResponse
    {
        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return new JsonResponse([
                'error' => 'Payment not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$payment->isRefundable()) {
            return new JsonResponse([
                'error' => 'Payment cannot be refunded',
                'status' => $payment->getStatus()->value,
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$payment->getPaymentProviderId()) {
            return new JsonResponse([
                'error' => 'Payment has no provider ID',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $refundAmount = isset($data['amount']) ? (float) $data['amount'] : null;
            $reason = $data['reason'] ?? 'Refund requested by admin';

            // Validate refund amount
            if ($refundAmount !== null) {
                $maxRefundable = (float) $payment->getAmount() - (float) $payment->getRefundedAmount();
                if ($refundAmount > $maxRefundable) {
                    return new JsonResponse([
                        'error' => 'Refund amount exceeds available amount',
                        'max_refundable' => $maxRefundable,
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $this->logger->info('Processing refund', [
                'payment_id' => $payment->getId(),
                'amount' => $refundAmount,
                'admin' => $this->getUser()->getUserIdentifier(),
            ]);

            // Process refund via Mercado Pago
            $refundResult = $this->mercadoPagoService->refundPayment(
                $payment->getPaymentProviderId(),
                $refundAmount
            );

            // Update payment status
            $refundedAmount = (string) ($refundResult['amount'] ?? $refundAmount ?? $payment->getAmount());
            $currentRefunded = (float) $payment->getRefundedAmount();
            $newRefunded = (string) ($currentRefunded + (float) $refundedAmount);

            $payment->setRefundedAmount($newRefunded);

            // Update status
            if (bccomp($newRefunded, $payment->getAmount(), 2) >= 0) {
                $payment->setStatus(PaymentStatus::REFUNDED);
            } else {
                $payment->setStatus(PaymentStatus::PARTIALLY_REFUNDED);
            }

            // Create transaction record
            $transaction = new PaymentTransaction();
            $transaction->setPayment($payment);
            $transaction->setEventType(TransactionEvent::REFUNDED);
            $transaction->setStatus($payment->getStatus());
            $transaction->setProviderResponse($refundResult);
            $transaction->setNotes("Refund: {$refundedAmount}. Reason: {$reason}");

            $payment->addTransaction($transaction);
            $this->entityManager->flush();

            // Dispatch event
            $this->eventDispatcher->dispatch(
                new PaymentRefundedEvent($payment, $refundedAmount, $reason),
                PaymentRefundedEvent::NAME
            );

            $this->logger->info('Refund processed successfully', [
                'payment_id' => $payment->getId(),
                'refund_amount' => $refundedAmount,
                'new_status' => $payment->getStatus()->value,
            ]);

            return new JsonResponse([
                'success' => true,
                'payment_id' => $payment->getId(),
                'refunded_amount' => $refundedAmount,
                'total_refunded' => $payment->getRefundedAmount(),
                'status' => $payment->getStatus()->value,
                'refund_id' => $refundResult['refund_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Refund processing failed', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Refund processing failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/reconciliation', name: 'api_admin_payment_reconciliation', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function reconciliation(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        if (!$from || !$to) {
            return new JsonResponse([
                'error' => 'Missing required parameters: from and to dates',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $fromDate = new \DateTimeImmutable($from);
            $toDate = new \DateTimeImmutable($to);

            $this->logger->info('Running payment reconciliation', [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
                'admin' => $this->getUser()->getUserIdentifier(),
            ]);

            // Get payments from database
            $dbPayments = $this->paymentRepository->createQueryBuilder('p')
                ->where('p.createdAt BETWEEN :from AND :to')
                ->setParameter('from', $fromDate)
                ->setParameter('to', $toDate)
                ->getQuery()
                ->getResult();

            // Get payments from Mercado Pago
            $mpPayments = $this->mercadoPagoService->getPaymentsByDateRange($fromDate, $toDate);

            // Reconcile
            $dbPaymentIds = array_map(fn($p) => $p->getPaymentProviderId(), $dbPayments);
            $mpPaymentIds = array_column($mpPayments, 'id');

            $missingInMP = array_diff($dbPaymentIds, $mpPaymentIds);
            $missingInDB = array_diff($mpPaymentIds, $dbPaymentIds);

            // Calculate totals
            $totalPayments = count($dbPayments);
            $totalAmount = array_reduce($dbPayments, fn($sum, $p) => $sum + (float) $p->getAmount(), 0);
            $approvedCount = count(array_filter($dbPayments, fn($p) => $p->getStatus() === PaymentStatus::APPROVED));

            $discrepancies = [];

            foreach ($missingInMP as $paymentId) {
                $payment = array_filter($dbPayments, fn($p) => $p->getPaymentProviderId() === $paymentId);
                $payment = reset($payment);
                if ($payment) {
                    $discrepancies[] = [
                        'type' => 'missing_in_mp',
                        'payment_id' => $payment->getId(),
                        'provider_id' => $paymentId,
                        'amount' => $payment->getAmount(),
                        'date' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                    ];
                }
            }

            foreach ($missingInDB as $paymentId) {
                $discrepancies[] = [
                    'type' => 'missing_in_db',
                    'provider_id' => $paymentId,
                ];
            }

            return new JsonResponse([
                'period' => [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d'),
                ],
                'summary' => [
                    'total_payments' => $totalPayments,
                    'total_amount' => number_format($totalAmount, 2),
                    'approved_count' => $approvedCount,
                    'matched' => $totalPayments - count($missingInMP),
                    'missing_in_mp' => count($missingInMP),
                    'missing_in_db' => count($missingInDB),
                ],
                'discrepancies' => $discrepancies,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Reconciliation failed', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Reconciliation failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/stats', name: 'api_admin_payment_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $from = $request->query->get('from', (new \DateTimeImmutable('-30 days'))->format('Y-m-d'));
        $to = $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d'));

        try {
            $fromDate = new \DateTimeImmutable($from);
            $toDate = new \DateTimeImmutable($to);

            $approvedCount = $this->paymentRepository->countByStatusAndDateRange(
                PaymentStatus::APPROVED,
                $fromDate,
                $toDate
            );

            $pendingCount = $this->paymentRepository->countByStatusAndDateRange(
                PaymentStatus::PENDING,
                $fromDate,
                $toDate
            );

            $rejectedCount = $this->paymentRepository->countByStatusAndDateRange(
                PaymentStatus::REJECTED,
                $fromDate,
                $toDate
            );

            $totalCount = $approvedCount + $pendingCount + $rejectedCount;

            return new JsonResponse([
                'period' => [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d'),
                ],
                'stats' => [
                    'total' => $totalCount,
                    'approved' => $approvedCount,
                    'pending' => $pendingCount,
                    'rejected' => $rejectedCount,
                    'approval_rate' => $totalCount > 0 ? round(($approvedCount / $totalCount) * 100, 2) : 0,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to fetch stats: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
