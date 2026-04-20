<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ActiveRoute;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when an ActiveRoute transitions to `cancelled` from any non-terminal
 * state (scheduled, in_progress, arriving). Fired by ActiveRouteStatusListener
 * via Doctrine postUpdate/postFlush, so both manual cancellation paths
 * (admin action, payment-preference superseding) and the nightly
 * ExpireStaleActiveRoutesHandler trigger it without extra plumbing.
 *
 * Consumers: TripMercureSubscriber publishes to the parent notification and
 * per-route tracking topics so the mobile app can move to a terminal screen
 * without waiting for the next polling cycle.
 */
class RouteCancelledEvent extends Event
{
    use HasEventId;

    public const NAME = 'route.cancelled';

    public function __construct(
        private readonly ActiveRoute $route,
    ) {
    }

    public function getRoute(): ActiveRoute
    {
        return $this->route;
    }
}
