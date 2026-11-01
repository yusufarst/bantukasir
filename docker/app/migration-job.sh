#!/bin/bash
# The migration job (ADR-011 item 7; RELEASE_PROCESS RB-20 step 8; TASK-014 scope
# item 4): the one way a release's migrations are applied. The operator runs it as a
# one-off container of the release image, on the environment's private network, with
# the migration role's credential supplied at run time from the password manager and
# stored in no environment of a resource:
#
#   docker run --rm --network <private network> \
#       -e APP_ENV=production -e APP_ENVIRONMENT_NAME=<environment> \
#       -e DB_HOST=<host> -e DB_DATABASE=<database> \
#       -e DB_MIGRATION_USERNAME -e DB_MIGRATION_PASSWORD \
#       <image>@<digest> bantukasir-migration-job \
#       --environment=<environment> \
#       --migrations=<the release record's list, by name, or none> \
#       --backup-schedule-paused
#
# On a new database, which has no backup yet, the operator adds --first-installation,
# which it refuses unless the catalog shows that database truly empty (REL-23, D-116).
#
# It runs release:preflight, the migrations and the verification queries of REL-20,
# in that order, each stopping the job (App\Console\Commands\ReleaseMigrate). It
# refuses to start with a credential of the application or backup container, so that
# their environments are never reused for it, and prints no value.
set -euo pipefail

for name in APP_PROCESS_ROLE APP_KEY DB_USERNAME DB_PASSWORD BACKUP_DB_USERNAME BACKUP_DB_PASSWORD BACKUP_STORE_KEY_ID BACKUP_STORE_KEY; do
    if [ -n "${!name:-}" ]; then
        echo "JOB_REFUSED SETTING $name belongs to the application or backup container"
        exit 78
    fi
done

for name in APP_ENVIRONMENT_NAME DB_HOST DB_DATABASE DB_MIGRATION_USERNAME DB_MIGRATION_PASSWORD; do
    if [ -z "${!name:-}" ]; then
        echo "JOB_REFUSED SETTING $name is required"
        exit 78
    fi
done

exec php artisan release:migrate --no-interaction "$@"
