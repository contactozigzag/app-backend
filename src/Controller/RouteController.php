<?php

namespace App\Controller;

use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\GoogleMapsService;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[RouteAttribute('/api/routes', name: 'api_routes_')]
class RouteController extends AbstractController
{
    public function __construct(
        private readonly RouteOptimizationService $optimizationService,
        private readonly GoogleMapsService $googleMapsService,
        private readonly RouteRepository $routeRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Optimize a route's stops
     */
    #[RouteAttribute('/{id}/optimize', name: 'optimize', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function optimizeRoute(int $id): JsonResponse
    {
        $route = $this->routeRepository->find($id);

        if (!$route) {
            return $this->json([
                'error' => 'Route not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Extract start and end points
        $startPoint = [
            'lat' => (float)$route->getStartLatitude(),
            'lng' => (float)$route->getStartLongitude(),
        ];

        $endPoint = [
            'lat' => (float)$route->getEndLatitude(),
            'lng' => (float)$route->getEndLongitude(),
        ];

        // Extract stops
        $stops = [];
        foreach ($route->getStops() as $stop) {
            if ($stop->isActive()) {
                $address = $stop->getAddress();
                $stops[] = [
                    'id' => $stop->getId(),
                    'lat' => (float)$address->getLatitude(),
                    'lng' => (float)$address->getLongitude(),
                ];
            }
        }

        // Optimize
        $result = $this->optimizationService->optimizeRoute($startPoint, $endPoint, $stops);

        if ($result === null) {
            return $this->json([
                'error' => 'Could not optimize route'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Update route stops order
        $stopEntities = $route->getStops()->toArray();
        $stopMap = [];
        foreach ($stopEntities as $stop) {
            $stopMap[$stop->getId()] = $stop;
        }

        $newOrder = 0;
        $currentTime = 0;
        foreach ($result['optimized_order'] as $stopId) {
            if (isset($stopMap[$stopId])) {
                $stopMap[$stopId]->setStopOrder($newOrder++);

                // Calculate estimated arrival time from segments
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
        $route->setEstimatedDistance($result['total_distance']);
        $route->setEstimatedDuration($result['total_duration']);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'optimized_order' => $result['optimized_order'],
            'total_distance' => $result['total_distance'],
            'total_duration' => $result['total_duration'],
            'distance_km' => round($result['total_distance'] / 1000, 2),
            'duration_minutes' => round($result['total_duration'] / 60, 2),
        ]);
    }

    /**
     * Preview route optimization without saving
     */
    #[RouteAttribute('/{id}/optimize-preview', name: 'optimize_preview', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function previewOptimization(int $id): JsonResponse
    {
        $route = $this->routeRepository->find($id);

        if (!$route) {
            return $this->json([
                'error' => 'Route not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $startPoint = [
            'lat' => (float)$route->getStartLatitude(),
            'lng' => (float)$route->getStartLongitude(),
        ];

        $endPoint = [
            'lat' => (float)$route->getEndLatitude(),
            'lng' => (float)$route->getEndLongitude(),
        ];

        $stops = [];
        $stopDetails = [];
        foreach ($route->getStops() as $stop) {
            if ($stop->isActive()) {
                $address = $stop->getAddress();
                $stops[] = [
                    'id' => $stop->getId(),
                    'lat' => (float)$address->getLatitude(),
                    'lng' => (float)$address->getLongitude(),
                ];
                $stopDetails[$stop->getId()] = [
                    'student_name' => $stop->getStudent()->getFirstName() . ' ' . $stop->getStudent()->getLastName(),
                    'address' => $address->getStreet(),
                ];
            }
        }

        $result = $this->optimizationService->optimizeRoute($startPoint, $endPoint, $stops);

        if ($result === null) {
            return $this->json([
                'error' => 'Could not optimize route'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Add stop details to result
        $optimizedStops = [];
        foreach ($result['optimized_order'] as $order => $stopId) {
            $optimizedStops[] = [
                'order' => $order,
                'stop_id' => $stopId,
                'student_name' => $stopDetails[$stopId]['student_name'],
                'address' => $stopDetails[$stopId]['address'],
            ];
        }

        return $this->json([
            'optimized_stops' => $optimizedStops,
            'total_distance' => $result['total_distance'],
            'total_duration' => $result['total_duration'],
            'distance_km' => round($result['total_distance'] / 1000, 2),
            'duration_minutes' => round($result['total_duration'] / 60, 2),
            'segments' => $result['segments'],
        ]);
    }

    /**
     * Clone a route template
     */
    #[RouteAttribute('/{id}/clone', name: 'clone', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function cloneRoute(int $id, Request $request): JsonResponse
    {
        $route = $this->routeRepository->find($id);

        if (!$route) {
            return $this->json([
                'error' => 'Route not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $newRoute = new Route();
        $newRoute->setName($data['name'] ?? $route->getName() . ' (Copy)');
        $newRoute->setSchool($route->getSchool());
        $newRoute->setType($route->getType());
        $newRoute->setDriver($route->getDriver());
        $newRoute->setStartLatitude($route->getStartLatitude());
        $newRoute->setStartLongitude($route->getStartLongitude());
        $newRoute->setEndLatitude($route->getEndLatitude());
        $newRoute->setEndLongitude($route->getEndLongitude());
        $newRoute->setEstimatedDuration($route->getEstimatedDuration());
        $newRoute->setEstimatedDistance($route->getEstimatedDistance());
        $newRoute->setPolyline($route->getPolyline());
        $newRoute->setIsActive($data['is_active'] ?? false);
        $newRoute->setIsTemplate($data['is_template'] ?? false);

        // Clone stops
        foreach ($route->getStops() as $stop) {
            $newStop = new \App\Entity\RouteStop();
            $newStop->setStudent($stop->getStudent());
            $newStop->setAddress($stop->getAddress());
            $newStop->setStopOrder($stop->getStopOrder());
            $newStop->setEstimatedArrivalTime($stop->getEstimatedArrivalTime());
            $newStop->setGeofenceRadius($stop->getGeofenceRadius());
            $newStop->setNotes($stop->getNotes());
            $newStop->setIsActive($stop->isActive());

            $newRoute->addStop($newStop);
        }

        $this->entityManager->persist($newRoute);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'route_id' => $newRoute->getId(),
        ], Response::HTTP_CREATED);
    }
}
