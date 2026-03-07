<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use App\Entity\Address;
use App\Entity\School;
use App\Tests\Factory\UserFactory;
use App\Service\AddressGeocoder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SchoolCrudControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function it_creates_school_with_geocoded_address_successfully(): void
    {
        // Create admin user
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        // Navigate to school creation page
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/school/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Create New School');

        // Simulate the frontend autocomplete: inject pre-geocoded JSON directly to avoid
        // making a real Google Maps API call in tests.
        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['School']['name'] = 'Test Elementary School';
        $values['School']['addressInput'] = '350 5th Ave, New York, NY 10118, USA';
        $values['School']['addressGeocodedData'] = (string) json_encode([
            'placeId' => 'ChIJaXQRs6lZwokRY6EFpJnhNNE',
            'streetAddress' => '350 5th Ave',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'postalCode' => '10118',
            'lat' => 40.7484,
            'lng' => -73.9967,
        ]);

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // Should redirect after successful creation
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Verify school was created
        $school = $this->entityManager->getRepository(School::class)->findOneBy(['name' => 'Test Elementary School']);

        $this->assertInstanceOf(School::class, $school, 'School should be created');
        $this->assertInstanceOf(Address::class, $school->getAddress(), 'School should have an address');
        $this->assertStringContainsString('5th Ave', (string) $school->getAddress()->getStreetAddress());
        $this->assertSame('New York', $school->getAddress()->getCity());
        $this->assertNotEmpty($school->getAddress()->getLatitude());
        $this->assertNotEmpty($school->getAddress()->getLongitude());
        $this->assertNotEmpty($school->getAddress()->getPlaceId());
    }

    #[Test]
    public function it_shows_error_when_address_cannot_be_geocoded(): void
    {
        // Create admin user
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        // Navigate to school creation page
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/school/new');

        // Fill in form with an address that will fail geocoding (no addressGeocodedData provided)
        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['School']['name'] = 'Test School';
        $values['School']['addressInput'] = 'INVALID_ADDRESS_XYZ_123_NOT_REAL';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // EasyAdmin redirects after persistEntity returns early; follow and check flash
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger, .flash-danger');

        // Verify school was NOT created
        $school = $this->entityManager->getRepository(School::class)->findOneBy(['name' => 'Test School']);
        $this->assertNotInstanceOf(School::class, $school, 'School should not be created with invalid address');
    }

    #[Test]
    public function it_displays_school_list_with_address_details(): void
    {
        // Create admin user
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        // Create a school manually for testing display
        $school = new School();
        $school->setName('Display Test School');

        $addressGeocoder = self::getContainer()->get(AddressGeocoder::class);
        $address = $addressGeocoder->createFromPlainText('1600 Pennsylvania Avenue NW, Washington, DC 20500');

        if ($address !== null) {
            $school->setAddress($address);
            $this->entityManager->persist($school);
            $this->entityManager->flush();

            // Navigate to school list
            $this->client->request(Request::METHOD_GET, '/admin/school');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Display Test School');
            self::assertSelectorTextContains('body', 'Washington');
        } else {
            self::markTestSkipped('Geocoding service unavailable for integration test');
        }
    }

    #[Test]
    public function it_updates_school_with_new_geocoded_address(): void
    {
        // Create admin user
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        // Create existing school
        $school = new School();
        $school->setName('School to Update');

        $addressGeocoder = self::getContainer()->get(AddressGeocoder::class);
        $originalAddress = $addressGeocoder->createFromPlainText('1 Main St, Boston, MA 02101');

        if ($originalAddress !== null) {
            $school->setAddress($originalAddress);
            $this->entityManager->persist($school);
            $this->entityManager->flush();

            $schoolId = $school->getId();

            // Navigate to edit page
            $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/school/%d/edit', $schoolId));

            self::assertResponseIsSuccessful();

            // Update using pre-geocoded data to avoid real API call
            $form = $crawler->selectButton('Save changes')->form();
            $values = $form->getPhpValues();
            $values['School']['name'] = 'Updated School Name';
            $values['School']['addressInput'] = '1600 Amphitheatre Parkway, Mountain View, CA 94043';
            $values['School']['addressGeocodedData'] = (string) json_encode([
                'placeId' => 'ChIJj61dQgK6j4AR4GeTYWZsKWw',
                'streetAddress' => '1600 Amphitheatre Pkwy',
                'city' => 'Mountain View',
                'state' => 'CA',
                'country' => 'US',
                'postalCode' => '94043',
                'lat' => 37.4224,
                'lng' => -122.0842,
            ]);

            $this->client->request($form->getMethod(), $form->getUri(), $values);
            self::assertResponseRedirects();

            // Verify update
            $this->entityManager->clear();
            $updatedSchool = $this->entityManager->getRepository(School::class)->find($schoolId);

            $this->assertInstanceOf(School::class, $updatedSchool);
            $this->assertSame('Updated School Name', $updatedSchool->getName());
            $this->assertInstanceOf(Address::class, $updatedSchool->getAddress());
            $this->assertStringContainsString('Amphitheatre', (string) $updatedSchool->getAddress()->getStreetAddress());
            $this->assertSame('Mountain View', $updatedSchool->getAddress()->getCity());
        } else {
            self::markTestSkipped('Geocoding service unavailable for integration test');
        }
    }

    #[Test]
    public function it_requires_admin_role_to_access_crud_operations(): void
    {
        // Create regular user (not admin)
        $user = UserFactory::new()->create([
            'email' => 'user@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $this->client->loginUser($user);

        // Try to access school creation page
        $this->client->request(Request::METHOD_GET, '/admin/school/new');

        // Should be forbidden (user is authenticated but lacks ROLE_SUPER_ADMIN)
        self::assertResponseStatusCodeSame(403);
    }
}
