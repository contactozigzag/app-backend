<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DriverLocationUpdatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class MercurePublishHandler
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DriverLocationUpdatedMessage $message): void
    {
        $startTime = microtime(true);

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
            } catch (Throwable $e) {
                $elapsed = (int) ((microtime(true) - $startTime) * 1000);

                $this->logger->error('Handler failed', [
                    'handler' => self::class,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'topic' => $topic,
                    'correlationId' => $message->correlationId,
                    'duration_ms' => $elapsed,
                ]);
            }
        }

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->info('Handler completed', [
            'handler' => self::class,
            'topics' => $topics,
            'correlationId' => $message->correlationId,
            'duration_ms' => $elapsed,
        ]);
    }
}
