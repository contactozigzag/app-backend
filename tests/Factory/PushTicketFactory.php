<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\PushTicket;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PushTicket>
 */
final class PushTicketFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PushTicket::class;
    }

    protected function defaults(): array
    {
        return [
            'ticketId' => self::faker()->uuid(),
            'expoPushToken' => 'ExponentPushToken[' . self::faker()->uuid() . ']',
            'notificationType' => self::faker()->randomElement(['trips', 'payments', 'messages', 'reminders']),
            'status' => 'ok',
        ];
    }

    public function error(): static
    {
        return $this->afterInstantiate(function (PushTicket $ticket): void {
            $ticket->markError('DeviceNotRegistered', [
                'details' => 'Token expired',
            ]);
        });
    }
}
