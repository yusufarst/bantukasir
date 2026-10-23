#!/usr/bin/env bash
# scripts/executor/snapshot.sh: a tree object of the working tree (D-101;
# EXECUTION_CONTEXT CE-40): the tracked files and the untracked files that Git
# does not ignore, without the known untracked ones (plan-p0-p11-review.patch,
# "Claude outputs/", .claude/settings.local.json). It uses a temporary index in
# the executor state (GIT_INDEX_FILE) and never touches the real index or the
# working tree; it removes the temporary index.
#
# Usage: bash scripts/executor/snapshot.sh [--base <sha>]
# Prints "TREE <sha>" and, with --base, the diffstat from the base.
# Exit codes: 0 done; 1 failed; 2 usage.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

base=""
case "${1:-}" in
    "") ;;
    --base) base="${2:-}"; [ -n "$base" ] || { echo "USAGE snapshot.sh [--base <sha>]"; exit 2; } ;;
    *) echo "USAGE snapshot.sh [--base <sha>]"; exit 2 ;;
esac

tree="$(snapshot_tree)"
echo "TREE $tree"
if [ -n "$base" ]; then git diff --stat "$base" "$tree"; fi
