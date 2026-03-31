<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[AsController]
class HealthController extends AbstractController
{
    // Alert threshold: if Redis uses more than 80% of maxmemory, eviction rate
    // climbs and effective TTLs shrink. Time to tune pool sizes or TTLs.
    private const float REDIS_MEMORY_WARN_THRESHOLD = 0.8;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire(env: 'REDIS_URL')]
        private readonly string $redisUrl,
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];
        $httpStatus = 200;

        // Check database connectivity
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (Exception $exception) {
            $status = 'unhealthy';
            $httpStatus = 503;
            $checks['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $exception->getMessage(),
            ];
        }

        // Check disk space
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        $checks['disk'] = [
            'status' => $diskUsedPercent < 90 ? 'healthy' : 'warning',
            'used_percent' => round($diskUsedPercent, 2),
            'free_bytes' => $diskFree,
            'total_bytes' => $diskTotal,
        ];

        if ($diskUsedPercent >= 90) {
            $status = 'warning';
        }

        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        $checks['memory'] = [
            'status' => 'healthy',
            'usage_bytes' => $memoryUsage,
            'usage_human' => $this->formatBytes($memoryUsage),
            'limit' => $memoryLimit === -1 ? 'unlimited' : $this->formatBytes($memoryLimit),
        ];

        // Check Redis memory usage
        $redisCheck = $this->checkRedisMemory();
        $checks['redis'] = $redisCheck;
        if ($redisCheck['status'] === 'unhealthy') {
            $status = 'unhealthy';
            $httpStatus = 503;
        } elseif ($redisCheck['status'] === 'warning' && $status === 'healthy') {
            $status = 'warning';
        }

        // Application info
        $checks['application'] = [
            'status' => 'healthy',
            'environment' => $this->getParameter('kernel.environment'),
            'debug' => $this->getParameter('kernel.debug'),
            'php_version' => PHP_VERSION,
            'symfony_version' => Kernel::VERSION,
        ];

        return $this->json([
            'status' => $status,
            'timestamp' => new DateTimeImmutable()->format(DateTimeInterface::RFC3339),
            'checks' => $checks,
        ], $httpStatus);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        // Readiness check - is the app ready to serve traffic?
        try {
            $this->connection->executeQuery('SELECT 1');
            return $this->json([
                'status' => 'ready',
                'timestamp' => new DateTimeImmutable()->format(DateTimeInterface::RFC3339),
            ]);
        } catch (Exception) {
            return $this->json([
                'status' => 'not_ready',
                'reason' => 'Database not available',
                'timestamp' => new DateTimeImmutable()->format(DateTimeInterface::RFC3339),
            ], 503);
        }
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        // Liveness check - is the app alive?
        return $this->json([
            'status' => 'alive',
            'timestamp' => new DateTimeImmutable()->format(DateTimeInterface::RFC3339),
        ]);
    }

    /**
     * Checks Redis memory usage via INFO memory.
     * Warns at 80% of maxmemory — beyond that, allkeys-lru eviction rate rises
     * and effective TTLs shrink, which can cause unexpected cache misses.
     *
     * @return array{status: string, used_bytes: int|null, max_bytes: int|null, used_percent: float|null, message: string}
     */
    private function checkRedisMemory(): array
    {
        try {
            $connection = RedisAdapter::createConnection($this->redisUrl);

            // Works for both ext-redis (\Redis) and Predis (\Predis\ClientInterface)
            /** @var array<string, mixed> $info */
            $info = $connection->info('memory'); // @phpstan-ignore-line method.notFound

            $usedBytes = isset($info['used_memory']) && is_scalar($info['used_memory']) ? (int) $info['used_memory'] : null;
            $maxBytes = isset($info['maxmemory']) && is_scalar($info['maxmemory']) ? (int) $info['maxmemory'] : null;

            if ($usedBytes === null) {
                return [
                    'status' => 'healthy',
                    'used_bytes' => null,
                    'max_bytes' => null,
                    'used_percent' => null,
                    'message' => 'Redis reachable (memory stats unavailable)',
                ];
            }

            if ($maxBytes === null || $maxBytes === 0) {
                return [
                    'status' => 'healthy',
                    'used_bytes' => $usedBytes,
                    'max_bytes' => null,
                    'used_percent' => null,
                    'message' => 'Redis reachable (no maxmemory set)',
                ];
            }

            $usedPercent = $usedBytes / $maxBytes;
            $status = $usedPercent >= self::REDIS_MEMORY_WARN_THRESHOLD ? 'warning' : 'healthy';

            return [
                'status' => $status,
                'used_bytes' => $usedBytes,
                'max_bytes' => $maxBytes,
                'used_percent' => round($usedPercent * 100, 2),
                'message' => $status === 'warning'
                    ? sprintf('Redis memory above %.0f%% threshold — check TTLs or pool sizes', self::REDIS_MEMORY_WARN_THRESHOLD * 100)
                    : 'Redis memory usage normal',
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'unhealthy',
                'used_bytes' => null,
                'max_bytes' => null,
                'used_percent' => null,
                'message' => 'Redis unreachable: ' . $throwable->getMessage(),
            ];
        }
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return -1;
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
