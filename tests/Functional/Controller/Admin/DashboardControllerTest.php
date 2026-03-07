<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DashboardControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
    }

    #[Test]
    public function super_admin_can_access_dashboard(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'superadmin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="admin-dashboard"]');
    }

    #[Test]
    public function dashboard_renders_kpi_cards(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'superadmin2@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-stat="schools"]');
        self::assertSelectorExists('[data-stat="users"]');
        self::assertSelectorExists('[data-stat="students"]');
        self::assertSelectorExists('[data-stat="drivers"]');
        self::assertSelectorExists('[data-stat="activeRoutes"]');
        self::assertSelectorExists('[data-stat="openAlerts"]');
    }

    #[Test]
    public function dashboard_renders_chart_canvases(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'superadmin3@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('canvas');
    }

    #[Test]
    public function non_admin_user_is_redirected_from_dashboard(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'user@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseStatusCodeSame(403);
    }
}
