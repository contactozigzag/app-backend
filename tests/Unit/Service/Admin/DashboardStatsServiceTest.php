<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Admin;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\User;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use App\Repository\DriverRepository;
use App\Repository\SchoolRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use App\Service\Admin\DashboardStatsService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DashboardStatsServiceTest extends TestCase
{
    #[Test]
    public function it_returns_platform_kpis_with_correct_keys(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('count')->willReturn(10);

        $studentRepo = $this->createStub(StudentRepository::class);
        $studentRepo->method('count')->willReturn(25);

        $driverRepo = $this->createStub(DriverRepository::class);
        $driverRepo->method('count')->willReturn(5);

        $schoolRepo = $this->createStub(SchoolRepository::class);
        $schoolRepo->method('count')->willReturn(3);

        $activeRouteRepo = $this->createStub(ActiveRouteRepository::class);
        $activeRouteRepo->method('countInProgressToday')->willReturn(2);

        $driverAlertRepo = $this->createStub(DriverAlertRepository::class);
        $driverAlertRepo->method('countOpenAlerts')->willReturn(1);

        $service = new DashboardStatsService(
            $userRepo,
            $studentRepo,
            $driverRepo,
            $schoolRepo,
            $activeRouteRepo,
            $driverAlertRepo,
        );

        $kpis = $service->getPlatformKpis();

        $this->assertArrayHasKey('schools', $kpis);
        $this->assertArrayHasKey('users', $kpis);
        $this->assertArrayHasKey('students', $kpis);
        $this->assertArrayHasKey('drivers', $kpis);
        $this->assertArrayHasKey('activeRoutes', $kpis);
        $this->assertArrayHasKey('openAlerts', $kpis);

        $this->assertSame(3, $kpis['schools']);
        $this->assertSame(10, $kpis['users']);
        $this->assertSame(25, $kpis['students']);
        $this->assertSame(5, $kpis['drivers']);
        $this->assertSame(2, $kpis['activeRoutes']);
        $this->assertSame(1, $kpis['openAlerts']);
    }

    #[Test]
    public function it_maps_active_routes_correctly(): void
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');

        $driver = new Driver();
        $driver->setUser($user);
        $driver->setNickname('johnd');

        $route = new ActiveRoute();
        $route->setDriver($driver);
        $route->setStatus('in_progress');
        $route->setStartedAt(new DateTimeImmutable('08:30'));

        $activeRouteRepo = $this->createStub(ActiveRouteRepository::class);
        $activeRouteRepo->method('findInProgress')->willReturn([$route]);

        $service = new DashboardStatsService(
            $this->createStub(UserRepository::class),
            $this->createStub(StudentRepository::class),
            $this->createStub(DriverRepository::class),
            $this->createStub(SchoolRepository::class),
            $activeRouteRepo,
            $this->createStub(DriverAlertRepository::class),
        );

        $result = $service->getActiveRoutesNow();

        $this->assertCount(1, $result);
        $this->assertSame('John Doe', $result[0]['driverName']);
        $this->assertSame('johnd', $result[0]['driverNickname']);
        $this->assertSame('in_progress', $result[0]['status']);
        $this->assertSame(0, $result[0]['progressPct']);
        $this->assertSame(0, $result[0]['totalStops']);
    }

    #[Test]
    public function it_returns_weekly_chart_data_with_seven_labels(): void
    {
        $activeRouteRepo = $this->createStub(ActiveRouteRepository::class);
        $activeRouteRepo->method('findWeeklyStats')->willReturn([]);

        $service = new DashboardStatsService(
            $this->createStub(UserRepository::class),
            $this->createStub(StudentRepository::class),
            $this->createStub(DriverRepository::class),
            $this->createStub(SchoolRepository::class),
            $activeRouteRepo,
            $this->createStub(DriverAlertRepository::class),
        );

        $data = $service->getWeeklyRouteChartData();

        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('datasets', $data);
        $this->assertIsArray($data['labels']);
        $this->assertIsArray($data['datasets']);
        $this->assertCount(7, $data['labels']);
        $this->assertCount(3, $data['datasets']); // completed, in_progress, cancelled
    }

    #[Test]
    public function it_returns_alert_chart_data_with_three_labels(): void
    {
        $driverAlertRepo = $this->createStub(DriverAlertRepository::class);
        $driverAlertRepo->method('countAllByStatus')->willReturn([
            'PENDING' => 3,
            'RESPONDED' => 1,
            'RESOLVED' => 10,
        ]);

        $service = new DashboardStatsService(
            $this->createStub(UserRepository::class),
            $this->createStub(StudentRepository::class),
            $this->createStub(DriverRepository::class),
            $this->createStub(SchoolRepository::class),
            $this->createStub(ActiveRouteRepository::class),
            $driverAlertRepo,
        );

        $data = $service->getAlertChartData();

        $this->assertSame(['Pending', 'Responded', 'Resolved'], $data['labels']);
        $this->assertSame([3, 1, 10], $data['datasets'][0]['data']);
    }

    #[Test]
    public function get_stats_as_json_returns_valid_json(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('count')->willReturn(7);

        $service = new DashboardStatsService(
            $userRepo,
            $this->createStub(StudentRepository::class),
            $this->createStub(DriverRepository::class),
            $this->createStub(SchoolRepository::class),
            $this->createStub(ActiveRouteRepository::class),
            $this->createStub(DriverAlertRepository::class),
        );

        $json = $service->getStatsAsJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('users', $decoded);
        $this->assertSame(7, $decoded['users']);
    }
}
