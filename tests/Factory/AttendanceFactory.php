<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Attendance;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Attendance>
 */
final class AttendanceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Attendance::class;
    }

    protected function defaults(): array
    {
        return [
            'student' => StudentFactory::new(),
            'activeRouteStop' => ActiveRouteStopFactory::new(),
            'date' => new DateTimeImmutable('today'),
            'status' => 'picked_up',
        ];
    }

    public function withStudent(mixed $student): static
    {
        return $this->with([
            'student' => $student,
        ]);
    }

    public function droppedOff(): static
    {
        return $this->with([
            'status' => 'dropped_off',
            'droppedOffAt' => new DateTimeImmutable(),
        ]);
    }
}
