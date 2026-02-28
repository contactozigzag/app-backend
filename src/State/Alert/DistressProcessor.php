<?php

declare(strict_types=1);

namespace App\State\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Alert\DistressOutput;
use App\Entity\DriverAlert;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Message\DriverDistressMessage;
use App\Repository\ActiveRouteRepository;
use App\Repository\DriverAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles POST /api/routes/sessions/{id}/distress.
 *
 * Validates that the authenticated driver owns the given route session,
 * creates a DriverAlert, and dispatches DriverDistressMessage for async
 * notification of nearby drivers.
 *
 * @implements ProcessorInterface<mixed, DistressOutput>
 */
final readonly class DistressProcessor implements ProcessorInterface
{
    public function __construct(
        private ActiveRouteRepository $activeRouteRepository,
        private DriverAlertRepository $driverAlertRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DistressOutput
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $activeRoute = $this->activeRouteRepository->find($id);

        if ($activeRoute === null) {
            throw new NotFoundHttpException('Route session not found.');
        }

        if ($activeRoute->getStatus() !== 'in_progress') {
            throw new UnprocessableEntityHttpException('Route session is not in progress.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $user->getDriver();

        if ($driver === null || $activeRoute->getDriver()?->getId() !== $driver->getId()) {
            throw new AccessDeniedHttpException('You are not the driver of this route session.');
        }

        // Check if there's already an active alert for this driver
        $existing = $this->driverAlertRepository->findActiveByDistressedDriver($driver);
        if ($existing instanceof DriverAlert) {
            throw new HttpException(409, 'An active distress alert already exists.');
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

        return new DistressOutput(alertId: $alert->getAlertId());
    }
}
