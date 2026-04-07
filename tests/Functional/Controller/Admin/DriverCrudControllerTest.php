<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class DriverCrudControllerTest extends WebTestCase
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
        DriverFactory::new()->createMany(3);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/driver');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200_and_shows_driver(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create([
            'nickname' => 'speedydriver',
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d', $driver->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'speedydriver');
    }

    #[Test]
    public function role_parent_is_forbidden_from_driver_crud(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);

        $this->client->request(Request::METHOD_GET, '/admin/driver');

        self::assertResponseStatusCodeSame(403);
    }
}
