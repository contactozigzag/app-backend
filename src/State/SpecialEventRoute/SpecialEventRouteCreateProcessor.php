<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRoute;
use App\Entity\User;
use App\Enum\RouteMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/special-event-routes.
 *
 * Validates departureMode incompatible with ONE_WAY; sets school from user's school; persists.
 *
 * @implements ProcessorInterface<SpecialEventRoute, SpecialEventRoute>
 */
final readonly class SpecialEventRouteCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SpecialEventRoute
    {
        /** @var SpecialEventRoute $route */
        $route = $data;

        if ($route->getDepartureMode() !== null && $route->getRouteMode() === RouteMode::ONE_WAY) {
            throw new UnprocessableEntityHttpException('departureMode cannot be set when routeMode is ONE_WAY.');
        }

        if ($route->getSchool() === null) {
            /** @var User $user */
            $user = $this->security->getUser();

            if ($user->getSchool() !== null) {
                $route->setSchool($user->getSchool());
            }
        }

        $this->entityManager->persist($route);
        $this->entityManager->flush();

        return $route;
    }
}
