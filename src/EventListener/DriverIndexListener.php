<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\User;
use App\Message\IndexDriverMessage;
use App\Message\RemoveDriverFromIndexMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches async messages to keep the OpenSearch drivers index in sync.
 *
 * Listens to:
 * - Driver persist/update/remove
 * - User update (firstName/lastName live on User but are indexed)
 * - Route persist/update/remove (school_id is derived from route assignments)
 *
 * All OpenSearch calls are async via Messenger — never synchronous in the listener.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final readonly class DriverIndexListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Driver && $entity->getId() !== null) {
            $this->messageBus->dispatch(new IndexDriverMessage($entity->getId()));

            return;
        }

        // Route created with a driver — re-index that driver (school_id may have changed)
        if ($entity instanceof Route) {
            $driver = $entity->getDriver();

            if ($driver instanceof Driver && $driver->getId() !== null) {
                $this->messageBus->dispatch(new IndexDriverMessage($driver->getId()));
            }
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Driver && $entity->getId() !== null) {
            $this->messageBus->dispatch(new IndexDriverMessage($entity->getId()));

            return;
        }

        // User updated — if this user has an associated Driver, re-index it
        if ($entity instanceof User) {
            $driver = $entity->getDriver();

            if ($driver instanceof Driver && $driver->getId() !== null) {
                $this->messageBus->dispatch(new IndexDriverMessage($driver->getId()));
            }

            return;
        }

        // Route updated — re-index the driver (driver or school assignment may have changed)
        if ($entity instanceof Route) {
            $driver = $entity->getDriver();

            if ($driver instanceof Driver && $driver->getId() !== null) {
                $this->messageBus->dispatch(new IndexDriverMessage($driver->getId()));
            }
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Driver && $entity->getId() !== null) {
            $this->messageBus->dispatch(new RemoveDriverFromIndexMessage($entity->getId()));

            return;
        }

        // Route being removed — re-index the driver (school_id array may shrink)
        if ($entity instanceof Route) {
            $driver = $entity->getDriver();

            if ($driver instanceof Driver && $driver->getId() !== null) {
                $this->messageBus->dispatch(new IndexDriverMessage($driver->getId()));
            }
        }
    }
}
