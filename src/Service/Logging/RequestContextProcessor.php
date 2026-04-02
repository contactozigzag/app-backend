<?php

declare(strict_types=1);

namespace App\Service\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Enriches log records with HTTP request context (url, ip, method, etc.)
 * using Symfony's RequestStack instead of $_SERVER, which is unreliable
 * in FrankenPHP worker mode (stale between requests).
 */
final readonly class RequestContextProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();

        if (! $request instanceof Request) {
            return $record;
        }

        return $record->with(
            extra: array_merge($record->extra, [
                'url' => $request->getRequestUri(),
                'ip' => $request->getClientIp(),
                'http_method' => $request->getMethod(),
                'server' => $request->getHost(),
                'referrer' => $request->headers->get('referer'),
            ]),
        );
    }
}
