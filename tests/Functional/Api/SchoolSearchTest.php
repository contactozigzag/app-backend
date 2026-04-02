<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Functional tests for GET /api/schools/search.
 *
 * All tests exercise the Doctrine fallback path (no live OpenSearch in CI).
 * The fallback performs a prefix LIKE query so results match the leading
 * characters of a school name — e.g., "Esc" matches "Escuela San Martín".
 */
final class SchoolSearchTest extends AbstractApiTestCase
{
    public function testSearchByNamePrefix(): void
    {
        $client = $this->createApiClient();

        SchoolFactory::new()->with(['name' => 'Escuela San Martín'])->create();
        SchoolFactory::new()->with(['name' => 'Colegio Nacional'])->create();

        $user = UserFactory::new()->with(['roles' => ['ROLE_PARENT']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=Esc');

        self::assertResponseIsSuccessful();
        $this->assertCount(1, $data['results']);
        $this->assertSame('Escuela San Martín', $data['results'][0]['name']);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $client = $this->createApiClient();

        SchoolFactory::new()->with(['name' => 'Escuela San Martín'])->create();

        $user = UserFactory::new()->with(['roles' => ['ROLE_PARENT']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=escuela');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
        $this->assertSame('Escuela San Martín', $data['results'][0]['name']);
    }

    public function testSearchReturnsExpectedFields(): void
    {
        $client = $this->createApiClient();

        SchoolFactory::new()->with(['name' => 'Colegio Nacional'])->create();

        $user = UserFactory::new()->with(['roles' => ['ROLE_SCHOOL_ADMIN']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=Colegio');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
        $result = $data['results'][0];
        $this->assertArrayHasKey('schoolId', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('score', $result);
    }

    public function testSearchReturnsEmptyForNoMatch(): void
    {
        $client = $this->createApiClient();

        SchoolFactory::new()->with(['name' => 'Escuela San Martín'])->create();

        $user = UserFactory::new()->with(['roles' => ['ROLE_PARENT']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=ZZZZZ');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $data['results']);
        $this->assertSame(0, $data['total']);
    }

    public function testSearchMinimumQueryLength(): void
    {
        $client = $this->createApiClient();

        $user = UserFactory::new()->with(['roles' => ['ROLE_PARENT']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=E');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $data['results']);
        $this->assertSame(0, $data['total']);
    }

    public function testSearchEmptyQuery(): void
    {
        $client = $this->createApiClient();

        $user = UserFactory::new()->with(['roles' => ['ROLE_PARENT']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=');

        self::assertResponseIsSuccessful();
        $this->assertSame([], $data['results']);
    }

    public function testSearchRequiresAuthentication(): void
    {
        $client = $this->createApiClient();

        $client->request(Request::METHOD_GET, '/api/schools/search?q=test');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAnyAuthenticatedUserCanSearch(): void
    {
        $client = $this->createApiClient();

        SchoolFactory::new()->with(['name' => 'Colegio Nacional'])->create();

        // ROLE_DRIVER (lower than PARENT/ADMIN) must also be allowed
        $driver = UserFactory::new()->with(['roles' => ['ROLE_DRIVER']])->create();
        $this->loginUser($client, $driver);

        $data = $this->getJson($client, '/api/schools/search?q=Colegio');

        self::assertResponseIsSuccessful();
        $this->assertNotEmpty($data['results']);
    }

    public function testSearchPagination(): void
    {
        $client = $this->createApiClient();

        for ($i = 1; $i <= 5; ++$i) {
            SchoolFactory::new()->with(['name' => 'Escuela Test ' . $i])->create();
        }

        $user = UserFactory::new()->with(['roles' => ['ROLE_SCHOOL_ADMIN']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=Escuela&itemsPerPage=2&page=1');

        self::assertResponseIsSuccessful();
        $this->assertCount(2, $data['results']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(2, $data['itemsPerPage']);
    }

    public function testSearchResponseStructure(): void
    {
        $client = $this->createApiClient();

        $user = UserFactory::new()->with(['roles' => ['ROLE_PARENT']])->create();
        $this->loginUser($client, $user);

        $data = $this->getJson($client, '/api/schools/search?q=test');

        self::assertResponseIsSuccessful();
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('itemsPerPage', $data);
    }

    public function testRateLimiterIsConfigured(): void
    {
        $this->createApiClient();

        $container = self::getContainer();
        $container->get('limiter.school_search');
        $this->addToAssertionCount(1);
    }
}
