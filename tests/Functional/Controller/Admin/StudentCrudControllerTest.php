<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Student;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class StudentCrudControllerTest extends WebTestCase
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
        StudentFactory::new()->createMany(3);

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, '/admin/student');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
    }

    #[Test]
    public function new_form_renders_and_creates_student(): void
    {
        $school = SchoolFactory::new()->create();
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/student/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['Student']['firstName'] = 'María';
        $values['Student']['lastName'] = 'González';
        $values['Student']['identificationNumber'] = '12345678';
        // grade backing value '1' → Grade::One (ChoiceField enum case conversion)
        $values['Student']['grade'] = '1';
        // AssociationField with autocomplete submits as school[autocomplete]
        $values['Student']['school']['autocomplete'] = $school->getId();

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $student = $this->entityManager->getRepository(Student::class)->findOneBy([
            'firstName' => 'María',
            'lastName' => 'González',
        ]);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertSame('12345678', $student->getIdentificationNumber());
    }

    #[Test]
    public function edit_updates_student_name(): void
    {
        $school = SchoolFactory::new()->create();
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $student = StudentFactory::new()->create([
            'school' => $school,
            'firstName' => 'Original',
            'lastName' => 'Name',
        ]);

        $this->client->loginUser($admin);

        $studentId = $student->getId();
        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/student/%d/edit', $studentId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form();
        $values = $form->getPhpValues();
        $values['Student']['firstName'] = 'Updated';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Student::class)->find($studentId);
        $this->assertInstanceOf(Student::class, $updated);
        $this->assertSame('Updated', $updated->getFirstName());
    }

    #[Test]
    public function detail_returns_200_and_shows_student(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $student = StudentFactory::new()->create([
            'firstName' => 'Lucas',
            'lastName' => 'Pérez',
        ]);

        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_GET, sprintf('/admin/student/%d', $student->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Lucas');
        self::assertSelectorTextContains('body', 'Pérez');
    }

    #[Test]
    public function non_admin_is_forbidden_from_student_crud(): void
    {
        // /admin requires ROLE_SUPER_ADMIN — ROLE_PARENT cannot access it
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $this->client->loginUser($parent);

        $this->client->request(Request::METHOD_GET, '/admin/student');

        self::assertResponseStatusCodeSame(403);
    }
}
