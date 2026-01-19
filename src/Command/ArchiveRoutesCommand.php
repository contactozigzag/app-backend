<?php

namespace App\Command;

use App\Service\RouteArchivingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:archive-routes',
    description: 'Archive completed routes older than specified days',
)]
class ArchiveRoutesCommand extends Command
{
    public function __construct(
        private readonly RouteArchivingService $archivingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Archive routes older than this many days',
                7
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');

        $io->info(sprintf('Archiving completed routes older than %d days...', $days));

        try {
            $archivedCount = $this->archivingService->archiveCompletedRoutes($days);

            $io->success(sprintf('Successfully archived %d routes', $archivedCount));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to archive routes: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
