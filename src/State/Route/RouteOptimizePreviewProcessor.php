<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Route\RouteOptimizePreviewOutput;
use App\Repository\RouteRepository;
use App\Service\RouteOptimizationService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles POST /api/routes/{id}/optimize-preview.
 *
 * Returns the optimized stop order and timing data without persisting changes.
 *
 * @implements ProcessorInterface<mixed, RouteOptimizePreviewOutput>
 */
final readonly class RouteOptimizePreviewProcessor implements ProcessorInterface
{
    public function __construct(
        private RouteRepository $routeRepository,
        private RouteOptimizationService $optimizationService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RouteOptimizePreviewOutput
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
        $stopDetails = [];
        foreach ($route->getStops() as $stop) {
            $address = $stop->getAddress();
            $stopId = $stop->getId();
            if ($stop->getIsActive() && $stop->getIsConfirmed() && $address !== null && $stopId !== null) {
                $student = $stop->getStudent();
                $stops[] = [
                    'id' => $stopId,
                    'lat' => (float) $address->getLatitude(),
                    'lng' => (float) $address->getLongitude(),
                ];
                $stopDetails[$stopId] = [
                    'studentName' => ($student?->getFirstName() ?? '') . ' ' . ($student?->getLastName() ?? ''),
                    'address' => $address->getStreetAddress() ?? '',
                ];
            }
        }

        $result = $this->optimizationService->optimizeRoute($startPoint, $endPoint, $stops);

        if ($result === null) {
            throw new HttpException(500, 'Could not optimize route.');
        }

        $optimizedStops = [];
        foreach ($result['optimized_order'] as $order => $stopId) {
            $optimizedStops[] = [
                'order' => $order,
                'stopId' => $stopId,
                'studentName' => $stopDetails[$stopId]['studentName'] ?? '',
                'address' => $stopDetails[$stopId]['address'] ?? '',
            ];
        }

        return new RouteOptimizePreviewOutput(
            optimizedStops: $optimizedStops,
            totalDistance: $result['total_distance'],
            totalDuration: $result['total_duration'],
            distanceKm: round($result['total_distance'] / 1000, 2),
            durationMinutes: round($result['total_duration'] / 60, 2),
            segments: $result['segments'],
        );
    }
}
