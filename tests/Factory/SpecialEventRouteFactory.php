<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Driver;
use App\Entity\SpecialEventRoute;
use App\Entity\Student;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SpecialEventRoute>
 */
final class SpecialEventRouteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SpecialEventRoute::class;
    }

    protected function defaults(): array
    {
        return [
            'school' => SchoolFactory::new(),
            'name' => self::faker()->sentence(3),
            'eventType' => EventType::OTHER,
            'routeMode' => RouteMode::ONE_WAY,
            'status' => SpecialEventRouteStatus::DRAFT,
            'eventDate' => new DateTimeImmutable('+7 days'),
        ];
    }

    public function published(): static
    {
        return $this->with([
            'status' => SpecialEventRouteStatus::PUBLISHED,
        ]);
    }

    public function inProgress(): static
    {
        return $this->with([
            'status' => SpecialEventRouteStatus::IN_PROGRESS,
            'outboundDepartureTime' => new DateTimeImmutable(),
        ]);
    }

    public function withDriver(Driver $driver): static
    {
        return $this->with([
            'assignedDriver' => $driver,
        ]);
    }

    /**
     * @param Student[] $students
     */
    public function withStudents(array $students): static
    {
        return $this->afterInstantiate(function (SpecialEventRoute $route) use ($students): void {
            foreach ($students as $student) {
                $route->addStudent($student);
            }
        });
    }
}
