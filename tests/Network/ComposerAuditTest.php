<?php

namespace Tests\Network;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Unit\StaticChecks\RunsChecks;

/**
 * Negative sample of the Composer dependency audit (TEST_STRATEGY section 6,
 * THR-14, AC-001-04): a project with the advisory policy of composer.json and
 * a locked package with known advisories fails on the high ones only. The
 * sample project is written at run time in the test's own temporary directory
 * and removed afterwards. The audit reads Packagist's advisories, so the test
 * needs the network, like the audit itself: it is not an L1 test, which runs
 * without network (TS-08), and composer lint runs it, as the PHPUnit suite
 * Network, right after composer audit --locked has passed on the real tree.
 */
final class ComposerAuditTest extends TestCase
{
    use RunsChecks;

    private ?string $project = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = self::makeTemporaryDirectory('composer-audit');
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

    public function test_a_locked_package_with_high_advisories_fails_and_lower_ones_are_ignored(): void
    {
        // guzzlehttp/guzzle 7.4.0 has known high and medium advisories.
        $this->writeProject(['guzzlehttp/guzzle' => '7.4.0']);

        $process = $this->audit();
        $report = $this->report($process);
        $reported = $this->severities($report['advisories'] ?? []);
        $ignored = $this->severities($report['ignored-advisories'] ?? []);

        $this->assertSame(1, $process->getExitCode(), $process->getErrorOutput());
        $this->assertNotSame([], $reported);
        $this->assertSame([], array_diff($reported, ['high', 'critical']));
        $this->assertNotSame([], $ignored);
        $this->assertSame([], array_diff($ignored, ['low', 'medium']));
    }

    public function test_a_project_without_advisories_passes(): void
    {
        $this->writeProject([]);

        $process = $this->audit();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }

    /**
     * @param  array<string, string>  $packages
     */
    private function writeProject(array $packages): void
    {
        $manifest = json_decode((string) file_get_contents(self::projectRoot().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $policy = is_array($manifest) && is_array($manifest['config'] ?? null) ? ($manifest['config']['policy'] ?? null) : null;
        $this->assertIsArray($policy);

        $locked = [];

        foreach ($packages as $name => $version) {
            $locked[] = ['name' => $name, 'version' => $version, 'type' => 'library'];
        }

        $this->writeJson('composer.json', [
            'name' => 'sample/audit',
            'version' => '1.0.0',
            'require' => $packages === [] ? new \stdClass : $packages,
            'config' => ['policy' => $policy],
        ]);
        $this->writeJson('composer.lock', [
            'packages' => $locked,
            'packages-dev' => [],
            'aliases' => [],
            'minimum-stability' => 'stable',
            'stability-flags' => new \stdClass,
            'prefer-stable' => false,
            'prefer-lowest' => false,
            'platform' => new \stdClass,
            'platform-dev' => new \stdClass,
            'plugin-api-version' => '2.9.0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function writeJson(string $name, array $content): void
    {
        file_put_contents($this->project.'/'.$name, json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function audit(): Process
    {
        return self::runCheck([
            'composer', 'audit', '--locked', '--format=json', '--no-interaction', '--no-ansi',
            '--working-dir='.$this->project,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(Process $process): array
    {
        $report = json_decode($process->getOutput(), true);
        $this->assertIsArray($report, "The audit gave no JSON report.\n".$process->getOutput().$process->getErrorOutput());

        /** @var array<string, mixed> $report */
        return $report;
    }

    /**
     * The severities of the advisories of an audit report, per package.
     *
     * @return list<string>
     */
    private function severities(mixed $advisories): array
    {
        $severities = [];

        foreach (is_array($advisories) ? $advisories : [] as $list) {
            foreach (is_array($list) ? $list : [] as $advisory) {
                if (is_array($advisory) && is_string($advisory['severity'] ?? null)) {
                    $severities[] = $advisory['severity'];
                }
            }
        }

        return array_values(array_unique($severities));
    }
}
