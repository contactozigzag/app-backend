<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Vehicle;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Vehicle>
 */
final class VehicleFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Vehicle::class;
    }

    protected function defaults(): array
    {
        return [
            'licensePlate' => strtoupper(self::faker()->bothify('??-###-??')),
            'make' => self::faker()->randomElement(['Toyota', 'Ford', 'Mercedes', 'Volkswagen']),
            'model' => self::faker()->word(),
            'capacity' => self::faker()->numberBetween(8, 50),
            'year' => self::faker()->numberBetween(2010, 2025),
            'color' => self::faker()->safeColorName(),
            'type' => self::faker()->randomElement(['bus', 'minibus', 'van']),
        ];
    }

    public function withDriver(mixed $driver): static
    {
        return $this->with([
            'driver' => $driver,
        ]);
    }
}
