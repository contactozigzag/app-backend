<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Event\Payment\PaymentApprovedEvent;
use App\Event\Payment\PaymentFailedEvent;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:payment:sync',
    description: 'Sync payment status from Mercado Pago and trigger the full event flow'
)]
class SyncPaymentStatusCommand extends Command
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('payment-id', InputArgument::REQUIRED, 'Internal payment ID')
            ->addOption('provider-id', 'p', InputOption::VALUE_REQUIRED, 'Mercado Pago payment ID (required if not already set on the payment)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $paymentId = (int) $input->getArgument('payment-id');
        $providerIdOption = $input->getOption('provider-id');
        $dryRun = $input->getOption('dry-run');

        $payment = $this->paymentRepository->find($paymentId);

        if (! $payment instanceof Payment) {
            $io->error(sprintf('Payment #%d not found.', $paymentId));

            return Command::FAILURE;
        }

        $providerId = is_string($providerIdOption) ? $providerIdOption : $payment->getPaymentProviderId();

        if ($providerId === null || $providerId === '') {
            $io->error('No Mercado Pago payment ID available. Use --provider-id to specify one.');

            return Command::FAILURE;
        }

        $oldStatus = $payment->getStatus();

        $io->section('Payment details');
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', (string) $payment->getId()],
                ['Preference ID', $payment->getPreferenceId() ?? '—'],
                ['Provider ID', $payment->getPaymentProviderId() ?? '—'],
                ['Status', $oldStatus->value],
                ['Amount', $payment->getAmount() . ' ' . $payment->getCurrency()],
                ['Created', $payment->getCreatedAt()?->format('Y-m-d H:i:s') ?? '—'],
            ],
        );

        $io->info(sprintf('Fetching status from Mercado Pago for provider ID: %s', $providerId));

        if ($dryRun) {
            $io->warning('Dry run — no changes will be made.');

            return Command::SUCCESS;
        }

        try {
            $payment = $this->paymentProcessor->updatePaymentFromWebhook(
                $payment,
                $providerId,
                ['source' => 'cli', 'command' => 'app:payment:sync'],
            );
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to sync: %s', $e->getMessage()));
            $this->logger->error('app:payment:sync failed', [
                'payment_id' => $paymentId,
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $newStatus = $payment->getStatus();

        if ($oldStatus === $newStatus) {
            $io->success(sprintf('Status unchanged: %s', $newStatus->value));

            return Command::SUCCESS;
        }

        $io->info(sprintf('Status changed: %s → %s', $oldStatus->value, $newStatus->value));

        // Dispatch events (same logic as ProcessWebhookMessageHandler)
        $this->dispatchStatusEvent($payment, $newStatus);

        $io->success(sprintf('Payment #%d synced and events dispatched. New status: %s', $paymentId, $newStatus->value));

        return Command::SUCCESS;
    }

    private function dispatchStatusEvent(Payment $payment, PaymentStatus $newStatus): void
    {
        match ($newStatus) {
            PaymentStatus::APPROVED => $this->eventDispatcher->dispatch(
                new PaymentApprovedEvent($payment),
                PaymentApprovedEvent::NAME,
            ),
            PaymentStatus::REJECTED,
            PaymentStatus::CANCELLED => $this->eventDispatcher->dispatch(
                new PaymentFailedEvent($payment),
                PaymentFailedEvent::NAME,
            ),
            default => null,
        };
    }
}
