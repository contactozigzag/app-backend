<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
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
        } catch (\Exception $exception) {
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

        // Application info
        $checks['application'] = [
            'status' => 'healthy',
            'environment' => $this->getParameter('kernel.environment'),
            'debug' => $this->getParameter('kernel.debug'),
            'php_version' => PHP_VERSION,
            'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
        ];

        return $this->json([
            'status' => $status,
            'timestamp' => new \DateTimeImmutable()->format(\DateTimeInterface::RFC3339),
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
                'timestamp' => new \DateTimeImmutable()->format(\DateTimeInterface::RFC3339),
            ]);
        } catch (\Exception) {
            return $this->json([
                'status' => 'not_ready',
                'reason' => 'Database not available',
                'timestamp' => new \DateTimeImmutable()->format(\DateTimeInterface::RFC3339),
            ], 503);
        }
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        // Liveness check - is the app alive?
        return $this->json([
            'status' => 'alive',
            'timestamp' => new \DateTimeImmutable()->format(\DateTimeInterface::RFC3339),
        ]);
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
