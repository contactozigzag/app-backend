<?php

declare(strict_types=1);

namespace App\Controller\Admin\Page;

use App\Entity\ActiveRoute;
use App\Entity\School;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use App\Service\DriverLocationCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LiveOperationsController extends AbstractController
{
    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly DriverLocationCacheService $locationCache,
        #[Autowire(env: 'MERCURE_PUBLIC_URL')]
        private readonly string $mercurePublicUrl,
    ) {
    }

    #[Route('/admin/live-operations', name: 'admin_live_operations')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/page/live_operations.html.twig', [
            'mercurePublicUrl' => $this->mercurePublicUrl,
        ]);
    }

    #[Route('/admin/api/active-drivers', name: 'admin_api_active_drivers')]
    public function activeDrivers(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $routes = $this->getInProgressRoutes();
        $result = [];

        foreach ($routes as $route) {
            $driver = $route->getDriver();
            if ($driver === null) {
                continue;
            }

            $driverId = $driver->getId();
            if ($driverId === null) {
                continue;
            }

            $user = $driver->getUser();
            $location = $this->locationCache->getLocation($driverId);
            $lastSeen = $this->locationCache->getLastSeen($driverId);

            $lat = $location !== null ? $location['lat'] : (float) ($route->getCurrentLatitude() ?? '0');
            $lng = $location !== null ? $location['lng'] : (float) ($route->getCurrentLongitude() ?? '0');

            $result[] = [
                'driverId' => $driverId,
                'userId' => $user?->getId(),
                'name' => $user !== null ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
                'nickname' => $driver->getNickname() ?? '',
                'latitude' => $lat,
                'longitude' => $lng,
                'activeRouteId' => $route->getId(),
                'routeName' => $route->getRouteTemplate()?->getName() ?? 'Route #' . $route->getId(),
                'status' => $route->getStatus(),
                'lastUpdate' => $lastSeen?->format('c'),
                'speed' => $location['speed'] ?? null,
                'heading' => $location['heading'] ?? null,
                'hasAlert' => false,
            ];
        }

        return $this->json($result);
    }

    #[Route('/admin/api/active-routes/list', name: 'admin_api_active_routes_list')]
    public function activeRoutesList(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $routes = $this->getInProgressRoutes();
        $routeData = [];

        foreach ($routes as $route) {
            $stops = $route->getStops();
            $totalStops = $stops->count();
            $completedStops = 0;

            foreach ($stops as $stop) {
                if (in_array($stop->getStatus(), ['picked_up', 'dropped_off', 'skipped', 'absent'], true)) {
                    ++$completedStops;
                }
            }

            $progressPct = $totalStops > 0 ? (int) round($completedStops / $totalStops * 100) : 0;
            $driver = $route->getDriver();
            $user = $driver?->getUser();

            $routeData[] = [
                'id' => $route->getId(),
                'name' => $route->getRouteTemplate()?->getName() ?? 'Route #' . $route->getId(),
                'driverName' => $user !== null ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
                'status' => $route->getStatus(),
                'startedAt' => $route->getStartedAt()?->format('H:i'),
                'progressPct' => $progressPct,
                'completedStops' => $completedStops,
                'totalStops' => $totalStops,
            ];
        }

        return $this->render('admin/live_operations/route_list.html.twig', [
            'routes' => $routeData,
        ]);
    }

    #[Route('/admin/api/route/{id}/detail-panel', name: 'admin_api_route_detail_panel')]
    public function routeDetailPanel(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        /** @var ActiveRoute|null $route */
        $route = $this->activeRouteRepository->find($id);

        if ($route === null) {
            throw $this->createNotFoundException('Route not found.');
        }

        $this->assertSchoolAccess($route);

        $driver = $route->getDriver();
        $user = $driver?->getUser();
        $vehicles = $driver?->getVehicles();
        $vehicle = $vehicles !== null && ! $vehicles->isEmpty() ? $vehicles->first() : null;

        $stops = $route->getStops();
        $totalStops = $stops->count();
        $completedStops = 0;

        foreach ($stops as $stop) {
            if (in_array($stop->getStatus(), ['picked_up', 'dropped_off', 'skipped', 'absent'], true)) {
                ++$completedStops;
            }
        }

        $progressPct = $totalStops > 0 ? (int) round($completedStops / $totalStops * 100) : 0;

        return $this->render('admin/live_operations/route_detail.html.twig', [
            'route' => $route,
            'driverName' => $user !== null ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
            'driverPhone' => $user?->getPhoneNumber(),
            'vehiclePlate' => $vehicle?->getLicensePlate(),
            'stops' => $stops,
            'progressPct' => $progressPct,
            'completedStops' => $completedStops,
            'totalStops' => $totalStops,
        ]);
    }

    /**
     * @return ActiveRoute[]
     */
    private function getInProgressRoutes(): array
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $school = $currentUser->getSchool();

        if ($school !== null) {
            return $this->activeRouteRepository->findInProgressBySchool($school);
        }

        return $this->activeRouteRepository->findInProgress();
    }

    private function assertSchoolAccess(ActiveRoute $route): void
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $userSchool = $currentUser->getSchool();

        if ($userSchool === null) {
            return;
        }

        $routeSchool = $route->getRouteTemplate()?->getSchool();

        if (! $routeSchool instanceof School || $routeSchool->getId() !== $userSchool->getId()) {
            throw $this->createAccessDeniedException('Route belongs to a different school.');
        }
    }
}
