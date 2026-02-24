<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\Index(name: 'idx_payments_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_payments_provider_id', columns: ['payment_provider_id'])]
#[ORM\Index(name: 'idx_payments_idempotency', columns: ['idempotency_key'])]
#[ORM\Index(name: 'idx_payments_created_at', columns: ['created_at'])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment:read', 'payment:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read'])]
    private ?User $user = null;

    /**
     * The driver who receives the funds.
     * Nullable at the DB level for migration safety; new payments always require a driver.
     */
    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment:read', 'payment:detail'])]
    private ?Driver $driver = null;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\ManyToMany(targetEntity: Student::class)]
    #[ORM\JoinTable(name: 'payment_student')]
    #[Groups(['payment:read', 'payment:detail'])]
    private Collection $students;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['payment:read', 'payment:list'])]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Currency]
    #[Groups(['payment:read', 'payment:list'])]
    private string $currency = 'USD';

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: PaymentMethod::class)]
    #[Groups(['payment:read', 'payment:list'])]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: PaymentStatus::class)]
    #[Groups(['payment:read', 'payment:list'])]
    private PaymentStatus $status = PaymentStatus::PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['payment:read'])]
    private ?string $paymentProviderId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['payment:read'])]
    private ?string $preferenceId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(length: 36, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?string $idempotencyKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['payment:read', 'payment:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['payment:read', 'payment:list'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['payment:read', 'payment:detail'])]
    private ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['payment:read', 'payment:detail'])]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: [
        'default' => '0.00',
    ])]
    #[Groups(['payment:read', 'payment:detail'])]
    private string $refundedAmount = '0.00';

    /**
     * @var Collection<int, PaymentTransaction>
     */
    #[ORM\OneToMany(targetEntity: PaymentTransaction::class, mappedBy: 'payment', cascade: ['persist'], orphanRemoval: true)]
    private Collection $transactions;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): static
    {
        if (! $this->students->contains($student)) {
            $this->students->add($student);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        $this->students->removeElement($student);

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getPaymentProviderId(): ?string
    {
        return $this->paymentProviderId;
    }

    public function setPaymentProviderId(?string $paymentProviderId): static
    {
        $this->paymentProviderId = $paymentProviderId;

        return $this;
    }

    public function getPreferenceId(): ?string
    {
        return $this->preferenceId;
    }

    public function setPreferenceId(?string $preferenceId): static
    {
        $this->preferenceId = $preferenceId;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRefundedAmount(): string
    {
        return $this->refundedAmount;
    }

    public function setRefundedAmount(string $refundedAmount): static
    {
        $this->refundedAmount = $refundedAmount;

        return $this;
    }

    /**
     * @return Collection<int, PaymentTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(PaymentTransaction $transaction): static
    {
        if (! $this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setPayment($this);
        }

        return $this;
    }

    public function removeTransaction(PaymentTransaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction) && $transaction->getPayment() === $this) {
            $transaction->setPayment(null);
        }

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

    public function isExpired(): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt < new DateTimeImmutable();
    }

    public function isRefundable(): bool
    {
        return $this->status === PaymentStatus::APPROVED && $this->refundedAmount === '0.00';
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->status === PaymentStatus::PARTIALLY_REFUNDED
            || ($this->refundedAmount !== '0.00' && bccomp($this->refundedAmount, (string) $this->amount, 2) < 0);
    }
}
