<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Route;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Route>
 */
final class RouteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Route::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->words(3, true),
            'school' => SchoolFactory::new(),
            'type' => self::faker()->randomElement(['morning', 'afternoon']),
            'startLatitude' => (string) self::faker()->latitude(-55, -22),
            'startLongitude' => (string) self::faker()->longitude(-73, -53),
            'endLatitude' => (string) self::faker()->latitude(-55, -22),
            'endLongitude' => (string) self::faker()->longitude(-73, -53),
        ];
    }

    public function withDriver(mixed $driver): static
    {
        return $this->with([
            'driver' => $driver,
        ]);
    }

    public function withSchool(mixed $school): static
    {
        return $this->with([
            'school' => $school,
        ]);
    }
}
