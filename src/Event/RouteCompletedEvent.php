<?php

namespace App\Event;

use App\Entity\ActiveRoute;
use Symfony\Contracts\EventDispatcher\Event;

class RouteCompletedEvent extends Event
{
    public const NAME = 'route.completed';

    public function __construct(
        private readonly ActiveRoute $route,
    ) {
    }

    public function getRoute(): ActiveRoute
    {
        return $this->route;
    }
}
