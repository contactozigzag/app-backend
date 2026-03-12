<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;

final class RouteControllerTest extends AbstractApiTestCase
{
    // ── GET /api/routes?driver= — filter by driver IRI ───────────────────────

    public function testGetCollectionFilteredByDriver(): void
    {
        $client = $this->createApiClient();
        $driver1 = DriverFactory::createOne();
        $driver2 = DriverFactory::createOne();
        RouteFactory::new()->withDriver($driver1)->create();
        RouteFactory::new()->withDriver($driver1)->create();
        RouteFactory::new()->withDriver($driver2)->create();
        $this->loginUser($client, $driver1->getUser());

        $data = $this->getJson($client, '/api/routes?driver=/api/drivers/' . $driver1->getId());

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data);
        foreach ($data as $route) {
            $this->assertSame('/api/drivers/' . $driver1->getId(), $route['driver']);
        }
    }

    // ── GET /api/routes?school= — filter by school IRI ───────────────────────

    public function testGetCollectionFilteredBySchool(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $school1 = SchoolFactory::createOne();
        $school2 = SchoolFactory::createOne();
        RouteFactory::new()->withSchool($school1)->create();
        RouteFactory::new()->withSchool($school1)->create();
        RouteFactory::new()->withSchool($school2)->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/routes?school=/api/schools/' . $school1->getId());

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data);
        foreach ($data as $route) {
            $this->assertSame('/api/schools/' . $school1->getId(), $route['school']);
        }
    }

    // ── POST /api/routes/{id}/optimize — authentication & authorisation ────────

    public function testOptimizeRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/routes/1/optimize', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testOptimizeRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/routes/1/optimize', []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOptimizeRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/routes/99999/optimize', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }

    // ── POST /api/routes/{id}/optimize-preview — authentication & validation ──

    public function testPreviewOptimizationRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/routes/1/optimize-preview', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testPreviewOptimizationRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/routes/99999/optimize-preview', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }

    // ── POST /api/routes/{id}/clone — authentication & validation ─────────────

    public function testCloneRouteRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/routes/1/clone', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCloneRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/routes/99999/clone', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertArrayHasKey('detail', $data);
    }
}
