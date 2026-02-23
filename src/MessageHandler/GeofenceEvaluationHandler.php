<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DriverLocationUpdatedMessage;
use App\Repository\ActiveRouteRepository;
use App\Service\DriverLocationCacheService;
use App\Service\GeofencingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GeofenceEvaluationHandler
{
    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly GeofencingService $geofencingService,
        private readonly DriverLocationCacheService $locationCache,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(DriverLocationUpdatedMessage $message): void
    {
        if ($message->activeRouteId === null) {
            return;
        }

        $activeRoute = $this->activeRouteRepository->find($message->activeRouteId);

        if ($activeRoute === null) {
            $this->logger->warning('GeofenceEvaluationHandler: ActiveRoute not found', [
                'activeRouteId' => $message->activeRouteId,
                'correlationId' => $message->correlationId,
            ]);

            return;
        }

        // Sync the latest Redis position into the entity before geofencing
        $cached = $this->locationCache->getLocation($message->driverId);
        if ($cached !== null) {
            $activeRoute->setCurrentLatitude((string) $cached['lat']);
            $activeRoute->setCurrentLongitude((string) $cached['lng']);
        }

        $result = $this->geofencingService->checkActiveRoute($activeRoute);

        $this->logger->info('GeofenceEvaluationHandler: completed', [
            'activeRouteId' => $message->activeRouteId,
            'approaching' => count($result['approaching']),
            'arrived' => count($result['arrived']),
            'correlationId' => $message->correlationId,
        ]);
    }
}
