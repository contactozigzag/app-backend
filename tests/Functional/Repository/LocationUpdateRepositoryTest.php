<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Repository\LocationUpdateRepository;
use App\Tests\Factory\ActiveRouteFactory;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\LocationUpdateFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Integration tests for PostGIS-backed repository methods.
 * DB isolation is handled by dama/doctrine-test-bundle (transaction rollback).
 */
final class LocationUpdateRepositoryTest extends KernelTestCase
{
    use Factories;

    // Buenos Aires reference point used as the distress location in all tests.
    private const float CENTER_LAT = -34.603722;

    private const float CENTER_LNG = -58.381592;

    // ~90 m north of the centre — well within 500 m radius.
    private const string NEAR_LAT = '-34.602912';

    private const string NEAR_LNG = '-58.381592';

    // ~11 km south-west — outside 500 m radius.
    private const string FAR_LAT = '-34.700000';

    private const string FAR_LNG = '-58.500000';

    private function repository(): LocationUpdateRepository
    {
        return self::getContainer()->get(LocationUpdateRepository::class);
    }

    public function testReturnsDriverWithinRadius(): void
    {
        $driver = DriverFactory::createOne();
        $route = ActiveRouteFactory::new()->inProgress()->withDriver($driver)->create();
        LocationUpdateFactory::new()
            ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
            ->withActiveRoute($route)
            ->create([
                'driver' => $driver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
        );

        $this->assertCount(1, $results);
        $this->assertSame($driver->getId(), $results[0]['driverId']);
        $this->assertLessThan(500.0, $results[0]['distanceMeters']);
    }

    public function testExcludesDriverOutsideRadius(): void
    {
        $driver = DriverFactory::createOne();
        $route = ActiveRouteFactory::new()->inProgress()->withDriver($driver)->create();
        LocationUpdateFactory::new()
            ->withCoordinates(self::FAR_LAT, self::FAR_LNG)
            ->withActiveRoute($route)
            ->create([
                'driver' => $driver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
        );

        $this->assertCount(0, $results);
    }

    public function testExcludesSpecifiedDriverId(): void
    {
        $driver = DriverFactory::createOne();
        $route = ActiveRouteFactory::new()->inProgress()->withDriver($driver)->create();
        LocationUpdateFactory::new()
            ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
            ->withActiveRoute($route)
            ->create([
                'driver' => $driver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: (int) $driver->getId(),
        );

        $this->assertCount(0, $results);
    }

    public function testIgnoresNonInProgressRoutes(): void
    {
        foreach (['scheduled', 'completed', 'cancelled'] as $status) {
            $driver = DriverFactory::createOne();
            $route = ActiveRouteFactory::new()->withDriver($driver)->create([
                'status' => $status,
            ]);
            LocationUpdateFactory::new()
                ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
                ->withActiveRoute($route)
                ->create([
                    'driver' => $driver,
                ]);
        }

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
        );

        $this->assertCount(0, $results);
    }

    public function testIncludesArrivingStatus(): void
    {
        $driver = DriverFactory::createOne();
        $route = ActiveRouteFactory::new()->withDriver($driver)->create([
            'status' => 'arriving',
        ]);
        LocationUpdateFactory::new()
            ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
            ->withActiveRoute($route)
            ->create([
                'driver' => $driver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
        );

        $this->assertCount(1, $results);
    }

    public function testIgnoresStaleLocations(): void
    {
        $driver = DriverFactory::createOne();
        $route = ActiveRouteFactory::new()->inProgress()->withDriver($driver)->create();
        LocationUpdateFactory::new()
            ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
            ->withActiveRoute($route)
            ->stale(600) // 10 minutes old
            ->create([
                'driver' => $driver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
            maxAgeSeconds: 300, // 5-minute window
        );

        $this->assertCount(0, $results);
    }

    public function testSortsByDistanceAscending(): void
    {
        // ~90 m away
        $nearDriver = DriverFactory::createOne();
        $nearRoute = ActiveRouteFactory::new()->inProgress()->withDriver($nearDriver)->create();
        LocationUpdateFactory::new()
            ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
            ->withActiveRoute($nearRoute)
            ->create([
                'driver' => $nearDriver,
            ]);

        // ~200 m away
        $midDriver = DriverFactory::createOne();
        $midRoute = ActiveRouteFactory::new()->inProgress()->withDriver($midDriver)->create();
        LocationUpdateFactory::new()
            ->withCoordinates('-34.601900', '-58.381592')
            ->withActiveRoute($midRoute)
            ->create([
                'driver' => $midDriver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
        );

        $this->assertCount(2, $results);
        $this->assertSame($nearDriver->getId(), $results[0]['driverId']);
        $this->assertLessThan($results[1]['distanceMeters'], $results[0]['distanceMeters'] + 0.001);
    }

    public function testReturnsOnlyMostRecentLocationPerDriver(): void
    {
        $driver = DriverFactory::createOne();
        $route = ActiveRouteFactory::new()->inProgress()->withDriver($driver)->create();

        // Older location — far away
        LocationUpdateFactory::new()
            ->withCoordinates(self::FAR_LAT, self::FAR_LNG)
            ->withActiveRoute($route)
            ->stale(60)
            ->create([
                'driver' => $driver,
            ]);

        // Recent location — nearby
        LocationUpdateFactory::new()
            ->withCoordinates(self::NEAR_LAT, self::NEAR_LNG)
            ->withActiveRoute($route)
            ->create([
                'driver' => $driver,
            ]);

        $results = $this->repository()->findNearbyDriversInProgress(
            lat: self::CENTER_LAT,
            lng: self::CENTER_LNG,
            radiusMeters: 500,
            excludeDriverId: 0,
        );

        $this->assertCount(1, $results);
        $this->assertSame($driver->getId(), $results[0]['driverId']);
    }
}
