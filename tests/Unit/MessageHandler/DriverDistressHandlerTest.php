<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\DriverAlert;
use App\Entity\Route;
use App\Entity\School;
use App\Message\DriverDistressMessage;
use App\MessageHandler\DriverDistressHandler;
use App\Repository\DriverAlertRepository;
use App\Repository\LocationUpdateRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class DriverDistressHandlerTest extends TestCase
{
    private function makeAlert(
        int $distressedDriverId,
        int $schoolId,
        string $lat = '-34.603722',
        string $lng = '-58.381592',
    ): MockObject&DriverAlert {
        $distressedDriver = $this->createStub(Driver::class);
        $distressedDriver->method('getId')->willReturn($distressedDriverId);

        $school = $this->createStub(School::class);
        $school->method('getId')->willReturn($schoolId);

        $routeTemplate = $this->createStub(Route::class);
        $routeTemplate->method('getSchool')->willReturn($school);

        $routeSession = $this->createStub(ActiveRoute::class);
        $routeSession->method('getRouteTemplate')->willReturn($routeTemplate);
        $routeSession->method('getId')->willReturn(10);

        $alert = $this->createMock(DriverAlert::class);
        $alert->method('getLocationLat')->willReturn($lat);
        $alert->method('getLocationLng')->willReturn($lng);
        $alert->method('getDistressedDriver')->willReturn($distressedDriver);
        $alert->method('getAlertId')->willReturn('test-alert-uuid');
        $alert->method('getRouteSession')->willReturn($routeSession);
        $alert->method('setNearbyDriverIds')->willReturnSelf();

        return $alert;
    }

    public function testPublishesToNearbyDriverAndAdminTopics(): void
    {
        $alert = $this->makeAlert(distressedDriverId: 99, schoolId: 5);
        $alert->expects($this->once())
            ->method('setNearbyDriverIds')
            ->with([42]);

        $alertRepo = $this->createStub(DriverAlertRepository::class);
        $alertRepo->method('find')->willReturn($alert);

        $locationRepo = $this->createStub(LocationUpdateRepository::class);
        $locationRepo->method('findNearbyDriversInProgress')->willReturn([
            [
                'driverId' => 42,
                'lat' => -34.604,
                'lng' => -58.382,
                'distanceMeters' => 150.0,
            ],
        ]);

        $publishedTopics = [];
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))
            ->method('publish')
            ->willReturnCallback(static function (Update $update) use (&$publishedTopics): string {
                $publishedTopics[] = $update->getTopics()[0];
                return '';
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $handler = new DriverDistressHandler(
            driverAlertRepository: $alertRepo,
            locationUpdateRepository: $locationRepo,
            hub: $hub,
            entityManager: $em,
            logger: new NullLogger(),
            proximityRadiusKm: 5.0,
        );

        ($handler)(new DriverDistressMessage(driverAlertId: 1));

        $this->assertContains('/alerts/driver/42', $publishedTopics);
        $this->assertContains('/alerts/admin/5', $publishedTopics);
    }

    public function testSkipsPublishingWhenAlertNotFound(): void
    {
        $alertRepo = $this->createStub(DriverAlertRepository::class);
        $alertRepo->method('find')->willReturn(null);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $handler = new DriverDistressHandler(
            driverAlertRepository: $alertRepo,
            locationUpdateRepository: $this->createStub(LocationUpdateRepository::class),
            hub: $hub,
            entityManager: $em,
            logger: new NullLogger(),
            proximityRadiusKm: 5.0,
        );

        ($handler)(new DriverDistressMessage(driverAlertId: 999));
    }

    public function testPublishesOnlyToAdminWhenNoNearbyDrivers(): void
    {
        $alert = $this->makeAlert(distressedDriverId: 99, schoolId: 5);
        $alert->expects($this->once())
            ->method('setNearbyDriverIds')
            ->with([]);

        $alertRepo = $this->createStub(DriverAlertRepository::class);
        $alertRepo->method('find')->willReturn($alert);

        $locationRepo = $this->createStub(LocationUpdateRepository::class);
        $locationRepo->method('findNearbyDriversInProgress')->willReturn([]);

        $publishedTopics = [];
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willReturnCallback(static function (Update $update) use (&$publishedTopics): void {
                $publishedTopics[] = $update->getTopics()[0];
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $handler = new DriverDistressHandler(
            driverAlertRepository: $alertRepo,
            locationUpdateRepository: $locationRepo,
            hub: $hub,
            entityManager: $em,
            logger: new NullLogger(),
            proximityRadiusKm: 5.0,
        );

        ($handler)(new DriverDistressMessage(driverAlertId: 1));

        $this->assertSame(['/alerts/admin/5'], $publishedTopics);
    }

    public function testPassesRadiusInMetersToRepository(): void
    {
        $distressedDriver = $this->createStub(Driver::class);
        $distressedDriver->method('getId')->willReturn(99);

        $alert = $this->createStub(DriverAlert::class);
        $alert->method('getLocationLat')->willReturn('-34.603722');
        $alert->method('getLocationLng')->willReturn('-58.381592');
        $alert->method('getDistressedDriver')->willReturn($distressedDriver);
        $alert->method('getAlertId')->willReturn('test-alert-uuid');

        $alertRepo = $this->createStub(DriverAlertRepository::class);
        $alertRepo->method('find')->willReturn($alert);

        $locationRepo = $this->createMock(LocationUpdateRepository::class);
        $locationRepo->expects($this->once())
            ->method('findNearbyDriversInProgress')
            ->with(
                self::anything(),
                self::anything(),
                3000.0, // 3 km × 1000 = 3000 m
                self::anything(),
                self::anything(),
            )
            ->willReturn([]);

        $handler = new DriverDistressHandler(
            driverAlertRepository: $alertRepo,
            locationUpdateRepository: $locationRepo,
            hub: $this->createStub(HubInterface::class),
            entityManager: $this->createStub(EntityManagerInterface::class),
            logger: new NullLogger(),
            proximityRadiusKm: 3.0,
        );

        ($handler)(new DriverDistressMessage(driverAlertId: 1));
    }
}
