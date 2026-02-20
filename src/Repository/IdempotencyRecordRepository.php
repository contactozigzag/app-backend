<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IdempotencyRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdempotencyRecord>
 */
class IdempotencyRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdempotencyRecord::class);
    }

    public function findActiveByKey(string $idempotencyKey): ?IdempotencyRecord
    {
        return $this->createQueryBuilder('r')
            ->where('r.idempotencyKey = :key')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('key', $idempotencyKey)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByKey(string $idempotencyKey): ?IdempotencyRecord
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }
}
