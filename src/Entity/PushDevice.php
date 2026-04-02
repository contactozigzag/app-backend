<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Dto\PushDevice\PushDeviceOutput;
use App\Dto\PushDevice\RegisterPushDeviceInput;
use App\Repository\PushDeviceRepository;
use App\State\PushDevice\DeactivatePushDeviceProcessor;
use App\State\PushDevice\RegisterPushDeviceProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushDeviceRepository::class)]
#[ORM\Table(name: 'push_devices')]
#[ORM\UniqueConstraint(name: 'uniq_expo_token', columns: ['expo_push_token'])]
#[ORM\Index(name: 'idx_push_devices_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_push_devices_active', columns: ['is_active'])]
#[ApiResource(
    description: 'Registers and manages Expo push notification device tokens for authenticated users.',
    operations: [
        new Post(
            uriTemplate: '/push-devices',
            openapi: new Operation(
                responses: [
                    '201' => new Response('Device registered or updated'),
                    '400' => new Response('Validation error'),
                    '401' => new Response('Unauthenticated'),
                ],
                summary: 'Register a push device',
                description: 'Registers an Expo push token for the authenticated user. If the token already exists, updates the owning user and refreshes the last-seen timestamp.',
            ),
            security: "is_granted('ROLE_USER')",
            input: RegisterPushDeviceInput::class,
            output: PushDeviceOutput::class,
            processor: RegisterPushDeviceProcessor::class,
        ),
        new Delete(
            uriTemplate: '/push-devices/{id}',
            openapi: new Operation(
                responses: [
                    '204' => new Response('Device deactivated (or not found — always succeeds)'),
                    '401' => new Response('Unauthenticated'),
                ],
                summary: 'Deactivate a push device',
                description: 'Marks the device as inactive so it no longer receives push notifications. Silently succeeds when the device does not exist or belongs to another user.',
            ),
            security: "is_granted('ROLE_USER')",
            output: false,
            read: false,
            processor: DeactivatePushDeviceProcessor::class,
        ),
    ],
)]
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
