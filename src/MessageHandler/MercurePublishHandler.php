<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DriverLocationUpdatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class MercurePublishHandler
{
    public function __construct(
        private HubInterface    $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DriverLocationUpdatedMessage $message): void
    {
        $payload = json_encode([
            'driverId' => $message->driverId,
            'lat' => $message->latitude,
            'lng' => $message->longitude,
            'speed' => $message->speed,
            'heading' => $message->heading,
            'timestamp' => $message->recordedAt->format('c'),
            'routeId' => $message->activeRouteId,
        ], JSON_THROW_ON_ERROR);

        $topics = [sprintf('/tracking/driver/%d', $message->driverId)];

        if ($message->activeRouteId !== null) {
            $topics[] = sprintf('/tracking/route/%d', $message->activeRouteId);
        }

        foreach ($topics as $topic) {
            $update = new Update($topic, $payload, false);

            try {
                $this->hub->publish($update);
            } catch (\Throwable $e) {
                $this->logger->error('MercurePublishHandler: failed to publish', [
                    'topic' => $topic,
                    'correlationId' => $message->correlationId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('MercurePublishHandler: published', [
            'topics' => $topics,
            'correlationId' => $message->correlationId,
        ]);
    }
}
