<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Entity\Student;
use App\Event\BusArrivingEvent;
use App\Event\StopApproachingEvent;
use App\EventSubscriber\GeofencingBridgeSubscriber;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class GeofencingBridgeSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = GeofencingBridgeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(StopApproachingEvent::NAME, $events);
        $this->assertSame('onStopApproaching', $events[StopApproachingEvent::NAME]);
    }

    public function testOnStopApproachingDispatchesBusArrivingEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(BusArrivingEvent::class),
                BusArrivingEvent::NAME,
            );

        $subscriber = new GeofencingBridgeSubscriber($dispatcher, new NullLogger());
        $subscriber->onStopApproaching(new StopApproachingEvent($this->createStopStub()));
    }

    public function testUsesDefaultMinutesWhenNoEta(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (BusArrivingEvent $event): bool => $event->getEstimatedMinutes() === 5),
                BusArrivingEvent::NAME,
            );

        $stop = $this->createStopStub();

        $subscriber = new GeofencingBridgeSubscriber($dispatcher, new NullLogger());
        $subscriber->onStopApproaching(new StopApproachingEvent($stop));
    }

    public function testCalculatesMinutesFromEta(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (BusArrivingEvent $event): bool => $event->getEstimatedMinutes() >= 1),
                BusArrivingEvent::NAME,
            );

        // Route started 5 minutes ago, ETA is 600 seconds (10 min) from start
        $route = $this->createStub(ActiveRoute::class);
        $route->method('getStartedAt')->willReturn(new DateTimeImmutable('-5 minutes'));

        $stop = $this->createStub(ActiveRouteStop::class);
        $stop->method('getId')->willReturn(1);
        $stop->method('getEstimatedArrivalTime')->willReturn(600);
        $stop->method('getActiveRoute')->willReturn($route);

        $student = $this->createStub(Student::class);
        $student->method('getId')->willReturn(1);
        $stop->method('getStudent')->willReturn($student);

        $subscriber = new GeofencingBridgeSubscriber($dispatcher, new NullLogger());
        $subscriber->onStopApproaching(new StopApproachingEvent($stop));
    }

    private function createStopStub(?int $eta = null): ActiveRouteStop
    {
        $route = $this->createStub(ActiveRoute::class);
        $route->method('getStartedAt')->willReturn(new DateTimeImmutable('-5 minutes'));

        $student = $this->createStub(Student::class);
        $student->method('getId')->willReturn(1);

        $stop = $this->createStub(ActiveRouteStop::class);
        $stop->method('getId')->willReturn(1);
        $stop->method('getEstimatedArrivalTime')->willReturn($eta);
        $stop->method('getActiveRoute')->willReturn($route);
        $stop->method('getStudent')->willReturn($student);

        return $stop;
    }
}
