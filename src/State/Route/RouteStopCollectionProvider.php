<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\RouteStop;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Scopes GET /api/route-stops to the authenticated user's role.
 *
 * - ROLE_SCHOOL_ADMIN / ROLE_SUPER_ADMIN: all route stops
 * - ROLE_DRIVER: only stops on routes assigned to the authenticated driver
 * - ROLE_PARENT: stops for their own students
 * - Others: empty collection
 *
 * @implements ProviderInterface<RouteStop>
 */
final readonly class RouteStopCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return RouteStop[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if ($this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            return $this->entityManager->getRepository(RouteStop::class)->findAll();
        }

        /** @var User $user */
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_DRIVER')) {
            $driver = $user->getDriver();

            if ($driver === null) {
                return [];
            }

            return $this->entityManager->createQuery(
                'SELECT rs FROM App\Entity\RouteStop rs
                 JOIN rs.route r
                 WHERE r.driver = :driver'
            )
                ->setParameter('driver', $driver)
                ->getResult();
        }

        if ($this->security->isGranted('ROLE_PARENT')) {
            return $this->entityManager->createQuery(
                'SELECT rs FROM App\Entity\RouteStop rs
                 JOIN rs.student s
                 JOIN s.parents p
                 WHERE p = :user'
            )
                ->setParameter('user', $user)
                ->getResult();
        }

        return [];
    }
}
