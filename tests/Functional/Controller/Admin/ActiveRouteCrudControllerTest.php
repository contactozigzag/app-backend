<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\ActiveRouteFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class ActiveRouteCrudControllerTest extends WebTestCase
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
        ActiveRouteFactory::new()->inProgress()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/active-route');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $session = ActiveRouteFactory::new()->completed()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/active-route/%d', $session->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_GET, '/admin/active-route');

        self::assertResponseIsSuccessful();
        // The "Create" button should not appear since new is disabled
        self::assertSelectorNotExists('a[href*="active-route/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);

        $this->client->request(Request::METHOD_GET, '/admin/active-route');

        self::assertResponseStatusCodeSame(403);
    }
}
