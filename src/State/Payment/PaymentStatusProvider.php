<?php

declare(strict_types=1);

namespace App\State\Payment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Payment\PaymentStatusOutput;
use App\Entity\Driver;
use App\Entity\Payment;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentProcessor;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * State provider for GET /api/payments/{id}/status.
 *
 * Loads the payment, optionally syncs status from Mercado Pago, then returns
 * a PaymentStatusOutput DTO. Syncing is best-effort — network errors are logged
 * and silently ignored so the cached DB state is always returned.
 *
 * @implements ProviderInterface<PaymentStatusOutput>
 */
final readonly class PaymentStatusProvider implements ProviderInterface
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentProcessor $paymentProcessor,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaymentStatusOutput
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;
        $payment = $this->paymentRepository->find($id);

        if (! $payment instanceof Payment) {
            throw new NotFoundHttpException('Payment not found.');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        // Only the owner or a school admin may view the status
        if ($payment->getUser()?->getId() !== $user->getId() && ! $this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        // Sync from Mercado Pago if we have a provider ID (best-effort)
        if ($payment->getPaymentProviderId() !== null) {
            try {
                $payment = $this->paymentProcessor->syncPaymentStatus($payment);
            } catch (Exception $e) {
                $this->logger->warning('Failed to sync payment status from Mercado Pago', [
                    'payment_id' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $driver = $payment->getDriver();

        return new PaymentStatusOutput(
            paymentId: (int) $payment->getId(),
            status: $payment->getStatus()->value,
            paymentMethod: $payment->getPaymentMethod()?->value,
            amount: (string) $payment->getAmount(),
            currency: $payment->getCurrency(),
            paidAt: $payment->getPaidAt()?->format('c'),
            createdAt: $payment->getCreatedAt()->format('c'),
            mercadoPagoId: $payment->getPaymentProviderId(),
            driver: $driver instanceof Driver ? [
                'id' => (int) $driver->getId(),
                'nickname' => (string) $driver->getNickname(),
                'mpAccountId' => $driver->getMpAccountId(),
            ] : null,
            students: array_map(
                static fn (Student $s): array => [
                    'id' => (int) $s->getId(),
                    'name' => $s->getFirstName() . ' ' . $s->getLastName(),
                ],
                $payment->getStudents()->toArray(),
            ),
        );
    }
}
