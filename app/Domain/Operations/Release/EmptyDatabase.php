<?php

namespace App\Domain\Operations\Release;

use Illuminate\Database\Connection;

/**
 * Whether a database is truly empty, the only database on which the first
 * installation may go without a backup (RELEASE_PROCESS REL-23; the Owner's D-116).
 * It reads the catalog alone and never takes the operator's word: what the setup of
 * ENV-30 leaves (the schema public, plpgsql and pg_trgm with its members) is all that
 * may exist. Whatever else is found is named by its kind, and an answer it cannot read
 * counts as found, so the check fails closed.
 */
final class EmptyDatabase
{
    /** The kinds of object, each a column of the query. */
    private const array KINDS = ['schemas', 'relations', 'functions', 'operators', 'types', 'extensions', 'large_objects', 'other_objects'];

    /**
     * Every object that the server's own initialization made has an oid below 16384
     * (FirstNormalObjectId), so an object at or above it was made later, in any
     * schema; a temporary schema belongs to a session and holds nothing that is kept.
     * An implicit array type has no membership of its own in the extension of its
     * element (pg_trgm's _gtrgm), and a relation's row type is found with the relation.
     */
    private const string QUERY = <<<'SQL'
        select
            exists (
                select from pg_catalog.pg_namespace n
                where not (n.nspname operator(pg_catalog.=) any (array['public', 'pg_catalog', 'information_schema', 'pg_toast']::pg_catalog.name[]))
                  and n.nspname operator(pg_catalog.!~) '^pg_(temp|toast_temp)_[0-9]+$'
            ) as schemas,
            exists (
                select from pg_catalog.pg_class c
                join pg_catalog.pg_namespace n on n.oid operator(pg_catalog.=) c.relnamespace
                where c.oid operator(pg_catalog.>=) 16384
                  and n.nspname operator(pg_catalog.!~) '^pg_(temp|toast_temp)_[0-9]+$'
            ) as relations,
            exists (
                select from pg_catalog.pg_proc p
                where p.oid operator(pg_catalog.>=) 16384
                  and not exists (
                      select from pg_catalog.pg_depend d
                      where d.classid operator(pg_catalog.=) 'pg_catalog.pg_proc'::pg_catalog.regclass
                        and d.objid operator(pg_catalog.=) p.oid
                        and d.deptype operator(pg_catalog.=) 'e'
                  )
            ) as functions,
            exists (
                select from pg_catalog.pg_operator o
                where o.oid operator(pg_catalog.>=) 16384
                  and not exists (
                      select from pg_catalog.pg_depend d
                      where d.classid operator(pg_catalog.=) 'pg_catalog.pg_operator'::pg_catalog.regclass
                        and d.objid operator(pg_catalog.=) o.oid
                        and d.deptype operator(pg_catalog.=) 'e'
                  )
            ) as operators,
            exists (
                select from pg_catalog.pg_type t
                join pg_catalog.pg_namespace n on n.oid operator(pg_catalog.=) t.typnamespace
                where t.oid operator(pg_catalog.>=) 16384
                  and n.nspname operator(pg_catalog.!~) '^pg_(temp|toast_temp)_[0-9]+$'
                  and not exists (select from pg_catalog.pg_type e where e.typarray operator(pg_catalog.=) t.oid)
                  and not exists (
                      select from pg_catalog.pg_depend d
                      where d.classid operator(pg_catalog.=) 'pg_catalog.pg_type'::pg_catalog.regclass
                        and d.objid operator(pg_catalog.=) t.oid
                        and d.deptype operator(pg_catalog.=) 'e'
                  )
            ) as types,
            exists (
                select from pg_catalog.pg_extension x
                where not (x.extname operator(pg_catalog.=) any (array['plpgsql', 'pg_trgm']::pg_catalog.name[]))
            ) as extensions,
            exists (select from pg_catalog.pg_largeobject_metadata) as large_objects,
            exists (
                select from (
                    select 'pg_catalog.pg_collation'::pg_catalog.regclass, c.oid from pg_catalog.pg_collation c
                    union all select 'pg_catalog.pg_conversion'::pg_catalog.regclass, c.oid from pg_catalog.pg_conversion c
                    union all select 'pg_catalog.pg_ts_config'::pg_catalog.regclass, c.oid from pg_catalog.pg_ts_config c
                    union all select 'pg_catalog.pg_ts_dict'::pg_catalog.regclass, c.oid from pg_catalog.pg_ts_dict c
                    union all select 'pg_catalog.pg_ts_parser'::pg_catalog.regclass, c.oid from pg_catalog.pg_ts_parser c
                    union all select 'pg_catalog.pg_ts_template'::pg_catalog.regclass, c.oid from pg_catalog.pg_ts_template c
                    union all select 'pg_catalog.pg_opclass'::pg_catalog.regclass, c.oid from pg_catalog.pg_opclass c
                    union all select 'pg_catalog.pg_opfamily'::pg_catalog.regclass, c.oid from pg_catalog.pg_opfamily c
                    union all select 'pg_catalog.pg_am'::pg_catalog.regclass, c.oid from pg_catalog.pg_am c
                    union all select 'pg_catalog.pg_language'::pg_catalog.regclass, c.oid from pg_catalog.pg_language c
                    union all select 'pg_catalog.pg_cast'::pg_catalog.regclass, c.oid from pg_catalog.pg_cast c
                    union all select 'pg_catalog.pg_transform'::pg_catalog.regclass, c.oid from pg_catalog.pg_transform c
                    union all select 'pg_catalog.pg_foreign_data_wrapper'::pg_catalog.regclass, c.oid from pg_catalog.pg_foreign_data_wrapper c
                    union all select 'pg_catalog.pg_foreign_server'::pg_catalog.regclass, c.oid from pg_catalog.pg_foreign_server c
                    union all select 'pg_catalog.pg_publication'::pg_catalog.regclass, c.oid from pg_catalog.pg_publication c
                    union all select 'pg_catalog.pg_event_trigger'::pg_catalog.regclass, c.oid from pg_catalog.pg_event_trigger c
                ) as o (classid, id)
                where o.id operator(pg_catalog.>=) 16384
                  and not exists (
                      select from pg_catalog.pg_depend d
                      where d.classid operator(pg_catalog.=) o.classid
                        and d.objid operator(pg_catalog.=) o.id
                        and d.deptype operator(pg_catalog.=) 'e'
                  )
            ) as other_objects
        SQL;

    /**
     * The kinds of object that this database holds beyond what the setup leaves; an
     * empty list means that it is truly empty.
     *
     * @return list<string>
     */
    public static function holds(Connection $connection): array
    {
        $found = (array) $connection->selectOne(self::QUERY);

        return array_values(array_filter(self::KINDS, static fn (string $kind): bool => ($found[$kind] ?? null) !== false));
    }
}
