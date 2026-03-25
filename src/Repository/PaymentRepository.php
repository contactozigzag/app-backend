<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Driver;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->persist($payment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->remove($payment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Payment[]
     */
    public function findByUser(User $user, ?PaymentStatus $status = null, int $limit = 30, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($status instanceof PaymentStatus) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Payment[]
     */
    public function findByDriver(Driver $driver, ?PaymentStatus $status = null, int $limit = 30, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($status instanceof PaymentStatus) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Payment
    {
        return $this->findOneBy([
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function findByPaymentProviderId(string $paymentProviderId): ?Payment
    {
        return $this->findOneBy([
            'paymentProviderId' => $paymentProviderId,
        ]);
    }

    /**
     * @return Payment[]
     */
    public function findPendingPayments(DateTimeInterface $olderThan): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt < :olderThan')
            ->setParameter('status', PaymentStatus::PENDING)
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Payment[]
     */
    public function findPaymentsByDateRange(
        User $user,
        DateTimeInterface $from,
        DateTimeInterface $to
    ): array {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalAmountByUserAndStatus(User $user, PaymentStatus $status): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->where('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Find an active (unexpired) pending payment for a given user+driver pair.
     */
    public function findActivePendingPayment(User $user, Driver $driver): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.driver = :driver')
            ->andWhere('p.status = :status')
            ->andWhere('p.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('driver', $driver)
            ->setParameter('status', PaymentStatus::PENDING)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all pending payments that have passed their expiration date.
     *
     * @return Payment[]
     */
    public function findExpiredPendingPayments(int $limit = 500): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.expiresAt <= :now')
            ->setParameter('status', PaymentStatus::PENDING)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('p.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count payments by status for a date range
     */
    public function countByStatusAndDateRange(
        PaymentStatus $status,
        DateTimeInterface $from,
        DateTimeInterface $to
    ): int {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.createdAt BETWEEN :from AND :to')
            ->setParameter('status', $status)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
