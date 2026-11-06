#!/usr/bin/env bash
# The required jobs of a run of the ci workflow (TEST_STRATEGY section 7, D-120):
# required-jobs.sh <job name>.... The job `Command contract` runs it as its last
# step, with the names of the jobs `PostgreSQL (1/2)` and `PostgreSQL (2/2)`. The
# tick and pr.sh read a head as green only when `Command contract` passed and no
# other check run failed (scripts/executor/lib.sh, ci_result), and a job that is
# skipped, or missing from the run, fails no check run of its own: this step makes
# `Command contract` pass only when each named job of the same run, in its latest
# attempt, finished with success. It waits for a job still queued or running,
# reading the run's jobs every REQUIRED_JOBS_POLL seconds (30 by default) for at
# most REQUIRED_JOBS_WAIT seconds (1800). It only reads, with the token GitHub
# issues to the workflow (GH_TOKEN) and the run's own GITHUB_REPOSITORY and
# GITHUB_RUN_ID. The status is 0 when every named job passed; 1 when one is
# missing, ended otherwise, or still runs at the end of the wait; and 2 on a usage
# error or when the run's jobs cannot be read, so that an unknown state never passes.
set -uo pipefail

if [ $# -eq 0 ] || [ -z "${GITHUB_REPOSITORY:-}" ] || [ -z "${GITHUB_RUN_ID:-}" ]; then
    echo "REQUIRED_JOBS_USAGE GITHUB_REPOSITORY=<owner/name> GITHUB_RUN_ID=<run> required-jobs.sh <job name>..."
    exit 2
fi

wait_seconds="${REQUIRED_JOBS_WAIT:-1800}"
poll="${REQUIRED_JOBS_POLL:-30}"
start="$(date +%s)"

while :; do
    # Every attempt of every job of the run, as attempt|id|status|conclusion|name.
    if ! jobs="$(gh api --paginate "repos/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID/jobs?filter=all&per_page=100" --jq '.jobs[] | "\(.run_attempt)|\(.id)|\(.status)|\(.conclusion // "")|\(.name)"')"; then
        echo "REQUIRED_JOBS_UNREADABLE the jobs of run $GITHUB_RUN_ID cannot be read"
        exit 2
    fi

    waiting=""
    for name in "$@"; do
        # The job's latest attempt: the highest attempt, then the highest identifier.
        latest="$(printf '%s\n' "$jobs" | awk -F'|' -v want="$name" '
            NF >= 5 {
                n = $5
                for (i = 6; i <= NF; i++) n = n "|" $i
                if (n == want && ($1 + 0 > a + 0 || ($1 + 0 == a + 0 && $2 + 0 > b + 0))) { a = $1; b = $2; s = $3; c = $4; seen = 1 }
            }
            END { if (seen) print s "|" c }')"
        case "$latest" in
            "") echo "REQUIRED_JOBS_MISSING $name is not a job of run $GITHUB_RUN_ID"; exit 1 ;;
            "completed|success") ;;
            "completed|"*) echo "REQUIRED_JOBS_FAILED $name ended with ${latest#completed|}"; exit 1 ;;
            *) waiting="$name, ${latest%%|*}" ;;
        esac
    done

    if [ -z "$waiting" ]; then
        printf 'REQUIRED_JOBS_PASSED'
        printf ' "%s"' "$@"
        printf '\n'
        exit 0
    fi

    if [ $(( $(date +%s) - start + poll )) -ge "$wait_seconds" ]; then
        echo "REQUIRED_JOBS_TIMEOUT $waiting, still not finished after $wait_seconds seconds"
        exit 1
    fi
    sleep "$poll"
done
