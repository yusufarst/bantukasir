<?php

/*
|--------------------------------------------------------------------------
| Time limits (TECHNICAL_RULES section 9; ENVIRONMENTS ENV-31)
|--------------------------------------------------------------------------
|
| Fixed here, not per environment: a change follows change control. The role
| settings below put them in the database, as database/setup/setup.sql writes
| them, and db:check-privileges compares the two.
|
| The transaction budgets are enforced with PostgreSQL's transaction_timeout
| (PostgreSQL 17 and later), verified on 18.6 by TASK-004: the value in force
| when a transaction begins times it from its start, and a transaction that
| exceeds it is ended with its session and leaves nothing (the server's
| SQLSTATE 25P04, which pdo_pgsql reports as HY000 with the reason in its
| message). Inside a running transaction a new non-zero value replaces no
| running timer, and SET LOCAL arms one only when none runs; but zero stops the
| running timer. So a command sets its budget for the session immediately
| before BEGIN and restores the role's default after the transaction, and no
| code ever sets transaction_timeout inside a command's transaction. The
| runtime role's default, the larger budget, also bounds every transaction that
| sets none. Every role's session ends after 30 seconds idle in a transaction.
|
*/

$limits = [
    'lock_timeout' => '5s',
    'statement_timeout' => '10s',
    'read_statement_timeout' => '15s',
    'idle_in_transaction_session_timeout' => '30s',
    'transaction_budget' => '10s',
    'heavy_transaction_budget' => '30s',
    'retry_window' => '15s',
];

return [

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | PostgreSQL only (ADR-001), with the three roles of SCHEMA section 10,
    | created by database/setup/setup.sql (ENVIRONMENTS ENV-30; TASK-004):
    |
    | - pgsql, the default: the runtime role, the only one the application
    |   uses (SCH-41);
    | - pgsql_migration: the migration role, used only by migrations and the
    |   grants procedure (SCH-40), and given its credential only in the
    |   migration job (ENVIRONMENTS section 11; ADR-011 item 7);
    | - pgsql_backup: the backup role (SCH-42), used by the backup container.
    |
    | Migrations run with "php artisan migrate --database=pgsql_migration";
    | App\Providers\DatabaseServiceProvider refuses any other role.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'bantukasir'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'pgsql_migration' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'bantukasir'),
            'username' => env('DB_MIGRATION_USERNAME'),
            'password' => env('DB_MIGRATION_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'pgsql_backup' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'bantukasir'),
            'username' => env('BACKUP_DB_USERNAME'),
            'password' => env('BACKUP_DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'limits' => $limits,

    // Each role's settings in its database, exactly as pg_db_role_setting holds
    // them (ENVIRONMENTS ENV-31). bantukasir.role names the logical role, by
    // which the application finds the role of each kind without its name in
    // configuration (App\Database\DatabaseRoles). The backup role has
    // no statement or transaction limit of its own: the backup run's limit of
    // 30 minutes bounds it.
    'role_settings' => [
        'migration' => [
            'bantukasir.role' => 'migration',
            'lock_timeout' => $limits['lock_timeout'],
            'idle_in_transaction_session_timeout' => $limits['idle_in_transaction_session_timeout'],
        ],
        'runtime' => [
            'bantukasir.role' => 'runtime',
            'lock_timeout' => $limits['lock_timeout'],
            'statement_timeout' => $limits['statement_timeout'],
            'idle_in_transaction_session_timeout' => $limits['idle_in_transaction_session_timeout'],
            'transaction_timeout' => $limits['heavy_transaction_budget'],
        ],
        'backup' => [
            'bantukasir.role' => 'backup',
            'statement_timeout' => '0',
            'idle_in_transaction_session_timeout' => $limits['idle_in_transaction_session_timeout'],
            'transaction_timeout' => '0',
        ],
    ],

    // The database's own settings (ENVIRONMENTS ENV-33), as pg_db_role_setting
    // holds them.
    'database_settings' => [
        'TimeZone' => 'UTC',
    ],

];
