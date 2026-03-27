<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\ActiveRoute;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ActiveRoute>
 */
final class ActiveRouteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ActiveRoute::class;
    }

    protected function defaults(): array
    {
        return [
            'routeTemplate' => RouteFactory::new(),
            'driver' => DriverFactory::new(),
            'date' => new DateTimeImmutable('today'),
            'status' => 'scheduled',
        ];
    }

    public function inProgress(): static
    {
        return $this->with([
            'status' => 'in_progress',
            'startedAt' => new DateTimeImmutable(),
        ]);
    }

    public function completed(): static
    {
        return $this->with([
            'status' => 'completed',
            'startedAt' => new DateTimeImmutable('-1 hour'),
            'completedAt' => new DateTimeImmutable(),
        ]);
    }

    public function withDriver(mixed $driver): static
    {
        return $this->with([
            'driver' => $driver,
        ]);
    }

    public function withRoute(mixed $route): static
    {
        return $this->with([
            'routeTemplate' => $route,
        ]);
    }
}
