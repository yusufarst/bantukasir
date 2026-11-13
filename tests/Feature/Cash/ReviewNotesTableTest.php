<?php

namespace Tests\Feature\Cash;

use App\Database\PrivilegeCheck;
use App\Database\PrivilegeMatrix;
use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The table of TASK-032, `cash_session_review_notes` (AC-032-04; SCHEMA sections 5.10, 6, 9.4, 10 and 11;
 * BR-CASH-19; D-069 (f); the migration 2026_11_13_000001_create_cash_session_review_notes_table;
 * EXECUTION_CONTEXT section 10, a table; suites DB01, KEEP01 and SESSION07; TASK-032 section 7, L2): the
 * constraint family, written directly by the runtime role — a note only of a session that has its close,
 * its text of 1 to 2,000 characters, its author and command, one per command; the privilege family — a
 * note is added, never changed or removed; the retention family; and the indexes of section 11. The table
 * has no deferred key: a note follows its close, which exists before it.
 */
final class ReviewNotesTableTest extends DatabaseTestCase
{
    private const string TABLE = 'cash_session_review_notes';

    private int $owner = 0;

    private int $register = 0;

    private int $session = 0;

    /** @var list<int> the identities of commands, for the notes written directly */
    private array $commands = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
        $this->register = TokoContoh::register();
        $this->session = TokoContoh::closeSession(TokoContoh::openSession('Hendra', $this->register));

