#!/usr/bin/env bash
# scripts/executor/lib.sh: the shared library of continuous execution (D-101,
# EXECUTION_CONTEXT section 19). Sourced by the other scripts, never run.
#
# CE rules: CE-10 and CE-11 (the parsers of TASK_PLAN section 4 and of the
# effort table), CE-20 (the executor state), CE-22 (the lock and the liveness
# check of its holder), CE-30 (the invocation identity that keeps a commit and
# its push in separate Bash calls), CE-31 (the message and path rules shared by
# commit.sh, guard.sh and pr.sh), CE-43 (the accounts whose comments count).
#
# Usage: . "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
# Exit codes: it exits 2 when the current directory is not in a Git work tree;
# each function returns 0 on success and non-zero on failure.
#
# Every GitHub call and every process query goes through a function below that
# prints simple lines (gh api ... --jq, free text as base64). With both
# EXECUTOR_SELFTEST=1 and EXECUTOR_FIXTURES=<dir> set, and only then, they read
# fixture files instead (selftest.sh); EXECUTOR_SELFTEST=1 alone never reaches
# GitHub. EXECUTOR_NOW and EXECUTOR_POLL_SECONDS are honoured only then too.

REPO=yusufarst/bantukasir
OWNER_LOGIN=yusufarst
OWNER_NAME="Yusuf Arif Setiawan"
OWNER_EMAIL=134618411+yusufarst@users.noreply.github.com
BASE=develop
# Bot accounts whose comments are read as findings (CE-43); none is configured.
REVIEW_BOTS=""
# KNOWN_UNTRACKED: plan-p0-p11-review.patch, everything under "Claude outputs/"
# and .claude/settings.local.json (known_untracked below).
LOCK_ESCALATE_MINUTES=120
CI_MAX_MINUTES=60
MAX_CI_FAILURES=3
MAX_RESUMES=3
SHELL_NAMES="bash sh dash zsh bash.exe sh.exe cmd.exe conhost.exe env.exe timeout.exe"
SYSTEM_NAMES="explorer.exe svchost.exe services.exe wininit.exe winlogon.exe userinit.exe csrss.exe smss.exe System Idle"
PUBLIC_HOSTS="github.com docs.github.com laravel.com php.net www.php.net postgresql.org www.postgresql.org nodejs.org npmjs.com www.npmjs.com packagist.org getcomposer.org react.dev typescriptlang.org www.typescriptlang.org vite.dev vitejs.dev vitest.dev tailwindcss.com playwright.dev eslint.org phpstan.org inertiajs.com ui.shadcn.com json.schemastore.org docs.docker.com ghcr.io nginx.org owasp.org cheatsheetseries.owasp.org developer.mozilla.org w3.org www.w3.org unicode.org semver.org code.claude.com example.com example.org example.net localhost"
PUBLIC_SUFFIXES=".test .invalid .example .localhost"
# Words that no commit message, pull request title or description may hold
# (AGENTS rule 12, CE-30, CE-32); the last two only as whole words.
FORBIDDEN_RE='co-authored-by|generated-by|assisted-by|claude|anthropic|openai|chatgpt|codex|copilot|gemini'
FORBIDDEN_WORDS_RE='opus|fable'

ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "NOT_A_REPOSITORY"; exit 2; }
cd "$ROOT" || exit 2
STATE_DIR="$(cd "$(git rev-parse --git-common-dir)" && pwd)/bantukasir-executor"
STATE_FILE="$STATE_DIR/state"
LOG_FILE="$STATE_DIR/log"
LOCK_DIR="$STATE_DIR/lock"
REQ_FILE="$STATE_DIR/requests"
IGN_FILE="$STATE_DIR/ignored"
export GIT_OPTIONAL_LOCKS=0

