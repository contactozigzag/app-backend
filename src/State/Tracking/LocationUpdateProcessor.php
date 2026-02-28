<?php

declare(strict_types=1);

namespace App\State\Tracking;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Tracking\LocationUpdateInput;
use App\Dto\Tracking\LocationUpdateOutput;
use App\Entity\ActiveRoute;
use App\Entity\LocationUpdate;
use App\Message\DriverLocationUpdatedMessage;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverRepository;
use App\Service\DriverLocationCacheService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Handles POST /api/tracking/location.
 *
 * Enforces per-driver GPS rate limiting, persists the location update,
 * caches the latest position in Redis, and dispatches
 * DriverLocationUpdatedMessage for the async tracking pipeline.
 *
 * @implements ProcessorInterface<LocationUpdateInput, LocationUpdateOutput>
 */
final readonly class LocationUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DriverRepository $driverRepository,
        private ActiveRouteRepository $activeRouteRepository,
        private MessageBusInterface $bus,
        private DriverLocationCacheService $locationCache,
        #[Autowire(service: 'limiter.gps_ingestion')]
        private RateLimiterFactoryInterface $gpsIngestionLimiter,
    ) {
    }

    /**
     * @param LocationUpdateInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LocationUpdateOutput
    {
        $driver = $this->driverRepository->find((int) $data->driverId);

        if ($driver === null) {
            throw new NotFoundHttpException('Driver not found.');
        }

        // Rate-limit per driver (1 update per 3 seconds)
        $limiter = $this->gpsIngestionLimiter->create(sprintf('driver_%d', $driver->getId()));
        if (! $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Too many GPS updates. Please wait before sending another.');
        }

        // Check if driver has an active route today
        $today = new DateTimeImmutable('today');
        $activeRoute = $this->activeRouteRepository->findActiveByDriverAndDate($driver, $today);

        $recordedAt = $data->timestamp !== null
            ? new DateTimeImmutable($data->timestamp)
            : new DateTimeImmutable();

        $lat = (float) $data->latitude;
        $lng = (float) $data->longitude;

        // Persist LocationUpdate to DB (kept for history/audit)
        $location = new LocationUpdate();
        $location->setDriver($driver);
        $location->setLatitude((string) $lat);
        $location->setLongitude((string) $lng);
        $location->setTimestamp($recordedAt);

        if ($data->speed !== null) {
            $location->setSpeed((string) $data->speed);
        }

        if ($data->heading !== null) {
            $location->setHeading((string) $data->heading);
        }

        if ($data->accuracy !== null) {
            $location->setAccuracy((string) $data->accuracy);
        }

        if ($activeRoute instanceof ActiveRoute) {
            $location->setActiveRoute($activeRoute);

            // Update active route current position
            $activeRoute->setCurrentLatitude((string) $lat);
            $activeRoute->setCurrentLongitude((string) $lng);
        }

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $speed = $data->speed;
        $heading = $data->heading;
        $activeRouteId = $activeRoute?->getId();

        // Cache latest position in Redis (15s TTL)
        $this->locationCache->cacheLocation((int) $driver->getId(), $lat, $lng, $speed, $heading, $activeRouteId);

        // Build correlation ID
        $correlationId = $activeRouteId !== null
            ? (string) $activeRouteId
            : sprintf('driver-%d-%d', $driver->getId(), $recordedAt->getTimestamp());

        // Dispatch async tracking pipeline
        $this->bus->dispatch(new DriverLocationUpdatedMessage(
            driverId: (int) $driver->getId(),
            activeRouteId: $activeRouteId,
            latitude: $lat,
            longitude: $lng,
            speed: $speed,
            heading: $heading,
            correlationId: $correlationId,
            recordedAt: $recordedAt,
        ));

        return new LocationUpdateOutput(
            success: true,
            locationId: (int) $location->getId(),
            hasActiveRoute: $activeRoute instanceof ActiveRoute,
        );
    }
}
