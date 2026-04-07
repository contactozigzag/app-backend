<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\PaymentFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class PaymentTransactionCrudControllerTest extends WebTestCase
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
        PaymentFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/payment-transaction');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/payment-transaction');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="payment-transaction/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/payment-transaction');

        self::assertResponseStatusCodeSame(403);
    }
}
