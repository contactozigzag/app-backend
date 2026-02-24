<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use DateTimeImmutable;
use App\Entity\Driver;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Driver>
 */
final class DriverFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Driver::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new()->with([
                'roles' => ['ROLE_DRIVER'],
            ]),
            'nickname' => self::faker()->unique()->userName(),
            'licenseNumber' => self::faker()->bothify('??-####-??'),
        ];
    }

    /**
     * State: driver has completed the Mercado Pago OAuth flow.
     * Tokens are stored as valid (but fake) encrypted blobs via TokenEncryptor.
     */
    public function withMpAuthorized(
        string $mpAccessToken = 'enc-access-token',
        string $mpRefreshToken = 'enc-refresh-token',
        string $mpAccountId = '987654321',
    ): static {
        return $this->with([
            'mpAccessToken' => $mpAccessToken,
            'mpRefreshToken' => $mpRefreshToken,
            'mpAccountId' => $mpAccountId,
            'mpTokenExpiresAt' => new DateTimeImmutable('+90 days'),
        ]);
    }
}
