<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;

final class DriverControllerTest extends AbstractApiTestCase
{
    // ── GET /api/drivers/{id} ────────────────────────────────────────────────

    public function testGetDriverRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();

        $this->getJson($client, '/api/drivers/' . $driver->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetDriverReturnsEmbeddedUserFields(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/drivers/' . $driver->getId());

        self::assertResponseIsSuccessful();
        $this->assertSame($driver->getId(), $data['id'] ?? null);
        $this->assertSame($driver->getNickname(), $data['nickname'] ?? null);
        $this->assertSame($driver->getLicenseNumber(), $data['licenseNumber'] ?? null);

        $this->assertArrayHasKey('user', $data);
        $this->assertIsArray($data['user']);

        $user = $driver->getUser();
        self::assertNotNull($user);
        $this->assertSame($user->getFirstName(), $data['user']['firstName']);
        $this->assertSame($user->getLastName(), $data['user']['lastName']);
        $this->assertSame($user->getIdentificationNumber(), $data['user']['identificationNumber']);
        $this->assertSame($user->getEmail(), $data['user']['email']);
        $this->assertSame($user->getPhoneNumber(), $data['user']['phoneNumber']);
    }

    public function testGetDriverUserDoesNotExposeOtherUserFields(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/drivers/' . $driver->getId());

        self::assertResponseIsSuccessful();
        $this->assertIsArray($data['user']);
        $this->assertArrayNotHasKey('roles', $data['user']);
        $this->assertArrayNotHasKey('password', $data['user']);
        $this->assertArrayNotHasKey('students', $data['user']);
    }

    public function testGetDriverNotFoundReturns404(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        $this->loginUser($client, $user);

        $this->getJson($client, '/api/drivers/999999');

        self::assertResponseStatusCodeSame(404);
    }

    // ── GET /api/drivers (collection) — user remains an IRI ─────────────────

    public function testGetCollectionDriverUserIsIri(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/drivers');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('user', $first);
        $this->assertIsString($first['user'], 'user should be an IRI string in collection');
        $this->assertStringStartsWith('/api/users/', $first['user']);
    }
}
