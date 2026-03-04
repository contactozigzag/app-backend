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
 * Handles DELETE /api/special-event-routes/{id}.
 *
 * Only DRAFT or CANCELLED routes can be deleted.
 *
 * @implements ProcessorInterface<SpecialEventRoute, void>
 */
final readonly class SpecialEventRouteDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private SpecialEventRouteRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $route = $this->repository->find($id);

        if ($route === null) {
            throw new NotFoundHttpException('Special event route not found.');
        }

        if (! in_array($route->getStatus(), [SpecialEventRouteStatus::DRAFT, SpecialEventRouteStatus::CANCELLED], true)) {
            throw new UnprocessableEntityHttpException('Only DRAFT or CANCELLED routes can be deleted.');
        }

        $this->entityManager->remove($route);
        $this->entityManager->flush();
    }
}