# ---------------------------------------------------------------- self-test mode
selftest_mode() { [ "${EXECUTOR_SELFTEST:-}" = 1 ] && [ -n "${EXECUTOR_FIXTURES:-}" ] && [ -d "${EXECUTOR_FIXTURES:-}" ]; }
fx() { printf '%s/%s' "$EXECUTOR_FIXTURES" "$1"; }
# fixture_out <name>: the fixture <name>, or the next of a sequence <name>.1,
# <name>.2 ... (the last one repeats), or nothing.
fixture_out() {
    local f c k
    f="$(fx "$1")"
    if [ -e "$f.1" ]; then
        c=$(( $(cat "$f.count" 2>/dev/null || echo 0) + 1 ))
        printf '%s\n' "$c" > "$f.count"
        k=$c
        while [ "$k" -gt 1 ] && [ ! -e "$f.$k" ]; do k=$((k - 1)); done
        cat "$f.$k"
    elif [ -e "$f" ]; then
        cat "$f"
    fi
    return 0
}

# ---------------------------------------------------------------- small helpers
is_int() { case "${1:-}" in '' | *[!0-9]*) return 1 ;; esac; }
now() { if selftest_mode && is_int "${EXECUTOR_NOW:-}"; then printf '%s\n' "$EXECUTOR_NOW"; else date +%s; fi; }
stamp() { date -u -d "@$(( $(now) + 25200 ))" +%Y-%m-%dT%H:%M:%S+07:00; }
b64e() { printf '%s' "$1" | base64 -w0; }
b64d() { printf '%s' "$1" | base64 -d 2> /dev/null || true; }
in_words() { local x="$1" w; for w in $2; do [ "$x" = "$w" ] && return 0; done; return 1; }
cur_branch() { git symbolic-ref --short -q HEAD || true; }
task_of_branch() { case "$1" in task/TASK-[0-9][0-9][0-9]-*) printf '%s\n' "${1:5:8}" ;; esac; }
is_windows() { [ -r "/proc/$$/winpid" ]; }
native_path() { if is_windows; then (cd "$1" && pwd -W); else (cd "$1" && pwd); fi; }
sha256_of() { sha256sum | cut -d' ' -f1; }
# id_gt <a> <b>: GitHub identifiers, compared as integers.
id_gt() { [ "${1:-0}" -gt "${2:-0}" ] 2> /dev/null; }

known_untracked() {
    case "$1" in "Claude outputs/"* | plan-p0-p11-review.patch | .claude/settings.local.json) return 0 ;; esac
    return 1
}
# excluded_path <path>: a path that is never committed or published (AGENTS
# rule 9, EXC-30, guard 4.6).
excluded_path() {
    local b="${1##*/}"
    case "$1" in "Claude outputs/"* | plan-p0-p11-review.patch | .claude/settings.local.json) return 0 ;; esac
    case "$b" in
        .env.example) return 1 ;;
        .env | .env.* | *.pem | *.key | *.p12 | *.pfx | id_rsa* | auth.json | *.dump | *.sqlite | *.backup) return 0 ;;
    esac
    return 1
}

# msg_problem <file> <1 when the first paragraph is a subject>: prints WORD,
# TRAILER or KEY_VALUE for the first rule the text breaks, or nothing.
msg_problem() {
    local f="$1" subj="$2" rest last
    if grep -qiE "$FORBIDDEN_RE" "$f" || grep -qiwE "$FORBIDDEN_WORDS_RE" "$f"; then echo WORD; return 0; fi
    if [ -n "$(git interpret-trailers --parse < "$f")" ]; then echo TRAILER; return 0; fi
    if [ "$subj" = 1 ]; then
        rest="$(sed '1,/^[[:space:]]*$/d' "$f")"
    else
        rest="$(cat "$f")"
    fi
    last="$(printf '%s\n' "$rest" | { grep -v '^[[:space:]]*$' || true; } | tail -n 1)"
    if printf '%s\n' "$last" | grep -qE '^[[:space:]]*[A-Za-z0-9][A-Za-z0-9_-]*:[[:space:]]+[^[:space:]]'; then echo KEY_VALUE; fi
    return 0
}

