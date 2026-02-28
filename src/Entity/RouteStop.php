<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\RouteStop\RouteStopActionOutput;
use App\Repository\RouteStopRepository;
use App\State\RouteStop\RouteStopConfirmProcessor;
use App\State\RouteStop\RouteStopRejectProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RouteStopRepository::class)]
#[ORM\Table(name: 'route_stops')]
#[ApiResource(operations: [
    new GetCollection(uriTemplate: '/route-stops'),
    new Get(uriTemplate: '/route-stops/{id}'),
    new Post(uriTemplate: '/route-stops'),
    new Put(uriTemplate: '/route-stops/{id}'),
    new Patch(uriTemplate: '/route-stops/{id}'),
    new Delete(uriTemplate: '/route-stops/{id}'),
    new Patch(
        uriTemplate: '/route-stops/{id}/confirm',
        openapi: new Operation(
            responses: [
                '200' => new Response('Route stop confirmed'),
                '401' => new Response('Unauthenticated'),
                '403' => new Response('Not the route driver'),
                '404' => new Response('Route stop not found'),
            ],
            summary: 'Confirm a route stop',
            description: 'Marks the route stop as active and confirmed. The caller must be the driver of the associated route.',
        ),
        normalizationContext: [
            'groups' => ['route_stop:action:read'],
        ],
        security: "is_granted('ROLE_DRIVER')",
        input: false,
        output: RouteStopActionOutput::class,
        read: false,
        processor: RouteStopConfirmProcessor::class,
    ),
    new Patch(
        uriTemplate: '/route-stops/{id}/reject',
        openapi: new Operation(
            responses: [
                '200' => new Response('Route stop rejected'),
                '401' => new Response('Unauthenticated'),
                '403' => new Response('Not the route driver'),
                '404' => new Response('Route stop not found'),
            ],
            summary: 'Reject a route stop',
            description: 'Marks the route stop as inactive and unconfirmed. The caller must be the driver of the associated route.',
        ),
        normalizationContext: [
            'groups' => ['route_stop:action:read'],
        ],
        security: "is_granted('ROLE_DRIVER')",
        input: false,
        output: RouteStopActionOutput::class,
        read: false,
        processor: RouteStopRejectProcessor::class,
    ),
], normalizationContext: [
    'groups' => ['route_stop:read'],
], denormalizationContext: [
    'groups' => ['route_stop:write'],
], security: "is_granted('ROLE_USER')")]
class RouteStop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['route_stop:read', 'route:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class, inversedBy: 'stops')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['route_stop:read', 'route_stop:write'])]
    private ?Route $route = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private ?Address $address = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private ?int $stopOrder = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private ?int $estimatedArrivalTime = null; // seconds from route start

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private int $geofenceRadius = 50; // meters

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['route_stop:read', 'route_stop:write', 'route:read'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['route_stop:read', 'route:read'])]
    private bool $isConfirmed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['route_stop:read'])]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): static
    {
        $this->route = $route;
        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getStopOrder(): ?int
    {
        return $this->stopOrder;
    }

    public function setStopOrder(int $stopOrder): static
    {
        $this->stopOrder = $stopOrder;
        return $this;
    }

    public function getEstimatedArrivalTime(): ?int
    {
        return $this->estimatedArrivalTime;
    }

    public function setEstimatedArrivalTime(?int $estimatedArrivalTime): static
    {
        $this->estimatedArrivalTime = $estimatedArrivalTime;
        return $this;
    }

    public function getGeofenceRadius(): int
    {
        return $this->geofenceRadius;
    }

    public function setGeofenceRadius(int $geofenceRadius): static
    {
        $this->geofenceRadius = $geofenceRadius;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getIsConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(bool $isConfirmed): static
    {
        $this->isConfirmed = $isConfirmed;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
