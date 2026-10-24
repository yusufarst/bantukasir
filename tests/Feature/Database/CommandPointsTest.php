<?php

namespace Tests\Feature\Database;

use App\Database\CommandPoints;
use App\Database\NoCommandPoints;
use Illuminate\Container\Attributes\Bind;
use ReflectionClass;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;
use Tests\Harness\Race\PipePoints;
use Tests\TestCase;

/**
 * Neither hook exists outside the testing environment (AC-004-05; TEST_STRATEGY
 * TS-20, TS-21): the application binds CommandPoints to NoCommandPoints in
 * every environment, with no condition; the barrier and the failure injection
 * are classes of the test harness under tests/, which only composer.json's
 * autoload-dev maps, so a build installed without dev dependencies — the
 * release image of TASK-012 — cannot load them; and no application code names
 * the harness. The worker that binds the barriers refuses to run outside the
 * testing environment (Tests\Concurrency\RaceHarnessTest).
 */
final class CommandPointsTest extends TestCase
{
    public function test_the_application_binds_the_points_that_do_nothing_in_every_environment(): void
    {
        $bindings = (new ReflectionClass(CommandPoints::class))->getAttributes(Bind::class);

        $this->assertCount(1, $bindings);
        $this->assertSame([NoCommandPoints::class], $bindings[0]->getArguments());
        $this->assertInstanceOf(NoCommandPoints::class, $this->app->make(CommandPoints::class));

        $points = new NoCommandPoints;
        $points->afterIdentity();
        $points->afterLock('6');
        $points->afterWrite(1);
        $points->beforeCommit();
        $this->assertSame([], (new ReflectionClass(NoCommandPoints::class))->getProperties());
    }

    public function test_the_hooks_are_test_classes_that_a_build_without_dev_dependencies_cannot_load(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 16, JSON_THROW_ON_ERROR);

        $this->assertIsArray($composer);
        $this->assertSame(['psr-4' => ['App\\' => 'app/']], $composer['autoload'] ?? null);
        $this->assertSame(['psr-4' => ['Tests\\' => 'tests/']], $composer['autoload-dev'] ?? null);

        foreach ([FailurePoints::class, PipePoints::class, InjectedFailure::class] as $class) {
            $this->assertStringStartsWith(base_path('tests').DIRECTORY_SEPARATOR, (string) (new ReflectionClass($class))->getFileName());
        }

        $this->assertStringStartsWith(base_path('app').DIRECTORY_SEPARATOR, (string) (new ReflectionClass(NoCommandPoints::class))->getFileName());
    }

    public function test_no_application_code_names_the_test_harness(): void
    {
        // Every folder of the release build's PHP outside vendor/ and tests/.
        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'lang', 'public'] as $folder) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($folder), \FilesystemIterator::SKIP_DOTS));
            $count = 0;

            foreach ($files as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php' && ! str_contains($file->getPathname(), 'bootstrap'.DIRECTORY_SEPARATOR.'cache')) {
                    $count++;
                    $this->assertDoesNotMatchRegularExpression('/\bTests\\\\/', (string) file_get_contents($file->getPathname()), $file->getPathname());
                }
            }

            $this->assertGreaterThan(0, $count, $folder);
        }
    }
}
