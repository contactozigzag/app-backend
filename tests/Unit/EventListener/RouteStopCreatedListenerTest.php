<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\RouteStop;
use App\EventListener\RouteStopCreatedListener;
use App\Service\RouteStopNotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;

final class RouteStopCreatedListenerTest extends TestCase
{
    public function testPostPersistCollectsUnconfirmedRouteStop(): void
    {
        $publisher = $this->createMock(RouteStopNotificationPublisher::class);
        $publisher->expects($this->once())->method('notifyDriverOfNewRequest');

        $listener = new RouteStopCreatedListener($publisher, new NullLogger());

        $routeStop = $this->createStub(RouteStop::class);
        $routeStop->method('getIsConfirmed')->willReturn(false);

        $em = $this->createStub(EntityManagerInterface::class);
        $persistArgs = new PostPersistEventArgs($routeStop, $em);
        $flushArgs = new PostFlushEventArgs($em);

        $listener->postPersist($persistArgs);
        $listener->postFlush($flushArgs);
    }

    public function testPostPersistIgnoresConfirmedRouteStop(): void
    {
        $publisher = $this->createMock(RouteStopNotificationPublisher::class);
        $publisher->expects($this->never())->method('notifyDriverOfNewRequest');

        $listener = new RouteStopCreatedListener($publisher, new NullLogger());

        $routeStop = $this->createStub(RouteStop::class);
        $routeStop->method('getIsConfirmed')->willReturn(true);

        $em = $this->createStub(EntityManagerInterface::class);
        $persistArgs = new PostPersistEventArgs($routeStop, $em);
        $flushArgs = new PostFlushEventArgs($em);

        $listener->postPersist($persistArgs);
        $listener->postFlush($flushArgs);
    }

    public function testPostPersistIgnoresNonRouteStopEntities(): void
    {
        $publisher = $this->createMock(RouteStopNotificationPublisher::class);
        $publisher->expects($this->never())->method('notifyDriverOfNewRequest');

        $listener = new RouteStopCreatedListener($publisher, new NullLogger());

        $entity = new stdClass();
        $em = $this->createStub(EntityManagerInterface::class);
        $persistArgs = new PostPersistEventArgs($entity, $em);
        $flushArgs = new PostFlushEventArgs($em);

        $listener->postPersist($persistArgs);
        $listener->postFlush($flushArgs);
    }

    public function testPendingStopsAreClearedAfterFlush(): void
    {
        $publisher = $this->createMock(RouteStopNotificationPublisher::class);
        $publisher->expects($this->once())->method('notifyDriverOfNewRequest');

        $listener = new RouteStopCreatedListener($publisher, new NullLogger());

        $routeStop = $this->createStub(RouteStop::class);
        $routeStop->method('getIsConfirmed')->willReturn(false);

        $em = $this->createStub(EntityManagerInterface::class);

        $listener->postPersist(new PostPersistEventArgs($routeStop, $em));
        $listener->postFlush(new PostFlushEventArgs($em));

        // Second flush should not re-notify
        $listener->postFlush(new PostFlushEventArgs($em));
    }

    public function testMultipleStopsAreNotifiedInSingleFlush(): void
    {
        $publisher = $this->createMock(RouteStopNotificationPublisher::class);
        $publisher->expects($this->exactly(2))->method('notifyDriverOfNewRequest');

        $listener = new RouteStopCreatedListener($publisher, new NullLogger());
        $em = $this->createStub(EntityManagerInterface::class);

        for ($i = 0; $i < 2; $i++) {
            $routeStop = $this->createStub(RouteStop::class);
            $routeStop->method('getIsConfirmed')->willReturn(false);
            $listener->postPersist(new PostPersistEventArgs($routeStop, $em));
        }

        $listener->postFlush(new PostFlushEventArgs($em));
    }
}
