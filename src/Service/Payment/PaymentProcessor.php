<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\PaymentTransaction;
use App\Entity\Student;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\TransactionEvent;
use App\Repository\PaymentRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PaymentProcessor
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = [1000, 2000, 4000]; // Exponential backoff

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly StudentRepository $studentRepository,
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly IdempotencyService $idempotencyService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create a new payment with idempotency protection
     *
     * @param User $user
     * @param array<int> $studentIds
     * @param string $amount
     * @param string $description
     * @param string $idempotencyKey
     * @param string $currency
     * @return Payment
     * @throws \Exception
     */
    public function createPayment(
        User $user,
        array $studentIds,
        string $amount,
        string $description,
        string $idempotencyKey,
        string $currency = 'USD'
    ): Payment {
        return $this->idempotencyService->processWithIdempotency(
            $idempotencyKey,
            function () use ($user, $studentIds, $amount, $description, $idempotencyKey, $currency) {
                $this->logger->info('Creating payment', [
                    'user_id' => $user->getId(),
                    'student_ids' => $studentIds,
                    'amount' => $amount,
                    'idempotency_key' => $idempotencyKey,
                ]);

                // Validate students belong to user
                $students = $this->validateAndGetStudents($user, $studentIds);

                // Create payment entity
                $payment = new Payment();
                $payment->setUser($user);
                $payment->setAmount($amount);
                $payment->setCurrency($currency);
                $payment->setDescription($description);
                $payment->setIdempotencyKey($idempotencyKey);
                $payment->setStatus(PaymentStatus::PENDING);
                $payment->setExpiresAt(new \DateTimeImmutable('+24 hours'));

                foreach ($students as $student) {
                    $payment->addStudent($student);
                }

                // Create initial transaction record
                $transaction = new PaymentTransaction();
                $transaction->setPayment($payment);
                $transaction->setEventType(TransactionEvent::CREATED);
                $transaction->setStatus(PaymentStatus::PENDING);
                $transaction->setIdempotencyKey($idempotencyKey);
                $payment->addTransaction($transaction);

                // Persist payment
                $this->entityManager->persist($payment);
                $this->entityManager->flush();

                $this->logger->info('Payment created successfully', [
                    'payment_id' => $payment->getId(),
                    'user_id' => $user->getId(),
                ]);

                return $payment;
            }
        );
    }

    /**
     * Create Mercado Pago preference for payment
     *
     * @param Payment $payment
     * @param string $backUrl
     * @param string $notificationUrl
     * @return array{preference_id: string, init_point: string}
     * @throws \Exception
     */
    public function createPaymentPreference(
        Payment $payment,
        string $backUrl,
        string $notificationUrl
    ): array {
        $retryCount = 0;

        while ($retryCount < self::MAX_RETRIES) {
            try {
                $preference = $this->mercadoPagoService->createPreference(
                    $payment,
                    $payment->getUser(),
                    $backUrl,
                    $notificationUrl
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
     * Update payment status from webhook
     *
     * @param Payment $payment
     * @param string $paymentProviderId
     * @param array $webhookData
     * @return Payment
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
            if ($newStatus === PaymentStatus::APPROVED && $payment->getPaidAt() === null) {
                $payment->setPaidAt(new \DateTimeImmutable());
            }

            // Map payment method
            if (isset($paymentDetails['payment_method_id'])) {
                $paymentMethod = $this->mapPaymentMethod($paymentDetails['payment_method_id']);
                $payment->setPaymentMethod($paymentMethod);
            }

            // Create transaction record
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
     *
     * @param Payment $payment
     * @return Payment
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

                if ($newStatus === PaymentStatus::APPROVED && $payment->getPaidAt() === null) {
                    $payment->setPaidAt(new \DateTimeImmutable());
                }

                $this->entityManager->flush();

                $this->logger->info('Payment status synchronized', [
                    'payment_id' => $payment->getId(),
                    'status' => $newStatus->value,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to synchronize payment status', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $payment;
    }

    /**
     * Validate and retrieve students
     *
     * @param User $user
     * @param array<int> $studentIds
     * @return array<Student>
     * @throws \InvalidArgumentException
     */
    private function validateAndGetStudents(User $user, array $studentIds): array
    {
        if (empty($studentIds)) {
            throw new \InvalidArgumentException('At least one student must be specified');
        }

        $students = [];
        foreach ($studentIds as $studentId) {
            $student = $this->studentRepository->find($studentId);

            if (!$student) {
                throw new \InvalidArgumentException("Student with ID {$studentId} not found");
            }

            // Verify student belongs to user (parent relationship)
            if (!$student->getParents()->contains($user)) {
                throw new \InvalidArgumentException("Student with ID {$studentId} does not belong to user");
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
            'refunded' => PaymentStatus::REFUNDED,
            'charged_back' => PaymentStatus::REFUNDED,
            'in_process', 'in_mediation' => PaymentStatus::PROCESSING,
            'pending' => PaymentStatus::PENDING,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Map Mercado Pago payment method to internal enum
     */
    private function mapPaymentMethod(string $mpMethod): ?\App\Enum\PaymentMethod
    {
        return match (true) {
            str_contains($mpMethod, 'credit') => \App\Enum\PaymentMethod::CREDIT_CARD,
            str_contains($mpMethod, 'debit') => \App\Enum\PaymentMethod::DEBIT_CARD,
            str_contains($mpMethod, 'ticket') => \App\Enum\PaymentMethod::CASH,
            str_contains($mpMethod, 'bank') => \App\Enum\PaymentMethod::BANK_TRANSFER,
            default => \App\Enum\PaymentMethod::MERCADO_PAGO,
        };
    }
}
