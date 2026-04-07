<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Enum\PaymentStatus;
use App\Tests\Factory\PaymentFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class ReconciliationControllerTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
    }

    #[Test]
    public function page_returns_200_for_super_admin(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        PaymentFactory::new()->withStatus(PaymentStatus::APPROVED)->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/reconciliation');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function page_respects_date_filter(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_GET, '/admin/reconciliation', [
            'from' => '2020-01-01',
            'to' => '2020-01-31',
        ]);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/reconciliation');

        self::assertResponseStatusCodeSame(403);
    }
}
