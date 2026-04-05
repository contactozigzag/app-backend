<?php

declare(strict_types=1);

namespace App\State\RouteStop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RouteStop;
use App\Repository\RouteStopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Handles POST /api/route-stops.
 *
 * Prevents duplicate active route stops for the same route + student pair.
 *
 * @implements ProcessorInterface<RouteStop, RouteStop>
 */
final readonly class RouteStopCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private RouteStopRepository $routeStopRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RouteStop
    {
        /** @var RouteStop $data */
        $route = $data->getRoute();
        $student = $data->getStudent();

        if ($route !== null && $student !== null) {
            $existing = $this->routeStopRepository->findActiveForRouteAndStudent($route, $student);

            if ($existing instanceof RouteStop) {
                throw new ConflictHttpException(
                    sprintf('An active route stop already exists for this student on route "%s".', $route),
                );
            }
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
