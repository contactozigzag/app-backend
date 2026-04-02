<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ActiveRouteStop;
use Symfony\Contracts\EventDispatcher\Event;

class StopArrivedEvent extends Event
{
    use HasEventId;

    public const NAME = 'stop.arrived';

    public function __construct(
        private readonly ActiveRouteStop $stop,
    ) {
    }

    public function getStop(): ActiveRouteStop
    {
        return $this->stop;
    }
}
