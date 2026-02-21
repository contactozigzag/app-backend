<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user in the database',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email address')
            ->addArgument('password', InputArgument::REQUIRED, 'User password')
            ->addArgument('firstName', InputArgument::REQUIRED, 'User first name')
            ->addArgument('lastName', InputArgument::REQUIRED, 'User last name')
            ->addArgument('phoneNumber', InputArgument::REQUIRED, 'User phone number')
            ->addArgument('identificationNumber', InputArgument::REQUIRED, 'User identification number (8-10 digits)')
            ->addOption(
                'super-admin',
                's',
                InputOption::VALUE_NONE,
                'Create user with ROLE_SUPER_ADMIN role'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getArgument('firstName');
        $lastName = $input->getArgument('lastName');
        $phoneNumber = $input->getArgument('phoneNumber');
        $identificationNumber = $input->getArgument('identificationNumber');
        $isSuperAdmin = $input->getOption('super-admin');

        // Validate identification number format
        if (! preg_match('/^\d{8,10}$/', (string) $identificationNumber)) {
            $io->error('Identification number must be 8 to 10 digits.');
            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => $email,
            ]);

        if ($existingUser instanceof \App\Entity\User) {
            $io->error(sprintf('User with email "%s" already exists.', $email));
            return Command::FAILURE;
        }

        // Check if identification number already exists
        $existingUserByIdNumber = $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'identificationNumber' => $identificationNumber,
            ]);

        if ($existingUserByIdNumber instanceof \App\Entity\User) {
            $io->error(sprintf('User with identification number "%s" already exists.', $identificationNumber));
            return Command::FAILURE;
        }

        try {
            // Create new user
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPhoneNumber($phoneNumber);
            $user->setIdentificationNumber($identificationNumber);

            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Set roles
            if ($isSuperAdmin) {
                $user->setRoles(['ROLE_SUPER_ADMIN']);
                $io->note('User will be created with ROLE_SUPER_ADMIN');
            }

            // Persist user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success(sprintf(
                'User "%s" created successfully with ID: %d',
                $email,
                $user->getId()
            ));

            // Display user information
            $io->table(
                ['Property', 'Value'],
                [
                    ['Email', $user->getEmail()],
                    ['First Name', $user->getFirstName()],
                    ['Last Name', $user->getLastName()],
                    ['Phone Number', $user->getPhoneNumber()],
                    ['Identification Number', $user->getIdentificationNumber()],
                    ['Roles', implode(', ', $user->getRoles())],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $io->error(sprintf('Failed to create user: %s', $exception->getMessage()));
            return Command::FAILURE;
        }
    }
}
