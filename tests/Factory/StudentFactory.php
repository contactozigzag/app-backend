<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Student;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Student>
 */
final class StudentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Student::class;
    }

    protected function defaults(): array
    {
        return [
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'identificationNumber' => self::faker()->unique()->numerify('##########'),
            'school' => SchoolFactory::new(),
        ];
    }

    public function withParent(User $parent): static
    {
        return $this->afterInstantiate(function (Student $student) use ($parent): void {
            $student->addParent($parent);
        });
    }
}
