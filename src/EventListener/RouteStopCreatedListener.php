<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RouteStop;
use App\Service\RouteStopNotificationPublisher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Notifies the route's driver via Mercure when a new unconfirmed
 * RouteStop is created (parent requests to add their child to a route).
 *
 * Uses the postPersist/postFlush two-phase pattern so the notification
 * fires only after the entity has been fully persisted.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
class RouteStopCreatedListener
{
    /**
     * @var list<RouteStop>
     */
    private array $pendingStops = [];

    public function __construct(
        private readonly RouteStopNotificationPublisher $notificationPublisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (! $entity instanceof RouteStop) {
            return;
        }

        // Only notify for unconfirmed stops (parent-created requests)
        if ($entity->getIsConfirmed()) {
            return;
        }

        $this->pendingStops[] = $entity;

        $this->logger->info('RouteStopCreatedListener: queued unconfirmed stop for driver notification', [
            'route_stop_id' => $entity->getId(),
            'route_id' => $entity->getRoute()?->getId(),
            'student_id' => $entity->getStudent()?->getId(),
        ]);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $stops = $this->pendingStops;
        $this->pendingStops = [];

        if ($stops === []) {
            return;
        }

        $this->logger->info('RouteStopCreatedListener: notifying drivers for new stop requests', [
            'count' => count($stops),
        ]);

        foreach ($stops as $routeStop) {
            $this->notificationPublisher->notifyDriverOfNewRequest($routeStop);
        }
    }
}
