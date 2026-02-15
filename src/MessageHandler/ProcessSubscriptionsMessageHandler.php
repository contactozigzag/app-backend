<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\SubscriptionStatus;
use App\Message\ProcessSubscriptionsMessage;
use App\Repository\SubscriptionRepository;
use App\Service\Payment\PaymentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessSubscriptionsMessageHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessSubscriptionsMessage $message): void
    {
        $this->logger->info('Starting scheduled subscription processing', [
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

                    $studentIds = $subscription->getStudents()->map(fn($s) => $s->getId())->toArray();

                    $payment = $this->paymentProcessor->createPayment(
                        user: $subscription->getUser(),
                        studentIds: $studentIds,
                        amount: $subscription->getAmount(),
                        description: sprintf(
                            'Subscription billing - %s (%s)',
                            $subscription->getPlanType(),
                            $subscription->getNextBillingDate()->format('Y-m-d')
                        ),
                        idempotencyKey: $idempotencyKey,
                        currency: $subscription->getCurrency()
                    );

                    // Update subscription
                    $subscription->setNextBillingDate($subscription->calculateNextBillingDate());
                    $subscription->setLastPaymentAttemptAt(new \DateTimeImmutable());
                    $subscription->resetFailedPaymentCount();

                    $this->entityManager->flush();

                    $this->logger->info('Subscription payment processed', [
                        'subscription_id' => $subscription->getId(),
                        'payment_id' => $payment->getId(),
                        'next_billing_date' => $subscription->getNextBillingDate()->format('Y-m-d'),
                    ]);

                    $processed++;
                } catch (\Exception $e) {
                    $failed++;

                    // Increment failed payment count
                    $subscription->incrementFailedPaymentCount();
                    $subscription->setLastPaymentAttemptAt(new \DateTimeImmutable());

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

                        $studentIds = $subscription->getStudents()->map(fn($s) => $s->getId())->toArray();

                        $payment = $this->paymentProcessor->createPayment(
                            user: $subscription->getUser(),
                            studentIds: $studentIds,
                            amount: $subscription->getAmount(),
                            description: sprintf(
                                'Subscription billing retry #%d - %s',
                                $subscription->getFailedPaymentCount() + 1,
                                $subscription->getPlanType()
                            ),
                            idempotencyKey: $idempotencyKey,
                            currency: $subscription->getCurrency()
                        );

                        $subscription->setLastPaymentAttemptAt(new \DateTimeImmutable());
                        $this->entityManager->flush();

                        $this->logger->info('Retry payment processed', [
                            'subscription_id' => $subscription->getId(),
                            'payment_id' => $payment->getId(),
                            'retry_attempt' => $subscription->getFailedPaymentCount() + 1,
                        ]);

                        $processed++;
                    } catch (\Exception $e) {
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

            $this->logger->info('Subscription processing completed', [
                'processed' => $processed,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            $this->logger->critical('Subscription processing failed with critical error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
