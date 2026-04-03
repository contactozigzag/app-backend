<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\NotificationPreference;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<NotificationPreference>
 */
final class NotificationPreferenceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return NotificationPreference::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'emailEnabled' => true,
            'smsEnabled' => true,
            'pushEnabled' => true,
            'notifyOnArriving' => true,
            'notifyOnPickup' => true,
            'notifyOnDropoff' => true,
            'notifyOnRouteStart' => false,
            'notifyOnDelay' => true,
            'notifyOnCancellation' => true,
            'arrivalNotificationMinutes' => 5,
        ];
    }
}
