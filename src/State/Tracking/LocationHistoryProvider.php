<?php

declare(strict_types=1);

namespace App\State\Tracking;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Tracking\LocationHistoryOutput;
use App\Entity\LocationUpdate;
use App\Repository\DriverRepository;
use App\Repository\LocationUpdateRepository;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles GET /api/tracking/location/driver/{driverId}/history.
 *
 * Returns GPS history for a driver within a date range.
 * Query params: start (ISO 8601), end (ISO 8601) — both required.
 *
 * @implements ProviderInterface<LocationHistoryOutput>
 */
final readonly class LocationHistoryProvider implements ProviderInterface
{
    public function __construct(
        private DriverRepository $driverRepository,
        private LocationUpdateRepository $locationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LocationHistoryOutput
    {
        $rawId = $uriVariables['driverId'] ?? null;
        $driverId = is_numeric($rawId) ? (int) $rawId : 0;

        $driver = $this->driverRepository->find($driverId);

        if ($driver === null) {
            throw new NotFoundHttpException('Driver not found.');
        }

        $request = $context['request'] instanceof Request ? $context['request'] : null;
        $start = $request?->query->get('start');
        $end = $request?->query->get('end');

        if (! $start || ! $end) {
            throw new BadRequestHttpException('Start and end dates are required (ISO 8601 format).');
        }

        try {
            $startDate = new DateTimeImmutable((string) $start);
            $endDate = new DateTimeImmutable((string) $end);
        } catch (Exception) {
            throw new BadRequestHttpException('Invalid date format. Use ISO 8601 format.');
        }

        $locationEntities = $this->locationRepository->findByDriverAndDateRange($driver, $startDate, $endDate);

        $locations = array_values(array_map(fn (LocationUpdate $location): array => [
            'latitude' => $location->getLatitude() ?? '0',
            'longitude' => $location->getLongitude() ?? '0',
            'speed' => $location->getSpeed(),
            'heading' => $location->getHeading(),
            'accuracy' => $location->getAccuracy(),
            'timestamp' => ($location->getTimestamp() ?? new DateTimeImmutable())->format('c'),
        ], $locationEntities));

        return new LocationHistoryOutput(
            driverId: (int) $driver->getId(),
            count: count($locations),
            locations: $locations,
        );
    }
}
