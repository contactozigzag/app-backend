<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\RouteStopFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class RouteStopCrudControllerTest extends WebTestCase
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
        RouteStopFactory::new()->createMany(3);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/route-stop');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200_and_shows_stop(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $stop = RouteStopFactory::new()->create([
            'stopOrder' => 7,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/route-stop/%d', $stop->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '7');
    }

    #[Test]
    public function role_parent_is_forbidden_from_route_stop_crud(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);

        $this->client->request(Request::METHOD_GET, '/admin/route-stop');

        self::assertResponseStatusCodeSame(403);
    }
}
