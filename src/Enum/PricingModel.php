<?php

declare(strict_types=1);

namespace App\Enum;

enum PricingModel: string
{
    case FLAT = 'flat';
    case PER_ROUTE = 'per_route';
    case PER_STUDENT = 'per_student';
    case PER_ROUTE_STUDENT = 'per_route_student';

    public function label(): string
    {
        return match ($this) {
            self::FLAT => 'Flat',
            self::PER_ROUTE => 'Per Route',
            self::PER_STUDENT => 'Per Student',
            self::PER_ROUTE_STUDENT => 'Per Route/Student',
        };
    }

    public function requiresRoute(): bool
    {
        return match ($this) {
            self::PER_ROUTE, self::PER_ROUTE_STUDENT => true,
            self::FLAT, self::PER_STUDENT => false,
        };
    }

    public function usesPerStudentAmount(): bool
    {
        return match ($this) {
            self::PER_STUDENT, self::PER_ROUTE_STUDENT => true,
            self::FLAT, self::PER_ROUTE => false,
        };
    }
}
