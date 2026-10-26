<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-006: the tables of the Access module (SCHEMA section 5.1) — `users`
 * (class M), `sessions` and `password_reset_tokens` (class E, the tables of
 * the framework's database session driver and password broker, SCHEMA
 * section 5.11) — with their checks, indexes and grants (SCHEMA section 10,
 * database/privileges.php; SCH-45). The checks are written as SQL (SCH-51);
 * each is named, so that the constraint family names the one that refuses.
 *
 * - users: an identity key; the three role flags, SUPER_ADMIN alone (D-032);
 *   the status with its times (D-043); the email unique without regard to
 *   letter case by lower() (SCH-14, SEC-ACC-03); at most one ACTIVE
 *   SUPER_ADMIN (users_one_active_owner, D-048, RACE-30). No remember_token
 *   and no email_verified_at column (SEC-AUTH-07, D-049). The runtime role
 *   may change the listed columns only, never role_super_admin.
 * - sessions: the framework's columns; the sign-in time and the time of the
 *   last user-initiated request live in the encrypted payload (SEC-SESS-02).
 * - password_reset_tokens: one hashed token per email, and so per account.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under
 * a second. Lock impact: none on an existing table. Verification (REL-20):
 * db:check-privileges passes; tests/Feature/Access/AccessTablesTest.php
 * proves the constraint, privilege and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE users (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                display_name text NOT NULL CONSTRAINT users_display_name_length CHECK (char_length(display_name) BETWEEN 1 AND 100),
                email text NOT NULL CONSTRAINT users_email_length CHECK (char_length(email) BETWEEN 1 AND 254),
                password text NULL,
                role_super_admin boolean NOT NULL,
                role_operations_admin boolean NOT NULL,
                role_cashier boolean NOT NULL,
                status text NOT NULL CONSTRAINT users_status_allowed CHECK (status IN ('ACTIVE', 'DEACTIVATED', 'ANONYMIZED')),
                deactivated_at timestamptz NULL,
                anonymized_at timestamptz NULL,
                version integer NOT NULL CONSTRAINT users_version_positive CHECK (version >= 1),
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL,
                CONSTRAINT users_some_role CHECK (role_super_admin OR role_operations_admin OR role_cashier),
                CONSTRAINT users_super_admin_alone CHECK (NOT role_super_admin OR (NOT role_operations_admin AND NOT role_cashier)),
                CONSTRAINT users_status_times CHECK (
                    (status = 'ACTIVE' AND deactivated_at IS NULL AND anonymized_at IS NULL)
                    OR (status = 'DEACTIVATED' AND deactivated_at IS NOT NULL AND anonymized_at IS NULL)
                    OR (status = 'ANONYMIZED' AND deactivated_at IS NOT NULL AND anonymized_at IS NOT NULL AND password IS NULL)
                )
            )
            SQL);

        $database->statement('CREATE UNIQUE INDEX users_email_ci_uniq ON users (lower(email))');
        $database->statement('CREATE UNIQUE INDEX users_one_active_owner ON users (role_super_admin) WHERE role_super_admin AND status = \'ACTIVE\'');

        $database->statement(<<<'SQL'
            CREATE TABLE sessions (
                id varchar(255) PRIMARY KEY,
                user_id bigint NULL,
                ip_address varchar(45) NULL,
                user_agent text NULL,
                payload text NOT NULL,
                last_activity integer NOT NULL
            )
            SQL);

        $database->statement('CREATE INDEX sessions_user_id_index ON sessions (user_id)');
        $database->statement('CREATE INDEX sessions_last_activity_index ON sessions (last_activity)');

        $database->statement(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                email text PRIMARY KEY,
                token text NOT NULL,
                created_at timestamptz NOT NULL
            )
            SQL);

        $database->statement('GRANT SELECT, INSERT ON TABLE users TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT UPDATE (display_name, email, password, role_operations_admin, role_cashier, status, deactivated_at, anonymized_at, version, updated_at) ON TABLE users TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE sessions, password_reset_tokens TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE users, sessions, password_reset_tokens TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE users_id_seq TO '.Identifier::quote($roles->backup));
    }
};
