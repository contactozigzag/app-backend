<?php

declare(strict_types=1);

namespace App\State\RouteStop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\RouteStop\UnconfirmedStopsOutput;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles GET /api/route-stops/unconfirmed.
 *
 * Returns all unconfirmed route stops across the authenticated driver's routes.
 *
 * @implements ProviderInterface<UnconfirmedStopsOutput>
 */
final readonly class UnconfirmedRouteStopsProvider implements ProviderInterface
{
    public function __construct(
        private RouteRepository $routeRepository,
        private RouteStopRepository $routeStopRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UnconfirmedStopsOutput
    {
        // Role check inside provider: security: is checked AFTER the provider in AP4's chain for GET ops
        if (! $this->security->isGranted('ROLE_DRIVER')) {
            throw new AccessDeniedHttpException('ROLE_DRIVER required.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $user->getDriver();

        $unconfirmedStops = [];

        if ($driver !== null) {
            $driverRoutes = $this->routeRepository->findBy([
                'driver' => $driver,
            ]);

            foreach ($driverRoutes as $route) {
                $stops = $this->routeStopRepository->createQueryBuilder('rs')
                    ->andWhere('rs.route = :route')
                    ->andWhere('rs.isConfirmed = :confirmed')
                    ->andWhere('rs.isActive = :active')
                    ->setParameter('route', $route)
                    ->setParameter('confirmed', false)
                    ->setParameter('active', true)
                    ->getQuery()
                    ->getResult();

                foreach ($stops as $stop) {
                    /** @var RouteStop $stop */
                    $student = $stop->getStudent();
                    $address = $stop->getAddress();

                    $unconfirmedStops[] = [
                        'id' => $stop->getId(),
                        'routeId' => $route->getId(),
                        'routeName' => $route->getName(),
                        'studentId' => $student?->getId(),
                        'studentName' => ($student?->getFirstName() ?? '') . ' ' . ($student?->getLastName() ?? ''),
                        'address' => $address !== null ? [
                            'id' => $address->getId(),
                            'street' => $address->getStreetAddress(),
                            'latitude' => $address->getLatitude(),
                            'longitude' => $address->getLongitude(),
                        ] : null,
                        'notes' => $stop->getNotes(),
                        'createdAt' => $stop->getCreatedAt()->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        return new UnconfirmedStopsOutput(
            unconfirmedStops: $unconfirmedStops,
            total: count($unconfirmedStops),
        );
    }
}
