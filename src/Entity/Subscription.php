<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\BillingCycle;
use App\Enum\SubscriptionStatus;
use App\Repository\SubscriptionRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscription')]
#[ORM\Index(name: 'idx_subscriptions_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_subscriptions_next_billing', columns: ['next_billing_date'])]
#[ApiResource(operations: [
    new Get(normalizationContext: [
        'groups' => ['subscription:read'],
    ]),
    new GetCollection(normalizationContext: [
        'groups' => ['subscription:read', 'subscription:list'],
    ]),
    new Post(denormalizationContext: [
        'groups' => ['subscription:write'],
    ]),
    new Patch(denormalizationContext: [
        'groups' => ['subscription:update'],
    ]),
], paginationItemsPerPage: 30, security: 'is_granted("ROLE_USER")')]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscription:read', 'subscription:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['subscription:read'])]
    private ?User $user = null;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\ManyToMany(targetEntity: Student::class)]
    #[ORM\JoinTable(name: 'subscription_student')]
    #[Groups(['subscription:read', 'subscription:write'])]
    private Collection $students;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['subscription:read', 'subscription:list', 'subscription:write'])]
    private ?string $planType = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: SubscriptionStatus::class)]
    #[Groups(['subscription:read', 'subscription:list'])]
    private SubscriptionStatus $status = SubscriptionStatus::ACTIVE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['subscription:read', 'subscription:list', 'subscription:write'])]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    #[Assert\Currency]
    #[Groups(['subscription:read', 'subscription:list', 'subscription:write'])]
    private string $currency = 'USD';

    #[ORM\Column(type: Types::STRING, length: 50, enumType: BillingCycle::class)]
    #[Assert\NotBlank]
    #[Groups(['subscription:read', 'subscription:list', 'subscription:write'])]
    private ?BillingCycle $billingCycle = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['subscription:read', 'subscription:list'])]
    private ?DateTimeImmutable $nextBillingDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['subscription:read'])]
    private ?string $mercadoPagoSubscriptionId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['subscription:read', 'subscription:list'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['subscription:read'])]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['subscription:read', 'subscription:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    #[Groups(['subscription:read'])]
    private int $failedPaymentCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastPaymentAttemptAt = null;

    public function __construct()
    {
        $this->students = new ArrayCollection();
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

    public function getPlanType(): ?string
    {
        return $this->planType;
    }

    public function setPlanType(string $planType): static
    {
        $this->planType = $planType;

        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();

        if ($status === SubscriptionStatus::CANCELLED && ! $this->cancelledAt instanceof DateTimeImmutable) {
            $this->cancelledAt = new DateTimeImmutable();
        }

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

    public function getBillingCycle(): ?BillingCycle
    {
        return $this->billingCycle;
    }

    public function setBillingCycle(BillingCycle $billingCycle): static
    {
        $this->billingCycle = $billingCycle;

        return $this;
    }

    public function getNextBillingDate(): ?DateTimeImmutable
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(DateTimeImmutable $nextBillingDate): static
    {
        $this->nextBillingDate = $nextBillingDate;

        return $this;
    }

    public function getMercadoPagoSubscriptionId(): ?string
    {
        return $this->mercadoPagoSubscriptionId;
    }

    public function setMercadoPagoSubscriptionId(?string $mercadoPagoSubscriptionId): static
    {
        $this->mercadoPagoSubscriptionId = $mercadoPagoSubscriptionId;

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

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
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

    public function getFailedPaymentCount(): int
    {
        return $this->failedPaymentCount;
    }

    public function incrementFailedPaymentCount(): static
    {
        $this->failedPaymentCount++;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function resetFailedPaymentCount(): static
    {
        $this->failedPaymentCount = 0;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getLastPaymentAttemptAt(): ?DateTimeImmutable
    {
        return $this->lastPaymentAttemptAt;
    }

    public function setLastPaymentAttemptAt(DateTimeImmutable $lastPaymentAttemptAt): static
    {
        $this->lastPaymentAttemptAt = $lastPaymentAttemptAt;

        return $this;
    }

    public function calculateNextBillingDate(): DateTimeImmutable
    {
        if (! $this->nextBillingDate instanceof DateTimeImmutable) {
            $this->nextBillingDate = new DateTimeImmutable();
        }

        $days = $this->billingCycle?->getDays() ?? 30;

        return $this->nextBillingDate->modify(sprintf('+%d days', $days));
    }

    public function isDueForBilling(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE
            && $this->nextBillingDate instanceof DateTimeImmutable
            && $this->nextBillingDate <= new DateTimeImmutable();
    }

    public function shouldRetryPayment(): bool
    {
        return $this->status === SubscriptionStatus::PAYMENT_FAILED
            && $this->failedPaymentCount < 3
            && $this->lastPaymentAttemptAt instanceof DateTimeImmutable
            && $this->lastPaymentAttemptAt < new DateTimeImmutable('-24 hours');
    }
}
