<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\IdempotencyRecord;
use App\Repository\IdempotencyRecordRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IdempotencyService
{
    private const int TTL = 86400; // 24 hours

    private const float LOCK_TTL = 60.0; // 1-minute lock timeout

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $entityManager,
        private readonly IdempotencyRecordRepository $repository,
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
                $lock = $this->lockFactory->createLock('idempotency_' . $key, self::LOCK_TTL);

                if (! $lock->acquire()) {
                    $this->logger->warning('Failed to acquire idempotency lock', [
                        'key' => $key,
                    ]);

                    // Wait briefly and try to get a cached result
                    sleep(1);
                    $cached = $this->getCachedResult($key);
                    if ($cached !== null) {
                        return $cached;
                    }

                    throw new RuntimeException('Concurrent idempotency key usage detected');
                }

                try {
                    $this->logger->info('Idempotency cache miss, executing operation', [
                        'key' => $key,
                    ]);

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
        } catch (Exception $exception) {
            $this->logger->error('Redis cache error, falling back to database', [
                'key' => $key,
                'error' => $exception->getMessage(),
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
                $this->logger->info('Idempotency key found in cache', [
                    'key' => $key,
                ]);
                return true;
            }
        } catch (Exception $exception) {
            $this->logger->warning('Cache check failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }

        // Check database fallback
        try {
            return $this->repository->findActiveByKey($key) instanceof IdempotencyRecord;
        } catch (Exception $exception) {
            $this->logger->error('Database idempotency check failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
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

            $record = $this->repository->findByKey($key);
            if ($record instanceof IdempotencyRecord) {
                $this->entityManager->remove($record);
                $this->entityManager->flush();
            }
        } catch (Exception $exception) {
            $this->logger->error('Failed to delete idempotency key', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function processWithDatabaseFallback(string $key, callable $operation, int $ttl): mixed
    {
        // Acquire lock for database operations
        $lock = $this->lockFactory->createLock('idempotency_db_' . $key, self::LOCK_TTL);

        if (! $lock->acquire(true)) { // blocking=true, wait for lock
            throw new RuntimeException('Failed to acquire database lock for idempotency');
        }

        try {
            // Check if already exists in a database
            $cached = $this->getFromDatabase($key);
            if ($cached !== null) {
                $this->logger->info('Idempotency key found in database', [
                    'key' => $key,
                ]);
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
            $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', $ttl));
            $serializedResult = serialize($result);

            $record = $this->repository->findByKey($key);
            if ($record instanceof IdempotencyRecord) {
                $record->setResult($serializedResult);
                $record->setExpiresAt($expiresAt);
            } else {
                $record = new IdempotencyRecord($key, $serializedResult, $expiresAt);
                $this->entityManager->persist($record);
            }

            $this->entityManager->flush();
        } catch (Exception $exception) {
            $this->logger->error('Failed to store idempotency record in database', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function getFromDatabase(string $key): mixed
    {
        try {
            $record = $this->repository->findActiveByKey($key);
            if ($record instanceof IdempotencyRecord) {
                return unserialize($record->getResult());
            }
        } catch (Exception $exception) {
            $this->logger->error('Failed to get idempotency record from database', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    private function getCachedResult(string $key): mixed
    {
        try {
            $cacheKey = $this->getCacheKey($key);
            return $this->cache->get($cacheKey, fn (): null => null);
        } catch (Exception) {
            return null;
        }
    }

    private function getCacheKey(string $key): string
    {
        return 'idempotency:' . $key;
    }
}
