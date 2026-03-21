<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Payment;

use App\Entity\Driver;
use App\Entity\DriverRate;
use App\Entity\Route;
use App\Enum\PricingModel;
use App\Repository\DriverRateRepository;
use App\Service\Payment\DriverRateCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DriverRateCalculatorTest extends TestCase
{
    public function testFlatModelReturnsFixedAmount(): void
    {
        $driver = $this->createDriverStub(PricingModel::FLAT);
        $rate = $this->createRateStub(amount: '1500.00');

        $repository = $this->createStub(DriverRateRepository::class);
        $repository->method('findByDriverAndRoute')->willReturn($rate);

        $calculator = new DriverRateCalculator($repository);
        $result = $calculator->calculateAmount($driver, null, 3);

        $this->assertSame('1500.00', $result->amount);
        $this->assertSame('flat', $result->rateSnapshot['pricingModel']);
        $this->assertSame('1500.00', $result->rateSnapshot['calculatedAmount']);
    }

    public function testPerRouteModelReturnsRouteAmount(): void
    {
        $driver = $this->createDriverStub(PricingModel::PER_ROUTE);
        $route = $this->createRouteStub(5, 'Morning Route A');
        $rate = $this->createRateStub(amount: '2000.00');

        $repository = $this->createStub(DriverRateRepository::class);
        $repository->method('findByDriverAndRoute')->willReturn($rate);

        $calculator = new DriverRateCalculator($repository);
        $result = $calculator->calculateAmount($driver, $route, 2);

        $this->assertSame('2000.00', $result->amount);
        $this->assertSame('per_route', $result->rateSnapshot['pricingModel']);
        $this->assertSame(5, $result->rateSnapshot['routeId']);
        $this->assertSame('Morning Route A', $result->rateSnapshot['routeName']);
    }

    public function testPerStudentModelMultipliesByCount(): void
    {
        $driver = $this->createDriverStub(PricingModel::PER_STUDENT);
        $rate = $this->createRateStub(perStudentAmount: '500.00');

        $repository = $this->createStub(DriverRateRepository::class);
        $repository->method('findByDriverAndRoute')->willReturn($rate);

        $calculator = new DriverRateCalculator($repository);
        $result = $calculator->calculateAmount($driver, null, 3);

        $this->assertSame('1500.00', $result->amount);
        $this->assertSame('per_student', $result->rateSnapshot['pricingModel']);
        $this->assertSame('500.00', $result->rateSnapshot['perStudentAmount']);
        $this->assertSame(3, $result->rateSnapshot['studentCount']);
    }

    public function testPerRouteStudentModelMultipliesByCount(): void
    {
        $driver = $this->createDriverStub(PricingModel::PER_ROUTE_STUDENT);
        $route = $this->createRouteStub(7, 'Afternoon Route B');
        $rate = $this->createRateStub(perStudentAmount: '600.00');

        $repository = $this->createStub(DriverRateRepository::class);
        $repository->method('findByDriverAndRoute')->willReturn($rate);

        $calculator = new DriverRateCalculator($repository);
        $result = $calculator->calculateAmount($driver, $route, 4);

        $this->assertSame('2400.00', $result->amount);
        $this->assertSame('per_route_student', $result->rateSnapshot['pricingModel']);
        $this->assertSame(7, $result->rateSnapshot['routeId']);
        $this->assertSame('600.00', $result->rateSnapshot['perStudentAmount']);
        $this->assertSame(4, $result->rateSnapshot['studentCount']);
    }

    public function testThrowsWhenDriverHasNoPricingModel(): void
    {
        $driver = $this->createDriverStub(null);
        $repository = $this->createStub(DriverRateRepository::class);

        $calculator = new DriverRateCalculator($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver has no pricing model configured.');

        $calculator->calculateAmount($driver, null, 1);
    }

    public function testThrowsWhenRouteRequiredButNull(): void
    {
        $driver = $this->createDriverStub(PricingModel::PER_ROUTE);
        $repository = $this->createStub(DriverRateRepository::class);

        $calculator = new DriverRateCalculator($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route is required');

        $calculator->calculateAmount($driver, null, 1);
    }

    public function testThrowsWhenRateNotFound(): void
    {
        $driver = $this->createDriverStub(PricingModel::FLAT);

        $repository = $this->createStub(DriverRateRepository::class);
        $repository->method('findByDriverAndRoute')->willReturn(null);

        $calculator = new DriverRateCalculator($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No rate configured for driver');

        $calculator->calculateAmount($driver, null, 1);
    }

    private function createDriverStub(?PricingModel $pricingModel): Driver
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('getPricingModel')->willReturn($pricingModel);
        $driver->method('getNickname')->willReturn('driver-nick');

        return $driver;
    }

    private function createRouteStub(int $id, string $name): Route
    {
        $route = $this->createStub(Route::class);
        $route->method('getId')->willReturn($id);
        $route->method('getName')->willReturn($name);

        return $route;
    }

    private function createRateStub(?string $amount = null, ?string $perStudentAmount = null): DriverRate
    {
        $rate = $this->createStub(DriverRate::class);
        $rate->method('getAmount')->willReturn($amount);
        $rate->method('getPerStudentAmount')->willReturn($perStudentAmount);
        $rate->method('getCurrency')->willReturn('ARS');

        return $rate;
    }
}
