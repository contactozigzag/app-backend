<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DriverAlert;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Repository\DriverAlertRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route(name: 'api_driver_alerts_')]
class DriverAlertController extends AbstractController
{
    public function __construct(
        private readonly DriverAlertRepository $driverAlertRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HubInterface $hub,
    ) {
    }

    /**
     * Respond to a distress alert (notifies distressed driver of responder).
     */
    #[Route('/api/driver-alerts/{alertId}/respond', name: 'respond', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function respond(string $alertId): JsonResponse
    {
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if (! $alert instanceof DriverAlert) {
            return $this->json([
                'error' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($alert->getStatus() !== AlertStatus::PENDING) {
            return $this->json([
                'error' => 'Alert is not in PENDING state',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            return $this->json([
                'error' => 'Caller has no associated driver record',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! in_array($driver->getId(), $alert->getNearbyDriverIds(), true)) {
            return $this->json([
                'error' => 'You were not notified of this alert',
            ], Response::HTTP_FORBIDDEN);
        }

        $alert->setStatus(AlertStatus::RESPONDED);
        $alert->setRespondingDriver($driver);

        $this->entityManager->flush();

        // Notify distressed driver via Mercure
        $distressedDriverId = $alert->getDistressedDriver()?->getId();
        if ($distressedDriverId !== null) {
            try {
                $this->hub->publish(new Update(
                    sprintf('/alerts/driver/%d', $distressedDriverId),
                    json_encode([
                        'alertId' => $alert->getAlertId(),
                        'type' => 'responder_assigned',
                        'responderDriverId' => $driver->getId(),
                        'responderName' => $user->getfullName(),
                    ], JSON_THROW_ON_ERROR),
                ));
            } catch (Throwable) {
                // Non-fatal: Mercure publish failure should not fail the response
            }
        }

        return $this->json([
            'success' => true,
            'alertId' => $alert->getAlertId(),
            'status' => $alert->getStatus()->value,
        ]);
    }

    /**
     * Resolve a distress alert.
     */
    #[Route('/api/driver-alerts/{alertId}/resolve', name: 'resolve', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function resolve(string $alertId): JsonResponse
    {
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if (! $alert instanceof DriverAlert) {
            return $this->json([
                'error' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($alert->getStatus() === AlertStatus::RESOLVED) {
            return $this->json([
                'error' => 'Alert is already resolved',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        // Check that caller is the distressed driver, responding driver, or school admin
        $distressedDriverId = $alert->getDistressedDriver()?->getId();
        $respondingDriverId = $alert->getRespondingDriver()?->getId();
        $isSchoolAdmin = $this->isGranted('ROLE_SCHOOL_ADMIN');
        $isDistressedDriver = $driver !== null && $driver->getId() === $distressedDriverId;
        $isRespondingDriver = $driver !== null && $driver->getId() === $respondingDriverId;

        if (! $isDistressedDriver && ! $isRespondingDriver && ! $isSchoolAdmin) {
            return $this->json([
                'error' => 'You are not authorised to resolve this alert',
            ], Response::HTTP_FORBIDDEN);
        }

        $alert->setStatus(AlertStatus::RESOLVED);
        $alert->setResolvedAt(new DateTimeImmutable());
        $alert->setResolvedBy($user);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'alertId' => $alert->getAlertId(),
            'status' => $alert->getStatus()->value,
        ]);
    }
}
