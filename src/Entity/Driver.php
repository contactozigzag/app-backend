<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\DriverRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DriverRepository::class)]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            parameters: [
                'search[:property]' => new QueryParameter(
                    filter: new PartialSearchFilter(),
                    properties: ['nickname']
                ),
            ]
        ),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_DRIVER')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class Driver
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'driver', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['driver:read', 'driver:write', 'user:write'])]
    private ?string $licenseNumber = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['driver:read', 'driver:write', 'user:write'])]
    private ?string $nickname = null;

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
    private ?DateTimeImmutable $mpTokenExpiresAt = null;

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
     * Returns true once the driver has completed the OAuth flow.
     */
    public function hasMpAuthorized(): bool
    {
        return $this->mpAccessToken !== null && $this->mpAccountId !== null;
    }
}
