#!/bin/sh
# The database setup of the local environment and of CI (TASK-004): the service
# db-setup of compose.yaml runs this file in the PostgreSQL image, with psql of
# the pinned version, at every "docker compose up", before the application
# starts. It runs database/setup/setup.sql, the script that the operator runs on
# a real environment (ENVIRONMENTS ENV-30), unchanged, for two databases of the
# local cluster:
#
#   bantukasir       marker local, the database of the local application
#   bantukasir_test  marker test, the only database that the tests reset (TS-07)
#
# Both use the same three local roles, without passwords: the local cluster
# accepts connections without one on the project's private network only
# (compose.yaml). These names are local and synthetic; a real environment's
# names and passwords are the operator's and are never written in the
# repository (SCHEMA section 10).
#
# An existing cluster, such as the volume that TASK-001 created, is brought to
# this state without deleting anything; setup.sql refuses what it cannot bring.
# Then database/setup/selftest.sh proves that a second run changes nothing,
# that every refusal leaves the cluster unchanged and that objects of the
# migration role in the schema public stay inert. Before the setup, it removes
# the probes that an interrupted self-test may have left in the test database.
set -eu

export BANTUKASIR_SETUP_MIGRATION_ROLE=bk_migration
export BANTUKASIR_SETUP_RUNTIME_ROLE=bk_runtime
export BANTUKASIR_SETUP_BACKUP_ROLE=bk_backup
export BANTUKASIR_SETUP_MIGRATION_PASSWORD=
export BANTUKASIR_SETUP_RUNTIME_PASSWORD=
export BANTUKASIR_SETUP_BACKUP_PASSWORD=

setup() {
    BANTUKASIR_SETUP_DATABASE="$1" BANTUKASIR_SETUP_MARKER="$2" \
        psql -X -v ON_ERROR_STOP=1 -d postgres -f /setup/setup.sql
}

sh /setup/selftest.sh cleanup

setup bantukasir local
setup bantukasir_test test

sh /setup/selftest.sh
