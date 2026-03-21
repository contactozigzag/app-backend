<?php

declare(strict_types=1);

namespace App\State\Route;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Scopes GET /api/routes to the authenticated user's role.
 *
 * - ROLE_SCHOOL_ADMIN / ROLE_SUPER_ADMIN: all routes (optionally filtered by ?driver=)
 * - ROLE_DRIVER: only routes assigned to the authenticated driver
 * - ROLE_PARENT: routes for a specific driver (?driver=) or routes with stops for their students
 * - Others: empty collection
 *
 * @implements ProviderInterface<Route>
 */
final readonly class RouteCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return Route[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $driverFilter = $this->resolveDriverFilter();

        if ($this->security->isGranted('ROLE_SCHOOL_ADMIN')) {
            if ($driverFilter instanceof Driver) {
                return $this->entityManager->getRepository(Route::class)->findBy([
                    'driver' => $driverFilter,
                ]);
            }

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
            if ($driverFilter instanceof Driver) {
                return $this->entityManager->getRepository(Route::class)->findBy([
                    'driver' => $driverFilter,
                ]);
            }

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

    private function resolveDriverFilter(): ?Driver
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        $driverId = $request->query->get('driver');

        if ($driverId === null || !is_numeric($driverId)) {
            return null;
        }

        return $this->entityManager->getRepository(Driver::class)->find((int) $driverId);
    }
}
