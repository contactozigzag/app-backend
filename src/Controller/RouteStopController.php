<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Student;
use App\Repository\AddressRepository;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[RouteAttribute('/api/route-stops', name: 'api_route_stops_')]
class RouteStopController extends AbstractController
{
    public function __construct(
        private readonly RouteStopRepository $routeStopRepository,
        private readonly RouteRepository $routeRepository,
        private readonly StudentRepository $studentRepository,
        private readonly AddressRepository $addressRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Parents can create a route stop for their student
     */
    #[RouteAttribute('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createRouteStop(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['route_id'], $data['student_id'], $data['address_id'])) {
            return $this->json([
                'error' => 'Missing required fields: route_id, student_id, address_id'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get the route
        $route = $this->routeRepository->find($data['route_id']);
        if (!$route) {
            return $this->json([
                'error' => 'Route not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify route belongs to parent's school
        if ($user->getSchool() !== $route->getSchool()) {
            return $this->json([
                'error' => 'Route does not belong to your school'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get the student
        $student = $this->studentRepository->find($data['student_id']);
        if (!$student) {
            return $this->json([
                'error' => 'Student not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify student belongs to the parent
        if (!$user->getStudents()->contains($student)) {
            return $this->json([
                'error' => 'Student does not belong to you'
            ], Response::HTTP_FORBIDDEN);
        }

        // Verify student belongs to the same school as the route
        if ($student->getSchool() !== $route->getSchool()) {
            return $this->json([
                'error' => 'Student does not belong to the route\'s school'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get the address
        $address = $this->addressRepository->find($data['address_id']);
        if (!$address) {
            return $this->json([
                'error' => 'Address not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Create the route stop
        $routeStop = new RouteStop();
        $routeStop->setRoute($route);
        $routeStop->setStudent($student);
        $routeStop->setAddress($address);
        $routeStop->setStopOrder($data['stop_order'] ?? 0);
        $routeStop->setGeofenceRadius($data['geofence_radius'] ?? 50);
        $routeStop->setNotes($data['notes'] ?? null);
        $routeStop->setIsActive(true);
        $routeStop->setIsConfirmed(false); // Initially unconfirmed

        $this->entityManager->persist($routeStop);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'route_stop_id' => $routeStop->getId(),
            'message' => 'Route stop created successfully. Waiting for driver confirmation.'
        ], Response::HTTP_CREATED);
    }

    /**
     * Drivers can list unconfirmed route stops for their routes
     */
    #[RouteAttribute('/unconfirmed', name: 'unconfirmed', methods: ['GET'])]
    #[IsGranted('ROLE_DRIVER')]
    public function listUnconfirmedRouteStops(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        if (!$driver) {
            return $this->json([
                'error' => 'Driver profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Get all routes assigned to this driver
        $driverRoutes = $this->routeRepository->findBy(['driver' => $driver]);

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
                $student = $stop->getStudent();
                $address = $stop->getAddress();
                $unconfirmedStops[] = [
                    'id' => $stop->getId(),
                    'route_id' => $route->getId(),
                    'route_name' => $route->getName(),
                    'student_id' => $student->getId(),
                    'student_name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'address' => [
                        'id' => $address->getId(),
                        'street' => $address->getStreet(),
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
    #[RouteAttribute('/{id}/confirm', name: 'confirm', methods: ['PATCH'])]
    #[IsGranted('ROLE_DRIVER')]
    public function confirmRouteStop(int $id): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        if (!$driver) {
            return $this->json([
                'error' => 'Driver profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $routeStop = $this->routeStopRepository->find($id);

        if (!$routeStop) {
            return $this->json([
                'error' => 'Route stop not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify the route stop belongs to a route assigned to this driver
        if ($routeStop->getRoute()->getDriver() !== $driver) {
            return $this->json([
                'error' => 'This route stop does not belong to your routes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Confirm the route stop
        $routeStop->setIsConfirmed(true);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Route stop confirmed successfully',
            'route_stop_id' => $routeStop->getId(),
        ]);
    }

    /**
     * Drivers can reject (deactivate) a route stop
     */
    #[RouteAttribute('/{id}/reject', name: 'reject', methods: ['PATCH'])]
    #[IsGranted('ROLE_DRIVER')]
    public function rejectRouteStop(int $id): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();

        if (!$driver) {
            return $this->json([
                'error' => 'Driver profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $routeStop = $this->routeStopRepository->find($id);

        if (!$routeStop) {
            return $this->json([
                'error' => 'Route stop not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify the route stop belongs to a route assigned to this driver
        if ($routeStop->getRoute()->getDriver() !== $driver) {
            return $this->json([
                'error' => 'This route stop does not belong to your routes'
            ], Response::HTTP_FORBIDDEN);
        }

        // Reject the route stop by deactivating it
        $routeStop->setIsActive(false);
        $routeStop->setIsConfirmed(false);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Route stop rejected successfully',
            'route_stop_id' => $routeStop->getId(),
        ]);
    }
}
