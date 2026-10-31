#!/bin/bash
# The web server of the image, in role web and in the local environment
# (TASK-012; ADR-002 item 1): PHP-FPM and Nginx in the foreground of one
# container. When either of them ends, the other is stopped and the container
# ends with a failure, so that the platform sees it; a stop signal ends both
# gracefully, with success.
set -u

stopping=0
fpm=
web=

stop() {
    kill -QUIT "$web" "$fpm" 2>/dev/null || true
}

trap 'stopping=1; stop' TERM INT

php-fpm --nodaemonize &
fpm=$!

nginx -e stderr -g 'daemon off;' &
web=$!

wait -n "$fpm" "$web"

stop
wait "$web" "$fpm" 2>/dev/null

if [ "$stopping" -eq 1 ]; then
    exit 0
fi

exit 1
