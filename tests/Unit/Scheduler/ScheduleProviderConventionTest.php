<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Scheduler\Attribute\AsSchedule;

/**
 * Guards the "one provider, one transport" convention.
 *
 * Every `#[AsSchedule('name')]` the app declares creates a matching
 * `scheduler_<name>` transport that the worker must consume. Adding a new
 * provider without updating the compose `messenger:consume` command produces
 * a schedule that is registered in the container but never fires — a silent
 * production bug. To avoid that class of incident we aggregate all recurring
 * messages into a single `AppScheduleProvider` with `#[AsSchedule('default')]`.
 *
 * If you are failing this test because you added another provider: don't.
 * Add the new `RecurringMessage` to `AppScheduleProvider::getSchedule()`
 * instead. See the docblock on that class for why.
 */
final class ScheduleProviderConventionTest extends TestCase
{
    public function testOnlyOneScheduleProviderWithDefaultName(): void
    {
        $srcRoot = realpath(__DIR__ . '/../../../src');
        $this->assertNotFalse($srcRoot);

        $finder = new Finder()
            ->files()
            ->in($srcRoot)
            ->name('*.php');

        $scheduleNames = [];
        foreach ($finder as $file) {
            $relative = substr((string) $file->getRealPath(), strlen($srcRoot) + 1);
            $class = 'App\\' . str_replace('/', '\\', substr($relative, 0, -4));
            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            foreach ($reflection->getAttributes(AsSchedule::class) as $attribute) {
                $scheduleNames[] = [$class, $attribute->newInstance()->name];
            }
        }

        $this->assertCount(
            1,
            $scheduleNames,
            'src/Scheduler must contain exactly one #[AsSchedule] provider. '
            . 'Add new recurring messages to AppScheduleProvider instead of '
            . 'creating another provider — see the class docblock for why. '
            . 'Found: ' . json_encode($scheduleNames, JSON_THROW_ON_ERROR)
        );

        $this->assertSame(
            'default',
            $scheduleNames[0][1],
            'The sole #[AsSchedule] must be named "default" so it routes to the '
            . '`scheduler_default` transport that the messenger worker consumes.'
        );
    }
}
