<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpecialEventRouteStopRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpecialEventRouteStopRepository::class)]
#[ORM\Table(name: 'special_event_route_stops')]
class SpecialEventRouteStop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SpecialEventRoute::class, inversedBy: 'stops')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SpecialEventRoute $specialEventRoute = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Address $address = null;

    #[ORM\Column]
    private int $stopOrder = 0;

    /** Seconds from return departure time */
    #[ORM\Column(nullable: true)]
    private ?int $estimatedArrivalTime = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column]
    private bool $isStudentReady = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readyAt = null;

    #[ORM\Column]
    private int $geofenceRadius = 50;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpecialEventRoute(): ?SpecialEventRoute
    {
        return $this->specialEventRoute;
    }

    public function setSpecialEventRoute(?SpecialEventRoute $specialEventRoute): static
    {
        $this->specialEventRoute = $specialEventRoute;

        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(Student $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getStopOrder(): int
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isStudentReady(): bool
    {
        return $this->isStudentReady;
    }

    public function setIsStudentReady(bool $isStudentReady): static
    {
        $this->isStudentReady = $isStudentReady;

        return $this;
    }

    public function getReadyAt(): ?\DateTimeImmutable
    {
        return $this->readyAt;
    }

    public function setReadyAt(?\DateTimeImmutable $readyAt): static
    {
        $this->readyAt = $readyAt;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
