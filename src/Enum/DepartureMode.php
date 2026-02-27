<?php

declare(strict_types=1);

namespace App\Enum;

enum DepartureMode: string
{
    case GROUPED = 'GROUPED';
    case INDIVIDUAL = 'INDIVIDUAL';
}
