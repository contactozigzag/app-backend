<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notification_preferences')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.user == user"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_USER') and object.user == user"),
    ],
    normalizationContext: [
        'groups' => ['notification_pref:read'],
    ],
    denormalizationContext: [
        'groups' => ['notification_pref:write'],
    ]
)]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification_pref:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $emailEnabled = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $smsEnabled = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $pushEnabled = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $notifyOnArriving = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $notifyOnPickup = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $notifyOnDropoff = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $notifyOnRouteStart = false;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $notifyOnDelay = true;

    #[ORM\Column]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private bool $notifyOnCancellation = true;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification_pref:read', 'notification_pref:write'])]
    private ?int $arrivalNotificationMinutes = 5;

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

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;
        return $this;
    }

    public function isSmsEnabled(): bool
    {
        return $this->smsEnabled;
    }

    public function setSmsEnabled(bool $smsEnabled): static
    {
        $this->smsEnabled = $smsEnabled;
        return $this;
    }

    public function isPushEnabled(): bool
    {
        return $this->pushEnabled;
    }

    public function setPushEnabled(bool $pushEnabled): static
    {
        $this->pushEnabled = $pushEnabled;
        return $this;
    }

    public function isNotifyOnArriving(): bool
    {
        return $this->notifyOnArriving;
    }

    public function setNotifyOnArriving(bool $notifyOnArriving): static
    {
        $this->notifyOnArriving = $notifyOnArriving;
        return $this;
    }

    public function isNotifyOnPickup(): bool
    {
        return $this->notifyOnPickup;
    }

    public function setNotifyOnPickup(bool $notifyOnPickup): static
    {
        $this->notifyOnPickup = $notifyOnPickup;
        return $this;
    }

    public function isNotifyOnDropoff(): bool
    {
        return $this->notifyOnDropoff;
    }

    public function setNotifyOnDropoff(bool $notifyOnDropoff): static
    {
        $this->notifyOnDropoff = $notifyOnDropoff;
        return $this;
    }

    public function isNotifyOnRouteStart(): bool
    {
        return $this->notifyOnRouteStart;
    }

    public function setNotifyOnRouteStart(bool $notifyOnRouteStart): static
    {
        $this->notifyOnRouteStart = $notifyOnRouteStart;
        return $this;
    }

    public function isNotifyOnDelay(): bool
    {
        return $this->notifyOnDelay;
    }

    public function setNotifyOnDelay(bool $notifyOnDelay): static
    {
        $this->notifyOnDelay = $notifyOnDelay;
        return $this;
    }

    public function isNotifyOnCancellation(): bool
    {
        return $this->notifyOnCancellation;
    }

    public function setNotifyOnCancellation(bool $notifyOnCancellation): static
    {
        $this->notifyOnCancellation = $notifyOnCancellation;
        return $this;
    }

    public function getArrivalNotificationMinutes(): ?int
    {
        return $this->arrivalNotificationMinutes;
    }

    public function setArrivalNotificationMinutes(?int $arrivalNotificationMinutes): static
    {
        $this->arrivalNotificationMinutes = $arrivalNotificationMinutes;
        return $this;
    }
}
