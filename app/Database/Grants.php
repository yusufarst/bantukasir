<?php

namespace App\Database;

use Illuminate\Database\Connection;

/**
 * The grants procedure (TASK-004; BACKUP_RECOVERY RB-01 step 9): it brings the
 * privileges of every relation of the schema public, and of the schema itself,
 * to the privilege matrix, as the migration role, which owns them (SCH-40). For
 * each table and sequence it revokes everything from PUBLIC and the runtime
 * and backup roles, then grants exactly the matrix's privileges, so a second
 * run changes nothing and an extra grant to PUBLIC or to those roles is
 * removed; a grant to any other role stays, and db:check-privileges reports
 * it. The database's own rights belong to the setup (database/setup/setup.sql),
 * which the migration role cannot change.
 *
 * It runs in one transaction of its own that searches pg_catalog only, and
 * names every relation with its schema. It refuses before any change when the
 * connection is not the migration role, when a table or a sequence that no
 * column owns is not in the matrix, or when a table or sequence of the matrix
 * is missing: the schema is then not the one the matrix describes. A revoke
 * that other grants depend on fails, and so does the whole transaction.
 */
final class Grants
{
    public function __construct(private readonly PrivilegeMatrix $matrix) {}

    /**
     * Applies the matrix in one transaction and returns the statements, or,
     * with $pretend, returns them without running any.
     *
     * @return list<string>
     *
     * @throws DatabaseRefused
     */
    public function apply(Connection $connection, bool $pretend = false): array
    {
        if ($connection->transactionLevel() !== 0) {
            throw new DatabaseRefused('The grants procedure runs in a transaction of its own, outside any other.');
        }

        $connection->beginTransaction();

        try {
            $connection->statement('set local search_path = pg_catalog, pg_temp');
            $roles = DatabaseRoles::requireCurrent($connection, 'migration');
            [$tables, $sequences] = $this->relations($connection);
            $run = fn () => $this->grant($connection, $roles, $tables, $sequences);

            if ($pretend) {
                $statements = self::queries($connection->pretend($run));
                $connection->rollBack();

                return $statements;
            }

            $connection->enableQueryLog();
            $connection->flushQueryLog();
            $run();
            $statements = self::queries($connection->getQueryLog());
            $connection->disableQueryLog();
            $connection->commit();

            return $statements;
        } catch (\Throwable $failure) {
            $connection->disableQueryLog();

            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $failure;
        }
    }

    /**
     * @param  list<string>  $tables
     * @param  list<string>  $sequences
     */
    private function grant(Connection $connection, DatabaseRoles $roles, array $tables, array $sequences): void
    {
        $connection->statement('REVOKE ALL ON SCHEMA public FROM PUBLIC, '.Identifier::quote($roles->runtime).', '.Identifier::quote($roles->backup));
        $connection->statement('GRANT USAGE ON SCHEMA public TO '.Identifier::quote($roles->runtime).', '.Identifier::quote($roles->backup));

        foreach ($tables as $table) {
            // Revoking a table's privileges also revokes its column privileges.
            $connection->statement('REVOKE ALL ON TABLE public.'.Identifier::quote($table).' FROM PUBLIC, '.Identifier::quote($roles->runtime).', '.Identifier::quote($roles->backup));

            foreach ($this->matrix->tablePrivileges($table, 'runtime') as $privilege) {
                $keyword = self::keyword($privilege);
                $connection->statement('GRANT '.$keyword.' ON TABLE public.'.Identifier::quote($table).' TO '.Identifier::quote($roles->runtime));
            }

            foreach ($this->matrix->updateColumns($table) as $column) {
                $connection->statement('GRANT UPDATE ('.Identifier::quote($column).') ON TABLE public.'.Identifier::quote($table).' TO '.Identifier::quote($roles->runtime));
            }

            foreach ($this->matrix->tablePrivileges($table, 'backup') as $privilege) {
                $keyword = self::keyword($privilege);
                $connection->statement('GRANT '.$keyword.' ON TABLE public.'.Identifier::quote($table).' TO '.Identifier::quote($roles->backup));
            }
        }

        foreach ($sequences as $sequence) {
            $connection->statement('REVOKE ALL ON SEQUENCE public.'.Identifier::quote($sequence).' FROM PUBLIC, '.Identifier::quote($roles->runtime).', '.Identifier::quote($roles->backup));
            $connection->statement('GRANT SELECT ON SEQUENCE public.'.Identifier::quote($sequence).' TO '.Identifier::quote($roles->backup));

            if ($this->matrix->sequences()[$sequence] ?? false) {
                $connection->statement('GRANT USAGE ON SEQUENCE public.'.Identifier::quote($sequence).' TO '.Identifier::quote($roles->runtime));
            }
        }
    }

    /**
     * The tables and sequences of the schema public outside any extension,
     * checked against the matrix before anything changes.
     *
     * @return array{list<string>, list<string>}
     *
     * @throws DatabaseRefused
     */
    private function relations(Connection $connection): array
    {
        $rows = $connection->select(<<<'SQL'
            select c.relname, c.relkind,
                   exists (select from pg_catalog.pg_depend o
                           where o.classid = 'pg_catalog.pg_class'::regclass and o.objid = c.oid
                             and o.refclassid = 'pg_catalog.pg_class'::regclass and o.deptype in ('a', 'i')) as owned
            from pg_catalog.pg_class c
            join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind in ('r', 'p', 'S')
              and not exists (select from pg_catalog.pg_depend d
                              where d.classid = 'pg_catalog.pg_class'::regclass and d.objid = c.oid and d.deptype = 'e')
            order by c.relname
            SQL);

        $tables = [];
        $sequences = [];
        $unknown = [];

        foreach ($rows as $row) {
            $relation = (array) $row;
            $name = is_string($relation['relname']) ? $relation['relname'] : '';

            if ($relation['relkind'] === 'S') {
                $sequences[] = $name;

                if ($relation['owned'] !== true && ! array_key_exists($name, $this->matrix->sequences())) {
                    $unknown[] = 'sequence '.$name;
                }
            } else {
                $tables[] = $name;

                if (! $this->matrix->has($name)) {
                    $unknown[] = 'table '.$name;
                }
            }
        }

        $missing = [
            ...array_map(fn (string $table): string => 'table '.$table, array_values(array_diff(array_keys($this->matrix->tables()), $tables))),
            ...array_map(fn (string $sequence): string => 'sequence '.$sequence, array_values(array_diff(array_keys($this->matrix->sequences()), $sequences))),
        ];

        if ($unknown !== [] || $missing !== []) {
            throw new DatabaseRefused(sprintf(
                'The schema differs from the privilege matrix; nothing was granted. Not in the matrix: %s. Missing: %s.',
                $unknown === [] ? 'none' : implode(', ', $unknown),
                $missing === [] ? 'none' : implode(', ', $missing),
            ));
        }

        return [$tables, $sequences];
    }

    /**
     * @return 'SELECT'|'INSERT'|'UPDATE'|'DELETE'
     */
    private static function keyword(string $privilege): string
    {
        return match ($privilege) {
            'SELECT' => 'SELECT',
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => throw new DatabaseRefused(sprintf('No table privilege %s in the matrix.', $privilege)),
        };
    }

    /**
     * @param  array<int, mixed>  $log
     * @return list<string>
     */
    private static function queries(array $log): array
    {
        $queries = [];

        foreach ($log as $entry) {
            if (is_array($entry) && is_string($entry['query'] ?? null)) {
                $queries[] = $entry['query'];
            }
        }

        return $queries;
    }
}
