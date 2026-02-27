<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ArchivedRoute;
use App\Entity\School;
use App\Repository\ArchivedRouteRepository;
use DateTimeImmutable;

class PerformanceMetricsService
{
    public function __construct(
        private readonly ArchivedRouteRepository $archivedRouteRepository,
    ) {
    }

    /**
     * Generate comprehensive performance report for a school
     */
    public function generatePerformanceReport(
        School $school,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        // Get archived route statistics
        $routeStats = $this->archivedRouteRepository->getPerformanceStats($school, $startDate, $endDate);

        // Calculate derived metrics
        $report = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'school' => [
                'id' => $school->getId(),
                'name' => $school->getName(),
            ],
            'routes' => [
                'total_routes' => (int) $routeStats['totalRoutes'],
                'total_stops' => (int) $routeStats['totalStops'],
                'completed_stops' => (int) $routeStats['completedStops'],
                'skipped_stops' => (int) $routeStats['skippedStops'],
                'completion_rate' => $routeStats['totalStops'] > 0
                    ? round(($routeStats['completedStops'] / $routeStats['totalStops']) * 100, 2)
                    : 0,
            ],
            'students' => [
                'total_picked_up' => (int) $routeStats['studentsPickedUp'],
                'total_dropped_off' => (int) $routeStats['studentsDroppedOff'],
                'total_no_shows' => (int) $routeStats['noShows'],
                'no_show_rate' => $this->calculateNoShowRate($routeStats),
            ],
            'on_time_performance' => [
                'average_percentage' => round((float) $routeStats['avgOnTimePercentage'], 2),
                'by_day' => $this->archivedRouteRepository->getOnTimePerformanceByDay(
                    $school,
                    $startDate,
                    $endDate
                ),
            ],
            'distance' => [
                'total_km' => round(((int) $routeStats['totalDistance']) / 1000, 2),
                'average_per_route' => $routeStats['totalRoutes'] > 0
                    ? round(((int) $routeStats['totalDistance']) / (int) $routeStats['totalRoutes'] / 1000, 2)
                    : 0,
            ],
            'duration' => [
                'total_hours' => round(((int) $routeStats['totalDuration']) / 3600, 2),
                'average_minutes_per_route' => $routeStats['totalRoutes'] > 0
                    ? round(((int) $routeStats['totalDuration']) / (int) $routeStats['totalRoutes'] / 60, 2)
                    : 0,
            ],
        ];

