-- BantuKasir: the row counts of a restored database against its backup's manifest (BACKUP_RECOVERY
-- RB-01 step 11, RB-02 step 5; section 5, `counts`; TASK-037 scope item 7). Reviewed as SQL; run by the
-- operator with psql as the migration role, on the restored target only, through the SSH tunnel of RB-01
-- step 9, with the decrypted manifest on the operator's own machine named by BANTUKASIR_MANIFEST:
--
--     BANTUKASIR_MANIFEST=<work>/manifest.json \
--     psql -X -v ON_ERROR_STOP=1 -v environment=<name> \
--          -h 127.0.0.1 -p <tunnel port> -U <migration role> -d <database> \
--          -f scripts/operations/restore/compare-counts.sql
--
-- Every table that the manifest counts must exist in the schema public and hold exactly that many rows;
-- any difference fails the run and names each table. It reads only, in a read-only transaction.

\set ON_ERROR_STOP on
\set QUIET on
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = notice;

\if :{?environment}
\else
\set environment ''
\endif

\unset bk_refusal
SELECT CASE
    WHEN :'environment' NOT IN ('local', 'test', 'drill', 'staging', 'production')
        THEN 'environment must be local, test, drill, staging or production'
    WHEN pg_catalog.shobj_description(d.oid, 'pg_database') IS DISTINCT FROM :'environment'
        THEN 'the typed environment is not the marker of this database'
    WHEN pg_catalog.current_setting('bantukasir.role', true) IS DISTINCT FROM 'migration'
        THEN 'the session is not the migration role of this database'
END AS bk_refusal
FROM pg_catalog.pg_database d
WHERE d.datname OPERATOR(pg_catalog.=) pg_catalog.current_database() \gset

\if :{?bk_refusal}
SELECT pg_catalog.set_config('bantukasir_restore.refusal', :'bk_refusal', false) \gset bk_ignored_
DO $$ BEGIN RAISE EXCEPTION 'BantuKasir count comparison refused: %', pg_catalog.current_setting('bantukasir_restore.refusal'); END $$;
\endif

\set bk_manifest `cat "$BANTUKASIR_MANIFEST"`

BEGIN READ ONLY;
SELECT pg_catalog.set_config('bantukasir_restore.manifest', :'bk_manifest', true) \gset bk_ignored_

DO $$
DECLARE
    manifest json := pg_catalog.current_setting('bantukasir_restore.manifest')::json;
    item record;
    actual bigint;
    checked integer := 0;
    differences text[] := '{}';
BEGIN
    IF (manifest OPERATOR(pg_catalog.->>) 'format') IS DISTINCT FROM '1'
       OR pg_catalog.json_typeof(manifest OPERATOR(pg_catalog.->) 'counts') IS DISTINCT FROM 'object' THEN
        RAISE EXCEPTION 'BantuKasir count comparison refused: the file is no manifest of format 1 with its counts';
    END IF;

    FOR item IN SELECT c.key, c.value FROM pg_catalog.json_each_text(manifest OPERATOR(pg_catalog.->) 'counts') c ORDER BY c.key LOOP
        IF NOT EXISTS (SELECT FROM pg_catalog.pg_tables t
                       WHERE t.schemaname OPERATOR(pg_catalog.=) 'public' AND t.tablename OPERATOR(pg_catalog.=) item.key) THEN
            differences := differences OPERATOR(pg_catalog.||) (item.key OPERATOR(pg_catalog.||) ': missing');
            CONTINUE;
        END IF;

        EXECUTE pg_catalog.format('SELECT pg_catalog.count(*) FROM public.%I', item.key) INTO actual;
        checked := checked OPERATOR(pg_catalog.+) 1;

        IF actual::text IS DISTINCT FROM item.value THEN
            differences := differences OPERATOR(pg_catalog.||) (item.key OPERATOR(pg_catalog.||) ': manifest ' OPERATOR(pg_catalog.||) item.value OPERATOR(pg_catalog.||) ', restored ' OPERATOR(pg_catalog.||) actual::text);
        END IF;
    END LOOP;

    IF pg_catalog.cardinality(differences) OPERATOR(pg_catalog.>) 0 THEN
        RAISE EXCEPTION 'BantuKasir counts differ from the manifest: %', pg_catalog.array_to_string(differences, '; ');
    END IF;

    RAISE NOTICE 'BantuKasir counts equal to the manifest: % tables', checked;
END $$;

COMMIT;
