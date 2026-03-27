<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Student;
use App\Entity\User;
use App\Service\RouteStopNotificationPublisher;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class RouteStopNotificationPublisherTest extends TestCase
{
    public function testNotifyDriverOfNewRequestPublishesToDriverTopic(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $publishedTopics = [];
        $hub->expects($this->once())->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedTopics): string {
                $publishedTopics[] = $update->getTopics()[0];

                return 'id';
            });

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());
        $publisher->notifyDriverOfNewRequest($this->createRouteStop(10, 42, 'Morning Route', 5, 99));

        $this->assertContains('/api/users/99/notifications', $publishedTopics);
    }

    public function testNotifyDriverSkipsWhenNoDriver(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $route = $this->createStub(Route::class);
        $route->method('getDriver')->willReturn(null);

        $routeStop = $this->createStub(RouteStop::class);
        $routeStop->method('getRoute')->willReturn($route);

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());
        $publisher->notifyDriverOfNewRequest($routeStop);
    }

    public function testNotifyParentsOfConfirmationPublishesToAllParents(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $publishedTopics = [];
        $hub->expects($this->exactly(2))->method('publish')
            ->willReturnCallback(function (Update $update) use (&$publishedTopics): string {
                $publishedTopics[] = $update->getTopics()[0];
                $data = json_decode($update->getData(), true);
                $this->assertSame('route_stop_confirmed', $data['event']);

                return 'id';
            });

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());
        $publisher->notifyParentsOfConfirmation(
            $this->createRouteStopWithTwoParents(10, 42, 5, 100, 200),
        );

        $this->assertContains('/api/users/100/notifications', $publishedTopics);
        $this->assertContains('/api/users/200/notifications', $publishedTopics);
    }

    public function testNotifyParentsOfRejectionPublishesCorrectEvent(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('publish')
            ->willReturnCallback(function (Update $update): string {
                $data = json_decode($update->getData(), true);
                $this->assertSame('route_stop_rejected', $data['event']);

                return 'id';
            });

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());
        $publisher->notifyParentsOfRejection(
            $this->createRouteStop(10, 42, 'Route', 5, 99, [300]),
        );
    }

    public function testPublishFailureIsLoggedNotThrown(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('publish')->willThrowException(new RuntimeException('Hub down'));

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());

        // Must not throw
        $publisher->notifyDriverOfNewRequest($this->createRouteStop(10, 42, 'Route', 5, 99));

        $this->addToAssertionCount(1);
    }

    public function testNotifyParentsSkipsWhenNoStudent(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $routeStop = $this->createStub(RouteStop::class);
        $routeStop->method('getStudent')->willReturn(null);

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());
        $publisher->notifyParentsOfConfirmation($routeStop);
    }

    public function testPayloadContainsExpectedFields(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('publish')
            ->willReturnCallback(function (Update $update): string {
                $data = json_decode($update->getData(), true);
                $this->assertArrayHasKey('routeStopId', $data);
                $this->assertArrayHasKey('routeId', $data);
                $this->assertArrayHasKey('routeName', $data);
                $this->assertArrayHasKey('studentId', $data);
                $this->assertArrayHasKey('studentName', $data);
                $this->assertArrayHasKey('timestamp', $data);
                $this->assertSame(10, $data['routeStopId']);
                $this->assertSame(42, $data['routeId']);
                $this->assertSame('Morning Route', $data['routeName']);

                return 'id';
            });

        $publisher = new RouteStopNotificationPublisher($hub, new NullLogger());
        $publisher->notifyDriverOfNewRequest(
            $this->createRouteStop(10, 42, 'Morning Route', 5, 99),
        );
    }

    /**
     * @param int[] $parentIds
     */
    private function createRouteStop(
        int $stopId,
        int $routeId,
        string $routeName,
        int $studentId,
        int $driverUserId,
        array $parentIds = [],
    ): RouteStop {
        $driverUser = $this->createStub(User::class);
        $driverUser->method('getId')->willReturn($driverUserId);
        $driverUser->method('getFirstName')->willReturn('John');
        $driverUser->method('getLastName')->willReturn('Driver');

        $driver = $this->createStub(Driver::class);
        $driver->method('getUser')->willReturn($driverUser);

        $route = $this->createStub(Route::class);
        $route->method('getId')->willReturn($routeId);
        $route->method('getName')->willReturn($routeName);
        $route->method('getDriver')->willReturn($driver);

        $parents = [];
        foreach ($parentIds as $parentId) {
            $parent = $this->createStub(User::class);
            $parent->method('getId')->willReturn($parentId);
            $parents[] = $parent;
        }

        $student = $this->createStub(Student::class);
        $student->method('getId')->willReturn($studentId);
        $student->method('getFirstName')->willReturn('Test');
        $student->method('getLastName')->willReturn('Student');
        $student->method('getParents')->willReturn(new ArrayCollection($parents));

        $routeStop = $this->createStub(RouteStop::class);
        $routeStop->method('getId')->willReturn($stopId);
        $routeStop->method('getRoute')->willReturn($route);
        $routeStop->method('getStudent')->willReturn($student);

        return $routeStop;
    }

    private function createRouteStopWithTwoParents(
        int $stopId,
        int $routeId,
        int $studentId,
        int $parentId1,
        int $parentId2,
    ): RouteStop {
        return $this->createRouteStop($stopId, $routeId, 'Test Route', $studentId, 99, [$parentId1, $parentId2]);
    }
}
