<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\ActiveRoute;
use App\Entity\ActiveRouteStop;
use App\Entity\Attendance;
use App\Entity\Driver;
use App\Entity\Student;
use App\Entity\User;
use App\Event\BusArrivingEvent;
use App\Event\RouteCompletedEvent;
use App\Event\RouteStartedEvent;
use App\Event\StopArrivedEvent;
use App\Event\StudentDroppedOffEvent;
use App\Event\StudentPickedUpEvent;
use App\EventSubscriber\TripMercureSubscriber;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class TripMercureSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsReturnsAllEvents(): void
    {
        $events = TripMercureSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(BusArrivingEvent::NAME, $events);
        $this->assertArrayHasKey(StopArrivedEvent::NAME, $events);
        $this->assertArrayHasKey(StudentPickedUpEvent::NAME, $events);
        $this->assertArrayHasKey(StudentDroppedOffEvent::NAME, $events);
        $this->assertArrayHasKey(RouteStartedEvent::NAME, $events);
        $this->assertArrayHasKey(RouteCompletedEvent::NAME, $events);
    }

    public function testOnBusArrivingPublishesToParentAndRoute(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))->method('publish')
            ->willReturnCallback(function (Update $update): string {
                $topics = $update->getTopics();
                $this->assertCount(1, $topics);

                return 'id';
            });

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onBusArriving(new BusArrivingEvent(
            $this->createStopWithParent(42, 7, 15, 99),
            5,
        ));
    }

    public function testOnStopArrivedPublishesToParentAndRoute(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))->method('publish');

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onStopArrived(new StopArrivedEvent(
            $this->createStopWithParent(42, 7, 15, 99),
        ));
    }

    public function testOnStudentPickedUpPublishesToParentAndRoute(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $publishedTopics = [];
        $hub->expects($this->exactly(2))->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedTopics): string {
                $publishedTopics[] = $update->getTopics()[0];

                return 'id';
            });

        $stop = $this->createStopWithParent(42, 7, 15, 99);
        $attendance = $this->createAttendanceForStop($stop);

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onStudentPickedUp(new StudentPickedUpEvent($attendance, $stop));

        $this->assertContains('/api/users/99/notifications', $publishedTopics);
        $this->assertContains('/tracking/route/42', $publishedTopics);
    }

    public function testOnStudentDroppedOffPublishesToParentAndRoute(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))->method('publish');

        $stop = $this->createStopWithParent(42, 7, 15, 99);
        $attendance = $this->createAttendanceForStop($stop);

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onStudentDroppedOff(new StudentDroppedOffEvent($attendance, $stop));
    }

    public function testOnRouteStartedPublishesToAllParentsAndRoute(): void
    {
        $hub = $this->createMock(HubInterface::class);
        // 2 unique parents + 1 route topic = 3 publishes
        $hub->expects($this->exactly(3))->method('publish');

        $route = $this->createRouteWithTwoStops();

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onRouteStarted(new RouteStartedEvent($route));
    }

    public function testOnRouteCompletedPublishesToAllParentsAndRoute(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(3))->method('publish');

        $route = $this->createRouteWithTwoStops();

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onRouteCompleted(new RouteCompletedEvent($route));
    }

    public function testMercurePublishFailureIsLoggedNotThrown(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willThrowException(new RuntimeException('Hub down'));

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());

        // Must not throw
        $subscriber->onStopArrived(new StopArrivedEvent(
            $this->createStopWithParent(1, 1, 1, 1),
        ));

        $this->addToAssertionCount(1);
    }

    public function testSkipsWhenStudentIsNull(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $stop = $this->createStub(ActiveRouteStop::class);
        $stop->method('getStudent')->willReturn(null);
        $stop->method('getActiveRoute')->willReturn($this->createStub(ActiveRoute::class));

        $subscriber = new TripMercureSubscriber($hub, new NullLogger());
        $subscriber->onStopArrived(new StopArrivedEvent($stop));
    }

    private function createStopWithParent(int $routeId, int $stopId, int $studentId, int $parentId): ActiveRouteStop
    {
        $parent = $this->createStub(User::class);
        $parent->method('getId')->willReturn($parentId);

        $student = $this->createStub(Student::class);
        $student->method('getId')->willReturn($studentId);
        $student->method('getFirstName')->willReturn('Test');
        $student->method('getLastName')->willReturn('Student');
        $student->method('getParents')->willReturn(new ArrayCollection([$parent]));

        $route = $this->createStub(ActiveRoute::class);
        $route->method('getId')->willReturn($routeId);
        $route->method('getStartedAt')->willReturn(new DateTimeImmutable());
        $route->method('getCompletedAt')->willReturn(new DateTimeImmutable());

        $stop = $this->createStub(ActiveRouteStop::class);
        $stop->method('getId')->willReturn($stopId);
        $stop->method('getStudent')->willReturn($student);
        $stop->method('getActiveRoute')->willReturn($route);

        return $stop;
    }

    /**
     * Creates an attendance stub whose getStudent() returns the same student as the stop,
     * so parent lookups via $attendance->getStudent()->getParents() work correctly.
     */
    private function createAttendanceForStop(ActiveRouteStop $stop): Attendance
    {
        $attendance = $this->createStub(Attendance::class);
        $attendance->method('getStudent')->willReturn($stop->getStudent());
        $attendance->method('getPickedUpAt')->willReturn(new DateTimeImmutable());
        $attendance->method('getDroppedOffAt')->willReturn(new DateTimeImmutable());

        return $attendance;
    }

    private function createRouteWithTwoStops(): ActiveRoute
    {
        $parent1 = $this->createStub(User::class);
        $parent1->method('getId')->willReturn(100);

        $parent2 = $this->createStub(User::class);
        $parent2->method('getId')->willReturn(200);

        $student1 = $this->createStub(Student::class);
        $student1->method('getParents')->willReturn(new ArrayCollection([$parent1]));

        $student2 = $this->createStub(Student::class);
        $student2->method('getParents')->willReturn(new ArrayCollection([$parent2]));

        $stop1 = $this->createStub(ActiveRouteStop::class);
        $stop1->method('getStudent')->willReturn($student1);

        $stop2 = $this->createStub(ActiveRouteStop::class);
        $stop2->method('getStudent')->willReturn($student2);

        $driverUser = $this->createStub(User::class);
        $driverUser->method('getFirstName')->willReturn('John');
        $driverUser->method('getLastName')->willReturn('Driver');

        $driver = $this->createStub(Driver::class);
        $driver->method('getUser')->willReturn($driverUser);

        $route = $this->createStub(ActiveRoute::class);
        $route->method('getId')->willReturn(42);
        $route->method('getDriver')->willReturn($driver);
        $route->method('getStartedAt')->willReturn(new DateTimeImmutable());
        $route->method('getCompletedAt')->willReturn(new DateTimeImmutable());
        $route->method('getStops')->willReturn(new ArrayCollection([$stop1, $stop2]));

        return $route;
    }
}
