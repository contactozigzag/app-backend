<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PushDeviceRepository;
use App\Repository\PushTicketRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:push:cleanup',
    description: 'Delete old push tickets and deactivate inactive devices',
)]
class CleanupPushTicketsCommand extends Command
{
    public function __construct(
        private readonly PushTicketRepository $ticketRepo,
        private readonly PushDeviceRepository $deviceRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deleted = $this->ticketRepo->deleteCheckedOlderThan(new DateTimeImmutable('-7 days'));
        $deactivated = $this->deviceRepo->deactivateInactiveSince(new DateTimeImmutable('-90 days'));

        $io->success(sprintf('Deleted %d tickets, deactivated %d devices.', $deleted, $deactivated));

        return Command::SUCCESS;
    }
}
