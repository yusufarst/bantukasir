<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-004: the tables of the database cache store, `cache` and `cache_locks`,
 * class E, used only for rate-limiter counters and scheduler locks (SCHEMA
 * section 5.11, ADR-002 item 3, TR-RUN-02), with the framework's own columns;
 * and the backup role's read of the framework's `migrations` table, which the
 * migrator created as the migration role before this migration ran. The
 * grants are those of SCHEMA section 10 and database/privileges.php (SCH-45):
 * the runtime role holds SELECT, INSERT, UPDATE and DELETE on both tables and
 * nothing on `migrations`; the backup role reads all three and the sequence of
 * `migrations`.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under
 * a second. Lock impact: none on an existing table. Verification (REL-20):
 * db:check-privileges passes; the cache store writes, reads, locks and
 * forgets as the runtime role (tests/Feature/Database/CacheTablesTest.php).
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $schema->create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        $schema->create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        $database->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE cache, cache_locks TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE cache, cache_locks, migrations TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE migrations_id_seq TO '.Identifier::quote($roles->backup));
    }
};
