<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DriverDistressMessage;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use App\Service\DriverLocationCacheService;
use App\Service\GeoCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DriverDistressHandler
{
    public function __construct(
        private readonly DriverAlertRepository $driverAlertRepository,
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly DriverLocationCacheService $locationCache,
        private readonly GeoCalculatorService $geoCalculator,
        private readonly HubInterface $hub,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.distress_proximity_km%')]
        private readonly float $proximityRadiusKm,
    ) {
    }

    public function __invoke(DriverDistressMessage $message): void
    {
        $alert = $this->driverAlertRepository->find($message->driverAlertId);

        if ($alert === null) {
            $this->logger->warning('DriverDistressHandler: alert not found', [
                'driverAlertId' => $message->driverAlertId,
            ]);

            return;
        }

        $distressLat = (float) $alert->getLocationLat();
        $distressLng = (float) $alert->getLocationLng();
        $distressedDriverId = $alert->getDistressedDriver()?->getId();

        // Collect positions of all in-progress drivers
        $inProgressRoutes = $this->activeRouteRepository->findInProgress();
        $positions = [];

        foreach ($inProgressRoutes as $route) {
            $driverId = $route->getDriver()?->getId();
            if ($driverId === null || $driverId === $distressedDriverId) {
                continue;
            }

            $cached = $this->locationCache->getLocation($driverId);
            if ($cached === null) {
                continue;
            }

            $positions[] = [
                'driverId' => $driverId,
                'lat' => $cached['lat'],
                'lng' => $cached['lng'],
            ];
        }

        // Find nearby drivers
        $nearby = $this->geoCalculator->getNearbyFromCachedPositions(
            $positions,
            $distressLat,
            $distressLng,
            $this->proximityRadiusKm,
        );

        $notifiedDriverIds = [];
        $alertPayload = json_encode([
            'alertId' => $alert->getAlertId(),
            'distressedDriverId' => $distressedDriverId,
            'lat' => $distressLat,
            'lng' => $distressLng,
            'routeId' => $alert->getRouteSession()?->getId(),
            'type' => 'distress',
        ], JSON_THROW_ON_ERROR);

        foreach ($nearby as $nearbyDriver) {
            $nearbyDriverId = $nearbyDriver['driverId'];
            $topic = sprintf('/alerts/driver/%d', $nearbyDriverId);

            try {
                $this->hub->publish(new Update($topic, $alertPayload));
                $notifiedDriverIds[] = $nearbyDriverId;
            } catch (\Throwable $e) {
                $this->logger->error('DriverDistressHandler: failed to publish to nearby driver', [
                    'nearbyDriverId' => $nearbyDriverId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Publish to school admin topic
        $school = $alert->getRouteSession()?->getRouteTemplate()?->getSchool();
        if ($school !== null) {
            try {
                $this->hub->publish(new Update(
                    sprintf('/alerts/admin/%d', $school->getId()),
                    $alertPayload,
                ));
            } catch (\Throwable $e) {
                $this->logger->error('DriverDistressHandler: failed to publish to admin', [
                    'schoolId' => $school->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $alert->setNearbyDriverIds($notifiedDriverIds);
        $this->entityManager->flush();

        $this->logger->info('DriverDistressHandler: completed', [
            'alertId' => $alert->getAlertId(),
            'notifiedCount' => count($notifiedDriverIds),
        ]);
    }
}
