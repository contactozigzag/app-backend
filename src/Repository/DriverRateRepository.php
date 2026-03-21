<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Driver;
use App\Entity\DriverRate;
use App\Entity\Route;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DriverRate>
 */
class DriverRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DriverRate::class);
    }

    /**
     * @return DriverRate[]
     */
    public function findByDriver(Driver $driver): array
    {
        return $this->findBy([
            'driver' => $driver,
        ]);
    }

    public function findByDriverAndRoute(Driver $driver, ?Route $route): ?DriverRate
    {
        return $this->findOneBy([
            'driver' => $driver,
            'route' => $route,
        ]);
    }
}
