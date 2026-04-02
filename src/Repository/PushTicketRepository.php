<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushTicket;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushTicket>
 */
class PushTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushTicket::class);
    }

    public function save(PushTicket $ticket, bool $flush = true): void
    {
        $this->getEntityManager()->persist($ticket);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTicketId(string $ticketId): ?PushTicket
    {
        return $this->findOneBy([
            'ticketId' => $ticketId,
        ]);
    }

    /**
     * @return PushTicket[]
     */
    public function findPendingOlderThan(DateTimeImmutable $olderThan, int $limit = 1000): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.createdAt < :olderThan')
            ->setParameter('status', 'pending')
            ->setParameter('olderThan', $olderThan)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function deleteCheckedOlderThan(DateTimeImmutable $olderThan): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.status != :pending')
            ->andWhere('t.createdAt < :olderThan')
            ->setParameter('pending', 'pending')
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->execute();
    }
}
