<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Message\ProcessWebhookMessage;
use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\PaymentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class WebhookControllerTest extends AbstractApiTestCase
{
    use InteractsWithMessenger;

    private const string SECRET = 'test-webhook-secret';

    // ── signature helper ──────────────────────────────────────────────────────

    private function validSignatureHeaders(string $requestId, string $dataId = '', int $timestamp = 0): array
    {
        $timestamp = $timestamp ?: time();
        $signedData = sprintf('id:%s;request-id:%s;ts:%s;', $dataId, $requestId, $timestamp);
        $signature = hash_hmac('sha256', $signedData, self::SECRET);

        return [
            'HTTP_X_SIGNATURE' => sprintf('ts=%s,v1=%s', $timestamp, $signature),
            'HTTP_X_REQUEST_ID' => $requestId,
        ];
    }

    // ── 401: invalid signature ────────────────────────────────────────────────

    public function testHandleWebhookReturns401OnInvalidSignature(): void
    {
        $client = $this->createApiClient();
        $client->request(Request::METHOD_POST, '/api/webhooks/mercadopago', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => 'ts=1234,v1=badsig',
            'HTTP_X_REQUEST_ID' => 'req-test',
        ], '{}');

        self::assertResponseStatusCodeSame(401);
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $body);
    }

    // ── 200: non-payment event ────────────────────────────────────────────────

    public function testHandleNonPaymentEventReturns200Ignored(): void
    {
        $client = $this->createApiClient();
        $requestId = 'req-non-payment';
        $payload = [
            'type' => 'subscription',
            'action' => 'subscription.created',
            'data' => [],
        ];

        $client->request(
            Request::METHOD_POST,
            '/api/webhooks/mercadopago',
            [],
            [],
            array_merge([
                'CONTENT_TYPE' => 'application/json',
            ], $this->validSignatureHeaders($requestId)),
            json_encode($payload)
        );

        self::assertResponseIsSuccessful();
        $this->assertSame('ignored', json_decode($client->getResponse()->getContent(), true)['status']);
    }

    // ── 200: payment not found ────────────────────────────────────────────────

    public function testHandleWebhookPaymentNotFoundReturns200(): void
    {
        $client = $this->createApiClient();
        $requestId = 'req-not-found';
        $dataId = '99999999';
        $payload = [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => [
                'id' => $dataId,
            ],
        ];

        $client->request(
            Request::METHOD_POST,
            '/api/webhooks/mercadopago?id=' . $dataId,
            [],
            [],
            array_merge([
                'CONTENT_TYPE' => 'application/json',
            ], $this->validSignatureHeaders($requestId, $dataId)),
            json_encode($payload)
        );

        self::assertResponseIsSuccessful();
        $this->assertSame('payment_not_found', json_decode($client->getResponse()->getContent(), true)['status']);
    }

    // ── 200: valid webhook enqueues message ───────────────────────────────────

    public function testHandleValidWebhookEnqueuesProcessWebhookMessage(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();
        PaymentFactory::createOne([
            'user' => $user,
            'paymentProviderId' => 'mp-pay-id-777',
        ]);

        $requestId = 'req-valid';
        $dataId = 'mp-pay-id-777';
        $payload = [
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => [
                'id' => $dataId,
            ],
        ];

        $client->request(
            Request::METHOD_POST,
            '/api/webhooks/mercadopago?id=' . $dataId,
            [],
            [],
            array_merge([
                'CONTENT_TYPE' => 'application/json',
            ], $this->validSignatureHeaders($requestId, $dataId)),
            json_encode($payload)
        );

        self::assertResponseIsSuccessful();
        $this->assertSame('received', json_decode($client->getResponse()->getContent(), true)['status']);

        $this->transport('async_webhooks')
            ->queue()
            ->assertContains(ProcessWebhookMessage::class, 1);
    }

    // ── 400: missing payment ID in payload ────────────────────────────────────

    public function testHandleWebhookMissingPaymentIdReturns400(): void
    {
        $client = $this->createApiClient();
        $requestId = 'req-no-id';
        $payload = [
            'type' => 'payment',
            'action' => 'payment.created',
            'data' => [], // data.id missing
        ];

        $client->request(
            Request::METHOD_POST,
            '/api/webhooks/mercadopago',
            [],
            [],
            array_merge([
                'CONTENT_TYPE' => 'application/json',
            ], $this->validSignatureHeaders($requestId)),
            json_encode($payload)
        );

        self::assertResponseStatusCodeSame(400);
    }
}
