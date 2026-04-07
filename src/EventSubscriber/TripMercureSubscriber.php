<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActiveRoute;
use App\Entity\Student;
use App\Entity\User;
use App\Event\BusArrivingEvent;
use App\Event\RouteArrivingEvent;
use App\Event\RouteCompletedEvent;
use App\Event\RouteStartedEvent;
use App\Event\StopArrivedEvent;
use App\Event\StudentDroppedOffEvent;
use App\Event\StudentPickedUpEvent;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes Mercure updates for all active-trip events so the mobile app
 * can replace polling with Server-Sent Events.
 *
 * Two topic families:
 *   - /api/users/{id}/notifications (private) — per-parent push
 *   - /tracking/route/{id}          (public)  — route state enrichment
 *
 * All publishes are non-fatal: failures are logged but never propagated.
 */
readonly class TripMercureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusArrivingEvent::NAME => 'onBusArriving',
            StopArrivedEvent::NAME => 'onStopArrived',
            StudentPickedUpEvent::NAME => 'onStudentPickedUp',
            StudentDroppedOffEvent::NAME => 'onStudentDroppedOff',
            RouteStartedEvent::NAME => 'onRouteStarted',
            RouteArrivingEvent::NAME => 'onRouteArriving',
            RouteCompletedEvent::NAME => 'onRouteCompleted',
        ];
    }

    public function onBusArriving(BusArrivingEvent $event): void
    {
        $stop = $event->getStop();
        $student = $stop->getStudent();
        $route = $stop->getActiveRoute();

        if (! $student instanceof Student || ! $route instanceof ActiveRoute) {
            return;
        }

        $data = [
            'event' => 'bus_arriving',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'stopId' => $stop->getId(),
            'studentId' => $student->getId(),
            'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
            'estimatedMinutes' => $event->getEstimatedMinutes(),
            'timestamp' => new DateTimeImmutable()->format('c'),
        ];

        $this->publishToParents($student->getParents()->toArray(), $data);
        $this->publishToRoute((int) $route->getId(), [
            'event' => 'stop_status_changed',
            'stopId' => $stop->getId(),
            'status' => 'approaching',
            'studentId' => $student->getId(),
            'timestamp' => $data['timestamp'],
        ]);
    }

    public function onStopArrived(StopArrivedEvent $event): void
    {
        $stop = $event->getStop();
        $student = $stop->getStudent();
        $route = $stop->getActiveRoute();

        if (! $student instanceof Student || ! $route instanceof ActiveRoute) {
            return;
        }

        $data = [
            'event' => 'bus_arrived',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'stopId' => $stop->getId(),
            'studentId' => $student->getId(),
            'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
            'timestamp' => new DateTimeImmutable()->format('c'),
        ];

        $this->publishToParents($student->getParents()->toArray(), $data);
        $this->publishToRoute((int) $route->getId(), [
            'event' => 'stop_status_changed',
            'stopId' => $stop->getId(),
            'status' => 'arrived',
            'studentId' => $student->getId(),
            'timestamp' => $data['timestamp'],
        ]);
    }

    public function onStudentPickedUp(StudentPickedUpEvent $event): void
    {
        $stop = $event->getStop();
        $attendance = $event->getAttendance();
        $student = $attendance->getStudent();
        $route = $stop->getActiveRoute();

        if (! $student instanceof Student || ! $route instanceof ActiveRoute) {
            return;
        }

        $data = [
            'event' => 'student_picked_up',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'stopId' => $stop->getId(),
            'studentId' => $student->getId(),
            'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
            'pickedUpAt' => $attendance->getPickedUpAt()?->format('c'),
            'timestamp' => new DateTimeImmutable()->format('c'),
        ];

        $this->publishToParents($student->getParents()->toArray(), $data);
        $this->publishToRoute((int) $route->getId(), [
            'event' => 'stop_status_changed',
            'stopId' => $stop->getId(),
            'status' => 'picked_up',
            'studentId' => $student->getId(),
            'timestamp' => $data['timestamp'],
        ]);
    }

    public function onStudentDroppedOff(StudentDroppedOffEvent $event): void
    {
        $stop = $event->getStop();
        $attendance = $event->getAttendance();
        $student = $attendance->getStudent();
        $route = $stop->getActiveRoute();

        if (! $student instanceof Student || ! $route instanceof ActiveRoute) {
            return;
        }

        $data = [
            'event' => 'student_dropped_off',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'stopId' => $stop->getId(),
            'studentId' => $student->getId(),
            'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
            'droppedOffAt' => $attendance->getDroppedOffAt()?->format('c'),
            'timestamp' => new DateTimeImmutable()->format('c'),
        ];

        $this->publishToParents($student->getParents()->toArray(), $data);
        $this->publishToRoute((int) $route->getId(), [
            'event' => 'stop_status_changed',
            'stopId' => $stop->getId(),
            'status' => 'dropped_off',
            'studentId' => $student->getId(),
            'timestamp' => $data['timestamp'],
        ]);
    }

    public function onRouteArriving(RouteArrivingEvent $event): void
    {
        $route = $event->getRoute();
        $now = new DateTimeImmutable()->format('c');

        $this->publishToRoute((int) $route->getId(), [
            'event' => 'route_arriving',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'timestamp' => $now,
        ]);
    }

    public function onRouteStarted(RouteStartedEvent $event): void
    {
        $route = $event->getRoute();
        $driver = $route->getDriver();
        $driverName = $driver?->getUser()?->getFirstName() . ' ' . $driver?->getUser()?->getLastName();
        $now = new DateTimeImmutable()->format('c');

        $parentData = [
            'event' => 'route_started',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'driverName' => trim($driverName),
            'startedAt' => $route->getStartedAt()?->format('c') ?? $now,
            'timestamp' => $now,
        ];

        $this->publishToParents($this->collectParentsFromRoute($route), $parentData);
        $this->publishToRoute((int) $route->getId(), [
            'event' => 'route_started',
            'routeId' => $route->getId(),
            'timestamp' => $now,
        ]);
    }

    public function onRouteCompleted(RouteCompletedEvent $event): void
    {
        $route = $event->getRoute();
        $now = new DateTimeImmutable()->format('c');

        $parentData = [
            'event' => 'route_completed',
            'eventId' => $event->getEventId(),
            'routeId' => $route->getId(),
            'completedAt' => $route->getCompletedAt()?->format('c') ?? $now,
            'timestamp' => $now,
        ];

        $this->publishToParents($this->collectParentsFromRoute($route), $parentData);
        $this->publishToRoute((int) $route->getId(), [
            'event' => 'route_completed',
            'routeId' => $route->getId(),
            'timestamp' => $now,
        ]);
    }

    /**
     * @param User[] $parents
     * @param array<string, mixed> $data
     */
    private function publishToParents(array $parents, array $data): void
    {
        foreach ($parents as $parent) {
            $parentId = $parent->getId();

            if ($parentId === null) {
                continue;
            }

            try {
                $topic = sprintf('/api/users/%d/notifications', $parentId);
                $update = new Update(
                    topics: [$topic],
                    data: json_encode($data, JSON_THROW_ON_ERROR),
                    private: true,
                );
                $this->hub->publish($update);

                $this->logger->info('TripMercureSubscriber: published parent notification', [
                    'user_id' => $parentId,
                    'event' => $data['event'] ?? 'unknown',
                ]);
            } catch (Exception $exception) {
                $this->logger->error('TripMercureSubscriber: failed to publish parent notification', [
                    'user_id' => $parentId,
                    'event' => $data['event'] ?? 'unknown',
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publishToRoute(int $routeId, array $data): void
    {
        try {
            $topic = sprintf('/tracking/route/%d', $routeId);
            $update = new Update(
                topics: [$topic],
                data: json_encode($data, JSON_THROW_ON_ERROR),
                private: false,
            );
            $this->hub->publish($update);

            $this->logger->info('TripMercureSubscriber: published route update', [
                'route_id' => $routeId,
                'event' => $data['event'] ?? 'unknown',
            ]);
        } catch (Exception $exception) {
            $this->logger->error('TripMercureSubscriber: failed to publish route update', [
                'route_id' => $routeId,
                'event' => $data['event'] ?? 'unknown',
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Collect unique parents from all stops on a route.
     *
     * @return User[]
     */
    private function collectParentsFromRoute(ActiveRoute $route): array
    {
        $parents = [];

        foreach ($route->getStops() as $stop) {
            $student = $stop->getStudent();

            if ($student === null) {
                continue;
            }

            foreach ($student->getParents() as $parent) {
                $parentId = $parent->getId();

                if ($parentId !== null) {
                    $parents[$parentId] = $parent;
                }
            }
        }

        return array_values($parents);
    }
}
