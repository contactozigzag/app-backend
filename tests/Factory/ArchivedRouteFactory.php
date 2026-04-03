<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\ArchivedRoute;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ArchivedRoute>
 */
final class ArchivedRouteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ArchivedRoute::class;
    }

    protected function defaults(): array
    {
        return [
            'school' => SchoolFactory::new(),
            'originalActiveRouteId' => self::faker()->randomNumber(4),
            'routeName' => self::faker()->words(3, true),
            'routeType' => 'morning',
            'driverName' => self::faker()->name(),
            'date' => new DateTimeImmutable(),
            'status' => 'completed',
            'totalStops' => 5,
            'completedStops' => 5,
            'skippedStops' => 0,
            'studentsPickedUp' => 5,
            'studentsDroppedOff' => 0,
            'noShows' => 0,
        ];
    }
}