# ---------------------------------------------------------------- state (CE-20)
st_get() { [ -f "$STATE_FILE" ] && git config -f "$STATE_FILE" --get "$1" 2> /dev/null || true; }
st_all() { [ -f "$STATE_FILE" ] && git config -f "$STATE_FILE" --get-all "$1" 2> /dev/null || true; }
st_set() { mkdir -p "$STATE_DIR" && git config -f "$STATE_FILE" "$1" "$2"; }
st_add() { mkdir -p "$STATE_DIR" && git config -f "$STATE_FILE" --add "$1" "$2"; }
st_unset() { if [ -f "$STATE_FILE" ]; then git config -f "$STATE_FILE" --unset-all "$1" 2> /dev/null || true; fi; }
st_drop_section() { if [ -f "$STATE_FILE" ]; then git config -f "$STATE_FILE" --remove-section "$1" 2> /dev/null || true; fi; }
# Free text is stored as base64, so that no value can be read as an option.
st_set_text() { st_set "$1" "b64:$(b64e "$2")"; }
st_get_text() { local v; v="$(st_get "$1")"; case "$v" in b64:*) b64d "${v#b64:}" ;; *) printf '%s' "$v" ;; esac; }
log_event() { mkdir -p "$STATE_DIR" && printf '%s %s\n' "$(stamp)" "$*" >> "$LOG_FILE"; }
state_hash() { if [ -f "$STATE_FILE" ]; then sha256_of < "$STATE_FILE"; else echo none; fi; }
index_hash() { git ls-files -s | sha256_of; }
# snapshot_tree: the tree of the working tree (snapshot.sh, review-pack.sh),
# built in a temporary index that starts as a copy of the real one, so that
# file modes are kept as Git keeps them; the real index is only read.
snapshot_tree() {
    local idx="$STATE_DIR/snapshot-index.$$" f tree real
    mkdir -p "$STATE_DIR"
    rm -f -- "$idx"
    real="$(git rev-parse --git-path index)"
    if [ -f "$real" ]; then cp -- "$real" "$idx"; fi
    {
        git ls-files -z --cached
        git ls-files -z --others --exclude-standard
    } | while IFS= read -r -d '' f; do
        known_untracked "$f" && continue
        printf '%s\0' "$f"
    done | LC_ALL=C sort -z -u | GIT_INDEX_FILE="$idx" git update-index --add --remove -z --stdin
    tree="$(GIT_INDEX_FILE="$idx" git write-tree)"
    rm -f -- "$idx"
    printf '%s\n' "$tree"
}

