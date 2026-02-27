<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\DriverAlert;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * @return ChatMessage[]
     */
    public function findByAlert(DriverAlert $alert, int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('cm')
            ->andWhere('cm.alert = :alert')
            ->setParameter('alert', $alert)
            ->orderBy('cm.sentAt', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ChatMessage[]
     */
    public function findUnreadByUserAndAlert(User $user, DriverAlert $alert): array
    {
        // Messages where the user's ID is NOT in the readBy JSON array.
        // Uses LIKE as a cross-DB compatible approximation.
        $user->getId();

        $all = $this->findByAlert($alert);

        return array_filter($all, static fn (ChatMessage $m): bool => ! in_array($user->getId(), $m->getReadBy(), true));
    }
}
