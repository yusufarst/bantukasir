#!/usr/bin/env bash
# scripts/executor/push.sh: the only way the executor pushes (D-101;
# EXECUTION_CONTEXT CE-30, CE-31, EXC-31, AGENTS rule 11). An ask rule of
# .claude/settings.json, so the Owner approves each push at its own prompt.
#
# Usage: bash scripts/executor/push.sh
#        bash scripts/executor/push.sh --probe    prints PROBE push.sh, changes nothing
# It refuses, with one line "REFUSED <CODE> <detail>": a branch other than
# task/... or ops/..., and a detached HEAD; when the state records task.branch,
# any other branch; SAME_INVOCATION, the Bash call of the last commit;
# UNAPPROVED_COMMIT <sha>, a commit of HEAD --not --remotes=origin that
# commit.sh did not create; a guard stamp that is missing or not HEAD;
# NOT_FAST_FORWARD, when origin/<branch> is not an ancestor of HEAD (a stop).
# Then it records the call in push.lastInvocation, pushes
# refs/heads/<branch> without force, checks it with git ls-remote, and sets
# pr.head when the state records an open pull request for the branch.
# Exit codes: 0 pushed (or --probe); 1 refused; 2 usage; 3 the push or its check failed.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

refuse() { echo "REFUSED $*"; exit 1; }

if [ "$#" -eq 1 ] && [ "$1" = --probe ]; then echo "PROBE push.sh"; exit 0; fi
[ "$#" -eq 0 ] || { echo "USAGE push.sh [--probe]"; exit 2; }

br="$(cur_branch)"
case "$br" in
    task/TASK-[0-9][0-9][0-9]-* | ops/?*) ;;
    *) refuse "BRANCH ${br:-detached HEAD}: only a task/ or ops/ branch is pushed" ;;
esac
tb="$(st_get task.branch)"
[ -z "$tb" ] || [ "$tb" = "$br" ] || refuse "BRANCH the state's branch is $tb, not $br"

inv="$(invocation_id)" || refuse "INVOCATION the Bash call could not be identified"
[ "$inv" != "$(st_get commit.lastInvocation)" ] || refuse "SAME_INVOCATION a commit ran in this Bash call; push in a separate call"

timeout 60 git fetch --quiet origin || refuse "FETCH git fetch origin failed"
head="$(git rev-parse HEAD)"
approved="$(st_all commit.approved)"
for c in $(git rev-list HEAD --not --remotes=origin); do
    printf '%s\n' "$approved" | grep -qx "$c" || refuse "UNAPPROVED_COMMIT $c was not created by commit.sh"
done

stampf="$STATE_DIR/guard-pass"
[ -f "$stampf" ] || refuse "GUARD no guard stamp; run bash scripts/executor/guard.sh"
[ "$(sed -n '1p' "$stampf")" = "$head" ] || refuse "GUARD the guard stamp is not for HEAD $head"

if git rev-parse -q --verify "refs/remotes/origin/$br" > /dev/null; then
    git merge-base --is-ancestor "refs/remotes/origin/$br" HEAD || refuse "NOT_FAST_FORWARD origin/$br is not an ancestor of HEAD; stop and report"
fi

st_set push.lastInvocation "$inv"
log_event "PUSH $br $head"
if ! timeout 300 git push --quiet origin "refs/heads/$br:refs/heads/$br"; then echo "FAILED git push"; exit 3; fi
remote="$(git ls-remote origin "refs/heads/$br" | cut -f1)"
[ "$remote" = "$head" ] || { echo "FAILED origin has $remote for $br, not $head"; exit 3; }
if [ -n "$(st_get pr.number)" ] && { [ -z "$tb" ] || [ "$tb" = "$br" ]; }; then st_set pr.head "$head"; fi
log_event "PUSHED $br $head"
echo "PUSHED $br $head"
