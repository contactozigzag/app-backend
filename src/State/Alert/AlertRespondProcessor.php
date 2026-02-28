<?php

declare(strict_types=1);

namespace App\State\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Alert\AlertActionOutput;
use App\Entity\DriverAlert;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Repository\DriverAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Handles POST /api/driver-alerts/{alertId}/respond.
 *
 * Sets the alert status to RESPONDED and notifies the distressed driver
 * via a Mercure SSE event. The caller must be listed in nearbyDriverIds.
 *
 * @implements ProcessorInterface<mixed, AlertActionOutput>
 */
final readonly class AlertRespondProcessor implements ProcessorInterface
{
    public function __construct(
        private DriverAlertRepository $driverAlertRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private HubInterface $hub,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AlertActionOutput
    {
        $alertId = is_string($uriVariables['alertId'] ?? null) ? $uriVariables['alertId'] : '';
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if (! $alert instanceof DriverAlert) {
            throw new NotFoundHttpException('Alert not found.');
        }

        if ($alert->getStatus() !== AlertStatus::PENDING) {
            throw new UnprocessableEntityHttpException('Alert is not in PENDING state.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            throw new AccessDeniedHttpException('Caller has no associated driver record.');
        }

        if (! in_array($driver->getId(), $alert->getNearbyDriverIds(), true)) {
            throw new AccessDeniedHttpException('You were not notified of this alert.');
        }

        $alert->setStatus(AlertStatus::RESPONDED);
        $alert->setRespondingDriver($driver);

        $this->entityManager->flush();

        // Notify distressed driver via Mercure (non-fatal)
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

        return new AlertActionOutput(
            success: true,
            alertId: $alert->getAlertId(),
            status: $alert->getStatus()->value,
        );
    }
}
