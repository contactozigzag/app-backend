<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\ArchivedRouteFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class ArchivedRouteCrudControllerTest extends WebTestCase
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
        ArchivedRouteFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/archived-route');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $route = ArchivedRouteFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/archived-route/%d', $route->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function csv_export_returns_csv_headers(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        ArchivedRouteFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/archived-route/export-csv');

        self::assertResponseIsSuccessful();
        $this->assertStringContainsString('text/csv', $this->client->getResponse()->headers->get('Content-Type') ?? '');
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/archived-route');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="archived-route/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/archived-route');

        self::assertResponseStatusCodeSame(403);
    }
}
