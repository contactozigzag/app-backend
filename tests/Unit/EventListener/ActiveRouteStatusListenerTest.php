<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ActiveRoute;
use App\Event\RouteCompletedEvent;
use App\Event\RouteStartedEvent;
use App\EventListener\ActiveRouteStatusListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ActiveRouteStatusListenerTest extends TestCase
{
    public function testDispatchesRouteStartedOnStatusChangeToInProgress(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(RouteStartedEvent::class),
                RouteStartedEvent::NAME,
            );

        $listener = new ActiveRouteStatusListener($dispatcher, new NullLogger());

        $route = new ActiveRoute();
        $postUpdateArgs = $this->createPostUpdateArgs($route, [
            'status' => ['scheduled', 'in_progress'],
        ]);

        $listener->postUpdate($postUpdateArgs);
        $listener->postFlush($this->createPostFlushArgs());
    }

    public function testDispatchesRouteCompletedOnStatusChangeToCompleted(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(RouteCompletedEvent::class),
                RouteCompletedEvent::NAME,
            );

        $listener = new ActiveRouteStatusListener($dispatcher, new NullLogger());

        $route = new ActiveRoute();
        $postUpdateArgs = $this->createPostUpdateArgs($route, [
            'status' => ['in_progress', 'completed'],
        ]);

        $listener->postUpdate($postUpdateArgs);
        $listener->postFlush($this->createPostFlushArgs());
    }

    public function testIgnoresNonStatusChanges(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $listener = new ActiveRouteStatusListener($dispatcher, new NullLogger());

        $route = new ActiveRoute();
        $postUpdateArgs = $this->createPostUpdateArgs($route, [
            'currentLatitude' => ['0.0', '1.0'],
        ]);

        $listener->postUpdate($postUpdateArgs);
        $listener->postFlush($this->createPostFlushArgs());
    }

    public function testIgnoresNonActiveRouteEntities(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $listener = new ActiveRouteStatusListener($dispatcher, new NullLogger());

        $nonRouteEntity = new \stdClass();
        $uow = $this->createStub(UnitOfWork::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $args = new PostUpdateEventArgs($nonRouteEntity, $em);

        $listener->postUpdate($args);
        $listener->postFlush($this->createPostFlushArgs());
    }

    public function testPendingTransitionsAreClearedAfterFlush(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch');

        $listener = new ActiveRouteStatusListener($dispatcher, new NullLogger());

        $route = new ActiveRoute();
        $postUpdateArgs = $this->createPostUpdateArgs($route, [
            'status' => ['scheduled', 'in_progress'],
        ]);

        $listener->postUpdate($postUpdateArgs);
        $listener->postFlush($this->createPostFlushArgs());

        // Second flush should not re-dispatch
        $listener->postFlush($this->createPostFlushArgs());
    }

    /**
     * @param array<string, array{0: string, 1: string}> $changeSet
     */
    private function createPostUpdateArgs(ActiveRoute $route, array $changeSet): PostUpdateEventArgs
    {
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getEntityChangeSet')->willReturn($changeSet);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        return new PostUpdateEventArgs($route, $em);
    }

    private function createPostFlushArgs(): PostFlushEventArgs
    {
        return new PostFlushEventArgs(
            $this->createStub(EntityManagerInterface::class),
        );
    }
}
