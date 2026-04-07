<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Entity\Address;
use App\Entity\Student;
use App\Message\DriverLocationUpdatedMessage;
use App\Message\SendPushNotification;
use App\Repository\ActiveRouteRepository;
use App\Repository\ActiveRouteStopRepository;
use App\Service\GeoCalculatorService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Evaluates proximity to the next scheduled stop and transitions the route
 * to 'arriving' status when the driver is within the configurable threshold.
 *
 * Fires an arriving_soon push notification once per stop per route (deduped
 * via a Redis cache key with a 10-minute TTL).
 */
#[AsMessageHandler]
class ProximityEvaluationHandler
{
    /**
     * Default distance in meters below which the route transitions to 'arriving'.
     * Overridable via ARRIVING_THRESHOLD_METERS env var.
     */
    private const int DEFAULT_ARRIVING_THRESHOLD = 500;

    /**
     * Seconds before an arriving_soon notification for the same stop can fire again.
     * Prevents duplicate pushes on reconnects or rapid GPS updates.
     */
    private const int DEDUP_TTL_SECONDS = 600; // 10 minutes

    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly ActiveRouteStopRepository $stopRepository,
        private readonly GeoCalculatorService $geoCalculator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly HubInterface $hub,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'int:ARRIVING_THRESHOLD_METERS')]
        private readonly int $arrivingThresholdMeters = self::DEFAULT_ARRIVING_THRESHOLD,
    ) {
    }

    public function __invoke(DriverLocationUpdatedMessage $message): void
    {
        if ($message->activeRouteId === null) {
            return;
        }

        $activeRoute = $this->activeRouteRepository->find($message->activeRouteId);

        if (! $activeRoute instanceof ActiveRoute) {
            return;
        }

        // Only process routes that are actively running
        if (! in_array($activeRoute->getStatus(), ['in_progress', 'arriving'], true)) {
            return;
        }

        $nextStop = $this->stopRepository->findNextPendingStop($activeRoute);

        if (! $nextStop instanceof ActiveRouteStop) {
            // No pending stops — route is nearly complete
            return;
        }

        $address = $nextStop->getAddress();

        if (! $address instanceof Address) {
            return;
        }

        $distance = $this->geoCalculator->calculateDistance(
            $message->latitude,
            $message->longitude,
            (float) $address->getLatitude(),
            (float) $address->getLongitude(),
        );

        $this->logger->debug('ProximityEvaluationHandler: distance to next stop', [
            'activeRouteId' => $message->activeRouteId,
            'stopId' => $nextStop->getId(),
            'distanceMeters' => $distance,
            'thresholdMeters' => $this->arrivingThresholdMeters,
        ]);

        if ($distance <= $this->arrivingThresholdMeters) {
            $this->handleWithinThreshold($activeRoute, $nextStop, $message);
        } elseif ($activeRoute->getStatus() === 'arriving') {
            // Driver moved away from the stop — reset to in_progress
            $activeRoute->setStatus('in_progress');
            $this->entityManager->flush();

            $this->logger->info('ProximityEvaluationHandler: route reset to in_progress (driver moved away)', [
                'activeRouteId' => $activeRoute->getId(),
                'distanceMeters' => $distance,
            ]);
        }
    }

    private function handleWithinThreshold(
        ActiveRoute $activeRoute,
        ActiveRouteStop $nextStop,
        DriverLocationUpdatedMessage $message,
    ): void {
        $routeId = (int) $activeRoute->getId();
        $stopId = (int) $nextStop->getId();

        // Transition route status to 'arriving' if not already
        if ($activeRoute->getStatus() === 'in_progress') {
            $activeRoute->setStatus('arriving');
            $this->entityManager->flush();

            $this->logger->info('ProximityEvaluationHandler: route transitioned to arriving', [
                'activeRouteId' => $routeId,
                'stopId' => $stopId,
                'correlationId' => $message->correlationId,
            ]);
        }

        // Dedup: send arriving_soon push at most once per stop per route
        $dedupKey = sprintf('proximity.arriving_notified.%d.%d', $routeId, $stopId);
        $alreadyNotified = $this->cache->get($dedupKey, static fn (): string => '') !== '';

        if ($alreadyNotified) {
            return;
        }

        // Record that we're sending the notification (set dedup flag)
        $this->cache->delete($dedupKey);
        $this->cache->get($dedupKey, static function (ItemInterface $item): string {
            $item->expiresAfter(self::DEDUP_TTL_SECONDS);
            return 'sent';
        });

        $this->dispatchArrivingSoonNotification($activeRoute, $nextStop, $message);
    }

    private function dispatchArrivingSoonNotification(
        ActiveRoute $activeRoute,
        ActiveRouteStop $nextStop,
        DriverLocationUpdatedMessage $message,
    ): void {
        $student = $nextStop->getStudent();

        if (! $student instanceof Student) {
            return;
        }

        $recipientUserIds = [];

        foreach ($student->getParents() as $parent) {
            $parentId = $parent->getId();

            if ($parentId !== null) {
                $recipientUserIds[] = $parentId;
            }
        }

        if ($recipientUserIds === []) {
            return;
        }

        $studentName = $student->getFirstName() . ' ' . $student->getLastName();
        $stopAddress = $nextStop->getAddress()?->getStreetAddress() ?? '';

        $notification = new SendPushNotification(
            recipientUserIds: $recipientUserIds,
            title: 'El transporte está llegando',
            body: sprintf('El transporte está llegando a la parada de %s.', $studentName),
            notificationType: 'trip_arriving_soon',
            extraData: [
                'routeId' => $activeRoute->getId(),
                'stopId' => $nextStop->getId(),
                'studentName' => $studentName,
                'stopAddress' => $stopAddress,
            ],
        );

        $this->bus->dispatch($notification);

        // Also publish Mercure status update for the route
        try {
            $this->hub->publish(new Update(
                topics: [sprintf('/tracking/route/%d', $activeRoute->getId())],
                data: json_encode([
                    'event' => 'route_arriving',
                    'routeId' => $activeRoute->getId(),
                    'stopId' => $nextStop->getId(),
                    'studentName' => $studentName,
                    'timestamp' => new DateTimeImmutable()->format('c'),
                ], JSON_THROW_ON_ERROR),
                private: false,
            ));
        } catch (Exception $exception) {
            $this->logger->warning('ProximityEvaluationHandler: Mercure publish failed', [
                'activeRouteId' => $activeRoute->getId(),
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logger->info('ProximityEvaluationHandler: arriving_soon dispatched', [
            'activeRouteId' => $activeRoute->getId(),
            'stopId' => $nextStop->getId(),
            'recipientCount' => count($recipientUserIds),
            'correlationId' => $message->correlationId,
        ]);
    }
}
