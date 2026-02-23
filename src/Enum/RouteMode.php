<?php

declare(strict_types=1);

namespace App\Enum;

enum RouteMode: string
{
    case FULL_DAY_TRIP = 'FULL_DAY_TRIP';
    case RETURN_TO_SCHOOL = 'RETURN_TO_SCHOOL';
    case ONE_WAY = 'ONE_WAY';
}
