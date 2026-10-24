<?php

namespace App\Providers;

use App\Database\DatabaseRoles;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * The migration discipline (EXECUTION_CONTEXT EXC-41, SCHEMA SCH-40, SCH-50;
 * TASK-004), in every environment:
 *
 * - the framework's destructive database commands — db:wipe, migrate:fresh,
 *   migrate:refresh, migrate:reset and migrate:rollback — and db:seed are
 *   prohibited: migrations are forward only, and no reset or seeder exists on
 *   a path that staging or production can reach (PRODUCTION_DATA_SAFETY
 *   section 1);
 * - migrate and migrate:install start only when the connection they are given
 *   (--database, or the default) is the migration role of its database, before
 *   the migrations table is created or read, so that a superuser never owns it;
 * - a migration runs only as the migration role: before each one, inside its
 *   transaction, both the connection of the migration and the connection that
 *   records it in the migrations table must be that role, or it stops before
 *   its first statement. This also holds where the console events are not
 *   dispatched, as in the framework's tests.
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    private const array MIGRATING_COMMANDS = ['migrate', 'migrate:install'];

    public function boot(): void
    {
        DB::prohibitDestructiveCommands();
        SeedCommand::prohibit();

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, self::MIGRATING_COMMANDS, true)) {
                return;
            }

            $name = $event->input->hasOption('database') ? $event->input->getOption('database') : null;
            $connection = $this->app->make(DatabaseManager::class)->connection(is_string($name) && $name !== '' ? $name : null);

            DatabaseRoles::requireCurrent($connection, 'migration');
        });

        Event::listen(MigrationStarted::class, function (MigrationStarted $event): void {
            $migrator = $this->app->make(Migrator::class);
            $connection = $event->migration->getConnection();

            DatabaseRoles::requireCurrent($migrator->resolveConnection(is_string($connection) ? $connection : null), 'migration');
            DatabaseRoles::requireCurrent($migrator->resolveConnection(null), 'migration');
        });
    }
}
