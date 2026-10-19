<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Negative samples of the Composer validation of the command contract,
 * `composer validate --strict --no-check-all` (TEST_STRATEGY section 6,
 * AC-001-04): copies of composer.json and composer.lock in the test's own
 * temporary directory pass unchanged, and fail once composer.json no longer
 * matches the lock or breaks the schema. The real files pass in the command
 * contract.
 */
final class ComposerValidateTest extends TestCase
{
    use RunsChecks;

    private ?string $project = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = self::makeTemporaryDirectory('composer-validate');
        copy(self::projectRoot().'/composer.json', $this->project.'/composer.json');
        copy(self::projectRoot().'/composer.lock', $this->project.'/composer.lock');
    }

    protected function tearDown(): void
    {
        if ($this->project !== null) {
            self::removeTemporaryDirectory($this->project);
            $this->assertDirectoryDoesNotExist($this->project);
            $this->project = null;
        }

        parent::tearDown();
    }

    public function test_the_copies_pass(): void
    {
        $process = $this->validate();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }

    public function test_a_requirement_that_the_lock_does_not_match_fails(): void
    {
        $this->replaceInManifest('"laravel/framework": "13.37.0"', '"laravel/framework": "13.37.1"');

        $process = $this->validate();

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('The lock file is not up to date', $process->getOutput().$process->getErrorOutput());
    }

    public function test_a_manifest_that_breaks_the_schema_fails(): void
    {
        $this->replaceInManifest('"name": "bantukasir/bantukasir"', '"name": "Bantukasir With Spaces"');

        $process = $this->validate();

        $this->assertNotSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }

    private function replaceInManifest(string $search, string $replace): void
    {
        $manifest = (string) file_get_contents($this->project.'/composer.json');
        $this->assertStringContainsString($search, $manifest);
        file_put_contents($this->project.'/composer.json', str_replace($search, $replace, $manifest));
    }

    private function validate(): Process
    {
        return self::runCheck([
            'composer', 'validate', '--strict', '--no-check-all', '--no-interaction', '--no-ansi',
            '--working-dir='.$this->project,
        ]);
    }
}
