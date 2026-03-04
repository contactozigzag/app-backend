<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRoute;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/special-event-routes/{id}/start-outbound.
 *
 * Validates PUBLISHED status; sets IN_PROGRESS and records outbound departure time.
 *
 * @implements ProcessorInterface<null, SpecialEventRoute>
 */
final readonly class StartOutboundProcessor implements ProcessorInterface
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

        if ($route->getStatus() !== SpecialEventRouteStatus::PUBLISHED) {
            throw new UnprocessableEntityHttpException('Route must be PUBLISHED to start outbound.');
        }

        $route->setStatus(SpecialEventRouteStatus::IN_PROGRESS);
        $route->setOutboundDepartureTime(new DateTimeImmutable());

        $this->entityManager->flush();

        return $route;
    }
}
