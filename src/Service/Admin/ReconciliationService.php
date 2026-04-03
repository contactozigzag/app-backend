<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ReconciliationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *   totalCollected: string,
     *   totalRefunded: string,
     *   totalPending: string,
     *   approvedCount: int,
     *   refundedCount: int,
     *   pendingCount: int,
     *   cancelledCount: int
     * }
     */
    public function getSummary(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $rows = $qb
            ->select('p.status, COUNT(p.id) AS cnt, SUM(p.amount) AS total')
            ->from(Payment::class, 'p')
            ->where('p.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[$row['status']->value] = [
                'count' => (int) $row['cnt'],
                'total' => (string) $row['total'],
            ];
        }

        return [
            'totalCollected' => $byStatus[PaymentStatus::APPROVED->value]['total'] ?? '0.00',
            'totalRefunded' => $byStatus[PaymentStatus::REFUNDED->value]['total'] ?? '0.00',
            'totalPending' => $byStatus[PaymentStatus::PENDING->value]['total'] ?? '0.00',
            'approvedCount' => $byStatus[PaymentStatus::APPROVED->value]['count'] ?? 0,
            'refundedCount' => $byStatus[PaymentStatus::REFUNDED->value]['count'] ?? 0,
            'pendingCount' => $byStatus[PaymentStatus::PENDING->value]['count'] ?? 0,
            'cancelledCount' => $byStatus[PaymentStatus::CANCELLED->value]['count'] ?? 0,
        ];
    }

    /**
     * @return array<int, array{driverId: int, nickname: string, paymentCount: int, totalAmount: string}>
     */
    public function getDriverBreakdown(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('d.id AS driverId, d.nickname, COUNT(p.id) AS paymentCount, SUM(p.amount) AS totalAmount')
            ->from(Payment::class, 'p')
            ->join('p.driver', 'd')
            ->where('p.createdAt BETWEEN :from AND :to')
            ->andWhere('p.status = :status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', PaymentStatus::APPROVED)
            ->groupBy('d.id, d.nickname')
            ->orderBy('totalAmount', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'driverId' => (int) $row['driverId'],
                'nickname' => (string) $row['nickname'],
                'paymentCount' => (int) $row['paymentCount'],
                'totalAmount' => (string) $row['totalAmount'],
            ];
        }

        return $result;
    }
}
