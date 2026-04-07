<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ActiveRoute;
use Symfony\Contracts\EventDispatcher\Event;

class RouteArrivingEvent extends Event
{
    use HasEventId;

    public const NAME = 'route.arriving';

    public function __construct(
        private readonly ActiveRoute $route,
    ) {
    }

    public function getRoute(): ActiveRoute
    {
        return $this->route;
    }
}
