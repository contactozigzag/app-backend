<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\LocationUpdateRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes location_updates rows older than a configurable number of days.
 *
 * GPS history accumulates fast (one row per 3-10 seconds per active route).
 * Run this command daily via cron or Symfony Scheduler to keep the table size
 * manageable while retaining enough history for performance reports.
 *
 * Usage:
 *   php bin/console app:tracking:prune-history          # prune > 30 days (default)
 *   php bin/console app:tracking:prune-history --days=7 # prune > 7 days
 *   php bin/console app:tracking:prune-history --dry-run
 */
#[AsCommand(
    name: 'app:tracking:prune-history',
    description: 'Delete location update records older than a configurable number of days',
)]
class PruneLocationHistoryCommand extends Command
{
    private const int DEFAULT_DAYS = 30;

    public function __construct(
        private readonly LocationUpdateRepository $locationUpdateRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Retain records from the last N days; older rows are deleted',
                self::DEFAULT_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview how many rows would be deleted without actually deleting them',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $daysOption = $input->getOption('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : self::DEFAULT_DAYS;

        if ($days < 1) {
            $io->error('--days must be a positive integer.');
            return Command::FAILURE;
        }

        $cutoff = new DateTimeImmutable(sprintf('-%d days', $days));

        $io->comment(sprintf(
            'Pruning location_updates older than %d days (before %s)…',
            $days,
            $cutoff->format('Y-m-d H:i:s'),
        ));

        if ($input->getOption('dry-run')) {
            // Count without deleting
            $count = $this->locationUpdateRepository->countOlderThan($cutoff);
            $io->note(sprintf('[dry-run] %d rows would be deleted.', $count));
            return Command::SUCCESS;
        }

        $deleted = $this->locationUpdateRepository->deleteOlderThan($cutoff);

        $io->success(sprintf('Pruned %d location update records older than %d days.', $deleted, $days));

        return Command::SUCCESS;
    }
}
