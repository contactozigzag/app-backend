<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\BusArrivingEvent;
use App\Event\RouteCompletedEvent;
use App\Event\RouteStartedEvent;
use App\Event\StudentDroppedOffEvent;
use App\Event\StudentPickedUpEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RouteNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
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
        $minutes = $event->getEstimatedMinutes();

        // Get all parents of the student
        $parents = $student->getParents();

        foreach ($parents as $parent) {
            $this->notificationService->notify(
                $parent,
                'Bus Arriving Soon',
                sprintf(
                    'The bus will arrive at %s in approximately %d minutes to pick up %s.',
                    $stop->getAddress()->getName(),
                    $minutes,
                    $student->getFirstName()
                ),
                [
                    'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'estimated_minutes' => $minutes,
                    'stop_address' => $stop->getAddress()->getStreetAddress(),
                    'route_id' => $stop->getActiveRoute()->getId(),
                    'event_type' => 'bus_arriving',
                ]
            );
        }
    }

    public function onStudentPickedUp(StudentPickedUpEvent $event): void
    {
        $attendance = $event->getAttendance();
        $student = $attendance->getStudent();
        $stop = $event->getStop();

        // Get all parents of the student
        $parents = $student->getParents();

        foreach ($parents as $parent) {
            $this->notificationService->notify(
                $parent,
                'Student Picked Up',
                sprintf(
                    '%s has been picked up by the bus at %s.',
                    $student->getFirstName(),
                    $stop->getAddress()->getName()
                ),
                [
                    'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'pickup_time' => $attendance->getPickedUpAt()?->format('g:i A'),
                    'pickup_address' => $stop->getAddress()->getStreetAddress(),
                    'route_id' => $stop->getActiveRoute()->getId(),
                    'event_type' => 'student_picked_up',
                ]
            );
        }
    }

    public function onStudentDroppedOff(StudentDroppedOffEvent $event): void
    {
        $attendance = $event->getAttendance();
        $student = $attendance->getStudent();
        $stop = $event->getStop();

        // Get all parents of the student
        $parents = $student->getParents();

        foreach ($parents as $parent) {
            $this->notificationService->notify(
                $parent,
                'Student Dropped Off',
                sprintf(
                    '%s has been safely dropped off at %s.',
                    $student->getFirstName(),
                    $stop->getAddress()->getName()
                ),
                [
                    'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'dropoff_time' => $attendance->getDroppedOffAt()?->format('g:i A'),
                    'dropoff_address' => $stop->getAddress()->getStreetAddress(),
                    'route_id' => $stop->getActiveRoute()->getId(),
                    'event_type' => 'student_dropped_off',
                ]
            );
        }
    }

    public function onRouteStarted(RouteStartedEvent $event): void
    {
        $route = $event->getRoute();

        // Notify all parents with students on this route
        $parents = [];
        foreach ($route->getStops() as $stop) {
            foreach ($stop->getStudent()->getParents() as $parent) {
                $parents[$parent->getId()] = $parent;
            }
        }

        foreach ($parents as $parent) {
            $this->notificationService->notify(
                $parent,
                'Route Started',
                sprintf(
                    'The school bus route has started. Driver: %s %s',
                    $route->getDriver()->getUser()->getFirstName(),
                    $route->getDriver()->getUser()->getLastName()
                ),
                [
                    'driver_name' => $route->getDriver()->getUser()->getFirstName() . ' ' .
                                   $route->getDriver()->getUser()->getLastName(),
                    'route_id' => $route->getId(),
                    'started_at' => $route->getStartedAt()?->format('g:i A'),
                    'event_type' => 'route_started',
                ]
            );
        }
    }

    public function onRouteCompleted(RouteCompletedEvent $event): void
    {
        $route = $event->getRoute();

        // Notify school admins
        $route->getRouteTemplate()->getSchool();

        // In a real implementation, we'd query for school admins
        // For now, we'll just log this event
        // Could also send summary statistics to admins
    }
}
