<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\SubscriptionStatus;
use App\Message\ProcessSubscriptionsMessage;
use App\Repository\SubscriptionRepository;
use App\Service\Payment\DriverRateCalculator;
use App\Service\Payment\PaymentProcessor;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessSubscriptionsMessageHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly DriverRateCalculator $driverRateCalculator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessSubscriptionsMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'limit' => $message->getLimit(),
            'process_retries' => $message->shouldProcessRetries(),
        ]);

        $processed = 0;
        $failed = 0;

        try {
            // Process due subscriptions
            $subscriptions = $this->subscriptionRepository->findDueForBilling();
            $subscriptions = array_slice($subscriptions, 0, $message->getLimit());

            $this->logger->info('Found subscriptions due for billing', [
                'count' => count($subscriptions),
            ]);

            foreach ($subscriptions as $subscription) {
                try {
                    // Create payment for subscription
                    $idempotencyKey = sprintf(
                        'subscription_%d_billing_%s',
                        $subscription->getId(),
                        $subscription->getNextBillingDate()->format('Y-m-d')
                    );

                    $studentIds = $subscription->getStudents()->map(fn ($s): ?int => $s->getId())->toArray();

                    $calculatedRate = $this->driverRateCalculator->calculateAmount(
                        $subscription->getDriver(),
                        $subscription->getRoute(),
                        count($studentIds),
                    );

                    $payment = $this->paymentProcessor->createPayment(
                        user: $subscription->getUser(),
                        studentIds: $studentIds,
                        amount: $calculatedRate->amount,
                        description: sprintf(
                            'Subscription billing - %s (%s)',
                            $subscription->getPlanType(),
                            $subscription->getNextBillingDate()->format('Y-m-d'),
                        ),
                        idempotencyKey: $idempotencyKey,
                        currency: $calculatedRate->currency,
                        driver: $subscription->getDriver(),
                        rateSnapshot: $calculatedRate->rateSnapshot,
                    );

                    // Update subscription
                    $subscription->setNextBillingDate($subscription->calculateNextBillingDate());
                    $subscription->setLastPaymentAttemptAt(new DateTimeImmutable());
                    $subscription->resetFailedPaymentCount();

                    $this->entityManager->flush();

                    $this->logger->info('Subscription payment processed', [
                        'subscription_id' => $subscription->getId(),
                        'payment_id' => $payment->getId(),
                        'next_billing_date' => $subscription->getNextBillingDate()->format('Y-m-d'),
                    ]);

                    $processed++;
                } catch (Exception $e) {
                    $failed++;

                    // Increment failed payment count
                    $subscription->incrementFailedPaymentCount();
                    $subscription->setLastPaymentAttemptAt(new DateTimeImmutable());

                    // Mark as failed if exceeded max retries
                    if ($subscription->getFailedPaymentCount() >= 3) {
                        $subscription->setStatus(SubscriptionStatus::PAYMENT_FAILED);
                        $this->logger->error('Subscription marked as PAYMENT_FAILED', [
                            'subscription_id' => $subscription->getId(),
                            'failed_attempts' => $subscription->getFailedPaymentCount(),
                        ]);
                    }

                    $this->entityManager->flush();

                    $this->logger->error('Subscription payment failed', [
                        'subscription_id' => $subscription->getId(),
                        'error' => $e->getMessage(),
                        'failed_count' => $subscription->getFailedPaymentCount(),
                    ]);
                }
            }

            // Process failed payment retries if enabled
            if ($message->shouldProcessRetries()) {
                $retrySubscriptions = $this->subscriptionRepository->findFailedPaymentRetries();
                $retrySubscriptions = array_slice($retrySubscriptions, 0, $message->getLimit() - $processed);

                $this->logger->info('Found subscriptions for payment retry', [
                    'count' => count($retrySubscriptions),
                ]);

                foreach ($retrySubscriptions as $subscription) {
                    try {
                        // Retry payment
                        $idempotencyKey = sprintf(
                            'subscription_%d_retry_%d_%s',
                            $subscription->getId(),
                            $subscription->getFailedPaymentCount(),
                            date('Y-m-d-H-i-s')
                        );

                        $studentIds = $subscription->getStudents()->map(fn ($s): ?int => $s->getId())->toArray();

                        $calculatedRate = $this->driverRateCalculator->calculateAmount(
                            $subscription->getDriver(),
                            $subscription->getRoute(),
                            count($studentIds),
                        );

                        $payment = $this->paymentProcessor->createPayment(
                            user: $subscription->getUser(),
                            studentIds: $studentIds,
                            amount: $calculatedRate->amount,
                            description: sprintf(
                                'Subscription billing retry #%d - %s',
                                $subscription->getFailedPaymentCount() + 1,
                                $subscription->getPlanType(),
                            ),
                            idempotencyKey: $idempotencyKey,
                            currency: $calculatedRate->currency,
                            driver: $subscription->getDriver(),
                            rateSnapshot: $calculatedRate->rateSnapshot,
                        );

                        $subscription->setLastPaymentAttemptAt(new DateTimeImmutable());
                        $this->entityManager->flush();

                        $this->logger->info('Retry payment processed', [
                            'subscription_id' => $subscription->getId(),
                            'payment_id' => $payment->getId(),
                            'retry_attempt' => $subscription->getFailedPaymentCount() + 1,
                        ]);

                        $processed++;
                    } catch (Exception $e) {
                        $failed++;
                        $subscription->incrementFailedPaymentCount();
                        $this->entityManager->flush();

                        $this->logger->error('Retry payment failed', [
                            'subscription_id' => $subscription->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $elapsed = (int) ((microtime(true) - $startTime) * 1000);

            $this->logger->info('Handler completed', [
                'handler' => self::class,
                'processed' => $processed,
                'failed' => $failed,
                'duration_ms' => $elapsed,
            ]);
        } catch (Exception $exception) {
            $elapsed = (int) ((microtime(true) - $startTime) * 1000);

            $this->logger->critical('Handler failed', [
                'handler' => self::class,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'duration_ms' => $elapsed,
            ]);

            throw $exception;
        }
    }
}
