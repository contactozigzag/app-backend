<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class RouteCrudControllerTest extends WebTestCase
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
        RouteFactory::new()->createMany(3);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/route');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200_and_shows_route(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create([
            'nickname' => 'morning_driver',
        ]);
        $route = RouteFactory::new()->withDriver($driver)->create([
            'name' => 'School Run Alpha',
            'type' => 'morning',
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/route/%d', $route->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'School Run Alpha');
    }

    #[Test]
    public function role_parent_is_forbidden_from_route_crud(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);

        $this->client->request(Request::METHOD_GET, '/admin/route');

        self::assertResponseStatusCodeSame(403);
    }
}
