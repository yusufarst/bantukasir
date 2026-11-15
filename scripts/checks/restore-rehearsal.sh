#!/usr/bin/env bash
# The local rehearsal of the restore runbooks (TASK-037 scope item 7, AC-037-06; BACKUP_RECOVERY RB-01
# phases C to F and RB-02; TEST_STRATEGY section 2, L6), with synthetic data only, on CI's runner: the job
# Restore rehearsal of .github/workflows/ci.yml runs it after starting the local environment, installing
# the npm dependencies, building the frontend and migrating the local database, which stands for
# production. Nothing here reaches staging, production or any real store or key (EXECUTION_CONTEXT
# EXC-13): the store is the local test server, and every password and key is made here, at run time,
# masked in the job's log and destroyed with the target.
#
# The steps follow the runbooks and the checklists of docs/09-operations/RESTORE_CHECKLISTS.md:
#
#   1. the source's synthetic data: Toko Contoh with a stored file and a closed cash session with Sales;
#   2. the target, off the source: its own PostgreSQL with the roles and the marker drill, its SSH gateway
#      and its application, on a network that the operator's machine reaches only through the tunnel;
#   3. the operator's own keys: the recovery key and the SSH key, on the operator's machine only;
#   4. the backup of the source, encrypted to the recipient alone (SC-03 on demand);
#   5. RB-01 step 8: download, hashes and decryption on the operator's machine, never in an application
#      container;
#   6. RB-01 step 9: pg_restore through the SSH tunnel as the migration role; the grants procedure; the
#      privilege test;
#   7. RB-01 step 10: the files over SSH into the target's file volume, each checked;
#   8. RB-01 step 11: the counts against the manifest; reconciliation:run, which must be clean; the
#      decrypted copy destroyed;
#   9. RB-01 steps 12 to 14: OP-04, the sequence advance, synchronous_commit;
#  10. RB-02 step 7: the application on /up, then a receipt and a session report rendered through an Owner
#      recovery link of OP-02 issued on the target;
#  11. RB-02 steps 8 to 10: the durations, the target destroyed, the drill recorded on the source by OP-08.
#
# It stops at the first failure with the step that failed; it ends with the durations of every step.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

declare -a TIMES=()
CURRENT=""
STEP_START=0

begin() {
    CURRENT="$1"
    STEP_START="$(date +%s)"
    echo "== ${CURRENT}"
}

end() {
    TIMES+=("${CURRENT}|$(( $(date +%s) - STEP_START ))")
}

trap 'status=$?; if [ "$status" -ne 0 ]; then echo "REHEARSAL_FAILED at: ${CURRENT}"; fi' EXIT

secret() {
    openssl rand -hex 24
}

mask() {
    if [ "${GITHUB_ACTIONS:-}" = true ]; then
        echo "::add-mask::$1"
    fi
}

operator() {
    docker compose exec -T "$@"
}

DRILL_SUPERUSER_PASSWORD="$(secret)"
DRILL_MIGRATION_PASSWORD="$(secret)"
DRILL_RUNTIME_PASSWORD="$(secret)"
DRILL_BACKUP_PASSWORD="$(secret)"
export DRILL_SUPERUSER_PASSWORD DRILL_MIGRATION_PASSWORD DRILL_RUNTIME_PASSWORD DRILL_BACKUP_PASSWORD
for value in "$DRILL_SUPERUSER_PASSWORD" "$DRILL_MIGRATION_PASSWORD" "$DRILL_RUNTIME_PASSWORD" "$DRILL_BACKUP_PASSWORD"; do
    mask "$value"
done

STORE_ENDPOINT='http://backup-store:9000'
STORE_KEY_ID='minioadmin'
STORE_KEY='minioadmin'
BUCKET="bk-rehearsal-$(openssl rand -hex 6)"
WORK='/home/operator/work'
KEY='/home/operator/recovery.key'
TUNNEL_PORT=15432
NOTE='Gladi pemulihan lokal dengan data sintetis (TASK-037)'

# The operator's psql and pg_restore reach the target only through the tunnel, as the migration role.
target_sql() {
    operator -e PGPASSWORD="$DRILL_MIGRATION_PASSWORD" -e PGHOST=127.0.0.1 -e PGPORT="$TUNNEL_PORT" -e PGUSER=bk_migration -e PGDATABASE=bantukasir operator "$@"
}

DRILL_STARTED="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

