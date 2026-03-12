<?php

declare(strict_types=1);

namespace App\Entity;

use Deprecated;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\VehicleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SCHOOL_ADMIN') or is_granted('ROLE_DRIVER') or is_granted('ROLE_PARENT')",
            parameters: [
                'driver' => new QueryParameter(
                    filter: new IriFilter(),
                    property: 'driver',
                ),
            ],
        ),
        new Get(security: "is_granted('ROLE_SCHOOL_ADMIN') or is_granted('ROLE_DRIVER')"),
        new Post(security: "is_granted('ROLE_SCHOOL_ADMIN') or is_granted('ROLE_DRIVER')"),
        new Patch(security: "is_granted('ROLE_SCHOOL_ADMIN') or is_granted('ROLE_DRIVER')"),
        new Delete(security: "is_granted('ROLE_SCHOOL_ADMIN')"),
    ],
    normalizationContext: [
        'groups' => ['vehicle:read'],
    ],
    denormalizationContext: [
        'groups' => ['vehicle:write'],
    ],
)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vehicle:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $licensePlate = null;

    #[ORM\Column(length: 100)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $make = null;

    #[ORM\Column(length: 100)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $model = null;

    #[ORM\Column]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?int $capacity = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?int $year = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $color = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Driver::class, inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?Driver $driver = null;

    /**
     * @deprecated Use $driver instead. Kept for backward compatibility during migration.
     */
    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['vehicle:read'])]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLicensePlate(): ?string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): static
    {
        $this->licensePlate = $licensePlate;

        return $this;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function setMake(string $make): static
    {
        $this->make = $make;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
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

    #[Deprecated(message: 'Use getDriver() instead.')]
    public function getUser(): ?User
    {
        return $this->user;
    }

    #[Deprecated(message: 'Use setDriver() instead.')]
    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
