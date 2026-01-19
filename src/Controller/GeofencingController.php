<?php

namespace App\Controller;

use App\Repository\ActiveRouteRepository;
use App\Service\GeofencingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/geofencing', name: 'api_geofencing_')]
class GeofencingController extends AbstractController
{
    public function __construct(
        private readonly GeofencingService $geofencingService,
        private readonly ActiveRouteRepository $activeRouteRepository
    ) {
    }

    /**
     * Check geofencing for a specific active route
     */
    #[Route('/check/{routeId}', name: 'check_route', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function checkRoute(int $routeId): JsonResponse
    {
        $activeRoute = $this->activeRouteRepository->find($routeId);

        if (!$activeRoute) {
            return $this->json([
                'error' => 'Active route not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->geofencingService->checkActiveRoute($activeRoute);

        return $this->json([
            'route_id' => $routeId,
            'approaching_stops' => $result['approaching'],
            'arrived_stops' => $result['arrived'],
            'count_approaching' => count($result['approaching']),
            'count_arrived' => count($result['arrived']),
        ]);
    }

    /**
     * Check all in-progress routes
     */
    #[Route('/check-all', name: 'check_all', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function checkAllRoutes(): JsonResponse
    {
        $activeRoutes = $this->activeRouteRepository->findInProgress();

        $results = $this->geofencingService->processActiveRoutes($activeRoutes);

        $totalApproaching = 0;
        $totalArrived = 0;

        foreach ($results as $result) {
            $totalApproaching += count($result['approaching']);
            $totalArrived += count($result['arrived']);
        }

        return $this->json([
            'routes_checked' => count($activeRoutes),
            'routes_with_changes' => count($results),
            'total_approaching' => $totalApproaching,
            'total_arrived' => $totalArrived,
            'results' => $results,
        ]);
    }

    /**
     * Get distance to next stop for an active route
     */
    #[Route('/distance-to-next/{routeId}', name: 'distance_to_next', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDistanceToNext(int $routeId): JsonResponse
    {
        $activeRoute = $this->activeRouteRepository->find($routeId);

        if (!$activeRoute) {
            return $this->json([
                'error' => 'Active route not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->geofencingService->getDistanceToNextStop($activeRoute);

        if ($result === null) {
            return $this->json([
                'message' => 'No next stop or no current location available'
            ]);
        }

        return $this->json([
            'route_id' => $routeId,
            'distance_meters' => $result['distance'],
            'distance_km' => round($result['distance'] / 1000, 2),
            'stop_id' => $result['stop_id'],
            'student_name' => $result['student_name'],
        ]);
    }
}
