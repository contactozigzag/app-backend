<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

final class DriverAlertControllerTest extends AbstractApiTestCase
{
    // ── POST /api/driver-alerts/{alertId}/respond — authentication & authorisation

    public function testRespondRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/driver-alerts/some-alert-id/respond', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRespondRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — not ROLE_DRIVER
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/driver-alerts/some-alert-id/respond', []);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/driver-alerts/{alertId}/respond — validation ───────────────

    public function testRespondAlertNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/driver-alerts/non-existent-uuid/respond', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertSame('Alert not found', $data['error']);
    }

    // ── POST /api/driver-alerts/{alertId}/resolve — authentication & authorisation

    public function testResolveRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/driver-alerts/some-alert-id/resolve', []);

        self::assertResponseStatusCodeSame(401);
    }

    public function testResolveRequiresDriverRole(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — not ROLE_DRIVER
        $this->loginUser($client, $user);

        $this->postJson($client, '/api/driver-alerts/some-alert-id/resolve', []);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST /api/driver-alerts/{alertId}/resolve — validation ───────────────

    public function testResolveAlertNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/driver-alerts/non-existent-uuid/resolve', []);

        self::assertResponseStatusCodeSame(404);
        $this->assertSame('Alert not found', $data['error']);
    }

    public function testResolveAlreadyResolvedReturns422(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        // Create a RESOLVED alert directly via EntityManager
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $alert = new DriverAlert();
        $alert->setDistressedDriver($driver);
        $alert->setStatus(AlertStatus::RESOLVED);

        $em->persist($alert);
        $em->flush();

        $data = $this->postJson($client, sprintf('/api/driver-alerts/%s/resolve', $alert->getAlertId()), []);

        self::assertResponseStatusCodeSame(422);
        $this->assertSame('Alert is already resolved', $data['error']);
    }
}
