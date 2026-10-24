<?php

namespace App\Database;

use Illuminate\Database\ConnectionInterface;

/**
 * The migration, runtime and backup roles of the current database (SCHEMA
 * section 10). Their names are the operator's and are never written in the
 * repository or in configuration (ENVIRONMENTS ENV-30): the setup marks each
 * role, in this database only, with its logical role in the setting
 * bantukasir.role (database/setup/setup.sql), which only a superuser can set
 * or remove — a role cannot change its own mark. Migrations, the grants
 * procedure and the privilege check read the names from there.
 */
final class DatabaseRoles
{
    public const array KINDS = ['migration', 'runtime', 'backup'];

    private function __construct(
        public readonly string $migration,
        public readonly string $runtime,
        public readonly string $backup,
    ) {}

    /**
     * @throws DatabaseRefused when a logical role is missing or marked twice
     */
    public static function of(ConnectionInterface $connection): self
    {
        $found = [];

        foreach (self::marks($connection) as [$name, $kind]) {
            $found[$kind][] = $name;
        }

        $names = [];

        foreach (self::KINDS as $kind) {
            $roles = $found[$kind] ?? [];

            if (count($roles) !== 1) {
                throw new DatabaseRefused(sprintf(
                    'This database has %d roles marked bantukasir.role=%s, not exactly one (database/setup/setup.sql).',
                    count($roles),
                    $kind,
                ));
            }

            $names[$kind] = $roles[0];
        }

        return new self($names['migration'], $names['runtime'], $names['backup']);
    }

    /**
     * The roles marked in this database, as [name, logical role] pairs, in the
     * order of their names; a mark that is not one of KINDS is returned too,
     * so that the privilege check reports it.
     *
     * @return list<array{string, string}>
     */
    public static function marks(ConnectionInterface $connection): array
    {
        // Every function and operator is pg_catalog's by name, so that nothing
        // of the schema public can change the answer, also in a session that
        // searches it (setup.sql).
        $rows = $connection->select(<<<'SQL'
            select r.rolname as name, pg_catalog.substr(c.setting, 17) as kind
            from pg_catalog.pg_db_role_setting s
            join pg_catalog.pg_database d on d.oid operator(pg_catalog.=) s.setdatabase
            join pg_catalog.pg_roles r on r.oid operator(pg_catalog.=) s.setrole
            cross join lateral pg_catalog.unnest(s.setconfig) as c(setting)
            where d.datname operator(pg_catalog.=) pg_catalog.current_database()
              and pg_catalog.starts_with(c.setting, 'bantukasir.role=')
            order by r.rolname, kind
            SQL);

        $marks = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $marks[] = [self::text($values['name'] ?? null), self::text($values['kind'] ?? null)];
        }

        return $marks;
    }

    /**
     * Refuses unless the connection's role is the one marked with this
     * logical role.
     *
     * @param  'migration'|'runtime'|'backup'  $kind
     *
     * @throws DatabaseRefused
     */
    public static function requireCurrent(ConnectionInterface $connection, string $kind): self
    {
        $roles = self::of($connection);
        $current = self::text($connection->scalar('select current_user'));
        $expected = match ($kind) {
            'migration' => $roles->migration,
            'runtime' => $roles->runtime,
            'backup' => $roles->backup,
        };

        if ($current !== $expected) {
            throw new DatabaseRefused(sprintf(
                'This connection is the role %s; the operation needs the %s role of this database (SCHEMA section 10).',
                $current,
                $kind,
            ));
        }

        return $roles;
    }

    private static function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DatabaseRefused('The catalogue returned a role name that is not text.');
        }

        return $value;
    }
}
