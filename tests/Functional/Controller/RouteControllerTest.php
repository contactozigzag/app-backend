<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;

final class RouteControllerTest extends AbstractApiTestCase
{
    // ── GET /api/routes — driver sees only own routes ──────────────────────────

    public function testGetCollectionDriverSeesOnlyOwnRoutes(): void
    {
        $client = $this->createApiClient();
        $driver1 = DriverFactory::createOne();
        $driver2 = DriverFactory::createOne();
        RouteFactory::new()->withDriver($driver1)->create();
        RouteFactory::new()->withDriver($driver1)->create();
        RouteFactory::new()->withDriver($driver2)->create();
        $this->loginUser($client, $driver1->getUser());

        $data = $this->getJson($client, '/api/routes');

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data);
        foreach ($data as $route) {
            $this->assertSame('/api/drivers/' . $driver1->getId(), $route['driver']);
        }
    }

    // ── GET /api/routes — school admin sees all routes ──────────────────────

    public function testGetCollectionSchoolAdminSeesAllRoutes(): void
    {
        $client = $this->createApiClient();
        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();
        $school1 = SchoolFactory::createOne();
        $school2 = SchoolFactory::createOne();
        RouteFactory::new()->withSchool($school1)->create();
        RouteFactory::new()->withSchool($school1)->create();
        RouteFactory::new()->withSchool($school2)->create();
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/routes');

        self::assertResponseIsSuccessful();
        $this->assertCount(3, $data);
    }

    public function testGetCollectionDriverDoesNotSeeOtherDriverRoutes(): void
    {
        $client = $this->createApiClient();
        $driver1 = DriverFactory::createOne();
        $driver2 = DriverFactory::createOne();
        RouteFactory::new()->withDriver($driver1)->create();
        RouteFactory::new()->withDriver($driver2)->create();
        $this->loginUser($client, $driver2->getUser());

        $data = $this->getJson($client, '/api/routes');

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $data);
        $this->assertSame('/api/drivers/' . $driver2->getId(), $data[0]['driver']);
    }

    // ── GET /api/routes?driver= — parent sees driver's routes ─────────────────

    public function testGetCollectionParentSeesDriverRoutesViaFilter(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();
        $otherSchool = SchoolFactory::createOne();
        $driver = DriverFactory::createOne();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();
        RouteFactory::new()->withDriver($driver)->withSchool($otherSchool)->create();

        $parent = UserFactory::createOne(); // ROLE_PARENT, no school
        StudentFactory::new()->with([
            'school' => $school,
        ])->withParent($parent)->create();
        $this->loginUser($client, $parent);

        $data = $this->getJson($client, '/api/routes?driver=' . $driver->getId());

        self::assertResponseIsSuccessful();
        // Only 2 routes from the child's school, not the 3rd from another school
        $this->assertCount(2, $data);
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
