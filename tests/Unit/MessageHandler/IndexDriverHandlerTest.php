<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Driver;
use App\Message\IndexDriverMessage;
use App\MessageHandler\IndexDriverHandler;
use App\Repository\DriverRepository;
use App\Service\OpenSearch\DriverSearchService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class IndexDriverHandlerTest extends TestCase
{
    public function testIndexCalledWhenDriverExists(): void
    {
        $driver = $this->createStub(Driver::class);

        $repo = $this->createStub(DriverRepository::class);
        $repo->method('find')->willReturn($driver);

        $searchService = $this->createMock(DriverSearchService::class);
        $searchService->expects($this->once())->method('index')->with($driver);

        $handler = new IndexDriverHandler($searchService, $repo, new NullLogger());
        $handler(new IndexDriverMessage(7));
    }

    public function testDriverDeletedBetweenDispatchAndHandling(): void
    {
        $repo = $this->createStub(DriverRepository::class);
        $repo->method('find')->willReturn(null);

        $searchService = $this->createMock(DriverSearchService::class);
        $searchService->expects($this->never())->method('index');

        $handler = new IndexDriverHandler($searchService, $repo, new NullLogger());
        // Should not throw
        $handler(new IndexDriverMessage(999));
    }

    public function testOpenSearchFailurePropagatesForRetry(): void
    {
        $driver = $this->createStub(Driver::class);

        $repo = $this->createStub(DriverRepository::class);
        $repo->method('find')->willReturn($driver);

        $searchService = $this->createStub(DriverSearchService::class);
        $searchService->method('index')->willThrowException(new RuntimeException('OpenSearch down'));

        $handler = new IndexDriverHandler($searchService, $repo, new NullLogger());

        $this->expectException(RuntimeException::class);
        $handler(new IndexDriverMessage(7));
    }
}
