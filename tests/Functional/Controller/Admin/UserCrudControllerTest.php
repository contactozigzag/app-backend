<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class UserCrudControllerTest extends WebTestCase
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
    public function it_creates_user_with_valid_password(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['User']['email'] = 'newuser@test.com';
        $values['User']['identificationNumber'] = '12345678';
        $values['User']['firstName'] = 'Test';
        $values['User']['lastName'] = 'User';
        $values['User']['phoneNumber'] = '1234567890';
        $values['User']['roles'] = ['ROLE_USER'];
        $values['User']['newPassword'] = 'SecurePass123!';
        $values['User']['newPasswordConfirm'] = 'SecurePass123!';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'newuser@test.com',
        ]);
        $this->assertInstanceOf(User::class, $user, 'User should be created');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($user, 'SecurePass123!'), 'Password should be hashed correctly');
    }

    #[Test]
    public function it_fails_to_create_user_without_password(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['User']['email'] = 'newuser@test.com';
        $values['User']['identificationNumber'] = '12345678';
        $values['User']['firstName'] = 'Test';
        $values['User']['lastName'] = 'User';
        $values['User']['phoneNumber'] = '1234567890';
        $values['User']['roles'] = ['ROLE_USER'];
        $values['User']['newPassword'] = '';
        $values['User']['newPasswordConfirm'] = '';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger, .flash-danger');

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'newuser@test.com',
        ]);
        $this->assertNotInstanceOf(User::class, $user, 'User should not be created without a password');
    }

    #[Test]
    public function it_fails_to_create_user_when_passwords_dont_match(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        $values['User']['email'] = 'newuser@test.com';
        $values['User']['identificationNumber'] = '12345678';
        $values['User']['firstName'] = 'Test';
        $values['User']['lastName'] = 'User';
        $values['User']['phoneNumber'] = '1234567890';
        $values['User']['roles'] = ['ROLE_USER'];
        $values['User']['newPassword'] = 'SecurePass123!';
        $values['User']['newPasswordConfirm'] = 'DifferentPass456!';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger, .flash-danger');

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'newuser@test.com',
        ]);
        $this->assertNotInstanceOf(User::class, $user, 'User should not be created when passwords do not match');
    }

    #[Test]
    public function it_updates_user_password_successfully(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $target = UserFactory::new()->create([
            'email' => 'target@test.com',
            'roles' => ['ROLE_USER'],
        ]);

        $this->client->loginUser($admin);

        $targetId = $target->getId();
        $originalHash = $target->getPassword();

        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/user/%d/edit', $targetId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form();
        $values = $form->getPhpValues();
        $values['User']['newPassword'] = 'NewSecurePass789!';
        $values['User']['newPasswordConfirm'] = 'NewSecurePass789!';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->find($targetId);

        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertNotSame($originalHash, $updatedUser->getPassword(), 'Password hash should have changed');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($updatedUser, 'NewSecurePass789!'), 'New password should be valid');
    }

    #[Test]
    public function it_updates_user_without_changing_password(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $target = UserFactory::new()->create([
            'email' => 'target@test.com',
            'roles' => ['ROLE_USER'],
        ]);

        $this->client->loginUser($admin);

        $targetId = $target->getId();
        $originalHash = $target->getPassword();

        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/user/%d/edit', $targetId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form();
        $values = $form->getPhpValues();
        $values['User']['newPassword'] = '';
        $values['User']['newPasswordConfirm'] = '';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->find($targetId);

        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertSame($originalHash, $updatedUser->getPassword(), 'Password hash should not change when fields left blank');
    }

    #[Test]
    public function it_fails_to_update_user_when_passwords_dont_match(): void
    {
        $admin = UserFactory::new()->create([
            'email' => 'admin@example.com',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        $target = UserFactory::new()->create([
            'email' => 'target@test.com',
            'roles' => ['ROLE_USER'],
        ]);

        $this->client->loginUser($admin);

        $targetId = $target->getId();
        $originalHash = $target->getPassword();

        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/user/%d/edit', $targetId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form();
        $values = $form->getPhpValues();
        $values['User']['newPassword'] = 'NewSecurePass789!';
        $values['User']['newPasswordConfirm'] = 'DifferentPass000!';

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger, .flash-danger');

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->find($targetId);

        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertSame($originalHash, $updatedUser->getPassword(), 'Password hash should not change when passwords do not match');
    }
}
