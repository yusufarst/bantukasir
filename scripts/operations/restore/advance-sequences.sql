-- BantuKasir: the sequence advance after a restore (BACKUP_RECOVERY RB-01 step 13, RB-02 step 6; SCHEMA
-- SCH-28; TASK-037 scope item 5). Reviewed as SQL; run by the operator, a person, with psql as the
-- migration role, on the restored target only — through the SSH tunnel of RB-01 step 9:
--
--     psql -X -v ON_ERROR_STOP=1 -v environment=<name> -v sales_seen=<n> -v sessions_seen=<n> \
--          -h 127.0.0.1 -p <tunnel port> -U <migration role> -d <database> \
--          -f scripts/operations/restore/advance-sequences.sql
--
--   environment    the environment's name as the operator types it (ENVIRONMENTS ENV-04); it must equal
--                  the marker of the database, so that no other database is ever advanced
--   sales_seen     the highest counter of a Sale number found in the gap review (section 11), the part
--                  after the hyphen of YYYYMMDD-n, or 0 when none was found
--   sessions_seen  the same for the cash session numbers
--
-- Each of sale_number_seq and cash_session_number_seq is set so that the next number it issues is more
-- than 100,000 above both its restored value and the highest counter seen, so that no number already in
-- a customer's hands is issued again. The jump is a gap, which SCH-26 allows. A sequence is never moved
-- backwards. The runtime role cannot do this (SCH-27). It prints each sequence's restored value and the
-- value it was advanced to, for the incident record; it changes nothing else.

\set ON_ERROR_STOP on
\set QUIET on
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = warning;

\if :{?environment}
\else
\set environment ''
\endif
\if :{?sales_seen}
\else
\set sales_seen ''
\endif
\if :{?sessions_seen}
\else
\set sessions_seen ''
\endif

\unset bk_refusal
SELECT CASE
    WHEN :'environment' NOT IN ('local', 'test', 'drill', 'staging', 'production')
        THEN 'environment must be local, test, drill, staging or production'
    WHEN pg_catalog.shobj_description(d.oid, 'pg_database') IS DISTINCT FROM :'environment'
        THEN 'the typed environment is not the marker of this database'
    WHEN pg_catalog.current_setting('bantukasir.role', true) IS DISTINCT FROM 'migration'
        THEN 'the session is not the migration role of this database'
    WHEN :'sales_seen' !~ '^(0|[1-9][0-9]{0,17})$' OR :'sessions_seen' !~ '^(0|[1-9][0-9]{0,17})$'
        THEN 'sales_seen and sessions_seen must be the highest counters seen, whole numbers, 0 when none'
END AS bk_refusal
FROM pg_catalog.pg_database d
WHERE d.datname OPERATOR(pg_catalog.=) pg_catalog.current_database() \gset

\if :{?bk_refusal}
SELECT pg_catalog.set_config('bantukasir_restore.refusal', :'bk_refusal', false) \gset bk_ignored_
DO $$ BEGIN RAISE EXCEPTION 'BantuKasir sequence advance refused: %', pg_catalog.current_setting('bantukasir_restore.refusal'); END $$;
\endif

\set QUIET off
SELECT 'sale_number_seq' AS sequence,
       s.last_value AS restored,
       pg_catalog.setval('public.sale_number_seq', GREATEST(s.last_value, :'sales_seen'::bigint) OPERATOR(pg_catalog.+) 100000, true) AS advanced_to
FROM public.sale_number_seq s
UNION ALL
SELECT 'cash_session_number_seq',
       c.last_value,
       pg_catalog.setval('public.cash_session_number_seq', GREATEST(c.last_value, :'sessions_seen'::bigint) OPERATOR(pg_catalog.+) 100000, true)
FROM public.cash_session_number_seq c;
