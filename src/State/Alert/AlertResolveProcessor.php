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
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/driver-alerts/{alertId}/resolve.
 *
 * Marks the alert as RESOLVED. The caller must be the distressed driver,
 * the responding driver, or a school admin.
 *
 * @implements ProcessorInterface<mixed, AlertActionOutput>
 */
final readonly class AlertResolveProcessor implements ProcessorInterface
{
    public function __construct(
        private DriverAlertRepository $driverAlertRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AlertActionOutput
    {
        $alertId = is_string($uriVariables['alertId'] ?? null) ? $uriVariables['alertId'] : '';
        $alert = $this->driverAlertRepository->findByAlertId($alertId);

        if (! $alert instanceof DriverAlert) {
            throw new NotFoundHttpException('Alert not found.');
        }

        if ($alert->getStatus() === AlertStatus::RESOLVED) {
            throw new UnprocessableEntityHttpException('Alert is already resolved.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $user->getDriver();

        $distressedDriverId = $alert->getDistressedDriver()?->getId();
        $respondingDriverId = $alert->getRespondingDriver()?->getId();
        $isSchoolAdmin = $this->security->isGranted('ROLE_SCHOOL_ADMIN');
        $isDistressedDriver = $driver !== null && $driver->getId() === $distressedDriverId;
        $isRespondingDriver = $driver !== null && $driver->getId() === $respondingDriverId;

        if (! $isDistressedDriver && ! $isRespondingDriver && ! $isSchoolAdmin) {
            throw new AccessDeniedHttpException('You are not authorised to resolve this alert.');
        }

        $alert->setStatus(AlertStatus::RESOLVED);
        $alert->setResolvedAt(new DateTimeImmutable());
        $alert->setResolvedBy($user);

        $this->entityManager->flush();

        return new AlertActionOutput(
            success: true,
            alertId: $alert->getAlertId(),
            status: $alert->getStatus()->value,
        );
    }
}
