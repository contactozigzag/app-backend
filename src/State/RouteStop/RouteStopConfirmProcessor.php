<?php

declare(strict_types=1);

namespace App\State\RouteStop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\RouteStop\RouteStopActionOutput;
use App\Entity\User;
use App\Repository\RouteStopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles PATCH /api/route-stops/{id}/confirm.
 *
 * Validates that the authenticated driver owns the route stop's route,
 * then marks it as active and confirmed.
 *
 * @implements ProcessorInterface<mixed, RouteStopActionOutput>
 */
final readonly class RouteStopConfirmProcessor implements ProcessorInterface
{
    public function __construct(
        private RouteStopRepository $routeStopRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RouteStopActionOutput
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $user->getDriver();

        if ($driver === null) {
            throw new NotFoundHttpException('Driver profile not found.');
        }

        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $routeStop = $this->routeStopRepository->find($id);

        if ($routeStop === null) {
            throw new NotFoundHttpException('Route stop not found.');
        }

        $route = $routeStop->getRoute();

        if ($route === null || $route->getDriver() !== $driver) {
            throw new AccessDeniedHttpException('This route stop does not belong to your routes.');
        }

        $routeStop->setIsActive(true);
        $routeStop->setIsConfirmed(true);

        $this->entityManager->flush();

        return new RouteStopActionOutput(
            success: true,
            message: 'Route stop confirmed successfully',
            routeStopId: (int) $routeStop->getId(),
        );
    }
}
