<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\RemoveDriverFromIndexMessage;
use App\MessageHandler\RemoveDriverFromIndexHandler;
use App\Service\OpenSearch\DriverSearchService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RemoveDriverFromIndexHandlerTest extends TestCase
{
    public function testDeleteCalledWithCorrectId(): void
    {
        $searchService = $this->createMock(DriverSearchService::class);
        $searchService->expects($this->once())->method('delete')->with(42);

        $handler = new RemoveDriverFromIndexHandler($searchService);
        $handler(new RemoveDriverFromIndexMessage(42));
    }

    public function testOpenSearchFailurePropagatesForRetry(): void
    {
        $searchService = $this->createStub(DriverSearchService::class);
        $searchService->method('delete')->willThrowException(new RuntimeException('OpenSearch down'));

        $handler = new RemoveDriverFromIndexHandler($searchService);

        $this->expectException(RuntimeException::class);
        $handler(new RemoveDriverFromIndexMessage(42));
    }
}
