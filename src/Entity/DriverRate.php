<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\PricingModel;
use App\Repository\DriverRateRepository;
use App\State\DriverRate\DriverRateCollectionProvider;
use App\State\DriverRate\DriverRateCreateProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: DriverRateRepository::class)]
#[ORM\Table(name: 'driver_rate')]
#[ORM\UniqueConstraint(name: 'uniq_driver_rate_driver_route', columns: ['driver_id', 'route_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/driver-rates',
            normalizationContext: [
                'groups' => ['driver_rate:read'],
            ],
            security: "is_granted('ROLE_USER')",
            provider: DriverRateCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/driver-rates/{id}',
            normalizationContext: [
                'groups' => ['driver_rate:read', 'driver_rate:item:read'],
            ],
            security: "is_granted('ROLE_USER')",
        ),
        new Post(
            uriTemplate: '/driver-rates',
            denormalizationContext: [
                'groups' => ['driver_rate:write'],
            ],
            security: "is_granted('ROLE_DRIVER')",
            processor: DriverRateCreateProcessor::class,
        ),
        new Patch(
            uriTemplate: '/driver-rates/{id}',
            denormalizationContext: [
                'groups' => ['driver_rate:write'],
            ],
            security: "is_granted('ROLE_DRIVER') and object.getDriver().getUser() == user",
        ),
        new Delete(
            uriTemplate: '/driver-rates/{id}',
            security: "is_granted('ROLE_DRIVER') and object.getDriver().getUser() == user",
        ),
    ],
)]
class DriverRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['driver_rate:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Driver::class, inversedBy: 'rates')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['driver_rate:read', 'driver_rate:write'])]
    private ?Driver $driver = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: PricingModel::class)]
    #[Assert\NotNull]
    #[Groups(['driver_rate:read', 'driver_rate:write'])]
    private ?PricingModel $pricingModel = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['driver_rate:read', 'driver_rate:write'])]
    private ?Route $route = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    #[Groups(['driver_rate:read', 'driver_rate:write'])]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    #[Groups(['driver_rate:read', 'driver_rate:write'])]
    private ?string $perStudentAmount = null;

    #[ORM\Column(length: 3)]
    #[Assert\Currency]
    #[Groups(['driver_rate:read', 'driver_rate:write'])]
    private string $currency = 'ARS';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['driver_rate:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['driver_rate:read'])]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validatePricingModelConsistency(ExecutionContextInterface $context): void
    {
        if (! $this->pricingModel instanceof PricingModel) {
            return;
        }

        if ($this->pricingModel->requiresRoute() && ! $this->route instanceof Route) {
            $context->buildViolation('Route is required for {{ model }} pricing model.')
                ->setParameter('{{ model }}', $this->pricingModel->label())
                ->atPath('route')
                ->addViolation();
        }

        if (! $this->pricingModel->requiresRoute() && $this->route instanceof Route) {
            $context->buildViolation('Route must be null for {{ model }} pricing model.')
                ->setParameter('{{ model }}', $this->pricingModel->label())
                ->atPath('route')
                ->addViolation();
        }

        if ($this->pricingModel->usesPerStudentAmount()) {
            if ($this->perStudentAmount === null) {
                $context->buildViolation('Per-student amount is required for {{ model }} pricing model.')
                    ->setParameter('{{ model }}', $this->pricingModel->label())
                    ->atPath('perStudentAmount')
                    ->addViolation();
            }

            if ($this->amount !== null) {
                $context->buildViolation('Amount must be null for {{ model }} pricing model. Use perStudentAmount instead.')
                    ->setParameter('{{ model }}', $this->pricingModel->label())
                    ->atPath('amount')
                    ->addViolation();
            }
        } else {
            if ($this->amount === null) {
                $context->buildViolation('Amount is required for {{ model }} pricing model.')
                    ->setParameter('{{ model }}', $this->pricingModel->label())
                    ->atPath('amount')
                    ->addViolation();
            }

            if ($this->perStudentAmount !== null) {
                $context->buildViolation('Per-student amount must be null for {{ model }} pricing model. Use amount instead.')
                    ->setParameter('{{ model }}', $this->pricingModel->label())
                    ->atPath('perStudentAmount')
                    ->addViolation();
            }
        }
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

    public function getPricingModel(): ?PricingModel
    {
        return $this->pricingModel;
    }

    public function setPricingModel(PricingModel $pricingModel): static
    {
        $this->pricingModel = $pricingModel;

        return $this;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPerStudentAmount(): ?string
    {
        return $this->perStudentAmount;
    }

    public function setPerStudentAmount(?string $perStudentAmount): static
    {
        $this->perStudentAmount = $perStudentAmount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

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
