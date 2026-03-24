<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Middleware;

use App\Messenger\Middleware\CorrelationIdMiddleware;
use App\Messenger\Stamp\CorrelationIdStamp;
use App\Service\Logging\CorrelationIdService;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class CorrelationIdMiddlewareTest extends TestCase
{
    public function testAddsStampOnDispatch(): void
    {
        $correlationIdService = new CorrelationIdService();
        $correlationIdService->set('disp1234');

        $middleware = new CorrelationIdMiddleware($correlationIdService);

        $envelope = new Envelope(new stdClass());

        $passedEnvelope = null;
        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(
            function (Envelope $envelope) use (&$passedEnvelope): Envelope {
                $passedEnvelope = $envelope;

                return $envelope;
            },
        );

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        $middleware->handle($envelope, $stack);

        $this->assertInstanceOf(Envelope::class, $passedEnvelope);
        $stamp = $passedEnvelope->last(CorrelationIdStamp::class);
        $this->assertInstanceOf(CorrelationIdStamp::class, $stamp);
        $this->assertSame('disp1234', $stamp->correlationId);
    }

    public function testExtractsStampOnReceive(): void
    {
        $correlationIdService = new CorrelationIdService();
        $middleware = new CorrelationIdMiddleware($correlationIdService);

        $envelope = new Envelope(new stdClass(), [
            new ReceivedStamp('async'),
            new CorrelationIdStamp('recv5678'),
        ]);

        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnArgument(0);

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        $middleware->handle($envelope, $stack);

        $this->assertSame('recv5678', $correlationIdService->get());
    }

    public function testDoesNotOverwriteExistingStampOnDispatch(): void
    {
        $correlationIdService = new CorrelationIdService();
        $correlationIdService->set('newid123');

        $middleware = new CorrelationIdMiddleware($correlationIdService);

        $envelope = new Envelope(new stdClass(), [
            new CorrelationIdStamp('existing'),
        ]);

        $passedEnvelope = null;
        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(
            function (Envelope $envelope) use (&$passedEnvelope): Envelope {
                $passedEnvelope = $envelope;

                return $envelope;
            },
        );

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        $middleware->handle($envelope, $stack);

        $this->assertInstanceOf(Envelope::class, $passedEnvelope);
        $stamp = $passedEnvelope->last(CorrelationIdStamp::class);
        $this->assertInstanceOf(CorrelationIdStamp::class, $stamp);
        $this->assertSame('existing', $stamp->correlationId);
    }
}
