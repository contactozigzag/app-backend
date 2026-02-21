<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Payment;

use App\Service\Payment\WebhookValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

final class WebhookValidatorTest extends TestCase
{
    private const SECRET = 'test-webhook-secret';

    private WebhookValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WebhookValidator(self::SECRET, new NullLogger());
    }

    // ── isValid ───────────────────────────────────────────────────────────────

    public function testIsValidReturnsTrueForCorrectSignature(): void
    {
        $requestId = 'req-abc123';
        $dataId    = '987654321';
        $timestamp = time();

        $signedData = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";
        $signature  = hash_hmac('sha256', $signedData, self::SECRET);

        $request = $this->buildRequest($requestId, $timestamp, $signature, $dataId);

        self::assertTrue($this->validator->isValid($request));
    }

    public function testIsValidReturnsFalseWhenXSignatureHeaderMissing(): void
    {
        $request = Request::create('/api/webhooks/mercadopago', 'POST');
        $request->headers->set('x-request-id', 'req-123');

        self::assertFalse($this->validator->isValid($request));
    }

    public function testIsValidReturnsFalseWhenXRequestIdHeaderMissing(): void
    {
        $request = Request::create('/api/webhooks/mercadopago', 'POST');
        $request->headers->set('x-signature', 'ts=123456,v1=abc');

        self::assertFalse($this->validator->isValid($request));
    }

    public function testIsValidReturnsFalseOnMalformedSignatureHeader(): void
    {
        $request = Request::create('/api/webhooks/mercadopago', 'POST');
        $request->headers->set('x-signature', 'invalid-format-no-equals');
        $request->headers->set('x-request-id', 'req-123');

        self::assertFalse($this->validator->isValid($request));
    }

    public function testIsValidReturnsFalseWhenTimestampTooOld(): void
    {
        $requestId = 'req-old';
        $dataId    = '111';
        $timestamp = time() - 600; // 10 minutes ago (tolerance is 5)

        $signedData = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";
        $signature  = hash_hmac('sha256', $signedData, self::SECRET);

        $request = $this->buildRequest($requestId, $timestamp, $signature, $dataId);

        self::assertFalse($this->validator->isValid($request));
    }

    public function testIsValidReturnsFalseOnSignatureMismatch(): void
    {
        $requestId = 'req-tampered';
        $dataId    = '999';
        $timestamp = time();

        $request = $this->buildRequest($requestId, $timestamp, 'wrong-signature', $dataId);

        self::assertFalse($this->validator->isValid($request));
    }

    public function testIsValidAcceptsTimestampAtEdgeOfTolerance(): void
    {
        $requestId = 'req-edge';
        $dataId    = '123';
        $timestamp = time() - 299; // Just inside the 300 s window

        $signedData = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";
        $signature  = hash_hmac('sha256', $signedData, self::SECRET);

        $request = $this->buildRequest($requestId, $timestamp, $signature, $dataId);

        self::assertTrue($this->validator->isValid($request));
    }

    // ── extractPaymentId ──────────────────────────────────────────────────────

    public function testExtractPaymentIdReturnsIdFromNestedDataKey(): void
    {
        $data = ['data' => ['id' => '112233445']];

        self::assertSame('112233445', $this->validator->extractPaymentId($data));
    }

    public function testExtractPaymentIdReturnsNullWhenMissing(): void
    {
        self::assertNull($this->validator->extractPaymentId([]));
        self::assertNull($this->validator->extractPaymentId(['data' => []]));
    }

    // ── isPaymentEvent ────────────────────────────────────────────────────────

    public function testIsPaymentEventReturnsTrueForPaymentCreated(): void
    {
        $data = ['type' => 'payment', 'action' => 'payment.created'];

        self::assertTrue($this->validator->isPaymentEvent($data));
    }

    public function testIsPaymentEventReturnsTrueForPaymentUpdated(): void
    {
        $data = ['type' => 'payment', 'action' => 'payment.updated'];

        self::assertTrue($this->validator->isPaymentEvent($data));
    }

    public function testIsPaymentEventReturnsFalseForUnknownType(): void
    {
        self::assertFalse($this->validator->isPaymentEvent(['type' => 'subscription', 'action' => 'payment.created']));
        self::assertFalse($this->validator->isPaymentEvent(['type' => 'payment', 'action' => 'merchant.order.closed']));
        self::assertFalse($this->validator->isPaymentEvent([]));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildRequest(
        string $requestId,
        int    $timestamp,
        string $signature,
        string $dataId = '',
    ): Request {
        $url = '/api/webhooks/mercadopago' . ($dataId ? "?id={$dataId}" : '');
        $request = Request::create($url, 'POST');
        $request->headers->set('x-signature', "ts={$timestamp},v1={$signature}");
        $request->headers->set('x-request-id', $requestId);

        return $request;
    }
}
