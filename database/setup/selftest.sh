#!/bin/sh
# The self-test of database/setup/setup.sql (TASK-004, AC-004-01), run by
# database/setup/local.sh after the setup, at every local and CI start:
#
#  0. PostgreSQL's default rights on the schema public of a new database;
#  1. a second run on the test database changes nothing (database/setup/state.sql
#     before and after);
#  2. each refusal below exits with an error that names it, before any change:
#     the state of the cluster and of the test database is the same afterwards,
#     and the database it would have created does not exist. Some cases first
#     put a probe in the test database or among the local roles — a role
#     membership, a schema, a table, a function or an extension named
#     harness_probe or citext — and remove it afterwards;
#  3. functions and operators that the migration role puts in the schema public
#     of the test database, to take over the superuser's session, stay inert:
#     the setup runs past them, then they are removed.
#
# It touches only the test database bantukasir_test, after checking that its
# marker is "test", and the local roles of local.sh. With the argument
# "cleanup" it only removes what an interrupted run may have left; local.sh
# calls it so before the setup.
set -eu

db=bantukasir_test

fail() {
    echo "setup self-test FAILED: $*" >&2
    exit 1
}

# psql as the superuser in the test database, which searches pg_catalog only.
in_test() {
    psql -X -At -v ON_ERROR_STOP=1 -d "$db" -c 'SET search_path = pg_catalog, pg_temp' "$@"
}

require_test_marker() {
    marker="$(psql -X -At -v ON_ERROR_STOP=1 -d postgres -c "SELECT pg_catalog.shobj_description(oid, 'pg_database') FROM pg_catalog.pg_database WHERE datname = '$db'")"
    [ "$marker" = "test" ] || fail "the marker of $db is '$marker', not 'test'"
}

cleanup() {
    if ! psql -X -At -d postgres -c "SELECT 1 FROM pg_catalog.pg_database WHERE datname = '$db'" | grep -q 1; then
        return 0
    fi
    require_test_marker
    psql -X -q -v ON_ERROR_STOP=1 -d "$db" <<'SQL'
SET search_path = pg_catalog, pg_temp;
SET client_min_messages = warning;
DROP OPERATOR IF EXISTS public.!~ (name, name);
DROP OPERATOR IF EXISTS public.= (oid, regclass);
DROP FUNCTION IF EXISTS public.harness_hijack_name(name, name);
DROP FUNCTION IF EXISTS public.harness_hijack_oid(oid, regclass);
DROP FUNCTION IF EXISTS public.harness_probe();
DROP TABLE IF EXISTS public.harness_probe;
DROP SCHEMA IF EXISTS harness_probe;
DROP EXTENSION IF EXISTS citext;
SELECT 'REVOKE bk_backup FROM bk_runtime'
WHERE EXISTS (SELECT FROM pg_catalog.pg_auth_members
              WHERE roleid = 'bk_backup'::pg_catalog.regrole AND member = 'bk_runtime'::pg_catalog.regrole)
\gexec
SQL
}

if [ "${1:-}" = "cleanup" ]; then
    cleanup
    exit 0
fi

state() {
    psql -X -At -v ON_ERROR_STOP=1 -v db="$db" -d postgres -f /setup/state.sql
}

run() {
    psql -X -v ON_ERROR_STOP=1 -d postgres -f /setup/setup.sql
}

require_test_marker
trap cleanup EXIT

# 0. PostgreSQL's default for a new database, which the setup tightens: in the
# template, PUBLIC may use the schema public but not create in it (SCHEMA
# SCH-43, SCH-52).
template="$(psql -X -At -v ON_ERROR_STOP=1 -d template1 -c "SELECT nspacl::text FROM pg_catalog.pg_namespace WHERE nspname = 'public'")"
[ "$template" = "{pg_database_owner=UC/pg_database_owner,=U/pg_database_owner}" ] || fail "the schema public of a new database has the rights $template"
echo "setup self-test: a new database gives PUBLIC USAGE and no CREATE on the schema public"

