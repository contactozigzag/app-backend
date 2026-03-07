<?php

declare(strict_types=1);

namespace App\EventSubscriber\Admin;

use App\Entity\ActiveRoute;
use App\Entity\Driver;
use App\Entity\DriverAlert;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Service\Admin\DashboardStatsService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class AdminDashboardPublisher
{
    private bool $pendingStatsPublish = false;

    /**
     * @var list<DriverAlert>
     */
    private array $pendingAlerts = [];

    /**
     * @var list<ActiveRoute>
     */
    private array $pendingRoutes = [];

    public function __construct(
        private readonly HubInterface $hub,
        private readonly DashboardStatsService $statsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof User || $entity instanceof Student || $entity instanceof Driver || $entity instanceof School) {
            $this->pendingStatsPublish = true;
        }

        if ($entity instanceof DriverAlert) {
            $this->pendingAlerts[] = $entity;
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof DriverAlert) {
            $this->pendingAlerts[] = $entity;
        }

        if ($entity instanceof ActiveRoute) {
            $this->pendingRoutes[] = $entity;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingStatsPublish) {
            $this->pendingStatsPublish = false;
            $this->publish('admin/dashboard/stats', $this->statsService->getStatsAsJson());
        }

        foreach ($this->pendingAlerts as $alert) {
            $this->publishAlert($alert);
        }

        $this->pendingAlerts = [];

        foreach ($this->pendingRoutes as $route) {
            $this->publishRoute($route);
        }

        $this->pendingRoutes = [];
    }

    private function publishAlert(DriverAlert $alert): void
    {
        $driver = $alert->getDistressedDriver();
        $user = $driver?->getUser();

        $payload = (string) json_encode([
            'id' => $alert->getId(),
            'alertId' => $alert->getAlertId(),
            'status' => $alert->getStatus()->value,
            'driverName' => $user instanceof User ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
            'triggeredAt' => $alert->getTriggeredAt()->format('Y-m-d H:i'),
            'isOpen' => in_array($alert->getStatus(), [AlertStatus::PENDING, AlertStatus::RESPONDED], true),
        ]);

        $this->publish('admin/alerts', $payload);
    }

    private function publishRoute(ActiveRoute $route): void
    {
        $driver = $route->getDriver();
        $user = $driver?->getUser();

        $payload = (string) json_encode([
            'id' => $route->getId(),
            'status' => $route->getStatus(),
            'driverName' => $user instanceof User ? $user->getFirstName() . ' ' . $user->getLastName() : 'Unknown',
            'startedAt' => $route->getStartedAt()?->format('H:i'),
        ]);

        $this->publish('admin/routes', $payload);
    }

    private function publish(string $topic, string $data): void
    {
        try {
            $this->hub->publish(new Update($topic, $data, false));
        } catch (Exception $exception) {
            $this->logger->error('AdminDashboardPublisher: failed to publish Mercure update', [
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
