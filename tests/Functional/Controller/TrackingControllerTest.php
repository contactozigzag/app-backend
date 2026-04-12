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
            'driverId' => 999,
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/tracking/location — validation ──────────────────────────────

    public function testUpdateLocationMissingFieldsReturns422(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location', [
            'latitude' => -34.6037,
            // missing longitude and driverId
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testUpdateLocationDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location', [
            'driverId' => 99999,
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }

    // ── POST /api/tracking/location — success ─────────────────────────────────

    public function testUpdateLocationSuccess(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location', [
            'driverId' => $driver->getId(),
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('locationId', $data);
        $this->assertFalse($data['hasActiveRoute']);
    }

    public function testUpdateLocationRateLimitedReturnsSoftFailure(): void
    {
        $client = $this->createApiClient();
        // Keep the same kernel between requests so the InMemoryStorage
        // backing the gps_ingestion limiter survives the rate-limit window.
        $client->disableReboot();

        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        // Vary the timestamp so the idempotency dedup doesn't kick in.
        $makePayload = static fn (int $i): array => [
            'driverId' => $driver->getId(),
            'latitude' => -34.6037,
            'longitude' => -58.3816,
            'timestamp' => sprintf('2026-04-12T10:00:%02d+00:00', $i),
        ];

        // gps_ingestion: 5 per 10s sliding window.
        // First five calls consume the bucket.
        for ($i = 0; $i < 5; $i++) {
            $ok = $this->postJson($client, '/api/tracking/location', $makePayload($i));
            self::assertResponseStatusCodeSame(201);
            $this->assertTrue($ok['success'], sprintf('call #%d should succeed', $i));
        }

        // Sixth call inside the same window must NOT throw 429 — it should fail soft.
        $rateLimited = $this->postJson($client, '/api/tracking/location', $makePayload(5));
        self::assertResponseStatusCodeSame(201);
        $this->assertFalse($rateLimited['success']);
        $this->assertTrue($rateLimited['rateLimited']);
        $this->assertSame(0, $rateLimited['locationId']);
        $this->assertFalse($rateLimited['hasActiveRoute']);
        $this->assertArrayHasKey('retryAfterSeconds', $rateLimited);
        $this->assertArrayHasKey('message', $rateLimited);
        $this->assertNotNull($rateLimited['message']);
    }

    // ── POST /api/tracking/location/batch — authentication & validation ───────

    public function testBatchUpdateLocationRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/tracking/location/batch', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testBatchUpdateLocationMissingFieldsReturns422(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/tracking/location/batch', [
            // missing driverId and locations
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testBatchUpdateLocationDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $this->postJson($client, '/api/tracking/location/batch', [
            'driverId' => 99999,
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
            'driverId' => $driver->getId(),
            'locations' => [
                [
                    'latitude' => -34.6037,
                    'longitude' => -58.3816,
                    'timestamp' => '2024-01-15T10:00:00+00:00',
                ],
                [
                    'latitude' => -34.6040,
                    'longitude' => -58.3820,
                    'timestamp' => '2024-01-15T10:00:01+00:00',
                ],
                [
                    'latitude' => -34.6045,
                    'longitude' => -58.3825,
                    'speed' => 30.5,
                    'timestamp' => '2024-01-15T10:00:02+00:00',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertTrue($data['success']);
        $this->assertSame(3, $data['processedCount']);
        $this->assertSame(3, $data['totalCount']);
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
        $this->assertArrayHasKey('detail', $data);
    }

    public function testGetDriverLocationNoDataReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, sprintf('/api/tracking/location/driver/%d', $driver->getId()));

        self::assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('No location data available', (string) $data['detail']);
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
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $user);

        // Provider must succeed (driver exists, dates valid) before AP4's post-read
        // security check can deny access and return 403.
        $this->getJson($client, sprintf('/api/tracking/location/driver/%d/history?start=2026-01-01&end=2026-02-01', $driver->getId()));

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
        $this->assertArrayHasKey('detail', $data);
    }
}
