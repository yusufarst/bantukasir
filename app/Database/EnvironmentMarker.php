<?php

namespace App\Database;

use Illuminate\Database\ConnectionInterface;

/**
 * The marker of a database: the environment's name, written by the setup with
 * COMMENT ON DATABASE (ENVIRONMENTS ENV-04; database/setup/setup.sql). Before
 * an operation that may only touch some environments, the marker and the name
 * in configuration (config app.environment_name) must both be one of them and
 * equal to each other; a database's name alone is never proof (PRODUCTION_DATA_SAFETY
 * section 2). Only the user who runs the setup can change a marker: no role of
 * the application owns the database.
 */
final class EnvironmentMarker
{
    public static function of(ConnectionInterface $connection): ?string
    {
        // pg_catalog's operator by name: nothing of the schema public can change
        // the answer.
        $marker = $connection->scalar(
            "select pg_catalog.shobj_description(d.oid, 'pg_database') from pg_catalog.pg_database d where d.datname operator(pg_catalog.=) pg_catalog.current_database()"
        );

        return is_string($marker) ? $marker : null;
    }

    /**
     * The marker, when it and the configured environment name are equal and
     * one of the allowed names; otherwise the operation is refused.
     *
     * @param  non-empty-list<string>  $allowed
     *
     * @throws DatabaseRefused
     */
    public static function require(ConnectionInterface $connection, array $allowed): string
    {
        $configured = config('app.environment_name');
        $marker = self::of($connection);

        if (! is_string($configured) || $configured === '') {
            throw new DatabaseRefused('No environment name is configured (APP_ENVIRONMENT_NAME, ENV-04).');
        }

        if ($marker !== $configured) {
            throw new DatabaseRefused(sprintf(
                'The database marker %s differs from the environment name %s (ENV-04).',
                json_encode($marker),
                json_encode($configured),
            ));
        }

        if (! in_array($marker, $allowed, true)) {
            throw new DatabaseRefused(sprintf(
                'The database marker %s is not one of %s.',
                json_encode($marker),
                implode(', ', $allowed),
            ));
        }

        return $marker;
    }
}
