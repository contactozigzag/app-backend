<?php

declare(strict_types=1);

namespace App\Service\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

/**
 * Tag-based cache invalidation for the main domain entities.
 *
 * Each pool (routes, drivers, students) uses tag-aware Redis adapters so a
 * single call to invalidateTags() flushes all related keys atomically —
 * regardless of how many cache entries exist for that entity.
 *
 * Invalidation rules:
 *  - Route updated/removed    → flush route_{id} + route_{id}_students
 *  - Driver updated/removed   → flush driver_{id}
 *  - Student updated/removed  → flush student_{id} + optionally route_{id}
 *  - Trip status changed      → flush viaje_{id} + driver_{id}_viajes_{date}
 *  - MP fees changed          → use: php bin/console cache:pool:clear cache.mp_fees
 *  - App config changed       → use: php bin/console cache:pool:clear cache.config
 */
readonly class CacheInvalidator
{
    public function __construct(
        #[Autowire(service: 'cache.routes')]
        private TagAwareCacheInterface $routesCache,
        #[Autowire(service: 'cache.drivers')]
        private TagAwareCacheInterface $driversCache,
        #[Autowire(service: 'cache.students')]
        private TagAwareCacheInterface $studentsCache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Invalidate all caches related to a route: its own entries plus
     * the student manifest cached under this route ID.
     */
    public function invalidateRoute(int $routeId): void
    {
        $tags = ['route_' . $routeId, 'route_' . $routeId . '_students'];

        $this->invalidateTags($this->routesCache, $tags, 'routes', $routeId);
        $this->invalidateTags($this->studentsCache, ['route_' . $routeId], 'students', $routeId);
    }

    /**
     * Invalidate all caches for a driver (profile, vehicle info, route list).
     */
    public function invalidateDriver(int $driverId): void
    {
        $this->invalidateTags($this->driversCache, ['driver_' . $driverId], 'drivers', $driverId);
    }

    /**
     * Invalidate all caches for a student. When the student belongs to a route,
     * pass $routeId to also flush the route's student manifest cache.
     */
    public function invalidateStudent(int $studentId, ?int $routeId = null): void
    {
        $tags = ['student_' . $studentId];
        $this->invalidateTags($this->studentsCache, $tags, 'students', $studentId);

        if ($routeId !== null) {
            $this->invalidateTags($this->studentsCache, ['route_' . $routeId], 'students', $routeId);
        }
    }

    /**
     * @param string[] $tags
     */
    private function invalidateTags(TagAwareCacheInterface $pool, array $tags, string $poolName, int $entityId): void
    {
        try {
            $pool->invalidateTags($tags);
        } catch (Throwable $throwable) {
            // Cache invalidation failure must never break the write path.
            // Log and continue — stale data will expire via TTL.
            $this->logger->error('CacheInvalidator: failed to invalidate tags', [
                'pool' => $poolName,
                'entity_id' => $entityId,
                'tags' => $tags,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
