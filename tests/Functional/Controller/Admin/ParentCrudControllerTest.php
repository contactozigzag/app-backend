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

final class ParentCrudControllerTest extends WebTestCase
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
    public function index_only_shows_role_parent_users(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        UserFactory::new()->createMany(3, [
            'roles' => ['ROLE_PARENT'],
        ]);
        UserFactory::new()->createMany(2, [
            'roles' => ['ROLE_DRIVER'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/parent');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Parents', $content);
        // 3 parents should be listed (not the 2 drivers)
        $this->assertStringContainsString('3</strong> results', $content);
    }

    #[Test]
    public function detail_page_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/parent/%d', $parent->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString((string) $parent->getFirstName(), $content);
    }

    #[Test]
    public function index_returns_403_for_parent_role(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);

        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/parent');

        self::assertResponseStatusCodeSame(403);
    }
}
