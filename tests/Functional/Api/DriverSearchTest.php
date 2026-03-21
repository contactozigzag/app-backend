<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Functional tests for GET /api/drivers/search.
 *
 * These tests exercise the Doctrine fallback path since the test environment
 * does not have OpenSearch running. Tests requiring a live OpenSearch instance
 * should be tagged @group opensearch.
 *
 * Parent users must pass a `school` query parameter and have a child enrolled
 * in that school. Drivers are found via their route assignments to that school.
 * School admins search within their own school automatically.
 */
final class DriverSearchTest extends AbstractApiTestCase
{
    public function testSearchByNickname(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        StudentFactory::new()->with([
            'school' => $school,
        ])->withParent($parentUser)->create();

        $driverUser = UserFactory::new()->with([
            'firstName' => 'Carlos',
            'lastName' => 'García',
            'identificationNumber' => '12345678',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driver = DriverFactory::new()->with([
            'user' => $driverUser,
            'nickname' => 'Carlitos',
        ])->create();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();

        $this->loginUser($client, $parentUser);
        $data = $this->getJson($client, '/api/drivers/search?q=Carlitos&school=' . $school->getId());

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
        $this->assertSame('Carlitos', $data['results'][0]['nickname']);
    }

    public function testSearchByFirstName(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        StudentFactory::new()->with([
            'school' => $school,
        ])->withParent($parentUser)->create();

        $driverUser = UserFactory::new()->with([
            'firstName' => 'Juan',
            'lastName' => 'Pérez',
            'identificationNumber' => '87654321',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driver = DriverFactory::new()->with([
            'user' => $driverUser,
            'nickname' => 'Juancho',
        ])->create();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();

        $this->loginUser($client, $parentUser);
        $data = $this->getJson($client, '/api/drivers/search?q=Juan&school=' . $school->getId());

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
        $this->assertSame('Juan', $data['results'][0]['firstName']);
    }

    public function testSearchByLastName(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        StudentFactory::new()->with([
            'school' => $school,
        ])->withParent($parentUser)->create();

        $driverUser = UserFactory::new()->with([
            'firstName' => 'Carlos',
            'lastName' => 'García',
            'identificationNumber' => '12345678',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driver = DriverFactory::new()->with([
            'user' => $driverUser,
            'nickname' => 'Carlitos',
        ])->create();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();

        $this->loginUser($client, $parentUser);
        $data = $this->getJson($client, '/api/drivers/search?q=Garc&school=' . $school->getId());

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
        $this->assertSame('García', $data['results'][0]['lastName']);
    }

    public function testSearchByIdentificationNumber(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        StudentFactory::new()->with([
            'school' => $school,
        ])->withParent($parentUser)->create();

        $driverUser = UserFactory::new()->with([
            'firstName' => 'Carlos',
            'lastName' => 'García',
            'identificationNumber' => '12345678',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driver = DriverFactory::new()->with([
            'user' => $driverUser,
            'nickname' => 'Carlitos',
        ])->create();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();

        $this->loginUser($client, $parentUser);
        $data = $this->getJson($client, '/api/drivers/search?q=1234&school=' . $school->getId());

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
        $this->assertSame('12345678', $data['results'][0]['identificationNumber']);
    }

    public function testSearchMultiTenancy(): void
    {
        $client = $this->createApiClient();
        $schoolA = SchoolFactory::createOne();
        $schoolB = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        StudentFactory::new()->with([
            'school' => $schoolA,
        ])->withParent($parentUser)->create();

        // Driver with route in school A
        $driverUserA = UserFactory::new()->with([
            'firstName' => 'Carlos',
            'lastName' => 'García',
            'identificationNumber' => '12345678',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driverA = DriverFactory::new()->with([
            'user' => $driverUserA,
            'nickname' => 'Carlitos',
        ])->create();
        RouteFactory::new()->withDriver($driverA)->withSchool($schoolA)->create();

        // Driver with route in school B — must NOT appear
        $driverUserB = UserFactory::new()->with([
            'firstName' => 'Carlos',
            'lastName' => 'López',
            'identificationNumber' => '99998888',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driverB = DriverFactory::new()->with([
            'user' => $driverUserB,
            'nickname' => 'Carlitos2',
        ])->create();
        RouteFactory::new()->withDriver($driverB)->withSchool($schoolB)->create();

        $this->loginUser($client, $parentUser);
        $data = $this->getJson($client, '/api/drivers/search?q=Carlos&school=' . $schoolA->getId());

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $data['results']);
        $this->assertSame('Carlitos', $data['results'][0]['nickname']);
    }

    public function testSearchRequiresAuthentication(): void
    {
        $client = $this->createApiClient();
        $client->request(Request::METHOD_GET, '/api/drivers/search?q=test');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSearchRequiresParentOrAdminRole(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $driverUser = UserFactory::new()->with([
            'school' => $school,
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        DriverFactory::new()->with([
            'user' => $driverUser,
        ])->create();

        $this->loginUser($client, $driverUser);
        $client->request(Request::METHOD_GET, '/api/drivers/search?q=test', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSearchMinimumQueryLength(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $user = UserFactory::new()->with([
            'school' => $school,
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $this->loginUser($client, $user);
        $data = $this->getJson($client, '/api/drivers/search?q=a');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $data['results']);
    }

    public function testSearchEmptyQuery(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $user = UserFactory::new()->with([
            'school' => $school,
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $this->loginUser($client, $user);
        $data = $this->getJson($client, '/api/drivers/search?q=');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $data['results']);
    }

    public function testSearchPagination(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $adminUser = UserFactory::new()->with([
            'school' => $school,
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        // Create 5 drivers with 'Test' prefix and routes to the school
        for ($i = 1; $i <= 5; ++$i) {
            $driverUser = UserFactory::new()->with([
                'firstName' => 'Test' . $i,
                'lastName' => 'Driver',
                'identificationNumber' => '1000000' . $i,
                'roles' => ['ROLE_DRIVER'],
            ])->create();
            $driver = DriverFactory::new()->with([
                'user' => $driverUser,
                'nickname' => 'TestDriver' . $i,
            ])->create();
            RouteFactory::new()->withDriver($driver)->withSchool($school)->create();
        }

        $this->loginUser($client, $adminUser);
        $data = $this->getJson($client, '/api/drivers/search?q=Test&itemsPerPage=2&page=1');

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data['results']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(2, $data['itemsPerPage']);
    }

    public function testSearchReturnsExpectedFields(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $adminUser = UserFactory::new()->with([
            'school' => $school,
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $driverUser = UserFactory::new()->with([
            'firstName' => 'Carlos',
            'lastName' => 'García',
            'identificationNumber' => '12345678',
            'roles' => ['ROLE_DRIVER'],
        ])->create();
        $driver = DriverFactory::new()->with([
            'user' => $driverUser,
            'nickname' => 'Carlitos',
        ])->create();
        RouteFactory::new()->withDriver($driver)->withSchool($school)->create();

        $this->loginUser($client, $adminUser);
        $data = $this->getJson($client, '/api/drivers/search?q=Carlitos');

        self::assertResponseIsSuccessful();
        $result = $data['results'][0];
        $this->assertArrayHasKey('driverId', $result);
        $this->assertArrayHasKey('nickname', $result);
        $this->assertArrayHasKey('firstName', $result);
        $this->assertArrayHasKey('lastName', $result);
        $this->assertArrayHasKey('identificationNumber', $result);
        $this->assertArrayHasKey('score', $result);
    }

    public function testSearchNoResults(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $adminUser = UserFactory::new()->with([
            'school' => $school,
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ])->create();

        $this->loginUser($client, $adminUser);
        $data = $this->getJson($client, '/api/drivers/search?q=ZZZZZZZ');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $data['results']);
        $this->assertSame(0, $data['total']);
    }

    public function testParentWithoutSchoolParamGetsBadRequest(): void
    {
        $client = $this->createApiClient();
        $school = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        StudentFactory::new()->with([
            'school' => $school,
        ])->withParent($parentUser)->create();

        $this->loginUser($client, $parentUser);
        $client->request(Request::METHOD_GET, '/api/drivers/search?q=test', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testParentCannotSearchSchoolWithoutChild(): void
    {
        $client = $this->createApiClient();
        $schoolA = SchoolFactory::createOne();
        $schoolB = SchoolFactory::createOne();

        $parentUser = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        // Parent has child in school A, but tries to search school B
        StudentFactory::new()->with([
            'school' => $schoolA,
        ])->withParent($parentUser)->create();

        $this->loginUser($client, $parentUser);
        $client->request(Request::METHOD_GET, '/api/drivers/search?q=test&school=' . $schoolB->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Rate limiting (30 req/10s per user) is configured in rate_limiter.yaml but
     * cannot be reliably tested in WebTestCase because InMemoryStorage is recreated
     * per request (the kernel reboots). The rate limiter component itself is tested
     * by Symfony. This test verifies the limiter service is wired correctly.
     */
    public function testSearchRateLimiterIsConfigured(): void
    {
        $this->createApiClient();

        $container = self::getContainer();
        // Verify the rate limiter service is wired — throws if not found
        $container->get('limiter.driver_search');
        $this->addToAssertionCount(1);
    }
}
