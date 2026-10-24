-- BantuKasir database setup (ENVIRONMENTS ENV-30 to ENV-33, SCHEMA section 10;
-- TASK-004). Reviewed as SQL; run with psql, as a superuser of the cluster,
-- connected to its maintenance database:
--
--     psql -X -v ON_ERROR_STOP=1 -d postgres -f database/setup/setup.sql
--
-- Do not add -a, -e, -L or -o: they would copy the statements, passwords
-- included, to the terminal or a file.
--
-- Its input comes from environment variables that the operator sets in his own
-- shell at run time; the script writes none of them anywhere:
--
--     BANTUKASIR_SETUP_DATABASE            the database to create or bring up to date
--     BANTUKASIR_SETUP_MARKER              the environment's name (ENV-04): local, test,
--                                          drill, staging or production
--     BANTUKASIR_SETUP_MIGRATION_ROLE      the role that owns the schema (SCH-40)
--     BANTUKASIR_SETUP_RUNTIME_ROLE        the application's role (SCH-41)
--     BANTUKASIR_SETUP_BACKUP_ROLE         the backup's role (SCH-42)
--     BANTUKASIR_SETUP_MIGRATION_PASSWORD  their passwords; empty only for the markers
--     BANTUKASIR_SETUP_RUNTIME_PASSWORD    local and test, whose clusters accept
--     BANTUKASIR_SETUP_BACKUP_PASSWORD     connections on a private network only
--
-- What it does, idempotently, so that a second run changes nothing:
--
--  1. refuses, before any change, every input or state it cannot bring to the
--     required state without deleting or overwriting something:
--     - an invalid name or marker, or a missing password;
--     - a named role that is a superuser, the current user, a member of a role
--       or has members, is marked as another logical role in another database,
--       or serves another database whose marker is not local or test (only the
--       local and the test database of one cluster may share their roles);
--     - an existing database that is a template, has another encoding, provider
--       or locale, another marker, or an owner that is not a superuser, or that
--       holds no marker yet and is not empty;
--     - inside an existing database: another schema than public; a relation,
--       function or operator in public, outside an extension, that the
--       migration role does not own; an extension other than plpgsql and the
--       migration role's pg_trgm; or a schema public that is not empty while
--       pg_trgm does not exist yet, because pg_trgm's installation script runs
--       as the bootstrap superuser in that schema;
--  2. creates the three roles, or brings existing ones to: LOGIN, NOSUPERUSER,
--     NOCREATEDB, NOCREATEROLE, NOREPLICATION, NOBYPASSRLS, NOINHERIT, with the
--     password given (PASSWORD NULL when it is empty);
--  3. creates the database, owned by the user running this script, with UTF-8,
--     the libc provider and the locale en_US.utf8 (SCH-14), or keeps it;
--  4. sets the marker with COMMENT ON DATABASE (ENV-04); revokes CONNECT and
--     TEMPORARY from PUBLIC and grants CONNECT to the three roles only (SCH-43);
--     sets the database's time zone to UTC (ENV-33);
--  5. sets, for each role in this database, exactly its logical role
--     (bantukasir.role, read by the application's privilege tools) and the
--     settings of ENV-31, which config/database.php names;
--  6. gives the schema public to the migration role, with USAGE only for the
--     runtime and backup roles and nothing for PUBLIC;
--  7. creates pg_trgm as the migration role (ENV-33), which needs CREATE on the
--     database for that moment only.
--
-- The migration role owns the schema public, so it could put functions and
-- operators there. Every session of this script therefore searches pg_catalog
-- only: right after each connection it sets search_path to pg_catalog and
-- pg_temp, which is never searched for functions or operators, so that no
-- object of the schema public ever runs with the superuser's rights.
--
-- It never drops, truncates, deletes or renames anything, and it never touches
-- a table: the tables and their grants come from the migrations (SCH-45).

\set ON_ERROR_STOP on
\set ECHO none
\set ECHO_HIDDEN off
\set QUIET on
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = warning;

-- Keep the passwords out of the server's logs and statistics for this session:
-- they reach the server only in this session's statements (the checks below
-- and ALTER ROLE … PASSWORD), which it then neither logs nor records.
SET log_statement = 'none';
SET log_min_duration_statement = -1;
SET log_min_duration_sample = -1;
SET log_transaction_sample_rate = 0;
SET log_min_error_statement = 'panic';
SET pg_stat_statements.track_utility = off;
SET auto_explain.log_min_duration = -1;

\set bk_admin_database :DBNAME

-- Input -------------------------------------------------------------------------

