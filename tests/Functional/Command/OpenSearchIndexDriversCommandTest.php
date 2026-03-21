<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Tests\Factory\DriverFactory;
use App\Tests\Factory\SchoolFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Functional tests for app:opensearch:index-drivers command.
 *
 * These tests require a live OpenSearch instance.
 */
#[Group('opensearch')]
final class OpenSearchIndexDriversCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:opensearch:index-drivers');
        $this->commandTester = new CommandTester($command);
    }

    public function testForceRecreatesIndexAndIndexesAllDrivers(): void
    {
        $school = SchoolFactory::createOne();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $school,
                'roles' => ['ROLE_DRIVER'],
                'firstName' => 'Carlos',
                'lastName' => 'García',
            ]),
            'nickname' => 'Carlitos',
        ])->create();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $school,
                'roles' => ['ROLE_DRIVER'],
                'firstName' => 'Juan',
                'lastName' => 'Pérez',
            ]),
            'nickname' => 'Juancho',
        ])->create();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $school,
                'roles' => ['ROLE_DRIVER'],
            ]),
        ])->create();

        $this->commandTester->execute([
            '--force' => true,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Recreating index', $output);
        $this->assertStringContainsString('Indexed', $output);
    }

    public function testSchoolFilterLimitsIndexing(): void
    {
        $schoolA = SchoolFactory::createOne();
        $schoolB = SchoolFactory::createOne();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $schoolA,
                'roles' => ['ROLE_DRIVER'],
            ]),
        ])->create();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $schoolA,
                'roles' => ['ROLE_DRIVER'],
            ]),
        ])->create();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $schoolB,
                'roles' => ['ROLE_DRIVER'],
            ]),
        ])->create();

        $this->commandTester->execute([
            '--force' => true,
            '--school' => (string) $schoolA->getId(),
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Indexed 2 drivers', $output);
    }

    public function testEmptyDatabaseCompletesSuccessfully(): void
    {
        $this->commandTester->execute([
            '--force' => true,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No drivers found to index', $output);
    }

    public function testOutputIncludesExpectedSummaryFormat(): void
    {
        $school = SchoolFactory::createOne();

        DriverFactory::new()->with([
            'user' => UserFactory::new()->with([
                'school' => $school,
                'roles' => ['ROLE_DRIVER'],
            ]),
        ])->create();

        $this->commandTester->execute([
            '--force' => true,
            '--batch-size' => '50',
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Driver Indexing', $output);
        $this->assertStringContainsString('Batch size: 50', $output);
        $this->assertStringContainsString('Force recreate: Yes', $output);
        $this->assertMatchesRegularExpression('/Indexed \d+ drivers in [\d.]+s \(\d+ errors\)/', $output);
    }
}
