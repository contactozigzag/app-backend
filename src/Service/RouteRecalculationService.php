<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Absence;
use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Repository\ActiveRouteStopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RouteRecalculationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveRouteStopRepository $stopRepository,
        private readonly RouteOptimizationService $optimizationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Recalculate routes affected by an absence
     *
     * @return array{affected_routes: array, recalculated: bool}
     */
    public function recalculateForAbsence(Absence $absence): array
    {
        $student = $absence->getStudent();
        $date = $absence->getDate();
        $routeType = $this->getRouteTypeFromAbsence($absence);

        $this->logger->info('Starting route recalculation for absence', [
            'student_id' => $student->getId(),
            'date' => $date->format('Y-m-d'),
            'type' => $routeType,
        ]);

        // Find active route stops for this student on this date
        $stops = $this->stopRepository->createQueryBuilder('ars')
            ->join('ars.activeRoute', 'ar')
            ->join('ar.routeTemplate', 'rt')
            ->andWhere('ars.student = :student')
            ->andWhere('ar.date = :date')
            ->andWhere('rt.type = :type')
            ->setParameter('student', $student)
            ->setParameter('date', $date)
            ->setParameter('type', $routeType)
            ->getQuery()
            ->getResult();

        if (empty($stops)) {
            $this->logger->info('No stops found for recalculation');
            return [
                'affected_routes' => [],
                'recalculated' => false,
            ];
        }

        $affectedRoutes = [];

        /** @var ActiveRouteStop $stop */
        foreach ($stops as $stop) {
            $activeRoute = $stop->getActiveRoute();

            // Mark stop as absent/skipped
            $stop->setStatus('absent');
            $stop->setNotes('Student reported absent: ' . $absence->getReason());

            // Only recalculate if route hasn't started yet
            if ($activeRoute->getStatus() === 'scheduled') {
                $this->recalculateActiveRoute($activeRoute);
                $affectedRoutes[] = $activeRoute->getId();
            }
        }

        // Mark absence as processed
        $absence->setRouteRecalculated(true);

        $this->entityManager->flush();

        $this->logger->info('Route recalculation completed', [
            'affected_routes' => $affectedRoutes,
        ]);

        return [
            'affected_routes' => $affectedRoutes,
            'recalculated' => $affectedRoutes !== [],
        ];
    }

    /**
     * Recalculate an active route by removing absent students and re-optimizing
     */
    private function recalculateActiveRoute(ActiveRoute $activeRoute): void
    {
        $stops = $this->stopRepository->findByActiveRouteOrdered($activeRoute);

        // Filter out absent/skipped stops
        $activeStops = array_filter($stops, fn (ActiveRouteStop $stop): bool => ! in_array($stop->getStatus(), ['absent', 'skipped'], true));

        if ($activeStops === []) {
            $this->logger->warning('No active stops remaining for route', [
                'route_id' => $activeRoute->getId(),
            ]);
            return;
        }

        $routeTemplate = $activeRoute->getRouteTemplate();

        $startPoint = [
            'lat' => (float) $routeTemplate->getStartLatitude(),
            'lng' => (float) $routeTemplate->getStartLongitude(),
        ];

        $endPoint = [
            'lat' => (float) $routeTemplate->getEndLatitude(),
            'lng' => (float) $routeTemplate->getEndLongitude(),
        ];

        // Prepare stops for optimization
        $optimizationStops = [];
        $stopMap = [];

        foreach ($activeStops as $stop) {
            $address = $stop->getAddress();
            $optimizationStops[] = [
                'id' => $stop->getId(),
                'lat' => (float) $address->getLatitude(),
                'lng' => (float) $address->getLongitude(),
            ];
            $stopMap[$stop->getId()] = $stop;
        }

        // Optimize route
        $result = $this->optimizationService->optimizeRoute($startPoint, $endPoint, $optimizationStops);

        if ($result === null) {
            $this->logger->error('Route optimization failed', [
                'route_id' => $activeRoute->getId(),
            ]);
            return;
        }

        // Update stop order and estimated times
        $newOrder = 0;
        $currentTime = 0;

        foreach ($result['optimized_order'] as $stopId) {
            if (isset($stopMap[$stopId])) {
                $stopMap[$stopId]->setStopOrder($newOrder++);

                // Update estimated arrival time
                foreach ($result['segments'] as $segment) {
                    if ($segment['to'] === $stopId) {
                        $currentTime += $segment['duration'];
                        $stopMap[$stopId]->setEstimatedArrivalTime($currentTime);
                        break;
                    }
                }
            }
        }

        // Update route metadata
        $activeRoute->setTotalDistance($result['total_distance']);
        $activeRoute->setTotalDuration($result['total_duration']);

        $this->logger->info('Active route recalculated', [
            'route_id' => $activeRoute->getId(),
            'new_distance' => $result['total_distance'],
            'new_duration' => $result['total_duration'],
        ]);
    }

    /**
     * Get route type from absence type
     */
    private function getRouteTypeFromAbsence(Absence $absence): ?string
    {
        return match ($absence->getType()) {
            'morning' => 'morning',
            'afternoon' => 'afternoon',
            'full_day' => null, // Will need to process both
            default => null,
        };
    }

    /**
     * Process all pending absence recalculations
     */
    public function processPendingRecalculations(): array
    {
        $absenceRepository = $this->entityManager->getRepository(Absence::class);
        $pendingAbsences = $absenceRepository->findPendingRecalculation();

        $results = [];

        foreach ($pendingAbsences as $absence) {
            try {
                $result = $this->recalculateForAbsence($absence);
                $results[] = [
                    'absence_id' => $absence->getId(),
                    'student_id' => $absence->getStudent()->getId(),
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                $this->logger->error('Failed to process absence', [
                    'absence_id' => $absence->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
