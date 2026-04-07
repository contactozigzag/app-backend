<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\PushDevice;
use App\Tests\Factory\PushDeviceFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class PushDeviceCrudControllerTest extends WebTestCase
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
        PushDeviceFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/push-device');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $device = PushDeviceFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/push-device/%d', $device->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function deactivate_sets_device_inactive(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $device = PushDeviceFactory::new()->create();

        $this->assertTrue($device->isActive());

        $this->client->loginUser($admin);
        $this->client->request(
            Request::METHOD_GET,
            sprintf('/admin/push-device/%d/deactivate', $device->getId())
        );

        self::assertResponseRedirects();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        $updated = $em->find(PushDevice::class, $device->getId());
        $this->assertFalse($updated?->isActive());
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/push-device');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="push-device/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/push-device');

        self::assertResponseStatusCodeSame(403);
    }
}
