#!/usr/bin/env bash
# scripts/executor/review-pack.sh: the review package that the isolated,
# read-only reviewer agent reads (D-101; EXECUTION_CONTEXT CE-40).
#
# Usage: bash scripts/executor/review-pack.sh --base <sha> --round <n> [--previous <package>]
#        bash scripts/executor/review-pack.sh --check <package>
# The first form writes STATE_DIR/review/<task>/r<n>/: MANIFEST.txt (the Task,
# its class, the base, the snapshot tree, the previous tree, the review or
# verification agent of the table, the time, HEAD, the hash of git ls-files -s
# and the hash of the state file), diff.patch (git diff <base> <tree>),
# stat.txt, files.txt (the changed paths with their status) and, with
# --previous, fix.patch (git diff <previous tree> <tree>). It prints the
# package's path for the Read tool. It never overwrites a package.
# --check computes the snapshot tree, HEAD, the index hash and the state hash
# again and prints UNCHANGED when all four equal the manifest, otherwise
# CHANGED <what>, which is a stop.
# Exit codes: 0 written or UNCHANGED; 1 CHANGED or refused; 2 usage.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

usage() { echo "USAGE review-pack.sh --base <sha> --round <n> [--previous <package>] | --check <package>"; exit 2; }
BASE_SHA="" ROUND="" PREV="" CHECK=""
while [ $# -gt 0 ]; do
    case "$1" in
        --base) BASE_SHA="${2:-}"; shift 2 ;;
        --round) ROUND="${2:-}"; shift 2 ;;
        --previous) PREV="${2:-}"; shift 2 ;;
        --check) CHECK="${2:-}"; shift 2 ;;
        *) usage ;;
    esac
done
mval() { sed -n "s/^$2: //p" "$1/MANIFEST.txt" | sed -n '1p'; }

if [ -n "$CHECK" ]; then
    [ -f "$CHECK/MANIFEST.txt" ] || { echo "REFUSED no manifest in $CHECK"; exit 1; }
    changed=""
    [ "$(snapshot_tree)" = "$(mval "$CHECK" Tree)" ] || changed="$changed tree"
    [ "$(git rev-parse HEAD)" = "$(mval "$CHECK" HEAD)" ] || changed="$changed HEAD"
    [ "$(index_hash)" = "$(mval "$CHECK" Index)" ] || changed="$changed index"
    [ "$(state_hash)" = "$(mval "$CHECK" State)" ] || changed="$changed state"
    if [ -n "$changed" ]; then echo "CHANGED$changed"; exit 1; fi
    echo "UNCHANGED"
    exit 0
fi

[ -n "$BASE_SHA" ] && is_int "$ROUND" || usage
base="$(git rev-parse --verify "$BASE_SHA^{commit}")" || { echo "REFUSED unknown base $BASE_SHA"; exit 1; }
task="$(st_get task.id)"
br="$(cur_branch)"
if [ -z "$task" ]; then
    case "$br" in task/TASK-*) task="$(task_of_branch "$br")" ;; ops/*) task="ops-${br#ops/}" ;; *) task=none ;; esac
fi
task="${task//\//-}"
pkg="$STATE_DIR/review/$task/r$ROUND"
[ ! -e "$pkg" ] || { echo "REFUSED $pkg exists; a package is never overwritten"; exit 1; }
prevtree="none"
if [ -n "$PREV" ]; then
    [ -f "$PREV/MANIFEST.txt" ] || { echo "REFUSED no manifest in $PREV"; exit 1; }
    prevtree="$(mval "$PREV" Tree)"
    agent="$(st_get task.verifyAgent)"
    role=verification
else
    agent="$(st_get task.reviewAgent)"
    role=review
fi
tree="$(snapshot_tree)"
mkdir -p "$pkg"
{
    echo "MANIFEST of the review package $task round $ROUND (D-101, EXECUTION_CONTEXT CE-40)"
    echo "Task: $task"
    echo "Class: $(st_get task.class)"
    echo "Base: $base"
    echo "Tree: $tree"
    echo "Previous tree: $prevtree"
    echo "Agent ($role): ${agent:-none recorded}"
    echo "Time: $(stamp)"
    echo "HEAD: $(git rev-parse HEAD)"
    echo "Index: $(index_hash)"
    echo "State: $(state_hash)"
    echo "Branch: ${br:-detached}"
} > "$pkg/MANIFEST.txt"
git diff "$base" "$tree" > "$pkg/diff.patch"
git diff --stat "$base" "$tree" > "$pkg/stat.txt"
git diff --name-status "$base" "$tree" > "$pkg/files.txt"
if [ -n "$PREV" ]; then git diff "$prevtree" "$tree" > "$pkg/fix.patch"; fi
log_event "REVIEW_PACK $task r$ROUND tree $tree"
echo "PACKAGE $(native_path "$pkg")"
