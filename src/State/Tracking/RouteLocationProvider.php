<?php

declare(strict_types=1);

namespace App\State\Tracking;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Tracking\RouteLocationOutput;
use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Entity\Address;
use App\Entity\Driver;
use App\Entity\LocationUpdate;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use App\Repository\ActiveRouteStopRepository;
use App\Repository\LocationUpdateRepository;
use App\Service\DriverLocationCacheService;
use App\Service\GeoCalculatorService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Handles GET /tracking/route/{routeId}/location/latest.
 *
 * Returns the most recent GPS snapshot for an active route together with
 * route status, next-stop proximity, and Mercure subscription details for
 * the real-time GPS stream.
 *
 * Access: ROLE_SCHOOL_ADMIN, the assigned driver, or any parent who has a
 * child enrolled on this route.
 *
 * @implements ProviderInterface<RouteLocationOutput>
 */
final readonly class RouteLocationProvider implements ProviderInterface
{
    public function __construct(
        private ActiveRouteRepository $activeRouteRepository,
        private ActiveRouteStopRepository $stopRepository,
        private LocationUpdateRepository $locationUpdateRepository,
        private DriverLocationCacheService $locationCache,
        private GeoCalculatorService $geoCalculator,
        private AuthorizationCheckerInterface $authChecker,
        private TokenStorageInterface $tokenStorage,
        #[Autowire(env: 'MERCURE_PUBLIC_URL')]
        private string $mercurePublicUrl,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RouteLocationOutput
    {
        $rawId = $uriVariables['routeId'] ?? null;
        $routeId = is_numeric($rawId) ? (int) $rawId : 0;

        $activeRoute = $this->activeRouteRepository->find($routeId);

        if (! $activeRoute instanceof ActiveRoute) {
            throw new NotFoundHttpException('Route not found.');
        }

        $this->assertAccess($activeRoute);

        // Read latest location from route-level cache or fall back to driver cache / DB
        $locationData = $this->resolveLocation($activeRoute);

        if ($locationData === null) {
            throw new NotFoundHttpException('No location data available for this route.');
        }

        // Distance to next pending stop
        $nextStop = $this->stopRepository->findNextPendingStop($activeRoute);
        $nextStopPayload = $this->buildNextStopPayload($nextStop, $locationData['lat'], $locationData['lng']);

        $driverId = $activeRoute->getDriver()?->getId();

        $topics = [sprintf('/tracking/route/%d', $routeId)];

        if ($driverId !== null) {
            $topics[] = sprintf('/tracking/driver/%d', $driverId);
        }

        return new RouteLocationOutput(
            latitude: $locationData['lat'],
            longitude: $locationData['lng'],
            heading: $locationData['heading'],
            speed: $locationData['speed'],
            recordedAt: $locationData['recordedAt'],
            routeStatus: $activeRoute->getStatus(),
            nextStop: $nextStopPayload,
            mercure: [
                'topicUrl' => sprintf('/tracking/route/%d', $routeId),
                'driverTopicUrl' => $driverId !== null ? sprintf('/tracking/driver/%d', $driverId) : null,
                'hubUrl' => $this->mercurePublicUrl,
            ],
        );
    }

    /**
     * @return array{lat: float, lng: float, heading: float|null, speed: float|null, recordedAt: string}|null
     */
    private function resolveLocation(ActiveRoute $activeRoute): ?array
    {
        $routeId = (int) $activeRoute->getId();

        // 1. Route-level cache (most up-to-date, 30s TTL)
        $routeCache = $this->locationCache->getRouteLocation($routeId);

        if ($routeCache !== null) {
            return [
                'lat' => $routeCache['lat'],
                'lng' => $routeCache['lng'],
                'heading' => $routeCache['heading'],
                'speed' => $routeCache['speed'],
                'recordedAt' => $routeCache['cachedAt'],
            ];
        }

        // 2. Driver-level cache (15s TTL)
        $driver = $activeRoute->getDriver();

        if ($driver instanceof Driver) {
            $driverCache = $this->locationCache->getLocation((int) $driver->getId());

            if ($driverCache !== null) {
                return [
                    'lat' => $driverCache['lat'],
                    'lng' => $driverCache['lng'],
                    'heading' => $driverCache['heading'],
                    'speed' => $driverCache['speed'],
                    'recordedAt' => $driverCache['cachedAt'],
                ];
            }
        }

        // 3. DB fallback
        $location = $this->locationUpdateRepository->findLatestByActiveRoute($activeRoute);

        if (! $location instanceof LocationUpdate) {
            return null;
        }

        return [
            'lat' => (float) ($location->getLatitude() ?? '0'),
            'lng' => (float) ($location->getLongitude() ?? '0'),
            'heading' => $location->getHeading() !== null ? (float) $location->getHeading() : null,
            'speed' => $location->getSpeed() !== null ? (float) $location->getSpeed() : null,
            'recordedAt' => ($location->getTimestamp() ?? new DateTimeImmutable())->format('c'),
        ];
    }

    /**
     * @return array{stopId: int|null, stopAddress: string|null, distanceMeters: float|null}|null
     */
    private function buildNextStopPayload(?ActiveRouteStop $nextStop, float $lat, float $lng): ?array
    {
        if (! $nextStop instanceof ActiveRouteStop) {
            return null;
        }

        $address = $nextStop->getAddress();

        $distanceMeters = null;

        if ($address instanceof Address) {
            $distanceMeters = $this->geoCalculator->calculateDistance(
                $lat,
                $lng,
                (float) $address->getLatitude(),
                (float) $address->getLongitude(),
            );
        }

        return [
            'stopId' => $nextStop->getId(),
            'stopAddress' => $address?->getStreetAddress(),
            'distanceMeters' => $distanceMeters !== null ? round($distanceMeters, 1) : null,
        ];
    }

    private function assertAccess(ActiveRoute $activeRoute): void
    {
        if ($this->authChecker->isGranted('ROLE_SCHOOL_ADMIN')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $currentUser = $token?->getUser();

        if (! $currentUser instanceof User) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        // Driver of this route
        $driver = $activeRoute->getDriver();

        if ($this->authChecker->isGranted('ROLE_DRIVER')
            && $driver?->getUser()?->getId() === $currentUser->getId()
        ) {
            return;
        }

        // Parent with a child on this route
        foreach ($activeRoute->getStops() as $stop) {
            $student = $stop->getStudent();

            if ($student === null) {
                continue;
            }

            foreach ($student->getParents() as $parent) {
                if ($parent->getId() === $currentUser->getId()) {
                    return;
                }
            }
        }

        throw new AccessDeniedHttpException('Access denied.');
    }
}
