<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\DriverRateFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class DriverRateCrudControllerTest extends WebTestCase
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
        DriverRateFactory::new()->flat()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/driver-rate');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function detail_returns_200(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $rate = DriverRateFactory::new()->flat()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/driver-rate/%d', $rate->getId()));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function create_flat_rate_succeeds(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/driver-rate/new');
        self::assertResponseIsSuccessful();

        $form = $this->client->getCrawler()->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['DriverRate']['driver']['autocomplete'] = $driver->getId();
        $values['DriverRate']['pricingModel'] = '0'; // 0 = PricingModel::FLAT (EasyAdmin ChoiceField index)
        $values['DriverRate']['amount'] = '2000.00';
        $values['DriverRate']['currency'] = 'ARS';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
    }

    #[Test]
    public function role_parent_is_forbidden(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, '/admin/driver-rate');

        self::assertResponseStatusCodeSame(403);
    }
}
