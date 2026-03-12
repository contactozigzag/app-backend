<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\RouteStop;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<RouteStop>
 */
final class RouteStopFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RouteStop::class;
    }

    protected function defaults(): array
    {
        return [
            'route' => RouteFactory::new(),
            'student' => StudentFactory::new(),
            'address' => AddressFactory::new(),
            'stopOrder' => self::faker()->numberBetween(0, 20),
        ];
    }

    public function withStudent(mixed $student): static
    {
        return $this->with([
            'student' => $student,
        ]);
    }

    public function withRoute(mixed $route): static
    {
        return $this->with([
            'route' => $route,
        ]);
    }
}