begin "1. The source's synthetic data (Toko Contoh, a stored file, a closed session with Sales)"
docker compose run --rm -T -e E2E_BASE_URL=http://bantukasir-app:8080 playwright npx playwright test --config=playwright.rehearsal.config.ts tests/Rehearsal/source.spec.ts
end

begin "2. The target off the source: its PostgreSQL with the marker drill, its SSH gateway, its application"
docker compose --profile rehearsal up -d --build --wait drill-db drill-gateway operator drill-app
end

begin "3. The operator's own recovery key and SSH key, on the operator's machine only"
operator operator age-keygen -o "$KEY"
RECIPIENT="$(operator operator age-keygen -y "$KEY")"
[[ "$RECIPIENT" =~ ^age1[a-z0-9]+$ ]] || { echo "no recipient"; exit 1; }
operator operator ssh-keygen -q -t ed25519 -N '' -f /home/operator/.ssh/id_ed25519
operator operator sh -c 'printf "%s\n" "Host drill-gateway" "  User operator" "  IdentityFile /home/operator/.ssh/id_ed25519" "  StrictHostKeyChecking accept-new" "  UserKnownHostsFile /home/operator/.ssh/known_hosts" "  BatchMode yes" > /home/operator/.ssh/config && chmod 600 /home/operator/.ssh/config'
operator operator cat /home/operator/.ssh/id_ed25519.pub \
    | docker compose exec -T drill-gateway sh -c 'cat > /home/operator/.ssh/authorized_keys && chown operator:operator /home/operator/.ssh/authorized_keys && chmod 600 /home/operator/.ssh/authorized_keys && chown operator:operator /srv/media'
end

begin "4. The backup of the source, to the recipient alone (SC-03 on demand)"
operator operator curl --silent --show-error --fail --aws-sigv4 'aws:amz:us-east-1:s3' --user "${STORE_KEY_ID}:${STORE_KEY}" -X PUT -H 'x-amz-bucket-object-lock-enabled: true' "${STORE_ENDPOINT}/${BUCKET}"
BACKUP_LINE="$(docker compose exec -T -e APP_PROCESS_ROLE=backup -e BACKUP_STORE_ENDPOINT="$STORE_ENDPOINT" -e BACKUP_STORE_BUCKET="$BUCKET" -e BACKUP_STORE_KEY_ID="$STORE_KEY_ID" -e BACKUP_STORE_KEY="$STORE_KEY" -e BACKUP_RECIPIENT="$RECIPIENT" app php artisan backup:run --reason=ON_DEMAND | grep -E '^BACKUP_SUCCEEDED ')"
echo "$BACKUP_LINE"
BACKUP_ID="$(cut -d' ' -f2 <<< "$BACKUP_LINE")"
end

RESTORE_STARTED="$(date +%s)"

begin "5. RB-01 step 8: download, hashes and decryption on the operator's machine"
FETCHED="$(operator -e BANTUKASIR_STORE_ENDPOINT="$STORE_ENDPOINT" -e BANTUKASIR_STORE_BUCKET="$BUCKET" -e BANTUKASIR_STORE_KEY_ID="$STORE_KEY_ID" -e BANTUKASIR_STORE_KEY="$STORE_KEY" -e BANTUKASIR_RECOVERY_KEY="$KEY" operator bash /workspace/scripts/operations/restore/fetch-backup.sh "$BACKUP_ID" "$WORK")"
echo "$FETCHED"
MANIFEST_SHA256="$(sed -E 's/^.*manifest_sha256=([0-9a-f]{64})$/\1/' <<< "$FETCHED")"
# No application container holds the recovery key or a decrypted copy.
for container in app drill-app; do
    if docker compose exec -T "$container" sh -c "test -e '$KEY' || ls /var/www/html/storage/app/backup-tmp 2>/dev/null | grep -q 'plain'"; then
        echo "a decrypted copy or the recovery key is in the container $container"
        exit 1
    fi
done
end

begin "6. RB-01 step 9: pg_restore through the SSH tunnel as the migration role, the grants, the privilege test"
operator operator ssh -f -N -o ExitOnForwardFailure=yes -L "127.0.0.1:${TUNNEL_PORT}:drill-db:5432" drill-gateway
target_sql pg_isready -q -t 30
target_sql pg_restore --no-owner --no-privileges --exit-on-error --single-transaction --dbname=bantukasir "$WORK/plain/dump.pgcustom"
docker compose exec -T -e DB_MIGRATION_PASSWORD="$DRILL_MIGRATION_PASSWORD" drill-app php artisan db:apply-grants --environment=drill
docker compose exec -T drill-app php artisan db:check-privileges
end

