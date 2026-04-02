<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\School;
use App\Message\IndexSchoolMessage;
use App\Message\RemoveSchoolFromIndexMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches async messages to keep the OpenSearch schools index in sync.
 *
 * All OpenSearch calls are async via Messenger — never synchronous in the
 * listener — so a slow or unavailable OpenSearch node never blocks a write.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final readonly class SchoolIndexListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof School && $entity->getId() !== null) {
            $this->messageBus->dispatch(new IndexSchoolMessage($entity->getId()));
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof School && $entity->getId() !== null) {
            $this->messageBus->dispatch(new IndexSchoolMessage($entity->getId()));
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof School && $entity->getId() !== null) {
            $this->messageBus->dispatch(new RemoveSchoolFromIndexMessage($entity->getId()));
        }
    }
}
