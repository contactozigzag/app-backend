<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DriverDistressMessage;
use App\Repository\DriverAlertRepository;
use App\Repository\LocationUpdateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
class DriverDistressHandler
{
    public function __construct(
        private readonly DriverAlertRepository $driverAlertRepository,
        private readonly LocationUpdateRepository $locationUpdateRepository,
        private readonly HubInterface $hub,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.distress_proximity_km')]
        private readonly float $proximityRadiusKm,
    ) {
    }

    public function __invoke(DriverDistressMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Handler started', [
            'handler' => self::class,
            'driver_alert_id' => $message->driverAlertId,
        ]);

        $alert = $this->driverAlertRepository->find($message->driverAlertId);

        if ($alert === null) {
            $this->logger->warning('DriverDistressHandler: alert not found', [
                'driverAlertId' => $message->driverAlertId,
            ]);

            return;
        }

        $distressLat = (float) $alert->getLocationLat();
        $distressLng = (float) $alert->getLocationLng();
        $distressedDriverId = (int) $alert->getDistressedDriver()?->getId();

        $nearby = $this->locationUpdateRepository->findNearbyDriversInProgress(
            lat: $distressLat,
            lng: $distressLng,
            radiusMeters: $this->proximityRadiusKm * 1000,
            excludeDriverId: $distressedDriverId,
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
            } catch (Throwable $e) {
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
            } catch (Throwable $e) {
                $this->logger->error('DriverDistressHandler: failed to publish to admin', [
                    'schoolId' => $school->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $alert->setNearbyDriverIds($notifiedDriverIds);
        $this->entityManager->flush();

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'alertId' => $alert->getAlertId(),
            'notifiedCount' => count($notifiedDriverIds),
            'duration_ms' => $elapsed,
        ]);
    }
}
