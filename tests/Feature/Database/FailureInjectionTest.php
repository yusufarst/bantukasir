<?php

namespace Tests\Feature\Database;

use App\Database\CommandPoints;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Probe\ProbeCommand;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;
use Tests\Harness\Race\Worker;

/**
 * The failure-injection hook (AC-004-05; TEST_STRATEGY TS-21): a command fails
 * after write step k, for every k, and leaves no row behind, seen by an
 * independent session; without a failure it commits every row. In the test
 * process the hook is bound in the container; in a worker process it is given
 * on the command line.
 */
final class FailureInjectionTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (ProbeCommand::createTables($this->migration()) as $table) {
            $this->harnessTable($table);
        }
    }

    public function test_a_failure_after_each_write_step_leaves_nothing(): void
    {
        foreach ([1, 2] as $step) {
            $points = new FailurePoints($step);
            $this->app->instance(CommandPoints::class, $points);

            try {
                $this->app->make(ProbeCommand::class)->run(['key' => 'probe-'.$step]);
                $this->fail(sprintf('No failure after write step %d.', $step));
            } catch (InjectedFailure $failure) {
                $this->assertSame(sprintf('Failure injected after write step %d (TS-21).', $step), $failure->getMessage());
            }

            $this->assertSame(array_slice(['after_identity', 'after_lock:6', 'after_write:1', 'after_write:2'], 0, 2 + $step), $points->passed);
            $this->assertNothingIn(['harness_identities', 'harness_log']);
            $this->assertSame(0, $this->observer()->scalar('select value from harness_counters where id = 1'));
            $this->assertSame(0, $this->runtime()->transactionLevel());
        }
    }

    public function test_without_a_failure_every_write_commits(): void
    {
        $points = new FailurePoints;
        $this->app->instance(CommandPoints::class, $points);

        $this->assertSame(['key' => 'probe', 'value' => 1], $this->app->make(ProbeCommand::class)->run(['key' => 'probe']));
        $this->assertSame(['after_identity', 'after_lock:6', 'after_write:1', 'after_write:2', 'before_commit'], $points->passed);
        $this->assertSame(1, $this->observer()->scalar('select value from harness_counters where id = 1'));
        $this->assertSame(['probe'], $this->observer()->table('harness_log')->pluck('key')->all());
    }

    public function test_a_worker_fails_after_the_step_it_is_given(): void
    {
        $worker = Worker::start(ProbeCommand::class, ['key' => 'worker'], [], 2)->run();

        try {
            $outcome = $worker->finish();
        } finally {
            $worker->stop();
        }

        $this->assertFalse($outcome->succeeded());
        $this->assertSame(InjectedFailure::class, $outcome->failureClass());
        $this->assertSame(['after_identity', 'after_lock:6', 'after_write:1', 'after_write:2'], $outcome->points);
        $this->assertNothingIn(['harness_identities', 'harness_log']);
        $this->assertSame(0, $this->observer()->scalar('select value from harness_counters where id = 1'));
    }

    public function test_an_unknown_lock_step_is_refused(): void
    {
        $this->expectExceptionMessage('8 is no lock step of TECHNICAL_RULES section 7.1.');

        (new FailurePoints)->afterLock('8');
    }
}
