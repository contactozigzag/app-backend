<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\AttendanceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttendanceRepository::class)]
#[ORM\Table(name: 'attendance')]
#[ORM\Index(name: 'idx_student_date', columns: ['student_id', 'date'])]
#[ORM\Index(name: 'idx_route_stop', columns: ['active_route_stop_id'])]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_DRIVER') or is_granted('ROLE_SCHOOL_ADMIN')"),
    ],
    normalizationContext: [
        'groups' => ['attendance:read'],
    ],
    denormalizationContext: [
        'groups' => ['attendance:write'],
    ]
)]
class Attendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['attendance:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: ActiveRouteStop::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?ActiveRouteStop $activeRouteStop = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?DateTimeImmutable $date = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['picked_up', 'dropped_off', 'no_show', 'cancelled'])]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?DateTimeImmutable $pickedUpAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?DateTimeImmutable $droppedOffAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?string $pickupLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?string $pickupLongitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?string $dropoffLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?string $dropoffLongitude = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?Driver $recordedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['attendance:read', 'attendance:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['attendance:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['attendance:read'])]
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getActiveRouteStop(): ?ActiveRouteStop
    {
        return $this->activeRouteStop;
    }

    public function setActiveRouteStop(?ActiveRouteStop $activeRouteStop): static
    {
        $this->activeRouteStop = $activeRouteStop;
        return $this;
    }

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getPickupLatitude(): ?string
    {
        return $this->pickupLatitude;
    }

    public function setPickupLatitude(?string $pickupLatitude): static
    {
        $this->pickupLatitude = $pickupLatitude;
        return $this;
    }

    public function getPickupLongitude(): ?string
    {
        return $this->pickupLongitude;
    }

    public function setPickupLongitude(?string $pickupLongitude): static
    {
        $this->pickupLongitude = $pickupLongitude;
        return $this;
    }

    public function getDropoffLatitude(): ?string
    {
        return $this->dropoffLatitude;
    }

    public function setDropoffLatitude(?string $dropoffLatitude): static
    {
        $this->dropoffLatitude = $dropoffLatitude;
        return $this;
    }

    public function getDropoffLongitude(): ?string
    {
        return $this->dropoffLongitude;
    }

    public function setDropoffLongitude(?string $dropoffLongitude): static
    {
        $this->dropoffLongitude = $dropoffLongitude;
        return $this;
    }

    public function getRecordedBy(): ?Driver
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?Driver $recordedBy): static
    {
        $this->recordedBy = $recordedBy;
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
