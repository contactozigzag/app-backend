<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Logging;

use App\Service\Logging\TraceContextProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class TraceContextProcessorTest extends TestCase
{
    public function testNoTraceContextReturnsRecordUnchanged(): void
    {
        $processor = new TraceContextProcessor();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
        );

        $result = $processor($record);

        // Without an active span, trace_id is all-zeros → processor should skip
        $this->assertArrayNotHasKey('trace_id', $result->extra);
        $this->assertArrayNotHasKey('span_id', $result->extra);
    }

    public function testExistingExtraFieldsArePreserved(): void
    {
        $processor = new TraceContextProcessor();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
            extra: [
                'existing_key' => 'existing_value',
            ],
        );

        $result = $processor($record);

        $this->assertSame('existing_value', $result->extra['existing_key']);
    }
}
