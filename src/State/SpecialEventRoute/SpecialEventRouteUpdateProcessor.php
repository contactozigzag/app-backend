<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRoute;
use App\Enum\SpecialEventRouteStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles PATCH /api/special-event-routes/{id}.
 *
 * Only DRAFT routes can be updated.
 *
 * @implements ProcessorInterface<SpecialEventRoute, SpecialEventRoute>
 */
final readonly class SpecialEventRouteUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SpecialEventRoute
    {
        /** @var SpecialEventRoute $previousData */
        $previousData = $context['previous_data'] ?? $data;

        if ($previousData->getStatus() !== SpecialEventRouteStatus::DRAFT) {
            throw new UnprocessableEntityHttpException('Only DRAFT routes can be updated.');
        }

        /** @var SpecialEventRoute $route */
        $route = $data;

        $this->entityManager->flush();

        return $route;
    }
}
