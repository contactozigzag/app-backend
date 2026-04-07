<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Subscription;
use App\Enum\SubscriptionStatus;
use App\Tests\Factory\SubscriptionFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class SubscriptionCrudControllerTest extends WebTestCase
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
        SubscriptionFactory::new()->active()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/subscription');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $subscription = SubscriptionFactory::new()->active()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/subscription/%d', $subscription->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function cancel_action_sets_status_to_cancelled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $subscription = SubscriptionFactory::new()->active()->create();
        $id = $subscription->getId();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/subscription/%d/cancel', $id));

        self::assertResponseRedirects(sprintf('/admin/subscription/%d', $id));

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $refreshed = $em->find(Subscription::class, $id);
        $this->assertInstanceOf(Subscription::class, $refreshed);
        $this->assertSame(SubscriptionStatus::CANCELLED, $refreshed->getStatus());
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/subscription');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="subscription/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/subscription');

        self::assertResponseStatusCodeSame(403);
    }
}
