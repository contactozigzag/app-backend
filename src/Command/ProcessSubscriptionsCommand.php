<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\SubscriptionStatus;
use App\Repository\SubscriptionRepository;
use App\Service\Payment\PaymentProcessor;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-subscriptions',
    description: 'Process due subscription payments'
)]
class ProcessSubscriptionsCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without making actual changes')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of subscriptions to process', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        $io->title('Processing Due Subscriptions');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No actual payments will be processed');
        }

        // Find subscriptions due for billing
        $subscriptions = $this->subscriptionRepository->findDueForBilling();
        $subscriptions = array_slice($subscriptions, 0, $limit);

        if ($subscriptions === []) {
            $io->success('No subscriptions due for billing');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d subscription(s) due for billing', count($subscriptions)));

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($subscriptions as $subscription) {
            $io->section(sprintf(
                'Processing Subscription #%d - User: %s',
                $subscription->getId(),
                $subscription->getUser()->getEmail()
            ));

            try {
                if ($dryRun) {
                    $io->text('Would process payment for subscription');
                    $skipped++;
                    continue;
                }

                // Create payment for subscription
                $idempotencyKey = sprintf(
                    'subscription_%d_billing_%s',
                    $subscription->getId(),
                    $subscription->getNextBillingDate()->format('Y-m-d')
                );

                $studentIds = $subscription->getStudents()->map(fn ($s): ?int => $s->getId())->toArray();

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
                $subscription->setLastPaymentAttemptAt(new DateTimeImmutable());
                $subscription->resetFailedPaymentCount();

                $this->logger->info('Subscription payment processed', [
                    'subscription_id' => $subscription->getId(),
                    'payment_id' => $payment->getId(),
                    'next_billing_date' => $subscription->getNextBillingDate()->format('Y-m-d'),
                ]);

                $io->success(sprintf(
                    'Created payment #%d for subscription #%d',
                    $payment->getId(),
                    $subscription->getId()
                ));

                $processed++;
            } catch (Exception $e) {
                $failed++;

                // Increment failed payment count
                $subscription->incrementFailedPaymentCount();
                $subscription->setLastPaymentAttemptAt(new DateTimeImmutable());

                // Mark as failed if exceeded max retries
                if ($subscription->getFailedPaymentCount() >= 3) {
                    $subscription->setStatus(SubscriptionStatus::PAYMENT_FAILED);
                    $io->error(sprintf(
                        'Subscription #%d marked as PAYMENT_FAILED after %d attempts',
                        $subscription->getId(),
                        $subscription->getFailedPaymentCount()
                    ));
                }

                $this->logger->error('Subscription payment failed', [
                    'subscription_id' => $subscription->getId(),
                    'error' => $e->getMessage(),
                    'failed_count' => $subscription->getFailedPaymentCount(),
                ]);

                $io->error(sprintf(
                    'Failed to process subscription #%d: %s',
                    $subscription->getId(),
                    $e->getMessage()
                ));
            }
        }

        // Process failed payment retries
        $io->section('Processing Failed Payment Retries');

        $retrySubscriptions = $this->subscriptionRepository->findFailedPaymentRetries();
        $retrySubscriptions = array_slice($retrySubscriptions, 0, $limit - $processed);

        if ($retrySubscriptions !== []) {
            $io->text(sprintf('Found %d subscription(s) to retry', count($retrySubscriptions)));

            foreach ($retrySubscriptions as $subscription) {
                try {
                    if ($dryRun) {
                        $io->text(sprintf('Would retry subscription #%d', $subscription->getId()));
                        continue;
                    }

                    // Retry payment
                    $idempotencyKey = sprintf(
                        'subscription_%d_retry_%d_%s',
                        $subscription->getId(),
                        $subscription->getFailedPaymentCount(),
                        date('Y-m-d-H-i-s')
                    );

                    $studentIds = $subscription->getStudents()->map(fn ($s): ?int => $s->getId())->toArray();

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

                    $subscription->setLastPaymentAttemptAt(new DateTimeImmutable());

                    $io->success(sprintf('Retry payment #%d created for subscription #%d', $payment->getId(), $subscription->getId()));
                    $processed++;
                } catch (Exception $e) {
                    $failed++;
                    $subscription->incrementFailedPaymentCount();

                    $io->error(sprintf('Retry failed for subscription #%d: %s', $subscription->getId(), $e->getMessage()));
                }
            }
        }

        // Summary
        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Found', count($subscriptions)],
                ['Processed', $processed],
                ['Failed', $failed],
                ['Skipped (Dry Run)', $skipped],
                ['Retries Attempted', count($retrySubscriptions ?? [])],
            ]
        );

        if ($failed > 0) {
            $io->warning(sprintf('%d subscription(s) failed to process', $failed));
            return Command::FAILURE;
        }

        $io->success('Subscription processing completed successfully');
        return Command::SUCCESS;
    }
}
