<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The last step of Command contract (D-120; TEST_STRATEGY section 7):
 * scripts/checks/required-jobs.sh against a stub of gh first on the PATH, which
 * answers the run's jobs from files and records its calls. It passes only when
 * each named job's latest attempt finished with success, and waits for one that
 * still runs; it fails on a job that is missing from the run, that ended with
 * any other conclusion, or that still runs at the end of its wait, and on a run
 * whose jobs cannot be read.
 */
final class RequiredJobsTest extends TestCase
{
    use RunsChecks;

    private const string ONE = 'PostgreSQL (1/2)';

    private const string TWO = 'PostgreSQL (2/2)';

    private string $folder;

    private string $answers = '';

    private int $checks = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = self::makeTemporaryDirectory('required-jobs');
        $stub = $this->folder.'/gh';
        file_put_contents($stub, <<<'BASH'
            #!/usr/bin/env bash
            # A stub of gh: it records its arguments, then answers with the next of
            # the numbered files in $STUB_ANSWERS, the last one again once they are
            # used up; with a file "unreadable" there, it fails as gh does when
            # GitHub cannot be read.
            echo "$*" >> "$STUB_ANSWERS/calls"
            if [ -f "$STUB_ANSWERS/unreadable" ]; then
                echo "HTTP 502: Bad Gateway" >&2
                exit 1
            fi
            n=$(( $(cat "$STUB_ANSWERS/count") + 1 ))
            echo "$n" > "$STUB_ANSWERS/count"
            while [ "$n" -gt 1 ] && [ ! -f "$STUB_ANSWERS/$n" ]; do n=$(( n - 1 )); done
            cat "$STUB_ANSWERS/$n"

            BASH);
        chmod($stub, 0755);
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->folder);

        parent::tearDown();
    }

    public function test_it_passes_when_each_part_passed_in_the_run(): void
    {
        $process = $this->check([[
            self::job(1, 10, 'in_progress', '', 'Command contract'),
            self::job(1, 11, 'completed', 'success', self::ONE),
            self::job(1, 12, 'completed', 'success', self::TWO),
            self::job(1, 13, 'completed', 'success', 'Image checks'),
        ]]);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('REQUIRED_JOBS_PASSED "PostgreSQL (1/2)" "PostgreSQL (2/2)"', $process->getOutput());
        $this->assertStringContainsString('api --paginate repos/owner/repository/actions/runs/123/jobs?filter=all&per_page=100 --jq', $this->calls());
    }

    public function test_it_waits_for_a_part_that_still_runs(): void
    {
        $process = $this->check([
            [self::job(1, 11, 'completed', 'success', self::ONE), self::job(1, 12, 'in_progress', '', self::TWO)],
            [self::job(1, 11, 'completed', 'success', self::ONE), self::job(1, 12, 'completed', 'success', self::TWO)],
        ]);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertSame(2, substr_count($this->calls(), 'api --paginate'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherEndings(): array
    {
        return [
            'failure' => ['failure'],
            'skipped' => ['skipped'],
            'cancelled' => ['cancelled'],
            'timed out' => ['timed_out'],
            'neutral' => ['neutral'],
            'action required' => ['action_required'],
            'no conclusion' => [''],
        ];
    }

    #[DataProvider('otherEndings')]
    public function test_a_part_that_ended_without_success_fails_the_step(string $conclusion): void
    {
        $process = $this->check([[self::job(1, 11, 'completed', 'success', self::ONE), self::job(1, 12, 'completed', $conclusion, self::TWO)]]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('REQUIRED_JOBS_FAILED PostgreSQL (2/2) ended with '.$conclusion, $process->getOutput());
    }

    public function test_a_part_missing_from_the_run_fails_the_step(): void
    {
        $process = $this->check([[self::job(1, 10, 'in_progress', '', 'Command contract'), self::job(1, 11, 'completed', 'success', self::ONE)]]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('REQUIRED_JOBS_MISSING PostgreSQL (2/2) is not a job of run 123', $process->getOutput());
    }

    public function test_a_part_still_running_at_the_end_of_the_wait_fails_the_step(): void
    {
        $process = $this->check([[self::job(1, 11, 'completed', 'success', self::ONE), self::job(1, 12, 'queued', '', self::TWO)]], 0);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('REQUIRED_JOBS_TIMEOUT PostgreSQL (2/2), queued, still not finished after 0 seconds', $process->getOutput());
    }

    public function test_the_latest_attempt_of_a_part_decides(): void
    {
        $passedOnRerun = $this->check([[
            self::job(1, 11, 'completed', 'failure', self::ONE),
            self::job(2, 21, 'completed', 'success', self::ONE),
            self::job(1, 12, 'completed', 'success', self::TWO),
        ]]);

        $this->assertSame(0, $passedOnRerun->getExitCode(), $passedOnRerun->getOutput());

        $failedOnRerun = $this->check([[
            self::job(2, 21, 'completed', 'failure', self::ONE),
            self::job(1, 11, 'completed', 'success', self::ONE),
            self::job(1, 12, 'completed', 'success', self::TWO),
        ]]);

        $this->assertSame(1, $failedOnRerun->getExitCode(), $failedOnRerun->getOutput());
        $this->assertStringContainsString('REQUIRED_JOBS_FAILED PostgreSQL (1/2) ended with failure', $failedOnRerun->getOutput());
    }

    public function test_a_run_whose_jobs_cannot_be_read_is_never_a_pass(): void
    {
        $process = $this->check([], 60, true);

        $this->assertSame(2, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('REQUIRED_JOBS_UNREADABLE the jobs of run 123 cannot be read', $process->getOutput());
    }

    public function test_it_needs_a_job_name_and_the_run(): void
    {
        $noName = self::runCheck(['bash', 'scripts/checks/required-jobs.sh'], ['GITHUB_REPOSITORY' => 'owner/repository', 'GITHUB_RUN_ID' => '123']);
        $noRun = self::runCheck(['bash', 'scripts/checks/required-jobs.sh', self::ONE], ['GITHUB_REPOSITORY' => 'owner/repository', 'GITHUB_RUN_ID' => '']);

        foreach ([$noName, $noRun] as $process) {
            $this->assertSame(2, $process->getExitCode(), $process->getOutput());
            $this->assertStringContainsString('REQUIRED_JOBS_USAGE', $process->getOutput());
        }
    }

    /**
     * One line of the run's jobs, as gh prints it with the script's --jq.
     */
    private static function job(int $attempt, int $id, string $status, string $conclusion, string $name): string
    {
        return sprintf('%d|%d|%s|%s|%s', $attempt, $id, $status, $conclusion, $name);
    }

    /**
     * Runs the step for both parts, with the stub's answers in this order.
     *
     * @param  list<list<string>>  $answers  the lines of each answer
     */
    private function check(array $answers, int $wait = 60, bool $unreadable = false): Process
    {
        $this->answers = $this->folder.'/answers-'.(++$this->checks);
        mkdir($this->answers, 0700);
        file_put_contents($this->answers.'/count', "0\n");

        foreach ($answers as $index => $lines) {
            file_put_contents($this->answers.'/'.($index + 1), implode("\n", $lines)."\n");
        }

        if ($unreadable) {
            file_put_contents($this->answers.'/unreadable', '');
        }

        return self::runCheck(['bash', 'scripts/checks/required-jobs.sh', self::ONE, self::TWO], [
            'PATH' => $this->folder.PATH_SEPARATOR.(string) getenv('PATH'),
            'STUB_ANSWERS' => $this->answers,
            'GITHUB_REPOSITORY' => 'owner/repository',
            'GITHUB_RUN_ID' => '123',
            'REQUIRED_JOBS_WAIT' => (string) $wait,
            'REQUIRED_JOBS_POLL' => '0',
        ]);
    }

    private function calls(): string
    {
        return (string) file_get_contents($this->answers.'/calls');
    }
}
