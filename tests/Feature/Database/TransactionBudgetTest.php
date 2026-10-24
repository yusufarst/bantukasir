<?php

namespace Tests\Feature\Database;

use App\Database\Identifier;
use Closure;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Throwable;

/**
 * How the time limits of TECHNICAL_RULES section 9 are enforced on PostgreSQL
 * 18.6 (AC-004-08; config/database.php, ENVIRONMENTS ENV-31), with short
 * values so that each limit is reached within the test; the runtime role's
 * own values are SetupTest's.
 *
 * The transaction budget is PostgreSQL's transaction_timeout: the value in
 * force when a transaction begins bounds it from its start; past it the server
 * ends the session (its SQLSTATE 25P04) and nothing of the transaction is
 * committed. Inside a running transaction a new non-zero value replaces no
 * running timer, and zero stops it, so a command sets its budget immediately
 * before BEGIN and nothing sets it inside. A statement limit set with SET LOCAL
 * applies to the transaction's later statements.
 *
 * pdo_pgsql reports the end of a session as a general error, HY000, whose
 * message names the reason; and the framework's transaction() then tries to
 * roll back on the dead connection and throws that failure — "no connection to
 * the server" — in place of the statement's, so the reason is seen only by
 * code inside the transaction (TASK-004 evidence, finding F-4).
 */
final class TransactionBudgetTest extends DatabaseTestCase
{
    private ?Throwable $cause = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->harnessTable('harness_budget');
        $this->migration()->statement('CREATE TABLE harness_budget (id bigint PRIMARY KEY)');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_budget TO '.Identifier::quote($this->roles()->runtime));
    }

    public function test_a_transaction_past_its_budget_is_ended_with_its_session_and_leaves_nothing(): void
    {
        $session = $this->observer();
        $session->statement("set transaction_timeout = '1s'");

        [$cause, $surfaced] = $this->ended($session, function (Connection $c): void {
            $c->insert('insert into harness_budget values (1)');
            $c->select('select pg_catalog.pg_sleep(0.6)');
            $c->select('select pg_catalog.pg_sleep(0.6)');
            $c->insert('insert into harness_budget values (2)');
        });

        $this->assertSame('HY000', (string) $cause->getCode(), 'pdo_pgsql reports the end of a session as a general error');
        $this->assertStringContainsString('terminating connection due to transaction timeout', $cause->getMessage());
        $this->assertStringContainsString('no connection to the server', $surfaced->getMessage());
        $this->assertSame(0, $this->observer()->table('harness_budget')->count());
    }

    public function test_a_transaction_within_its_budget_commits_and_the_default_returns(): void
    {
        $session = $this->observer();
        $session->statement("set transaction_timeout = '2s'");
        $session->transaction(function (Connection $c): void {
            $c->insert('insert into harness_budget values (1)');
            $c->select('select pg_catalog.pg_sleep(0.3)');
        });
        $session->statement('set transaction_timeout to default');

        $this->assertSame('30s', $session->scalar('show transaction_timeout'));
        $this->assertSame(1, $this->observer()->table('harness_budget')->count());
    }

    public function test_a_budget_set_inside_a_running_transaction_replaces_no_running_timer(): void
    {
        // Lowering inside: the role's 30 seconds still run, not the 1 second.
        $this->observer()->transaction(function (Connection $c): void {
            $c->statement("set local transaction_timeout = '1s'");
            $c->select('select pg_catalog.pg_sleep(1.3)');
            $c->insert('insert into harness_budget values (1)');
        });
        $this->assertSame(1, $this->observer()->table('harness_budget')->count());

        // Raising inside: the 1 second set before BEGIN still ends it.
        $session = $this->observer();
        $session->statement("set transaction_timeout = '1s'");
        [$cause] = $this->ended($session, function (Connection $c): void {
            $c->statement("set local transaction_timeout = '5s'");
            $c->select('select pg_catalog.pg_sleep(1.3)');
            $c->insert('insert into harness_budget values (2)');
        });

        $this->assertStringContainsString('terminating connection due to transaction timeout', $cause->getMessage());
        $this->assertSame([1], $this->observer()->table('harness_budget')->pluck('id')->all());
    }

    public function test_zero_set_inside_a_running_transaction_stops_its_budget(): void
    {
        // So no code may set transaction_timeout inside a command's
        // transaction (config/database.php; review finding R-06).
        $session = $this->observer();
        $session->statement("set transaction_timeout = '1s'");
        $session->transaction(function (Connection $c): void {
            $c->statement('set local transaction_timeout = 0');
            $c->select('select pg_catalog.pg_sleep(1.3)');
            $c->insert('insert into harness_budget values (1)');
        });

        $this->assertSame(1, $this->observer()->table('harness_budget')->count());
    }

    public function test_a_statement_limit_set_for_the_transaction_cancels_a_slower_statement(): void
    {
        $session = $this->observer();

        [$cause, $surfaced] = $this->ended($session, function (Connection $c): void {
            $c->insert('insert into harness_budget values (1)');
            $c->statement("set local statement_timeout = '200ms'");
            $c->select('select pg_catalog.pg_sleep(0.5)');
        });

        $this->assertSame('57014', (string) $cause->getCode());
        $this->assertSame($cause, $surfaced, 'the session lives on, so the framework rethrows the statement\'s own error');
        $this->assertSame(0, $this->observer()->table('harness_budget')->count());
        $this->assertSame('10s', $session->scalar('show statement_timeout'));
    }

    public function test_a_session_idle_in_its_transaction_past_the_limit_is_ended(): void
    {
        $session = $this->observer();
        $session->statement("set idle_in_transaction_session_timeout = '300ms'");

        [$cause, $surfaced] = $this->ended($session, function (Connection $c): void {
            $c->insert('insert into harness_budget values (1)');
            usleep(700000);
            $c->insert('insert into harness_budget values (2)');
        });

        $this->assertSame('HY000', (string) $cause->getCode());
        $this->assertStringContainsString('terminating connection due to idle-in-transaction timeout', $cause->getMessage());
        $this->assertStringContainsString('no connection to the server', $surfaced->getMessage());
        $this->assertSame(0, $this->observer()->table('harness_budget')->count());
    }

    /**
     * Runs the statements in a transaction of the session that a limit ends,
     * and returns the error the statements met and the one the framework's
     * transaction() surfaced.
     *
     * @param  Closure(Connection): void  $statements
     * @return array{Throwable, Throwable}
     */
    private function ended(Connection $session, Closure $statements): array
    {
        $this->cause = null;

        try {
            $session->transaction(function (Connection $c) use ($statements): void {
                try {
                    $statements($c);
                } catch (Throwable $failure) {
                    $this->cause = $failure;

                    throw $failure;
                }
            });
        } catch (Throwable $surfaced) {
            $cause = $this->cause ?? $this->fail('The transaction failed outside its statements: '.$surfaced->getMessage());

            return [$cause, $surfaced];
        }

        $this->fail('The transaction was not ended by its limit.');
    }
}
