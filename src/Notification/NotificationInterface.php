<?php

namespace App\Notification;

interface NotificationInterface
{
    /**
     * Send a notification
     *
     * @param string $recipient The recipient identifier (email, phone, device token)
     * @param string $subject The notification subject/title
     * @param string $message The notification message/body
     * @param array $data Additional data to include
     * @return bool Success status
     */
    public function send(string $recipient, string $subject, string $message, array $data = []): bool;

    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Check if the provider is enabled
     */
    public function isEnabled(): bool;
}
