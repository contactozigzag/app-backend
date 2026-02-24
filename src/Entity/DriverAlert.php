<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\AlertStatus;
use App\Repository\DriverAlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DriverAlertRepository::class)]
#[ORM\Table(name: 'driver_alerts')]
#[ORM\HasLifecycleCallbacks]
class DriverAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $alertId;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Driver $distressedDriver = null;

    #[ORM\ManyToOne(targetEntity: ActiveRoute::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ActiveRoute $routeSession = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $locationLat = '0.000000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $locationLng = '0.000000';

    #[ORM\Column(enumType: AlertStatus::class)]
    private AlertStatus $status = AlertStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $triggeredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $resolvedBy = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Driver $respondingDriver = null;

    /**
     * @var int[] Denormalized list of notified nearby driver IDs
     */
    #[ORM\Column(type: Types::JSON)]
    private array $nearbyDriverIds = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->alertId = Uuid::v4()->toRfc4122();
        $this->triggeredAt = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlertId(): string
    {
        return $this->alertId;
    }

    public function getDistressedDriver(): ?Driver
    {
        return $this->distressedDriver;
    }

    public function setDistressedDriver(Driver $distressedDriver): static
    {
        $this->distressedDriver = $distressedDriver;

        return $this;
    }

    public function getRouteSession(): ?ActiveRoute
    {
        return $this->routeSession;
    }

    public function setRouteSession(?ActiveRoute $routeSession): static
    {
        $this->routeSession = $routeSession;

        return $this;
    }

    public function getLocationLat(): string
    {
        return $this->locationLat;
    }

    public function setLocationLat(string $locationLat): static
    {
        $this->locationLat = $locationLat;

        return $this;
    }

    public function getLocationLng(): string
    {
        return $this->locationLng;
    }

    public function setLocationLng(string $locationLng): static
    {
        $this->locationLng = $locationLng;

        return $this;
    }

    public function getStatus(): AlertStatus
    {
        return $this->status;
    }

    public function setStatus(AlertStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTriggeredAt(): DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?User $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;

        return $this;
    }

    public function getRespondingDriver(): ?Driver
    {
        return $this->respondingDriver;
    }

    public function setRespondingDriver(?Driver $respondingDriver): static
    {
        $this->respondingDriver = $respondingDriver;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getNearbyDriverIds(): array
    {
        return $this->nearbyDriverIds;
    }

    /**
     * @param int[] $nearbyDriverIds
     */
    public function setNearbyDriverIds(array $nearbyDriverIds): static
    {
        $this->nearbyDriverIds = $nearbyDriverIds;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
