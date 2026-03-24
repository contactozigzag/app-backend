<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Logs API request/response lifecycle and exceptions.
 */
class RequestResponseLogSubscriber implements EventSubscriberInterface
{
    private const array EXCLUDED_PATHS = [
        '/health',
        '/health/ready',
        '/health/live',
        '/_profiler',
        '/_wdt',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (! $this->shouldLog($path)) {
            return;
        }

        $request->attributes->set('_request_start_time', microtime(true));

        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->logger->info('API request', [
            'method' => $request->getMethod(),
            'path' => $path,
            'query_params' => $this->sanitizeQueryParams($request->query->all()),
            'content_type' => $request->getContentTypeFormat(),
            'ip' => $request->getClientIp(),
            'user_id' => $userId,
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (! $this->shouldLog($path)) {
            return;
        }

        $response = $event->getResponse();
        $startTime = $request->attributes->get('_request_start_time');
        $responseTimeMs = null;

        if (is_float($startTime)) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
        }

        $context = [
            'method' => $request->getMethod(),
            'path' => $path,
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => $responseTimeMs,
            'content_length' => $response->headers->get('Content-Length'),
        ];

        if ($responseTimeMs !== null && $responseTimeMs >= 500) {
            $this->logger->warning('Slow API response', $context);
        } else {
            $this->logger->info('API response', $context);
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (! $this->shouldLog($path)) {
            return;
        }

        $exception = $event->getThrowable();
        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $trace = $exception->getTrace();
        $shortTrace = array_map(
            static fn (array $frame): string => sprintf(
                '%s:%d',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0,
            ),
            array_slice($trace, 0, 5),
        );

        $this->logger->error('Unhandled exception', [
            'exception_class' => $exception::class,
            'message' => $this->sanitizeExceptionMessage($exception->getMessage()),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $shortTrace,
            'method' => $request->getMethod(),
            'path' => $path,
            'user_id' => $userId,
        ]);
    }

    private function shouldLog(string $path): bool
    {
        if (! str_starts_with($path, '/api')) {
            return false;
        }

        return array_all(self::EXCLUDED_PATHS, fn ($excluded): bool => ! str_starts_with($path, (string) $excluded));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function sanitizeQueryParams(array $params): array
    {
        $sensitiveKeys = ['token', 'key', 'secret', 'authorization', 'access_token'];

        foreach (array_keys($params) as $key) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }

    private function sanitizeExceptionMessage(string $message): string
    {
        // Strip DSN strings (database URLs, AMQP URLs, etc.)
        $message = (string) preg_replace(
            '#(mysql|pgsql|amqp|redis|https?)://[^\s]+#',
            '[REDACTED_DSN]',
            $message,
        );

        // Strip anything that looks like a JWT or long token
        return (string) preg_replace(
            '#eyJ[A-Za-z0-9_-]{20,}#',
            '[REDACTED_TOKEN]',
            $message,
        );
    }
}
