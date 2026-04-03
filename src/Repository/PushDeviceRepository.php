<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushDevice;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushDevice>
 */
class PushDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushDevice::class);
    }

    public function save(PushDevice $device, bool $flush = true): void
    {
        $this->getEntityManager()->persist($device);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByToken(string $expoPushToken): ?PushDevice
    {
        return $this->findOneBy([
            'expoPushToken' => $expoPushToken,
        ]);
    }

    /**
     * @param list<int> $userIds
     * @return PushDevice[]
     */
    public function findActiveByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->where('IDENTITY(d.user) IN (:userIds)')
            ->andWhere('d.isActive = true')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deactivateInactiveSince(DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('d')
            ->update()
            ->set('d.isActive', 'false')
            ->where('d.isActive = true')
            ->andWhere('d.lastSeenAt < :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->execute();
    }
}
