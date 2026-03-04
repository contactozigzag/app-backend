<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRoute;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/special-event-routes/{id}/complete.
 *
 * Validates IN_PROGRESS status; sets COMPLETED.
 *
 * @implements ProcessorInterface<null, SpecialEventRoute>
 */
final readonly class CompleteProcessor implements ProcessorInterface
{
    public function __construct(
        private SpecialEventRouteRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SpecialEventRoute
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $route = $this->repository->find($id);

        if ($route === null) {
            throw new NotFoundHttpException('Special event route not found.');
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            throw new UnprocessableEntityHttpException('Route is not IN_PROGRESS.');
        }

        $route->setStatus(SpecialEventRouteStatus::COMPLETED);
        $this->entityManager->flush();

        return $route;
    }
}
