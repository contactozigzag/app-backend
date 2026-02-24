<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use App\Message\DetectGpsAnomalyMessage;
use App\Message\DriverDistressMessage;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use App\Service\DriverLocationCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DetectGpsAnomalyHandler
{
    private const int ANOMALY_THRESHOLD_SECONDS = 120; // 2 minutes

    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly DriverAlertRepository $driverAlertRepository,
        private readonly DriverLocationCacheService $locationCache,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DetectGpsAnomalyMessage $message): void
    {
        $inProgressRoutes = $this->activeRouteRepository->findInProgress();
        $now = new \DateTimeImmutable();

        foreach ($inProgressRoutes as $route) {
            $driver = $route->getDriver();
            if ($driver === null) {
                continue;
            }

            // Skip routes that already have an open alert
            $existingAlert = $this->driverAlertRepository->findActiveByDistressedDriver($driver);
            if ($existingAlert !== null) {
                continue;
            }

            $lastSeen = $this->locationCache->getLastSeen((int) $driver->getId());
            $startedAt = $route->getStartedAt();

            $routeIsOldEnough = $startedAt !== null
                && ($now->getTimestamp() - $startedAt->getTimestamp()) > self::ANOMALY_THRESHOLD_SECONDS;

            $anomalyDetected = false;

            if ($lastSeen === null && $routeIsOldEnough) {
                $anomalyDetected = true;

                $this->logger->warning('DetectGpsAnomalyHandler: no GPS data since route started', [
                    'routeId' => $route->getId(),
                    'driverId' => $driver->getId(),
                ]);
            } elseif ($lastSeen !== null) {
                $secondsSinceSeen = $now->getTimestamp() - $lastSeen->getTimestamp();

                if ($secondsSinceSeen > self::ANOMALY_THRESHOLD_SECONDS) {
                    $anomalyDetected = true;

                    $this->logger->warning('DetectGpsAnomalyHandler: GPS signal lost', [
                        'routeId' => $route->getId(),
                        'driverId' => $driver->getId(),
                        'secondsSinceSeen' => $secondsSinceSeen,
                    ]);
                }
            }

            if (! $anomalyDetected) {
                continue;
            }

            // Create DriverAlert
            $alert = new DriverAlert();
            $alert->setDistressedDriver($driver);
            $alert->setRouteSession($route);
            $alert->setStatus(AlertStatus::PENDING);

            $lat = $route->getCurrentLatitude() ?? '0.000000';
            $lng = $route->getCurrentLongitude() ?? '0.000000';
            $alert->setLocationLat($lat);
            $alert->setLocationLng($lng);

            $this->entityManager->persist($alert);
            $this->entityManager->flush();

            $this->bus->dispatch(new DriverDistressMessage((int) $alert->getId()));

            $this->logger->info('DetectGpsAnomalyHandler: distress alert created', [
                'alertId' => $alert->getAlertId(),
                'driverId' => $driver->getId(),
            ]);
        }
    }
}
