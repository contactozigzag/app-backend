<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\StudentReadyForPickupMessage;
use App\Repository\SpecialEventRouteRepository;
use App\Service\NotificationService;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class StudentReadyForPickupHandler
{
    public function __construct(
        private readonly SpecialEventRouteRepository $repository,
        private readonly LockFactory $lockFactory,
        private readonly RouteOptimizationService $routeOptimizationService,
        private readonly HubInterface $hub,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(StudentReadyForPickupMessage $message): void
    {
        // Acquire a non-blocking lock to coalesce rapid student-ready events
        $lock = $this->lockFactory->createLock($message->lockKey, 60.0, false);

        if (! $lock->acquire()) {
            // Another handler is already processing a batch for this route — skip
            $this->logger->debug('StudentReadyForPickupHandler: skipping duplicate batch', [
                'routeId' => $message->specialEventRouteId,
                'lockKey' => $message->lockKey,
            ]);

            return;
        }

        try {
            $route = $this->repository->find($message->specialEventRouteId);

            if ($route === null) {
                $this->logger->warning('StudentReadyForPickupHandler: route not found', [
                    'routeId' => $message->specialEventRouteId,
                ]);

                return;
            }

            // Re-sequence pending stops for all ready students using RouteOptimizationService
            $eventLocation = $route->getEventLocation();
            if ($eventLocation === null) {
                $this->logger->warning('StudentReadyForPickupHandler: no event location set, cannot optimise', [
                    'routeId' => $message->specialEventRouteId,
                ]);

                return;
            }

            $startPoint = ['lat' => (float) ($eventLocation->getLatitude() ?? '0'), 'lng' => (float) ($eventLocation->getLongitude() ?? '0')];

            $pendingReadyStops = [];
            foreach ($route->getStops() as $stop) {
                if ($stop->getStatus() === 'pending' && $stop->isStudentReady()) {
                    $addr = $stop->getAddress();
                    if ($addr === null) {
                        continue;
                    }

                    $pendingReadyStops[] = [
                        'id' => (int) $stop->getId(),
                        'lat' => (float) ($addr->getLatitude() ?? '0'),
                        'lng' => (float) ($addr->getLongitude() ?? '0'),
                    ];
                }
            }

            if ($pendingReadyStops !== []) {
                $schoolAddr = $route->getSchool()?->getAddress();
                $endPoint = $schoolAddr !== null
                    ? ['lat' => (float) ($schoolAddr->getLatitude() ?? '0'), 'lng' => (float) ($schoolAddr->getLongitude() ?? '0')]
                    : $startPoint;

                $optimised = $this->routeOptimizationService->optimizeRoute($startPoint, $endPoint, $pendingReadyStops);

                if ($optimised !== null) {
                    $order = 1;
                    foreach ($optimised['optimized_order'] as $stopId) {
                        foreach ($route->getStops() as $stop) {
                            if ($stop->getId() === $stopId) {
                                $stop->setStopOrder($order++);
                                break;
                            }
                        }
                    }

                    $this->entityManager->flush();
                }
            }

            // Publish updated route to driver
            $driverId = $route->getAssignedDriver()?->getId();
            if ($driverId !== null) {
                try {
                    $this->hub->publish(new Update(
                        sprintf('/tracking/driver/%d', $driverId),
                        json_encode([
                            'type' => 'route_updated',
                            'specialEventRouteId' => $route->getId(),
                            'studentReadyCount' => count($pendingReadyStops),
                        ], JSON_THROW_ON_ERROR),
                    ));
                } catch (\Throwable $e) {
                    $this->logger->error('StudentReadyForPickupHandler: Mercure publish failed', ['error' => $e->getMessage()]);
                }
            }

            // Notify parents of newly-ready students
            foreach ($route->getStudents() as $student) {
                if ($student->getId() !== $message->studentId) {
                    continue;
                }

                foreach ($student->getParents() as $parent) {
                    $this->notificationService->notify(
                        $parent,
                        'Student ready for pickup',
                        sprintf('%s is ready for pickup.', $student->getFirstName()),
                    );
                }
            }

            $this->logger->info('StudentReadyForPickupHandler: completed', [
                'routeId' => $message->specialEventRouteId,
                'studentId' => $message->studentId,
            ]);
        } finally {
            $lock->release();
        }
    }
}
