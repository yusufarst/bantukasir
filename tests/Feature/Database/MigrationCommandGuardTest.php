<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRefused;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Tests\Harness\DatabaseTestCase;
use Throwable;

/**
 * migrate and migrate:install refuse, before they create or read the
 * migrations table, a connection that is not the migration role of its
 * database (review finding R-17; App\Providers\DatabaseServiceProvider), as
 * the console does: the framework does not dispatch the console events in
 * tests unless asked, so this test asks.
 */
final class MigrationCommandGuardTest extends DatabaseTestCase
{
    use WithConsoleEvents;

    public function test_migrate_and_migrate_install_refuse_any_role_but_the_migration_role(): void
    {
        $before = $this->backup()->table('migrations')->pluck('migration')->all();

        foreach ([['migrate', 'pgsql'], ['migrate', 'pgsql_backup'], ['migrate', null], ['migrate:install', 'pgsql'], ['migrate:install', 'pgsql_backup']] as [$command, $connection]) {
            $options = $connection === null ? [] : ['--database' => $connection];

            try {
                Artisan::call($command, [...$options, '--force' => true]);
                $this->fail(sprintf('%s ran on %s.', $command, $connection ?? 'the default connection'));
            } catch (Throwable $refusal) {
                $this->assertInstanceOf(DatabaseRefused::class, $refusal, $refusal->getMessage());
                $this->assertStringContainsString('the operation needs the migration role of this database', $refusal->getMessage());
            }
        }

        $this->assertSame($before, $this->backup()->table('migrations')->pluck('migration')->all());
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'pgsql_migration', '--force' => true]));
    }
}
