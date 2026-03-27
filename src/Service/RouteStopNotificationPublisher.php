<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RouteStop;
use App\Entity\Student;
use App\Entity\User;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes Mercure notifications for the route-stop link request lifecycle:
 *   - Parent creates unconfirmed stop → driver notified
 *   - Driver confirms stop → parent(s) notified
 *   - Driver rejects stop → parent(s) notified
 *
 * All publishes target /api/users/{id}/notifications (private topic).
 * Failures are logged but never propagated.
 */
readonly class RouteStopNotificationPublisher
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Notify the driver that a parent has requested a new stop on their route.
     */
    public function notifyDriverOfNewRequest(RouteStop $routeStop): void
    {
        $driverUser = $routeStop->getRoute()?->getDriver()?->getUser();

        if (! $driverUser instanceof User) {
            return;
        }

        $student = $routeStop->getStudent();

        $this->publish($driverUser, [
            'event' => 'route_stop_requested',
            'routeStopId' => $routeStop->getId(),
            'routeId' => $routeStop->getRoute()?->getId(),
            'routeName' => $routeStop->getRoute()?->getName(),
            'studentId' => $student?->getId(),
            'studentName' => $student instanceof Student
                ? $student->getFirstName() . ' ' . $student->getLastName()
                : null,
            'timestamp' => new DateTimeImmutable()->format('c'),
        ]);
    }

    /**
     * Notify the student's parent(s) that the stop was confirmed by the driver.
     */
    public function notifyParentsOfConfirmation(RouteStop $routeStop): void
    {
        $this->notifyParents($routeStop, 'route_stop_confirmed');
    }

    /**
     * Notify the student's parent(s) that the stop was rejected by the driver.
     */
    public function notifyParentsOfRejection(RouteStop $routeStop): void
    {
        $this->notifyParents($routeStop, 'route_stop_rejected');
    }

    private function notifyParents(RouteStop $routeStop, string $eventType): void
    {
        $student = $routeStop->getStudent();

        if (! $student instanceof Student) {
            return;
        }

        $driverUser = $routeStop->getRoute()?->getDriver()?->getUser();
        $data = [
            'event' => $eventType,
            'routeStopId' => $routeStop->getId(),
            'routeId' => $routeStop->getRoute()?->getId(),
            'routeName' => $routeStop->getRoute()?->getName(),
            'studentId' => $student->getId(),
            'studentName' => $student->getFirstName() . ' ' . $student->getLastName(),
            'driverName' => $driverUser instanceof User
                ? $driverUser->getFirstName() . ' ' . $driverUser->getLastName()
                : null,
            'timestamp' => new DateTimeImmutable()->format('c'),
        ];

        foreach ($student->getParents() as $parent) {
            $this->publish($parent, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publish(User $user, array $data): void
    {
        $userId = $user->getId();

        if ($userId === null) {
            return;
        }

        try {
            $topic = sprintf('/api/users/%d/notifications', $userId);
            $update = new Update(
                topics: [$topic],
                data: json_encode($data, JSON_THROW_ON_ERROR),
                private: true,
            );
            $this->hub->publish($update);

            $this->logger->debug('RouteStopNotificationPublisher: published notification', [
                'user_id' => $userId,
                'event' => $data['event'] ?? 'unknown',
            ]);
        } catch (Exception $exception) {
            $this->logger->error('RouteStopNotificationPublisher: failed to publish notification', [
                'user_id' => $userId,
                'event' => $data['event'] ?? 'unknown',
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
