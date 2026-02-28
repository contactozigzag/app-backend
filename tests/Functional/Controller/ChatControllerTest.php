<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

final class ChatControllerTest extends AbstractApiTestCase
{
    // ── POST /api/driver-alerts/{alertId}/messages — authentication ───────────

    public function testPostMessageRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/driver-alerts/some-alert-id/messages', [
            'content' => 'Hello',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ── GET /api/driver-alerts/{alertId}/messages — authentication ────────────

    public function testGetMessagesRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->getJson($client, '/api/driver-alerts/some-alert-id/messages');

        self::assertResponseStatusCodeSame(401);
    }

    // ── POST — alert not found ────────────────────────────────────────────────

    public function testPostMessageAlertNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->postJson($client, '/api/driver-alerts/non-existent-uuid/messages', [
            'content' => 'Hello',
        ]);

        self::assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('Alert not found', (string) $data['detail']);
    }

    // ── GET — alert not found ─────────────────────────────────────────────────

    public function testGetMessagesAlertNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/driver-alerts/non-existent-uuid/messages');

        self::assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('Alert not found', (string) $data['detail']);
    }

    // ── POST — non-participant returns 403 ────────────────────────────────────

    public function testPostMessageNonParticipantReturns403(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne(); // ROLE_PARENT — not a driver participant
        $distressedDriver = DriverFactory::createOne();
        $this->loginUser($client, $user);

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $alert = new DriverAlert();
        $alert->setDistressedDriver($distressedDriver);
        $alert->setStatus(AlertStatus::PENDING);

        $em->persist($alert);
        $em->flush();

        $this->postJson($client, sprintf('/api/driver-alerts/%s/messages', $alert->getAlertId()), [
            'content' => 'Hello',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── POST — resolved alert returns 422 ─────────────────────────────────────

    public function testPostMessageResolvedAlertReturns422(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $alert = new DriverAlert();
        $alert->setDistressedDriver($driver);
        $alert->setStatus(AlertStatus::RESOLVED);

        $em->persist($alert);
        $em->flush();

        $data = $this->postJson($client, sprintf('/api/driver-alerts/%s/messages', $alert->getAlertId()), [
            'content' => 'Hello',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('read-only', (string) $data['detail']);
    }

    // ── POST — missing content returns 422 ────────────────────────────────────

    public function testPostMessageMissingContentReturns422(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $alert = new DriverAlert();
        $alert->setDistressedDriver($driver);
        $alert->setStatus(AlertStatus::PENDING);

        $em->persist($alert);
        $em->flush();

        $data = $this->postJson($client, sprintf('/api/driver-alerts/%s/messages', $alert->getAlertId()), []);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $data);
    }

    // ── POST — success ────────────────────────────────────────────────────────

    public function testPostMessageSuccess(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $alert = new DriverAlert();
        $alert->setDistressedDriver($driver);
        $alert->setStatus(AlertStatus::PENDING);

        $em->persist($alert);
        $em->flush();

        $data = $this->postJson($client, sprintf('/api/driver-alerts/%s/messages', $alert->getAlertId()), [
            'content' => 'I need help!',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $data);
    }

    // ── GET — success ─────────────────────────────────────────────────────────

    public function testGetMessagesSuccess(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $alert = new DriverAlert();
        $alert->setDistressedDriver($driver);
        $alert->setStatus(AlertStatus::PENDING);

        $em->persist($alert);
        $em->flush();

        $data = $this->getJson($client, sprintf('/api/driver-alerts/%s/messages', $alert->getAlertId()));

        self::assertResponseIsSuccessful();
        $this->assertSame($alert->getAlertId(), $data['alertId']);
        $this->assertArrayHasKey('messages', $data);
        $this->assertSame(0, $data['count']);
    }
}
