<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ArchivedRouteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ArchivedRouteRepository::class)]
#[ORM\Table(name: 'archived_routes')]
#[ORM\Index(name: 'idx_archived_date', columns: ['date'])]
#[ORM\Index(name: 'idx_school_date', columns: ['school_id', 'date'])]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
        new GetCollection(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
    ],
    normalizationContext: [
        'groups' => ['archived_route:read'],
    ],
)]
class ArchivedRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $originalActiveRouteId = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['archived_route:read'])]
    private ?School $school = null;

    #[ORM\Column(length: 255)]
    #[Groups(['archived_route:read'])]
    private ?string $routeName = null;

    #[ORM\Column(length: 20)]
    #[Groups(['archived_route:read'])]
    private ?string $routeType = null;

    #[ORM\Column(length: 255)]
    #[Groups(['archived_route:read'])]
    private ?string $driverName = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['archived_route:read'])]
    private ?DateTimeImmutable $date = null;

    #[ORM\Column(length: 20)]
    #[Groups(['archived_route:read'])]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?int $totalDistance = null; // meters

    #[ORM\Column(nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?int $totalDuration = null; // seconds

    #[ORM\Column(nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?int $actualDuration = null; // seconds (calculated from started_at to completed_at)

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $totalStops = null;

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $completedStops = null;

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $skippedStops = null;

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $studentsPickedUp = null;

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $studentsDroppedOff = null;

    #[ORM\Column]
    #[Groups(['archived_route:read'])]
    private ?int $noShows = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?string $onTimePercentage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?array $stopData = null; // Serialized stop information

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?array $performanceMetrics = null; // Additional calculated metrics

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['archived_route:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['archived_route:read'])]
    private DateTimeImmutable $archivedAt;

    public function __construct()
    {
        $this->archivedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalActiveRouteId(): ?int
    {
        return $this->originalActiveRouteId;
    }

    public function setOriginalActiveRouteId(int $originalActiveRouteId): static
    {
        $this->originalActiveRouteId = $originalActiveRouteId;
        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;
        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): static
    {
        $this->routeName = $routeName;
        return $this;
    }

    public function getRouteType(): ?string
    {
        return $this->routeType;
    }

    public function setRouteType(string $routeType): static
    {
        $this->routeType = $routeType;
        return $this;
    }

    public function getDriverName(): ?string
    {
        return $this->driverName;
    }

    public function setDriverName(string $driverName): static
    {
        $this->driverName = $driverName;
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

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getTotalDistance(): ?int
    {
        return $this->totalDistance;
    }

    public function setTotalDistance(?int $totalDistance): static
    {
        $this->totalDistance = $totalDistance;
        return $this;
    }

    public function getTotalDuration(): ?int
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(?int $totalDuration): static
    {
        $this->totalDuration = $totalDuration;
        return $this;
    }

    public function getActualDuration(): ?int
    {
        return $this->actualDuration;
    }

    public function setActualDuration(?int $actualDuration): static
    {
        $this->actualDuration = $actualDuration;
        return $this;
    }

    public function getTotalStops(): ?int
    {
        return $this->totalStops;
    }

    public function setTotalStops(int $totalStops): static
    {
        $this->totalStops = $totalStops;
        return $this;
    }

    public function getCompletedStops(): ?int
    {
        return $this->completedStops;
    }

    public function setCompletedStops(int $completedStops): static
    {
        $this->completedStops = $completedStops;
        return $this;
    }

    public function getSkippedStops(): ?int
    {
        return $this->skippedStops;
    }

    public function setSkippedStops(int $skippedStops): static
    {
        $this->skippedStops = $skippedStops;
        return $this;
    }

    public function getStudentsPickedUp(): ?int
    {
        return $this->studentsPickedUp;
    }

    public function setStudentsPickedUp(int $studentsPickedUp): static
    {
        $this->studentsPickedUp = $studentsPickedUp;
        return $this;
    }

    public function getStudentsDroppedOff(): ?int
    {
        return $this->studentsDroppedOff;
    }

    public function setStudentsDroppedOff(int $studentsDroppedOff): static
    {
        $this->studentsDroppedOff = $studentsDroppedOff;
        return $this;
    }

    public function getNoShows(): ?int
    {
        return $this->noShows;
    }

    public function setNoShows(int $noShows): static
    {
        $this->noShows = $noShows;
        return $this;
    }

    public function getOnTimePercentage(): ?string
    {
        return $this->onTimePercentage;
    }

    public function setOnTimePercentage(?string $onTimePercentage): static
    {
        $this->onTimePercentage = $onTimePercentage;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStopData(): ?array
    {
        return $this->stopData;
    }

    /**
     * @param array<string, mixed>|null $stopData
     */
    public function setStopData(?array $stopData): static
    {
        $this->stopData = $stopData;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPerformanceMetrics(): ?array
    {
        return $this->performanceMetrics;
    }

    /**
     * @param array<string, mixed>|null $performanceMetrics
     */
    public function setPerformanceMetrics(?array $performanceMetrics): static
    {
        $this->performanceMetrics = $performanceMetrics;
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

    public function getArchivedAt(): DateTimeImmutable
    {
        return $this->archivedAt;
    }
}
