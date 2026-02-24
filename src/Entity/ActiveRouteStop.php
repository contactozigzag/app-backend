<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ActiveRouteStopRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiveRouteStopRepository::class)]
#[ORM\Table(name: 'active_route_stops')]
#[ApiResource(normalizationContext: [
    'groups' => ['active_route_stop:read'],
], denormalizationContext: [
    'groups' => ['active_route_stop:write'],
], security: "is_granted('ROLE_USER')")]
class ActiveRouteStop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['active_route_stop:read', 'active_route:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ActiveRoute::class, inversedBy: 'stops')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['active_route_stop:read', 'active_route_stop:write'])]
    private ?ActiveRoute $activeRoute = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?Address $address = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?int $stopOrder = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['pending', 'approaching', 'arrived', 'picked_up', 'dropped_off', 'skipped', 'absent'])]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?DateTimeImmutable $arrivedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?DateTimeImmutable $pickedUpAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?DateTimeImmutable $droppedOffAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['active_route_stop:read', 'active_route:read'])]
    private ?int $estimatedArrivalTime = null; // seconds from route start

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private int $geofenceRadius = 50; // meters

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['active_route_stop:read', 'active_route_stop:write', 'active_route:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['active_route_stop:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['active_route_stop:read'])]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
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

    public function getActiveRoute(): ?ActiveRoute
    {
        return $this->activeRoute;
    }

    public function setActiveRoute(?ActiveRoute $activeRoute): static
    {
        $this->activeRoute = $activeRoute;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getArrivedAt(): ?DateTimeImmutable
    {
        return $this->arrivedAt;
    }

    public function setArrivedAt(?DateTimeImmutable $arrivedAt): static
    {
        $this->arrivedAt = $arrivedAt;
        return $this;
    }

    public function getPickedUpAt(): ?DateTimeImmutable
    {
        return $this->pickedUpAt;
    }

    public function setPickedUpAt(?DateTimeImmutable $pickedUpAt): static
    {
        $this->pickedUpAt = $pickedUpAt;
        return $this;
    }

    public function getDroppedOffAt(): ?DateTimeImmutable
    {
        return $this->droppedOffAt;
    }

    public function setDroppedOffAt(?DateTimeImmutable $droppedOffAt): static
    {
        $this->droppedOffAt = $droppedOffAt;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
