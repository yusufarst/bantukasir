<?php

namespace Tests\Feature\Database;

use App\Database\Identifier;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Tests\Harness\DatabaseTestCase;

/**
 * Every PostgreSQL feature that SCHEMA relies on, proven on the pinned version
 * 18.6 (AC-004-03; SCHEMA SCH-52, sections 3.6, 3.7, 6, 10 and 11), each on
 * test-only tables that the migration role creates and the runtime role
 * writes, as the schema's own tables will be. A difference here stops the
 * Task (contract section 10).
 */
final class PostgresFeaturesTest extends DatabaseTestCase
{
    public function test_the_server_is_the_pinned_version(): void
    {
        $this->assertSame('180006', $this->runtime()->scalar('show server_version_num'));
    }

    public function test_a_partial_unique_index_holds_one_open_row_per_key(): void
    {
        $this->harnessTable('harness_sessions');
        $this->migration()->statement("CREATE TABLE harness_sessions (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, register_id bigint NOT NULL, status text NOT NULL CHECK (status IN ('OPEN', 'CLOSED')))");
        $this->migration()->statement("CREATE UNIQUE INDEX harness_sessions_one_open ON harness_sessions (register_id) WHERE status = 'OPEN'");
        $this->migration()->statement('GRANT SELECT, INSERT, UPDATE (status) ON TABLE harness_sessions TO '.Identifier::quote($this->roles()->runtime));

        $runtime = $this->runtime();
        $runtime->insert("insert into harness_sessions (register_id, status) values (1, 'OPEN'), (1, 'CLOSED'), (1, 'CLOSED'), (2, 'OPEN')");

        $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into harness_sessions (register_id, status) values (1, 'OPEN')"), '23505', 'harness_sessions_one_open');

        $runtime->update("update harness_sessions set status = 'CLOSED' where register_id = 1 and status = 'OPEN'");
        $runtime->insert("insert into harness_sessions (register_id, status) values (1, 'OPEN')");
        $this->assertSame(5, $this->observer()->table('harness_sessions')->count());
    }

    public function test_an_expression_unique_index_on_lower_refuses_a_case_variant_also_of_non_ascii_letters(): void
    {
        $this->harnessTable('harness_products');
        $this->migration()->statement('CREATE TABLE harness_products (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, sku text NOT NULL)');
        $this->migration()->statement('CREATE UNIQUE INDEX harness_products_sku_ci_uniq ON harness_products (lower(sku))');
        $this->migration()->statement('CREATE INDEX harness_products_sku_prefix ON harness_products (lower(sku) text_pattern_ops)');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_products TO '.Identifier::quote($this->roles()->runtime));

        $runtime = $this->runtime();
        $runtime->insert("insert into harness_products (sku) values ('PEN-01'), ('ÉCLAIR'), ('STRASSE'), ('ΣΑΠΟΥΝΙ')");

        foreach (['pen-01', 'Pen-01', 'éclair', 'Éclair', 'σαπουνι'] as $variant) {
            $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_products (sku) values (?)', [$variant]), '23505', 'harness_products_sku_ci_uniq');
        }

        // lower() maps one letter to one letter (no full case folding): ß is
        // not "ss", so "straße" is another SKU than "STRASSE" (SCH-14).
        $runtime->insert("insert into harness_products (sku) values ('straße')");

        // The prefix search of SCHEMA section 11 can use its index whatever
        // the collation.
        $plan = $this->planOf(fn (Connection $c): array => $c->select("explain select id from harness_products where lower(sku) like 'pen%'"));
        $this->assertStringContainsString('harness_products_sku_prefix', $plan);
    }

