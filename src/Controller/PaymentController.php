<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Event\Payment\PaymentCreatedEvent;
use App\Repository\DriverRepository;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payments')]
#[IsGranted('ROLE_USER')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentProcessor            $paymentProcessor,
        private readonly PaymentRepository           $paymentRepository,
        private readonly DriverRepository            $driverRepository,
        private readonly EventDispatcherInterface    $eventDispatcher,
        #[Autowire(service: 'limiter.payment_api')]
        private readonly RateLimiterFactoryInterface $paymentApiLimiter,
        private readonly LoggerInterface             $logger,
        private readonly UrlGeneratorInterface       $urlGenerator,
    ) {
    }

    #[Route('/create-preference', name: 'api_payment_create_preference', methods: ['POST'])]
    public function createPreference(Request $request): JsonResponse
    {
        // Rate limiting
        $limiter = $this->paymentApiLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            $requiredFields = ['driver_id', 'student_ids', 'amount', 'description', 'idempotency_key'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return new JsonResponse([
                        'error' => "Missing required field: {$field}",
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Validate idempotency key format (UUID)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $data['idempotency_key'])) {
                return new JsonResponse([
                    'error' => 'Invalid idempotency_key format. Must be a valid UUID.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Resolve and validate the target driver
            $driver = $this->driverRepository->find((int) $data['driver_id']);
            if ($driver === null) {
                return new JsonResponse(
                    ['error' => "Driver {$data['driver_id']} not found."],
                    Response::HTTP_NOT_FOUND
                );
            }

            if (!$driver->hasMpAuthorized()) {
                return new JsonResponse(
                    ['error' => 'This driver has not connected their Mercado Pago account yet.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Create payment
            $payment = $this->paymentProcessor->createPayment(
                user: $user,
                studentIds: $data['student_ids'],
                amount: (string) $data['amount'],
                description: $data['description'],
                idempotencyKey: $data['idempotency_key'],
                currency: $data['currency'] ?? 'ARS',
                driver: $driver,
            );

            // Create Mercado Pago preference
            $backUrl = $this->urlGenerator->generate(
                'app_home',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $notificationUrl = $this->urlGenerator->generate(
                'api_webhook_mercadopago',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $preference = $this->paymentProcessor->createPaymentPreference(
                $payment,
                $backUrl,
                $notificationUrl
            );

            // Dispatch payment created event
            $this->eventDispatcher->dispatch(
                new PaymentCreatedEvent($payment),
                PaymentCreatedEvent::NAME
            );

            $this->logger->info('Payment preference created', [
                'payment_id' => $payment->getId(),
                'user_id' => $user->getId(),
                'preference_id' => $preference['preference_id'],
            ]);

            return new JsonResponse([
                'payment_id' => $payment->getId(),
                'preference_id' => $preference['preference_id'],
                'init_point' => $preference['init_point'],
                'sandbox_init_point' => $preference['sandbox_init_point'] ?? null,
                'status' => $payment->getStatus()->value,
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'expires_at' => $payment->getExpiresAt()?->format('c'),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Payment creation failed - validation error', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Payment preference creation failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to create payment preference. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/status', name: 'api_payment_status', methods: ['GET'])]
    public function getStatus(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return new JsonResponse([
                'error' => 'Payment not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify ownership
        if ($payment->getUser()->getId() !== $user->getId()) {
            return new JsonResponse([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        // Sync status if payment has provider ID
        if ($payment->getPaymentProviderId()) {
            try {
                $payment = $this->paymentProcessor->syncPaymentStatus($payment);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to sync payment status', [
                    'payment_id' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $driver = $payment->getDriver();

        return new JsonResponse([
            'payment_id'     => $payment->getId(),
            'status'         => $payment->getStatus()->value,
            'payment_method' => $payment->getPaymentMethod()?->value,
            'amount'         => $payment->getAmount(),
            'currency'       => $payment->getCurrency(),
            'paid_at'        => $payment->getPaidAt()?->format('c'),
            'created_at'     => $payment->getCreatedAt()->format('c'),
            'mercado_pago_id' => $payment->getPaymentProviderId(),
            'driver'         => $driver ? [
                'id'           => $driver->getId(),
                'nickname'     => $driver->getNickname(),
                'mp_account_id' => $driver->getMpAccountId(),
            ] : null,
            'students' => array_map(fn($student) => [
                'id'   => $student->getId(),
                'name' => $student->getFirstName() . ' ' . $student->getLastName(),
            ], $payment->getStudents()->toArray()),
        ]);
    }

    #[Route('', name: 'api_payments_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $status = $request->query->get('status');
        $limit = min((int) $request->query->get('limit', 30), 100);
        $offset = (int) $request->query->get('offset', 0);

        $paymentStatus = null;
        if ($status) {
            try {
                $paymentStatus = PaymentStatus::from($status);
            } catch (\ValueError $e) {
                return new JsonResponse([
                    'error' => 'Invalid status value',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $payments = $this->paymentRepository->findByUser($user, $paymentStatus, $limit, $offset);

        return new JsonResponse([
            'payments' => array_map(function (Payment $payment) {
                return [
                    'id' => $payment->getId(),
                    'status' => $payment->getStatus()->value,
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'description' => $payment->getDescription(),
                    'payment_method' => $payment->getPaymentMethod()?->value,
                    'created_at' => $payment->getCreatedAt()->format('c'),
                    'paid_at' => $payment->getPaidAt()?->format('c'),
                    'student_count' => $payment->getStudents()->count(),
                ];
            }, $payments),
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($payments),
            ],
        ]);
    }

    #[Route('/{id}', name: 'api_payment_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return new JsonResponse([
                'error' => 'Payment not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify ownership
        if ($payment->getUser()->getId() !== $user->getId()) {
            return new JsonResponse([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        $driver = $payment->getDriver();

        return new JsonResponse([
            'id'                  => $payment->getId(),
            'status'              => $payment->getStatus()->value,
            'amount'              => $payment->getAmount(),
            'currency'            => $payment->getCurrency(),
            'description'         => $payment->getDescription(),
            'payment_method'      => $payment->getPaymentMethod()?->value,
            'payment_provider_id' => $payment->getPaymentProviderId(),
            'preference_id'       => $payment->getPreferenceId(),
            'created_at'          => $payment->getCreatedAt()->format('c'),
            'paid_at'             => $payment->getPaidAt()?->format('c'),
            'expires_at'          => $payment->getExpiresAt()?->format('c'),
            'refunded_amount'     => $payment->getRefundedAmount(),
            'driver'              => $driver ? [
                'id'            => $driver->getId(),
                'nickname'      => $driver->getNickname(),
                'mp_account_id' => $driver->getMpAccountId(),
            ] : null,
            'students'     => array_map(fn($student) => [
                'id'         => $student->getId(),
                'first_name' => $student->getFirstName(),
                'last_name'  => $student->getLastName(),
            ], $payment->getStudents()->toArray()),
            'transactions' => array_map(fn($transaction) => [
                'event_type' => $transaction->getEventType()->value,
                'status'     => $transaction->getStatus()->value,
                'created_at' => $transaction->getCreatedAt()->format('c'),
            ], $payment->getTransactions()->toArray()),
        ]);
    }
}
