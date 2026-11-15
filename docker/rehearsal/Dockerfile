# The operator's machine and the target's SSH gateway of the restore rehearsal (TASK-037 scope item 7;
# BACKUP_RECOVERY sections 8, 10 and 13): the services operator and drill-gateway of compose.yaml, under
# the profile rehearsal, which scripts/checks/restore-rehearsal.sh starts in CI only. It is never in the
# release image and on no path of staging or production. It is the pinned PostgreSQL image, whose client
# tools are of the server's major version, with OpenSSH and curl from the archive of its Debian release,
# and age built from its source at the pinned version, as docker/app/Dockerfile builds it: the tools the
# operator's own machine needs for RB-01 phase C and RB-02 steps 3 to 6. The Go module system verifies
# age's sources against the checksum database.
FROM golang:1.27.3-trixie@sha256:c65ecfe613b539ac74c62a4c3b2c27e8f8a6076384c8f27e8a3c0f3be17cc385 AS age
ENV CGO_ENABLED=0
RUN go install -trimpath filippo.io/age/cmd/age@v1.3.2 filippo.io/age/cmd/age-keygen@v1.3.2

FROM postgres:18.6-trixie@sha256:74935e72241653ca55e0414067e6d8763aceb8a810eb51b452253ec3dcfc4336

# The gateway accepts the operator's key only, for one tunnel to the target's database and for the copy
# of the files into the target's file volume (ENVIRONMENTS section 9; RB-01 steps 9 and 10). The account
# has no password and is not locked, so that its key alone signs it in; sshd runs without PAM, whose
# session modules a container does not serve.
RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl openssh-client openssh-server; \
    rm -rf /var/lib/apt/lists/*; \
    useradd --create-home --uid 1000 --shell /bin/bash operator; \
    usermod --password '*' operator; \
    install -d -m 0700 -o operator -g operator /home/operator/.ssh; \
    install -d -m 0755 /run/sshd; \
    printf '%s\n' \
        'UsePAM no' \
        'PasswordAuthentication no' \
        'KbdInteractiveAuthentication no' \
        'PermitRootLogin no' \
        'AllowUsers operator' \
        'AllowTcpForwarding local' \
        'PermitOpen drill-db:5432' \
        'X11Forwarding no' \
        > /etc/ssh/sshd_config.d/rehearsal.conf

COPY --from=age /go/bin/age /go/bin/age-keygen /usr/local/bin/

ENTRYPOINT []
CMD ["sleep", "infinity"]
