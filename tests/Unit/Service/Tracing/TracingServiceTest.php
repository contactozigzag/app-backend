<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Tracing;

use App\Service\Tracing\TracingService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TracingServiceTest extends TestCase
{
    private TracingService $service;

    protected function setUp(): void
    {
        $this->service = new TracingService();
    }

    public function testTraceReturnsCallableResult(): void
    {
        /** @var mixed $result */
        $result = $this->service->trace('test.span', static fn (): string => 'hello');

        $this->assertSame('hello', $result);
    }

    public function testTracePassesAttributesWithoutError(): void
    {
        /** @var mixed $result */
        $result = $this->service->trace(
            'test.with_attrs',
            static fn (): int => 42,
            [
                'key' => 'value',
                'count' => 5,
            ],
        );

        $this->assertSame(42, $result);
    }

    public function testTraceRethrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->service->trace('test.fail', static function (): never {
            throw new RuntimeException('boom');
        });
    }
}
