<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\SpecialEventRoute;
use App\Enum\SpecialEventRouteStatus;
use App\Tests\Factory\SpecialEventRouteFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SpecialEventRouteCrudControllerTest extends WebTestCase
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
        SpecialEventRouteFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/special-event-route');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/special-event-route/%d', $route->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function publish_transitions_draft_to_published(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->create([
            'status' => SpecialEventRouteStatus::DRAFT,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(
            Request::METHOD_GET,
            sprintf('/admin/special-event-route/%d/publish', $route->getId())
        );

        self::assertResponseRedirects();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        $updated = $em->find(SpecialEventRoute::class, $route->getId());
        $this->assertSame(SpecialEventRouteStatus::PUBLISHED, $updated?->getStatus());
    }

    #[Test]
    public function cancel_transitions_published_to_cancelled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $route = SpecialEventRouteFactory::new()->published()->create();

        $this->client->loginUser($admin);
        $this->client->request(
            Request::METHOD_GET,
            sprintf('/admin/special-event-route/%d/cancel', $route->getId())
        );

        self::assertResponseRedirects();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        $updated = $em->find(SpecialEventRoute::class, $route->getId());
        $this->assertSame(SpecialEventRouteStatus::CANCELLED, $updated?->getStatus());
    }

    #[Test]
    public function new_action_is_disabled(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/special-event-route');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="special-event-route/new"]');
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/special-event-route');

        self::assertResponseStatusCodeSame(403);
    }
}
