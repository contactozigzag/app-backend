<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SpecialEventRoute;
use App\Entity\SpecialEventRouteStop;
use App\Enum\DepartureMode;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Message\StudentReadyForPickupMessage;
use App\Repository\SchoolRepository;
use App\Repository\SpecialEventRouteRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(name: 'api_special_event_routes_')]
class SpecialEventRouteController extends AbstractController
{
    public function __construct(
        private readonly SpecialEventRouteRepository $repository,
        private readonly SchoolRepository $schoolRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Create a special event route.
     */
    #[Route('/api/special-event-routes', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $school = $this->schoolRepository->find($data['school_id'] ?? 0);
        if ($school === null) {
            return $this->json([
                'error' => 'School not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $routeMode = RouteMode::tryFrom($data['route_mode'] ?? '');
        if ($routeMode === null) {
            return $this->json([
                'error' => 'Invalid route_mode',
            ], Response::HTTP_BAD_REQUEST);
        }

        $departureMode = isset($data['departure_mode'])
            ? DepartureMode::tryFrom($data['departure_mode'])
            : null;

        if ($departureMode !== null && $routeMode === RouteMode::ONE_WAY) {
            return $this->json(
                [
                    'error' => 'departure_mode cannot be set when route_mode is ONE_WAY',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $eventType = EventType::tryFrom($data['event_type'] ?? '') ?? EventType::OTHER;

        $route = new SpecialEventRoute();
        $route->setSchool($school);
        $route->setName($data['name'] ?? 'Unnamed Event');
        $route->setEventType($eventType);
        $route->setRouteMode($routeMode);
        $route->setDepartureMode($departureMode);

        if (isset($data['event_date'])) {
            $route->setEventDate(new \DateTimeImmutable($data['event_date']));
        }

        if (isset($data['outbound_departure_time'])) {
            $route->setOutboundDepartureTime(new \DateTimeImmutable($data['outbound_departure_time']));
        }

        if (isset($data['return_departure_time'])) {
            $route->setReturnDepartureTime(new \DateTimeImmutable($data['return_departure_time']));
        }

        $this->entityManager->persist($route);
        $this->entityManager->flush();

        return $this->json([
            'id' => $route->getId(),
            'status' => $route->getStatus()->value,
        ], Response::HTTP_CREATED);
    }

    /**
     * List special event routes for a school.
     */
    #[Route('/api/special-event-routes', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function list(Request $request): JsonResponse
    {
        $schoolId = (int) $request->query->get('school_id', 0);
        $school = $this->schoolRepository->find($schoolId);

        if ($school === null) {
            return $this->json([
                'error' => 'school_id is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $date = $request->query->get('date')
            ? new \DateTimeImmutable((string) $request->query->get('date'))
            : null;

        $status = $request->query->get('status')
            ? SpecialEventRouteStatus::tryFrom((string) $request->query->get('status'))
            : null;

        $eventType = $request->query->get('event_type')
            ? EventType::tryFrom((string) $request->query->get('event_type'))
            : null;

        $routeMode = $request->query->get('route_mode')
            ? RouteMode::tryFrom((string) $request->query->get('route_mode'))
            : null;

        $routes = $this->repository->findByFilters($school, $date, $status, $eventType, $routeMode);

        return $this->json(array_map(fn (SpecialEventRoute $r): array => $this->serialize($r), $routes));
    }

    /**
     * Get a single special event route.
     */
    #[Route('/api/special-event-routes/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function get(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($route));
    }

    /**
     * Update a special event route (only if DRAFT).
     */
    #[Route('/api/special-event-routes/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::DRAFT) {
            return $this->json([
                'error' => 'Only DRAFT routes can be updated',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $route->setName($data['name']);
        }

        if (isset($data['event_date'])) {
            $route->setEventDate(new \DateTimeImmutable($data['event_date']));
        }

        if (isset($data['event_type'])) {
            $et = EventType::tryFrom($data['event_type']);
            if ($et !== null) {
                $route->setEventType($et);
            }
        }

        if (isset($data['route_mode'])) {
            $rm = RouteMode::tryFrom($data['route_mode']);
            if ($rm !== null) {
                $route->setRouteMode($rm);
            }
        }

        if (isset($data['departure_mode'])) {
            $dm = DepartureMode::tryFrom($data['departure_mode']);
            $route->setDepartureMode($dm);
        }

        if (isset($data['outbound_departure_time'])) {
            $route->setOutboundDepartureTime(new \DateTimeImmutable($data['outbound_departure_time']));
        }

        if (isset($data['return_departure_time'])) {
            $route->setReturnDepartureTime(new \DateTimeImmutable($data['return_departure_time']));
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($route));
    }

    /**
     * Delete a special event route (only DRAFT or CANCELLED).
     */
    #[Route('/api/special-event-routes/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! in_array($route->getStatus(), [SpecialEventRouteStatus::DRAFT, SpecialEventRouteStatus::CANCELLED], true)) {
            return $this->json(
                [
                    'error' => 'Only DRAFT or CANCELLED routes can be deleted',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->entityManager->remove($route);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }

    /**
     * Publish a special event route (validates constraints, generates Mode A stops).
     */
    #[Route('/api/special-event-routes/{id}/publish', name: 'publish', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function publish(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::DRAFT) {
            return $this->json([
                'error' => 'Only DRAFT routes can be published',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validate departureMode + routeMode consistency
        if ($route->getDepartureMode() !== null && $route->getRouteMode() === RouteMode::ONE_WAY) {
            return $this->json(
                [
                    'error' => 'departure_mode cannot be set when route_mode is ONE_WAY',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // Generate stops from enrolled students
        $this->generateStops($route);

        $route->setStatus(SpecialEventRouteStatus::PUBLISHED);
        $this->entityManager->flush();

        return $this->json([
            'id' => $route->getId(),
            'status' => $route->getStatus()->value,
        ]);
    }

    /**
     * Start the outbound leg.
     */
    #[Route('/api/special-event-routes/{id}/start-outbound', name: 'start_outbound', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function startOutbound(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::PUBLISHED) {
            return $this->json([
                'error' => 'Route must be PUBLISHED to start outbound',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $route->setStatus(SpecialEventRouteStatus::IN_PROGRESS);
        $this->entityManager->flush();

        return $this->json([
            'id' => $route->getId(),
            'status' => $route->getStatus()->value,
        ]);
    }

    /**
     * Mark arrival at event (outbound complete).
     */
    #[Route('/api/special-event-routes/{id}/arrive-at-event', name: 'arrive_at_event', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function arriveAtEvent(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            return $this->json([
                'error' => 'Route is not IN_PROGRESS',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ONE_WAY routes auto-complete on arrival
        if ($route->getRouteMode() === RouteMode::ONE_WAY) {
            $route->setStatus(SpecialEventRouteStatus::COMPLETED);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $route->getId(),
            'status' => $route->getStatus()->value,
        ]);
    }

    /**
     * Start the return leg (not valid for ONE_WAY).
     */
    #[Route('/api/special-event-routes/{id}/start-return', name: 'start_return', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function startReturn(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getRouteMode() === RouteMode::ONE_WAY) {
            return $this->json([
                'error' => 'ONE_WAY routes do not have a return leg',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            return $this->json([
                'error' => 'Route is not IN_PROGRESS',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Mode B: notify parents that bus is departing event
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

        return $this->json([
            'id' => $route->getId(),
            'status' => $route->getStatus()->value,
        ]);
    }

    /**
     * Complete the route.
     */
    #[Route('/api/special-event-routes/{id}/complete', name: 'complete', methods: ['POST'])]
    #[IsGranted('ROLE_SCHOOL_ADMIN')]
    public function complete(int $id): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            return $this->json([
                'error' => 'Route is not IN_PROGRESS',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $route->setStatus(SpecialEventRouteStatus::COMPLETED);
        $this->entityManager->flush();

        return $this->json([
            'id' => $route->getId(),
            'status' => $route->getStatus()->value,
        ]);
    }

    /**
     * Mark a student as ready for pickup (Individual departure mode).
     */
    #[Route('/api/special-event-routes/{id}/students/{studentId}/ready', name: 'student_ready', methods: ['POST'])]
    #[IsGranted('ROLE_DRIVER')]
    public function studentReady(int $id, int $studentId): JsonResponse
    {
        $route = $this->repository->find($id);
        if ($route === null) {
            return $this->json([
                'error' => 'Route not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($route->getRouteMode() !== RouteMode::FULL_DAY_TRIP) {
            return $this->json([
                'error' => 'Only FULL_DAY_TRIP routes support student ready marking',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($route->getDepartureMode() !== DepartureMode::INDIVIDUAL) {
            return $this->json([
                'error' => 'Only INDIVIDUAL departure mode supports student ready marking',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($route->getStatus() !== SpecialEventRouteStatus::IN_PROGRESS) {
            return $this->json([
                'error' => 'Route is not IN_PROGRESS',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find the stop for this student
        $stop = null;
        foreach ($route->getStops() as $s) {
            if ($s->getStudent()?->getId() === $studentId) {
                $stop = $s;
                break;
            }
        }

        if ($stop === null) {
            return $this->json([
                'error' => 'Student stop not found for this route',
            ], Response::HTTP_NOT_FOUND);
        }

        $stop->setIsStudentReady(true);
        $stop->setReadyAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Dispatch with 30-second delay for debounce batching
        $this->bus->dispatch(
            new StudentReadyForPickupMessage((int) $route->getId(), $studentId, sprintf('ser_recalc_%d', (int) $route->getId())),
            [new DelayStamp(30_000)],
        );

        return $this->json([
            'success' => true,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Generate SpecialEventRouteStop entities for enrolled students.
     * Uses parent addresses as pickup/dropoff points.
     */
    private function generateStops(SpecialEventRoute $route): void
    {
        $order = 1;

        foreach ($route->getStudents() as $student) {
            // Use the address of the first parent who has one
            $address = null;
            foreach ($student->getParents() as $parent) {
                if ($parent->getAddress() !== null) {
                    $address = $parent->getAddress();
                    break;
                }
            }

            if ($address === null) {
                continue;
            }

            $stop = new SpecialEventRouteStop();
            $stop->setStudent($student);
            $stop->setAddress($address);
            $stop->setStopOrder($order++);

            $route->addStop($stop);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SpecialEventRoute $route): array
    {
        return [
            'id' => $route->getId(),
            'name' => $route->getName(),
            'school_id' => $route->getSchool()?->getId(),
            'event_date' => $route->getEventDate()?->format('Y-m-d'),
            'event_type' => $route->getEventType()->value,
            'route_mode' => $route->getRouteMode()->value,
            'departure_mode' => $route->getDepartureMode()?->value,
            'status' => $route->getStatus()->value,
            'student_count' => $route->getStudents()->count(),
            'stop_count' => $route->getStops()->count(),
            'outbound_departure_time' => $route->getOutboundDepartureTime()?->format('c'),
            'return_departure_time' => $route->getReturnDepartureTime()?->format('c'),
            'created_at' => $route->getCreatedAt()->format('c'),
            'updated_at' => $route->getUpdatedAt()->format('c'),
        ];
    }
}
