<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Enum\DepartureMode;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use App\State\SpecialEventRoute\ArriveAtEventProcessor;
use App\State\SpecialEventRoute\CompleteProcessor;
use App\State\SpecialEventRoute\PublishProcessor;
use App\State\SpecialEventRoute\SpecialEventRouteCollectionProvider;
use App\State\SpecialEventRoute\SpecialEventRouteCreateProcessor;
use App\State\SpecialEventRoute\SpecialEventRouteDeleteProcessor;
use App\State\SpecialEventRoute\SpecialEventRouteUpdateProcessor;
use App\State\SpecialEventRoute\StartOutboundProcessor;
use App\State\SpecialEventRoute\StartReturnProcessor;
use App\State\SpecialEventRoute\StudentReadyProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SpecialEventRouteRepository::class)]
#[ORM\Table(name: 'special_event_routes')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/special-event-routes',
            security: "is_granted('ROUTE_MANAGE')",
            provider: SpecialEventRouteCollectionProvider::class,
            parameters: [
                'search[:property]' => new QueryParameter(
                    properties: ['school', 'status', 'eventType', 'routeMode']
                ),
                'date' => new QueryParameter(),
            ],
        ),
        new Get(
            uriTemplate: '/special-event-routes/{id}',
            security: "is_granted('ROUTE_MANAGE')",
        ),
        new Post(
            uriTemplate: '/special-event-routes',
            security: "is_granted('ROUTE_MANAGE')",
            processor: SpecialEventRouteCreateProcessor::class,
        ),
        new Patch(
            uriTemplate: '/special-event-routes/{id}',
            security: "is_granted('ROUTE_MANAGE')",
            processor: SpecialEventRouteUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/special-event-routes/{id}',
            security: "is_granted('ROUTE_MANAGE')",
            processor: SpecialEventRouteDeleteProcessor::class,
        ),
        new Post(
            uriTemplate: '/special-event-routes/{id}/publish',
            status: 200,
            openapi: new Operation(
                responses: [
                    '200' => new Response('Route published'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROUTE_MANAGE'),
                    '404' => new Response('Not found'),
                    '422' => new Response('Invalid state or missing required fields'),
                ],
                summary: 'Publish a special event route (DRAFT → PUBLISHED)',
            ),
            security: "is_granted('ROUTE_MANAGE')",
            input: false,
            read: false,
            processor: PublishProcessor::class,
        ),
        new Post(
            uriTemplate: '/special-event-routes/{id}/start-outbound',
            status: 200,
            openapi: new Operation(
                responses: [
                    '200' => new Response('Outbound leg started'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROUTE_MANAGE'),
                    '404' => new Response('Not found'),
                    '422' => new Response('Invalid state'),
                ],
                summary: 'Start the outbound leg (PUBLISHED → IN_PROGRESS)',
            ),
            security: "is_granted('ROUTE_MANAGE')",
            input: false,
            read: false,
            processor: StartOutboundProcessor::class,
        ),
        new Post(
            uriTemplate: '/special-event-routes/{id}/arrive-at-event',
            status: 200,
            openapi: new Operation(
                responses: [
                    '200' => new Response('Arrived at event'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROUTE_MANAGE'),
                    '404' => new Response('Not found'),
                    '422' => new Response('Invalid state'),
                ],
                summary: 'Mark arrival at event (ONE_WAY auto-completes)',
            ),
            security: "is_granted('ROUTE_MANAGE')",
            input: false,
            read: false,
            processor: ArriveAtEventProcessor::class,
        ),
        new Post(
            uriTemplate: '/special-event-routes/{id}/start-return',
            status: 200,
            openapi: new Operation(
                responses: [
                    '200' => new Response('Return leg started'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROUTE_MANAGE'),
                    '404' => new Response('Not found'),
                    '422' => new Response('Invalid state or ONE_WAY route'),
                ],
                summary: 'Start the return leg (not valid for ONE_WAY)',
            ),
            security: "is_granted('ROUTE_MANAGE')",
            input: false,
            read: false,
            processor: StartReturnProcessor::class,
        ),
        new Post(
            uriTemplate: '/special-event-routes/{id}/complete',
            status: 200,
            openapi: new Operation(
                responses: [
                    '200' => new Response('Route completed'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROUTE_MANAGE'),
                    '404' => new Response('Not found'),
                    '422' => new Response('Invalid state'),
                ],
                summary: 'Complete the route (IN_PROGRESS → COMPLETED)',
            ),
            security: "is_granted('ROUTE_MANAGE')",
            input: false,
            read: false,
            processor: CompleteProcessor::class,
        ),
        new Post(
            uriTemplate: '/special-event-routes/{id}/students/{studentId}/ready',
            status: 202,
            openapi: new Operation(
                responses: [
                    '202' => new Response('Student marked as ready'),
                    '401' => new Response('Unauthenticated'),
                    '403' => new Response('Requires ROLE_DRIVER'),
                    '404' => new Response('Not found'),
                    '422' => new Response('Invalid state or route mode'),
                ],
                summary: 'Mark a student as ready for pickup (INDIVIDUAL departure mode)',
            ),
            security: "is_granted('ROLE_DRIVER')",
            input: false,
            read: false,
            processor: StudentReadyProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['special_event_route:read'],
    ],
    denormalizationContext: [
        'groups' => ['special_event_route:write'],
    ],
)]
class SpecialEventRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['special_event_route:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?School $school = null;

    #[ORM\Column(length: 255)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private string $name = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?DateTimeImmutable $eventDate = null;

    #[ORM\Column(enumType: EventType::class)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private EventType $eventType = EventType::OTHER;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?Address $eventLocation = null;

    #[ORM\Column(enumType: RouteMode::class)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private RouteMode $routeMode = RouteMode::ONE_WAY;

    #[ORM\Column(nullable: true, enumType: DepartureMode::class)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?DepartureMode $departureMode = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?Driver $assignedDriver = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?Vehicle $assignedVehicle = null;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\ManyToMany(targetEntity: Student::class)]
    #[ORM\JoinTable(name: 'special_event_route_student')]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private Collection $students;

    #[ORM\Column(enumType: SpecialEventRouteStatus::class)]
    #[Groups(['special_event_route:read'])]
    private SpecialEventRouteStatus $status = SpecialEventRouteStatus::DRAFT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?DateTimeImmutable $outboundDepartureTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['special_event_route:read', 'special_event_route:write'])]
    private ?DateTimeImmutable $returnDepartureTime = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['special_event_route:read'])]
    private ?string $currentLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['special_event_route:read'])]
    private ?string $currentLongitude = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['special_event_route:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['special_event_route:read'])]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, SpecialEventRouteStop>
     */
    #[ORM\OneToMany(targetEntity: SpecialEventRouteStop::class, mappedBy: 'specialEventRoute', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'stopOrder' => 'ASC',
    ])]
    #[Groups(['special_event_route:read'])]
    private Collection $stops;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->stops = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(School $school): static
    {
        $this->school = $school;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEventDate(): ?DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    public function setEventType(EventType $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getEventLocation(): ?Address
    {
        return $this->eventLocation;
    }

    public function setEventLocation(?Address $eventLocation): static
    {
        $this->eventLocation = $eventLocation;

        return $this;
    }

    public function getRouteMode(): RouteMode
    {
        return $this->routeMode;
    }

    public function setRouteMode(RouteMode $routeMode): static
    {
        $this->routeMode = $routeMode;

        return $this;
    }

    public function getDepartureMode(): ?DepartureMode
    {
        return $this->departureMode;
    }

    public function setDepartureMode(?DepartureMode $departureMode): static
    {
        $this->departureMode = $departureMode;

        return $this;
    }

    public function getAssignedDriver(): ?Driver
    {
        return $this->assignedDriver;
    }

    public function setAssignedDriver(?Driver $assignedDriver): static
    {
        $this->assignedDriver = $assignedDriver;

        return $this;
    }

    public function getAssignedVehicle(): ?Vehicle
    {
        return $this->assignedVehicle;
    }

    public function setAssignedVehicle(?Vehicle $assignedVehicle): static
    {
        $this->assignedVehicle = $assignedVehicle;

        return $this;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): static
    {
        if (! $this->students->contains($student)) {
            $this->students->add($student);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        $this->students->removeElement($student);

        return $this;
    }

    public function getStatus(): SpecialEventRouteStatus
    {
        return $this->status;
    }

    public function setStatus(SpecialEventRouteStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOutboundDepartureTime(): ?DateTimeImmutable
    {
        return $this->outboundDepartureTime;
    }

    public function setOutboundDepartureTime(?DateTimeImmutable $outboundDepartureTime): static
    {
        $this->outboundDepartureTime = $outboundDepartureTime;

        return $this;
    }

    public function getReturnDepartureTime(): ?DateTimeImmutable
    {
        return $this->returnDepartureTime;
    }

    public function setReturnDepartureTime(?DateTimeImmutable $returnDepartureTime): static
    {
        $this->returnDepartureTime = $returnDepartureTime;

        return $this;
    }

    public function getCurrentLatitude(): ?string
    {
        return $this->currentLatitude;
    }

    public function setCurrentLatitude(?string $currentLatitude): static
    {
        $this->currentLatitude = $currentLatitude;

        return $this;
    }

    public function getCurrentLongitude(): ?string
    {
        return $this->currentLongitude;
    }

    public function setCurrentLongitude(?string $currentLongitude): static
    {
        $this->currentLongitude = $currentLongitude;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, SpecialEventRouteStop>
     */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(SpecialEventRouteStop $stop): static
    {
        if (! $this->stops->contains($stop)) {
            $this->stops->add($stop);
            $stop->setSpecialEventRoute($this);
        }

        return $this;
    }

    public function removeStop(SpecialEventRouteStop $stop): static
    {
        if ($this->stops->removeElement($stop) && $stop->getSpecialEventRoute() === $this) {
            $stop->setSpecialEventRoute(null);
        }

        return $this;
    }
}
