<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use App\Repository\DriverRepository;
use App\Repository\SchoolRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

class DashboardStatsService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly StudentRepository $studentRepository,
        private readonly DriverRepository $driverRepository,
        private readonly SchoolRepository $schoolRepository,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly DriverAlertRepository $driverAlertRepository,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function getPlatformKpis(): array
    {
        return [
            'schools' => $this->schoolRepository->count([]),
            'users' => $this->userRepository->count([]),
            'students' => $this->studentRepository->count([]),
            'drivers' => $this->driverRepository->count([]),
            'activeRoutes' => $this->activeRouteRepository->countInProgressToday(),
            'openAlerts' => $this->driverAlertRepository->countOpenAlerts(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveRoutesNow(): array
    {
        $routes = $this->activeRouteRepository->findInProgress();
        $result = [];

        foreach ($routes as $route) {
            $driver = $route->getDriver();
            $user = $driver?->getUser();
            $stops = $route->getStops();
            $totalStops = $stops->count();
            $completedStops = 0;

            foreach ($stops as $stop) {
                if (in_array($stop->getStatus(), ['picked_up', 'dropped_off', 'skipped', 'absent'], true)) {
                    ++$completedStops;
                }
            }

            $progressPct = $totalStops > 0 ? (int) round($completedStops / $totalStops * 100) : 0;

            $result[] = [
                'id' => $route->getId(),
                'status' => $route->getStatus(),
                'driverName' => $user ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
                'driverNickname' => $driver?->getNickname() ?? '',
                'startedAt' => $route->getStartedAt()?->format('H:i'),
                'progressPct' => $progressPct,
                'completedStops' => $completedStops,
                'totalStops' => $totalStops,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenAlerts(): array
    {
        $alerts = $this->driverAlertRepository->findOpen(10);
        $result = [];

        foreach ($alerts as $alert) {
            $driver = $alert->getDistressedDriver();
            $user = $driver?->getUser();

            $result[] = [
                'id' => $alert->getId(),
                'alertId' => $alert->getAlertId(),
                'status' => $alert->getStatus()->value,
                'driverName' => $user ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
                'triggeredAt' => $alert->getTriggeredAt()->format('Y-m-d H:i'),
            ];
        }

        return $result;
    }

    /**
     * Returns Chart.js-compatible data for a stacked bar chart of routes by day/status.
     *
     * @return array<string, mixed>
     */
    public function getWeeklyRouteChartData(): array
    {
        $stats = $this->activeRouteRepository->findWeeklyStats();

        // Build labels: last 7 days
        $labels = [];
        $dayMap = [];
        for ($i = 6; $i >= 0; --$i) {
            $date = new DateTimeImmutable(sprintf('-%d days', $i));
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('M d');
            $dayMap[$key] = [
                'completed' => 0,
                'in_progress' => 0,
                'cancelled' => 0,
                'scheduled' => 0,
            ];
        }

        foreach ($stats as $row) {
            $dateObj = $row['date'];
            if ($dateObj instanceof DateTimeImmutable) {
                $dateKey = $dateObj->format('Y-m-d');
            } elseif (is_string($dateObj)) {
                $dateKey = substr($dateObj, 0, 10);
            } else {
                continue;
            }

            $status = is_string($row['status']) ? $row['status'] : '';
            $cnt = is_numeric($row['cnt']) ? (int) $row['cnt'] : 0;

            if (isset($dayMap[$dateKey]) && array_key_exists($status, $dayMap[$dateKey])) {
                $dayMap[$dateKey][$status] = $cnt;
            }
        }

        $completed = [];
        $inProgress = [];
        $cancelled = [];

        foreach ($dayMap as $day) {
            $completed[] = $day['completed'];
            $inProgress[] = $day['in_progress'];
            $cancelled[] = $day['cancelled'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Completed',
                    'data' => $completed,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                ],
                [
                    'label' => 'In Progress',
                    'data' => $inProgress,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
                [
                    'label' => 'Cancelled',
                    'data' => $cancelled,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
            ],
        ];
    }

    /**
     * Returns Chart.js-compatible data for a doughnut chart of alert distribution by status.
     *
     * @return array<string, mixed>
     */
    public function getAlertChartData(): array
    {
        $counts = $this->driverAlertRepository->countAllByStatus();

        return [
            'labels' => ['Pending', 'Responded', 'Resolved'],
            'datasets' => [
                [
                    'data' => [
                        $counts['PENDING'] ?? 0,
                        $counts['RESPONDED'] ?? 0,
                        $counts['RESOLVED'] ?? 0,
                    ],
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                    ],
                ],
            ],
        ];
    }

    public function getStatsAsJson(): string
    {
        return (string) json_encode($this->getPlatformKpis());
    }
}
