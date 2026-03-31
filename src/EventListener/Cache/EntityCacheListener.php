<?php

declare(strict_types=1);

namespace App\EventListener\Cache;

use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\Student;
use App\Service\Cache\CacheInvalidator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Invalidates Redis cache entries whenever a Route, Driver, or Student is
 * persisted or removed via Doctrine. Works identically to AdminDashboardPublisher:
 * fire on postUpdate/postRemove, filter by entity type, delegate to CacheInvalidator.
 *
 * Cache invalidation is non-fatal: CacheInvalidator catches all Throwable and logs,
 * so a Redis hiccup never breaks the write path.
 */
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
readonly class EntityCacheListener
{
    public function __construct(
        private CacheInvalidator $cacheInvalidator,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    private function invalidate(object $entity): void
    {
        if ($entity instanceof Route) {
            $id = $entity->getId();
            if ($id !== null) {
                $this->cacheInvalidator->invalidateRoute($id);
            }

            return;
        }

        if ($entity instanceof Driver) {
            $id = $entity->getId();
            if ($id !== null) {
                $this->cacheInvalidator->invalidateDriver($id);
            }

            return;
        }

        if ($entity instanceof Student) {
            $id = $entity->getId();
            if ($id !== null) {
                $this->cacheInvalidator->invalidateStudent($id);
            }
        }
    }
}
