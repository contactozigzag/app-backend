<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ExpireStalePaymentsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:payment:expire-stale',
    description: 'Dispatch a message to cancel pending payments whose MP preference has expired'
)]
class ExpireStalePaymentsCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of payments to process per batch', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $batchSizeOption */
        $batchSizeOption = $input->getOption('batch-size');
        $batchSize = (int) $batchSizeOption;

        $this->messageBus->dispatch(new ExpireStalePaymentsMessage(batchSize: $batchSize));

        $io->success(sprintf('Dispatched ExpireStalePaymentsMessage (batch size: %d).', $batchSize));

        return Command::SUCCESS;
    }
}
