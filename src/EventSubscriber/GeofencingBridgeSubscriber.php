<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActiveRouteStop;
use App\Event\BusArrivingEvent;
use App\Event\StopApproachingEvent;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Bridges low-level geofencing events (stop.approaching) to high-level
 * domain events (bus.arriving) consumed by notification subscribers.
 */
readonly class GeofencingBridgeSubscriber implements EventSubscriberInterface
{
    /**
     * Default estimate when no ETA is available from the stop.
     */
    private const int DEFAULT_APPROACHING_MINUTES = 5;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StopApproachingEvent::NAME => 'onStopApproaching',
        ];
    }

    public function onStopApproaching(StopApproachingEvent $event): void
    {
        $stop = $event->getStop();
        $estimatedMinutes = $this->calculateEstimatedMinutes($stop);

        $this->logger->info('GeofencingBridgeSubscriber: dispatching BusArrivingEvent', [
            'stop_id' => $stop->getId(),
            'student_id' => $stop->getStudent()?->getId(),
            'estimated_minutes' => $estimatedMinutes,
        ]);

        $this->eventDispatcher->dispatch(
            new BusArrivingEvent($stop, $estimatedMinutes),
            BusArrivingEvent::NAME,
        );
    }

    /**
     * Estimate minutes to arrival based on the stop's ETA offset from route start.
     * Falls back to a sensible default when no ETA is configured.
     */
    private function calculateEstimatedMinutes(ActiveRouteStop $stop): int
    {
        $etaSeconds = $stop->getEstimatedArrivalTime();

        if ($etaSeconds === null) {
            return self::DEFAULT_APPROACHING_MINUTES;
        }

        $startedAt = $stop->getActiveRoute()?->getStartedAt();

        if (! $startedAt instanceof DateTimeImmutable) {
            return self::DEFAULT_APPROACHING_MINUTES;
        }

        $elapsedSeconds = time() - $startedAt->getTimestamp();
        $remainingSeconds = max(0, $etaSeconds - $elapsedSeconds);

        return max(1, (int) ceil($remainingSeconds / 60));
    }
}
