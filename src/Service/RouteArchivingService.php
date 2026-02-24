<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use DateTimeImmutable;
use Exception;
use App\Entity\ActiveRoute;
use App\Entity\ArchivedRoute;
use App\Repository\ActiveRouteRepository;
use App\Repository\AttendanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RouteArchivingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Archive a completed route
     */
    public function archiveRoute(ActiveRoute $route): ArchivedRoute
    {
        if ($route->getStatus() !== 'completed') {
            throw new InvalidArgumentException('Only completed routes can be archived');
        }

        $archivedRoute = new ArchivedRoute();

        // Basic route information
        $archivedRoute->setOriginalActiveRouteId($route->getId());
        $archivedRoute->setSchool($route->getRouteTemplate()->getSchool());
        $archivedRoute->setRouteName($route->getRouteTemplate()->getName());
        $archivedRoute->setRouteType($route->getRouteTemplate()->getType());
        $archivedRoute->setDriverName(
            $route->getDriver()->getUser()->getFirstName() . ' ' .
            $route->getDriver()->getUser()->getLastName()
        );
        $archivedRoute->setDate($route->getDate());
        $archivedRoute->setStatus($route->getStatus());
        $archivedRoute->setStartedAt($route->getStartedAt());
        $archivedRoute->setCompletedAt($route->getCompletedAt());
        $archivedRoute->setTotalDistance($route->getTotalDistance());
        $archivedRoute->setTotalDuration($route->getTotalDuration());

        // Calculate actual duration
        if ($route->getStartedAt() && $route->getCompletedAt()) {
            $actualDuration = $route->getCompletedAt()->getTimestamp() -
                            $route->getStartedAt()->getTimestamp();
            $archivedRoute->setActualDuration($actualDuration);
        }

        // Calculate stop statistics
        $stopStats = $this->calculateStopStatistics($route);
        $archivedRoute->setTotalStops($stopStats['total']);
        $archivedRoute->setCompletedStops($stopStats['completed']);
        $archivedRoute->setSkippedStops($stopStats['skipped']);

        // Calculate attendance statistics
        $attendanceStats = $this->calculateAttendanceStatistics($route);
        $archivedRoute->setStudentsPickedUp($attendanceStats['pickedUp']);
        $archivedRoute->setStudentsDroppedOff($attendanceStats['droppedOff']);
        $archivedRoute->setNoShows($attendanceStats['noShows']);

        // Calculate on-time percentage
        $onTimePercentage = $this->calculateOnTimePercentage($route);
        $archivedRoute->setOnTimePercentage((string) $onTimePercentage);

        // Serialize stop data
        $stopData = $this->serializeStopData($route);
        $archivedRoute->setStopData($stopData);

        // Calculate performance metrics
        $performanceMetrics = $this->calculatePerformanceMetrics($route, $stopStats, $attendanceStats);
        $archivedRoute->setPerformanceMetrics($performanceMetrics);

        $this->entityManager->persist($archivedRoute);
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            'Archived route #%d (date: %s, school: %s)',
            $route->getId(),
            $route->getDate()->format('Y-m-d'),
            $route->getRouteTemplate()->getSchool()->getName()
        ));

        return $archivedRoute;
    }

    /**
     * Archive all completed routes older than specified days
     */
    public function archiveCompletedRoutes(int $olderThanDays = 7): int
    {
        $cutoffDate = new DateTimeImmutable(sprintf('-%d days', $olderThanDays));

        $completedRoutes = $this->activeRouteRepository->createQueryBuilder('ar')
            ->andWhere('ar.status = :status')
            ->andWhere('ar.date < :cutoffDate')
            ->setParameter('status', 'completed')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getResult();

        $archivedCount = 0;

        foreach ($completedRoutes as $route) {
            try {
                $this->archiveRoute($route);

                // Optionally delete the active route after archiving
                // $this->entityManager->remove($route);

                $archivedCount++;
            } catch (Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to archive route #%d: %s',
                    $route->getId(),
                    $e->getMessage()
                ));
            }
        }

        if ($archivedCount > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info(sprintf('Archived %d completed routes', $archivedCount));

        return $archivedCount;
    }

    private function calculateStopStatistics(ActiveRoute $route): array
    {
        $total = 0;
        $completed = 0;
        $skipped = 0;

        foreach ($route->getStops() as $stop) {
            $total++;
            if (in_array($stop->getStatus(), ['picked_up', 'dropped_off'], true)) {
                $completed++;
            } elseif ($stop->getStatus() === 'skipped') {
                $skipped++;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'skipped' => $skipped,
        ];
    }

    private function calculateAttendanceStatistics(ActiveRoute $route): array
    {
        $pickedUp = 0;
        $droppedOff = 0;
        $noShows = 0;

        $attendanceRecords = $this->attendanceRepository->createQueryBuilder('a')
            ->join('a.activeRouteStop', 'ars')
            ->andWhere('ars.activeRoute = :route')
            ->setParameter('route', $route)
            ->getQuery()
            ->getResult();

        foreach ($attendanceRecords as $attendance) {
            switch ($attendance->getStatus()) {
                case 'picked_up':
                    $pickedUp++;
                    break;
                case 'dropped_off':
                    $droppedOff++;
                    break;
                case 'no_show':
                    $noShows++;
                    break;
            }
        }

        return [
            'pickedUp' => $pickedUp,
            'droppedOff' => $droppedOff,
            'noShows' => $noShows,
        ];
    }

    private function calculateOnTimePercentage(ActiveRoute $route): float
    {
        $onTimeStops = 0;
        $totalStops = 0;

        foreach ($route->getStops() as $stop) {
            if (! $stop->getEstimatedArrivalTime()) {
                continue;
            }

            if (! $stop->getArrivedAt()) {
                continue;
            }

            $totalStops++;
            $estimatedTime = $route->getStartedAt()->modify('+' . $stop->getEstimatedArrivalTime() . ' seconds');
            $actualTime = $stop->getArrivedAt();

            // Consider on-time if within 5 minutes of estimate
            $diff = abs($actualTime->getTimestamp() - $estimatedTime->getTimestamp());
            if ($diff <= 300) { // 5 minutes
                $onTimeStops++;
            }
        }

        return $totalStops > 0 ? round(($onTimeStops / $totalStops) * 100, 2) : 0;
    }

    private function serializeStopData(ActiveRoute $route): array
    {
        $stopData = [];

        foreach ($route->getStops() as $stop) {
            $stopData[] = [
                'stop_order' => $stop->getStopOrder(),
                'student_name' => $stop->getStudent()->getFirstName() . ' ' . $stop->getStudent()->getLastName(),
                'address' => $stop->getAddress()->getStreetAddress(),
                'status' => $stop->getStatus(),
                'arrived_at' => $stop->getArrivedAt()?->format('c'),
                'picked_up_at' => $stop->getPickedUpAt()?->format('c'),
                'dropped_off_at' => $stop->getDroppedOffAt()?->format('c'),
            ];
        }

        return $stopData;
    }

    private function calculatePerformanceMetrics(ActiveRoute $route, array $stopStats, array $attendanceStats): array
    {
        $metrics = [];

        // Completion rate
        $metrics['completion_rate'] = $stopStats['total'] > 0
            ? round(($stopStats['completed'] / $stopStats['total']) * 100, 2)
            : 0;

        // Efficiency score (distance vs expected)
        if ($route->getTotalDistance() && $route->getRouteTemplate()->getEstimatedDistance()) {
            $metrics['distance_efficiency'] = round(
                ($route->getRouteTemplate()->getEstimatedDistance() / $route->getTotalDistance()) * 100,
                2
            );
        }

        // Time efficiency (actual vs estimated)
        if ($route->getStartedAt() && $route->getCompletedAt() && $route->getTotalDuration()) {
            $actualDuration = $route->getCompletedAt()->getTimestamp() - $route->getStartedAt()->getTimestamp();
            $metrics['time_efficiency'] = round(
                ($route->getTotalDuration() / $actualDuration) * 100,
                2
            );
        }

        // No-show rate
        $totalStudents = $attendanceStats['pickedUp'] + $attendanceStats['droppedOff'] + $attendanceStats['noShows'];
        $metrics['no_show_rate'] = $totalStudents > 0
            ? round(($attendanceStats['noShows'] / $totalStudents) * 100, 2)
            : 0;

        return $metrics;
    }
}
