<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ExpireStaleActiveRoutesMessage;
use App\Repository\ActiveRouteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:active-route:expire-stale',
    description: 'Cancel non-terminal ActiveRoutes whose trip date is older than today (zombies a driver never completed)'
)]
class ExpireStaleActiveRoutesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ActiveRouteRepository $activeRouteRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of routes to process per batch', '500')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the routes that would be cancelled without dispatching the message');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $batchSizeOption */
        $batchSizeOption = $input->getOption('batch-size');
        $batchSize = (int) $batchSizeOption;

        if ((bool) $input->getOption('dry-run')) {
            $stale = $this->activeRouteRepository->findStaleNonTerminal($batchSize);

            if ($stale === []) {
                $io->success('No stale active routes found.');

                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($stale as $route) {
                $rows[] = [
                    $route->getId(),
                    $route->getStatus(),
                    $route->getDate()?->format('Y-m-d'),
                    $route->getDriver()?->getId(),
                    $route->getStartedAt()?->format('c') ?? '—',
                ];
            }

            $io->table(['id', 'status', 'date', 'driver_id', 'started_at'], $rows);
            $io->warning(sprintf('%d stale route(s) would be cancelled. Re-run without --dry-run to dispatch.', count($stale)));

            return Command::SUCCESS;
        }

        $this->messageBus->dispatch(new ExpireStaleActiveRoutesMessage(batchSize: $batchSize));

        $io->success(sprintf('Dispatched ExpireStaleActiveRoutesMessage (batch size: %d).', $batchSize));

        return Command::SUCCESS;
    }
}
