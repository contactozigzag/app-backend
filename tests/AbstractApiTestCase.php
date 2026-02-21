<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base class for API functional tests.
 *
 * ## Kernel-boot ordering rule (Foundry + WebTestCase)
 *
 * Foundry shares the kernel with the test client, so the kernel can only be
 * booted once per test.  **Always call `createApiClient()` first**, then
 * create Foundry factories, then call `loginUser()` to add JWT auth:
 *
 *   $client = $this->createApiClient();          // 1. boot kernel
 *   $user   = UserFactory::createOne([...]);     // 2. persist (uses booted kernel)
 *   $this->loginUser($client, $user);            // 3. attach JWT
 */
abstract class AbstractApiTestCase extends WebTestCase
{
    use ResetDatabase;
    use Factories;

    /**
     * Create an unauthenticated browser client (and boot the kernel).
     * Must be called before any Foundry factory in a test method.
     */
    protected function createApiClient(): KernelBrowser
    {
        return static::createClient();
    }

    /**
     * Attach a JWT for $user to an already-created client.
     *
     * Generates the token via lexik_jwt_authentication.jwt_manager so it is
     * signed with the real private key and accepted by the api firewall.
     */
    protected function loginUser(KernelBrowser $client, User $user): void
    {
        $tokenManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $tokenManager->create($user);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->setServerParameter('CONTENT_TYPE', 'application/json');
        $client->setServerParameter('HTTP_ACCEPT', 'application/json');
    }

    /**
     * POST JSON body and return decoded response array.
     */
    protected function postJson(KernelBrowser $client, string $uri, array $data): array
    {
        $client->request('POST', $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ], json_encode($data));

        return json_decode($client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * GET and return decoded response array.
     */
    protected function getJson(KernelBrowser $client, string $uri): array
    {
        $client->request('GET', $uri, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        return json_decode($client->getResponse()->getContent(), true) ?? [];
    }
}
