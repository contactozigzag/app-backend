<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\School;
use App\Entity\SpecialEventRoute;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles GET /api/special-event-routes.
 *
 * Reads optional 'date', 'status', 'eventType', 'routeMode', 'school' filters
 * from $context['filters'] and delegates to SpecialEventRouteRepository::findByFilters().
 *
 * @implements ProviderInterface<SpecialEventRoute>
 */
final readonly class SpecialEventRouteCollectionProvider implements ProviderInterface
{
    public function __construct(
        private SpecialEventRouteRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return SpecialEventRoute[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        /** @var array<string, mixed> $search */
        $search = is_array($filters['search'] ?? null) ? $filters['search'] : [];

        $schoolIdRaw = $search['school'] ?? null;
        $schoolId = is_numeric($schoolIdRaw) ? (int) $schoolIdRaw : null;

        $school = $schoolId !== null ? $this->entityManager->find(School::class, $schoolId) : null;

        if (! $school instanceof School) {
            return [];
        }

        $dateRaw = $filters['date'] ?? null;
        $date = is_string($dateRaw) ? new DateTimeImmutable($dateRaw) : null;

        $statusRaw = $search['status'] ?? null;
        $status = is_string($statusRaw) ? SpecialEventRouteStatus::tryFrom($statusRaw) : null;

        $eventTypeRaw = $search['eventType'] ?? null;
        $eventType = is_string($eventTypeRaw) ? EventType::tryFrom($eventTypeRaw) : null;

        $routeModeRaw = $search['routeMode'] ?? null;
        $routeMode = is_string($routeModeRaw) ? RouteMode::tryFrom($routeModeRaw) : null;

        return $this->repository->findByFilters($school, $date, $status, $eventType, $routeMode);
    }
}
