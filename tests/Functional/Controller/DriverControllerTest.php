<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
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
        $this->assertInstanceOf(User::class, $user);
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

    // ── GET /api/drivers?nickname= — prefix search ───────────────────────────

    public function testSearchByNicknamePrefix(): void
    {
        $client = $this->createApiClient();
        $target = DriverFactory::new()->with([
            'nickname' => 'AlphaDriver',
        ])->create();
        DriverFactory::new()->with([
            'nickname' => 'BetaDriver',
        ])->create();
        $this->loginUser($client, $target->getUser());

        $data = $this->getJson($client, '/api/drivers?nickname=Alpha');

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $data);
        $this->assertSame('AlphaDriver', $data[0]['nickname']);
    }

    public function testSearchByNicknamePrefixIsCaseInsensitive(): void
    {
        $client = $this->createApiClient();
        $target = DriverFactory::new()->with([
            'nickname' => 'AlphaDriver',
        ])->create();
        DriverFactory::new()->with([
            'nickname' => 'BetaDriver',
        ])->create();
        $this->loginUser($client, $target->getUser());

        $data = $this->getJson($client, '/api/drivers?nickname=alpha');

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $data);
        $this->assertSame('AlphaDriver', $data[0]['nickname']);
    }

    public function testSearchByNicknameNoMatchReturnsEmpty(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::new()->with([
            'nickname' => 'AlphaDriver',
        ])->create();
        $this->loginUser($client, $driver->getUser());

        $data = $this->getJson($client, '/api/drivers?nickname=Zzzzz');

        self::assertResponseIsSuccessful();
        $this->assertCount(0, $data);
    }

    public function testSearchByNicknameSubstringDoesNotMatch(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::new()->with([
            'nickname' => 'AlphaDriver',
        ])->create();
        $this->loginUser($client, $driver->getUser());

        // "Driver" is a substring, not a prefix — should NOT match
        $data = $this->getJson($client, '/api/drivers?nickname=Driver');

        self::assertResponseIsSuccessful();
        $this->assertCount(0, $data);
    }

    public function testSearchByNicknameWithoutFilterReturnsAll(): void
    {
        $client = $this->createApiClient();
        DriverFactory::new()->with([
            'nickname' => 'AlphaDriver',
        ])->create();
        $second = DriverFactory::new()->with([
            'nickname' => 'BetaDriver',
        ])->create();
        $this->loginUser($client, $second->getUser());

        $data = $this->getJson($client, '/api/drivers');

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data);
    }
}
