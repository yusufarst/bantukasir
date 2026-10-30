#!/bin/bash
# The start of the release image (TASK-012). Given a command, it runs only that:
# an operator procedure or the migration job of the release (ADR-011 item 7),
# with the settings that command needs. Given none, it starts the process role
# that APP_PROCESS_ROLE selects (ENVIRONMENTS section 11):
#
#   1. the settings that the role needs are checked; a missing or default one
#      refuses the start, naming the setting and never its value (SEC-SECRET-03);
#   2. configuration, routes and views are cached (ADR-011 item 6);
#   3. role web serves HTTP; role backup serves nothing, and only its scheduler,
#      which the platform runs each minute, does its work (ADR-010 item 6).
#
# It never migrates: a migration is a controlled job of an authorized release,
# never a side effect of a start (PRODUCTION_DATA_SAFETY section 2).
set -euo pipefail

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# The cached configuration holds the role's settings: for this user only.
umask 027

php artisan settings:check
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

case "$APP_PROCESS_ROLE" in
    web)
        exec /usr/local/bin/bantukasir-serve
        ;;
    backup)
        trap 'exit 0' TERM INT
        while :; do
            sleep 3600 &
            wait "$!"
        done
        ;;
    *)
        exit 78
        ;;
esac
