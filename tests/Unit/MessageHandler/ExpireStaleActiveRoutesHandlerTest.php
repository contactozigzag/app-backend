<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ActiveRoute;
use App\Message\ExpireStaleActiveRoutesMessage;
use App\MessageHandler\ExpireStaleActiveRoutesHandler;
use App\Repository\ActiveRouteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ExpireStaleActiveRoutesHandlerTest extends TestCase
{
    public function testNoStaleRoutesIsNoOp(): void
    {
        $repo = $this->createStub(ActiveRouteRepository::class);
        $repo->method('findStaleNonTerminal')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $handler = new ExpireStaleActiveRoutesHandler($repo, $em, new NullLogger());
        $handler(new ExpireStaleActiveRoutesMessage());
    }

    public function testStaleRoutesAreCancelledAndStamped(): void
    {
        $route1 = new ActiveRoute();
        $route1->setStatus('in_progress');

        $route2 = new ActiveRoute();
        $route2->setStatus('scheduled');

        $repo = $this->createStub(ActiveRouteRepository::class);
        $repo->method('findStaleNonTerminal')->willReturn([$route1, $route2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $handler = new ExpireStaleActiveRoutesHandler($repo, $em, new NullLogger());
        $handler(new ExpireStaleActiveRoutesMessage());

        $this->assertSame('cancelled', $route1->getStatus());
        $this->assertSame('cancelled', $route2->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $route1->getCompletedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $route2->getCompletedAt());
    }

    public function testExistingCompletedAtIsPreserved(): void
    {
        $route = new ActiveRoute();
        $route->setStatus('in_progress');

        $stamped = new DateTimeImmutable('2026-04-12 18:00:00');
        $route->setCompletedAt($stamped);

        $repo = $this->createStub(ActiveRouteRepository::class);
        $repo->method('findStaleNonTerminal')->willReturn([$route]);

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new ExpireStaleActiveRoutesHandler($repo, $em, new NullLogger());
        $handler(new ExpireStaleActiveRoutesMessage());

        $this->assertSame('cancelled', $route->getStatus());
        $this->assertSame($stamped, $route->getCompletedAt());
    }

    public function testBatchSizeIsPassedToRepository(): void
    {
        $repo = $this->createMock(ActiveRouteRepository::class);
        $repo->expects($this->once())
            ->method('findStaleNonTerminal')
            ->with(200)
            ->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new ExpireStaleActiveRoutesHandler($repo, $em, new NullLogger());
        $handler(new ExpireStaleActiveRoutesMessage(batchSize: 200));
    }
}
