<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

final class DistressControllerTest extends AbstractApiTestCase
{
    // ── POST /api/routes/sessions/{id}/distress — authentication & authorisation

    public function testTriggerDistressRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/routes/sessions/1/distress', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTriggerDistressRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — not ROLE_DRIVER
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/routes/sessions/1/distress', []);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/routes/sessions/{id}/distress — validation ─────────────────

    public function testTriggerDistressRouteNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/routes/sessions/99999/distress', []);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Route session not found', $data['error']);
    }
}
