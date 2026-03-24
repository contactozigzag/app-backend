<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Logging;

use App\Service\Logging\CorrelationIdService;
use PHPUnit\Framework\TestCase;

final class CorrelationIdServiceTest extends TestCase
{
    public function testGeneratesIdOnFirstGet(): void
    {
        $service = new CorrelationIdService();
        $id = $service->get();

        $this->assertSame(8, strlen($id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
    }

    public function testReturnsSameIdOnSubsequentGets(): void
    {
        $service = new CorrelationIdService();
        $first = $service->get();
        $second = $service->get();

        $this->assertSame($first, $second);
    }

    public function testSetOverridesGeneratedId(): void
    {
        $service = new CorrelationIdService();
        $service->set('abc12345');

        $this->assertSame('abc12345', $service->get());
    }

    public function testGenerateProducesUniqueIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = CorrelationIdService::generate();
        }

        // All IDs should be unique
        $this->assertCount(100, array_unique($ids));
    }

    public function testGenerateProduces8HexChars(): void
    {
        $id = CorrelationIdService::generate();

        $this->assertSame(8, strlen($id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
    }
}
