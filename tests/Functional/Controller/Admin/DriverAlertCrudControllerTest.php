<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Enum\AlertStatus;
use App\Tests\Factory\DriverAlertFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DriverAlertCrudControllerTest extends WebTestCase
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
    public function index_returns_200_for_super_admin(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        DriverAlertFactory::new()->pending()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/driver-alert');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $alert = DriverAlertFactory::new()->resolved()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver-alert/%d', $alert->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function index_with_status_filter_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        DriverAlertFactory::new()->pending()->create();
        DriverAlertFactory::new()->resolved()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/driver-alert', [
            'filters' => [
                'status' => AlertStatus::PENDING->value,
            ],
        ]);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function chat_page_returns_200_with_no_messages(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $alert = DriverAlertFactory::new()->pending()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/alert/%d/chat', $alert->getId()));

        self::assertResponseIsSuccessful();
        $this->assertStringContainsString('No chat messages', (string) $this->client->getResponse()->getContent());
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/driver-alert');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="driver-alert/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/driver-alert');

        self::assertResponseStatusCodeSame(403);
    }
}
