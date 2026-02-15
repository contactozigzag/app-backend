<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case PAYMENT_FAILED = 'payment_failed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::PAYMENT_FAILED => 'Payment Failed',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