begin "7. RB-01 step 10: the files over SSH into the target's file volume, each checked"
target_sql bash /workspace/scripts/operations/restore/copy-files.sh "$WORK" operator@drill-gateway /srv/media
end

begin "8. RB-01 step 11: the counts against the manifest, reconciliation:run, the decrypted copy destroyed"
target_sql env BANTUKASIR_MANIFEST="$WORK/plain/manifest.json" psql -X -v ON_ERROR_STOP=1 -v environment=drill -f /workspace/scripts/operations/restore/compare-counts.sql
docker compose exec -T drill-app php artisan reconciliation:run --operator --environment=drill
operator operator rm -rf "$WORK"
end

begin "9. RB-01 steps 12 to 14: OP-04, the sequence advance, synchronous_commit"
docker compose exec -T drill-app php artisan restore:after --environment=drill --note="$NOTE"
target_sql psql -X -v ON_ERROR_STOP=1 -v environment=drill -v sales_seen=0 -v sessions_seen=0 -f /workspace/scripts/operations/restore/advance-sequences.sql
[ "$(target_sql psql -X -At -c 'show synchronous_commit')" = on ] || { echo "synchronous_commit is not on"; exit 1; }
end

RESTORE_SECONDS=$(( $(date +%s) - RESTORE_STARTED ))

begin "10. RB-02 step 7: /up, then a receipt and a session report through an Owner recovery link of OP-02"
[ "$(operator operator curl --silent --output /dev/null --write-out '%{http_code}' http://drill-app:8080/up)" = 200 ] || { echo "/up does not answer 200"; exit 1; }
RECOVERY="$(docker compose exec -T drill-app php artisan owner:recover --environment=drill --note="$NOTE")"
LINK="$(grep -oE 'http://drill-app:8080/set-password#u=[0-9]+&t=[^[:space:]]+' <<< "$RECOVERY" || true)"
[ -n "$LINK" ] || { echo "OP-02 printed no link"; exit 1; }
mask "$LINK"
SALE="$(target_sql psql -X -At -F ' ' -c "select id, sale_number from sales order by id desc limit 1")"
SESSION="$(target_sql psql -X -At -F ' ' -c "select id, session_number from cash_sessions where status = 'CLOSED' order by id desc limit 1")"
[ -n "$SALE" ] && [ -n "$SESSION" ] || { echo "the restored data hold no Sale or no closed session"; exit 1; }
docker compose run --rm -T -e E2E_BASE_URL=http://drill-app:8080 -e REHEARSAL_LINK="$LINK" -e REHEARSAL_OWNER_EMAIL=hendra@contoh.test \
    -e REHEARSAL_SALE_ID="${SALE%% *}" -e REHEARSAL_SALE_NUMBER="${SALE#* }" -e REHEARSAL_SESSION_ID="${SESSION%% *}" -e REHEARSAL_SESSION_NUMBER="${SESSION#* }" \
    playwright npx playwright test --config=playwright.rehearsal.config.ts tests/Rehearsal/target.spec.ts
end

begin "11. RB-02 step 9: the target and every decrypted file destroyed"
operator operator sh -c 'pkill -u operator ssh || true; rm -rf /home/operator/work /home/operator/recovery.key'
docker compose --profile rehearsal rm --stop --force -v drill-app drill-gateway drill-db drill-db-setup operator
docker volume rm bantukasir_drill-media
end

DRILL_FINISHED="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
DURATIONS="$(printf '%s; ' "${TIMES[@]}" | sed -E 's/\|/: /g; s/; $//')"

begin "12. RB-02 step 10: the drill recorded on the source by OP-08"
docker compose exec -T app php artisan restore:record-drill --environment=local --kind=ROUTINE --backup="$BACKUP_ID" --manifest-sha256="$MANIFEST_SHA256" \
    --started-at="$DRILL_STARTED" --finished-at="$DRILL_FINISHED" --result=PASS --restore-seconds="$RESTORE_SECONDS" --note="$(cut -c1-1000 <<< "Gladi lokal TASK-037. Durasi (detik): ${DURATIONS}")"
end

echo "== Durations of the rehearsal, in seconds (RB-02 step 8)"
for entry in "${TIMES[@]}"; do
    printf '%6s  %s\n' "${entry##*|}" "${entry%|*}"
done
echo "restore_seconds=${RESTORE_SECONDS}"
echo "REHEARSAL_PASSED backup=${BACKUP_ID}"
