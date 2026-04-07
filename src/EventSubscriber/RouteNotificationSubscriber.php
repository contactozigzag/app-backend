<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Student;
use App\Entity\User;
use App\Event\BusArrivingEvent;
use App\Event\RouteCompletedEvent;
use App\Event\RouteStartedEvent;
use App\Event\StudentDroppedOffEvent;
use App\Event\StudentPickedUpEvent;
use App\Message\SendPushNotification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches async Expo push notifications for critical trip lifecycle events.
 *
 * Each handler collects parent user IDs for the affected student(s) and
 * dispatches a SendPushNotification message — picked up by
 * SendPushNotificationHandler on the `async` Messenger transport.
 *
 * The eventId carried in each domain event is forwarded so the mobile client
 * can deduplicate the push against any Mercure SSE that already landed.
 */
class RouteNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusArrivingEvent::NAME => 'onBusArriving',
            StudentPickedUpEvent::NAME => 'onStudentPickedUp',
            StudentDroppedOffEvent::NAME => 'onStudentDroppedOff',
            RouteStartedEvent::NAME => 'onRouteStarted',
            RouteCompletedEvent::NAME => 'onRouteCompleted',
        ];
    }

    public function onBusArriving(BusArrivingEvent $event): void
    {
        $stop = $event->getStop();
        $student = $stop->getStudent();

        if (! $student instanceof Student) {
            return;
        }

        $parentIds = $this->collectParentIds($student->getParents()->toArray());

        if ($parentIds === []) {
            return;
        }

        $studentName = $student->getFirstName() . ' ' . $student->getLastName();

        $this->bus->dispatch(new SendPushNotification(
            recipientUserIds: $parentIds,
            title: 'El transporte está llegando',
            body: sprintf(
                'El transporte llegará en aproximadamente %d minutos a la parada de %s.',
                $event->getEstimatedMinutes(),
                $studentName,
            ),
            notificationType: 'trip_bus_arriving',
            extraData: [
                'routeId' => $stop->getActiveRoute()?->getId(),
                'stopId' => $stop->getId(),
                'studentName' => $studentName,
                'estimatedMinutes' => $event->getEstimatedMinutes(),
            ],
            eventId: $event->getEventId(),
        ));
    }

    public function onStudentPickedUp(StudentPickedUpEvent $event): void
    {
        $attendance = $event->getAttendance();
        $student = $attendance->getStudent();
        $stop = $event->getStop();

        if (! $student instanceof Student) {
            return;
        }

        $parentIds = $this->collectParentIds($student->getParents()->toArray());

        if ($parentIds === []) {
            return;
        }

        $studentName = $student->getFirstName() . ' ' . $student->getLastName();

        $this->bus->dispatch(new SendPushNotification(
            recipientUserIds: $parentIds,
            title: 'Estudiante a bordo',
            body: sprintf('%s ha subido al transporte.', $studentName),
            notificationType: 'trip_student_picked_up',
            extraData: [
                'routeId' => $stop->getActiveRoute()?->getId(),
                'stopId' => $stop->getId(),
                'studentName' => $studentName,
                'pickedUpAt' => $attendance->getPickedUpAt()?->format('c'),
            ],
            eventId: $event->getEventId(),
        ));
    }

    public function onStudentDroppedOff(StudentDroppedOffEvent $event): void
    {
        $attendance = $event->getAttendance();
        $student = $attendance->getStudent();
        $stop = $event->getStop();

        if (! $student instanceof Student) {
            return;
        }

        $parentIds = $this->collectParentIds($student->getParents()->toArray());

        if ($parentIds === []) {
            return;
        }

        $studentName = $student->getFirstName() . ' ' . $student->getLastName();

        $this->bus->dispatch(new SendPushNotification(
            recipientUserIds: $parentIds,
            title: 'Estudiante ha bajado',
            body: sprintf('%s ha bajado del transporte.', $studentName),
            notificationType: 'trip_student_dropped_off',
            extraData: [
                'routeId' => $stop->getActiveRoute()?->getId(),
                'stopId' => $stop->getId(),
                'studentName' => $studentName,
                'droppedOffAt' => $attendance->getDroppedOffAt()?->format('c'),
            ],
            eventId: $event->getEventId(),
        ));
    }

    public function onRouteStarted(RouteStartedEvent $event): void
    {
        $route = $event->getRoute();
        $parentIds = [];

        foreach ($route->getStops() as $stop) {
            $student = $stop->getStudent();

            if ($student === null) {
                continue;
            }

            foreach ($this->collectParentIds($student->getParents()->toArray()) as $parentId) {
                $parentIds[$parentId] = $parentId;
            }
        }

        $parentIds = array_values($parentIds);

        if ($parentIds === []) {
            return;
        }

        $driver = $route->getDriver();
        $driverName = trim(
            ($driver?->getUser()?->getFirstName() ?? '') . ' ' . ($driver?->getUser()?->getLastName() ?? '')
        );

        $this->bus->dispatch(new SendPushNotification(
            recipientUserIds: $parentIds,
            title: 'El recorrido ha iniciado',
            body: sprintf('El transporte escolar ha iniciado el recorrido. Conductor: %s.', $driverName),
            notificationType: 'trip_started',
            extraData: [
                'routeId' => $route->getId(),
                'driverName' => $driverName,
                'startedAt' => $route->getStartedAt()?->format('c'),
            ],
            eventId: $event->getEventId(),
        ));
    }

    public function onRouteCompleted(RouteCompletedEvent $event): void
    {
        $route = $event->getRoute();
        $parentIds = [];

        foreach ($route->getStops() as $stop) {
            $student = $stop->getStudent();

            if ($student === null) {
                continue;
            }

            foreach ($this->collectParentIds($student->getParents()->toArray()) as $parentId) {
                $parentIds[$parentId] = $parentId;
            }
        }

        $parentIds = array_values($parentIds);

        if ($parentIds === []) {
            return;
        }

        $this->bus->dispatch(new SendPushNotification(
            recipientUserIds: $parentIds,
            title: 'El recorrido ha finalizado',
            body: 'El recorrido escolar ha finalizado.',
            notificationType: 'trip_completed',
            extraData: [
                'routeId' => $route->getId(),
                'completedAt' => $route->getCompletedAt()?->format('c'),
            ],
            eventId: $event->getEventId(),
        ));
    }

    /**
     * @param User[] $parents
     * @return list<int>
     */
    private function collectParentIds(array $parents): array
    {
        $ids = [];

        foreach ($parents as $parent) {
            $id = $parent->getId();

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
