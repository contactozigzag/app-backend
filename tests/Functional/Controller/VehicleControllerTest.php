<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;

final class VehicleControllerTest extends AbstractApiTestCase
{
    // ── GET /api/vehicles — authentication & authorisation ───────────────────

    public function testGetCollectionRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/vehicles');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetCollectionForbiddenForUnprivilegedRole(): void
    {
        $client = $this->createApiClient();
        // ROLE_USER has no parent/driver/admin privileges
        $user = UserFactory::new()->with(['roles' => []])->create();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/vehicles');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGetCollectionAsParentReturnsVehicles(): void
    {
        $client = $this->createApiClient();
        $parent = UserFactory::createOne(); // ROLE_PARENT by default
        $driver = DriverFactory::createOne();
        VehicleFactory::new()->withDriver($driver)->create();
        $this->loginUser($client, $parent);

        $data = $this->getJson($client, '/api/vehicles');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data);
    }

    public function testGetCollectionAsDriverReturnsVehicles(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        VehicleFactory::new()->withDriver($driver)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/vehicles');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('driver', $data[0]);
    }

    // ── GET /api/vehicles?driver=/api/drivers/{id} — filter by driver IRI ────

    public function testGetCollectionFilteredByDriver(): void
    {
        $client = $this->createApiClient();
        $driver1 = DriverFactory::createOne();
        $driver2 = DriverFactory::createOne();
        VehicleFactory::new()->withDriver($driver1)->create();
        VehicleFactory::new()->withDriver($driver1)->create();
        VehicleFactory::new()->withDriver($driver2)->create();
        $this->loginUser($client, $driver1->getUser());

        $data = $this->getJson($client, '/api/vehicles?driver=/api/drivers/' . $driver1->getId());

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data);
        foreach ($data as $vehicle) {
            $this->assertSame('/api/drivers/' . $driver1->getId(), $vehicle['driver']);
        }
    }

    public function testGetCollectionFilteredByDriverReturnsEmptyForOtherDriver(): void
    {
        $client = $this->createApiClient();
        $driver1 = DriverFactory::createOne();
        $driver2 = DriverFactory::createOne();
        VehicleFactory::new()->withDriver($driver2)->create();
        $this->loginUser($client, $driver1->getUser());

        $data = $this->getJson($client, '/api/vehicles?driver=/api/drivers/' . $driver1->getId());

        self::assertResponseIsSuccessful();
        $this->assertEmpty($data);
    }

    // ── GET /api/vehicles/{id} ───────────────────────────────────────────────

    public function testGetItemRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $vehicle = VehicleFactory::new()->withDriver($driver)->create();

        $this->getJson($client, '/api/vehicles/' . $vehicle->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetItemReturnsDriverIri(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $vehicle = VehicleFactory::new()->withDriver($driver)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/vehicles/' . $vehicle->getId());

        self::assertResponseIsSuccessful();
        $this->assertSame($vehicle->getLicensePlate(), $data['licensePlate']);
        $this->assertSame('/api/drivers/' . $driver->getId(), $data['driver']);
    }

    // ── POST /api/vehicles ───────────────────────────────────────────────────

    public function testPostRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/vehicles', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testPostCreatesVehicleWithDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/vehicles', [
            'licensePlate' => 'AB-123-CD',
            'make' => 'Toyota',
            'model' => 'Hiace',
            'capacity' => 15,
            'driver' => '/api/drivers/' . $driver->getId(),
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertSame('AB-123-CD', $data['licensePlate']);
        $this->assertSame('/api/drivers/' . $driver->getId(), $data['driver']);
    }

    public function testPostCreatesVehicleWithoutDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/vehicles', [
            'licensePlate' => 'XY-999-ZZ',
            'make' => 'Ford',
            'model' => 'Transit',
            'capacity' => 20,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertNull($data['driver']);
    }

    // ── Driver.vehicles collection (driver:read group) ───────────────────────

    public function testDriverItemIncludesVehiclesCollection(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        VehicleFactory::new()->withDriver($driver)->create();
        VehicleFactory::new()->withDriver($driver)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/drivers/' . $driver->getId());

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('vehicles', $data);
        $this->assertCount(2, $data['vehicles']);
        foreach ($data['vehicles'] as $iri) {
            $this->assertStringStartsWith('/api/vehicles/', $iri);
        }
    }
}