    public function test_a_deferred_foreign_key_is_checked_at_commit(): void
    {
        $this->harnessTable('harness_children');
        $this->harnessTable('harness_parents');
        $this->migration()->statement('CREATE TABLE harness_parents (id bigint PRIMARY KEY)');
        $this->migration()->statement('CREATE TABLE harness_children (id bigint PRIMARY KEY, parent_id bigint NOT NULL CONSTRAINT harness_children_parent_fk REFERENCES harness_parents (id) DEFERRABLE INITIALLY DEFERRED, sibling_id bigint NULL CONSTRAINT harness_children_sibling_fk REFERENCES harness_parents (id))');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_parents, harness_children TO '.Identifier::quote($this->roles()->runtime));

        $this->runtime()->transaction(function (Connection $c): void {
            $c->insert('insert into harness_children (id, parent_id) values (1, 1)');
            $c->insert('insert into harness_parents (id) values (1)');
        });
        $this->assertSame(1, $this->observer()->table('harness_children')->count());

        $this->assertFailsAtCommit(function (Connection $c): void {
            $c->insert('insert into harness_children (id, parent_id) values (2, 2)');
            $c->insert('insert into harness_parents (id) values (3)');
        }, 'harness_children_parent_fk', []);
        $this->assertSame([1], $this->observer()->table('harness_parents')->orderBy('id')->pluck('id')->all());
        $this->assertSame([1], $this->observer()->table('harness_children')->orderBy('id')->pluck('id')->all());

        // A key not marked deferred is checked by each statement.
        $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_children (id, parent_id, sibling_id) values (4, 1, 9)'), '23503', 'harness_children_sibling_fk');
    }

    public function test_a_stored_presence_key_refers_to_the_identity_column_and_carries_a_deferred_key(): void
    {
        $this->harnessTable('harness_allocations');
        $this->harnessTable('harness_movements');
        $this->migration()->statement(<<<'SQL'
            CREATE TABLE harness_movements (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                movement_type text NOT NULL CHECK (movement_type IN ('RECEIPT', 'SALE_ISSUE')),
                allocation_presence bigint GENERATED ALWAYS AS (CASE WHEN movement_type = 'SALE_ISSUE' THEN id END) STORED
            )
            SQL);
        $this->migration()->statement('CREATE TABLE harness_allocations (movement_id bigint PRIMARY KEY REFERENCES harness_movements (id))');
        $this->migration()->statement('ALTER TABLE harness_movements ADD CONSTRAINT harness_movements_allocation_fk FOREIGN KEY (allocation_presence) REFERENCES harness_allocations (movement_id) DEFERRABLE INITIALLY DEFERRED');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_movements, harness_allocations TO '.Identifier::quote($this->roles()->runtime));

        $runtime = $this->runtime();

        // A movement that needs no allocation commits alone.
        $receipt = $runtime->scalar("insert into harness_movements (movement_type) values ('RECEIPT') returning allocation_presence");
        $this->assertNull($receipt);

        // An issue commits with its allocation, and its presence key is its id.
        $issue = $runtime->transaction(function (Connection $c): mixed {
            $id = $c->scalar("insert into harness_movements (movement_type) values ('SALE_ISSUE') returning id");
            $c->insert('insert into harness_allocations (movement_id) values (?)', [$id]);

            return $id;
        });
        $this->assertSame($issue, $this->observer()->scalar('select allocation_presence from harness_movements where id = ?', [$issue]));

        // An issue without its allocation cannot commit, and leaves nothing.
        $this->assertFailsAtCommit(fn (Connection $c) => $c->insert("insert into harness_movements (movement_type) values ('SALE_ISSUE')"), 'harness_movements_allocation_fk', []);
        $this->assertSame(2, $this->observer()->table('harness_movements')->count());

        // The database computes the key; nobody writes it, and the runtime
        // role cannot update the fact.
        $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into harness_movements (movement_type, allocation_presence) values ('RECEIPT', 1)"), '428C9');
        $this->assertDenied($runtime, fn (Connection $c) => $c->update("update harness_movements set movement_type = 'RECEIPT'"));
    }

