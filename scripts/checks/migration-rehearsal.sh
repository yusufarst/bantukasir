#!/usr/bin/env bash
# The rehearsal of every migration on the populated provisional benchmark dataset (RELEASE_PROCESS REL-21;
# TECHNICAL_RULES section 13.2; TASK-043 scope items 3 and 4, AC-043-03 and AC-043-04), and the dry run of
# the PERF01 scenarios on it, on CI's runner, in the containers of the local environment, with synthetic
# data only: the job Migration rehearsal of .github/workflows/ci.yml runs it. Nothing here reaches staging
# or production (EXECUTION_CONTEXT EXC-13); every password is made at run time by the harness and kept in
# the ignored folder storage/app/perf, never printed.
#
#   REHEARSAL_BASE=<commit> bash scripts/checks/migration-rehearsal.sh
#
#   1. The base: the commit that the change is compared with — a pull request's base, or develop before a
#      push — checked out beside the tree; its local environment migrates the local database and, where
#      the base holds the harness, builds the base dataset with the base's own code and Actions, as a
#      shop's data stands before a release.
#   2. The head: its local environment on the same database volume; its frontend built for the pages of
#      the dry run.
#   3. The rehearsal: the migration job of RB-20 step 8 (release:migrate) with the head's new migrations,
#      those of its tree that the base does not hold (EXC-41: a merged migration never changes), or none:
#      the preflight, the migrations, then the verification queries of REL-20 — row counts, constraints
#      valid, privileges equal to SCHEMA section 10, a clean reconciliation:run. A base without the harness
#      is TASK-043's own base: the dataset is then built at the head, which must add no migration, and the
#      job verifies it the same way.
#   4. The dry run: PERF_RUNS runs (1 by default) of PERF_SECONDS seconds (120 by default), each with its
#      plan, the load tool's run and its record, printed as a table and added to the job's summary. It is
#      a dry run, never PERF01's evidence (AC-043-03).
#
# It stops at the first failure with the step that failed, and ends with the durations of every step.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

BASE_REVISION="${REHEARSAL_BASE:?REHEARSAL_BASE names the commit of the base}"
SCALE="${REHEARSAL_SCALE:-1000000}"
RUNS="${PERF_RUNS:-1}"
SECONDS_PER_RUN="${PERF_SECONDS:-120}"
BASE_DIR="$(mktemp -d)/base"

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

base_compose() {
    docker compose --project-directory "$BASE_DIR" -f "$BASE_DIR/compose.yaml" "$@"
}

migrations_of() {
    find "$1/database/migrations" -maxdepth 1 -name '*.php' -printf '%f\n' | sed 's/\.php$//' | LC_ALL=C sort
}

begin "1. the base $BASE_REVISION"
git worktree add --detach "$BASE_DIR" "$BASE_REVISION"
base_compose up -d --build --wait
base_compose exec -T app php artisan migrate --database=pgsql_migration --force
mkdir -p storage/app/perf

if [ -f "$BASE_DIR/tests/Performance/perf.php" ]; then
    base_compose exec -T app php tests/Performance/perf.php dataset --dataset=base --scale="$SCALE"
    base_compose exec -T app cat storage/app/perf/dataset.json > storage/app/perf/dataset.json
    POPULATED=base
else
    echo "The base holds no harness: the dataset is built at the head (TASK-043's own base)."
    POPULATED=head
fi

base_compose down
end

begin "2. the head"
docker compose up -d --build --wait
docker compose run --rm node npm ci
docker compose run --rm node npm run build
end

begin "3. the rehearsal of the head's migrations"
NEW="$(LC_ALL=C comm -13 <(migrations_of "$BASE_DIR") <(migrations_of "$ROOT") | paste -sd, -)"
PENDING="${NEW:-none}"
echo "The head's new migrations: $PENDING"

if [ "$POPULATED" = head ]; then
    if [ "$PENDING" != none ]; then
        echo "A base without the harness and a head with new migrations: nothing can be rehearsed on data."
        exit 1
    fi

    docker compose exec -T app php tests/Performance/perf.php dataset --dataset=base --scale="$SCALE"
fi

docker compose exec -T app php artisan release:migrate --environment=local --migrations="$PENDING" --backup-schedule-paused
end

for run in $(seq 1 "$RUNS"); do
    begin "4. the dry run $run of $RUNS"
    docker compose exec -T app php tests/Performance/perf.php plan --run="$run" --runs="$RUNS" --seconds="$SECONDS_PER_RUN"
    docker compose run --rm -e PERF_RUN="$run" -e PERF_SECONDS="$SECONDS_PER_RUN" k6 run --quiet tests/Performance/scenarios/perf01.js
    docker compose exec -T -e PERF_REVISION="$(git rev-parse HEAD)" -e PERF_LOAD_TOOL="k6 2.3.0" -e PERF_HOST="GitHub-hosted runner ubuntu-24.04, the containers of the local environment" app php tests/Performance/perf.php record --run="$run" | tee storage/app/perf/record-"$run".md

    if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
        cat storage/app/perf/record-"$run".md >> "$GITHUB_STEP_SUMMARY"
    fi

    end
done

echo "== durations (seconds)"
for entry in "${TIMES[@]}"; do
    echo "${entry%%|*}: ${entry##*|}"
done
echo "REHEARSAL_PASSED populated at the $POPULATED, migrations: $PENDING"
