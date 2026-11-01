<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The release workflows and their scripts (AC-014-01; ADR-011 item 5; RELEASE_PROCESS
 * sections 3 and 4; TEST_STRATEGY section 6; TASK-014 scope items 1 and 2): the real
 * workflows pass the check; each is then changed in one way at a time, in a temporary
 * folder outside the tree, and the check refuses it. The run itself is the dry run of
 * .github/workflows/release-dry-run.yml.
 */
final class ReleaseWorkflowTest extends TestCase
{
    use RunsChecks;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = self::makeTemporaryDirectory('release-workflow');
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->folder);

        parent::tearDown();
    }

    public function test_the_real_workflows_pass(): void
    {
        $process = self::runCheck([PHP_BINARY, 'scripts/checks/release-workflow.php']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function changedReleases(): array
    {
        $tags = '            - "v[0-9]+.[0-9]+.[0-9]+-rc.[0-9]+"';

        return [
            'a push of a branch' => [$tags, $tags."\n        branches:\n            - develop", 'something other than a release or candidate tag'],
            'a pull request' => ['    push:', "    pull_request:\n    push:", 'something other than a release or candidate tag'],
            'a manual run' => ['    push:', "    workflow_dispatch:\n    push:", 'something other than a release or candidate tag'],
            'any tag' => ['"v[0-9]+.[0-9]+.[0-9]+"', '"*"', 'something other than a release or candidate tag'],
            'another secret' => ['secrets.GITHUB_TOKEN', 'secrets.STAGING_KEY', 'it names the secret STAGING_KEY'],
            'an environment' => ['        runs-on: ubuntu-24.04', "        environment: production\n        runs-on: ubuntu-24.04", 'it names an environment'],
            'no check of the repository' => ["github.repository == 'yusufarst/bantukasir' && ", '', 'a fork could publish'],
            'a permission for the whole workflow' => ['permissions: {}', "permissions:\n    packages: write", 'to the whole workflow'],
            'another write permission' => ['contents: read', 'contents: write', 'the write permission contents'],
            'an action by its tag' => ['@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1', '@v7', 'not pinned by its full commit'],
            'no commit for the label' => ['RELEASE_COMMIT: ${{ github.sha }}', 'RELEASE_COMMIT: ""', 'it does not pass RELEASE_COMMIT'],
        ];
    }

    #[DataProvider('changedReleases')]
    public function test_a_release_workflow_that_breaks_a_rule_is_refused(string $search, string $replace, string $expected): void
    {
        $file = $this->changed('.github/workflows/release.yml', $search, $replace);
        $process = self::runCheck([PHP_BINARY, 'scripts/checks/release-workflow.php', '--release='.$file]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString($expected, $process->getOutput());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function changedDryRuns(): array
    {
        $step = '            - name: Show the report';

        return [
            'a write permission' => ['    contents: read', '    packages: write', 'a write permission'],
            'a secret' => ['RELEASE_TAG: v0.0.0-rc.1', 'RELEASE_TAG: ${{ secrets.GITHUB_TOKEN }}', 'a secret'],
            'a login' => [$step, "            - run: docker login ghcr.io\n".$step, 'a login to a registry'],
            'a push' => [$step, "            - run: docker push ghcr.io/image\n".$step, 'a push to a registry'],
            'the publishing script' => [$step, "            - run: bash scripts/release/publish.sh\n".$step, 'the publishing script'],
            'no image checks' => ['scripts/checks/image-checks.sh', 'scripts/checks/other.sh', 'it does not run the image checks'],
        ];
    }

    #[DataProvider('changedDryRuns')]
    public function test_a_dry_run_that_could_publish_is_refused(string $search, string $replace, string $expected): void
    {
        $file = $this->changed('.github/workflows/release-dry-run.yml', $search, $replace);
        $process = self::runCheck([PHP_BINARY, 'scripts/checks/release-workflow.php', '--dry-run='.$file]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString($expected, $process->getOutput());
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function tags(): array
    {
        return [
            'a release' => ['v1.0.0', 0, 'TAG_ACCEPTED release'],
            'a later release' => ['v12.30.4', 0, 'TAG_ACCEPTED release'],
            'a candidate' => ['v1.0.0-rc.1', 0, 'TAG_ACCEPTED candidate'],
            'the dry run\'s candidate' => ['v0.0.0-rc.1', 0, 'TAG_ACCEPTED candidate'],
            'no v' => ['1.0.0', 1, 'TAG_REFUSED'],
            'two numbers' => ['v1.0', 1, 'TAG_REFUSED'],
            'a leading zero' => ['v01.0.0', 1, 'TAG_REFUSED'],
            'another pre-release' => ['v1.0.0-beta.1', 1, 'TAG_REFUSED'],
            'candidate zero' => ['v1.0.0-rc.0', 1, 'TAG_REFUSED'],
            'a suffix' => ['v1.0.0-rc.1x', 1, 'TAG_REFUSED'],
            'a branch' => ['develop', 1, 'TAG_REFUSED'],
            'nothing' => ['', 1, 'TAG_REFUSED'],
        ];
    }

    #[DataProvider('tags')]
    public function test_the_tag_check_takes_only_a_release_or_a_candidate(string $tag, int $exit, string $expected): void
    {
        $process = self::runCheck(['bash', 'scripts/release/check-tag.sh', $tag]);

        $this->assertSame($exit, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString($expected, $process->getOutput());
    }

    public function test_the_integrity_report_is_filled_from_the_template_and_leaves_what_a_person_knows(): void
    {
        $log = $this->folder.'/image-checks.log';
        file_put_contents($log, "PASS something\nDatabase: 2026-11-01T00:00:00Z\nCritical: 9\nHigh: 103\nImage checks: 280 passed, 0 failed\nScan gate tests: 50 passed, 0 failed\n");
        $output = $this->folder.'/report.md';

        $process = self::runCheck(['bash', 'scripts/release/integrity-report.sh', $log, $output], [
            'RELEASE_VERSION' => 'v1.2.3-rc.4',
            'RELEASE_COMMIT' => str_repeat('a', 40),
            'RELEASE_TAG' => 'v1.2.3-rc.4',
            'RELEASE_DIGEST' => 'sha256:'.str_repeat('b', 64),
            'RELEASE_RUN' => 'https://github.test/run/1',
        ]);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $report = (string) file_get_contents($output);
        $this->assertStringNotContainsString('{{', $report);

        foreach (['# Release integrity report — v1.2.3-rc.4', str_repeat('a', 40), 'sha256:'.str_repeat('b', 64), 'https://github.test/run/1', 'composer.lock', 'package-lock.json', 'php:8.5.12-fpm-trixie@sha256:', '- Image checks: 280 passed, 0 failed', '- Scan gate tests: 50 passed, 0 failed', '- Critical: 9', '2026_10_31_000001_create_backup_runs_table`: additive', 'TO_BE_COMPLETED'] as $expected) {
            $this->assertStringContainsString($expected, $report);
        }
    }

    public function test_the_templates_hold_what_their_sections_ask_for(): void
    {
        $record = (string) file_get_contents(self::projectRoot().'/docs/10-release/templates/RELEASE_RECORD.md');

        foreach (['Version', 'Commit', 'Tag', 'Image digest', 'authorization', 'window', 'pre-release backup', 'Migrations', 'RB-21', 'operator, by role only', 'Incidents and rollbacks', 'Integrity report'] as $field) {
            $this->assertStringContainsStringIgnoringCase($field, $record);
        }

        $report = (string) file_get_contents(self::projectRoot().'/docs/10-release/templates/RELEASE_INTEGRITY_REPORT.md');

        foreach (['Version', 'Commit', 'Tag', 'Image digest', 'Base images', 'Lockfiles', 'CI run', 'suites', 'Migrations', 'rehearsal', 'Scan results', 'publication guard', 'compatibility of the previous image', 'Known issues', 'cost inventory'] as $field) {
            $this->assertStringContainsStringIgnoringCase($field, $report);
        }
    }

    private function changed(string $workflow, string $search, string $replace): string
    {
        $original = (string) file_get_contents(self::projectRoot().'/'.$workflow);
        $this->assertStringContainsString($search, $original);

        $file = $this->folder.'/'.basename($workflow);
        file_put_contents($file, str_replace($search, $replace, $original));

        return $file;
    }
}
