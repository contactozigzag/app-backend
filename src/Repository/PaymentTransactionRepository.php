<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\PaymentTransaction;
use App\Enum\TransactionEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentTransaction>
 */
class PaymentTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentTransaction::class);
    }

    public function save(PaymentTransaction $transaction, bool $flush = false): void
    {
        $this->getEntityManager()->persist($transaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return PaymentTransaction[]
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.payment = :payment')
            ->setParameter('payment', $payment)
            ->orderBy('pt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLastTransactionByPayment(Payment $payment): ?PaymentTransaction
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.payment = :payment')
            ->setParameter('payment', $payment)
            ->orderBy('pt.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PaymentTransaction[]
     */
    public function findByEventType(TransactionEvent $eventType, int $limit = 100): array
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('pt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
