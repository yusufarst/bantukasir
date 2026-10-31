#!/bin/sh
# The image's health check (TASK-012; ENVIRONMENTS section 6): in role web RH-01
# through the loopback listener of nginx.conf, with the environment's host name,
# which the application checks (TASK-007 finding F-1); in role backup, which
# serves nothing, success while the container runs.
set -eu

case "${APP_PROCESS_ROLE:-}" in
    web)
        host="${APP_URL#*://}"
        host="${host%%[/:]*}"
        exec curl -fsS -o /dev/null --max-time 10 -H "Host: ${host}" http://127.0.0.1:8081/up
        ;;
    backup)
        exit 0
        ;;
    *)
        exit 1
        ;;
esac
