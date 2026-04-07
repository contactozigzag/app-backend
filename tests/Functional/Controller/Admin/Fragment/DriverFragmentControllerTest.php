<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin\Fragment;

use App\Tests\Factory\ActiveRouteFactory;
use App\Tests\Factory\DriverAlertFactory;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\DriverRateFactory;
use App\Tests\Factory\RouteFactory;
use App\Tests\Factory\UserFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class DriverFragmentControllerTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
    }

    #[Test]
    public function routes_tab_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        RouteFactory::new()->create([
            'driver' => $driver,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d/routes', $driver->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('driver-routes', $content);
    }

    #[Test]
    public function routes_tab_paginates_correctly(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        RouteFactory::new()->createMany(20, [
            'driver' => $driver,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d/routes?page=2', $driver->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('driver-routes', $content);
        // Page 2 with page size 15 means 5 remaining routes should be shown
        $this->assertStringContainsString('Prev', $content);
    }

    #[Test]
    public function rates_tab_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        DriverRateFactory::new()->create([
            'driver' => $driver,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d/rates', $driver->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('driver-rates', $content);
    }

    #[Test]
    public function history_tab_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        ActiveRouteFactory::new()->completed()->withDriver($driver)->create([
            'date' => new DateTimeImmutable('-5 days'),
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d/history', $driver->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('driver-history', $content);
    }

    #[Test]
    public function alerts_tab_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        DriverAlertFactory::new()->create([
            'distressedDriver' => $driver,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d/alerts', $driver->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('driver-alerts', $content);
    }

    #[Test]
    public function routes_tab_returns_403_for_parent(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $driver = DriverFactory::new()->create();

        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver/%d/routes', $driver->getId()));

        self::assertResponseStatusCodeSame(403);
    }
}
