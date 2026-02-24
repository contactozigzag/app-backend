<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DriverAlert;
use App\Enum\AlertStatus;
use App\Message\DriverDistressMessage;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'api_distress_')]
class DistressController extends AbstractController
{
    public function __construct(
        private readonly ActiveRouteRepository $activeRouteRepository,
        private readonly DriverAlertRepository $driverAlertRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Trigger a distress signal for an in-progress route session.
     */
    #[Route('/api/routes/sessions/{id}/distress', name: 'trigger_distress', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function triggerDistress(int $id): JsonResponse
    {
        $activeRoute = $this->activeRouteRepository->find($id);

        if ($activeRoute === null) {
            return $this->json([
                'error' => 'Route session not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($activeRoute->getStatus() !== 'in_progress') {
            return $this->json([
                'error' => 'Route session is not in progress',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        if ($driver === null || $activeRoute->getDriver()?->getId() !== $driver->getId()) {
            return $this->json([
                'error' => 'You are not the driver of this route session',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if there's already an active alert for this driver
        $existing = $this->driverAlertRepository->findActiveByDistressedDriver($driver);
        if ($existing !== null) {
            return $this->json([
                'error' => 'An active distress alert already exists',
                'alertId' => $existing->getAlertId(),
            ], Response::HTTP_CONFLICT);
        }

        $alert = new DriverAlert();
        $alert->setDistressedDriver($driver);
        $alert->setRouteSession($activeRoute);
        $alert->setStatus(AlertStatus::PENDING);

        $lat = $activeRoute->getCurrentLatitude() ?? '0.000000';
        $lng = $activeRoute->getCurrentLongitude() ?? '0.000000';
        $alert->setLocationLat($lat);
        $alert->setLocationLng($lng);

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        $this->bus->dispatch(new DriverDistressMessage((int) $alert->getId()));

        return $this->json([
            'alertId' => $alert->getAlertId(),
        ], Response::HTTP_ACCEPTED);
    }
}
