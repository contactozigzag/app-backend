<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;

final class GeofencingControllerTest extends AbstractApiTestCase
{
    // ── POST /api/geofencing/check/{routeId} — authentication & authorisation ─

    public function testCheckRouteRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/geofencing/check/1', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCheckRouteRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/geofencing/check/1', []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCheckRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/geofencing/check/99999', []);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Active route not found', $data['error']);
    }

    // ── POST /api/geofencing/check-all — authentication & authorisation ───────

    public function testCheckAllRoutesRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/geofencing/check-all', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCheckAllRoutesRequiresRouteManageVoter(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — no ROUTE_MANAGE
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/geofencing/check-all', []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCheckAllRoutesSuccessForAdmin(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $admin = UserFactory::createOne([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
            'school' => $school,
        ]);
        $this->loginUser($client, $admin);

        $data = $this->postJson($client, '/api/geofencing/check-all', []);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('routes_checked', $data);
        self::assertSame(0, $data['routes_checked']);
    }

    // ── GET /api/geofencing/distance-to-next/{routeId} — authentication ───────

    public function testDistanceToNextRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/geofencing/distance-to-next/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDistanceToNextRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/geofencing/distance-to-next/99999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Active route not found', $data['error']);
    }
}