# ---------------------------------------------------------------- processes (CE-22, CE-30)
# proc_list: every process as "pid|parent pid|image name|creation time".
proc_list() {
    if selftest_mode && [ -e "$(fx proc/fail)" ]; then return 1; fi
    if selftest_mode && [ -e "$(fx proc/list)" ]; then cat "$(fx proc/list)"; return 0; fi
    local out d s pid comm rest
    if is_windows; then
        out="$(MSYS_NO_PATHCONV=1 powershell.exe -NoProfile -NonInteractive -Command 'Get-CimInstance Win32_Process | ForEach-Object { $c = "-"; if ($_.CreationDate) { $c = $_.CreationDate.ToUniversalTime().ToString("o") }; "{0}|{1}|{2}|{3}" -f $_.ProcessId, $_.ParentProcessId, $_.Name, $c }' < /dev/null | tr -d '\r')" || return 1
        [ -n "$out" ] || return 1
        printf '%s\n' "$out"
    else
        for d in /proc/[0-9]*; do
            s="$(cat "$d/stat" 2> /dev/null)" || continue
            pid="${d#/proc/}"
            comm="${s#*(}"
            comm="${comm%)*}"
            rest="${s##*) }"
            # shellcheck disable=SC2086
            set -- $rest
            [ "$1" = Z ] && continue
            printf '%s|%s|%s|%s\n' "$pid" "$2" "$comm" "${20}"
        done
    fi
}
# boot_epoch: the computer's last start, in epoch seconds.
boot_epoch() {
    if selftest_mode && [ -e "$(fx proc/boot_fail)" ]; then return 1; fi
    if selftest_mode && [ -e "$(fx proc/boot)" ]; then cat "$(fx proc/boot)"; return 0; fi
    local b
    if is_windows; then
        b="$(MSYS_NO_PATHCONV=1 powershell.exe -NoProfile -NonInteractive -Command '[DateTimeOffset]::new((Get-CimInstance Win32_OperatingSystem).LastBootUpTime).ToUnixTimeSeconds()' < /dev/null | tr -d '\r')" || return 1
    else
        b="$(awk '$1 == "btime" { print $2 }' /proc/stat)" || return 1
    fi
    is_int "$b" || return 1
    printf '%s\n' "$b"
}
# self_pid: the script's own process (its Windows PID on Windows).
self_pid() { if is_windows; then cat "/proc/$$/winpid"; else printf '%s\n' "$$"; fi; }
# ancestry <pid>: the process and its ancestors as "pid|creation time|name",
# up to, not including, the first process of SYSTEM_NAMES (PID 1 on Linux) or a
# missing parent. On Windows <pid> is a process of the bash runtime: its chain
# is followed through the runtime's own /proc, which an exec does not break,
# then through the Windows parents. A parent created after its child is a
# reused PID and ends the walk.
ancestry() {
    local start="$1" list pre="" p w pp linux=1
    list="$(proc_list)" || return 1
    if is_windows; then
        linux=0
        p="$start"
        while [ -r "/proc/$p/winpid" ]; do
            w="$(cat "/proc/$p/winpid")"
            pre="$pre $w"
            pp="$(cat "/proc/$p/ppid" 2> /dev/null || echo 1)"
            if ! is_int "$pp" || [ "$pp" -le 1 ]; then break; fi
            p="$pp"
        done
    else
        pre="$start"
    fi
    printf '%s\n' "$list" | awk -F'|' -v pre="$pre" -v sys="$SYSTEM_NAMES" -v linux="$linux" '
        function newer(a, b) { if (a == "-" || b == "-" || a == "" || b == "") return 0; return linux ? (a + 0 > b + 0) : (a > b) }
        function stop(x) { return (N[x] in S) || (linux && x == 1) }
        BEGIN { n = split(sys, s, " "); for (i = 1; i <= n; i++) S[s[i]] = 1 }
        { P[$1] = $2; N[$1] = $3; C[$1] = $4 }
        END {
            m = split(pre, q, " "); last = ""
            for (i = 1; i <= m; i++) {
                x = q[i]
                if (!(x in N)) continue
                if (stop(x)) exit
                print x "|" C[x] "|" N[x]; last = x
            }
            if (last == "") exit
            cur = last
            for (d = 0; d < 64; d++) {
                par = P[cur]
                if (!(par in N) || par == cur || newer(C[par], C[cur]) || stop(par)) exit
                print par "|" C[par] "|" N[par]; cur = par
            }
        }'
}
# holder_record: the processes that host the session: the ancestors of the
# script that are not shells. Empty when nothing could be recorded.
holder_record() {
    local a
    a="$(ancestry "$$")" || return 0
    printf '%s\n' "$a" | awk -F'|' -v sh="$SHELL_NAMES" 'BEGIN { n = split(sh, s, " "); for (i = 1; i <= n; i++) S[s[i]] = 1 } NF >= 3 && !($3 in S)'
}
# holder_state <lock since> <lock boot> [holder file]: INACTIVE BOOT,
# ACTIVE <pid>, INACTIVE PROCESSES_GONE or UNKNOWN <reason>.
holder_state() {
    local since="${1:-}" lboot="${2:-}" hfile="${3:-$LOCK_DIR/holder}" b list pid ctime name
    b="$(boot_epoch)" && is_int "$b" || { echo "UNKNOWN BOOT_UNREADABLE"; return 0; }
    if is_int "$since" && [ "$b" -gt "$since" ]; then echo "INACTIVE BOOT"; return 0; fi
    if is_int "$lboot" && [ "$b" -gt $((lboot + 120)) ]; then echo "INACTIVE BOOT"; return 0; fi
    [ -s "$hfile" ] || { echo "UNKNOWN NO_RECORD"; return 0; }
    list="$(proc_list)" && [ -n "$list" ] || { echo "UNKNOWN PROC_UNREADABLE"; return 0; }
    while IFS='|' read -r pid ctime name || [ -n "${pid:-}" ]; do
        [ -n "$pid" ] || continue
        if printf '%s\n' "$list" | awk -F'|' -v p="$pid" -v c="$ctime" '$1 == p && $4 == c { f = 1 } END { exit !f }'; then
            echo "ACTIVE $pid"
            return 0
        fi
    done < "$hfile"
    echo "INACTIVE PROCESSES_GONE"
}
# invocation_id: the Bash call that runs the script: the outermost shell found
# by walking up from $PPID while the process is a shell, as "pid|creation time";
# the script itself when its parent is not a shell.
invocation_id() {
    local start="$$" a out
    if [ "${PPID:-1}" -gt 1 ] && { [ -r "/proc/$PPID/winpid" ] || { ! is_windows && [ -r "/proc/$PPID/stat" ]; }; }; then start="$PPID"; fi
    a="$(ancestry "$start")" || return 1
    out="$(printf '%s\n' "$a" | awk -F'|' -v sh="$SHELL_NAMES" 'BEGIN { n = split(sh, s, " "); for (i = 1; i <= n; i++) S[s[i]] = 1 } { if ($3 in S) o = $1 "|" $2; else exit } END { print o }')"
    if [ -z "$out" ] || [ "$start" = "$$" ]; then out="$(ancestry "$$" | sed -n '1p' | cut -d'|' -f1,2)"; fi
    [ -n "$out" ] || return 1
    printf '%s\n' "$out"
}

