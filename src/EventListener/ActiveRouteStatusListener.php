<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ActiveRoute;
use App\Event\RouteArrivingEvent;
use App\Event\RouteCompletedEvent;
use App\Event\RouteStartedEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Detects ActiveRoute status transitions via Doctrine lifecycle callbacks
 * and dispatches the corresponding domain events after flush.
 *
 * Uses the two-phase postUpdate/postFlush pattern to ensure events fire
 * only after the transaction is committed.
 */
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class ActiveRouteStatusListener
{
    /**
     * @var list<array{route: ActiveRoute, oldStatus: string, newStatus: string}>
     */
    private array $pendingTransitions = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (! $entity instanceof ActiveRoute) {
            return;
        }

        $changeSet = $args->getObjectManager()
            ->getUnitOfWork()
            ->getEntityChangeSet($entity);

        if (! isset($changeSet['status'])) {
            return;
        }

        /** @var array{0: string, 1: string} $statusChange */
        $statusChange = $changeSet['status'];

        $this->pendingTransitions[] = [
            'route' => $entity,
            'oldStatus' => $statusChange[0],
            'newStatus' => $statusChange[1],
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $transitions = $this->pendingTransitions;
        $this->pendingTransitions = [];

        foreach ($transitions as $transition) {
            $this->dispatchForTransition(
                $transition['route'],
                $transition['oldStatus'],
                $transition['newStatus'],
            );
        }
    }

    private function dispatchForTransition(ActiveRoute $route, string $oldStatus, string $newStatus): void
    {
        if ($newStatus === 'in_progress' && $oldStatus !== 'in_progress') {
            $this->logger->info('ActiveRouteStatusListener: route started', [
                'route_id' => $route->getId(),
            ]);

            $this->eventDispatcher->dispatch(
                new RouteStartedEvent($route),
                RouteStartedEvent::NAME,
            );

            return;
        }

        if ($newStatus === 'arriving' && $oldStatus !== 'arriving') {
            $this->logger->info('ActiveRouteStatusListener: route arriving', [
                'route_id' => $route->getId(),
            ]);

            $this->eventDispatcher->dispatch(
                new RouteArrivingEvent($route),
                RouteArrivingEvent::NAME,
            );

            return;
        }

        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $this->logger->info('ActiveRouteStatusListener: route completed', [
                'route_id' => $route->getId(),
            ]);

            $this->eventDispatcher->dispatch(
                new RouteCompletedEvent($route),
                RouteCompletedEvent::NAME,
            );
        }
    }
}
