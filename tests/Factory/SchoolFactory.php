<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\School;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<School>
 */
final class SchoolFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return School::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->company() . ' School',
        ];
    }
}
