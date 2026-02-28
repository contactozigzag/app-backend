<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Route\RouteOptimizeOutput;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles POST /api/routes/{id}/optimize.
 *
 * Optimizes the stop order and estimated arrival times in place and persists.
 *
 * @implements ProcessorInterface<mixed, RouteOptimizeOutput>
 */
final readonly class RouteOptimizeProcessor implements ProcessorInterface
{
    public function __construct(
        private RouteRepository $routeRepository,
        private RouteOptimizationService $optimizationService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RouteOptimizeOutput
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $route = $this->routeRepository->find($id);

        if ($route === null) {
            throw new NotFoundHttpException('Route not found.');
        }

        $startPoint = [
            'lat' => (float) $route->getStartLatitude(),
            'lng' => (float) $route->getStartLongitude(),
        ];

        $endPoint = [
            'lat' => (float) $route->getEndLatitude(),
            'lng' => (float) $route->getEndLongitude(),
        ];

        $stops = [];
        foreach ($route->getStops() as $stop) {
            $address = $stop->getAddress();
            $stopId = $stop->getId();
            if ($stop->getIsActive() && $stop->getIsConfirmed() && $address !== null && $stopId !== null) {
                $stops[] = [
                    'id' => $stopId,
                    'lat' => (float) $address->getLatitude(),
                    'lng' => (float) $address->getLongitude(),
                ];
            }
        }

        $result = $this->optimizationService->optimizeRoute($startPoint, $endPoint, $stops);

        if ($result === null) {
            throw new HttpException(500, 'Could not optimize route.');
        }

        $stopMap = [];
        foreach ($route->getStops()->toArray() as $stop) {
            $stopMap[$stop->getId()] = $stop;
        }

        $newOrder = 0;
        $currentTime = 0;
        foreach ($result['optimized_order'] as $stopId) {
            if (isset($stopMap[$stopId])) {
                $stopMap[$stopId]->setStopOrder($newOrder++);

                foreach ($result['segments'] as $segment) {
                    if ($segment['to'] === $stopId) {
                        $currentTime += $segment['duration'];
                        $stopMap[$stopId]->setEstimatedArrivalTime($currentTime);
                        break;
                    }
                }
            }
        }

        $route->setEstimatedDistance($result['total_distance']);
        $route->setEstimatedDuration($result['total_duration']);

        if (isset($result['polyline'])) {
            $route->setPolyline($result['polyline']);
        }

        $this->entityManager->flush();

        return new RouteOptimizeOutput(
            success: true,
            optimizedOrder: $result['optimized_order'],
            totalDistance: $result['total_distance'],
            totalDuration: $result['total_duration'],
            distanceKm: round($result['total_distance'] / 1000, 2),
            durationMinutes: round($result['total_duration'] / 60, 2),
        );
    }
}
