<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActiveRouteStop;
use App\Entity\Route;
use App\Repository\ActiveRouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfills ActiveRouteStop rows for ActiveRoutes that were created before
 * ActiveRouteCreateProcessor materialized stops on POST.
 *
 * Why this exists: ActiveRoutes created prior to the processor fix had no
 * rows in active_route_stops, so:
 *   - parents could not subscribe to the Mercure tracking topic (403),
 *   - the parent tracking screen showed "no children to deliver",
 *   - ProximityEvaluationHandler never fired the arriving push.
 *
 * This command walks each ActiveRoute whose stops collection is empty and
 * materializes one ActiveRouteStop per active RouteStop on its template,
 * mirroring exactly what ActiveRouteCreateProcessor does on POST.
 *
 * Usage:
 *   php bin/console app:active-route:backfill-stops              # backfill all empty
 *   php bin/console app:active-route:backfill-stops --id=42      # backfill one route
 *   php bin/console app:active-route:backfill-stops --dry-run    # preview only
 */
#[AsCommand(
    name: 'app:active-route:backfill-stops',
    description: 'Materialize ActiveRouteStop rows for ActiveRoutes whose stops were never created from their template',
)]
class BackfillActiveRouteStopsCommand extends Command
{
    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'id',
                null,
                InputOption::VALUE_REQUIRED,
                'Backfill a single ActiveRoute by ID instead of scanning every empty one',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview which routes would be backfilled without writing anything',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');
        $idOption = $input->getOption('id');

        if ($idOption !== null) {
            if (! is_numeric($idOption)) {
                $io->error('--id must be a positive integer.');
                return Command::FAILURE;
            }

            $activeRoute = $this->activeRouteRepository->find((int) $idOption);

            if ($activeRoute === null) {
                $io->error(sprintf('ActiveRoute #%d not found.', (int) $idOption));
                return Command::FAILURE;
            }

            $routes = [$activeRoute];
        } else {
            $routes = $this->activeRouteRepository->createQueryBuilder('ar')
                ->leftJoin('ar.stops', 'ars')
                ->groupBy('ar.id')
                ->having('COUNT(ars.id) = 0')
                ->getQuery()
                ->getResult();
        }

        if ($routes === []) {
            $io->success('No ActiveRoutes need backfilling — every route already has stops.');
            return Command::SUCCESS;
        }

        $io->comment(sprintf(
            '%s %d ActiveRoute(s)…',
            $dryRun ? '[dry-run] Would process' : 'Processing',
            count($routes),
        ));

        $totalStopsCreated = 0;
        $routesUpdated = 0;
        $routesSkipped = 0;

        foreach ($routes as $activeRoute) {
            // Defensive: re-check after fetch in case the QB returned a row
            // that has since been backfilled (concurrent admin action).
            if (! $activeRoute->getStops()->isEmpty()) {
                ++$routesSkipped;
                continue;
            }

            $template = $activeRoute->getRouteTemplate();

            if (! $template instanceof Route) {
                $io->writeln(sprintf(
                    '  <comment>skip</comment> ActiveRoute #%d — no route template attached',
                    $activeRoute->getId() ?? 0,
                ));
                ++$routesSkipped;
                continue;
            }

            $created = 0;

            foreach ($template->getStops() as $templateStop) {
                if (! $templateStop->getIsActive()) {
                    continue;
                }

                $student = $templateStop->getStudent();
                $address = $templateStop->getAddress();

                if ($student === null) {
                    continue;
                }

                if ($address === null) {
                    continue;
                }

                if (! $dryRun) {
                    $stop = new ActiveRouteStop();
                    $stop->setActiveRoute($activeRoute);
                    $stop->setStudent($student);
                    $stop->setAddress($address);
                    $stop->setStopOrder($templateStop->getStopOrder() ?? 0);
                    $stop->setGeofenceRadius($templateStop->getGeofenceRadius());
                    $stop->setNotes($templateStop->getNotes());
                    $stop->setEstimatedArrivalTime($templateStop->getEstimatedArrivalTime());

                    $activeRoute->addStop($stop);
                    $this->entityManager->persist($stop);
                }

                ++$created;
            }

            if ($created === 0) {
                $io->writeln(sprintf(
                    '  <comment>skip</comment> ActiveRoute #%d — template has no usable active stops',
                    $activeRoute->getId() ?? 0,
                ));
                ++$routesSkipped;
                continue;
            }

            $io->writeln(sprintf(
                '  <info>%s</info> ActiveRoute #%d → %d stop(s)%s',
                $dryRun ? 'would backfill' : 'backfilled',
                $activeRoute->getId() ?? 0,
                $created,
                $dryRun ? '' : sprintf(' (template #%d)', $template->getId() ?? 0),
            ));

            $totalStopsCreated += $created;
            ++$routesUpdated;
        }

        if (! $dryRun && $totalStopsCreated > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s %d ActiveRoute(s) with %d new stop(s). Skipped: %d.',
            $dryRun ? '[dry-run] Would update' : 'Updated',
            $routesUpdated,
            $totalStopsCreated,
            $routesSkipped,
        ));

        return Command::SUCCESS;
    }
}
