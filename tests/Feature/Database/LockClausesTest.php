<?php

namespace Tests\Feature\Database;

use App\Database\Identifier;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Tests\Harness\DatabaseTestCase;

/**
 * The query builder's lock clauses on the pinned framework, Laravel 13.37.0
 * (AC-004-08; SCHEMA SCH-30, SCH-34, section 7): lock('for no key update'),
 * lockForUpdate() and sharedLock() emit the row-lock modes that the lock steps
 * of TECHNICAL_RULES section 7.1 name, and lock('… of <table>') names only the
 * locked table; each runs as the runtime role. The modes then conflict as
 * section 7 says: the update lock of a command leaves a foreign-key check's
 * key-share lock free and refuses every share or update lock; FOR UPDATE also
 * refuses the key-share lock.
 */
final class LockClausesTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->harnessTable('harness_appended');
        $this->harnessTable('harness_rows');
        $this->migration()->statement('CREATE TABLE harness_rows (id bigint PRIMARY KEY, version integer NOT NULL)');
        $this->migration()->statement('CREATE TABLE harness_appended (id bigint PRIMARY KEY, row_id bigint NOT NULL REFERENCES harness_rows (id))');
        $this->migration()->statement('GRANT SELECT, INSERT, UPDATE (version) ON TABLE harness_rows TO '.Identifier::quote($this->roles()->runtime));
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_appended TO '.Identifier::quote($this->roles()->runtime));
        $this->runtime()->insert('insert into harness_rows values (1, 1), (2, 1)');
        $this->runtime()->insert('insert into harness_appended values (1, 1), (2, 2)');
    }

    public function test_each_builder_method_emits_the_intended_lock_clause_and_runs(): void
    {
        $rows = fn (): Builder => $this->runtime()->table('harness_rows')->whereIn('id', [1, 2])->orderBy('id');
        $joined = fn (): Builder => $this->runtime()->table('harness_rows')
            ->join('harness_appended', 'harness_appended.row_id', '=', 'harness_rows.id')
            ->select('harness_rows.*')->whereIn('harness_rows.id', [1, 2])->orderBy('harness_rows.id');

        $cases = [
            ['select * from "harness_rows" where "id" in (?, ?) order by "id" asc for no key update', $rows()->lock('for no key update')],
            ['select * from "harness_rows" where "id" in (?, ?) order by "id" asc for update', $rows()->lockForUpdate()],
            ['select * from "harness_rows" where "id" in (?, ?) order by "id" asc for share', $rows()->sharedLock()],
            ['select "harness_rows".* from "harness_rows" inner join "harness_appended" on "harness_appended"."row_id" = "harness_rows"."id" where "harness_rows"."id" in (?, ?) order by "harness_rows"."id" asc for no key update of harness_rows', $joined()->lock('for no key update of harness_rows')],
        ];

        foreach ($cases as [$sql, $query]) {
            $this->assertSame($sql, $query->toSql());
            $this->assertSame([1, 2], $this->runtime()->transaction(fn (): array => $query->pluck('id')->all()), $sql);
        }

        // Without OF the join would also lock the insert-only table, which the
        // runtime role cannot lock (SCH-34).
        $this->assertDenied($this->runtime(), fn () => $joined()->lock('for no key update')->get());
    }

    public function test_the_lock_modes_conflict_as_section_7_describes(): void
    {
        $other = $this->observer();

        foreach (['for no key update' => [true, false, false], 'for update' => [false, false, false], 'for share' => [true, true, false]] as $held => [$keyShare, $share, $update]) {
            $this->runtime()->transaction(function (Connection $holder) use ($held, $other, $keyShare, $share, $update): void {
                $holder->table('harness_rows')->where('id', 1)->lock($held)->value('id');

                $this->assertSame($keyShare, $this->granted($other, fn (Connection $c) => $c->scalar('select id from harness_rows where id = 1 for key share nowait')), $held.' against for key share');
                $this->assertSame($share, $this->granted($other, fn (Connection $c) => $c->scalar('select id from harness_rows where id = 1 for share nowait')), $held.' against for share');
                $this->assertSame($update, $this->granted($other, fn (Connection $c) => $c->scalar('select id from harness_rows where id = 1 for no key update nowait')), $held.' against for no key update');
            });
        }

        // A foreign-key check takes the key-share lock: an append that names a
        // row under a command's update lock does not wait for it.
        $this->runtime()->transaction(function (Connection $holder) use ($other): void {
            $holder->table('harness_rows')->where('id', 1)->lock('for no key update')->value('id');

            $other->transaction(function (Connection $c): void {
                $c->statement("set local lock_timeout = '100ms'");
                $c->insert('insert into harness_appended values (3, 1)');
            });
        });

        $this->assertSame(3, $this->observer()->table('harness_appended')->count());
    }

    /**
     * @param  Closure(Connection): mixed  $lock
     */
    private function granted(Connection $connection, Closure $lock): bool
    {
        $connection->beginTransaction();

        try {
            $lock($connection);

            return true;
        } catch (QueryException $refusal) {
            $this->assertSame('55P03', (string) $refusal->getCode(), $refusal->getMessage());

            return false;
        } finally {
            $connection->rollBack();
        }
    }
}
