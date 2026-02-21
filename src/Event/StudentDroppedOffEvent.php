<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ActiveRouteStop;
use App\Entity\Attendance;
use Symfony\Contracts\EventDispatcher\Event;

class StudentDroppedOffEvent extends Event
{
    public const NAME = 'student.dropped_off';

    public function __construct(
        private readonly Attendance $attendance,
        private readonly ActiveRouteStop $stop,
    ) {
    }

    public function getAttendance(): Attendance
    {
        return $this->attendance;
    }

    public function getStop(): ActiveRouteStop
    {
        return $this->stop;
    }
}
