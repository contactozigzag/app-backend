<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\ActiveRouteStop;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ActiveRouteStop>
 */
final class ActiveRouteStopFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ActiveRouteStop::class;
    }

    protected function defaults(): array
    {
        return [
            'activeRoute' => ActiveRouteFactory::new(),
            'student' => StudentFactory::new(),
            'address' => AddressFactory::new(),
            'stopOrder' => self::faker()->numberBetween(1, 10),
            'status' => 'pending',
            'geofenceRadius' => 50,
        ];
    }

    public function withActiveRoute(mixed $activeRoute): static
    {
        return $this->with([
            'activeRoute' => $activeRoute,
        ]);
    }

    public function withStudent(mixed $student): static
    {
        return $this->with([
            'student' => $student,
        ]);
    }
}
