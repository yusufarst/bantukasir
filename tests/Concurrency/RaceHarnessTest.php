<?php

namespace Tests\Concurrency;

use RuntimeException;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Probe\ProbeCommand;
use Tests\Harness\Race\InjectedFailure;
use Tests\Harness\Race\Worker;
use Tests\Harness\TestDatabase;

/**
 * The self-tests of the race harness (AC-004-05; TEST_STRATEGY TS-20): two
 * commands run in separate processes, each with its own connection, under
 * barrier control — the test lets one stop after its identity insert, after a
 * lock step or before commit, waits until the other waits for a lock that the
 * first holds, and then releases or aborts the first. Every outcome below is
 * the database's own under that interleaving: the lock wait and its order, the
 * wait on a uniqueness entry of an uncommitted identity, the lock timeout of
 * ENVIRONMENTS ENV-31, and a rolled-back command that leaves nothing.
 */
final class RaceHarnessTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (ProbeCommand::createTables($this->migration()) as $table) {
            $this->harnessTable($table);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_a_command_waits_for_the_lock_of_another_and_reads_its_committed_change(): void
    {
        $first = $this->start(['key' => 'first'], ['after_lock:6']);
        $second = $this->start(['key' => 'second']);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:6');
        $second->run()->awaitBlocked($this->observer());

        $first->release();
        $firstOutcome = $first->finish();
        $secondOutcome = $second->finish();

        $this->assertSame(['key' => 'first', 'value' => 1], $firstOutcome->result);
        $this->assertSame(['key' => 'second', 'value' => 2], $secondOutcome->result);
        $this->assertSame(['after_identity', 'after_lock:6', 'after_write:1', 'after_write:2', 'before_commit'], $firstOutcome->points);
        $this->assertSame(2, $this->observer()->scalar('select value from harness_counters where id = 1'));
        $this->assertSame(['first' => 1, 'second' => 2], $this->observer()->table('harness_log')->orderBy('id')->pluck('value', 'key')->all());
    }

    public function test_a_second_identical_identity_waits_for_the_first_and_is_refused_when_it_commits(): void
    {
        $first = $this->start(['key' => 'same'], ['after_identity']);
        $second = $this->start(['key' => 'same']);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_identity');
        $second->run()->awaitBlocked($this->observer());

        $first->release();
        $this->assertTrue($first->finish()->succeeded());

        $outcome = $second->finish();
        $this->assertSame('23505', $outcome->sqlstate());
        $this->assertSame([], $outcome->points);
        $this->assertSame(1, $this->observer()->scalar('select value from harness_counters where id = 1'));
        $this->assertSame(['same'], $this->observer()->table('harness_identities')->pluck('key')->all());
    }

    public function test_a_second_identical_identity_proceeds_when_the_first_rolls_back(): void
    {
        $first = $this->start(['key' => 'same'], ['before_commit']);
        $second = $this->start(['key' => 'same']);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('before_commit');
        $second->run()->awaitBlocked($this->observer());

        $first->abort();
        $firstOutcome = $first->finish();

        $this->assertSame(RuntimeException::class, $firstOutcome->failureClass());
        $this->assertSame('Aborted by the test at before_commit.', $firstOutcome->failureMessage());
        $this->assertSame(['key' => 'same', 'value' => 1], $second->finish()->result);
        $this->assertSame(['same' => 1], $this->observer()->table('harness_log')->pluck('value', 'key')->all());
    }

    public function test_a_lock_wait_beyond_the_lock_timeout_fails_and_writes_nothing(): void
    {
        $first = $this->start(['key' => 'first'], ['after_lock:6']);
        $second = $this->start(['key' => 'second']);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:6');
        $second->run()->awaitBlocked($this->observer());

        $started = microtime(true);
        $outcome = $second->finish();
        $waited = microtime(true) - $started;

        $this->assertSame('55P03', $outcome->sqlstate());
        $this->assertStringContainsString('canceling statement due to lock timeout', (string) $outcome->failureMessage());
        $this->assertGreaterThan(4.0, $waited);
        $this->assertLessThan(Worker::WAIT_SECONDS, $waited);

        $first->release();
        $this->assertTrue($first->finish()->succeeded());
        $this->assertSame(['first'], $this->observer()->table('harness_identities')->pluck('key')->all());
        $this->assertSame(1, $this->observer()->scalar('select value from harness_counters where id = 1'));
    }

    public function test_a_failure_injected_in_a_worker_under_a_barrier_leaves_nothing(): void
    {
        $first = $this->start(['key' => 'first'], ['after_write:1'], 1);
        $first->run()->awaitBarrier('after_write:1');
        $first->release();

        $outcome = $first->finish();
        $this->assertSame(InjectedFailure::class, $outcome->failureClass());
        $this->assertNothingIn(['harness_identities', 'harness_log']);
    }

    public function test_a_worker_refuses_to_run_outside_the_testing_environment_or_the_test_database(): void
    {
        $outside = Worker::start(ProbeCommand::class, ['key' => 'outside'], ['after_identity'], null, ['APP_ENV' => 'production']);
        $local = Worker::start(ProbeCommand::class, ['key' => 'local'], ['after_identity'], null, ['DB_DATABASE' => 'bantukasir']);
        $this->workers[] = $outside;
        $this->workers[] = $local;

        $this->assertSame('The race worker runs only in the testing environment.', $outside->finish()->failureMessage());
        $this->assertSame('The database marker "local" differs from the environment name "test" (ENV-04).', $local->finish()->failureMessage());
        $this->assertNothingIn(['harness_identities', 'harness_log']);
    }

    public function test_a_worker_refuses_a_connection_to_any_database_but_the_test_database_of_its_test(): void
    {
        // A DB_URL in the worker's environment takes its runtime connection
        // to the local database of the same cluster (config/database.php).
        $runtime = (array) config('database.connections.'.TestDatabase::RUNTIME);
        $url = sprintf('pgsql://%s@%s:%s/bantukasir', self::text($runtime['username'] ?? null), self::text($runtime['host'] ?? null), self::text($runtime['port'] ?? null));
        $elsewhere = Worker::start(ProbeCommand::class, ['key' => 'elsewhere'], ['after_identity'], null, ['DB_URL' => $url]);
        $unnamed = Worker::start(ProbeCommand::class, ['key' => 'unnamed'], ['after_identity'], null, [Worker::DATABASE => '']);
        $another = Worker::start(ProbeCommand::class, ['key' => 'another'], ['after_identity'], null, [Worker::DATABASE => '1/1/bantukasir_test']);
        $this->workers[] = $elsewhere;
        $this->workers[] = $unnamed;
        $this->workers[] = $another;

        $this->assertSame('The database marker "local" differs from the environment name "test" (ENV-04).', $elsewhere->finish()->failureMessage());
        $this->assertSame('The race worker needs the identity of the test database ('.Worker::DATABASE.').', $unnamed->finish()->failureMessage());
        $this->assertSame('The runtime connection reaches the database '.TestDatabase::verifiedIdentity().', not the test database 1/1/bantukasir_test (TS-07).', $another->finish()->failureMessage());
        $this->assertNothingIn(['harness_identities', 'harness_log']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     */
    private function start(array $arguments, array $barriers = [], ?int $failAfter = null): Worker
    {
        $worker = Worker::start(ProbeCommand::class, $arguments, $barriers, $failAfter);
        $this->workers[] = $worker;

        return $worker;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : self::fail('The configuration holds '.get_debug_type($value));
    }
}
