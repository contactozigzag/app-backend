<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\AbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Table(name: 'absences')]
#[ORM\Index(name: 'idx_student_date', columns: ['student_id', 'date'])]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_PARENT') or is_granted('ROLE_SCHOOL_ADMIN')"),
    ],
    normalizationContext: [
        'groups' => ['absence:read'],
    ],
    denormalizationContext: [
        'groups' => ['absence:write'],
    ]
)]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['absence:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['absence:read', 'absence:write'])]
    private ?Student $student = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['absence:read', 'absence:write'])]
    private ?DateTimeImmutable $date = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['morning', 'afternoon', 'full_day'])]
    #[Groups(['absence:read', 'absence:write'])]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['sick', 'family_emergency', 'vacation', 'other'])]
    #[Groups(['absence:read', 'absence:write'])]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['absence:read', 'absence:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['absence:read'])]
    private ?User $reportedBy = null;

    #[ORM\Column]
    #[Groups(['absence:read', 'absence:write'])]
    private bool $routeRecalculated = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['absence:read'])]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
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

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
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

    public function getReportedBy(): ?User
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?User $reportedBy): static
    {
        $this->reportedBy = $reportedBy;
        return $this;
    }

    public function isRouteRecalculated(): bool
    {
        return $this->routeRecalculated;
    }

    public function setRouteRecalculated(bool $routeRecalculated): static
    {
        $this->routeRecalculated = $routeRecalculated;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
