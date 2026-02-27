<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

final class TrackingControllerTest extends AbstractApiTestCase
{
    // ── POST /api/tracking/location — authentication & authorisation ──────────

    public function testUpdateLocationRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/tracking/location', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUpdateLocationRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — no ROLE_DRIVER
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/tracking/location', [
            'driver_id' => 999,
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/tracking/location — validation ──────────────────────────────

    public function testUpdateLocationMissingFieldsReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location', [
            'latitude' => -34.6037,
            // missing longitude and driver_id
        ]);

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUpdateLocationDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location', [
            'driver_id' => 99999,
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    // ── POST /api/tracking/location — success ─────────────────────────────────

    public function testUpdateLocationSuccess(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location', [
            'driver_id' => $driver->getId(),
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('location_id', $data);
        $this->assertFalse($data['has_active_route']);
    }

    // ── POST /api/tracking/location/batch — authentication & validation ───────

    public function testBatchUpdateLocationRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/tracking/location/batch', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testBatchUpdateLocationMissingFieldsReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location/batch', [
            // missing driver_id and locations
        ]);

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
    }

    public function testBatchUpdateLocationDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $this->postJson($client, '/api/tracking/location/batch', [
            'driver_id' => 99999,
            'locations' => [],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testBatchUpdateLocationSuccess(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location/batch', [
            'driver_id' => $driver->getId(),
            'locations' => [
                [
                    'latitude' => -34.6037,
                    'longitude' => -58.3816,
                ],
                [
                    'latitude' => -34.6040,
                    'longitude' => -58.3820,
                ],
                [
                    'latitude' => -34.6045,
                    'longitude' => -58.3825,
                    'speed' => 30.5,
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertTrue($data['success']);
        $this->assertSame(3, $data['processed_count']);
        $this->assertSame(3, $data['total_count']);
        $this->assertEmpty($data['errors']);
    }

    // ── GET /api/tracking/location/driver/{id} — authentication ───────────────

    public function testGetDriverLocationRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/tracking/location/driver/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetDriverLocationDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/tracking/location/driver/99999');

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetDriverLocationNoDataReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, sprintf('/api/tracking/location/driver/%d', $driver->getId()));

        self::assertResponseStatusCodeSame(404);
        $this->assertSame('No location data available', $data['error']);
    }

    // ── GET /api/tracking/location/driver/{id}/history — authentication ───────

    public function testGetDriverLocationHistoryRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/tracking/location/driver/1/history');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetDriverLocationHistoryRequiresRouteManage(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — no ROUTE_MANAGE
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/tracking/location/driver/1/history?start=2026-01-01&end=2026-02-01');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGetDriverLocationHistoryMissingDatesReturnsBadRequest(): void
    {
        $client = $this->createApiClient();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, sprintf('/api/tracking/location/driver/%d/history', $driver->getId()));

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $data);
    }
}
