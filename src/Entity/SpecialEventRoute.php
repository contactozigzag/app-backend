<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DepartureMode;
use App\Enum\EventType;
use App\Enum\RouteMode;
use App\Enum\SpecialEventRouteStatus;
use App\Repository\SpecialEventRouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpecialEventRouteRepository::class)]
#[ORM\Table(name: 'special_event_routes')]
#[ORM\HasLifecycleCallbacks]
class SpecialEventRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $eventDate = null;

    #[ORM\Column(enumType: EventType::class)]
    private EventType $eventType = EventType::OTHER;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Address $eventLocation = null;

    #[ORM\Column(enumType: RouteMode::class)]
    private RouteMode $routeMode = RouteMode::ONE_WAY;

    #[ORM\Column(enumType: DepartureMode::class, nullable: true)]
    private ?DepartureMode $departureMode = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Driver $assignedDriver = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Vehicle $assignedVehicle = null;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\ManyToMany(targetEntity: Student::class)]
    #[ORM\JoinTable(name: 'special_event_route_student')]
    private Collection $students;

    #[ORM\Column(enumType: SpecialEventRouteStatus::class)]
    private SpecialEventRouteStatus $status = SpecialEventRouteStatus::DRAFT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $outboundDepartureTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $returnDepartureTime = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    private ?string $currentLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    private ?string $currentLongitude = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, SpecialEventRouteStop>
     */
    #[ORM\OneToMany(targetEntity: SpecialEventRouteStop::class, mappedBy: 'specialEventRoute', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'stopOrder' => 'ASC',
    ])]
    private Collection $stops;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->stops = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): static
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

    public function getOutboundDepartureTime(): ?\DateTimeImmutable
    {
        return $this->outboundDepartureTime;
    }

    public function setOutboundDepartureTime(?\DateTimeImmutable $outboundDepartureTime): static
    {
        $this->outboundDepartureTime = $outboundDepartureTime;

        return $this;
    }

    public function getReturnDepartureTime(): ?\DateTimeImmutable
    {
        return $this->returnDepartureTime;
    }

    public function setReturnDepartureTime(?\DateTimeImmutable $returnDepartureTime): static
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
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
