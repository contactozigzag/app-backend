<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpecialEventRoute;
use App\Entity\SpecialEventRouteStop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpecialEventRouteStop>
 */
class SpecialEventRouteStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpecialEventRouteStop::class);
    }

    /**
     * @return SpecialEventRouteStop[]
     */
    public function findByRouteOrdered(SpecialEventRoute $route): array
    {
        return $this->createQueryBuilder('sers')
            ->andWhere('sers.specialEventRoute = :route')
            ->setParameter('route', $route)
            ->orderBy('sers.stopOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SpecialEventRouteStop[]
     */
    public function findReadyPendingByRoute(SpecialEventRoute $route): array
    {
        return $this->createQueryBuilder('sers')
            ->andWhere('sers.specialEventRoute = :route')
            ->andWhere('sers.isStudentReady = true')
            ->andWhere('sers.status = :status')
            ->setParameter('route', $route)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();
    }
}