    public function test_match_full_refuses_a_partly_null_key_where_match_simple_lets_it_pass(): void
    {
        foreach (['harness_lines_simple', 'harness_lines_full', 'harness_revisions'] as $table) {
            $this->harnessTable($table);
        }

        $this->migration()->statement('CREATE TABLE harness_revisions (id bigint PRIMARY KEY, product_id bigint NOT NULL, UNIQUE (id, product_id))');
        $this->migration()->statement('CREATE TABLE harness_lines_full (id bigint PRIMARY KEY, revision_id bigint NULL, product_id bigint NULL, CONSTRAINT harness_lines_full_fk FOREIGN KEY (revision_id, product_id) REFERENCES harness_revisions (id, product_id) MATCH FULL)');
        $this->migration()->statement('CREATE TABLE harness_lines_simple (id bigint PRIMARY KEY, revision_id bigint NULL, product_id bigint NULL, CONSTRAINT harness_lines_simple_fk FOREIGN KEY (revision_id, product_id) REFERENCES harness_revisions (id, product_id))');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_revisions, harness_lines_full, harness_lines_simple TO '.Identifier::quote($this->roles()->runtime));

        $runtime = $this->runtime();
        $runtime->insert('insert into harness_revisions (id, product_id) values (1, 10)');
        $runtime->insert('insert into harness_lines_full (id, revision_id, product_id) values (1, 1, 10), (2, null, null)');

        $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_lines_full (id, revision_id, product_id) values (3, 1, null)'), '23503', 'harness_lines_full_fk');
        $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_lines_full (id, revision_id, product_id) values (4, null, 10)'), '23503', 'harness_lines_full_fk');
        $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_lines_full (id, revision_id, product_id) values (5, 1, 11)'), '23503', 'harness_lines_full_fk');

        // MATCH SIMPLE, the default, skips a key with one null (SCHEMA section 3.6).
        $runtime->insert('insert into harness_lines_simple (id, revision_id, product_id) values (1, 1, null), (2, 99, null)');
        $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_lines_simple (id, revision_id, product_id) values (3, 1, 11)'), '23503', 'harness_lines_simple_fk');
    }

    public function test_a_check_accepts_a_mutable_function_so_every_check_is_inspected(): void
    {
        $this->harnessTable('harness_numbers_mutable');
        $this->harnessTable('harness_numbers_immutable');

        // PostgreSQL accepts this check, although to_char() of a date is only
        // stable: it depends on the session's time zone and settings.
        $this->migration()->statement("CREATE TABLE harness_numbers_mutable (business_date date NOT NULL, number text NOT NULL, CONSTRAINT harness_numbers_mutable_format CHECK (number = to_char(business_date, 'YYYYMMDD')))");

        // The format of SCH-22 with immutable date and text functions only:
        // the business date as YYYYMMDD, a hyphen, and the counter zero-padded
        // to at least six digits, never truncated.
        $this->migration()->statement(<<<'SQL'
            CREATE TABLE harness_numbers_immutable (
                business_date date NOT NULL,
                number_seq bigint NOT NULL CHECK (number_seq > 0),
                number text NOT NULL,
                CONSTRAINT harness_numbers_immutable_format CHECK (number ~ '^[0-9]{8}-[0-9]{6,}$'),
                CONSTRAINT harness_numbers_immutable_parts CHECK (number =
                    (extract(year FROM business_date) * 10000 + extract(month FROM business_date) * 100 + extract(day FROM business_date))::text
                    || '-' || lpad(number_seq::text, greatest(6, length(number_seq::text)), '0'))
            )
            SQL);
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_numbers_immutable TO '.Identifier::quote($this->roles()->runtime));

        $this->assertSame(['harness_numbers_mutable.harness_numbers_mutable_format'], $this->mutableChecks());

        $runtime = $this->runtime();
        $runtime->insert("insert into harness_numbers_immutable values ('2026-10-05', 123, '20261005-000123'), ('2026-10-05', 1234567, '20261005-1234567'), ('2026-01-09', 999999, '20260109-999999')");

        // Unpadded, truncated, or with another date: refused.
        foreach ([['2026-10-05', 123, '20261005-123'], ['2026-10-05', 1234567, '20261005-234567'], ['2026-10-06', 123, '20261005-000123']] as [$date, $seq, $number]) {
            $this->assertWriteRefused(fn (Connection $c) => $c->insert('insert into harness_numbers_immutable values (?::date, ?, ?)', [$date, $seq, $number]), '23514');
        }
    }

