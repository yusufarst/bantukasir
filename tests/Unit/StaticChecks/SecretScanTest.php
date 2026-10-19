<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Process\Process;

/**
 * Negative samples of the secret scan (TEST_STRATEGY section 6, AC-001-04,
 * EXC-63). Each test builds a throwaway Git repository in its own temporary
 * directory outside the tracked tree, generates the secret-looking value there
 * at run time — no such value is ever written in the repository — and removes
 * that directory afterwards, also when the test fails. The real tree and the
 * commits a push would add pass in composer lint.
 */
final class SecretScanTest extends TestCase
{
    use RunsChecks;

    private ?string $repository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::makeTemporaryDirectory('secret-scan');
        $this->git('init', '--quiet', '--initial-branch=main');
        $this->commitFile('README.txt', "A throwaway repository of the secret-scan test.\n");
    }

    protected function tearDown(): void
    {
        if ($this->repository !== null) {
            self::removeTemporaryDirectory($this->repository);
            $this->assertDirectoryDoesNotExist($this->repository);
            $this->repository = null;
        }

        parent::tearDown();
    }

    public function test_a_clean_repository_passes(): void
    {
        $process = $this->scan();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }

    public function test_a_secret_in_an_untracked_file_of_the_tree_fails(): void
    {
        file_put_contents($this->path('settings.ini'), $this->secretLine());

        $process = $this->scan();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('leaks found', $process->getErrorOutput());
        $this->assertStringNotContainsString($this->generatedKey(), $process->getOutput().$process->getErrorOutput());
    }

    public function test_a_secret_in_the_commits_to_push_fails_after_its_removal_from_the_tree(): void
    {
        $this->commitFile('settings.ini', $this->secretLine());
        unlink($this->path('settings.ini'));
        $this->git('commit', '--quiet', '--all', '--message=Remove the setting');

        $process = $this->scan();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('leaks found', $process->getErrorOutput());
        $this->assertStringNotContainsString($this->generatedKey(), $process->getOutput().$process->getErrorOutput());
    }

    private function scan(): Process
    {
        return self::runCheck(['bash', 'scripts/checks/secret-scan.sh', (string) $this->repository]);
    }

    /**
     * An access-key identifier in the format that Gitleaks' rule
     * aws-access-token detects, made of a fixed prefix and sixteen distinct
     * characters drawn with a seeded generator, so the test is deterministic
     * (TS-08).
     */
    private function generatedKey(): string
    {
        $alphabet = (new Randomizer(new Mt19937(20261019)))->shuffleBytes('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567');

        return 'AKIA'.substr($alphabet, 0, 16);
    }

    private function secretLine(): string
    {
        return 'aws_access_key_id = '.$this->generatedKey()."\n";
    }

    private function commitFile(string $name, string $content): void
    {
        file_put_contents($this->path($name), $content);
        $this->git('add', '--', $name);
        $this->git('commit', '--quiet', '--message=Add '.$name);
    }

    private function git(string ...$arguments): void
    {
        $process = self::runCheck([
            'git', '-C', (string) $this->repository,
            '-c', 'user.name=Sample', '-c', 'user.email=sample@example.invalid', '-c', 'commit.gpgsign=false',
            ...array_values($arguments),
        ]);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    private function path(string $name): string
    {
        return $this->repository.'/'.$name;
    }
}
