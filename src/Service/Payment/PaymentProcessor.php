<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Driver;
use App\Entity\Payment;
use App\Entity\PaymentTransaction;
use App\Entity\Student;
use App\Entity\User;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\TransactionEvent;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class PaymentProcessor
{
    private const int MAX_RETRIES = 3;

    private const array RETRY_DELAY_MS = [1000, 2000, 4000]; // Exponential backoff

    public function __construct(
        private readonly StudentRepository $studentRepository,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly MercadoPagoOAuthService $oauthService,
        private readonly IdempotencyService $idempotencyService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a new payment with idempotency protection.
     *
     * @param User $user The parent making the payment
     * @param array<int> $studentIds
     * @param Driver|null $driver The driver who will receive the funds
     * @throws InvalidArgumentException
     */
    public function createPayment(
        User $user,
        array $studentIds,
        string $amount,
        string $description,
        string $idempotencyKey,
        string $currency = 'USD',
        ?Driver $driver = null,
    ): Payment {
        if ($driver instanceof \App\Entity\Driver && ! $driver->hasMpAuthorized()) {
            throw new \InvalidArgumentException(
                sprintf('Driver %s has not connected their Mercado Pago account.', $driver->getId())
            );
        }

        return $this->idempotencyService->processWithIdempotency(
            $idempotencyKey,
            function () use ($user, $driver, $studentIds, $amount, $description, $idempotencyKey, $currency): \App\Entity\Payment {
                $this->logger->info('Creating payment', [
                    'user_id' => $user->getId(),
                    'driver_id' => $driver?->getId(),
                    'student_ids' => $studentIds,
                    'amount' => $amount,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $students = $this->validateAndGetStudents($user, $studentIds);

                $payment = new Payment();
                $payment->setUser($user);
                if ($driver instanceof \App\Entity\Driver) {
                    $payment->setDriver($driver);
                }

                $payment->setAmount($amount);
                $payment->setCurrency($currency);
                $payment->setDescription($description);
                $payment->setIdempotencyKey($idempotencyKey);
                $payment->setStatus(PaymentStatus::PENDING);
                $payment->setExpiresAt(new \DateTimeImmutable('+24 hours'));

                foreach ($students as $student) {
                    $payment->addStudent($student);
                }

                $transaction = new PaymentTransaction();
                $transaction->setPayment($payment);
                $transaction->setEventType(TransactionEvent::CREATED);
                $transaction->setStatus(PaymentStatus::PENDING);
                $transaction->setIdempotencyKey($idempotencyKey);

                $payment->addTransaction($transaction);

                $this->entityManager->persist($payment);
                $this->entityManager->flush();

                $this->logger->info('Payment created successfully', [
                    'payment_id' => $payment->getId(),
                    'user_id' => $user->getId(),
                    'driver_id' => $driver?->getId(),
                ]);

                return $payment;
            }
        );
    }

    /**
     * Create Mercado Pago Marketplace preference for payment.
     *
     * Fetches (and auto-refreshes) the driver's access token then delegates
     * to MercadoPagoService, which passes it as a per-request RequestOptions.
     *
     * @param Payment $payment Must have a driver with a valid MP account
     * @return array{preference_id: string, init_point: string}
     * @throws \Exception
     */
    public function createPaymentPreference(
        Payment $payment,
        string $backUrl,
        string $notificationUrl,
    ): array {
        $driver = $payment->getDriver();
        if (! $driver instanceof \App\Entity\Driver) {
            throw new \InvalidArgumentException('Payment has no driver — cannot create a marketplace preference.');
        }

        // Decrypt and (if needed) refresh the driver's access token before use
        $driverAccessToken = $this->oauthService->getAccessToken($driver);

        $retryCount = 0;

        while ($retryCount < self::MAX_RETRIES) {
            try {
                $preference = $this->mercadoPagoService->createPreference(
                    $payment,
                    $payment->getUser(),
                    $backUrl,
                    $notificationUrl,
                    $driverAccessToken,
                );

                // Update payment with preference ID
                $payment->setPreferenceId($preference['preference_id']);
                $this->entityManager->flush();

                return $preference;
            } catch (\Exception $e) {
                $retryCount++;

                $this->logger->warning('Failed to create payment preference', [
                    'payment_id' => $payment->getId(),
                    'retry' => $retryCount,
                    'error' => $e->getMessage(),
                ]);

                if ($retryCount >= self::MAX_RETRIES) {
                    $this->logger->error('Max retries reached for payment preference creation', [
                        'payment_id' => $payment->getId(),
                    ]);

                    throw $e;
                }

                // Exponential backoff
                usleep(self::RETRY_DELAY_MS[$retryCount - 1] * 1000);
            }
        }

        throw new \RuntimeException('Failed to create payment preference after retries');
    }

    /**
     * Update payment status from the webhook
     */
    public function updatePaymentFromWebhook(
        Payment $payment,
        string $paymentProviderId,
        array $webhookData
    ): Payment {
        $this->logger->info('Updating payment from webhook', [
            'payment_id' => $payment->getId(),
            'provider_id' => $paymentProviderId,
        ]);

        // Fetch latest status from Mercado Pago
        $paymentDetails = $this->mercadoPagoService->getPaymentStatus($paymentProviderId);

        // Map Mercado Pago status to our status
        $newStatus = $this->mapMercadoPagoStatus($paymentDetails['status']);
        $oldStatus = $payment->getStatus();

        if ($newStatus !== $oldStatus) {
            $payment->setStatus($newStatus);
            $payment->setPaymentProviderId($paymentProviderId);
            $payment->setMetadata($paymentDetails);

            // Set paid_at if approved
            if ($newStatus === PaymentStatus::APPROVED && ! $payment->getPaidAt() instanceof \DateTimeImmutable) {
                $payment->setPaidAt(new \DateTimeImmutable());
            }

            // Map payment method
            if (isset($paymentDetails['payment_method_id'])) {
                $paymentMethod = $this->mapPaymentMethod($paymentDetails['payment_method_id']);
                $payment->setPaymentMethod($paymentMethod);
            }

            // Create a transaction record
            $transaction = new PaymentTransaction();
            $transaction->setPayment($payment);
            $transaction->setEventType(TransactionEvent::WEBHOOK_RECEIVED);
            $transaction->setStatus($newStatus);
            $transaction->setProviderResponse($webhookData);
            $payment->addTransaction($transaction);

            $this->entityManager->flush();

            $this->logger->info('Payment status updated', [
                'payment_id' => $payment->getId(),
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ]);

            // Dispatch event for status change
            // This will be handled by event subscribers
        }

        return $payment;
    }

    /**
     * Synchronize payment status with Mercado Pago
     */
    public function syncPaymentStatus(Payment $payment): Payment
    {
        if ($payment->getPaymentProviderId() === null) {
            $this->logger->warning('Cannot sync payment without provider ID', [
                'payment_id' => $payment->getId(),
            ]);

            return $payment;
        }

        try {
            $paymentDetails = $this->mercadoPagoService->getPaymentStatus($payment->getPaymentProviderId());

            $newStatus = $this->mapMercadoPagoStatus($paymentDetails['status']);

            if ($newStatus !== $payment->getStatus()) {
                $payment->setStatus($newStatus);
                $payment->setMetadata($paymentDetails);

                if ($newStatus === PaymentStatus::APPROVED && ! $payment->getPaidAt() instanceof \DateTimeImmutable) {
                    $payment->setPaidAt(new \DateTimeImmutable());
                }

                $this->entityManager->flush();

                $this->logger->info('Payment status synchronized', [
                    'payment_id' => $payment->getId(),
                    'status' => $newStatus->value,
                ]);
            }
        } catch (\Exception $exception) {
            $this->logger->error('Failed to synchronize payment status', [
                'payment_id' => $payment->getId(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $payment;
    }

    /**
     * Validate and retrieve students
     *
     * @param array<int> $studentIds
     * @return array<Student>
     * @throws \InvalidArgumentException
     */
    private function validateAndGetStudents(User $user, array $studentIds): array
    {
        if ($studentIds === []) {
            throw new \InvalidArgumentException('At least one student must be specified');
        }

        $students = [];
        foreach ($studentIds as $studentId) {
            $student = $this->studentRepository->find($studentId);

            if (! $student instanceof \App\Entity\Student) {
                throw new \InvalidArgumentException(sprintf('Student with ID %d not found', $studentId));
            }

            // Verify a student belongs to user (parent relationship)
            if (! $student->getParents()->contains($user)) {
                throw new \InvalidArgumentException(sprintf('Student with ID %d does not belong to user', $studentId));
            }

            $students[] = $student;
        }

        return $students;
    }

    /**
     * Map Mercado Pago status to internal PaymentStatus
     */
    private function mapMercadoPagoStatus(string $mpStatus): PaymentStatus
    {
        return match ($mpStatus) {
            'approved' => PaymentStatus::APPROVED,
            'rejected', 'cancelled' => PaymentStatus::REJECTED,
            'refunded', 'charged_back' => PaymentStatus::REFUNDED,
            'in_process', 'in_mediation' => PaymentStatus::PROCESSING,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Map Mercado Pago payment method to internal enum
     */
    private function mapPaymentMethod(string $mpMethod): \App\Enum\PaymentMethod
    {
        return match (true) {
            str_contains($mpMethod, 'credit') => PaymentMethod::CREDIT_CARD,
            str_contains($mpMethod, 'debit') => PaymentMethod::DEBIT_CARD,
            str_contains($mpMethod, 'ticket') => PaymentMethod::CASH,
            str_contains($mpMethod, 'bank') => PaymentMethod::BANK_TRANSFER,
            default => PaymentMethod::MERCADO_PAGO,
        };
    }
}