# 1. Idempotency ---------------------------------------------------------------------

before="$(state)"
BANTUKASIR_SETUP_DATABASE="$db" BANTUKASIR_SETUP_MARKER=test run > /dev/null
after="$(state)"
[ "$before" = "$after" ] || fail "a second run on $db changed the state"
echo "setup self-test: a second run changes nothing"

# 2. Refusals --------------------------------------------------------------------------

out="$(mktemp)"
trap 'rm -f "$out"; cleanup' EXIT

# refuse <case> <expected text> <VARIABLE=value>...
refuse() {
    case="$1"
    expected="$2"
    shift 2
    if env "$@" psql -X -v ON_ERROR_STOP=1 -d postgres -f /setup/setup.sql > "$out" 2>&1; then
        fail "$case: accepted"
    fi
    grep -q "BantuKasir setup refused: $expected" "$out" || { cat "$out" >&2; fail "$case: failed without the expected refusal"; }
    if psql -X -At -d postgres -c "SELECT 1 FROM pg_catalog.pg_database WHERE datname = 'bantukasir_setup_probe'" | grep -q 1; then
        fail "$case: the database bantukasir_setup_probe exists"
    fi
    echo "setup self-test: refused, as expected: $case"
}

# refuse_unchanged: as refuse, and the state is the same as before.
refuse_unchanged() {
    refuse "$@"
    [ "$(state)" = "$before" ] || fail "$1: the state changed"
}

# refuse_with_probe: as refuse; the state differs only by the probe, which is
# then removed, after which the state is the same as before.
refuse_with_probe() {
    refuse "$@"
    cleanup
    [ "$(state)" = "$before" ] || fail "$1: the state changed"
}

probe="BANTUKASIR_SETUP_DATABASE=bantukasir_setup_probe"
test_db="BANTUKASIR_SETUP_DATABASE=$db"
passwords="BANTUKASIR_SETUP_MIGRATION_PASSWORD=probe-not-used BANTUKASIR_SETUP_RUNTIME_PASSWORD=probe-not-used BANTUKASIR_SETUP_BACKUP_PASSWORD=probe-not-used"

refuse_unchanged "the test database under the marker local" "the database exists with another marker" \
    "$test_db" BANTUKASIR_SETUP_MARKER=local
refuse_unchanged "an unknown marker" "BANTUKASIR_SETUP_MARKER must be" \
    "$probe" BANTUKASIR_SETUP_MARKER=prod
refuse_unchanged "no marker" "BANTUKASIR_SETUP_MARKER must be" \
    "$probe" BANTUKASIR_SETUP_MARKER=
refuse_unchanged "no password under the marker staging" "every role needs a password" \
    "$probe" BANTUKASIR_SETUP_MARKER=staging
refuse_unchanged "an invalid database name" "BANTUKASIR_SETUP_DATABASE must match" \
    BANTUKASIR_SETUP_DATABASE=Bad-Name BANTUKASIR_SETUP_MARKER=test
refuse_unchanged "no database name" "BANTUKASIR_SETUP_DATABASE must match" \
    BANTUKASIR_SETUP_DATABASE= BANTUKASIR_SETUP_MARKER=test
refuse_unchanged "a system database" "BANTUKASIR_SETUP_DATABASE names a system database" \
    BANTUKASIR_SETUP_DATABASE=template1 BANTUKASIR_SETUP_MARKER=test
refuse_unchanged "an invalid role name" "every role name must match" \
    "$probe" BANTUKASIR_SETUP_MARKER=test BANTUKASIR_SETUP_RUNTIME_ROLE=Runtime
refuse_unchanged "a reserved role name" "a role name may not start with pg_" \
    "$probe" BANTUKASIR_SETUP_MARKER=test BANTUKASIR_SETUP_BACKUP_ROLE=pg_backup
refuse_unchanged "one role for two logical roles" "the three roles must be three different roles" \
    "$probe" BANTUKASIR_SETUP_MARKER=test BANTUKASIR_SETUP_RUNTIME_ROLE=bk_backup
