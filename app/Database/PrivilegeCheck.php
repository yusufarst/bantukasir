<?php

namespace App\Database;

use Illuminate\Database\ConnectionInterface;

/**
 * The read-only comparison of the database's catalogue with SCHEMA section 10
 * and the privilege matrix (EXC-43, SCH-45; RELEASE_PROCESS RB-21 step 2;
 * TASK-004). It returns every difference as one line; none means the check
 * passes. It reads the catalogue only, in one read-only snapshot, so any role
 * of the database can run it; and it searches pg_catalog only, so that no
 * function or operator of the schema public, which the migration role owns,
 * can change its result.
 *
 * What it compares, for the current database:
 *
 *  - the three roles, found by their mark (DatabaseRoles): exactly one of each;
 *    LOGIN and none of SUPERUSER, CREATEDB, CREATEROLE, REPLICATION and
 *    BYPASSRLS; no membership in or of any role (SCH-40 to SCH-42);
 *  - their settings and the database's: exactly config('database.role_settings')
 *    and config('database.database_settings'), and none for these roles or for
 *    all roles that applies in every database (ENVIRONMENTS ENV-31, ENV-33);
 *  - the database: owned by a superuser, never by one of them; CONNECT for
 *    exactly the three roles, and nothing else for them or for PUBLIC (SCH-43);
 *  - the schemas: public only, owned by the migration role, with USAGE for the
 *    runtime and backup roles and nothing for PUBLIC (SCH-41, SCH-43);
 *  - every relation of public that belongs to no extension: a table of the
 *    matrix or a sequence, owned by the migration role, whose privileges for
 *    anyone but the owner are exactly the matrix's, column privileges included;
 *    no view, materialized view, foreign table or composite type; every table
 *    and sequence of the matrix present (SCH-44, SCH-45);
 *  - no function or operator in public outside an extension (SCH-06); the
 *    extensions are plpgsql and pg_trgm, the latter owned by the migration role
 *    (ENV-33);
 *  - no default privileges (SCH-45); and, from the shared dependencies, that
 *    the runtime and backup roles own nothing and hold rights only on this
 *    database, the schema public and its relations — on another database only
 *    where this one and it are both a local or a test database, which share
 *    their roles (database/setup/local.sh) (SCH-41, SCH-42).
 */
final class PrivilegeCheck
{
    public function __construct(private readonly PrivilegeMatrix $matrix) {}

