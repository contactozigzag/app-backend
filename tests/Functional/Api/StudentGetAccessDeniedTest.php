<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Component\HttpFoundation\Request;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;

/**
 * Verifies GET /api/students/{id} returns a properly formatted JSON error
 * (not a leaked HTML stack trace) when a parent requests a student that
 * belongs to someone else. The mobile client needs a structured body so it
 * can surface a user-facing error instead of crashing on unparseable HTML.
 */
final class StudentGetAccessDeniedTest extends AbstractApiTestCase
{
    public function testGetOtherParentStudentReturnsJsonError(): void
    {
        $client = $this->createApiClient();

        $caller = UserFactory::new()->with([
            'roles' => ['ROLE_PARENT'],
        ])->create();

        $otherStudent = StudentFactory::createOne();

        $this->loginUser($client, $caller);

        $client->request(Request::METHOD_GET, '/api/students/' . $otherStudent->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(403);

        $response = $client->getResponse();
        $this->assertStringContainsString('application/', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('json', (string) $response->headers->get('Content-Type'));

        $body = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($body, 'response body must be JSON-decodable');

        // AP4 error resource (problem+json-compatible): `status`, `title`, `detail`.
        // The mobile client reads `detail` to surface a user-facing message.
        $this->assertSame(403, $body['status'] ?? null);
        $this->assertArrayHasKey('detail', $body);
    }
}
