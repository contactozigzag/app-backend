<?php

declare(strict_types=1);

namespace App\Enum;

enum EventType: string
{
    case FIELD_TRIP = 'FIELD_TRIP';
    case SPORTS_EVENT = 'SPORTS_EVENT';
    case MUSEUM_VISIT = 'MUSEUM_VISIT';
    case OTHER = 'OTHER';
}
