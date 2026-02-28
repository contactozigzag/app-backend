<?php

declare(strict_types=1);

namespace App\State\Tracking;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Tracking\DriverLocationOutput;
use App\Entity\LocationUpdate;
use App\Repository\DriverRepository;
use App\Repository\LocationUpdateRepository;
use App\Service\DriverLocationCacheService;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles GET /api/tracking/location/driver/{driverId}.
 *
 * Returns the driver's latest GPS position. Checks Redis cache first;
 * falls back to DB if the cache entry has expired.
 *
 * @implements ProviderInterface<DriverLocationOutput>
 */
final readonly class DriverLocationProvider implements ProviderInterface
{
    public function __construct(
        private DriverRepository $driverRepository,
        private LocationUpdateRepository $locationRepository,
        private DriverLocationCacheService $locationCache,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DriverLocationOutput
    {
        $rawId = $uriVariables['driverId'] ?? null;
        $driverId = is_numeric($rawId) ? (int) $rawId : 0;

        $driver = $this->driverRepository->find($driverId);

        if ($driver === null) {
            throw new NotFoundHttpException('Driver not found.');
        }

        // Try Redis cache first
        $cached = $this->locationCache->getLocation($driverId);
        if ($cached !== null) {
            return new DriverLocationOutput(
                driverId: $driverId,
                latitude: (string) $cached['lat'],
                longitude: (string) $cached['lng'],
                speed: $cached['speed'],
                heading: $cached['heading'],
                accuracy: null,
                timestamp: $cached['cachedAt'],
                source: 'cache',
            );
        }

        // Fall back to DB
        $location = $this->locationRepository->findLatestByDriver($driver);

        if (! $location instanceof LocationUpdate) {
            throw new NotFoundHttpException('No location data available.');
        }

        return new DriverLocationOutput(
            driverId: (int) $driver->getId(),
            latitude: $location->getLatitude() ?? '0',
            longitude: $location->getLongitude() ?? '0',
            speed: $location->getSpeed(),
            heading: $location->getHeading(),
            accuracy: $location->getAccuracy(),
            timestamp: ($location->getTimestamp() ?? new DateTimeImmutable())->format('c'),
            source: 'db',
        );
    }
}
