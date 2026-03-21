<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Driver;
use App\Entity\User;
use App\EventListener\DriverIndexListener;
use App\Message\IndexDriverMessage;
use App\Message\RemoveDriverFromIndexMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DriverIndexListenerTest extends TestCase
{
    public function testPostPersistOnDriverDispatchesIndexMessage(): void
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('getId')->willReturn(7);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(fn (IndexDriverMessage $msg): bool => $msg->driverId === 7))
            ->willReturn(new Envelope(new IndexDriverMessage(7)));

        $listener = new DriverIndexListener($bus);
        $listener->postPersist(new PostPersistEventArgs($driver, $this->createStub(EntityManagerInterface::class)));
    }

    public function testPostUpdateOnDriverDispatchesIndexMessage(): void
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('getId')->willReturn(7);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(fn (IndexDriverMessage $msg): bool => $msg->driverId === 7))
            ->willReturn(new Envelope(new IndexDriverMessage(7)));

        $listener = new DriverIndexListener($bus);
        $listener->postUpdate(new PostUpdateEventArgs($driver, $this->createStub(EntityManagerInterface::class)));
    }

    public function testPreRemoveOnDriverDispatchesRemoveMessage(): void
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('getId')->willReturn(7);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(fn (RemoveDriverFromIndexMessage $msg): bool => $msg->driverId === 7))
            ->willReturn(new Envelope(new RemoveDriverFromIndexMessage(7)));

        $listener = new DriverIndexListener($bus);
        $listener->preRemove(new PreRemoveEventArgs($driver, $this->createStub(EntityManagerInterface::class)));
    }

    public function testPostUpdateOnUserWithDriverDispatchesIndexMessage(): void
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('getId')->willReturn(5);

        $user = $this->createStub(User::class);
        $user->method('getDriver')->willReturn($driver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(fn (IndexDriverMessage $msg): bool => $msg->driverId === 5))
            ->willReturn(new Envelope(new IndexDriverMessage(5)));

        $listener = new DriverIndexListener($bus);
        $listener->postUpdate(new PostUpdateEventArgs($user, $this->createStub(EntityManagerInterface::class)));
    }

    public function testPostUpdateOnUserWithoutDriverDoesNotDispatch(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getDriver')->willReturn(null);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $listener = new DriverIndexListener($bus);
        $listener->postUpdate(new PostUpdateEventArgs($user, $this->createStub(EntityManagerInterface::class)));
    }
}
