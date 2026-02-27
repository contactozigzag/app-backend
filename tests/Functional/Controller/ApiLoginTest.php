<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AbstractApiTestCase;
use App\Tests\Factory\UserFactory;

final class ApiLoginTest extends AbstractApiTestCase
{
    // ── POST /api/login ───────────────────────────────────────────────────────

    public function testLoginWithValidCredentialsReturnsTokenAndRefreshToken(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();

        $data = $this->postJson($client, '/api/login', [
            'email' => $user->getEmail(),
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('refresh_token', $data);
        self::assertArrayHasKey('refresh_token_expiration', $data);
        self::assertIsString($data['token']);
        self::assertIsString($data['refresh_token']);
        self::assertIsInt($data['refresh_token_expiration']);
    }

    public function testLoginWithWrongPasswordReturnsUnauthorized(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();

        $this->postJson($client, '/api/login', [
            'email' => $user->getEmail(),
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentEmailReturnsUnauthorized(): void
    {
        $client = $this->createApiClient();

        UserFactory::createOne();

        $this->postJson($client, '/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithMissingCredentialsReturnsBadRequest(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/login', []);

        self::assertResponseStatusCodeSame(400);
    }

    // ── POST /api/token/refresh ───────────────────────────────────────────────

    public function testRefreshTokenWithValidTokenReturnsNewTokenPair(): void
    {
        $client = $this->createApiClient();
        $user = UserFactory::createOne();

        $loginData = $this->postJson($client, '/api/login', [
            'email' => $user->getEmail(),
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('refresh_token', $loginData);

        $refreshData = $this->postJson($client, '/api/token/refresh', [
            'refresh_token' => $loginData['refresh_token'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $refreshData);
        self::assertArrayHasKey('refresh_token', $refreshData);
        self::assertArrayHasKey('refresh_token_expiration', $refreshData);
        self::assertIsString($refreshData['token']);
        self::assertIsString($refreshData['refresh_token']);
        // Single-use rotation: the new refresh_token must differ from the consumed one
        self::assertNotSame($loginData['refresh_token'], $refreshData['refresh_token']);
    }

    public function testRefreshTokenWithInvalidTokenReturnsUnauthorized(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/token/refresh', [
            'refresh_token' => 'invalid-or-expired-token',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithMissingTokenReturnsUnauthorized(): void
    {
        $client = $this->createApiClient();

        $this->postJson($client, '/api/token/refresh', []);

        self::assertResponseStatusCodeSame(401);
    }
}
