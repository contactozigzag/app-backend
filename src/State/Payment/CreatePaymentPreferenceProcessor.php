<?php

declare(strict_types=1);

namespace App\State\Payment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Payment\CreatePaymentPreferenceInput;
use App\Dto\Payment\PaymentPreferenceOutput;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Event\Payment\PaymentCreatedEvent;
use App\Repository\DriverRepository;
use App\Repository\PaymentRepository;
use App\Repository\RouteRepository;
use App\Service\Payment\DriverRateCalculator;
use App\Service\Payment\PaymentProcessor;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles POST /api/payments/preference.
 *
 * Responsibilities:
 *  1. Enforce the per-IP rate limit (10 req/min via payment_api limiter)
 *  2. Resolve and validate the target Driver
 *  3. Create the Payment entity via PaymentProcessor (idempotency-protected)
 *  4. Create the Mercado Pago preference via PaymentProcessor
 *  5. Dispatch PaymentCreatedEvent for Mercure + notification pipeline
 *  6. Return a PaymentPreferenceOutput DTO (no entity exposed directly)
 *
 * @implements ProcessorInterface<CreatePaymentPreferenceInput, PaymentPreferenceOutput>
 */
final readonly class CreatePaymentPreferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentProcessor $paymentProcessor,
        private DriverRepository $driverRepository,
        private RouteRepository $routeRepository,
        private PaymentRepository $paymentRepository,
        private DriverRateCalculator $driverRateCalculator,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.payment_api')]
        private RateLimiterFactoryInterface $paymentApiLimiter,
    ) {
    }

    /**
     * @param CreatePaymentPreferenceInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentPreferenceOutput
    {
        // ── 1. Rate limiting ────────────────────────────────────────────────
        $request = $context['request'] instanceof Request ? $context['request'] : null;
        $clientIp = $request?->getClientIp() ?? 'unknown';

        $limiter = $this->paymentApiLimiter->create($clientIp);
        if (! $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Too many payment requests. Please try again later.');
        }

        // ── 2. Resolve authenticated user ───────────────────────────────────
        /** @var User $user */
        $user = $this->security->getUser();

        // ── 3. Resolve and validate driver ──────────────────────────────────
        $driver = $this->driverRepository->find($data->driverId);

        if ($driver === null) {
            throw new NotFoundHttpException(sprintf('Driver %d not found.', $data->driverId));
        }

        if (! $driver->hasMpAuthorized()) {
            throw new UnprocessableEntityHttpException('This driver has not connected their Mercado Pago account yet.');
        }

        // ── 4. Calculate amount from driver rate ─────────────────────────────
        $route = null;
        if ($data->routeId !== null) {
            $route = $this->routeRepository->find($data->routeId);
            if ($route === null) {
                throw new NotFoundHttpException(sprintf('Route %d not found.', $data->routeId));
            }
        }

        try {
            $calculatedRate = $this->driverRateCalculator->calculateAmount(
                $driver,
                $route,
                count($data->studentIds),
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new UnprocessableEntityHttpException($invalidArgumentException->getMessage(), $invalidArgumentException);
        }

        // ── 5. Cancel any existing unexpired pending payment for this user+driver ─
        $existingPending = $this->paymentRepository->findActivePendingPayment($user, $driver);

        if ($existingPending !== null) {
            $existingPending->setStatus(PaymentStatus::CANCELLED);
            $this->logger->info('Cancelled stale pending payment before creating new one', [
                'cancelled_payment_id' => $existingPending->getId(),
                'user_id' => $user->getId(),
                'driver_id' => $driver->getId(),
            ]);
        }

        // ── 6. Create payment (idempotency-protected) ───────────────────────
        try {
            $payment = $this->paymentProcessor->createPayment(
                user: $user,
                studentIds: $data->studentIds,
                amount: $calculatedRate->amount,
                description: $data->description,
                idempotencyKey: $data->idempotencyKey,
                currency: $calculatedRate->currency,
                driver: $driver,
                rateSnapshot: $calculatedRate->rateSnapshot,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new UnprocessableEntityHttpException($invalidArgumentException->getMessage(), $invalidArgumentException);
        }

        // ── 7. Create Mercado Pago preference ───────────────────────────────
        $backUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $notificationUrl = rtrim($request?->getSchemeAndHttpHost() ?? '', '/') . '/api/webhooks/mercadopago';

        try {
            $preference = $this->paymentProcessor->createPaymentPreference($payment, $backUrl, $notificationUrl);
        } catch (Exception $exception) {
            $this->logger->error('Payment preference creation failed', [
                'payment_id' => $payment->getId(),
                'user_id' => $user->getId(),
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(502, 'Failed to create payment preference with Mercado Pago. Please try again.', $exception);
        }

        // ── 8. Dispatch event (Mercure publish + notifications) ─────────────
        $this->eventDispatcher->dispatch(
            new PaymentCreatedEvent($payment),
            PaymentCreatedEvent::NAME,
        );

        $this->logger->info('Payment preference created', [
            'payment_id' => $payment->getId(),
            'user_id' => $user->getId(),
            'preference_id' => $preference['preference_id'],
        ]);

        // ── 9. Return output DTO ────────────────────────────────────────────
        return new PaymentPreferenceOutput(
            paymentId: (int) $payment->getId(),
            preferenceId: $preference['preference_id'],
            initPoint: $preference['init_point'],
            sandboxInitPoint: $preference['sandbox_init_point'] ?? null,
            status: $payment->getStatus()->value,
            amount: (string) $payment->getAmount(),
            currency: $payment->getCurrency(),
            expiresAt: $payment->getExpiresAt()?->format('c'),
        );
    }
}
