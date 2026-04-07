<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin\Fragment;

use App\Tests\Factory\PaymentFactory;
use App\Tests\Factory\RouteStopFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class ParentFragmentControllerTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
    }

    #[Test]
    public function children_tab_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        StudentFactory::new()->withParent($parent)->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/parent/%d/children', $parent->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('parent-children', $content);
    }

    #[Test]
    public function payments_tab_returns_payment_data(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        PaymentFactory::new()->create([
            'user' => $parent,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/parent/%d/payments', $parent->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('parent-payments', $content);
    }

    #[Test]
    public function route_links_tab_returns_html_with_turbo_frame(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $student = StudentFactory::new()->withParent($parent)->create();
        RouteStopFactory::new()->withStudent($student)->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/parent/%d/route-links', $parent->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('parent-route-links', $content);
    }

    #[Test]
    public function children_tab_returns_403_for_parent_role(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);

        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/parent/%d/children', $parent->getId()));

        self::assertResponseStatusCodeSame(403);
    }
}
