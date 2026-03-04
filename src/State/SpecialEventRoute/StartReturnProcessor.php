<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRoute;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use App\Service\NotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/special-event-routes/{id}/start-return.
 *
 * Validates IN_PROGRESS + not ONE_WAY; records return departure time; notifies parents.
 *
 * @implements ProcessorInterface<null, SpecialEventRoute>
 */
final readonly class StartReturnProcessor implements ProcessorInterface
{
    public function __construct(
        private SpecialEventRouteRepository $repository,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SpecialEventRoute
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $route = $this->repository->find($id);

        if ($route === null) {
            throw new NotFoundHttpException('Special event route not found.');
        }

        if ($route->getRouteMode() === RouteMode::ONE_WAY) {
            throw new UnprocessableEntityHttpException('ONE_WAY routes do not have a return leg.');
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            throw new UnprocessableEntityHttpException('Route is not IN_PROGRESS.');
        }

        $route->setReturnDepartureTime(new DateTimeImmutable());

        if ($route->getRouteMode() === RouteMode::RETURN_TO_SCHOOL) {
            foreach ($route->getStudents() as $student) {
                foreach ($student->getParents() as $parent) {
                    $this->notificationService->notify(
                        $parent,
                        'Bus departing event',
                        sprintf('The bus for %s is departing the event and heading back.', $route->getName()),
                    );
                }
            }
        }

        $this->entityManager->flush();

        return $route;
    }
}
