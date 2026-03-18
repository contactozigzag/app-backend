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
        self::assertArrayHasKey('driver', $data);
        self::assertIsArray($data['driver']);
        self::assertArrayHasKey('id', $data['driver']);
        self::assertSame('JohnnyD', $data['driver']['nickname']);
        self::assertSame('LIC-001', $data['driver']['licenseNumber']);
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
        self::assertNull($data['driver'] ?? null);
    }

    // ── PATCH /api/users/{id} — add driver to existing user ─────────────────

    public function testPatchUserAddsNestedDriver(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::new()->with(['roles' => ['ROLE_DRIVER']])->create();
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

        self::assertArrayHasKey('driver', $data);
        self::assertIsArray($data['driver']);
        self::assertArrayHasKey('id', $data['driver']);
        self::assertSame('PatchedNick', $data['driver']['nickname']);
        self::assertSame('LIC-PATCH', $data['driver']['licenseNumber']);
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
        $user = static::getContainer()->get('doctrine')->getRepository(User::class)->find($userId);
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

        self::assertIsArray($data['driver']);
        self::assertSame('MikeyUpdated', $data['driver']['nickname']);
        self::assertSame('LIC-ORIG', $data['driver']['licenseNumber'], 'Unpatched fields should remain unchanged');
    }

    // ── GET /api/users/{id} — driver embedded ───────────────────────────────

    public function testGetUserReturnsEmbeddedDriver(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $user = $driver->getUser();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/users/' . $user->getId());

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('driver', $data);
        self::assertIsArray($data['driver']);
        self::assertSame($driver->getId(), $data['driver']['id']);
        self::assertSame($driver->getNickname(), $data['driver']['nickname']);
        self::assertSame($driver->getLicenseNumber(), $data['driver']['licenseNumber']);
    }

    // ── GET /api/users — driver is IRI in collection ────────────────────────

    public function testGetCollectionReturnsDriverAsIri(): void
    {
        $client = $this->createApiClient();
        $driver = DriverFactory::createOne();
        $admin = UserFactory::new()->with(['roles' => ['ROLE_SCHOOL_ADMIN']])->create();
        $this->loginUser($client, $admin);

        $data = $this->getJson($client, '/api/users');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($data);

        // Find the driver user in the collection
        $driverUser = null;
        foreach ($data as $item) {
            if (($item['email'] ?? null) === $driver->getUser()->getEmail()) {
                $driverUser = $item;
                break;
            }
        }

        self::assertNotNull($driverUser, 'Driver user should appear in collection');
        self::assertArrayHasKey('driver', $driverUser);
        self::assertIsString($driverUser['driver'], 'Driver should be an IRI string in collection');
        self::assertStringStartsWith('/api/drivers/', $driverUser['driver']);
    }
}
