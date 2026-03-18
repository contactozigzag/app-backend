<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

final class UserControllerTest extends AbstractApiTestCase
{
    // ── POST /api/users — register with nested driver ───────────────────────

    public function testRegisterUserWithNestedDriverCreatesDriverEntity(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $data = $this->postJson($client, '/api/users', [
            'email' => 'driver@example.com',
            'plainPassword' => 'P@ssw0rd!',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'phoneNumber' => '1234567890',
            'identificationNumber' => '12345678',
            'roles' => ['ROLE_DRIVER'],
            'school' => '/api/schools/' . $school->getId(),
            'driver' => [
                'nickname' => 'JohnnyD',
                'licenseNumber' => 'LIC-001',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('driver', $data);
        $this->assertIsArray($data['driver']);
        $this->assertArrayHasKey('id', $data['driver']);
        $this->assertSame('JohnnyD', $data['driver']['nickname']);
        $this->assertSame('LIC-001', $data['driver']['licenseNumber']);
    }

    public function testRegisterUserWithoutDriverDoesNotCreateDriverEntity(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $data = $this->postJson($client, '/api/users', [
            'email' => 'parent@example.com',
            'plainPassword' => 'P@ssw0rd!',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'phoneNumber' => '0987654321',
            'identificationNumber' => '87654321',
            'roles' => ['ROLE_PARENT'],
            'school' => '/api/schools/' . $school->getId(),
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertNull($data['driver'] ?? null);
    }

    public function testRegisterDriverWithDuplicateNicknameReturns422(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        // Create a driver with nickname 'JohnnyD'
        DriverFactory::new()->with([
            'nickname' => 'JohnnyD',
        ])->create();

        $data = $this->postJson($client, '/api/users', [
            'email' => 'driver2@example.com',
            'plainPassword' => 'P@ssw0rd!',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'phoneNumber' => '1234567890',
            'identificationNumber' => '12345679',
            'roles' => ['ROLE_DRIVER'],
            'school' => '/api/schools/' . $school->getId(),
            'driver' => [
                'nickname' => 'JohnnyD',
                'licenseNumber' => 'LIC-002',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('violations', $data);
        $this->assertNotEmpty($data['violations']);
        $nicknameViolation = array_find($data['violations'], fn (array $v): bool => ($v['propertyPath'] ?? '') === 'driver.nickname');
        $this->assertNotNull($nicknameViolation, 'Should have a violation on driver.nickname');
        $this->assertIsString($nicknameViolation['message']);
        $this->assertStringContainsString('alias', $nicknameViolation['message']);
    }

    // ── PATCH /api/users/{id} — add driver to existing user ─────────────────

    public function testPatchUserAddsNestedDriver(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::new()->with([
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $this->loginUser($client, $user);

        $client->request(Request::METHOD_PATCH, '/api/users/' . $user->getId(), [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'driver' => [
                'nickname' => 'PatchedNick',
                'licenseNumber' => 'LIC-PATCH',
            ],
        ]));

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        $this->assertArrayHasKey('driver', $data);
        $this->assertIsArray($data['driver']);
        $this->assertArrayHasKey('id', $data['driver']);
        $this->assertSame('PatchedNick', $data['driver']['nickname']);
        $this->assertSame('LIC-PATCH', $data['driver']['licenseNumber']);
    }

    // ── PATCH /api/users/{id} — update existing driver fields ───────────────

    public function testPatchUserUpdatesExistingDriverFields(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        // Create user with driver via POST
        $createData = $this->postJson($client, '/api/users', [
            'email' => 'update-driver@example.com',
            'plainPassword' => 'P@ssw0rd!',
            'firstName' => 'Mike',
            'lastName' => 'Smith',
            'phoneNumber' => '5551234567',
            'identificationNumber' => '99887766',
            'roles' => ['ROLE_DRIVER'],
            'school' => '/api/schools/' . $school->getId(),
            'driver' => [
                'nickname' => 'MikeS',
                'licenseNumber' => 'LIC-ORIG',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $userId = $createData['id'];

        // Login as the new user
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->find($userId);
        $this->loginUser($client, $user);

        // Patch only the nickname
        $client->request(Request::METHOD_PATCH, '/api/users/' . $userId, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'driver' => [
                'nickname' => 'MikeyUpdated',
            ],
        ]));

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true) ?? [];

        $this->assertIsArray($data['driver']);
        $this->assertSame('MikeyUpdated', $data['driver']['nickname']);
        $this->assertSame('LIC-ORIG', $data['driver']['licenseNumber'], 'Unpatched fields should remain unchanged');
    }

    // ── GET /api/users/{id} — driver embedded ───────────────────────────────

    public function testGetUserReturnsEmbeddedDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $user = $driver->getUser();
        $this->assertInstanceOf(User::class, $user);
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/users/' . $user->getId());

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('driver', $data);
        $this->assertIsArray($data['driver']);
        $this->assertSame($driver->getId(), $data['driver']['id']);
        $this->assertSame($driver->getNickname(), $data['driver']['nickname']);
        $this->assertSame($driver->getLicenseNumber(), $data['driver']['licenseNumber']);
    }

    // ── GET /api/users — driver is IRI in collection ────────────────────────

    public function testGetCollectionReturnsDriverAsIri(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $driverOwner = $driver->getUser();
        $this->assertInstanceOf(User::class, $driverOwner);
        $admin = UserFactory::new()->with([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/users');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data);
        $driverUser = array_find($data, fn ($item): bool => ($item['email'] ?? null) === $driverOwner->getEmail());

        $this->assertNotNull($driverUser, 'Driver user should appear in collection');
        $this->assertArrayHasKey('driver', $driverUser);
        $this->assertIsString($driverUser['driver'], 'Driver should be an IRI string in collection');
        $this->assertStringStartsWith('/api/drivers/', $driverUser['driver']);
    }
}
