<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\SpecialEventRouteStop;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SpecialEventRouteStop>
 */
final class SpecialEventRouteStopFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SpecialEventRouteStop::class;
    }

    protected function defaults(): array
    {
        return [
            'specialEventRoute' => SpecialEventRouteFactory::new(),
            'student' => StudentFactory::new(),
            'address' => AddressFactory::new(),
            'stopOrder' => 1,
            'status' => 'pending',
        ];
    }
}
