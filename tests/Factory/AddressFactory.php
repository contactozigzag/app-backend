<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Address;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Address>
 */
final class AddressFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Address::class;
    }

    protected function defaults(): array
    {
        return [
            'streetAddress' => self::faker()->streetAddress(),
            'city' => self::faker()->city(),
            'state' => self::faker()->word(),
            'country' => 'AR',
            'postalCode' => self::faker()->postcode(),
            'latitude' => (string) self::faker()->latitude(-55, -22),
            'longitude' => (string) self::faker()->longitude(-73, -53),
        ];
    }
}