# ---------------------------------------------------------------- the lock (CE-22)
lock_exists() { [ -d "$LOCK_DIR" ]; }
lock_read() { cat "$LOCK_DIR/$1" 2> /dev/null || true; }
new_token() { od -An -N16 -tx1 /dev/urandom | tr -d ' \n'; }
lock_fill() {
    local t
    t="$(now)"
    printf '%s\n' "$t" > "$LOCK_DIR/since"
    printf '%s\n' "$t" > "$LOCK_DIR/heartbeat"
    boot_epoch > "$LOCK_DIR/boot" 2> /dev/null || : > "$LOCK_DIR/boot"
    holder_record > "$LOCK_DIR/holder" 2> /dev/null || : > "$LOCK_DIR/holder"
}
# lock_take: takes a free lock and prints its token; fails when a lock exists.
lock_take() {
    local tok
    mkdir -p "$STATE_DIR"
    mkdir "$LOCK_DIR" 2> /dev/null || return 1
    tok="$(new_token)"
    printf '%s\n' "$tok" > "$LOCK_DIR/token"
    lock_fill
    log_event "LOCK_TAKEN ${tok:0:8} holder=[$(tr '\n' ';' < "$LOCK_DIR/holder")]"
    printf '%s\n' "$tok"
}
# lock_takeover <evidence>: replaces a lock whose holder was shown inactive.
lock_takeover() {
    local stale="$STATE_DIR/lock.stale.$$" old
    mv "$LOCK_DIR" "$stale" 2> /dev/null || return 1
    old="$(cat "$stale/token" 2> /dev/null || true)"
    log_event "TAKEOVER $1 old=${old:0:8} since=$(cat "$stale/since" 2> /dev/null || true) holder=[$(tr '\n' ';' < "$stale/holder" 2> /dev/null || true)]"
    rm -rf -- "$stale"
    lock_take
}
lock_check() { lock_exists && [ -n "${1:-}" ] && [ "$(lock_read token)" = "$1" ]; }
lock_heartbeat() { now > "$LOCK_DIR/heartbeat"; }
lock_release() { rm -rf -- "$LOCK_DIR"; }

