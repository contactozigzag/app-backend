<?php

namespace App\Notification\Provider;

use App\Notification\AbstractNotificationProvider;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PushNotificationProvider extends AbstractNotificationProvider
{
    public function __construct(
        LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $fcmServerKey = null,
        private readonly ?string $fcmUrl = 'https://fcm.googleapis.com/fcm/send',
    ) {
        parent::__construct($logger);
        // Disable if FCM not configured
        $this->enabled = !empty($this->fcmServerKey);
    }

    public function getName(): string
    {
        return 'push';
    }

    public function send(string $recipient, string $subject, string $message, array $data = []): bool
    {
        if (!$this->isEnabled()) {
            $this->logger->warning('Push notification provider is not configured. Skipping push notification.');
            return false;
        }

        try {
            // Send via Firebase Cloud Messaging (FCM)
            $response = $this->httpClient->request('POST', $this->fcmUrl, [
                'headers' => [
                    'Authorization' => 'key=' . $this->fcmServerKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $recipient, // This should be the device FCM token
                    'notification' => [
                        'title' => $subject,
                        'body' => $message,
                        'icon' => 'bus_icon',
                        'sound' => 'default',
                    ],
                    'data' => array_merge($data, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'timestamp' => time(),
                    ]),
                    'priority' => 'high',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            if ($success) {
                $responseData = $response->toArray();
                $success = isset($responseData['success']) && $responseData['success'] > 0;
            }

            $this->logNotification($recipient, $subject, $success);

            return $success;
        } catch (\Exception $e) {
            $this->logError($recipient, $e->getMessage());
            return false;
        }
    }

    /**
     * Send to multiple devices at once
     */
    public function sendToMultiple(array $recipients, string $subject, string $message, array $data = []): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $results[$recipient] = $this->send($recipient, $subject, $message, $data);
        }

        return $results;
    }
}