    /**
     * @return list<string> the differences; empty when the check passes
     */
    public function run(ConnectionInterface $connection): array
    {
        if ($connection->transactionLevel() !== 0) {
            throw new DatabaseRefused('The privilege check reads one snapshot of its own, outside any transaction.');
        }

        $connection->beginTransaction();

        try {
            $connection->statement('set transaction isolation level repeatable read, read only');
            $connection->statement('set local search_path = pg_catalog, pg_temp');

            return $this->compareAll($connection);
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * @return list<string>
     */
    private function compareAll(ConnectionInterface $connection): array
    {
        $findings = [];

        foreach (DatabaseRoles::marks($connection) as [$name, $kind]) {
            if (! in_array($kind, DatabaseRoles::KINDS, true)) {
                $findings[] = sprintf('role %s: marked bantukasir.role=%s, which is no logical role', $name, $kind);
            }
        }

        try {
            $roles = DatabaseRoles::of($connection);
        } catch (DatabaseRefused $refusal) {
            return [...$findings, 'roles: '.$refusal->getMessage()];
        }

        return [
            ...$findings,
            ...$this->roleAttributes($connection, $roles),
            ...$this->settings($connection, $roles),
            ...$this->database($connection, $roles),
            ...$this->schemas($connection, $roles),
            ...$this->relations($connection, $roles),
            ...$this->routinesAndExtensions($connection, $roles),
            ...$this->ownershipAndDefaults($connection, $roles),
        ];
    }

    /**
     * @return list<string>
     */
    private function roleAttributes(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $findings = [];
        $rows = $connection->select(
            'select rolname, rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls from pg_catalog.pg_roles where rolname in (?, ?, ?) order by rolname',
            [$roles->migration, $roles->runtime, $roles->backup],
        );

        foreach ($rows as $row) {
            $role = (array) $row;
            $name = self::text($role['rolname']);

            if ($role['rolcanlogin'] !== true) {
                $findings[] = sprintf('role %s: cannot log in', $name);
            }

            foreach (['rolsuper' => 'SUPERUSER', 'rolcreatedb' => 'CREATEDB', 'rolcreaterole' => 'CREATEROLE', 'rolreplication' => 'REPLICATION', 'rolbypassrls' => 'BYPASSRLS'] as $column => $attribute) {
                if ($role[$column] !== false) {
                    $findings[] = sprintf('role %s: has %s', $name, $attribute);
                }
            }
        }

        $memberships = $connection->select(
            'select pg_catalog.pg_get_userbyid(roleid) as role, pg_catalog.pg_get_userbyid(member) as member from pg_catalog.pg_auth_members where pg_catalog.pg_get_userbyid(roleid) in (?, ?, ?) or pg_catalog.pg_get_userbyid(member) in (?, ?, ?) order by 1, 2',
            [$roles->migration, $roles->runtime, $roles->backup, $roles->migration, $roles->runtime, $roles->backup],
        );

        foreach ($memberships as $row) {
            $membership = (array) $row;
            $findings[] = sprintf('role %s: is a member of %s', self::text($membership['member']), self::text($membership['role']));
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function settings(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $actual = [];
        $rows = $connection->select(<<<'SQL'
            select case when s.setdatabase = 0 then '*' else 'database' end as scope,
                   coalesce(r.rolname, '*') as role,
                   split_part(c.setting, '=', 1) as name,
                   substr(c.setting, length(split_part(c.setting, '=', 1)) + 2) as value
            from pg_catalog.pg_db_role_setting s
            left join pg_catalog.pg_roles r on r.oid = s.setrole
            cross join lateral pg_catalog.unnest(s.setconfig) as c(setting)
            where (s.setdatabase = (select oid from pg_catalog.pg_database where datname = pg_catalog.current_database())
                   and (s.setrole = 0 or r.rolname in (?, ?, ?)))
               or (s.setdatabase = 0 and (s.setrole = 0 or r.rolname in (?, ?, ?)))
            order by 1, 2, 3
            SQL, [$roles->migration, $roles->runtime, $roles->backup, $roles->migration, $roles->runtime, $roles->backup]);

        foreach ($rows as $row) {
            $setting = (array) $row;
            $actual[self::text($setting['scope']).' '.self::text($setting['role'])][self::text($setting['name'])] = self::text($setting['value']);
        }

        $expected = ['database *' => self::stringMap(config('database.database_settings'))];

        foreach (DatabaseRoles::KINDS as $kind) {
            $expected['database '.$this->name($roles, $kind)] = self::stringMap(config('database.role_settings.'.$kind));
        }

        $findings = [];

        foreach ($expected + $actual as $scope => $ignored) {
            $want = $expected[$scope] ?? [];
            $have = $actual[$scope] ?? [];

            foreach ($want + $have as $name => $value) {
                if (($have[$name] ?? null) !== ($want[$name] ?? null)) {
                    $findings[] = sprintf(
                        'settings of %s: %s is %s, expected %s',
                        match (true) {
                            $scope === '* *' => 'all roles in every database',
                            str_starts_with($scope, '* ') => 'role '.substr($scope, 2).' in every database',
                            $scope === 'database *' => 'the database',
                            default => 'role '.substr($scope, 9).' in this database',
                        },
                        $name,
                        json_encode($have[$name] ?? null),
                        json_encode($want[$name] ?? null),
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function database(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $findings = [];
        $owner = (array) $connection->selectOne(<<<'SQL'
            select pg_catalog.pg_get_userbyid(d.datdba) as name, r.rolsuper as superuser
            from pg_catalog.pg_database d
            join pg_catalog.pg_roles r on r.oid = d.datdba
            where d.datname = pg_catalog.current_database()
            SQL);
        $name = self::text($owner['name'] ?? null);

        if (in_array($name, [$roles->migration, $roles->runtime, $roles->backup], true)) {
            $findings[] = sprintf('database: owned by %s, a role of the application', $name);
        } elseif (($owner['superuser'] ?? null) !== true) {
            $findings[] = sprintf('database: owned by %s, which is not a superuser and could change the marker', $name);
        }

        $grants = $this->grants($connection->select(<<<'SQL'
            select case when a.grantee = 0 then 'PUBLIC' else pg_catalog.pg_get_userbyid(a.grantee) end as grantee,
                   a.privilege_type, a.is_grantable
            from pg_catalog.pg_database d
            cross join lateral pg_catalog.aclexplode(coalesce(d.datacl, pg_catalog.acldefault('d', d.datdba))) as a
            where d.datname = pg_catalog.current_database() and a.grantee <> d.datdba
            SQL));

        $expected = [
            $roles->migration => ['CONNECT'],
            $roles->runtime => ['CONNECT'],
            $roles->backup => ['CONNECT'],
        ];

        return [...$findings, ...self::compare('database', $grants, $expected)];
    }

    /**
     * @return list<string>
     */
    private function schemas(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $findings = [];
        $schemas = $connection->select(<<<'SQL'
            select nspname, pg_catalog.pg_get_userbyid(nspowner) as owner
            from pg_catalog.pg_namespace
            where nspname not in ('pg_catalog', 'information_schema', 'pg_toast')
              and nspname !~ '^pg_(temp|toast_temp)_[0-9]+$'
            order by nspname
            SQL);

        $public = false;

        foreach ($schemas as $row) {
            $schema = (array) $row;
            $name = self::text($schema['nspname']);

            if ($name !== 'public') {
                $findings[] = sprintf('schema %s: not part of the schema (only public)', $name);

                continue;
            }

            $public = true;

            if (self::text($schema['owner']) !== $roles->migration) {
                $findings[] = sprintf('schema public: owned by %s, not the migration role', self::text($schema['owner']));
            }
        }

        if (! $public) {
            return [...$findings, 'schema public: missing'];
        }

        $grants = $this->grants($connection->select(<<<'SQL'
            select case when a.grantee = 0 then 'PUBLIC' else pg_catalog.pg_get_userbyid(a.grantee) end as grantee,
                   a.privilege_type, a.is_grantable
            from pg_catalog.pg_namespace n
            cross join lateral pg_catalog.aclexplode(coalesce(n.nspacl, pg_catalog.acldefault('n', n.nspowner))) as a
            where n.nspname = 'public' and a.grantee <> n.nspowner
            SQL));

        return [...$findings, ...self::compare('schema public', $grants, [
            $roles->runtime => ['USAGE'],
            $roles->backup => ['USAGE'],
        ])];
    }

    /**
     * @return list<string>
     */
    private function relations(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $findings = [];
        $relations = $connection->select(<<<'SQL'
            select c.oid, c.relname, c.relkind, pg_catalog.pg_get_userbyid(c.relowner) as owner
            from pg_catalog.pg_class c
            join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public'
              and not exists (select from pg_catalog.pg_depend d
                              where d.classid = 'pg_catalog.pg_class'::regclass and d.objid = c.oid and d.deptype = 'e')
            order by c.relname
            SQL);

        $present = ['table' => [], 'sequence' => []];

        foreach ($relations as $row) {
            $relation = (array) $row;
            $name = self::text($relation['relname']);
            $kind = self::text($relation['relkind']);
            $label = match ($kind) {
                'r', 'p' => 'table',
                'S' => 'sequence',
                'i', 'I' => 'index',
                default => 'relation',
            };

            if (self::text($relation['owner']) !== $roles->migration) {
                $findings[] = sprintf('%s %s: owned by %s, not the migration role', $label, $name, self::text($relation['owner']));
            }

            if ($label === 'relation') {
                $findings[] = sprintf('relation %s: of kind %s, which the schema does not use', $name, $kind);
            } elseif ($label === 'table') {
                $present['table'][] = $name;
                $findings = [...$findings, ...$this->table($connection, $roles, $name)];
            } elseif ($label === 'sequence') {
                $present['sequence'][] = $name;
                $findings = [...$findings, ...$this->sequence($connection, $roles, $name)];
            }
        }

        foreach (['table' => array_keys($this->matrix->tables()), 'sequence' => array_keys($this->matrix->sequences())] as $label => $names) {
            foreach ($names as $name) {
                if (! in_array($name, $present[$label], true)) {
                    $findings[] = sprintf('%s %s: in the privilege matrix but missing', $label, $name);
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function table(ConnectionInterface $connection, DatabaseRoles $roles, string $table): array
    {
        if (! $this->matrix->has($table)) {
            return [sprintf('table %s: not in the privilege matrix (database/privileges.php)', $table)];
        }

        $grants = $this->grants($connection->select(<<<'SQL'
            select case when a.grantee = 0 then 'PUBLIC' else pg_catalog.pg_get_userbyid(a.grantee) end as grantee,
                   a.privilege_type, a.is_grantable
            from pg_catalog.pg_class c
            join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            cross join lateral pg_catalog.aclexplode(coalesce(c.relacl, pg_catalog.acldefault('r', c.relowner))) as a
            where n.nspname = 'public' and c.relname = ? and a.grantee <> c.relowner
            SQL, [$table]));

        $findings = self::compare('table '.$table, $grants, [
            $roles->runtime => $this->matrix->tablePrivileges($table, 'runtime'),
            $roles->backup => $this->matrix->tablePrivileges($table, 'backup'),
        ]);

        $columns = $this->grants($connection->select(<<<'SQL'
            select case when a.grantee = 0 then 'PUBLIC' else pg_catalog.pg_get_userbyid(a.grantee) end as grantee,
                   a.privilege_type || ' (' || att.attname || ')' as privilege_type, a.is_grantable
            from pg_catalog.pg_class c
            join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            join pg_catalog.pg_attribute att on att.attrelid = c.oid and att.attnum > 0 and not att.attisdropped
            cross join lateral pg_catalog.aclexplode(att.attacl) as a
            where n.nspname = 'public' and c.relname = ? and att.attacl is not null and a.grantee <> c.relowner
            SQL, [$table]));

        $expected = array_map(fn (string $column): string => 'UPDATE ('.$column.')', $this->matrix->updateColumns($table));

        return [...$findings, ...self::compare('columns of table '.$table, $columns, $expected === [] ? [] : [$roles->runtime => $expected])];
    }

    /**
     * @return list<string>
     */
    private function sequence(ConnectionInterface $connection, DatabaseRoles $roles, string $sequence): array
    {
        $owned = $connection->scalar(<<<'SQL'
            select exists (select from pg_catalog.pg_depend d
                           join pg_catalog.pg_class c on c.oid = d.objid
                           join pg_catalog.pg_namespace n on n.oid = c.relnamespace
                           where d.classid = 'pg_catalog.pg_class'::regclass and d.refclassid = 'pg_catalog.pg_class'::regclass
                             and d.deptype in ('a', 'i') and n.nspname = 'public' and c.relname = ?)
            SQL, [$sequence]);

        $listed = array_key_exists($sequence, $this->matrix->sequences());

        if ($owned !== true && ! $listed) {
            return [sprintf('sequence %s: owned by no column and not in the privilege matrix', $sequence)];
        }

        $grants = $this->grants($connection->select(<<<'SQL'
            select case when a.grantee = 0 then 'PUBLIC' else pg_catalog.pg_get_userbyid(a.grantee) end as grantee,
                   a.privilege_type, a.is_grantable
            from pg_catalog.pg_class c
            join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            cross join lateral pg_catalog.aclexplode(coalesce(c.relacl, pg_catalog.acldefault('s', c.relowner))) as a
            where n.nspname = 'public' and c.relname = ? and a.grantee <> c.relowner
            SQL, [$sequence]));

        $expected = [$roles->backup => ['SELECT']];

        if ($this->matrix->sequences()[$sequence] ?? false) {
            $expected[$roles->runtime] = ['USAGE'];
        }

        return self::compare('sequence '.$sequence, $grants, $expected);
    }

    /**
     * @return list<string>
     */
    private function routinesAndExtensions(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $findings = [];

        $routines = $connection->select(<<<'SQL'
            select p.proname, pg_catalog.pg_get_function_identity_arguments(p.oid) as arguments
            from pg_catalog.pg_proc p
            join pg_catalog.pg_namespace n on n.oid = p.pronamespace
            where n.nspname = 'public'
              and not exists (select from pg_catalog.pg_depend d
                              where d.classid = 'pg_catalog.pg_proc'::regclass and d.objid = p.oid and d.deptype = 'e')
            order by 1, 2
            SQL);

        foreach ($routines as $row) {
            $routine = (array) $row;
            $findings[] = sprintf('function %s(%s): the schema has no function (SCH-06)', self::text($routine['proname']), self::text($routine['arguments']));
        }

        $operators = $connection->select(<<<'SQL'
            select o.oid::pg_catalog.regoperator::text as operator
            from pg_catalog.pg_operator o
            join pg_catalog.pg_namespace n on n.oid = o.oprnamespace
            where n.nspname = 'public'
              and not exists (select from pg_catalog.pg_depend d
                              where d.classid = 'pg_catalog.pg_operator'::regclass and d.objid = o.oid and d.deptype = 'e')
            order by 1
            SQL);

        foreach ($operators as $row) {
            $findings[] = sprintf('operator %s: the schema has no operator (SCH-06)', self::text(((array) $row)['operator']));
        }

        $extensions = $connection->select(
            'select e.extname, pg_catalog.pg_get_userbyid(e.extowner) as owner, n.nspname from pg_catalog.pg_extension e join pg_catalog.pg_namespace n on n.oid = e.extnamespace order by e.extname'
        );

        $trigram = false;

        foreach ($extensions as $row) {
            $extension = (array) $row;
            $name = self::text($extension['extname']);

            if ($name === 'plpgsql') {
                continue;
            }

            if ($name !== 'pg_trgm') {
                $findings[] = sprintf('extension %s: not used by the schema', $name);

                continue;
            }

            $trigram = true;

            if (self::text($extension['owner']) !== $roles->migration || self::text($extension['nspname']) !== 'public') {
                $findings[] = sprintf('extension pg_trgm: owned by %s in schema %s, not by the migration role in public (ENV-33)', self::text($extension['owner']), self::text($extension['nspname']));
            }
        }

        if (! $trigram) {
            $findings[] = 'extension pg_trgm: missing (ENV-33)';
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function ownershipAndDefaults(ConnectionInterface $connection, DatabaseRoles $roles): array
    {
        $findings = [];

        $defaults = $connection->scalar('select count(*) from pg_catalog.pg_default_acl');

        if ($defaults !== 0) {
            $findings[] = sprintf('default privileges: %s entries, none expected (SCH-45)', json_encode($defaults));
        }

        // Every object that names the runtime or the backup role, in this
        // database or among the cluster's shared objects: as owner ('o'), in
        // its rights ('a'), in a policy ('r') or initial privileges ('i'). The
        // rights on this database, the schema public and its relations are
        // compared exactly above; anything else is a difference.
        $dependencies = $connection->select(<<<'SQL'
            with here as (select oid from pg_catalog.pg_database where datname = pg_catalog.current_database())
            select pg_catalog.pg_get_userbyid(s.refobjid) as role,
                   s.deptype::text as kind,
                   coalesce(pg_catalog.pg_describe_object(s.classid, s.objid, s.objsubid), s.classid::regclass::text || ' ' || s.objid::text) as object,
                   case
                       when s.deptype <> 'a' then false
                       when s.dbid = 0 and s.classid = 'pg_catalog.pg_database'::regclass and s.objid = (select oid from here) then true
                       when s.dbid = 0 and s.classid = 'pg_catalog.pg_database'::regclass
                            and coalesce(pg_catalog.shobj_description((select oid from here), 'pg_database'), '') in ('local', 'test')
                            and coalesce(pg_catalog.shobj_description(s.objid, 'pg_database'), '') in ('local', 'test') then true
                       when s.dbid = (select oid from here) and s.classid = 'pg_catalog.pg_namespace'::regclass
                            and s.objid = 'public'::regnamespace::oid then true
                       when s.dbid = (select oid from here) and s.classid = 'pg_catalog.pg_class'::regclass
                            and exists (select from pg_catalog.pg_class c
                                        where c.oid = s.objid and c.relnamespace = 'public'::regnamespace) then true
                       else false
                   end as expected
            from pg_catalog.pg_shdepend s
            where (s.dbid = 0 or s.dbid = (select oid from here))
              and s.refclassid = 'pg_catalog.pg_authid'::regclass
              and pg_catalog.pg_get_userbyid(s.refobjid) in (?, ?)
            order by 1, 2, 3
            SQL, [$roles->runtime, $roles->backup]);

        foreach ($dependencies as $row) {
            $dependency = (array) $row;

            if ($dependency['expected'] === true) {
                continue;
            }

            $findings[] = sprintf(
                'role %s: %s %s, outside SCHEMA section 10',
                self::text($dependency['role']),
                match (self::text($dependency['kind'])) {
                    'o' => 'owns',
                    'a' => 'holds rights on',
                    'r' => 'is named in the policy',
                    default => 'is named by',
                },
                self::text($dependency['object']),
            );
        }

        return $findings;
    }

    /**
     * Grants read with aclexplode, as grantee => privileges; a grant option
     * is shown as "PRIVILEGE WITH GRANT OPTION", which no role of the
     * application holds.
     *
     * @param  array<int, object>  $rows
     * @return array<string, list<string>>
     */
    private function grants(array $rows): array
    {
        $grants = [];

        foreach ($rows as $row) {
            $grant = (array) $row;
            $grants[self::text($grant['grantee'])][] = self::text($grant['privilege_type']).($grant['is_grantable'] === true ? ' WITH GRANT OPTION' : '');
        }

        return $grants;
    }

    /**
     * @param  array<string, list<string>>  $actual
     * @param  array<string, list<string>>  $expected
     * @return list<string>
     */
    private static function compare(string $object, array $actual, array $expected): array
    {
        $findings = [];

        foreach ($expected + $actual as $grantee => $ignored) {
            $have = $actual[$grantee] ?? [];
            $want = $expected[$grantee] ?? [];

            foreach (array_diff($have, $want) as $privilege) {
                $findings[] = sprintf('%s: %s holds %s, not in SCHEMA section 10', $object, $grantee, $privilege);
            }

            foreach (array_diff($want, $have) as $privilege) {
                $findings[] = sprintf('%s: %s lacks %s', $object, $grantee, $privilege);
            }
        }

        sort($findings);

        return $findings;
    }

    private function name(DatabaseRoles $roles, string $kind): string
    {
        return match ($kind) {
            'migration' => $roles->migration,
            'runtime' => $roles->runtime,
            default => $roles->backup,
        };
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $values): array
    {
        $map = [];

        foreach (is_array($values) ? $values : [] as $name => $value) {
            $map[(string) $name] = is_string($value) ? $value : (string) json_encode($value);
        }

        return $map;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : (string) json_encode($value);
    }
}
