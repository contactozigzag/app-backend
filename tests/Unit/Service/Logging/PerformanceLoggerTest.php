<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Logging;

use App\Service\Logging\PerformanceLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class PerformanceLoggerTest extends TestCase
{
    public function testReturnsCallableResult(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $performanceLogger = new PerformanceLogger($logger, 500);

        $result = $performanceLogger->measure('test_op', fn (): string => 'hello');

        $this->assertSame('hello', $result);
    }

    public function testLogsWarningOnSlowOperation(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Slow operation', self::callback(
                static fn (array $context): bool => $context['operation'] === 'slow_op'
                    && isset($context['duration_ms'])
                    && $context['duration_ms'] >= 0,
            ));

        // threshold of 0ms so everything is "slow"
        $performanceLogger = new PerformanceLogger($logger, 0);
        $performanceLogger->measure('slow_op', fn (): bool => true);
    }

    public function testDoesNotLogWhenUnderThreshold(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        // 10 seconds threshold — instant operation won't exceed
        $performanceLogger = new PerformanceLogger($logger, 10000);
        $performanceLogger->measure('fast_op', fn (): bool => true);
    }

    public function testLogsErrorOnException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Operation failed', self::callback(
                static fn (array $context): bool => $context['operation'] === 'failing_op'
                    && $context['exception'] === RuntimeException::class
                    && isset($context['duration_ms']),
            ));

        $performanceLogger = new PerformanceLogger($logger, 500);

        $this->expectException(RuntimeException::class);
        $performanceLogger->measure('failing_op', static function (): never {
            throw new RuntimeException('boom');
        });
    }

    public function testPassesContextToLogEntry(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Slow operation', self::callback(
                static fn (array $context): bool => $context['school_id'] === 5
                    && $context['operation'] === 'ctx_op',
            ));

        $performanceLogger = new PerformanceLogger($logger, 0);
        $performanceLogger->measure('ctx_op', fn (): bool => true, [
            'school_id' => 5,
        ]);
    }
}
