<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ActiveRouteRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiveRouteRepository::class)]
#[ORM\Table(name: 'active_routes')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROUTE_MANAGE')"),
        new Patch(security: "is_granted('ROLE_DRIVER') or is_granted('ROLE_SCHOOL_ADMIN')"),
        new Delete(security: "is_granted('ROUTE_MANAGE')"),
    ],
    normalizationContext: [
        'groups' => ['active_route:read'],
    ],
    denormalizationContext: [
        'groups' => ['active_route:write'],
    ]
)]
class ActiveRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['active_route:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?Route $routeTemplate = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?Driver $driver = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?DateTimeImmutable $date = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['scheduled', 'in_progress', 'completed', 'cancelled'])]
    #[Groups(['active_route:read', 'active_route:write'])]
    private string $status = 'scheduled';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?string $currentLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['active_route:read', 'active_route:write'])]
    private ?string $currentLongitude = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['active_route:read'])]
    private ?int $totalDistance = null; // meters

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['active_route:read'])]
    private ?int $totalDuration = null; // seconds

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['active_route:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['active_route:read'])]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ActiveRouteStop>
     */
    #[ORM\OneToMany(targetEntity: ActiveRouteStop::class, mappedBy: 'activeRoute', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'stopOrder' => 'ASC',
    ])]
    #[Groups(['active_route:read'])]
    private Collection $stops;

    public function __construct()
    {
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

    public function getRouteTemplate(): ?Route
    {
        return $this->routeTemplate;
    }

    public function setRouteTemplate(?Route $routeTemplate): static
    {
        $this->routeTemplate = $routeTemplate;
        return $this;
    }

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(?Driver $driver): static
    {
        $this->driver = $driver;
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

    public function getStatus(): string
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, ActiveRouteStop>
     */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(ActiveRouteStop $stop): static
    {
        if (! $this->stops->contains($stop)) {
            $this->stops->add($stop);
            $stop->setActiveRoute($this);
        }

        return $this;
    }

    public function removeStop(ActiveRouteStop $stop): static
    {
        if ($this->stops->removeElement($stop) && $stop->getActiveRoute() === $this) {
            $stop->setActiveRoute(null);
        }

        return $this;
    }
}
