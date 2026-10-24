-- The state that database/setup/setup.sql manages, printed as text so that two
-- runs can be compared (database/setup/selftest.sh; TASK-004). Read only. Run
-- as a superuser with psql -X -At -v db=<database>, from the maintenance
-- database. Passwords are shown only as set or not set. Each session searches
-- pg_catalog only, as setup.sql's do, so that no object of the schema public
-- runs with the superuser's rights.

\set ON_ERROR_STOP on
SET search_path = pg_catalog, pg_temp;

SELECT 'database', d.datname, pg_catalog.pg_get_userbyid(d.datdba),
       coalesce(pg_catalog.shobj_description(d.oid, 'pg_database'), '-'),
       coalesce(d.datacl::text, '-'), pg_catalog.pg_encoding_to_char(d.encoding),
       d.datlocprovider, d.datcollate, d.datctype, d.datistemplate, d.datallowconn
FROM pg_catalog.pg_database d
ORDER BY d.datname;

SELECT 'role', a.rolname, a.rolsuper, a.rolinherit, a.rolcreaterole, a.rolcreatedb, a.rolcanlogin,
       a.rolreplication, a.rolbypassrls, a.rolconnlimit, coalesce(a.rolvaliduntil::text, '-'),
       a.rolpassword IS NOT NULL
FROM pg_catalog.pg_authid a
WHERE a.rolname !~ '^pg_'
ORDER BY a.rolname;

SELECT 'member', pg_catalog.pg_get_userbyid(m.roleid), pg_catalog.pg_get_userbyid(m.member)
FROM pg_catalog.pg_auth_members m
WHERE pg_catalog.pg_get_userbyid(m.roleid) !~ '^pg_'
ORDER BY 2, 3;

SELECT 'setting', coalesce(d.datname, '*'), coalesce(r.rolname, '*'), s.setconfig::text
FROM pg_catalog.pg_db_role_setting s
LEFT JOIN pg_catalog.pg_database d ON d.oid = s.setdatabase
LEFT JOIN pg_catalog.pg_roles r ON r.oid = s.setrole
ORDER BY 2, 3;

\connect :db
SET search_path = pg_catalog, pg_temp;

SELECT 'schema', n.nspname, pg_catalog.pg_get_userbyid(n.nspowner), coalesce(n.nspacl::text, '-')
FROM pg_catalog.pg_namespace n
WHERE n.nspname !~ '^pg_' AND n.nspname <> 'information_schema'
ORDER BY n.nspname;

SELECT 'extension', e.extname, e.extversion, pg_catalog.pg_get_userbyid(e.extowner), e.extnamespace::regnamespace::text
FROM pg_catalog.pg_extension e
ORDER BY e.extname;

SELECT 'relation', c.relname, c.relkind, pg_catalog.pg_get_userbyid(c.relowner), coalesce(c.relacl::text, '-')
FROM pg_catalog.pg_class c
JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
ORDER BY c.relname;

SELECT 'function', p.oid::pg_catalog.regprocedure::text, pg_catalog.pg_get_userbyid(p.proowner)
FROM pg_catalog.pg_proc p
JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
ORDER BY 2;

SELECT 'operator', o.oid::pg_catalog.regoperator::text, pg_catalog.pg_get_userbyid(o.oprowner)
FROM pg_catalog.pg_operator o
JOIN pg_catalog.pg_namespace n ON n.oid = o.oprnamespace
WHERE n.nspname = 'public'
ORDER BY 2;
