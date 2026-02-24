<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use Override;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'phoneNumber' => self::faker()->numerify('##########'),
            'identificationNumber' => self::faker()->unique()->numerify('##########'),
            'roles' => ['ROLE_PARENT'],
            'password' => 'password',
        ];
    }

    #[Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            if ($user->getPassword() && ! str_starts_with($user->getPassword(), '$argon')) {
                $user->setPassword(
                    $this->passwordHasher->hashPassword($user, $user->getPassword())
                );
            }
        });
    }
}
