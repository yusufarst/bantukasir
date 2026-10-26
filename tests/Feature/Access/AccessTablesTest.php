<?php

namespace Tests\Feature\Access;

use App\Database\DatabaseRoles;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;

/**
 * The tables of the Access module (AC-006-07; SCHEMA sections 5.1, 5.11, 9.4
 * and 10; TEST_STRATEGY section 5; the migration
 * 2026_10_26_000001_create_access_tables): the constraint family for `users`,
 * `sessions` and `password_reset_tokens` — every check, the unique keys with
 * the case-insensitive email vectors of SCH-14, at most one active Owner, and
 * a null in every column that may not hold one — written directly by the
 * runtime role; the privilege family, exactly the grants of SCHEMA section 10;
 * and the retention family: no row of `users` is ever deleted, and the class E
 * tables are emptied only of what their rules remove. They have no deferred
 * key.
 */
final class AccessTablesTest extends DatabaseTestCase
{
    public function test_a_valid_account_is_accepted_and_each_check_of_users_refuses_its_violation(): void
    {
        $this->insertUser(['email' => 'rina@contoh.test']);

        $cases = [
            'users_display_name_length' => ['display_name' => ''],
            'users_email_length' => ['email' => ''],
            'users_status_allowed' => ['status' => 'SUSPENDED'],
            'users_version_positive' => ['version' => 0],
            'users_some_role' => ['role_cashier' => false],
            'users_super_admin_alone' => ['role_super_admin' => true, 'role_cashier' => true],
        ];

        foreach ($cases as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => $constraint.'@contoh.test', ...$values], $c), '23514', $constraint);
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'long@contoh.test', 'display_name' => str_repeat('a', 101)], $c), '23514', 'users_display_name_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => str_repeat('a', 243).'@contoh.test'], $c), '23514', 'users_email_length');
        $this->insertUser(['email' => str_repeat('b', 242).'@contoh.test']);
        $this->insertUser(['email' => 'name@contoh.test', 'display_name' => str_repeat('n', 100)]);

        $this->assertSame(3, $this->observer()->table('users')->count());
    }

    public function test_the_status_and_its_times_go_together(): void
    {
        $at = '2026-10-17 09:00:00+00';

        $this->insertUser(['email' => 'deactivated@contoh.test', 'status' => 'DEACTIVATED', 'deactivated_at' => $at]);
        $this->insertUser(['email' => 'anonymized@contoh.test', 'status' => 'ANONYMIZED', 'deactivated_at' => $at, 'anonymized_at' => $at, 'password' => null]);

        $refused = [
            ['status' => 'ACTIVE', 'deactivated_at' => $at],
            ['status' => 'ACTIVE', 'anonymized_at' => $at],
            ['status' => 'DEACTIVATED'],
            ['status' => 'DEACTIVATED', 'deactivated_at' => $at, 'anonymized_at' => $at],
            ['status' => 'ANONYMIZED', 'deactivated_at' => $at],
            ['status' => 'ANONYMIZED', 'anonymized_at' => $at],
            ['status' => 'ANONYMIZED', 'deactivated_at' => $at, 'anonymized_at' => $at, 'password' => 'an unusable hash is null, never a value'],
        ];

        foreach ($refused as $index => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'status-'.$index.'@contoh.test', ...$values], $c), '23514', 'users_status_times');
        }
    }

    public function test_a_null_is_refused_in_every_column_that_may_not_hold_one(): void
    {
        foreach (['display_name', 'email', 'role_super_admin', 'role_operations_admin', 'role_cashier', 'status', 'version', 'created_at', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'null-'.$column.'@contoh.test', $column => null], $c), '23502');
        }

        // A null role flag makes the role checks null too, and NOT NULL refuses it first (SCH-13).
        $this->insertUser(['email' => 'no-password@contoh.test', 'password' => null]);
        $this->assertSame(1, $this->observer()->table('users')->count());
    }

    public function test_an_email_is_unique_without_regard_to_letter_case_by_lower(): void
    {
        $this->insertUser(['email' => 'Dewi@Contoh.test']);

        foreach (['dewi@contoh.test', 'DEWI@CONTOH.TEST', 'dEwI@cOnToH.TeSt'] as $email) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => $email], $c), '23505', 'users_email_ci_uniq');
        }

        // Non-ASCII letters fold by the database's lower() of the pinned
        // locale (SCHEMA section 15): one letter to one letter, never ß to ss.
        $this->insertUser(['email' => 'ÉLODIE@contoh.test']);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'élodie@contoh.test'], $c), '23505', 'users_email_ci_uniq');
        $this->insertUser(['email' => 'strasse@contoh.test']);
        $this->insertUser(['email' => 'straße@contoh.test']);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'STRAẞE@contoh.test'], $c), '23505', 'users_email_ci_uniq');
        $this->insertUser(['email' => 'İrma@contoh.test']);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'irma@contoh.test'], $c), '23505', 'users_email_ci_uniq');

        $this->assertSame(5, $this->observer()->table('users')->count());
    }

    public function test_at_most_one_owner_is_active(): void
    {
        $at = '2026-10-17 09:00:00+00';
        $this->insertUser(['email' => 'hendra@contoh.test', 'role_super_admin' => true, 'role_cashier' => false]);

        $this->assertWriteRefused(fn (Connection $c) => $this->insertUser(['email' => 'second-owner@contoh.test', 'role_super_admin' => true, 'role_cashier' => false], $c), '23505', 'users_one_active_owner');

        // A former Owner, deactivated, does not count (SEC-OP-03).
        $this->insertUser(['email' => 'former-owner@contoh.test', 'role_super_admin' => true, 'role_cashier' => false, 'status' => 'DEACTIVATED', 'deactivated_at' => $at]);
        $this->assertWriteRefused(
            fn (Connection $c) => $c->update("update users set status = 'ACTIVE', deactivated_at = null where email = 'former-owner@contoh.test'"),
            '23505',
            'users_one_active_owner',
        );

        $this->assertSame(1, $this->observer()->table('users')->where('role_super_admin', true)->where('status', 'ACTIVE')->count());
    }

    public function test_the_runtime_role_never_changes_the_owner_role_and_never_deletes_an_account(): void
    {
        $this->insertUser(['email' => 'hendra@contoh.test', 'role_super_admin' => true, 'role_cashier' => false]);
        $runtime = $this->runtime();

        $this->assertDenied($runtime, fn (Connection $c) => $c->update('update users set role_super_admin = false'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->update('update users set id = default'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->update('update users set created_at = created_at'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->delete('delete from users'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('users')->truncate());

        foreach (['display_name', 'email', 'password', 'role_operations_admin', 'role_cashier', 'status', 'deactivated_at', 'anonymized_at', 'version', 'updated_at'] as $column) {
            $this->assertSame(true, $runtime->scalar('select pg_catalog.has_column_privilege(?, ?, ?, ?)', [DatabaseRoles::of($runtime)->runtime, 'users', $column, 'UPDATE']), $column);
        }

        $runtime->update("update users set display_name = 'Hendra S', version = version + 1, updated_at = now()");
        $this->assertSame('Hendra S', $this->observer()->scalar('select display_name from users'));
    }

    public function test_the_runtime_role_holds_exactly_the_grants_of_schema_section_10_on_the_access_tables(): void
    {
        $roles = DatabaseRoles::of($this->runtime());
        $expected = [
            'users' => ['SELECT', 'INSERT'],
            'sessions' => ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
            'password_reset_tokens' => ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
        ];

        foreach ($expected as $table => $privileges) {
            $held = [];

            foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER', 'MAINTAIN'] as $privilege) {
                if ($this->runtime()->scalar('select pg_catalog.has_table_privilege(?, ?, ?)', [$roles->runtime, $table, $privilege]) === true) {
                    $held[] = $privilege;
                }
            }

            $this->assertSame($privileges, $held, $table);
            $this->assertSame(0, $this->backup()->table($table)->count());
        }

        $this->assertDenied($this->backup(), fn (Connection $c) => $c->insert('insert into users default values'));
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->insert('insert into sessions default values'));
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->insert('insert into password_reset_tokens default values'));

        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->table('sessions')->truncate());
        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->table('password_reset_tokens')->truncate());
        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->select("select pg_catalog.setval('users_id_seq', 1)"));
    }

    public function test_the_constraints_of_the_class_e_tables_refuse_a_violating_row(): void
    {
        $this->runtime()->table('sessions')->insert(['id' => 'harness-session', 'user_id' => null, 'payload' => 'p', 'last_activity' => 1]);
        $this->runtime()->table('password_reset_tokens')->insert(['email' => 'rina@contoh.test', 'token' => 'hash', 'created_at' => '2026-10-17 09:00:00+00']);

        $this->assertWriteRefused(fn (Connection $c) => $c->table('sessions')->insert(['id' => 'harness-session', 'payload' => 'q', 'last_activity' => 2]), '23505', 'sessions_pkey');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('password_reset_tokens')->insert(['email' => 'rina@contoh.test', 'token' => 'other', 'created_at' => '2026-10-17 09:00:00+00']), '23505', 'password_reset_tokens_pkey');

        foreach (['payload', 'last_activity'] as $column) {
            $row = ['id' => 'null-'.$column, 'payload' => 'p', 'last_activity' => 1];
            $row[$column] = null;
            $this->assertWriteRefused(fn (Connection $c) => $c->table('sessions')->insert($row), '23502');
        }

        foreach (['email', 'token', 'created_at'] as $column) {
            $row = ['email' => 'null-'.$column.'@contoh.test', 'token' => 't', 'created_at' => '2026-10-17 09:00:00+00'];
            $row[$column] = null;
            $this->assertWriteRefused(fn (Connection $c) => $c->table('password_reset_tokens')->insert($row), '23502');
        }

        $this->assertSame(1, $this->observer()->table('sessions')->count());
        $this->assertSame(1, $this->observer()->table('password_reset_tokens')->count());
    }

    public function test_the_checks_of_the_access_tables_are_immutable(): void
    {
        $this->assertSame([], array_values(array_filter($this->mutableChecks(), fn (string $check): bool => str_starts_with($check, 'users.'))));
    }

    /**
     * Inserts an ACTIVE cashier, with the values given in place of the
     * defaults, as the runtime role.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertUser(array $values, ?Connection $connection = null): void
    {
        ($connection ?? $this->runtime())->table('users')->insert([
            'display_name' => 'Rina',
            'email' => 'rina@contoh.test',
            'password' => null,
            'role_super_admin' => false,
            'role_operations_admin' => false,
            'role_cashier' => true,
            'status' => 'ACTIVE',
            'deactivated_at' => null,
            'anonymized_at' => null,
            'version' => 1,
            'created_at' => '2026-10-17 09:00:00+00',
            'updated_at' => '2026-10-17 09:00:00+00',
            ...$values,
        ]);
    }
}
