<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\PaymentStatus;
use App\Enum\TransactionEvent;
use App\Repository\PaymentTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentTransactionRepository::class)]
#[ORM\Table(name: 'payment_transaction')]
#[ORM\Index(name: 'idx_transactions_payment_id', columns: ['payment_id'])]
#[ORM\Index(name: 'idx_transactions_created_at', columns: ['created_at'])]
class PaymentTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Payment $payment = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TransactionEvent::class)]
    private ?TransactionEvent $eventType = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: PaymentStatus::class)]
    private ?PaymentStatus $status = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $providerResponse = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $idempotencyKey = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    public function getEventType(): ?TransactionEvent
    {
        return $this->eventType;
    }

    public function setEventType(TransactionEvent $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getStatus(): ?PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProviderResponse(): ?array
    {
        return $this->providerResponse;
    }

    /**
     * @param array<string, mixed>|null $providerResponse
     */
    public function setProviderResponse(?array $providerResponse): static
    {
        $this->providerResponse = $providerResponse;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }
}