    public function test_every_check_constraint_of_the_schema_is_immutable(): void
    {
        $this->assertSame([], $this->mutableChecks());
    }

    public function test_column_privileges_allow_the_listed_columns_only(): void
    {
        $this->harnessTable('harness_profiles');
        $this->migration()->statement('CREATE TABLE harness_profiles (id bigint PRIMARY KEY, name text NOT NULL, note text NOT NULL, version integer NOT NULL)');
        $this->migration()->statement('GRANT SELECT, INSERT, UPDATE (name, version) ON TABLE harness_profiles TO '.Identifier::quote($this->roles()->runtime));

        $runtime = $this->runtime();
        $runtime->insert("insert into harness_profiles values (1, 'a', 'n', 1)");
        $runtime->update("update harness_profiles set name = 'b', version = version + 1 where id = 1");

        $this->assertDenied($runtime, fn (Connection $c) => $c->update("update harness_profiles set note = 'm' where id = 1"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->update("update harness_profiles set name = 'c', note = 'm' where id = 1"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->delete('delete from harness_profiles'));
        $this->assertSame(['name' => 'b', 'note' => 'n', 'version' => 2], (array) $this->observer()->selectOne('select name, note, version from harness_profiles'));
    }

    public function test_row_locks_need_update_on_one_column_and_for_of_names_the_locked_table(): void
    {
        $this->harnessTable('harness_facts');
        $this->harnessTable('harness_guards');
        $this->migration()->statement('CREATE TABLE harness_guards (id bigint PRIMARY KEY, version integer NOT NULL)');
        $this->migration()->statement('CREATE TABLE harness_facts (id bigint PRIMARY KEY, guard_id bigint NOT NULL REFERENCES harness_guards (id))');
        $this->migration()->statement('GRANT SELECT, INSERT, UPDATE (version) ON TABLE harness_guards TO '.Identifier::quote($this->roles()->runtime));
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_facts TO '.Identifier::quote($this->roles()->runtime));

        $runtime = $this->runtime();
        $runtime->insert('insert into harness_guards values (1, 1)');
        $runtime->insert('insert into harness_facts values (1, 1)');

        foreach (['for no key update', 'for update', 'for share'] as $mode) {
            $this->assertSame(1, $runtime->transaction(fn (Connection $c): mixed => $c->table('harness_guards')->where('id', 1)->lock($mode)->value('id')), $mode);
            $this->assertDenied($runtime, fn (Connection $c) => $c->table('harness_facts')->where('id', 1)->lock($mode)->value('id'));
        }

        $this->assertSame(1, $runtime->transaction(fn (Connection $c): mixed => $c->scalar('select g.id from harness_guards g join harness_facts f on f.guard_id = g.id for no key update of g')));
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar('select g.id from harness_guards g join harness_facts f on f.guard_id = g.id for no key update'));
    }

    public function test_a_number_sequence_with_cache_1_allocates_in_request_order_never_cycles_and_never_reuses(): void
    {
        $this->migration()->statement('CREATE SEQUENCE harness_number_seq AS bigint START 1 INCREMENT 1 NO CYCLE CACHE 1');
        $this->migration()->statement('CREATE SEQUENCE harness_cached_seq AS bigint START 1 INCREMENT 1 NO CYCLE CACHE 20');
        $this->migration()->statement('CREATE SEQUENCE harness_short_seq AS bigint START 1 INCREMENT 1 MAXVALUE 2 NO CYCLE CACHE 1');
        $this->migration()->statement('GRANT USAGE ON SEQUENCE harness_number_seq, harness_cached_seq, harness_short_seq TO '.Identifier::quote($this->roles()->runtime));

        try {
            $this->assertNumberSequence('harness_number_seq');

            $first = $this->runtime();
            $second = $this->observer();
            $next = fn (Connection $c, string $sequence): mixed => $c->scalar('select pg_catalog.nextval(?)', [$sequence]);

            // Two sessions, interleaved: cache 1 hands out values in the order
            // they are asked for; a larger cache gives each session a block.
            $this->assertSame([1, 2, 3, 4], [$next($first, 'harness_number_seq'), $next($second, 'harness_number_seq'), $next($first, 'harness_number_seq'), $next($second, 'harness_number_seq')]);
            $this->assertSame([1, 21, 2, 22], [$next($first, 'harness_cached_seq'), $next($second, 'harness_cached_seq'), $next($first, 'harness_cached_seq'), $next($second, 'harness_cached_seq')]);

            // A rolled-back value is never reused: it leaves a gap (SCH-26).
            $first->beginTransaction();
            $this->assertSame(5, $next($first, 'harness_number_seq'));
            $first->rollBack();
            $this->assertSame(6, $next($second, 'harness_number_seq'));

            // NO CYCLE: past its maximum the sequence refuses.
            $this->assertSame([1, 2], [$next($first, 'harness_short_seq'), $next($first, 'harness_short_seq')]);
            $this->assertWriteRefused(fn (Connection $c) => $c->scalar("select pg_catalog.nextval('harness_short_seq')"), '2200H');
        } finally {
            $this->migration()->statement('DROP SEQUENCE harness_number_seq, harness_cached_seq, harness_short_seq');
        }
    }

    public function test_an_identity_column_needs_no_privilege_on_its_sequence(): void
    {
        $this->harnessTable('harness_identity');
        $this->migration()->statement('CREATE TABLE harness_identity (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, note text NOT NULL)');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_identity TO '.Identifier::quote($this->roles()->runtime));

        $this->assertSame([false, false, false], array_values((array) $this->runtime()->selectOne(
            "select pg_catalog.has_sequence_privilege(?, 'harness_identity_id_seq', 'USAGE') as usage, pg_catalog.has_sequence_privilege(?, 'harness_identity_id_seq', 'SELECT') as select, pg_catalog.has_sequence_privilege(?, 'harness_identity_id_seq', 'UPDATE') as update",
            [$this->roles()->runtime, $this->roles()->runtime, $this->roles()->runtime],
        )));

        $this->assertSame(1, $this->runtime()->scalar("insert into harness_identity (note) values ('a') returning id"));
        $this->assertSame(2, $this->runtime()->scalar("insert into harness_identity (note) values ('b') returning id"));
        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->scalar("select pg_catalog.nextval('harness_identity_id_seq')"));
        $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into harness_identity (id, note) values (9, 'c')"), '428C9');
    }

