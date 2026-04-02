<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushDeviceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushDeviceRepository::class)]
#[ORM\Table(name: 'push_devices')]
#[ORM\UniqueConstraint(name: 'uniq_expo_token', columns: ['expo_push_token'])]
#[ORM\Index(name: 'idx_push_devices_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_push_devices_active', columns: ['is_active'])]
class PushDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSeenAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        private ?User $user,
        #[ORM\Column(length: 255)]
        private string $expoPushToken,
        #[ORM\Column(length: 10)]
        private string $platform,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $deviceName = null,
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $osVersion = null,
    ) {
        $this->createdAt = new DateTimeImmutable();
        $this->lastSeenAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function updateUser(User $user): void
    {
        $this->user = $user;
    }

    public function getExpoPushToken(): string
    {
        return $this->expoPushToken;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function getOsVersion(): ?string
    {
        return $this->osVersion;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function touch(): void
    {
        $this->lastSeenAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }
}
