<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\ActiveRouteFactory;
use App\Tests\Factory\ActiveRouteStopFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class LiveOperationsControllerTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
    }

    #[Test]
    public function index_returns_200_for_super_admin(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/live-operations');

        self::assertResponseIsSuccessful();
        $this->assertStringContainsString('Live Operations', (string) $this->client->getResponse()->getContent());
    }

    #[Test]
    public function index_returns_403_for_parent(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);

        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/live-operations');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function active_drivers_returns_valid_json_array(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        ActiveRouteFactory::new()->inProgress()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/api/active-drivers');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('driverId', $data[0]);
        $this->assertArrayHasKey('latitude', $data[0]);
        $this->assertArrayHasKey('longitude', $data[0]);
        $this->assertArrayHasKey('status', $data[0]);
    }

    #[Test]
    public function active_drivers_returns_empty_array_when_no_routes(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/api/active-drivers');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    #[Test]
    public function active_routes_list_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        ActiveRouteFactory::new()->inProgress()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/api/active-routes/list');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('active-routes-list', $content);
    }

    #[Test]
    public function route_detail_panel_returns_html_with_student_manifest(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $student = StudentFactory::new()->create();
        $route = ActiveRouteFactory::new()->inProgress()->create();
        ActiveRouteStopFactory::new()->withActiveRoute($route)->withStudent($student)->create();

        $this->client->loginUser($admin);
        $this->client->request(
            Request::METHOD_GET,
            sprintf('/admin/api/route/%d/detail-panel', $route->getId())
        );

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('route-detail-panel', $content);
        $this->assertStringContainsString((string) $student->getFirstName(), $content);
    }

    #[Test]
    public function route_detail_panel_returns_404_for_unknown_route(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/api/route/99999/detail-panel');

        self::assertResponseStatusCodeSame(404);
    }
}
