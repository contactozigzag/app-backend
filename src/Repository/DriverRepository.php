<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\School;
use App\Entity\Driver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Driver>
 */
class DriverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Driver::class);
    }

    /**
     * Find all drivers for a school
     *
     * @return Driver[]
     */
    public function findBySchool(School $school): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.user', 'u')
            ->andWhere('u.school = :school')
            ->setParameter('school', $school)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count drivers for a school
     */
    public function countBySchool(School $school): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.user', 'u')
            ->andWhere('u.school = :school')
            ->setParameter('school', $school)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