\getenv bk_database BANTUKASIR_SETUP_DATABASE
\getenv bk_marker BANTUKASIR_SETUP_MARKER
\getenv bk_migration_role BANTUKASIR_SETUP_MIGRATION_ROLE
\getenv bk_runtime_role BANTUKASIR_SETUP_RUNTIME_ROLE
\getenv bk_backup_role BANTUKASIR_SETUP_BACKUP_ROLE
\getenv bk_migration_password BANTUKASIR_SETUP_MIGRATION_PASSWORD
\getenv bk_runtime_password BANTUKASIR_SETUP_RUNTIME_PASSWORD
\getenv bk_backup_password BANTUKASIR_SETUP_BACKUP_PASSWORD

\if :{?bk_database}
\else
\set bk_database ''
\endif
\if :{?bk_marker}
\else
\set bk_marker ''
\endif
\if :{?bk_migration_role}
\else
\set bk_migration_role ''
\endif
\if :{?bk_runtime_role}
\else
\set bk_runtime_role ''
\endif
\if :{?bk_backup_role}
\else
\set bk_backup_role ''
\endif
\if :{?bk_migration_password}
\else
\set bk_migration_password ''
\endif
\if :{?bk_runtime_password}
\else
\set bk_runtime_password ''
\endif
\if :{?bk_backup_password}
\else
\set bk_backup_password ''
\endif

-- 1. Refusals before any change ----------------------------------------------------

\unset bk_refusal
SELECT CASE
    WHEN NOT (SELECT rolsuper FROM pg_catalog.pg_roles WHERE rolname = current_user)
        THEN 'the script must run as a superuser of the cluster'
    WHEN current_setting('server_version_num')::integer / 10000 <> 18
        THEN 'the server is PostgreSQL ' || current_setting('server_version') || ', not the pinned major version 18'
    WHEN :'bk_database' !~ '^[a-z][a-z0-9_]{0,62}$'
        THEN 'BANTUKASIR_SETUP_DATABASE must match ^[a-z][a-z0-9_]{0,62}$'
    WHEN :'bk_database' IN ('postgres', 'template0', 'template1') OR :'bk_database' = :'bk_admin_database'
        THEN 'BANTUKASIR_SETUP_DATABASE names a system database or the database this script runs in'
    WHEN :'bk_marker' NOT IN ('local', 'test', 'drill', 'staging', 'production')
        THEN 'BANTUKASIR_SETUP_MARKER must be local, test, drill, staging or production'
    WHEN :'bk_migration_role' !~ '^[a-z][a-z0-9_]{0,62}$'
      OR :'bk_runtime_role' !~ '^[a-z][a-z0-9_]{0,62}$'
      OR :'bk_backup_role' !~ '^[a-z][a-z0-9_]{0,62}$'
        THEN 'every role name must match ^[a-z][a-z0-9_]{0,62}$'
    WHEN :'bk_migration_role' LIKE 'pg\_%' OR :'bk_runtime_role' LIKE 'pg\_%' OR :'bk_backup_role' LIKE 'pg\_%'
        THEN 'a role name may not start with pg_'
    WHEN :'bk_migration_role' IN (:'bk_runtime_role', :'bk_backup_role') OR :'bk_runtime_role' = :'bk_backup_role'
        THEN 'the three roles must be three different roles'
    WHEN :'bk_marker' NOT IN ('local', 'test')
     AND '' IN (:'bk_migration_password', :'bk_runtime_password', :'bk_backup_password')
        THEN 'every role needs a password unless the marker is local or test'
    WHEN current_user IN (:'bk_migration_role', :'bk_runtime_role', :'bk_backup_role')
      OR session_user IN (:'bk_migration_role', :'bk_runtime_role', :'bk_backup_role')
        THEN 'a role of BantuKasir may not be the user running this script'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_roles
                 WHERE rolname IN (:'bk_migration_role', :'bk_runtime_role', :'bk_backup_role') AND rolsuper)
        THEN 'a named role exists and is a superuser; it is never demoted by this script'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_auth_members m
                 JOIN pg_catalog.pg_roles r ON r.oid IN (m.roleid, m.member)
                 WHERE r.rolname IN (:'bk_migration_role', :'bk_runtime_role', :'bk_backup_role'))
        THEN 'a named role is a member of a role or has members'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_db_role_setting s
                 JOIN pg_catalog.pg_roles r ON r.oid = s.setrole
                 JOIN pg_catalog.pg_database d ON d.oid = s.setdatabase
                 CROSS JOIN LATERAL pg_catalog.unnest(s.setconfig) AS c(setting)
                 WHERE d.datname <> :'bk_database'
                   AND c.setting LIKE 'bantukasir.role=%'
                   AND c.setting <> 'bantukasir.role=' || CASE r.rolname
                         WHEN :'bk_migration_role' THEN 'migration'
                         WHEN :'bk_runtime_role' THEN 'runtime'
                         WHEN :'bk_backup_role' THEN 'backup' END
                   AND r.rolname IN (:'bk_migration_role', :'bk_runtime_role', :'bk_backup_role'))
        THEN 'a named role is marked as another logical role of BantuKasir in another database'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_db_role_setting s
                 JOIN pg_catalog.pg_roles r ON r.oid = s.setrole
                 JOIN pg_catalog.pg_database d ON d.oid = s.setdatabase
                 CROSS JOIN LATERAL pg_catalog.unnest(s.setconfig) AS c(setting)
                 WHERE d.datname <> :'bk_database'
                   AND c.setting LIKE 'bantukasir.role=%'
                   AND r.rolname IN (:'bk_migration_role', :'bk_runtime_role', :'bk_backup_role')
                   AND NOT (:'bk_marker' IN ('local', 'test')
                            AND coalesce(pg_catalog.shobj_description(d.oid, 'pg_database'), '') IN ('local', 'test')))
        THEN 'a named role serves another database of BantuKasir; only the local and the test database may share their roles'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_database
                 WHERE datname = :'bk_database'
                   AND pg_catalog.shobj_description(oid, 'pg_database') IS DISTINCT FROM :'bk_marker'
                   AND pg_catalog.shobj_description(oid, 'pg_database') IS NOT NULL)
        THEN 'the database exists with another marker; a marker is never changed by this script'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_database d
                 WHERE d.datname = :'bk_database'
                   AND NOT (SELECT r.rolsuper FROM pg_catalog.pg_roles r WHERE r.oid = d.datdba))
        THEN 'the database exists and is owned by a role that is not a superuser; its owner is never changed by this script'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_database
                 WHERE datname = :'bk_database'
                   AND (pg_catalog.pg_encoding_to_char(encoding) <> 'UTF8' OR datlocprovider <> 'c'
                        OR datcollate <> 'en_US.utf8' OR datctype <> 'en_US.utf8'
                        OR datistemplate OR NOT datallowconn))
        THEN 'the database exists with another encoding, locale provider or locale, or is a template'
