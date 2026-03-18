<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Scopes GET /api/routes to the authenticated user's role.
 *
 * - ROLE_SCHOOL_ADMIN / ROLE_SUPER_ADMIN: all routes
 * - ROLE_DRIVER: only routes assigned to the authenticated driver
 * - ROLE_PARENT: routes that have a stop for one of their students
 * - Others: empty collection
 *
 * @implements ProviderInterface<Route>
 */
final readonly class RouteCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return Route[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if ($this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            return $this->entityManager->getRepository(Route::class)->findAll();
        }

        /** @var User $user */
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_DRIVER')) {
            $driver = $user->getDriver();

            if ($driver === null) {
                return [];
            }

            return $this->entityManager->getRepository(Route::class)->findBy([
                'driver' => $driver,
            ]);
        }

        if ($this->security->isGranted('ROLE_PARENT')) {
            return $this->entityManager->createQuery(
                'SELECT DISTINCT r FROM App\Entity\Route r
                 JOIN App\Entity\RouteStop rs WITH rs.route = r
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
