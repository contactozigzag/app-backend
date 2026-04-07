<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;

final class MessengerMonitorControllerTest extends WebTestCase
{
    use Factories;

    public function testSuperAdminCanAccessMessengerMonitor(): void
    {
        $client = self::createClient();
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $client->loginUser($admin);

        $client->request(Request::METHOD_GET, '/admin/messenger');

        self::assertResponseIsSuccessful();
    }

    public function testSchoolAdminCannotAccessMessengerMonitor(): void
    {
        $client = self::createClient();
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SCHOOL_ADMIN'],
        ]);
        $client->loginUser($admin);

        $client->request(Request::METHOD_GET, '/admin/messenger');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/admin/messenger');

        self::assertResponseRedirects();
    }
}
