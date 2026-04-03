<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Route>
 */
class RouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Route::class);
    }

    /**
     * Find all active routes for a school
     *
     * @return Route[]
     */
    public function findActiveBySchool(School $school): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.school = :school')
            ->andWhere('r.isActive = :active')
            ->setParameter('school', $school)
            ->setParameter('active', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all route templates for a school
     *
     * @return Route[]
     */
    public function findTemplatesBySchool(School $school): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.school = :school')
            ->andWhere('r.isTemplate = :template')
            ->setParameter('school', $school)
            ->setParameter('template', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Route[]
     */
    public function findByDriver(Driver $driver, int $limit = 15, int $offset = 0): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('r.name', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByDriver(Driver $driver): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.driver = :driver')
            ->setParameter('driver', $driver)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find routes by type (morning/afternoon) for a school
     *
     * @return Route[]
     */
    public function findBySchoolAndType(School $school, string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.school = :school')
            ->andWhere('r.type = :type')
            ->andWhere('r.isActive = :active')
            ->setParameter('school', $school)
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
