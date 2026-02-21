<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LocationUpdate;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverRepository;
use App\Repository\LocationUpdateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'api_tracking_')]
class TrackingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationUpdateRepository $locationRepository,
        private readonly DriverRepository $driverRepository,
        private readonly ActiveRouteRepository $activeRouteRepository
    ) {
    }

    /**
     * Post driver location update
     */
    #[Route('/api/tracking/location', name: 'api_tracking_location_update', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function updateLocation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (! isset($data['latitude']) || ! isset($data['longitude']) || ! isset($data['driver_id'])) {
            return $this->json([
                'error' => 'Missing required fields: latitude, longitude, driver_id',
            ], Response::HTTP_BAD_REQUEST);
        }

        $driver = $this->driverRepository->find($data['driver_id']);
        if (! $driver instanceof \App\Entity\Driver) {
            return $this->json([
                'error' => 'Driver not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if driver has an active route today
        $today = new \DateTimeImmutable('today');
        $activeRoute = $this->activeRouteRepository->findActiveByDriverAndDate($driver, $today);

        // Create location update
        $location = new LocationUpdate();
        $location->setDriver($driver);
        $location->setLatitude((string) $data['latitude']);
        $location->setLongitude((string) $data['longitude']);
        $location->setTimestamp(
            isset($data['timestamp'])
                ? new \DateTimeImmutable($data['timestamp'])
                : new \DateTimeImmutable()
        );

        if (isset($data['speed'])) {
            $location->setSpeed((string) $data['speed']);
        }

        if (isset($data['heading'])) {
            $location->setHeading((string) $data['heading']);
        }

        if (isset($data['accuracy'])) {
            $location->setAccuracy((string) $data['accuracy']);
        }

        if ($activeRoute instanceof \App\Entity\ActiveRoute) {
            $location->setActiveRoute($activeRoute);

            // Update active route current position
            $activeRoute->setCurrentLatitude((string) $data['latitude']);
            $activeRoute->setCurrentLongitude((string) $data['longitude']);
        }

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'location_id' => $location->getId(),
            'has_active_route' => $activeRoute instanceof \App\Entity\ActiveRoute,
        ], Response::HTTP_CREATED);
    }

    /**
     * Batch post location updates (for offline sync)
     */
    #[Route('/api/tracking/location/batch', name: 'api_tracking_location_batch', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function batchUpdateLocations(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (! isset($data['driver_id']) || ! isset($data['locations']) || ! is_array($data['locations'])) {
            return $this->json([
                'error' => 'Missing required fields: driver_id, locations (array)',
            ], Response::HTTP_BAD_REQUEST);
        }

        $driver = $this->driverRepository->find($data['driver_id']);
        if (! $driver instanceof \App\Entity\Driver) {
            return $this->json([
                'error' => 'Driver not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $processedCount = 0;
        $errors = [];

        foreach ($data['locations'] as $index => $locationData) {
            try {
                if (! isset($locationData['latitude']) || ! isset($locationData['longitude'])) {
                    $errors[] = sprintf('Location at index %s missing latitude or longitude', $index);
                    continue;
                }

                $location = new LocationUpdate();
                $location->setDriver($driver);
                $location->setLatitude((string) $locationData['latitude']);
                $location->setLongitude((string) $locationData['longitude']);
                $location->setTimestamp(
                    isset($locationData['timestamp'])
                        ? new \DateTimeImmutable($locationData['timestamp'])
                        : new \DateTimeImmutable()
                );

                if (isset($locationData['speed'])) {
                    $location->setSpeed((string) $locationData['speed']);
                }

                if (isset($locationData['heading'])) {
                    $location->setHeading((string) $locationData['heading']);
                }

                if (isset($locationData['accuracy'])) {
                    $location->setAccuracy((string) $locationData['accuracy']);
                }

                $this->entityManager->persist($location);
                $processedCount++;
            } catch (\Exception $e) {
                $errors[] = sprintf('Error processing location at index %s: ', $index) . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'processed_count' => $processedCount,
            'total_count' => count($data['locations']),
            'errors' => $errors,
        ]);
    }

    /**
     * Get latest location for a driver
     */
    #[Route('/api/tracking/location/driver/{driverId}', name: 'api_tracking_driver_location', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDriverLocation(int $driverId): JsonResponse
    {
        $driver = $this->driverRepository->find($driverId);
        if (! $driver instanceof \App\Entity\Driver) {
            return $this->json([
                'error' => 'Driver not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $location = $this->locationRepository->findLatestByDriver($driver);

        if (! $location instanceof \App\Entity\LocationUpdate) {
            return $this->json([
                'error' => 'No location data available',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'driver_id' => $driver->getId(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'speed' => $location->getSpeed(),
            'heading' => $location->getHeading(),
            'accuracy' => $location->getAccuracy(),
            'timestamp' => $location->getTimestamp()->format('c'),
        ]);
    }

    /**
     * Get location history for a driver
     */
    #[Route('/api/tracking/location/driver/{driverId}/history', name: 'api_tracking_driver_location_history', methods: ['GET'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function getDriverLocationHistory(int $driverId, Request $request): JsonResponse
    {
        $driver = $this->driverRepository->find($driverId);
        if (! $driver instanceof \App\Entity\Driver) {
            return $this->json([
                'error' => 'Driver not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if (! $start || ! $end) {
            return $this->json([
                'error' => 'Start and end dates are required (ISO 8601 format)',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
        } catch (\Exception) {
            return $this->json([
                'error' => 'Invalid date format. Use ISO 8601 format.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $locations = $this->locationRepository->findByDriverAndDateRange($driver, $startDate, $endDate);

        $result = array_map(fn (\App\Entity\LocationUpdate $location): array => [
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'speed' => $location->getSpeed(),
            'heading' => $location->getHeading(),
            'accuracy' => $location->getAccuracy(),
            'timestamp' => $location->getTimestamp()->format('c'),
        ], $locations);

        return $this->json([
            'driver_id' => $driver->getId(),
            'count' => count($result),
            'locations' => $result,
        ]);
    }
}
