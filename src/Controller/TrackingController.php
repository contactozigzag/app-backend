<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\LocationUpdate;
use App\Message\DriverLocationUpdatedMessage;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverRepository;
use App\Repository\LocationUpdateRepository;
use App\Service\DriverLocationCacheService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'api_tracking_')]
class TrackingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationUpdateRepository $locationRepository,
        private readonly DriverRepository $driverRepository,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly MessageBusInterface $bus,
        #[Autowire(service: 'limiter.gps_ingestion')]
        private readonly RateLimiterFactoryInterface $gpsIngestionLimiter,
        private readonly DriverLocationCacheService $locationCache,
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
        if (! $driver instanceof Driver) {
            return $this->json([
                'error' => 'Driver not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Rate-limit per driver (1 update per 3 seconds)
        $limiter = $this->gpsIngestionLimiter->create(sprintf('driver_%d', $driver->getId()));
        if (! $limiter->consume(1)->isAccepted()) {
            return $this->json([
                'error' => 'Too many GPS updates. Please wait before sending another.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Check if driver has an active route today
        $today = new DateTimeImmutable('today');
        $activeRoute = $this->activeRouteRepository->findActiveByDriverAndDate($driver, $today);

        $recordedAt = isset($data['timestamp'])
            ? new DateTimeImmutable($data['timestamp'])
            : new DateTimeImmutable();

        // Persist LocationUpdate to DB (kept for history/audit)
        $location = new LocationUpdate();
        $location->setDriver($driver);
        $location->setLatitude((string) $data['latitude']);
        $location->setLongitude((string) $data['longitude']);
        $location->setTimestamp($recordedAt);

        if (isset($data['speed'])) {
            $location->setSpeed((string) $data['speed']);
        }

        if (isset($data['heading'])) {
            $location->setHeading((string) $data['heading']);
        }

        if (isset($data['accuracy'])) {
            $location->setAccuracy((string) $data['accuracy']);
        }

        if ($activeRoute instanceof ActiveRoute) {
            $location->setActiveRoute($activeRoute);

            // Update active route current position
            $activeRoute->setCurrentLatitude((string) $data['latitude']);
            $activeRoute->setCurrentLongitude((string) $data['longitude']);
        }

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $speed = isset($data['speed']) ? (float) $data['speed'] : null;
        $heading = isset($data['heading']) ? (float) $data['heading'] : null;
        $activeRouteId = $activeRoute?->getId();

        // Cache latest position in Redis (15s TTL)
        $this->locationCache->cacheLocation($driver->getId(), $lat, $lng, $speed, $heading, $activeRouteId);

        // Build correlation ID
        $correlationId = $activeRouteId !== null
            ? (string) $activeRouteId
            : sprintf('driver-%d-%d', $driver->getId(), $recordedAt->getTimestamp());

        // Dispatch async tracking pipeline
        $this->bus->dispatch(new DriverLocationUpdatedMessage(
            driverId: $driver->getId(),
            activeRouteId: $activeRouteId,
            latitude: $lat,
            longitude: $lng,
            speed: $speed,
            heading: $heading,
            correlationId: $correlationId,
            recordedAt: $recordedAt,
        ));

        return $this->json([
            'success' => true,
            'location_id' => $location->getId(),
            'has_active_route' => $activeRoute instanceof ActiveRoute,
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
        if (! $driver instanceof Driver) {
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
                        ? new DateTimeImmutable($locationData['timestamp'])
                        : new DateTimeImmutable()
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
            } catch (Exception $e) {
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
     * Get latest location for a driver (Redis-first, DB fallback)
     */
    #[Route('/api/tracking/location/driver/{driverId}', name: 'api_tracking_driver_location', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDriverLocation(int $driverId): JsonResponse
    {
        $driver = $this->driverRepository->find($driverId);
        if (! $driver instanceof Driver) {
            return $this->json([
                'error' => 'Driver not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Try Redis cache first
        $cached = $this->locationCache->getLocation($driverId);
        if ($cached !== null) {
            return $this->json([
                'driver_id' => $driverId,
                'latitude' => $cached['lat'],
                'longitude' => $cached['lng'],
                'speed' => $cached['speed'],
                'heading' => $cached['heading'],
                'accuracy' => null,
                'timestamp' => $cached['cachedAt'],
                'source' => 'cache',
            ]);
        }

        // Fall back to DB
        $location = $this->locationRepository->findLatestByDriver($driver);

        if (! $location instanceof LocationUpdate) {
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
            'source' => 'db',
        ]);
    }

    /**
     * Get location history for a driver
     */
    #[Route('/api/tracking/location/driver/{driverId}/history', name: 'api_tracking_driver_location_history', methods: ['GET'])]
    #[IsGranted('ROUTE_MANAGE')]
    public function getDriverLocationHistory(int $driverId, Request $request): JsonResponse
    {
        $driver = $this->driverRepository->find($driverId);
        if (! $driver instanceof Driver) {
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
            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);
        } catch (Exception) {
            return $this->json([
                'error' => 'Invalid date format. Use ISO 8601 format.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $locations = $this->locationRepository->findByDriverAndDateRange($driver, $startDate, $endDate);

        $result = array_map(fn (LocationUpdate $location): array => [
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
