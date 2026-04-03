<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushTicketRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity(repositoryClass: PushTicketRepository::class)]
#[ORM\Table(name: 'push_tickets')]
#[ORM\Index(name: 'idx_push_tickets_status_created', columns: ['status', 'created_at'])]
class PushTicket implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $errorDetails = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $checkedAt = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $ticketId,
        #[ORM\Column(length: 255)]
        private string $expoPushToken,
        #[ORM\Column(length: 50)]
        private string $notificationType,
        #[ORM\Column(length: 20)]
        private string $status = 'ok',
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketId(): string
    {
        return $this->ticketId;
    }

    public function getExpoPushToken(): string
    {
        return $this->expoPushToken;
    }

    public function getNotificationType(): string
    {
        return $this->notificationType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getErrorDetails(): ?array
    {
        return $this->errorDetails;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCheckedAt(): ?DateTimeImmutable
    {
        return $this->checkedAt;
    }

    /**
     * @param array<string, mixed> $details
     */
    public function markError(string $message, array $details): void
    {
        $this->status = 'error';
        $this->errorDetails = [
            'message' => $message,
            ...$details,
        ];
        $this->checkedAt = new DateTimeImmutable();
    }

    public function markChecked(): void
    {
        $this->status = 'ok';
        $this->checkedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return 'Ticket #' . ($this->id ?? '?');
    }
}
