<?php

declare(strict_types=1);

namespace App\Service\Payment;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IdempotencyService
{
    private const int TTL = 86400; // 24 hours
    private const float LOCK_TTL = 60.0; // 1-minute lock timeout

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Connection $connection,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process operation with idempotency protection using a Symfony Lock component
     *
     * @template T
     * @param string $key Idempotency key (UUID)
     * @param callable(): T $operation Operation to execute
     * @param int $ttl Cache TTL in seconds
     * @return T
     * @throws InvalidArgumentException
     */
    public function processWithIdempotency(string $key, callable $operation, int $ttl = self::TTL): mixed
    {
        $cacheKey = $this->getCacheKey($key);

        try {
            // Try to get from Redis cache first
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($operation, $key, $ttl) {
                $item->expiresAfter($ttl);

                // Acquire distributed lock using Symfony Lock component
                // This works across multiple PHP processes and servers
                $lock = $this->lockFactory->createLock("idempotency_{$key}", self::LOCK_TTL);

                if (!$lock->acquire()) {
                    $this->logger->warning('Failed to acquire idempotency lock', ['key' => $key]);

                    // Wait briefly and try to get a cached result
                    sleep(1);
                    $cached = $this->getCachedResult($key);
                    if ($cached !== null) {
                        return $cached;
                    }

                    throw new \RuntimeException('Concurrent idempotency key usage detected');
                }

                try {
                    $this->logger->info('Idempotency cache miss, executing operation', ['key' => $key]);

                    // Execute the operation
                    $result = $operation();

                    // Store in a database as a fallback
                    $this->storeInDatabase($key, $result, $ttl);

                    return $result;
                } finally {
                    // Always release the lock
                    $lock->release();
                }
            });
        } catch (\Exception $e) {
            $this->logger->error('Redis cache error, falling back to database', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Fallback to a database with locking
            return $this->processWithDatabaseFallback($key, $operation, $ttl);
        }
    }

    /**
     * Check if an idempotency key exists
     */
    public function exists(string $key): bool
    {
        $cacheKey = $this->getCacheKey($key);

        try {
            if ($this->cache->hasItem($cacheKey)) {
                $this->logger->info('Idempotency key found in cache', ['key' => $key]);
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Cache check failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        // Check database fallback
        try {
            $result = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM idempotency_records WHERE idempotency_key = ? AND expires_at > NOW()',
                [$key]
            );

            return (int) $result > 0;
        } catch (\Exception $e) {
            $this->logger->error('Database idempotency check failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete an idempotency key (for testing purposes)
     */
    public function delete(string $key): void
    {
        try {
            $cacheKey = $this->getCacheKey($key);
            $this->cache->delete($cacheKey);

            $this->connection->executeStatement(
                'DELETE FROM idempotency_records WHERE idempotency_key = ?',
                [$key]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete idempotency key', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processWithDatabaseFallback(string $key, callable $operation, int $ttl): mixed
    {
        // Acquire lock for database operations
        $lock = $this->lockFactory->createLock("idempotency_db_{$key}", self::LOCK_TTL);

        if (!$lock->acquire(true)) { // blocking=true, wait for lock
            throw new \RuntimeException('Failed to acquire database lock for idempotency');
        }

        try {
            // Check if already exists in a database
            $cached = $this->getFromDatabase($key);
            if ($cached !== null) {
                $this->logger->info('Idempotency key found in database', ['key' => $key]);
                return $cached;
            }

            // Execute operation and store a result
            $result = $operation();
            $this->storeInDatabase($key, $result, $ttl);

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function storeInDatabase(string $key, mixed $result, int $ttl): void
    {
        try {
            $expiresAt = new DateTimeImmutable()->modify("+{$ttl} seconds");
            $serializedResult = serialize($result);

            $this->connection->executeStatement(
                'INSERT INTO idempotency_records (idempotency_key, result, expires_at, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE result = VALUES(result), expires_at = VALUES(expires_at)',
                [$key, $serializedResult, $expiresAt->format('Y-m-d H:i:s')]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to store idempotency record in database', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getFromDatabase(string $key): mixed
    {
        try {
            $result = $this->connection->fetchAssociative(
                'SELECT result FROM idempotency_records
                 WHERE idempotency_key = ? AND expires_at > NOW()',
                [$key]
            );

            if ($result && isset($result['result'])) {
                return unserialize($result['result']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to get idempotency record from database', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getCachedResult(string $key): mixed
    {
        try {
            $cacheKey = $this->getCacheKey($key);
            return $this->cache->get($cacheKey, function () {
                return null;
            });
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getCacheKey(string $key): string
    {
        return "idempotency:{$key}";
    }
}