END AS bk_refusal \gset

\if :{?bk_refusal}
SELECT pg_catalog.set_config('bantukasir_setup.refusal', :'bk_refusal', false) \gset bk_ignored_
DO $$ BEGIN RAISE EXCEPTION 'BantuKasir setup refused: %', current_setting('bantukasir_setup.refusal'); END $$;
\endif

-- Inside an existing database: nothing in public that the migration role does
-- not own, no other schema, no extension other than plpgsql and the migration
-- role's pg_trgm; an unmarked database or one without pg_trgm must be empty.
SELECT EXISTS (SELECT FROM pg_catalog.pg_database WHERE datname = :'bk_database') AS bk_database_exists,
       EXISTS (SELECT FROM pg_catalog.pg_database
               WHERE datname = :'bk_database' AND pg_catalog.shobj_description(oid, 'pg_database') IS NOT NULL) AS bk_database_marked \gset
\if :bk_database_exists
\connect :bk_database
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = warning;
SET log_statement = 'none';
SET log_min_error_statement = 'panic';
\unset bk_refusal
SELECT CASE
    WHEN EXISTS (SELECT FROM pg_catalog.pg_namespace
                 WHERE nspname NOT IN ('public', 'pg_catalog', 'information_schema', 'pg_toast')
                   AND nspname !~ '^pg_(temp|toast_temp)_[0-9]+$')
        THEN 'the database holds a schema other than public'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_class c
                 JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                   AND c.relowner IS DISTINCT FROM (SELECT oid FROM pg_catalog.pg_roles WHERE rolname = :'bk_migration_role')
                   AND NOT EXISTS (SELECT FROM pg_catalog.pg_depend d
                                   WHERE d.classid = 'pg_catalog.pg_class'::pg_catalog.regclass AND d.objid = c.oid AND d.deptype = 'e'))
        THEN 'the schema public holds a relation that the migration role does not own'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_proc p
                 JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
                 WHERE n.nspname = 'public'
                   AND p.proowner IS DISTINCT FROM (SELECT oid FROM pg_catalog.pg_roles WHERE rolname = :'bk_migration_role')
                   AND NOT EXISTS (SELECT FROM pg_catalog.pg_depend d
                                   WHERE d.classid = 'pg_catalog.pg_proc'::pg_catalog.regclass AND d.objid = p.oid AND d.deptype = 'e'))
        THEN 'the schema public holds a function that the migration role does not own'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_operator o
                 JOIN pg_catalog.pg_namespace n ON n.oid = o.oprnamespace
                 WHERE n.nspname = 'public'
                   AND o.oprowner IS DISTINCT FROM (SELECT oid FROM pg_catalog.pg_roles WHERE rolname = :'bk_migration_role')
                   AND NOT EXISTS (SELECT FROM pg_catalog.pg_depend d
                                   WHERE d.classid = 'pg_catalog.pg_operator'::pg_catalog.regclass AND d.objid = o.oid AND d.deptype = 'e'))
        THEN 'the schema public holds an operator that the migration role does not own'
    WHEN EXISTS (SELECT FROM pg_catalog.pg_extension e
                 WHERE e.extname <> 'plpgsql'
                   AND (e.extname <> 'pg_trgm'
                        OR e.extowner IS DISTINCT FROM (SELECT oid FROM pg_catalog.pg_roles WHERE rolname = :'bk_migration_role')))
        THEN 'the database holds an extension other than plpgsql and the migration role''s pg_trgm'
    WHEN (NOT :'bk_database_marked'::boolean
          OR NOT EXISTS (SELECT FROM pg_catalog.pg_extension WHERE extname = 'pg_trgm'))
     AND (EXISTS (SELECT FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public')
          OR EXISTS (SELECT FROM pg_catalog.pg_proc p JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public')
          OR EXISTS (SELECT FROM pg_catalog.pg_operator o JOIN pg_catalog.pg_namespace n ON n.oid = o.oprnamespace WHERE n.nspname = 'public')
          OR EXISTS (SELECT FROM pg_catalog.pg_type t JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace WHERE n.nspname = 'public'))
        THEN 'the database holds no marker or no pg_trgm yet, and its schema public is not empty'
END AS bk_refusal \gset
\connect :bk_admin_database
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = warning;
SET log_statement = 'none';
SET log_min_duration_statement = -1;
SET log_min_duration_sample = -1;
SET log_transaction_sample_rate = 0;
SET log_min_error_statement = 'panic';
SET pg_stat_statements.track_utility = off;
SET auto_explain.log_min_duration = -1;
\if :{?bk_refusal}
SELECT pg_catalog.set_config('bantukasir_setup.refusal', :'bk_refusal', false) \gset bk_ignored_
DO $$ BEGIN RAISE EXCEPTION 'BantuKasir setup refused: %', current_setting('bantukasir_setup.refusal'); END $$;
\endif
\endif

\echo 'BantuKasir setup: database' :bk_database 'marker' :bk_marker

-- 2. Roles -----------------------------------------------------------------------

BEGIN;

SELECT pg_catalog.format('CREATE ROLE %I', r.name)
FROM (VALUES (:'bk_migration_role'), (:'bk_runtime_role'), (:'bk_backup_role')) AS r(name)
WHERE NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = r.name)
\gexec

ALTER ROLE :"bk_migration_role" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS NOINHERIT CONNECTION LIMIT -1 VALID UNTIL 'infinity';
ALTER ROLE :"bk_runtime_role" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS NOINHERIT CONNECTION LIMIT -1 VALID UNTIL 'infinity';
ALTER ROLE :"bk_backup_role" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS NOINHERIT CONNECTION LIMIT -1 VALID UNTIL 'infinity';

SELECT :'bk_migration_password' = '' AS bk_migration_password_empty,
       :'bk_runtime_password' = '' AS bk_runtime_password_empty,
       :'bk_backup_password' = '' AS bk_backup_password_empty \gset
\if :bk_migration_password_empty
ALTER ROLE :"bk_migration_role" WITH PASSWORD NULL;
\else
ALTER ROLE :"bk_migration_role" WITH PASSWORD :'bk_migration_password';
\endif
\if :bk_runtime_password_empty
ALTER ROLE :"bk_runtime_role" WITH PASSWORD NULL;
\else
ALTER ROLE :"bk_runtime_role" WITH PASSWORD :'bk_runtime_password';
\endif
\if :bk_backup_password_empty
ALTER ROLE :"bk_backup_role" WITH PASSWORD NULL;
\else
ALTER ROLE :"bk_backup_role" WITH PASSWORD :'bk_backup_password';
\endif
\unset bk_migration_password
\unset bk_runtime_password
\unset bk_backup_password

-- Settings that would apply in every database are not this script's: none.
ALTER ROLE :"bk_migration_role" RESET ALL;
ALTER ROLE :"bk_runtime_role" RESET ALL;
ALTER ROLE :"bk_backup_role" RESET ALL;

COMMIT;

-- 3. The database ------------------------------------------------------------------

SELECT pg_catalog.format('CREATE DATABASE %I WITH TEMPLATE template0 ENCODING %L LOCALE_PROVIDER libc LOCALE %L',
                         :'bk_database', 'UTF8', 'en_US.utf8')
WHERE NOT EXISTS (SELECT FROM pg_catalog.pg_database WHERE datname = :'bk_database')
\gexec

-- 4. Marker, connection rights and the time zone -------------------------------------

BEGIN;

COMMENT ON DATABASE :"bk_database" IS :'bk_marker';

REVOKE ALL ON DATABASE :"bk_database" FROM PUBLIC;
REVOKE ALL ON DATABASE :"bk_database" FROM :"bk_migration_role", :"bk_runtime_role", :"bk_backup_role";
GRANT CONNECT ON DATABASE :"bk_database" TO :"bk_migration_role", :"bk_runtime_role", :"bk_backup_role";

ALTER DATABASE :"bk_database" RESET ALL;
ALTER DATABASE :"bk_database" SET timezone TO 'UTC';

-- 5. Each role's logical role and settings in this database (ENV-31) ------------------
-- These values equal the 'role_settings' of config/database.php; the privilege
-- check compares them (db:check-privileges).

ALTER ROLE :"bk_migration_role" IN DATABASE :"bk_database" RESET ALL;
ALTER ROLE :"bk_migration_role" IN DATABASE :"bk_database" SET bantukasir.role TO 'migration';
ALTER ROLE :"bk_migration_role" IN DATABASE :"bk_database" SET lock_timeout TO '5s';
ALTER ROLE :"bk_migration_role" IN DATABASE :"bk_database" SET idle_in_transaction_session_timeout TO '30s';

ALTER ROLE :"bk_runtime_role" IN DATABASE :"bk_database" RESET ALL;
ALTER ROLE :"bk_runtime_role" IN DATABASE :"bk_database" SET bantukasir.role TO 'runtime';
ALTER ROLE :"bk_runtime_role" IN DATABASE :"bk_database" SET lock_timeout TO '5s';
ALTER ROLE :"bk_runtime_role" IN DATABASE :"bk_database" SET statement_timeout TO '10s';
ALTER ROLE :"bk_runtime_role" IN DATABASE :"bk_database" SET idle_in_transaction_session_timeout TO '30s';
ALTER ROLE :"bk_runtime_role" IN DATABASE :"bk_database" SET transaction_timeout TO '30s';

ALTER ROLE :"bk_backup_role" IN DATABASE :"bk_database" RESET ALL;
ALTER ROLE :"bk_backup_role" IN DATABASE :"bk_database" SET bantukasir.role TO 'backup';
ALTER ROLE :"bk_backup_role" IN DATABASE :"bk_database" SET statement_timeout TO '0';
ALTER ROLE :"bk_backup_role" IN DATABASE :"bk_database" SET idle_in_transaction_session_timeout TO '30s';
ALTER ROLE :"bk_backup_role" IN DATABASE :"bk_database" SET transaction_timeout TO '0';

COMMIT;

-- 6. and 7. Inside the database ----------------------------------------------------------

\connect :bk_database
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = warning;
SET log_statement = 'none';
SET log_min_error_statement = 'panic';

BEGIN;

ALTER SCHEMA public OWNER TO :"bk_migration_role";
REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM :"bk_runtime_role", :"bk_backup_role";
GRANT USAGE ON SCHEMA public TO :"bk_runtime_role", :"bk_backup_role";

GRANT CREATE ON DATABASE :"bk_database" TO :"bk_migration_role";
SET LOCAL ROLE :"bk_migration_role";
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;
RESET ROLE;
REVOKE CREATE ON DATABASE :"bk_database" FROM :"bk_migration_role";

COMMIT;

-- The result, for the operator's terminal -------------------------------------------------

SELECT pg_catalog.current_database() AS database,
       pg_catalog.shobj_description(d.oid, 'pg_database') AS marker,
       pg_catalog.pg_encoding_to_char(d.encoding) AS encoding,
       d.datlocprovider AS locale_provider,
       d.datcollate AS collate,
       d.datctype AS ctype,
       d.datcollversion AS collation_version,
       (SELECT e.extversion FROM pg_catalog.pg_extension e WHERE e.extname = 'pg_trgm') AS pg_trgm
FROM pg_catalog.pg_database d
WHERE d.datname = pg_catalog.current_database();

\echo 'BantuKasir setup: done'
