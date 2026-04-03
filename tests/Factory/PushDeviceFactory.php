<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\PushDevice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PushDevice>
 */
final class PushDeviceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PushDevice::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'expoPushToken' => 'ExponentPushToken[' . self::faker()->uuid() . ']',
            'platform' => self::faker()->randomElement(['ios', 'android']),
            'deviceName' => self::faker()->optional()->word(),
            'osVersion' => self::faker()->optional()->numerify('##.#'),
        ];
    }

    public function inactive(): static
    {
        return $this->afterInstantiate(function (PushDevice $device): void {
            $device->deactivate();
        });
    }
}
