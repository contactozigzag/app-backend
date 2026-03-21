<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Driver;
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
 * Listens to Driver persist/update/remove AND User update (because firstName/lastName
 * live on User but are indexed in the drivers index).
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
        // (firstName/lastName live on User but are indexed in the drivers index)
        if ($entity instanceof User) {
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
        }
    }
}
