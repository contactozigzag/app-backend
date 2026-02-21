<?php

declare(strict_types=1);

namespace App\Notification\Provider;

use App\Notification\AbstractNotificationProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('app.notification_provider')]
class SmsNotificationProvider extends AbstractNotificationProvider
{
    public function __construct(
        LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'default::SMS_API_KEY')]
        private readonly ?string $apiKey = null,
        #[Autowire(env: 'default::SMS_API_URL')]
        private readonly ?string $apiUrl = null,
        #[Autowire(env: 'default::SMS_FROM_NUMBER')]
        private readonly ?string $fromNumber = null,
    ) {
        parent::__construct($logger);
        // Disable if credentials not configured
        $this->enabled = ! in_array($this->apiKey, [null, '', '0'], true) && ! in_array($this->apiUrl, [null, '', '0'], true);
    }

    public function getName(): string
    {
        return 'sms';
    }

    public function send(string $recipient, string $subject, string $message, array $data = []): bool
    {
        if (! $this->isEnabled()) {
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
            $response = $this->httpClient->request('POST', (string) $this->apiUrl, [
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
        } catch (\Exception $exception) {
            $this->logError($recipient, $exception->getMessage());
            return false;
        }
    }
}