        return $report;
    }

    /**
     * Calculate efficiency metrics
     */
    public function calculateEfficiencyMetrics(
        School $school,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        $routes = $this->archivedRouteRepository->findBySchoolAndDateRange(
            $school,
            $startDate,
            $endDate
        );

        $totalRoutes = count($routes);
        $totalDistanceEfficiency = 0;
        $totalTimeEfficiency = 0;
        $routesWithMetrics = 0;

        foreach ($routes as $route) {
            $metrics = $route->getPerformanceMetrics();
            if ($metrics) {
                if (isset($metrics['distance_efficiency'])) {
                    $totalDistanceEfficiency += $metrics['distance_efficiency'];
                    $routesWithMetrics++;
                }

                if (isset($metrics['time_efficiency'])) {
                    $totalTimeEfficiency += $metrics['time_efficiency'];
                }
            }
        }

        return [
            'total_routes' => $totalRoutes,
            'average_distance_efficiency' => $routesWithMetrics > 0
                ? round($totalDistanceEfficiency / $routesWithMetrics, 2)
                : 0,
            'average_time_efficiency' => $routesWithMetrics > 0
                ? round($totalTimeEfficiency / $routesWithMetrics, 2)
                : 0,
            'efficiency_rating' => $this->calculateEfficiencyRating(
                $routesWithMetrics > 0 ? $totalDistanceEfficiency / $routesWithMetrics : 0,
                $routesWithMetrics > 0 ? $totalTimeEfficiency / $routesWithMetrics : 0
            ),
        ];
    }

    /**
     * Get top performing routes
     */
    public function getTopPerformingRoutes(
        School $school,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $limit = 10
    ): array {
        $routes = $this->archivedRouteRepository->findBySchoolAndDateRange(
            $school,
            $startDate,
            $endDate
        );

        // Sort by on-time percentage
        usort($routes, fn ($a, $b): int => (float) $b->getOnTimePercentage() <=> (float) $a->getOnTimePercentage());

        $topRoutes = array_slice($routes, 0, $limit);

        return array_map(fn (ArchivedRoute $route): array => [
            'id' => $route->getId(),
            'route_name' => $route->getRouteName(),
            'driver_name' => $route->getDriverName(),
            'date' => $route->getDate()->format('Y-m-d'),
            'on_time_percentage' => (float) $route->getOnTimePercentage(),
            'completion_rate' => $route->getTotalStops() > 0
                ? round(($route->getCompletedStops() / $route->getTotalStops()) * 100, 2)
                : 0,
            'no_shows' => $route->getNoShows(),
        ], $topRoutes);
    }

    /**
     * Get comparative metrics (current period vs previous period)
     */
    public function getComparativeMetrics(
        School $school,
        DateTimeImmutable $currentStart,
        DateTimeImmutable $currentEnd
    ): array {
        // Calculate previous period of same length
        $periodLength = $currentEnd->diff($currentStart)->days;
        $previousEnd = $currentStart->modify('-1 day');
        $previousStart = $previousEnd->modify(sprintf('-%s days', $periodLength));

        $currentMetrics = $this->archivedRouteRepository->getPerformanceStats(
            $school,
            $currentStart,
            $currentEnd
        );

        $previousMetrics = $this->archivedRouteRepository->getPerformanceStats(
            $school,
            $previousStart,
            $previousEnd
        );

        return [
            'current_period' => [
                'start' => $currentStart->format('Y-m-d'),
                'end' => $currentEnd->format('Y-m-d'),
                'on_time_percentage' => round((float) $currentMetrics['avgOnTimePercentage'], 2),
                'total_routes' => (int) $currentMetrics['totalRoutes'],
            ],
            'previous_period' => [
                'start' => $previousStart->format('Y-m-d'),
                'end' => $previousEnd->format('Y-m-d'),
                'on_time_percentage' => round((float) $previousMetrics['avgOnTimePercentage'], 2),
                'total_routes' => (int) $previousMetrics['totalRoutes'],
            ],
            'changes' => [
                'on_time_percentage' => $this->calculatePercentageChange(
                    (float) $previousMetrics['avgOnTimePercentage'],
                    (float) $currentMetrics['avgOnTimePercentage']
                ),
                'total_routes' => $this->calculatePercentageChange(
                    (int) $previousMetrics['totalRoutes'],
                    (int) $currentMetrics['totalRoutes']
                ),
            ],
        ];
    }

    private function calculateNoShowRate(array $stats): float
    {
        $totalStudents = (int) $stats['studentsPickedUp'] +
                        (int) $stats['studentsDroppedOff'] +
                        (int) $stats['noShows'];

        return $totalStudents > 0
            ? round(((int) $stats['noShows'] / $totalStudents) * 100, 2)
            : 0;
    }

    private function calculateEfficiencyRating(float $distanceEfficiency, float $timeEfficiency): string
    {
        $avgEfficiency = ($distanceEfficiency + $timeEfficiency) / 2;
        if ($avgEfficiency >= 90) {
            return 'Excellent';
        }

        if ($avgEfficiency >= 80) {
            return 'Good';
        }

        if ($avgEfficiency >= 70) {
            return 'Fair';
        }

        return 'Needs Improvement';
    }

    private function calculatePercentageChange(float $old, float $new): float
    {
        if ($old === 0.0) {
            return $new > 0.0 ? 100.0 : 0.0;
        }

        return round((($new - $old) / $old) * 100, 2);
    }
}
