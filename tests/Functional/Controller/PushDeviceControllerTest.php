<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\PushDevice;
use App\Repository\PushDeviceRepository;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\UserFactory;

final class PushDeviceControllerTest extends AbstractApiTestCase
{
    private const VALID_TOKEN = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';

    // ── Authentication guard ──────────────────────────────────────────────────

    public function testRegisterRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnregisterRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request('DELETE', '/api/devices/1');

        self::assertResponseStatusCodeSame(401);
    }

    // ── POST /api/devices — validation ───────────────────────────────────────

    public function testRegisterMissingTokenReturns400(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/devices', ['platform' => 'android']);

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $body);
    }

    public function testRegisterMissingPlatformReturns400(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/devices', ['expoPushToken' => self::VALID_TOKEN]);

        self::assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('error', $body);
    }

    // ── POST /api/devices — success ───────────────────────────────────────────

    public function testRegisterCreatesNewDevice(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $body = $this->postJson($client, '/api/devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
            'deviceName' => 'Pixel 7',
            'osVersion' => '13',
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $body);

        /** @var PushDeviceRepository $repo */
        $repo = static::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->findByToken(self::VALID_TOKEN);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertTrue($device->isActive());
        $this->assertSame('android', $device->getPlatform());
        $this->assertSame('Pixel 7', $device->getDeviceName());
    }

    public function testRegisterIsIdempotentForSameToken(): void
    {
        $client = $this->createApiClient();
        $user1 = UserFactory::createOne();
        $user2 = UserFactory::createOne();
        $this->loginUser($client, $user1);

        // First registration
        $this->postJson($client, '/api/devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'ios',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Second registration with different user (token transfer / upsert)
        $this->loginUser($client, $user2);
        $body = $this->postJson($client, '/api/devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'ios',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Only one device record should exist
        /** @var PushDeviceRepository $repo */
        $repo = static::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->findByToken(self::VALID_TOKEN);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertSame($body['id'], $device->getId());
    }

    // ── DELETE /api/devices/{id} ──────────────────────────────────────────────

    public function testUnregisterDeactivatesOwnDevice(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        // Register first
        $body = $this->postJson($client, '/api/devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
        ]);
        self::assertResponseStatusCodeSame(201);
        $deviceId = $body['id'];

        // Then unregister
        $client->request('DELETE', '/api/devices/' . $deviceId);

        self::assertResponseStatusCodeSame(204);

        /** @var PushDeviceRepository $repo */
        $repo = static::getContainer()->get(PushDeviceRepository::class);
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
        $body = $this->postJson($client, '/api/devices', [
            'expoPushToken' => self::VALID_TOKEN,
            'platform' => 'android',
        ]);
        $deviceId = $body['id'];

        // User2 tries to delete User1's device — should be silently ignored
        $this->loginUser($client, $user2);
        $client->request('DELETE', '/api/devices/' . $deviceId);

        self::assertResponseStatusCodeSame(204);

        // Device should still be active
        /** @var PushDeviceRepository $repo */
        $repo = static::getContainer()->get(PushDeviceRepository::class);
        $device = $repo->find($deviceId);
        $this->assertInstanceOf(PushDevice::class, $device);
        $this->assertTrue($device->isActive());
    }

    public function testUnregisterNonExistentDeviceReturns204(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $client->request('DELETE', '/api/devices/999999');

        self::assertResponseStatusCodeSame(204);
    }
}
