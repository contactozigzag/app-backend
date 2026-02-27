<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Notification\NotificationInterface;
use App\Repository\NotificationPreferenceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class NotificationService
{
    /**
     * @var array<string, NotificationInterface>
     */
    private array $providers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        #[AutowireIterator('app.notification_provider')]
        iterable $providers = [],
    ) {
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    public function addProvider(NotificationInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Send notification via all enabled providers based on user preferences
     */
    public function notify(User $user, string $subject, string $message, array $data = [], ?array $channels = null): array
    {
        $results = [];

        // Get user preferences
        $preferences = $this->preferenceRepository->findByUser($user);

        // If no preferences exist, use all channels by default
        if (! $preferences instanceof NotificationPreference) {
            $channels ??= array_keys($this->providers);
        } else {
            // Use preference-based channels if not explicitly specified
            $channels ??= $this->getEnabledChannels($preferences);
        }

        foreach ($channels as $channel) {
            if (! isset($this->providers[$channel])) {
                $this->logger->warning(sprintf('Notification provider "%s" not found', $channel));
                continue;
            }

            $provider = $this->providers[$channel];

            if (! $provider->isEnabled()) {
                $this->logger->info(sprintf('Provider "%s" is disabled, skipping', $channel));
                continue;
            }

            $recipient = $this->getRecipientForChannel($user, $channel);

            if (! $recipient) {
                $this->logger->warning(sprintf(
                    'No recipient information for user %d on channel %s',
                    $user->getId(),
                    $channel
                ));
                continue;
            }

            $results[$channel] = $provider->send($recipient, $subject, $message, $data);
        }

        return $results;
    }

    /**
     * Send notification to multiple users
     */
    public function notifyMultiple(array $users, string $subject, string $message, array $data = [], ?array $channels = null): array
    {
        $results = [];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $results[$user->getId()] = $this->notify($user, $subject, $message, $data, $channels);
        }

        return $results;
    }

    /**
     * Send notification via specific channel only
     */
    public function sendViaChannel(string $channel, string $recipient, string $subject, string $message, array $data = []): bool
    {
        if (! isset($this->providers[$channel])) {
            $this->logger->error(sprintf('Notification provider "%s" not found', $channel));
            return false;
        }

        return $this->providers[$channel]->send($recipient, $subject, $message, $data);
    }

    private function getRecipientForChannel(User $user, string $channel): ?string
    {
        return match ($channel) {
            'email' => $user->getEmail(),
            'sms' => $user->getPhoneNumber(),
            'push' => $this->getUserPushToken(),
            default => null,
        };
    }

    private function getUserPushToken(): ?string
    {
        // In a real implementation, this would fetch the FCM token from a UserDevice entity
        // For now, return null (would need to be implemented with device registration)
        return null;
    }

    private function getEnabledChannels(NotificationPreference $preferences): array
    {
        $channels = [];

        if ($preferences->isEmailEnabled()) {
            $channels[] = 'email';
        }

        if ($preferences->isSmsEnabled()) {
            $channels[] = 'sms';
        }

        if ($preferences->isPushEnabled()) {
            $channels[] = 'push';
        }

        return $channels;
    }

    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }
}
