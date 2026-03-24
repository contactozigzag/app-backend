<?php

declare(strict_types=1);

namespace App\Service\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Wraps operations with timing and logs slow operations as warnings.
 */
class PerformanceLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'int:LOG_SLOW_THRESHOLD_MS')]
        private readonly int $slowThreshold = 500,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function measure(string $operation, callable $fn, array $context = []): mixed
    {
        $start = microtime(true);

        try {
            $result = $fn();
            $elapsed = (int) ((microtime(true) - $start) * 1000);

            if ($elapsed >= $this->slowThreshold) {
                $this->logger->warning('Slow operation', [
                    'operation' => $operation,
                    'duration_ms' => $elapsed,
                    ...$context,
                ]);
            }

            return $result;
        } catch (Throwable $throwable) {
            $elapsed = (int) ((microtime(true) - $start) * 1000);

            $this->logger->error('Operation failed', [
                'operation' => $operation,
                'duration_ms' => $elapsed,
                'exception' => $throwable::class,
                ...$context,
            ]);

            throw $throwable;
        }
    }
}
