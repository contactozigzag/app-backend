<?php

declare(strict_types=1);

namespace App\Notification;

use Psr\Log\LoggerInterface;

abstract class AbstractNotificationProvider implements NotificationInterface
{
    protected bool $enabled = true;

    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    protected function logNotification(string $recipient, string $subject, bool $success): void
    {
        $this->logger->info(sprintf(
            '[%s] Notification %s to %s: %s',
            $this->getName(),
            $success ? 'sent successfully' : 'failed',
            $recipient,
            $subject
        ));
    }

    protected function logError(string $recipient, string $error): void
    {
        $this->logger->error(sprintf(
            '[%s] Failed to send notification to %s: %s',
            $this->getName(),
            $recipient,
            $error
        ));
    }
}
