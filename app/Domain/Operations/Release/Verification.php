<?php

namespace App\Domain\Operations\Release;

use App\Database\Identifier;
use App\Database\PrivilegeMatrix;
use App\Domain\Operations\Backup\BackupRunner;
use Illuminate\Database\Connection;

/**
 * The verification queries of RELEASE_PROCESS REL-20 that the migration job runs
 * after the migrations (TASK-014 scope item 4), read only: the row counts of the
 * tables the restore compares too (BACKUP_RECOVERY section 5), because a session,
 * a cache entry or an old log line may leave at any time; and that no constraint
 * or index was left invalid. db:check-privileges and reconciliation:run are their
 * own commands, which the job runs.
 */
final class Verification
{
    /**
     * The rows of each counted table that exists now.
     *
     * @return array<string, int>
     */
    public function counts(Connection $connection): array
    {
        $counts = [];

        foreach (PrivilegeMatrix::load()->tables() as $table => $entry) {
            if (! in_array($entry['class'], BackupRunner::COUNTED_CLASSES, true)) {
                continue;
            }

            if ($connection->scalar('select pg_catalog.to_regclass(?) is not null', ['public.'.$table]) !== true) {
                continue;
            }

            $count = $connection->scalar('select count(*) from '.Identifier::quote($table));
            $counts[$table] = is_numeric($count) ? (int) $count : 0;
        }

        return $counts;
    }

    /**
     * What the verification found wrong: a counted table that lost rows, unless an
     * approved destructive migration ran, and every constraint or index left
     * invalid. Each is one line that holds a name and numbers only.
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return list<string>
     */
    public function findings(Connection $connection, array $before, array $after, bool $destructiveApproved): array
    {
        $findings = [];

        if (! $destructiveApproved) {
            foreach ($before as $table => $count) {
                if (($after[$table] ?? 0) < $count) {
                    $findings[] = sprintf('ROWS_LOST %s %d %d', $table, $count, $after[$table] ?? 0);
                }
            }
        }

        $constraints = $connection->select(<<<'SQL'
            select r.relname as "table", c.conname as "name"
            from pg_catalog.pg_constraint c
            join pg_catalog.pg_class r on r.oid operator(pg_catalog.=) c.conrelid
            join pg_catalog.pg_namespace n on n.oid operator(pg_catalog.=) r.relnamespace
            where n.nspname operator(pg_catalog.=) 'public' and not c.convalidated
            order by r.relname, c.conname
            SQL);

        foreach ($constraints as $constraint) {
            $findings[] = 'CONSTRAINT_NOT_VALID '.self::nameOf($constraint);
        }

        $indexes = $connection->select(<<<'SQL'
            select r.relname as "table", x.relname as "name"
            from pg_catalog.pg_index i
            join pg_catalog.pg_class x on x.oid operator(pg_catalog.=) i.indexrelid
            join pg_catalog.pg_class r on r.oid operator(pg_catalog.=) i.indrelid
            join pg_catalog.pg_namespace n on n.oid operator(pg_catalog.=) r.relnamespace
            where n.nspname operator(pg_catalog.=) 'public' and not i.indisvalid
            order by r.relname, x.relname
            SQL);

        foreach ($indexes as $index) {
            $findings[] = 'INDEX_NOT_VALID '.self::nameOf($index);
        }

        return $findings;
    }

    private static function nameOf(object $row): string
    {
        $values = (array) $row;
        $table = $values['table'] ?? '';
        $name = $values['name'] ?? '';

        return (is_string($table) ? $table : '').'.'.(is_string($name) ? $name : '');
    }
}
