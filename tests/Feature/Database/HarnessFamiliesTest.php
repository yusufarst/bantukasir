<?php

namespace Tests\Feature\Database;

use App\Database\Identifier;
use Illuminate\Database\Connection;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Harness\DatabaseTestCase;

/**
 * The self-tests of the helpers of the test families (TASK-004 contract
 * item 7; TEST_STRATEGY section 5): each helper passes on the case it proves
 * and fails the test on the opposite case, so a later Task that uses it cannot
 * pass by accident. An independent session sees only what was committed.
 */
final class HarnessFamiliesTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->harnessTable('harness_facts');
        $this->harnessTable('harness_heads');
        $this->migration()->statement('CREATE TABLE harness_heads (id bigint PRIMARY KEY)');
        $this->migration()->statement("CREATE TABLE harness_facts (id bigint PRIMARY KEY, head_id bigint NOT NULL CONSTRAINT harness_facts_head_fk REFERENCES harness_heads (id) DEFERRABLE INITIALLY DEFERRED, kind text NOT NULL CONSTRAINT harness_facts_kind_check CHECK (kind IN ('A', 'B')), expires bigint NOT NULL)");
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_heads TO '.Identifier::quote($this->roles()->runtime));
        $this->migration()->statement('GRANT SELECT, INSERT, UPDATE (kind), DELETE ON TABLE harness_facts TO '.Identifier::quote($this->roles()->runtime));
        $this->runtime()->transaction(function (Connection $c): void {
            $c->insert('insert into harness_heads values (1)');
            $c->insert("insert into harness_facts values (1, 1, 'A', 10), (2, 1, 'B', 20), (3, 1, 'A', 30)");
        });
    }

    public function test_the_constraint_helper_names_the_refusing_constraint_and_fails_on_an_accepted_write(): void
    {
        $refusal = $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into harness_facts values (9, 1, 'C', 1)"), '23514', 'harness_facts_kind_check');
        $this->assertStringContainsString('harness_facts_kind_check', $refusal->getMessage());
        $this->assertSame(3, $this->observer()->table('harness_facts')->count());

        $this->assertHelperFails(fn () => $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into harness_facts values (9, 1, 'A', 1)"), '23514'));
        $this->assertHelperFails(fn () => $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into harness_facts values (9, 1, 'C', 1)"), '23514', 'another_constraint'));
        $this->assertSame(3, $this->observer()->table('harness_facts')->count(), 'an accepted write in the helper is rolled back');
    }

    public function test_the_deferred_helper_fails_when_the_commit_succeeds_or_a_statement_fails_first(): void
    {
        $this->assertFailsAtCommit(fn (Connection $c) => $c->insert("insert into harness_facts values (9, 7, 'A', 1)"), 'harness_facts_head_fk', []);
        $this->assertSame([1, 2, 3], $this->observer()->table('harness_facts')->orderBy('id')->pluck('id')->all());

        $this->assertHelperFails(fn () => $this->assertFailsAtCommit(fn (Connection $c) => $c->insert("insert into harness_facts values (9, 1, 'A', 1)"), 'harness_facts_head_fk', []));
        $this->runtime()->delete('delete from harness_facts where id = 9');
        $this->assertHelperFails(fn () => $this->assertFailsAtCommit(fn (Connection $c) => $c->insert("insert into harness_facts values (9, 7, 'C', 1)"), 'harness_facts_head_fk', []));
        $this->assertSame(0, $this->runtime()->transactionLevel());
    }

    public function test_the_privilege_helper_fails_on_an_allowed_statement(): void
    {
        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->update('update harness_facts set expires = 0'));
        $this->assertHelperFails(fn () => $this->assertDenied($this->runtime(), fn (Connection $c) => $c->update("update harness_facts set kind = 'B' where id = 1")));
        $this->assertSame('A', $this->observer()->scalar('select kind from harness_facts where id = 1'));
    }

    public function test_the_retention_helpers_see_a_deleted_or_rewritten_row(): void
    {
        $this->assertRetained(['harness_facts'], fn () => $this->runtime()->insert("insert into harness_facts values (4, 1, 'B', 40)"));
        $this->assertHelperFails(fn () => $this->assertRetained(['harness_facts'], fn () => $this->runtime()->update("update harness_facts set kind = 'B' where id = 1")));
        $this->assertHelperFails(fn () => $this->assertRetained(['harness_facts'], fn () => $this->runtime()->delete('delete from harness_facts where id = 2')));

        $this->assertRemovedOnly('harness_facts', fn (array $row): bool => $row['expires'] < 35, fn () => $this->runtime()->delete('delete from harness_facts where expires < 35'));
        $this->assertSame([4], $this->observer()->table('harness_facts')->pluck('id')->all());

        $this->runtime()->insert("insert into harness_facts values (5, 1, 'A', 50), (6, 1, 'A', 60)");
        $this->assertHelperFails(fn () => $this->assertRemovedOnly('harness_facts', fn (array $row): bool => $row['expires'] < 55, fn () => $this->runtime()->delete('delete from harness_facts where expires < 65')));
    }

    public function test_an_observer_sees_only_what_was_committed(): void
    {
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $runtime->insert("insert into harness_facts values (8, 1, 'A', 1)");
            $this->assertSame(4, $runtime->table('harness_facts')->count());
            $this->assertSame(3, $this->observer()->table('harness_facts')->count());
            $this->assertNotSame($runtime->scalar('select pg_backend_pid()'), $this->observer()->scalar('select pg_backend_pid()'));
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(3, $this->observer()->table('harness_facts')->count());
    }

    private function assertHelperFails(\Closure $helper): void
    {
        try {
            $helper();
        } catch (AssertionFailedError) {
            $this->assertSame(0, $this->runtime()->transactionLevel());

            return;
        }

        $this->fail('The helper passed on the opposite case.');
    }
}