        foreach (range(1, 2) as $index) {
            TokoContoh::category('Perintah '.(string) $index);
            $this->commands[] = self::number($this->watcher()->table('command_identities')->max('id'));
        }
    }

    public function test_each_check_and_key_of_a_note_refuses_its_violation(): void
    {
        // TECHNICAL_RULES section 11.3: 1 to 2,000 characters, counted as characters; the frozen name of
        // its author as every frozen name (SCHEMA section 3.5).
        foreach ([
            'cash_session_review_notes_note_length' => [['note' => ''], ['note' => str_repeat('c', 2001)]],
            'cash_session_review_notes_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('n', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertNote($c, $values), '23514', $constraint);
            }
        }

        // BR-CASH-19: a note belongs to a session that has its close, so an OPEN session, like one that does
        // not exist, has none; its author and command exist.
        $open = TokoContoh::openSession('Rina', TokoContoh::register('LACI-2', 'Laci 2'));
        $this->assertWriteRefused(fn (Connection $c) => $this->insertNote($c, ['session_id' => $open]), '23503', 'cash_session_review_notes_session_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertNote($c, ['session_id' => 999999]), '23503', 'cash_session_review_notes_session_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertNote($c, ['actor_user_id' => 999999]), '23503', 'cash_session_review_notes_actor_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertNote($c, ['command_id' => 999999]), '23503', 'cash_session_review_notes_command_fk');

        foreach (['session_id', 'note', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertNote($c, [$column => null]), '23502');
        }

        // One note per command; several per session (D-069 (f)).
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertNote($c);
            $this->insertNote($c);
        }, '23505', 'cash_session_review_notes_command_unique');

        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertNote($c, ['note' => "Baris satu\nBaris dua", 'command_id' => $this->commands[0]]);
            $this->insertNote($c, ['note' => str_repeat('é', 2000), 'command_id' => $this->commands[1]]);
        });
        $this->assertSame(2, $this->watcher()->table(self::TABLE)->where('session_id', $this->session)->count());

        $this->assertSame(['actor_display_name', 'actor_user_id', 'command_id', 'id', 'note', 'recorded_at', 'session_id'], $this->columnsOf());
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::reviewNote($this->session);
        $runtime = $this->runtime();

        // Class F: a note is added, never edited or removed (BR-CASH-19; D-069 (f)).
        $this->assertDenied($runtime, fn (Connection $c) => $c->table(self::TABLE)->update(['note' => 'Diubah']));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table(self::TABLE)->delete());
        $this->assertDenied($runtime, fn (Connection $c) => $c->table(self::TABLE)->truncate());
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table(self::TABLE)->update(['note' => 'Diubah']));
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table(self::TABLE)->delete());
        $this->assertSame(1, $this->backup()->table(self::TABLE)->count());

        // The grants equal the matrix of database/privileges.php, every table's (db:check-privileges).
        $this->assertSame([], (new PrivilegeCheck(PrivilegeMatrix::load()))->run($runtime));
    }

    public function test_a_later_note_only_adds_its_row_and_leaves_every_earlier_one_and_the_report(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; a note changes no earlier note, close or report.
        TokoContoh::reviewNote($this->session, 'Catatan pertama.');

        $this->assertRetained([self::TABLE], function (): void {
            $this->assertRetained(['cash_session_closes', 'cash_session_reports'], function (): void {
                TokoContoh::reviewNote($this->session, 'Koreksi: catatan kedua.');
                TokoContoh::reviewNote(TokoContoh::closeSession(TokoContoh::openSession('Hendra', $this->register)), 'Sesi berikutnya.');
            }, 'session_id');
        });

        $this->assertSame(3, $this->watcher()->table(self::TABLE)->count());
    }

    public function test_the_indexes_of_schema_section_11_exist_and_the_checks_are_immutable(): void
    {
        $indexes = $this->watcher()->table('pg_indexes')->where('schemaname', 'public')->where('tablename', self::TABLE)->pluck('indexdef', 'indexname')->all();

        $this->assertSame([
            'cash_session_review_notes_actor_index' => 'CREATE INDEX cash_session_review_notes_actor_index ON public.cash_session_review_notes USING btree (actor_user_id)',
            'cash_session_review_notes_command_unique' => 'CREATE UNIQUE INDEX cash_session_review_notes_command_unique ON public.cash_session_review_notes USING btree (command_id)',
            'cash_session_review_notes_pkey' => 'CREATE UNIQUE INDEX cash_session_review_notes_pkey ON public.cash_session_review_notes USING btree (id)',
            'cash_session_review_notes_session_index' => 'CREATE INDEX cash_session_review_notes_session_index ON public.cash_session_review_notes USING btree (session_id)',
        ], self::sorted($indexes));

        // Every key is immediate and restricts a change of what it names.
        $keys = $this->watcher()->table('pg_constraint')->whereIn('conname', ['cash_session_review_notes_actor_fk', 'cash_session_review_notes_command_fk', 'cash_session_review_notes_session_fk'])->orderBy('conname')->get(['conname', 'condeferrable', 'confupdtype', 'confdeltype']);
        $this->assertSame([
            ['cash_session_review_notes_actor_fk', false, 'r', 'r'],
            ['cash_session_review_notes_command_fk', false, 'r', 'r'],
            ['cash_session_review_notes_session_fk', false, 'r', 'r'],
        ], $keys->map(static fn (object $row): array => array_values((array) $row))->all());

        // The checks use only immutable functions (SCHEMA section 6).
        $this->assertSame([], array_values(array_filter($this->mutableChecks(), static fn (string $check): bool => str_starts_with($check, 'cash_session_review_notes_'))));
    }

    /**
     * A note of the Owner on the CLOSED session, written directly, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertNote(Connection $c, array $values = []): void
    {
        $c->table(self::TABLE)->insert([
            'session_id' => $this->session, 'note' => 'Selisih dijelaskan.', 'recorded_at' => now(), 'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra', 'command_id' => $this->commands[0], ...$values,
        ]);
    }

    /**
     * The table's columns, sorted.
     *
     * @return list<string>
     */
    private function columnsOf(): array
    {
        $columns = array_values(array_map(static fn (mixed $column): string => is_string($column) ? $column : '', $this->watcher()->table('information_schema.columns')->where('table_schema', 'public')->where('table_name', self::TABLE)->pluck('column_name')->all()));
        sort($columns);

        return $columns;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    private static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
