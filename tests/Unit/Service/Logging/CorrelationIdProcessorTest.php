<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Logging;

use App\Service\Logging\CorrelationIdProcessor;
use App\Service\Logging\CorrelationIdService;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class CorrelationIdProcessorTest extends TestCase
{
    public function testAddsCorrelationIdAndMetadata(): void
    {
        $correlationIdService = new CorrelationIdService();
        $correlationIdService->set('abc12345');

        $processor = new CorrelationIdProcessor($correlationIdService, 'test', '1.0.0');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test message',
            context: [
                'user_id' => 42,
            ],
        );

        $processed = $processor($record);

        $this->assertSame('abc12345', $processed->extra['correlation_id']);
        $this->assertSame('test', $processed->extra['environment']);
        $this->assertSame('1.0.0', $processed->extra['app_version']);
        $this->assertSame('zigzag-api', $processed->extra['service']);
    }

    public function testSanitizesSensitiveContextValues(): void
    {
        $correlationIdService = new CorrelationIdService();
        $processor = new CorrelationIdProcessor($correlationIdService, 'prod', '2.0.0');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test',
            context: [
                'user_id' => 1,
                'password' => 'should-be-redacted',
                'api_key' => 'sk-secret',
            ],
        );

        $processed = $processor($record);

        $this->assertSame(1, $processed->context['user_id']);
        $this->assertSame('[REDACTED]', $processed->context['password']);
        $this->assertSame('[REDACTED]', $processed->context['api_key']);
    }

    public function testPreservesExistingExtra(): void
    {
        $correlationIdService = new CorrelationIdService();
        $correlationIdService->set('test1234');

        $processor = new CorrelationIdProcessor($correlationIdService, 'dev', '1.0.0');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Debug,
            message: 'Test',
            context: [],
            extra: [
                'existing_key' => 'preserved',
            ],
        );

        $processed = $processor($record);

        $this->assertSame('preserved', $processed->extra['existing_key']);
        $this->assertSame('test1234', $processed->extra['correlation_id']);
    }
}
