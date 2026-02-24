<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\LocationUpdateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LocationUpdateRepository::class)]
#[ORM\Table(name: 'location_updates')]
#[ORM\Index(name: 'idx_driver_created', columns: ['driver_id', 'created_at'])]
#[ORM\Index(name: 'idx_route_created', columns: ['active_route_id', 'created_at'])]
#[ApiResource(
    operations: [
        new Post(security: "is_granted('ROLE_DRIVER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: [
        'groups' => ['location:read'],
    ],
    denormalizationContext: [
        'groups' => ['location:write'],
    ]
)]
class LocationUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['location:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['location:read', 'location:write'])]
    private ?Driver $driver = null;

    #[ORM\ManyToOne(targetEntity: ActiveRoute::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['location:read', 'location:write'])]
    private ?ActiveRoute $activeRoute = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['location:read', 'location:write'])]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['location:read', 'location:write'])]
    private ?string $longitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['location:read', 'location:write'])]
    private ?string $speed = null; // km/h

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['location:read', 'location:write'])]
    private ?string $heading = null; // degrees 0-360

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    #[Groups(['location:read', 'location:write'])]
    private ?string $accuracy = null; // meters

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['location:read', 'location:write'])]
    private ?DateTimeImmutable $timestamp = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['location:read'])]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getActiveRoute(): ?ActiveRoute
    {
        return $this->activeRoute;
    }

    public function setActiveRoute(?ActiveRoute $activeRoute): static
    {
        $this->activeRoute = $activeRoute;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getSpeed(): ?string
    {
        return $this->speed;
    }

    public function setSpeed(?string $speed): static
    {
        $this->speed = $speed;
        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function setHeading(?string $heading): static
    {
        $this->heading = $heading;
        return $this;
    }

    public function getAccuracy(): ?string
    {
        return $this->accuracy;
    }

    public function setAccuracy(?string $accuracy): static
    {
        $this->accuracy = $accuracy;
        return $this;
    }

    public function getTimestamp(): ?DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(DateTimeImmutable $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
