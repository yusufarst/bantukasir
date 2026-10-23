#!/usr/bin/env bash
# scripts/executor/commit.sh: the only way the executor commits (D-101;
# EXECUTION_CONTEXT CE-30, EXC-30, AGENTS rules 9, 11 and 12). An ask rule of
# .claude/settings.json, so the Owner approves each commit at its own prompt.
#
# Usage: bash scripts/executor/commit.sh -m <subject> [-m <paragraph>]... -- <file>...
#        bash scripts/executor/commit.sh --probe    prints PROBE commit.sh, changes nothing
# It refuses, with one line "REFUSED <CODE> <detail>": a branch other than
# task/TASK-NNN-<short> or ops/<name>; a subject not beginning "TASK-NNN: " with
# the branch's number, or "ops: " on an ops/ branch; a word of the forbidden
# list, a trailer or a last line "Key: value"; an identity other than the
# Owner's; a path that is a directory, ".", "-A", "--all", a pattern, missing,
# or excluded; anything already staged; PROTECTED_PATH, a path under
# scripts/executor/ or .claude/ on a task/ branch; SAME_INVOCATION, the Bash
# call of the last push.
# Then it stages exactly those paths, commits, records the commit in
# commit.approved and the call in commit.lastInvocation, and prints the commit.
# Exit codes: 0 committed (or --probe); 1 refused; 2 usage; 3 failed after
# staging (its own paths unstaged again, the working tree untouched).
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

refuse() { echo "REFUSED $*"; exit 1; }

if [ "$#" -eq 1 ] && [ "$1" = --probe ]; then echo "PROBE commit.sh"; exit 0; fi

PARAS=()
PATHS=()
while [ $# -gt 0 ]; do
    case "$1" in
        -m) [ $# -ge 2 ] || { echo "USAGE -m needs a value"; exit 2; }; PARAS+=("$2"); shift 2 ;;
        --) shift; PATHS=("$@"); break ;;
        *) echo "USAGE commit.sh -m <subject> [-m <paragraph>]... -- <file>..."; exit 2 ;;
    esac
done
[ "${#PARAS[@]}" -ge 1 ] && [ "${#PATHS[@]}" -ge 1 ] || { echo "USAGE commit.sh -m <subject> [-m <paragraph>]... -- <file>..."; exit 2; }

# The branch.
br="$(cur_branch)"
case "$br" in
    task/TASK-[0-9][0-9][0-9]-*) kind=task ;;
    ops/?*) kind=ops ;;
    *) refuse "BRANCH ${br:-detached HEAD}: only task/TASK-NNN-<short> or ops/<name>" ;;
esac
printf '%s' "$br" | grep -qE '^(task/TASK-[0-9]{3}-[a-z0-9][a-z0-9-]*|ops/[a-z0-9][a-z0-9._-]*)$' || refuse "BRANCH $br is not a valid task/ or ops/ name"

# The message.
subject="${PARAS[0]}"
case "$subject" in *$'\n'*) refuse "SUBJECT one line only" ;; esac
if [ "$kind" = task ]; then
    num="$(task_of_branch "$br")"
    case "$subject" in "$num: "?*) ;; *) refuse "SUBJECT must begin \"$num: \"" ;; esac
else
    case "$subject" in "ops: "?*) ;; *) refuse "SUBJECT must begin \"ops: \"" ;; esac
fi
msg="$(mktemp)"
trap 'rm -f -- "$msg"' EXIT
{
    printf '%s\n' "$subject"
    for p in "${PARAS[@]:1}"; do printf '\n%s\n' "$p"; done
} > "$msg"
case "$(msg_problem "$msg" 1)" in
    WORD) refuse "MESSAGE holds a forbidden word (an attribution or a mention of an AI model, agent or tool)" ;;
    TRAILER) refuse "MESSAGE holds a trailer" ;;
    KEY_VALUE) refuse "MESSAGE ends with a line of the form Key: value" ;;
esac

# The identity.
want="$OWNER_NAME <$OWNER_EMAIL>"
ai="$(git var GIT_AUTHOR_IDENT | sed -E 's/ [0-9]+ [+-][0-9]{4}$//')"
ci="$(git var GIT_COMMITTER_IDENT | sed -E 's/ [0-9]+ [+-][0-9]{4}$//')"
[ "$ai" = "$want" ] && [ "$ci" = "$want" ] || refuse "IDENTITY author \"$ai\", committer \"$ci\"; only $want"

# The paths.
for p in "${PATHS[@]}"; do
    case "$p" in
        '' | . | ./ | -A | --all | -* ) refuse "PATH \"$p\" is not one file" ;;
        *[*?[]* ) refuse "PATH \"$p\" is a pattern" ;;
        /* | ./* | *../* | ..* | *//* | *\\*) refuse "PATH \"$p\" is not a plain path relative to the repository" ;;
    esac
    [ -d "$p" ] && refuse "PATH $p is a directory"
    excluded_path "$p" && refuse "PATH $p is excluded (AGENTS rule 9, EXC-30)"
    if [ ! -f "$p" ] && [ ! -L "$p" ]; then
        git ls-files --error-unmatch -- "$p" > /dev/null 2>&1 || refuse "PATH $p is neither a file nor a deleted tracked file"
    fi
    if [ "$kind" = task ]; then
        case "$p" in scripts/executor/* | .claude/*) refuse "PROTECTED_PATH $p changes only on an ops/ branch (CE-70, CE-82)" ;; esac
    fi
done
merging=0
[ -e "$(git rev-parse --git-dir)/MERGE_HEAD" ] && merging=1
# In a merge of develop (EXC-36) the index holds the merge's own changes.
[ "$merging" = 1 ] || [ -z "$(git diff --cached --name-only)" ] || refuse "STAGED the index already holds staged changes"

# A commit and its push are separate Bash calls (CE-30).
inv="$(invocation_id)" || refuse "INVOCATION the Bash call could not be identified"
[ "$inv" != "$(st_get push.lastInvocation)" ] || refuse "SAME_INVOCATION a push ran in this Bash call"

# Stage exactly those paths, check, commit.
unstage() { git reset -q -- "${PATHS[@]}" > /dev/null 2>&1 || true; }
if ! git add -- "${PATHS[@]}"; then unstage; echo "FAILED staging"; exit 3; fi
wanted="$(printf '%s\n' "${PATHS[@]}" | LC_ALL=C sort -u)"
if [ "$merging" = 1 ]; then
    [ -z "$(git diff --name-only --diff-filter=U)" ] || { unstage; echo "FAILED unmerged paths remain"; exit 3; }
    staged="$(printf '%s\n' "${PATHS[@]}" | while IFS= read -r p; do git diff --cached --quiet HEAD -- "$p" || printf '%s\n' "$p"; done | LC_ALL=C sort -u)"
else
    staged="$(git diff --cached --name-only --no-renames | LC_ALL=C sort -u)"
fi
if [ "$staged" != "$wanted" ]; then
    unstage
    echo "FAILED the staged set differs from the paths given (a path without a change, or another path)"
    printf 'staged:\n%s\nwanted:\n%s\n' "$staged" "$wanted"
    exit 3
fi
if ! git commit -q --no-edit -F "$msg"; then unstage; echo "FAILED git commit"; exit 3; fi
sha="$(git rev-parse HEAD)"
st_add commit.approved "$sha"
st_set commit.lastInvocation "$inv"
log_event "COMMIT $sha $br"
echo "COMMITTED $sha"
git show --stat --format='%H %s' HEAD