    public function test_a_trigram_index_serves_a_search_anywhere_in_the_name(): void
    {
        $this->harnessTable('harness_names');
        $this->migration()->statement('CREATE TABLE harness_names (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, name text NOT NULL)');
        $this->migration()->statement('CREATE INDEX harness_names_trgm ON harness_names USING gin (lower(name) gin_trgm_ops)');
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_names TO '.Identifier::quote($this->roles()->runtime));
        $this->runtime()->insert("insert into harness_names (name) values ('Pensil 2B'), ('Buku Tulis 38'), ('Penghapus'), ('Pulpen Hitam')");

        $this->assertSame(['Pensil 2B', 'Penghapus', 'Pulpen Hitam'], $this->runtime()->table('harness_names')->whereRaw('lower(name) like ?', ['%pen%'])->orderBy('id')->pluck('name')->all());
        $this->assertStringContainsString('harness_names_trgm', $this->planOf(fn (Connection $c): array => $c->select("explain select id from harness_names where lower(name) like '%tulis%'")));
    }

    /**
     * The plan of a read as the runtime role, with sequential scans
     * discouraged so that a usable index shows.
     *
     * @param  \Closure(Connection): array<int, object>  $explain
     */
    private function planOf(\Closure $explain): string
    {
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $runtime->statement('set local enable_seqscan = off');

            return implode("\n", array_map(fn (object $row): string => (string) json_encode(array_values((array) $row)), $explain($runtime)));
        } catch (QueryException $failure) {
            $this->fail($failure->getMessage());
        } finally {
            $runtime->rollBack();
        }
    }
}
