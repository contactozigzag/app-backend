<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Address;
use App\Entity\Driver;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[RouteAttribute(name: 'api_route_stops_')]
class RouteStopController extends AbstractController
{
    public function __construct(
        private readonly RouteStopRepository $routeStopRepository,
        private readonly RouteRepository $routeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly IriConverterInterface $iriConverter
    ) {
    }

    /**
     * Parents can create a route stop for their student
     */
    #[RouteAttribute('/api/route-stops', name: 'api_route_stops_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createRouteStop(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (! isset($data['route'], $data['student'], $data['address'])) {
            return $this->json([
                'error' => 'Missing required fields: route, student, address',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var Route $route */
        $route = $this->getEntityByIri($data['route']);
        if (! $route) {
            return $this->json([
                'error' => 'Route not found',
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var Student $student */
        $student = $this->getEntityByIri($data['student']);
        if (! $student) {
            return $this->json([
                'error' => 'Student not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify a student belongs to the parent
        if (! $user->getStudents()->contains($student)) {
            return $this->json([
                'error' => 'Student does not belong to you',
            ], Response::HTTP_FORBIDDEN);
        }

        // Verify a student belongs to the same school as the route
        if ($student->getSchool() !== $route->getSchool()) {
            return $this->json([
                'error' => "Student does not belong to the route's school",
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var Address $address */
        $address = $this->getEntityByIri($data['address']);
        if (! $address) {
            return $this->json([
                'error' => 'Address not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Create the route stop
        $routeStop = new RouteStop();
        $routeStop->setRoute($route);
        $routeStop->setStudent($student);
        $routeStop->setAddress($address);
        $routeStop->setStopOrder($data['stopOrder'] ?? 0);
        $routeStop->setGeofenceRadius($data['geofenceRadius'] ?? 50);
        $routeStop->setNotes($data['notes'] ?? null);
        $routeStop->setIsActive(true);
        $routeStop->setIsConfirmed(false); // Initially unconfirmed

        $this->entityManager->persist($routeStop);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'route_stop_id' => $routeStop->getId(),
            'message' => 'Route stop created successfully. Waiting for driver confirmation.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Drivers can list unconfirmed route stops for their routes
     */
    #[RouteAttribute('/api/route-stops/unconfirmed', name: 'api_route_stops_unconfirmed', methods: ['GET'])]
    #[IsGranted('ROLE_DRIVER')]
    public function listUnconfirmedRouteStops(): JsonResponse
    {
        $driver = $this->getDriver();

        if (! $driver instanceof Driver) {
            return $this->json([
                'error' => 'Driver profile not found',
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var Route[] $driverRoutes */
        $driverRoutes = $this->routeRepository->findBy([
            'driver' => $driver,
        ]);

        $unconfirmedStops = [];
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
                /** @var Student $student */
                $student = $stop->getStudent();
                /** @var Address $address */
                $address = $stop->getAddress();
                $unconfirmedStops[] = [
                    'id' => $stop->getId(),
                    'route_id' => $route->getId(),
                    'route_name' => $route->getName(),
                    'student_id' => $student->getId(),
                    'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'address' => [
                        'id' => $address->getId(),
                        'street' => $address->getStreetAddress(),
                        'latitude' => $address->getLatitude(),
                        'longitude' => $address->getLongitude(),
                    ],
                    'notes' => $stop->getNotes(),
                    'created_at' => $stop->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $this->json([
            'unconfirmed_stops' => $unconfirmedStops,
            'total' => count($unconfirmedStops),
        ]);
    }

    /**
     * Drivers can confirm a route stop
     */
    #[RouteAttribute('/api/route-stops/{id}/confirm', name: 'api_route_stops_confirm', methods: ['PATCH'])]
    #[IsGranted('ROLE_DRIVER')]
    public function confirmRouteStop(int $id): JsonResponse
    {
        return $this->updateConfirmationOnRouteStop(true, $id, 'Route stop confirmed successfully');
    }

    /**
     * Drivers can reject (deactivate) a route stop
     */
    #[RouteAttribute('/api/route-stops/{id}/reject', name: 'api_route_stops_reject', methods: ['PATCH'])]
    #[IsGranted('ROLE_DRIVER')]
    public function rejectRouteStop(int $id): JsonResponse
    {
        return $this->updateConfirmationOnRouteStop(false, $id, 'Route stop rejected successfully');
    }

    private function getEntityByIri(string $iri): object
    {
        try {
            return $this->iriConverter->getResourceFromIri($iri);
        } catch (RouteNotFoundException | MissingMandatoryParametersException) {
            return $this->json([
                'error' => 'Not found or invalid resource IRI provided: ' . $iri . '.',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    protected function getDriver(): ?Driver
    {
        /** @var User $user */
        $user = $this->getUser();

        if (! $user instanceof User) {
            return null;
        }

        return $user->getDriver();
    }

    protected function updateConfirmationOnRouteStop(bool $confirmed, int $routeStopId, string $message): JsonResponse
    {
        $driver = $this->getDriver();

        if (! $driver instanceof Driver) {
            return $this->json([
                'error' => 'Driver profile not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $routeStop = $this->routeStopRepository->find($routeStopId);

        if (! $routeStop instanceof RouteStop) {
            return $this->json([
                'error' => 'Route stop not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($routeStop->getRoute()->getDriver() !== $driver) {
            return $this->json([
                'error' => 'This route stop does not belong to your routes',
            ], Response::HTTP_FORBIDDEN);
        }

        $routeStop->setIsActive($confirmed);
        $routeStop->setIsConfirmed($confirmed);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $message,
            'route_stop_id' => $routeStop->getId(),
        ]);
    }
}
