<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\DriverRate\SetDriverRatesInput;
use App\Enum\PricingModel;
use App\Repository\DriverRepository;
use App\State\DriverRate\SetDriverRatesProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DriverRepository::class)]
#[UniqueEntity(fields: ['nickname'], message: 'El alias "{{ value }}" ya está en uso. Por favor, elegí otro.')]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: [
                'groups' => ['driver:read', 'driver:item:read'],
            ],
            security: "is_granted('ROLE_USER')",
        ),
        new GetCollection(
            security: "is_granted('ROLE_USER')",
        ),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_DRIVER')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            uriTemplate: '/drivers/{id}/rates',
            security: "is_granted('ROLE_DRIVER')",
            input: SetDriverRatesInput::class,
            processor: SetDriverRatesProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'nickname' => 'start',
])]
class Driver
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['driver:read', 'user:item:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'driver', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['driver:item:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['driver:read', 'driver:write', 'user:item:read', 'user:write'])]
    private ?string $licenseNumber = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['driver:read', 'driver:write', 'user:item:read', 'user:write'])]
    private ?string $nickname = null;

    /**
     * @var Collection<int, Vehicle>
     */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'driver')]
    #[Groups(['driver:read'])]
    private Collection $vehicles;

    /**
     * Encrypted MP access token (XSalsa20-Poly1305 via TokenEncryptor).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mpAccessToken = null;

    /**
     * Encrypted MP refresh token.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mpRefreshToken = null;

    /**
     * MP seller account ID (user_id returned by OAuth).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mpAccountId = null;

    /**
     * When the current access token expires (MP tokens last ~180 days).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['driver:read'])]
    private ?DateTimeImmutable $mpTokenExpiresAt = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true, enumType: PricingModel::class)]
    #[Groups(['driver:read', 'driver:write'])]
    private ?PricingModel $pricingModel = null;

    /**
     * @var Collection<int, Route>
     */
    #[ORM\OneToMany(targetEntity: Route::class, mappedBy: 'driver')]
    #[Groups(['driver:item:read'])]
    private Collection $routes;

    /**
     * @var Collection<int, DriverRate>
     */
    #[ORM\OneToMany(targetEntity: DriverRate::class, mappedBy: 'driver', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['driver:item:read'])]
    private Collection $rates;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
        $this->routes = new ArrayCollection();
        $this->rates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(?string $licenseNumber): static
    {
        $this->licenseNumber = $licenseNumber;

        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    public function getMpAccessToken(): ?string
    {
        return $this->mpAccessToken;
    }

    public function setMpAccessToken(?string $mpAccessToken): static
    {
        $this->mpAccessToken = $mpAccessToken;

        return $this;
    }

    public function getMpRefreshToken(): ?string
    {
        return $this->mpRefreshToken;
    }

    public function setMpRefreshToken(?string $mpRefreshToken): static
    {
        $this->mpRefreshToken = $mpRefreshToken;

        return $this;
    }

    public function getMpAccountId(): ?string
    {
        return $this->mpAccountId;
    }

    public function setMpAccountId(?string $mpAccountId): static
    {
        $this->mpAccountId = $mpAccountId;

        return $this;
    }

    public function getMpTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->mpTokenExpiresAt;
    }

    public function setMpTokenExpiresAt(?DateTimeImmutable $mpTokenExpiresAt): static
    {
        $this->mpTokenExpiresAt = $mpTokenExpiresAt;

        return $this;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): static
    {
        if (! $this->vehicles->contains($vehicle)) {
            $this->vehicles->add($vehicle);
            $vehicle->setDriver($this);
        }

        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): static
    {
        if ($this->vehicles->removeElement($vehicle) && $vehicle->getDriver() === $this) {
            $vehicle->setDriver(null);
        }

        return $this;
    }

    public function getPricingModel(): ?PricingModel
    {
        return $this->pricingModel;
    }

    public function setPricingModel(?PricingModel $pricingModel): static
    {
        $this->pricingModel = $pricingModel;

        return $this;
    }

    /**
     * @return Collection<int, Route>
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    /**
     * @return Collection<int, DriverRate>
     */
    public function getRates(): Collection
    {
        return $this->rates;
    }

    public function addRate(DriverRate $rate): static
    {
        if (! $this->rates->contains($rate)) {
            $this->rates->add($rate);
            $rate->setDriver($this);
        }

        return $this;
    }

    public function removeRate(DriverRate $rate): static
    {
        if ($this->rates->removeElement($rate) && $rate->getDriver() === $this) {
            $rate->setDriver(null);
        }

        return $this;
    }

    /**
     * Returns true once the driver has completed the OAuth flow.
     */
    public function hasMpAuthorized(): bool
    {
        return $this->mpAccessToken !== null && $this->mpAccountId !== null;
    }

    #[Groups(['driver:read'])]
    public function getMpConnected(): bool
    {
        return $this->hasMpAuthorized();
    }
}
