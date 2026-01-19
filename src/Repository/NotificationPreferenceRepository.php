<?php

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function findByUser(User $user): ?NotificationPreference
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findOrCreateForUser(User $user): NotificationPreference
    {
        $preference = $this->findByUser($user);

        if (!$preference) {
            $preference = new NotificationPreference();
            $preference->setUser($user);
            $this->getEntityManager()->persist($preference);
            $this->getEntityManager()->flush();
        }

        return $preference;
    }
}
