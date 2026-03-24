<?php

declare(strict_types=1);

namespace App\Service\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Monolog processor that enriches every log record with correlation ID,
 * environment, app version, and service name. Also applies LogSanitizer
 * as a safety net against accidental secret leaks.
 */
class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CorrelationIdService $correlationIdService,
        #[Autowire(env: 'APP_ENV')]
        private readonly string $environment,
        #[Autowire(env: 'APP_VERSION')]
        private readonly string $appVersion,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        $extra['correlation_id'] = $this->correlationIdService->get();
        $extra['environment'] = $this->environment;
        $extra['app_version'] = $this->appVersion;
        $extra['service'] = 'zigzag-api';

        $sanitizedContext = LogSanitizer::sanitizeContext($record->context);

        return $record->with(extra: $extra, context: $sanitizedContext);
    }
}
