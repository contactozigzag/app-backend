<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ActiveRouteStop;
use Symfony\Contracts\EventDispatcher\Event;

class BusArrivingEvent extends Event
{
    public const NAME = 'bus.arriving';

    public function __construct(
        private readonly ActiveRouteStop $stop,
        private readonly int $estimatedMinutes,
    ) {
    }

    public function getStop(): ActiveRouteStop
    {
        return $this->stop;
    }

    public function getEstimatedMinutes(): int
    {
        return $this->estimatedMinutes;
    }
}
