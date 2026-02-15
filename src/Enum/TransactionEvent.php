<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionEvent: string
{
    case CREATED = 'created';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
    case WEBHOOK_RECEIVED = 'webhook_received';
    case STATUS_UPDATED = 'status_updated';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::REFUNDED => 'Refunded',
            self::CANCELLED => 'Cancelled',
            self::WEBHOOK_RECEIVED => 'Webhook Received',
            self::STATUS_UPDATED => 'Status Updated',
        };
    }
}
