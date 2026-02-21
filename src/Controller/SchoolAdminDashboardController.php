<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SchoolAdminDashboardDto;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use App\Repository\AttendanceRepository;
use App\Repository\DriverRepository;
use App\Repository\LocationUpdateRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted('ROLE_SCHOOL_ADMIN')]
class SchoolAdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly DriverRepository $driverRepository,
        private readonly StudentRepository $studentRepository,
        private readonly LocationUpdateRepository $locationUpdateRepository,
    ) {
    }

    #[Route('/api/school-admin/dashboard', name: 'school_admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $school = $user->getSchool();

        if (! $school) {
            return $this->json([
                'error' => 'No school associated with this admin',
            ], 400);
        }

        $today = new \DateTimeImmutable('today');

        // Get statistics
        $statistics = $this->getStatistics($school, $today);

        // Get active routes for today
        $activeRoutes = [];
        $routes = $this->activeRouteRepository->findActiveBySchool($school, $today);

        foreach ($routes as $route) {
            $completedStops = 0;
            $totalStops = count($route->getStops());

            foreach ($route->getStops() as $stop) {
                if (in_array($stop->getStatus(), ['picked_up', 'dropped_off'], true)) {
                    $completedStops++;
                }
            }

            $activeRoutes[] = [
                'id' => $route->getId(),
                'status' => $route->getStatus(),
                'driverName' => $route->getDriver()->getUser()->getFirstName() . ' ' .
                               $route->getDriver()->getUser()->getLastName(),
                'startedAt' => $route->getStartedAt()?->format('c'),
                'completedStops' => $completedStops,
                'totalStops' => $totalStops,
                'progress' => $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0,
                'currentLocation' => $route->getCurrentLatitude() && $route->getCurrentLongitude() ? [
                    'latitude' => (float) $route->getCurrentLatitude(),
                    'longitude' => (float) $route->getCurrentLongitude(),
                ] : null,
            ];
        }

        // Get driver statuses
        $driverStatuses = [];
        $drivers = $this->driverRepository->findBySchool($school);

        foreach ($drivers as $driver) {
            $activeRoute = $this->activeRouteRepository->findActiveByDriverAndDate($driver, $today);
            $latestLocation = $this->locationUpdateRepository->findLatestByDriver($driver);

            $driverStatuses[] = [
                'id' => $driver->getId(),
                'name' => $driver->getUser()->getFirstName() . ' ' . $driver->getUser()->getLastName(),
                'email' => $driver->getUser()->getEmail(),
                'phoneNumber' => $driver->getUser()->getPhoneNumber(),
                'status' => $activeRoute instanceof \App\Entity\ActiveRoute ? $activeRoute->getStatus() : 'idle',
                'activeRouteId' => $activeRoute?->getId(),
                'lastLocationUpdate' => $latestLocation?->getTimestamp()->format('c'),
                'currentLocation' => $latestLocation instanceof \App\Entity\LocationUpdate ? [
                    'latitude' => (float) $latestLocation->getLatitude(),
                    'longitude' => (float) $latestLocation->getLongitude(),
                ] : null,
            ];
        }

        // Get recent alerts (would need an Alert entity for real implementation)
        // For now, we'll check for delays and issues
        $recentAlerts = $this->generateAlerts($routes);

        // Get today's metrics
        $todayMetrics = $this->getTodayMetrics($school, $today);

        $dashboard = new SchoolAdminDashboardDto(
            statistics: $statistics,
            activeRoutes: $activeRoutes,
            driverStatuses: $driverStatuses,
            recentAlerts: $recentAlerts,
            todayMetrics: $todayMetrics,
        );

        return $this->json($dashboard);
    }

    private function getStatistics(\App\Entity\School $school, \DateTimeImmutable $today): array
    {
        $totalStudents = $this->studentRepository->count([
            'school' => $school,
        ]);

        $allRoutes = $this->activeRouteRepository->findBySchoolAndDate($school, $today);
        $activeRoutes = array_filter($allRoutes, fn (\App\Entity\ActiveRoute $r): bool => in_array($r->getStatus(), ['scheduled', 'in_progress'], true));
        $completedRoutes = array_filter($allRoutes, fn (\App\Entity\ActiveRoute $r): bool => $r->getStatus() === 'completed');

        $totalDrivers = $this->driverRepository->countBySchool($school);
        $activeDrivers = count(array_unique(array_map(fn (\App\Entity\ActiveRoute $r): ?int => $r->getDriver()->getId(), $activeRoutes)));

        $attendanceStats = $this->attendanceRepository->getStatsByDateRange($today, $today);

        return [
            'totalStudents' => $totalStudents,
            'totalDrivers' => $totalDrivers,
            'activeDrivers' => $activeDrivers,
            'totalRoutesToday' => count($allRoutes),
            'activeRoutes' => count($activeRoutes),
            'completedRoutes' => count($completedRoutes),
            'attendanceStats' => $attendanceStats,
        ];
    }

    private function generateAlerts(array $routes): array
    {
        $alerts = [];
        $now = new \DateTimeImmutable();

        foreach ($routes as $route) {
            // Check for delays (route started but taking too long)
            if ($route->getStatus() === 'in_progress' && $route->getStartedAt()) {
                $duration = $now->getTimestamp() - $route->getStartedAt()->getTimestamp();
                $expectedDuration = $route->getTotalDuration() ?? 3600; // Default 1 hour

                if ($duration > $expectedDuration * 1.5) {
                    $alerts[] = [
                        'type' => 'delay',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Route #%d is running %d minutes behind schedule',
                            $route->getId(),
                            round(($duration - $expectedDuration) / 60)
                        ),
                        'routeId' => $route->getId(),
                        'timestamp' => $now->format('c'),
                    ];
                }
            }

            // Check for no location updates (driver might have issues)
            if ($route->getStatus() === 'in_progress') {
                $latestLocation = $this->locationUpdateRepository->findLatestByActiveRoute($route);
                if ($latestLocation instanceof \App\Entity\LocationUpdate) {
                    $timeSinceUpdate = $now->getTimestamp() - $latestLocation->getTimestamp()->getTimestamp();
                    if ($timeSinceUpdate > 300) { // 5 minutes
                        $alerts[] = [
                            'type' => 'no_location',
                            'severity' => 'error',
                            'message' => sprintf(
                                'No location update for route #%d in %d minutes',
                                $route->getId(),
                                round($timeSinceUpdate / 60)
                            ),
                            'routeId' => $route->getId(),
                            'timestamp' => $now->format('c'),
                        ];
                    }
                }
            }
        }

        return $alerts;
    }

    private function getTodayMetrics(\App\Entity\School $school, \DateTimeImmutable $today): array
    {
        $routes = $this->activeRouteRepository->findBySchoolAndDate($school, $today);

        $totalStops = 0;
        $completedStops = 0;
        $totalDistance = 0;
        $totalDuration = 0;

        foreach ($routes as $route) {
            foreach ($route->getStops() as $stop) {
                $totalStops++;
                if (in_array($stop->getStatus(), ['picked_up', 'dropped_off'], true)) {
                    $completedStops++;
                }
            }

            if ($route->getTotalDistance()) {
                $totalDistance += $route->getTotalDistance();
            }

            if ($route->getTotalDuration()) {
                $totalDuration += $route->getTotalDuration();
            }
        }

        $attendanceStats = $this->attendanceRepository->getStatsByDateRange($today, $today);

        return [
            'totalStops' => $totalStops,
            'completedStops' => $completedStops,
            'completionRate' => $totalStops > 0 ? round(($completedStops / $totalStops) * 100, 1) : 0,
            'totalDistanceKm' => round($totalDistance / 1000, 1),
            'averageDurationMinutes' => $routes !== [] ? round($totalDuration / count($routes) / 60) : 0,
            'studentsPickedUp' => $attendanceStats['picked_up'] ?? 0,
            'studentsDroppedOff' => $attendanceStats['dropped_off'] ?? 0,
            'noShows' => $attendanceStats['no_show'] ?? 0,
        ];
    }
}
