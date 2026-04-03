<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin\Fragment;

use App\Tests\Factory\AttendanceFactory;
use App\Tests\Factory\RouteStopFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class StudentFragmentControllerTest extends WebTestCase
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
    public function attendance_fragment_returns_attendance_records(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $student = StudentFactory::new()->create();
        AttendanceFactory::new()->withStudent($student)->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/student/%d/attendance', $student->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('student-attendance', $content);
        $this->assertStringContainsString('Picked Up', $content);
    }

    #[Test]
    public function attendance_fragment_returns_empty_state_when_no_records(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $student = StudentFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/student/%d/attendance', $student->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('No attendance records', $content);
    }

    #[Test]
    public function route_assignment_fragment_returns_route_stops(): void
    {
        $admin = UserFactory::new()->create([
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);
        $student = StudentFactory::new()->create();
        RouteStopFactory::new()->withStudent($student)->create();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/student/%d/route-assignment', $student->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-frame', $content);
        $this->assertStringContainsString('student-route-assignment', $content);
    }

    #[Test]
    public function attendance_fragment_returns_403_for_parent_role(): void
    {
        $parent = UserFactory::new()->create([
            'roles' => ['ROLE_PARENT'],
        ]);
        $student = StudentFactory::new()->create();

        $this->client->loginUser($parent);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/student/%d/attendance', $student->getId()));

        self::assertResponseStatusCodeSame(403);
    }
}
