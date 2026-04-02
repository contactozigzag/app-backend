<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\PushDevice;
use App\Repository\PushDeviceRepository;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

final class PushDeviceControllerTest extends AbstractApiTestCase
{
    private const string VALID_TOKEN = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';

    // ── Authentication guard ──────────────────────────────────────────────────

    public function testRegisterRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnregisterRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_DELETE, '/api/push-devices/1');

        self::assertResponseStatusCodeSame(401);
    }

    // ── POST /api/push-devices — validation ──────────────────────────────────

    public function testRegisterMissingTokenReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/push-devices', [
            'platform' => 'android',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $body);
    }

    public function testRegisterMissingPlatformReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $body);
    }

    public function testRegisterInvalidPlatformReturns422(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'windows',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $body);
    }

    // ── POST /api/push-devices — success ─────────────────────────────────────

    public function testRegisterCreatesNewDevice(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
            'deviceName' => 'Pixel 7',
            'osVersion' => '13',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $body);
        $this->assertSame(self::VALID_TOKEN, $body['expoPushToken']);
        $this->assertSame('android', $body['platform']);
        $this->assertSame('Pixel 7', $body['deviceName']);

        /** @var PushDeviceRepository $repo */
        $repo = self::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->findByToken(self::VALID_TOKEN);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertTrue($device->isActive());
    }

    public function testRegisterIsIdempotentForSameToken(): void
    {
        $client = $this->createApiClient();
        $user1 = UserFactory::createOne();
        $user2 = UserFactory::createOne();
        $this->loginUser($client, $user1);

        // First registration
        $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'ios',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Second registration with different user (token transfer / upsert)
        $this->loginUser($client, $user2);
        $body = $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'ios',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Only one device record should exist
        /** @var PushDeviceRepository $repo */
        $repo = self::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->findByToken(self::VALID_TOKEN);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertSame($body['id'], $device->getId());
    }

    // ── DELETE /api/push-devices/{id} ────────────────────────────────────────

    public function testUnregisterDeactivatesOwnDevice(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        // Register first
        $body = $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
        ]);
        self::assertResponseStatusCodeSame(201);
        $deviceId = $body['id'];

        // Then unregister
        $client->request(Request::METHOD_DELETE, '/api/push-devices/' . $deviceId);

        self::assertResponseStatusCodeSame(204);

        /** @var PushDeviceRepository $repo */
        $repo = self::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->find($deviceId);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertFalse($device->isActive());
    }

    public function testUnregisterAnotherUsersDeviceIsIgnored(): void
    {
        $client = $this->createApiClient();
        $user1 = UserFactory::createOne();
        $user2 = UserFactory::createOne();

        // User1 registers a device
        $this->loginUser($client, $user1);
        $body = $this->postJson($client, '/api/push-devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
        ]);
        $deviceId = $body['id'];

        // User2 tries to delete User1's device — should be silently ignored
        $this->loginUser($client, $user2);
        $client->request(Request::METHOD_DELETE, '/api/push-devices/' . $deviceId);

        self::assertResponseStatusCodeSame(204);

        // Device should still be active
        /** @var PushDeviceRepository $repo */
        $repo = self::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->find($deviceId);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertTrue($device->isActive());
    }

    public function testUnregisterNonExistentDeviceReturns204(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_DELETE, '/api/push-devices/999999');

        self::assertResponseStatusCodeSame(204);
    }
}
