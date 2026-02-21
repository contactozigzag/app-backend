<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChildStatusDto;
use App\Dto\ParentDashboardDto;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use App\Repository\ActiveRouteStopRepository;
use App\Repository\AttendanceRepository;
use App\Repository\LocationUpdateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted('ROLE_PARENT')]
class ParentDashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly ActiveRouteStopRepository $activeRouteStopRepository,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly LocationUpdateRepository $locationUpdateRepository,
    ) {
    }

    #[Route('/api/parent/dashboard', name: 'parent_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $children = [];
        $activeRoutes = [];
        $todayAttendance = [];
        $upcomingRoutes = [];

        $today = new \DateTimeImmutable('today');

        // Get all children of the parent
        foreach ($user->getStudents() as $student) {
            // Find active route stops for this student today
            $activeStops = $this->activeRouteStopRepository->findActiveStopsByStudentAndDate(
                $student,
                $today
            );

            $childStatus = null;
            $activeRouteId = null;
            $routeStatus = null;
            $busLocation = null;
            $estimatedArrival = null;
            $lastUpdate = null;

            foreach ($activeStops as $stop) {
                $activeRoute = $stop->getActiveRoute();
                $activeRouteId = $activeRoute->getId();
                $routeStatus = $activeRoute->getStatus();
                $childStatus = $stop->getStatus();

                // Get latest bus location
                if ($activeRoute->getCurrentLatitude() && $activeRoute->getCurrentLongitude()) {
                    $busLocation = [
                        'latitude' => (float) $activeRoute->getCurrentLatitude(),
                        'longitude' => (float) $activeRoute->getCurrentLongitude(),
                    ];

                    // Get the latest location update timestamp
                    $latestLocation = $this->locationUpdateRepository->findLatestByActiveRoute($activeRoute);
                    if ($latestLocation instanceof \App\Entity\LocationUpdate) {
                        $lastUpdate = $latestLocation->getTimestamp()->format('c');
                    }
                }

                // Calculate estimated arrival
                if ($stop->getEstimatedArrivalTime() && $activeRoute->getStartedAt()) {
                    $estimatedArrivalTime = $activeRoute->getStartedAt()->modify(
                        '+' . $stop->getEstimatedArrivalTime() . ' seconds'
                    );
                    $estimatedArrival = $estimatedArrivalTime->format('c');
                }

                // Add to active routes collection
                $activeRoutes[] = [
                    'id' => $activeRoute->getId(),
                    'status' => $activeRoute->getStatus(),
                    'studentId' => $student->getId(),
                    'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'stopStatus' => $stop->getStatus(),
                    'stopOrder' => $stop->getStopOrder(),
                    'address' => [
                        'name' => $stop->getAddress()->getName(),
                        'streetAddress' => $stop->getAddress()->getStreetAddress(),
                    ],
                    'estimatedArrival' => $estimatedArrival,
                    'currentLocation' => $busLocation,
                ];
            }

            // Get today's attendance for this child
            $attendance = $this->attendanceRepository->findByStudentAndDate($student, $today);
            foreach ($attendance as $att) {
                $todayAttendance[] = [
                    'studentId' => $student->getId(),
                    'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'status' => $att->getStatus(),
                    'pickedUpAt' => $att->getPickedUpAt()?->format('c'),
                    'droppedOffAt' => $att->getDroppedOffAt()?->format('c'),
                ];
            }

            $children[] = new ChildStatusDto(
                studentId: $student->getId(),
                firstName: $student->getFirstName(),
                lastName: $student->getLastName(),
                currentStatus: $childStatus,
                activeRouteId: $activeRouteId,
                routeStatus: $routeStatus,
                busLocation: $busLocation,
                estimatedArrival: $estimatedArrival,
                lastUpdate: $lastUpdate,
            );
        }

        // Get upcoming routes (next 7 days)
        $nextWeek = $today->modify('+7 days');
        $upcomingActiveRoutes = $this->activeRouteRepository->findUpcomingByParent(
            $user,
            $today,
            $nextWeek
        );

        foreach ($upcomingActiveRoutes as $route) {
            $upcomingRoutes[] = [
                'id' => $route->getId(),
                'date' => $route->getDate()->format('Y-m-d'),
                'status' => $route->getStatus(),
                'driverName' => $route->getDriver()->getUser()->getFirstName() . ' ' .
                               $route->getDriver()->getUser()->getLastName(),
            ];
        }

        $dashboard = new ParentDashboardDto(
            children: array_map(fn (ChildStatusDto $child): array => [
                'studentId' => $child->studentId,
                'firstName' => $child->firstName,
                'lastName' => $child->lastName,
                'currentStatus' => $child->currentStatus,
                'activeRouteId' => $child->activeRouteId,
                'routeStatus' => $child->routeStatus,
                'busLocation' => $child->busLocation,
                'estimatedArrival' => $child->estimatedArrival,
                'lastUpdate' => $child->lastUpdate,
            ], $children),
            activeRoutes: $activeRoutes,
            todayAttendance: $todayAttendance,
            upcomingRoutes: $upcomingRoutes,
        );

        return $this->json($dashboard);
    }
}
