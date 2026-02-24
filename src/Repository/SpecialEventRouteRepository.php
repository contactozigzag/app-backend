<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\School;
use App\Entity\SpecialEventRoute;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpecialEventRoute>
 */
class SpecialEventRouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpecialEventRoute::class);
    }

    /**
     * @return SpecialEventRoute[]
     */
    public function findByFilters(
        School $school,
        ?DateTimeImmutable $date = null,
        ?SpecialEventRouteStatus $status = null,
        ?EventType $eventType = null,
        ?RouteMode $routeMode = null,
    ): array {
        $qb = $this->createQueryBuilder('ser')
            ->andWhere('ser.school = :school')
            ->setParameter('school', $school)
            ->orderBy('ser.eventDate', 'ASC');

        if ($date instanceof DateTimeImmutable) {
            $qb->andWhere('ser.eventDate = :date')->setParameter('date', $date);
        }

        if ($status instanceof SpecialEventRouteStatus) {
            $qb->andWhere('ser.status = :status')->setParameter('status', $status);
        }

        if ($eventType instanceof EventType) {
            $qb->andWhere('ser.eventType = :eventType')->setParameter('eventType', $eventType);
        }

        if ($routeMode instanceof RouteMode) {
            $qb->andWhere('ser.routeMode = :routeMode')->setParameter('routeMode', $routeMode);
        }

        return $qb->getQuery()->getResult();
    }
}
