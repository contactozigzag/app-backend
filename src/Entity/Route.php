<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\RouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RouteRepository::class)]
#[ORM\Table(name: 'routes')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
        new Put(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
        new Patch(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
        new Delete(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['route:read']],
    denormalizationContext: ['groups' => ['route:write']]
)]
class Route
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['route:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['route:read', 'route:write'])]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['route:read', 'route:write'])]
    private ?School $school = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['morning', 'afternoon'])]
    #[Groups(['route:read', 'route:write'])]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['route:read', 'route:write'])]
    private ?Driver $driver = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    #[Assert\NotBlank]
    #[Groups(['route:read', 'route:write'])]
    private ?string $startLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    #[Assert\NotBlank]
    #[Groups(['route:read', 'route:write'])]
    private ?string $startLongitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    #[Assert\NotBlank]
    #[Groups(['route:read', 'route:write'])]
    private ?string $endLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    #[Assert\NotBlank]
    #[Groups(['route:read', 'route:write'])]
    private ?string $endLongitude = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['route:read', 'route:write'])]
    private ?int $estimatedDuration = null; // in seconds

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['route:read', 'route:write'])]
    private ?int $estimatedDistance = null; // in meters

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['route:read', 'route:write'])]
    private ?string $polyline = null;

    #[ORM\Column]
    #[Groups(['route:read', 'route:write'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['route:read', 'route:write'])]
    private bool $isTemplate = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['route:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['route:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, RouteStop>
     */
    #[ORM\OneToMany(targetEntity: RouteStop::class, mappedBy: 'route', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['stopOrder' => 'ASC'])]
    #[Groups(['route:read', 'route:write'])]
    private Collection $stops;

    public function __construct()
    {
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getStartLatitude(): ?string
    {
        return $this->startLatitude;
    }

    public function setStartLatitude(string $startLatitude): static
    {
        $this->startLatitude = $startLatitude;
        return $this;
    }

    public function getStartLongitude(): ?string
    {
        return $this->startLongitude;
    }

    public function setStartLongitude(string $startLongitude): static
    {
        $this->startLongitude = $startLongitude;
        return $this;
    }

    public function getEndLatitude(): ?string
    {
        return $this->endLatitude;
    }

    public function setEndLatitude(string $endLatitude): static
    {
        $this->endLatitude = $endLatitude;
        return $this;
    }

    public function getEndLongitude(): ?string
    {
        return $this->endLongitude;
    }

    public function setEndLongitude(string $endLongitude): static
    {
        $this->endLongitude = $endLongitude;
        return $this;
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): static
    {
        $this->estimatedDuration = $estimatedDuration;
        return $this;
    }

    public function getEstimatedDistance(): ?int
    {
        return $this->estimatedDistance;
    }

    public function setEstimatedDistance(?int $estimatedDistance): static
    {
        $this->estimatedDistance = $estimatedDistance;
        return $this;
    }

    public function getPolyline(): ?string
    {
        return $this->polyline;
    }

    public function setPolyline(?string $polyline): static
    {
        $this->polyline = $polyline;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isTemplate(): bool
    {
        return $this->isTemplate;
    }

    public function setIsTemplate(bool $isTemplate): static
    {
        $this->isTemplate = $isTemplate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, RouteStop>
     */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(RouteStop $stop): static
    {
        if (!$this->stops->contains($stop)) {
            $this->stops->add($stop);
            $stop->setRoute($this);
        }

        return $this;
    }

    public function removeStop(RouteStop $stop): static
    {
        if ($this->stops->removeElement($stop)) {
            if ($stop->getRoute() === $this) {
                $stop->setRoute(null);
            }
        }

        return $this;
    }
}
