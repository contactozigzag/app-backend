<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

final class RouteControllerTest extends AbstractApiTestCase
{
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
        self::assertSame('Route not found', $data['error']);
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
        self::assertSame('Route not found', $data['error']);
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
        self::assertSame('Route not found', $data['error']);
    }
}
