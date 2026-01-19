<?php

namespace App\Notification\Provider;

use App\Notification\AbstractNotificationProvider;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsNotificationProvider extends AbstractNotificationProvider
{
    public function __construct(
        LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiUrl = null,
        private readonly ?string $fromNumber = null,
    ) {
        parent::__construct($logger);
        // Disable if credentials not configured
        $this->enabled = !empty($this->apiKey) && !empty($this->apiUrl);
    }

    public function getName(): string
    {
        return 'sms';
    }

    public function send(string $recipient, string $subject, string $message, array $data = []): bool
    {
        if (!$this->isEnabled()) {
            $this->logger->warning('SMS provider is not configured. Skipping SMS notification.');
            return false;
        }

        try {
            // Format the message to include subject
            $fullMessage = $subject . "\n\n" . $message;

            // Truncate to SMS limit (160 characters for single SMS)
            if (strlen($fullMessage) > 160) {
                $fullMessage = substr($fullMessage, 0, 157) . '...';
            }

            // This is a generic implementation - in production, use a service like Twilio, SNS, etc.
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $recipient,
                    'from' => $this->fromNumber,
                    'message' => $fullMessage,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            $this->logNotification($recipient, $subject, $success);

            return $success;
        } catch (\Exception $e) {
            $this->logError($recipient, $e->getMessage());
            return false;
        }
    }
}