# ---------------------------------------------------------------- GitHub (fixture-replaceable)
gh_call() {
    local name="$1"
    shift
    if selftest_mode; then
        [ -e "$(fx gh/FAIL)" ] && return 1
        [ -e "$(fx "gh/$name.fail")" ] && return 1
        printf '%s\n' "$*" >> "$(fx gh/calls.log)"
        fixture_out "gh/$name"
        return 0
    fi
    [ "${EXECUTOR_SELFTEST:-}" = 1 ] && return 1
    timeout 60 "$@"
}
gh_pr() { gh_call "pr_$1" gh api "repos/$REPO/pulls/$1" --jq '.state, .merged, (.merge_commit_sha // ""), .head.sha, .head.ref, .base.ref, .user.login, (.head.repo.full_name // ""), .html_url'; }
gh_open_prs() { gh_call open_prs gh api --paginate "repos/$REPO/pulls?state=open&base=$BASE&per_page=100" --jq '.[] | "\(.number)|\(.head.ref)|\(.head.sha)|\(.user.login)|\(.head.repo.full_name // "")|\(.html_url)"'; }
gh_issue_comments() { gh_call "issue_comments_$1" gh api --paginate "repos/$REPO/issues/$1/comments?per_page=100" --jq '.[] | "\(.id)|\(.user.login)|\(.body // "" | @base64)"'; }
gh_review_comments() { gh_call "review_comments_$1" gh api --paginate "repos/$REPO/pulls/$1/comments?per_page=100" --jq '.[] | "\(.id)|\(.user.login)|\(.body // "" | @base64)"'; }
gh_reviews() { gh_call "reviews_$1" gh api --paginate "repos/$REPO/pulls/$1/reviews?per_page=100" --jq '.[] | "\(.id)|\(.user.login)|\(.body // "" | @base64)"'; }
gh_check_runs() { gh_call "check_runs_$1" gh api --paginate "repos/$REPO/commits/$1/check-runs?per_page=100" --jq '.check_runs[] | "\(.id)|\(.name)|\(.status)|\(.conclusion // "")|\(.html_url)"'; }
# gh_ci_runs <sha> <push|pull_request>: the runs of .github/workflows/ci.yml.
gh_ci_runs() { gh_call "ci_runs_$2_$1" gh api "repos/$REPO/actions/workflows/ci.yml/runs?event=$2&head_sha=$1&per_page=100" --jq '.workflow_runs[] | "\(.id)|\(.status)|\(.conclusion // "")|\(.html_url)"'; }
gh_pr_create() { gh_call pr_create gh pr create --repo "$REPO" --base "$BASE" --head "$1" --title "$2" --body-file "$3"; }
gh_pr_edit_body() { gh_call pr_edit gh pr edit "$1" --repo "$REPO" --body-file "$2"; }
gh_run_rerun() { gh_call "rerun_$1" gh run rerun "$1" --failed --repo "$REPO"; }
gh_get() { gh_call get gh api --method GET "$@"; }
# max_id <lines "id|...">: the highest identifier, or 0.
max_id() { printf '%s\n' "$1" | awk -F'|' 'function gt(a, b) { return length(a) > length(b) || (length(a) == length(b) && a > b) } BEGIN { m = "0" } $1 ~ /^[0-9]+$/ && gt($1, m) { m = $1 } END { print m }'; }
# adopt_state <pr> <branch> <head>: AWAIT_MERGE for that pull request, with
# the highest existing comment identifiers (state.sh adopt, tick.sh step 4).
adopt_state() {
    local pr="$1" br="$2" head="$3" ic rc rv kind
    ic="$(gh_issue_comments "$pr")" || return 1
    rc="$(gh_review_comments "$pr")" || return 1
    rv="$(gh_reviews "$pr")" || return 1
    case "$br" in ops/*) kind=ops ;; *) kind=task ;; esac
    st_set loop.phase AWAIT_MERGE
    st_set pr.number "$pr"
    st_set pr.head "$head"
    st_set task.branch "$br"
    st_set task.kind "$kind"
    if [ "$kind" = task ]; then
        st_set task.id "$(task_of_branch "$br")"
        fill_task_from_plan "$(task_of_branch "$br")" "$br"
    fi
    st_set pr.lastIssueCommentId "$(max_id "$ic")"
    st_set pr.lastReviewCommentId "$(max_id "$rc")"
    st_set pr.lastReviewId "$(max_id "$rv")"
    st_unset loop.stopReason
    st_unset loop.stopDetail
    st_unset loop.prevPhase
}
# fill_task_from_plan <TASK-NNN> <branch>: the Task's row and agents from
# TASK_PLAN and the effort table at origin/develop, for an adopted or
# reconstructed Task pull request.
fill_task_from_plan() {
    local t row short title class er eff exe rev ver
    t="$(mktemp -d)"
    if git show "origin/$BASE:docs/11-tasks/TASK_PLAN.md" > "$t/plan" 2> /dev/null && git show "origin/$BASE:docs/11-tasks/EXECUTION_CONTEXT.md" > "$t/ctx" 2> /dev/null; then
        row="$(plan_rows "$t/plan" | awk -F'|' -v x="$1" '$1 == x')"
        if [ -n "$row" ]; then
            IFS='|' read -r _ short title class _ _ _ <<< "$row"
            er="$(effort_row "$t/ctx" "$1" "$class")"
            IFS='|' read -r eff exe rev ver <<< "$er"
            st_set task.title "$title"
            st_set task.class "$class"
            st_set task.contract "docs/11-tasks/tasks/$1-$short.md"
            st_set task.base "$(git merge-base "origin/$BASE" "refs/heads/$2" 2> /dev/null || echo unknown)"
            if [ -n "$exe" ]; then
                st_set task.effort "$eff"
                st_set task.effortSource "the table of EXECUTION_CONTEXT section 19"
                st_set task.agent "$exe"
                st_set task.reviewAgent "$rev"
                st_set task.verifyAgent "$ver"
            fi
        fi
    fi
    rm -rf -- "$t"
    return 0
}
# resume_state [note]: restores the phase before the stop (state.sh resume,
# a /resume comment); the Owner's resume also clears the count of resumes.
resume_state() {
    local prev
    prev="$(st_get loop.prevPhase)"
    st_set loop.phase "${prev:-SELECT}"
    if [ -n "${1:-}" ]; then st_set_text owner.note "$1"; fi
    st_unset loop.stopReason
    st_unset loop.stopDetail
    st_unset loop.prevPhase
    if [ -n "$(st_get task.resumes)" ]; then st_set task.resumes 0; fi
}

# ---------------------------------------------------------------- parsers (CE-10, CE-11)
# plan_rows <TASK_PLAN file>: the rows of section 4 as
# "id|short name|title|review class|depends on|gates|status".
plan_rows() {
    awk '
        /^## 4\./ { s = 1; next }
        /^## / { if (s) exit }
        s && /^\| \[TASK-[0-9]+\]\(tasks\/TASK-[0-9]+-[^)]*\.md\)/ {
            n = split($0, c, "|")
            for (i = 1; i <= n; i++) gsub(/^[ \t]+|[ \t]+$/, "", c[i])
            id = c[2]; sub(/^\[/, "", id); sub(/\].*$/, "", id)
            sh = c[2]; sub(/^[^(]*\(tasks\/TASK-[0-9]+-/, "", sh); sub(/\.md\).*$/, "", sh)
            st = c[10]; gsub(/`/, "", st)
            print id "|" sh "|" c[3] "|" c[7] "|" c[8] "|" c[9] "|" st
        }' "$1"
}
# gate_state <TASK_PLAN file> <gate>: the "State today" of the gate in section 3.
gate_state() {
    awk -v g="$2" '
        /^## 3\./ { s = 1; next }
        /^## / { if (s) exit }
        s && /^\|/ {
            n = split($0, c, "|")
            for (i = 1; i <= n; i++) gsub(/^[ \t]+|[ \t]+$/, "", c[i])
            if (c[2] == g) { print c[n - 1]; exit }
        }' "$1"
}
gate_satisfied() {
    [ "$2" = EXECUTOR ] && return 0
    case "$(gate_state "$1" "$2")" in APPROVED* | SATISFIED*) return 0 ;; esac
    return 1
}
# start_gates <gates cell>: the gates that must hold for a start, one a line.
start_gates() {
    local g="$1"
    g="${g%%before \`DONE\`:*}"
    printf '%s\n' "$g" | tr ',;' '\n\n' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' | { grep -v -e '^$' -e '^—$' || true; }
}
# row_problems <TASK_PLAN file> <row>: the unmet dependencies and gates.
row_problems() {
    local plan="$1" row="$2" deps gates d g st problems=""
    IFS='|' read -r _ _ _ _ deps gates _ <<< "$row"
    for d in $(printf '%s' "$deps" | tr ',' ' '); do
        case "$d" in
            TASK-*)
                st="$(plan_rows "$plan" | awk -F'|' -v t="$d" '$1 == t { print $7 }')"
                [ "$st" = DONE ] || problems="$problems dependency $d is ${st:-missing};"
                ;;
        esac
    done
    while IFS= read -r g; do
        [ -n "$g" ] || continue
        gate_satisfied "$plan" "$g" || problems="$problems gate $g not satisfied;"
    done < <(start_gates "$gates")
    printf '%s' "$problems"
}
# select_task <TASK_PLAN file>: "OK <row>", "BAD <id> <problems>" for a READY
# row that fails its checks, or "NONE <the first blocked Tasks>".
select_task() {
    local plan="$1" rows r id st p sel="" list="" n=0
    rows="$(plan_rows "$plan" | sort -t'|' -k1,1)"
    if [ -z "$rows" ]; then echo "BAD - no row in TASK_PLAN section 4"; return 0; fi
    while IFS= read -r r; do
        IFS='|' read -r id _ _ _ _ _ st <<< "$r"
        [ "$st" = READY ] || continue
        p="$(row_problems "$plan" "$r")"
        if [ -n "$p" ]; then echo "BAD $id$p"; return 0; fi
        [ -n "$sel" ] || sel="$r"
    done <<< "$rows"
    if [ -n "$sel" ]; then echo "OK $sel"; return 0; fi
    while IFS= read -r r; do
        IFS='|' read -r id _ _ _ _ _ st <<< "$r"
        case "$st" in DONE | SUPERSEDED) continue ;; esac
        p="$(row_problems "$plan" "$r")"
        list="$list $id ($st:${p:- none})"
        n=$((n + 1))
        [ "$n" -ge 3 ] && break
    done <<< "$rows"
    echo "NONE$list"
}
# effort_row <EXECUTION_CONTEXT file> <TASK-NNN> <review class>:
# "effort|executor|review agent|verification agent" from section 19's table.
effort_row() {
    awk -v t="$2" -v cl="$3" '
        /^## 19\./ { s = 1; next }
        /^## / { if (s) exit }
        s && /^\| (Max|XHigh|High|Medium) \|/ {
            n = split($0, c, "|")
            if (n != 7) next
            for (i = 1; i <= n; i++) { gsub(/^[ \t]+|[ \t]+$/, "", c[i]); gsub(/`/, "", c[i]) }
            R[c[2]] = tolower(c[2]) "|" c[4] "|" c[5] "|" c[6]; T[c[2]] = c[3]
        }
        END {
            re = "(^|[^0-9A-Za-z-])" t "([^0-9]|$)"
            if (("Max" in T) && T["Max"] ~ re) { print R["Max"]; exit }
            if (("Medium" in T) && T["Medium"] ~ re) { print R["Medium"]; exit }
            if (cl == "A") { if ("XHigh" in R) print R["XHigh"]; exit }
            if ("High" in R) print R["High"]
        }' "$1"
}
# agent_effort <agent> [fallback]: the effort its definition names.
agent_effort() {
    local f=".claude/agents/$1.md" e=""
    [ -f "$f" ] && e="$(awk '/^---/ { n++; next } n == 1 && /^effort:/ { print $2; exit }' "$f")"
    printf '%s\n' "${e:-${2:-${1##*-}}}"
}
