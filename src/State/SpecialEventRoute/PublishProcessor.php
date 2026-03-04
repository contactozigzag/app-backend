<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRoute;
use App\Entity\SpecialEventRouteStop;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/special-event-routes/{id}/publish.
 *
 * Validates DRAFT status and field constraints, generates stops from enrolled students,
 * then transitions the route to PUBLISHED.
 *
 * @implements ProcessorInterface<null, SpecialEventRoute>
 */
final readonly class PublishProcessor implements ProcessorInterface
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

        if ($route->getStatus() !== SpecialEventRouteStatus::DRAFT) {
            throw new UnprocessableEntityHttpException('Only DRAFT routes can be published.');
        }

        if ($route->getDepartureMode() !== null && $route->getRouteMode() === RouteMode::ONE_WAY) {
            throw new UnprocessableEntityHttpException('departureMode cannot be set when routeMode is ONE_WAY.');
        }

        $this->generateStops($route);

        $route->setStatus(SpecialEventRouteStatus::PUBLISHED);
        $this->entityManager->flush();

        return $route;
    }

    private function generateStops(SpecialEventRoute $route): void
    {
        $order = 1;

        foreach ($route->getStudents() as $student) {
            $address = null;
            foreach ($student->getParents() as $parent) {
                if ($parent->getAddress() !== null) {
                    $address = $parent->getAddress();
                    break;
                }
            }

            if ($address === null) {
                continue;
            }

            $stop = new SpecialEventRouteStop();
            $stop->setStudent($student);
            $stop->setAddress($address);
            $stop->setStopOrder($order++);

            $route->addStop($stop);
        }
    }
}
