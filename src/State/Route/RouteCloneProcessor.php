<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Route\RouteCloneInput;
use App\Dto\Route\RouteCloneOutput;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles POST /api/routes/{id}/clone.
 *
 * Creates a copy of the route and all its stops.
 *
 * @implements ProcessorInterface<RouteCloneInput, RouteCloneOutput>
 */
final readonly class RouteCloneProcessor implements ProcessorInterface
{
    public function __construct(
        private RouteRepository $routeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RouteCloneOutput
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $route = $this->routeRepository->find($id);

        if ($route === null) {
            throw new NotFoundHttpException('Route not found.');
        }

        /** @var RouteCloneInput $input */
        $input = $data;

        $newRoute = new Route();
        $newRoute->setName($input->name ?? $route->getName() . ' (Copy)');
        $newRoute->setSchool($route->getSchool());
        $newRoute->setType($route->getType() ?? 'morning');
        $newRoute->setDriver($route->getDriver());
        $newRoute->setStartLatitude($route->getStartLatitude() ?? '0');
        $newRoute->setStartLongitude($route->getStartLongitude() ?? '0');
        $newRoute->setEndLatitude($route->getEndLatitude() ?? '0');
        $newRoute->setEndLongitude($route->getEndLongitude() ?? '0');
        $newRoute->setEstimatedDuration($route->getEstimatedDuration());
        $newRoute->setEstimatedDistance($route->getEstimatedDistance());
        $newRoute->setPolyline($route->getPolyline());
        $newRoute->setIsActive($input->isActive);
        $newRoute->setIsTemplate($input->isTemplate);

        foreach ($route->getStops() as $stop) {
            $newStop = new RouteStop();
            $newStop->setStudent($stop->getStudent());
            $newStop->setAddress($stop->getAddress());
            $newStop->setStopOrder($stop->getStopOrder() ?? 0);
            $newStop->setEstimatedArrivalTime($stop->getEstimatedArrivalTime());
            $newStop->setGeofenceRadius($stop->getGeofenceRadius());
            $newStop->setNotes($stop->getNotes());
            $newStop->setIsActive($stop->getIsActive());
            $newStop->setIsConfirmed($stop->getIsConfirmed());

            $newRoute->addStop($newStop);
        }

        $this->entityManager->persist($newRoute);
        $this->entityManager->flush();

        return new RouteCloneOutput(
            success: true,
            routeId: (int) $newRoute->getId(),
        );
    }
}