refuse_unchanged "the user running the script as a role" "a role of BantuKasir may not be the user running this script" \
    "$probe" BANTUKASIR_SETUP_MARKER=test BANTUKASIR_SETUP_RUNTIME_ROLE=postgres
refuse_unchanged "roles swapped against another database" "a named role is marked as another logical role" \
    "$probe" BANTUKASIR_SETUP_MARKER=test BANTUKASIR_SETUP_MIGRATION_ROLE=bk_runtime BANTUKASIR_SETUP_RUNTIME_ROLE=bk_migration
# shellcheck disable=SC2086
refuse_unchanged "the local roles for a drill database" "a named role serves another database of BantuKasir" \
    "$probe" BANTUKASIR_SETUP_MARKER=drill $passwords

psql -X -q -v ON_ERROR_STOP=1 -d postgres -c 'GRANT bk_backup TO bk_runtime'
refuse_with_probe "a named role that is a member of another" "a named role is a member of a role or has members" \
    "$test_db" BANTUKASIR_SETUP_MARKER=test

in_test -c 'CREATE SCHEMA harness_probe' > /dev/null
refuse_with_probe "another schema than public" "the database holds a schema other than public" \
    "$test_db" BANTUKASIR_SETUP_MARKER=test

in_test -c 'CREATE TABLE public.harness_probe (id integer)' > /dev/null
refuse_with_probe "a table that the migration role does not own" "the schema public holds a relation that the migration role does not own" \
    "$test_db" BANTUKASIR_SETUP_MARKER=test

in_test -c "CREATE FUNCTION public.harness_probe() RETURNS integer LANGUAGE sql IMMUTABLE AS 'SELECT 1'" > /dev/null
refuse_with_probe "a function that the migration role does not own" "the schema public holds a function that the migration role does not own" \
    "$test_db" BANTUKASIR_SETUP_MARKER=test

in_test -c 'CREATE EXTENSION citext SCHEMA public' > /dev/null
refuse_with_probe "an extension other than plpgsql and pg_trgm" "the database holds an extension other than plpgsql" \
    "$test_db" BANTUKASIR_SETUP_MARKER=test

# 3. Objects of the migration role in the schema public stay inert ----------------------

psql -X -q -v ON_ERROR_STOP=1 -d "$db" <<'SQL'
SET search_path = pg_catalog, pg_temp;
SET ROLE bk_migration;
CREATE FUNCTION public.harness_hijack_name(name, name) RETURNS boolean
    LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'hijacked by the migration role'; END $$;
CREATE OPERATOR public.!~ (LEFTARG = name, RIGHTARG = name, FUNCTION = public.harness_hijack_name);
CREATE FUNCTION public.harness_hijack_oid(oid, regclass) RETURNS boolean
    LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'hijacked by the migration role'; END $$;
CREATE OPERATOR public.= (LEFTARG = oid, RIGHTARG = regclass, FUNCTION = public.harness_hijack_oid);
SQL

# Without the pinned search_path they would run: a session that searches the
# schema public, as a superuser's default does, calls them.
if psql -X -At -d "$db" -c "SELECT nspname FROM pg_catalog.pg_namespace WHERE nspname !~ '^pg_'" > "$out" 2>&1; then
    fail "the probe operator of the migration role did not run in an unpinned session"
fi
grep -q 'hijacked by the migration role' "$out" || { cat "$out" >&2; fail "the unpinned session failed otherwise"; }

BANTUKASIR_SETUP_DATABASE="$db" BANTUKASIR_SETUP_MARKER=test run > "$out" 2>&1 || { cat "$out" >&2; fail "the setup failed with the probe operators in place"; }
state > /dev/null
cleanup
[ "$(state)" = "$before" ] || fail "the state changed after the probe operators"
echo "setup self-test: the migration role's operators in the schema public stay inert"

echo "setup self-test: passed"
