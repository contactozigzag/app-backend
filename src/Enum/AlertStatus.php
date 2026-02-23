<?php

declare(strict_types=1);

namespace App\Enum;

enum AlertStatus: string
{
    case PENDING = 'PENDING';
    case RESPONDED = 'RESPONDED';
    case RESOLVED = 'RESOLVED';
}
