<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DriverAlert>
 */
final class DriverAlertFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DriverAlert::class;
    }

    protected function defaults(): array
    {
        return [
            'distressedDriver' => DriverFactory::new(),
            'status' => AlertStatus::PENDING,
            'locationLat' => '-34.603722',
            'locationLng' => '-58.381592',
        ];
    }

    public function pending(): static
    {
        return $this->with([
            'status' => AlertStatus::PENDING,
        ]);
    }

    public function responded(): static
    {
        return $this->with([
            'status' => AlertStatus::RESPONDED,
        ]);
    }

    public function resolved(): static
    {
        return $this->with([
            'status' => AlertStatus::RESOLVED,
        ]);
    }
}
