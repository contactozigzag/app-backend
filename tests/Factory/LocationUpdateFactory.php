<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\ActiveRoute;
use App\Entity\LocationUpdate;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<LocationUpdate>
 */
final class LocationUpdateFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return LocationUpdate::class;
    }

    protected function defaults(): array
    {
        return [
            'driver' => DriverFactory::new(),
            'latitude' => '-34.603722',
            'longitude' => '-58.381592',
            'timestamp' => new DateTimeImmutable(),
        ];
    }

    public function withCoordinates(string $lat, string $lng): static
    {
        return $this->with([
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    public function withActiveRoute(ActiveRoute $route): static
    {
        return $this->with([
            'activeRoute' => $route,
        ]);
    }

    public function stale(int $ageSeconds = 600): static
    {
        return $this->with([
            'timestamp' => new DateTimeImmutable(sprintf('-%d seconds', $ageSeconds)),
        ]);
    }
}
