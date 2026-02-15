<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $subscription, bool $flush = false): void
    {
        $this->getEntityManager()->persist($subscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Subscription[]
     */
    public function findDueForBilling(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.nextBillingDate <= :today')
            ->setParameter('status', SubscriptionStatus::ACTIVE)
            ->setParameter('today', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Subscription[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', SubscriptionStatus::ACTIVE)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Subscription[]
     */
    public function findFailedPaymentRetries(): array
    {
        $twentyFourHoursAgo = new \DateTimeImmutable('-24 hours');

        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.failedPaymentCount < :maxRetries')
            ->andWhere('s.lastPaymentAttemptAt < :threshold OR s.lastPaymentAttemptAt IS NULL')
            ->setParameter('status', SubscriptionStatus::PAYMENT_FAILED)
            ->setParameter('maxRetries', 3)
            ->setParameter('threshold', $twentyFourHoursAgo)
            ->getQuery()
            ->getResult();
    }
}
