<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Messenger\Stamp\CorrelationIdStamp;
use App\Service\Logging\CorrelationIdService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Propagates the correlation ID through the Messenger pipeline.
 *
 * On dispatch: stamps the envelope with the current correlation ID.
 * On receive (worker): extracts the stamp and sets it on CorrelationIdService
 * so that handler logs carry the original request's correlation ID.
 */
class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CorrelationIdService $correlationIdService,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // On receive (worker side): extract correlation ID from stamp
        if ($envelope->last(ReceivedStamp::class) instanceof StampInterface) {
            $stamp = $envelope->last(CorrelationIdStamp::class);

            if ($stamp instanceof CorrelationIdStamp) {
                $this->correlationIdService->set($stamp->correlationId);
            }

            return $stack->next()->handle($envelope, $stack);
        }

        // On dispatch: add correlation ID stamp if not already present
        if (! $envelope->last(CorrelationIdStamp::class) instanceof StampInterface) {
            $envelope = $envelope->with(
                new CorrelationIdStamp($this->correlationIdService->get()),
            );
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
