<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Vehicle;
use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class VehicleCrudControllerTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function index_returns_200_for_super_admin(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        VehicleFactory::new()->create([
            'driver' => $driver,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/vehicle');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function new_form_creates_vehicle(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();

        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/vehicle/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['Vehicle']['licensePlate'] = 'AB-123-CD';
        $values['Vehicle']['make'] = 'Toyota';
        $values['Vehicle']['model'] = 'Hiace';
        $values['Vehicle']['capacity'] = 15;
        // AssociationField with autocomplete submits as driver[autocomplete]
        $values['Vehicle']['driver']['autocomplete'] = $driver->getId();

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->findOneBy([
            'licensePlate' => 'AB-123-CD',
        ]);

        $this->assertInstanceOf(Vehicle::class, $vehicle);
        $this->assertSame('Toyota', $vehicle->getMake());
        $this->assertSame(15, $vehicle->getCapacity());
    }

    #[Test]
    public function detail_returns_200_and_shows_vehicle(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $driver = DriverFactory::new()->create();
        $vehicle = VehicleFactory::new()->create([
            'licensePlate' => 'XY-999-ZZ',
            'driver' => $driver,
        ]);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/vehicle/%d', $vehicle->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'XY-999-ZZ');
    }

    #[Test]
    public function role_parent_is_forbidden_from_vehicle_crud(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);

        $this->client->request(Request::METHOD_GET, '/admin/vehicle');

        self::assertResponseStatusCodeSame(403);
    }
}
