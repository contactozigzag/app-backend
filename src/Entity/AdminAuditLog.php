<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_audit_log')]
#[ORM\Index(name: 'idx_audit_entity_type_date', columns: ['entity_type', 'created_at'])]
#[ORM\Index(name: 'idx_audit_admin_email', columns: ['admin_email'])]
class AdminAuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $entityType = '';

    #[ORM\Column(length: 255)]
    private string $entityId = '';

    /**
     * create | update | delete
     */
    #[ORM\Column(length: 16)]
    private string $action = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminEmail = null;

    /**
     * @var array<string, list<string>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changes = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getAdminEmail(): ?string
    {
        return $this->adminEmail;
    }

    public function setAdminEmail(?string $adminEmail): static
    {
        $this->adminEmail = $adminEmail;

        return $this;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /**
     * @param array<string, list<string>>|null $changes
     */
    public function setChanges(?array $changes): static
    {
        $this->changes = $changes;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
