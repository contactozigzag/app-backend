<?php

declare(strict_types=1);

namespace App\State\SpecialEventRoute;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SpecialEventRouteStop;
use App\Enum\DepartureMode;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Message\StudentReadyForPickupMessage;
use App\Repository\SpecialEventRouteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Handles POST /api/special-event-routes/{id}/students/{studentId}/ready.
 *
 * Marks a student as ready for pickup and dispatches a delayed message for batched recalculation.
 *
 * @implements ProcessorInterface<null, SpecialEventRouteStop>
 */
final readonly class StudentReadyProcessor implements ProcessorInterface
{
    public function __construct(
        private SpecialEventRouteRepository $repository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SpecialEventRouteStop
    {
        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $rawStudentId = $uriVariables['studentId'] ?? null;
        $studentId = is_numeric($rawStudentId) ? (int) $rawStudentId : 0;

        $route = $this->repository->find($id);

        if ($route === null) {
            throw new NotFoundHttpException('Special event route not found.');
        }

        if ($route->getRouteMode() !== RouteMode::FULL_DAY_TRIP) {
            throw new UnprocessableEntityHttpException('Only FULL_DAY_TRIP routes support student ready marking.');
        }

        if ($route->getDepartureMode() !== DepartureMode::INDIVIDUAL) {
            throw new UnprocessableEntityHttpException('Only INDIVIDUAL departure mode supports student ready marking.');
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            throw new UnprocessableEntityHttpException('Route is not IN_PROGRESS.');
        }

        $stop = null;
        foreach ($route->getStops() as $s) {
            if ($s->getStudent()?->getId() === $studentId) {
                $stop = $s;
                break;
            }
        }

        if ($stop === null) {
            throw new NotFoundHttpException('Student stop not found for this route.');
        }

        $stop->setIsStudentReady(true);
        $stop->setReadyAt(new DateTimeImmutable());

        $this->entityManager->flush();

        $this->bus->dispatch(
            new StudentReadyForPickupMessage($id, $studentId, sprintf('ser_recalc_%d', $id)),
            [new DelayStamp(30_000)],
        );

        return $stop;
    }
}
