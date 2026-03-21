<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\DriverRate;
use App\Enum\PricingModel;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DriverRate>
 */
final class DriverRateFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DriverRate::class;
    }

    protected function defaults(): array
    {
        return [
            'driver' => DriverFactory::new(),
            'pricingModel' => PricingModel::FLAT,
            'amount' => (string) self::faker()->randomFloat(2, 500, 5000),
            'currency' => 'ARS',
        ];
    }

    public function flat(string $amount = '1500.00'): static
    {
        return $this->with([
            'pricingModel' => PricingModel::FLAT,
            'amount' => $amount,
            'perStudentAmount' => null,
            'route' => null,
        ]);
    }

    public function perRoute(mixed $route, string $amount = '1500.00'): static
    {
        return $this->with([
            'pricingModel' => PricingModel::PER_ROUTE,
            'amount' => $amount,
            'perStudentAmount' => null,
            'route' => $route,
        ]);
    }

    public function perStudent(string $perStudentAmount = '500.00'): static
    {
        return $this->with([
            'pricingModel' => PricingModel::PER_STUDENT,
            'amount' => null,
            'perStudentAmount' => $perStudentAmount,
            'route' => null,
        ]);
    }

    public function perRouteStudent(mixed $route, string $perStudentAmount = '500.00'): static
    {
        return $this->with([
            'pricingModel' => PricingModel::PER_ROUTE_STUDENT,
            'amount' => null,
            'perStudentAmount' => $perStudentAmount,
            'route' => $route,
        ]);
    }
}
