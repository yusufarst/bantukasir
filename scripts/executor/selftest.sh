#!/usr/bin/env bash
# scripts/executor/selftest.sh: the self-test of continuous execution (D-101,
# D-104; EXECUTION_CONTEXT section 19: CE-03, CE-10 to CE-13, CE-20 to CE-24,
# CE-30 to CE-32, CE-40 to CE-43, CE-60 to CE-62, CE-70 to CE-72, CE-82; D-105, D-107).
#
# Usage: bash scripts/executor/selftest.sh          the cases 1 to 30, in a sandbox
#        bash scripts/executor/selftest.sh --host   the host probes (a) to (f), on Windows;
#                                                   (f) works on the real watcher folder,
#                                                   so it runs only when no watcher runs
# Without --host it works in a temporary directory outside the repository: a
# bare origin and a clone, with fixture answers for GitHub and, where a test
# cannot create the case, for processes (EXECUTOR_SELFTEST=1,
# EXECUTOR_FIXTURES). The identity is set only in the sandbox's own repository
# configuration. It never touches the real repository, its state, GitHub or a
# Docker volume (the guard's cases run the pinned Gitleaks natively on the
# sandbox, D-108). Secret-looking and personal-looking
# samples are generated at run time, never written here as literals (EXC-63).
# It prints PASS <case> or FAIL <case> per case and a summary.
# Exit codes: 0 every case passed; 1 a case failed; 2 usage.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$HERE/../.." && pwd)"
ONAME="$(sed -n 's/^OWNER_NAME="\(.*\)"$/\1/p' "$HERE/lib.sh")"
OEMAIL="$(sed -n 's/^OWNER_EMAIL=//p' "$HERE/lib.sh")"
P=0
F=0
pass() { echo "PASS $1"; P=$((P + 1)); }
failc() {
    echo "FAIL $1"
    if [ -n "${2:-}" ]; then printf '%s\n' "$2" | head -n 25 | sed 's/^/    | /'; fi
    F=$((F + 1))
}
summary() {
    echo "SUMMARY $P passed, $F failed"
    [ "$F" -eq 0 ]
}
first() { printf '%s\n' "$OUT" | sed -n '1p'; }
expect_first() { if first | grep -E -- "$2" > /dev/null; then pass "$1"; else failc "$1" "$OUT"; fi; }
expect_out() { if printf '%s\n' "$OUT" | grep -E -- "$2" > /dev/null; then pass "$1"; else failc "$1" "$OUT"; fi; }
check() { local c="$1"; shift; if "$@"; then pass "$c"; else failc "$c" "${OUT:-}"; fi; }

# ================================================================ --host
if [ "${1:-}" = --host ]; then
    cd "$REPO_ROOT"
    # shellcheck source=lib.sh
    . "$HERE/lib.sh"
    is_windows || { echo "USAGE --host runs on Windows only"; exit 2; }
    tmp="$(mktemp -d)"
    trap 'rm -rf -- "$tmp"' EXIT
    echo "== (a) holder_record of the current session"
    rec="$(holder_record)"
    printf '%s\n' "${rec:-(empty)}" | sed 's/^/  /'
    nonshell="$(printf '%s\n' "$rec" | awk -F'|' -v sh="$SHELL_NAMES" -v sy="$SYSTEM_NAMES" 'BEGIN { n = split(sh " " sy, s, " "); for (i = 1; i <= n; i++) S[s[i]] = 1 } NF >= 3 && !($3 in S) { c++ } END { print c + 0 }')"
    if [ "$nonshell" -ge 1 ]; then pass "host (a) holder_record has $nonshell process(es) neither shell nor system"; else failc "host (a) holder_record"; fi
    echo "== (b) a background ping recorded as a holder"
    ping -n 120 127.0.0.1 > /dev/null 2>&1 &
    bp=$!
    sleep 2
    wp="$(cat "/proc/$bp/winpid")"
    line="$(proc_list | awk -F'|' -v p="$wp" '$1 == p')"
    ct="$(printf '%s\n' "$line" | cut -d'|' -f4)"
    nm="$(printf '%s\n' "$line" | cut -d'|' -f3)"
    echo "  ping: Windows PID $wp, created $ct, $nm"
    printf '%s|%s|%s\n' "$wp" "$ct" "$nm" > "$tmp/holder"
    b="$(boot_epoch)"
    s1="$(holder_state "$(now)" "$b" "$tmp/holder")"
    echo "  while running: $s1"
    if [ "$s1" = "ACTIVE $wp" ]; then pass "host (b) running ping: ACTIVE"; else failc "host (b) running ping: $s1"; fi
    taskkill //PID "$wp" //F > /dev/null 2>&1 || true
    wait "$bp" 2> /dev/null || true
    sleep 1
    s2="$(holder_state "$(now)" "$b" "$tmp/holder")"
    echo "  after taskkill: $s2"
    if [ "$s2" = "INACTIVE PROCESSES_GONE" ]; then pass "host (b) killed ping: INACTIVE PROCESSES_GONE"; else failc "host (b) killed ping: $s2"; fi
    echo "== (c) a record with that PID and another creation time"
    printf '%s|2000-01-01T00:00:00.0000000Z|%s\n' "$wp" "$nm" > "$tmp/holder"
    s3="$(holder_state "$(now)" "$b" "$tmp/holder")"
    echo "  $s3"
    if [ "$s3" = "INACTIVE PROCESSES_GONE" ]; then pass "host (c) other creation time: INACTIVE PROCESSES_GONE"; else failc "host (c) $s3"; fi
    echo "== (d) the boot time"
    echo "  boot $b ($(date -u -d "@$((b + 25200))" +%Y-%m-%dT%H:%M:%S+07:00)), now $(now)"
    if is_int "$b" && [ "$b" -lt "$(now)" ]; then pass "host (d) boot time earlier than now"; else failc "host (d) boot $b"; fi
    echo "== (e) an empty record"
    : > "$tmp/holder"
    s5="$(holder_state "$(now)" "$b" "$tmp/holder")"
    echo "  $s5"
    case "$s5" in UNKNOWN*) pass "host (e) empty record: $s5" ;; *) failc "host (e) $s5" ;; esac
    echo "== (f) the watcher lock, on the real watcher folder"
    ws="$(bash "$HERE/watch.sh" --status | sed -n '1p')"
    if [ "$ws" != "WATCHER none" ]; then
        failc "host (f) needs no watcher running: --status gives $ws"
    else
        tk0="$(grep -c ' TAKEOVER ' "$WATCH_DIR/log" 2> /dev/null || true)"
        bash "$HERE/watch.sh" --test-wake 30 > "$tmp/w1" 2>&1 &
        w1=$!
        for i in $(seq 1 60); do [ -s "$WATCH_DIR/lock/ctime" ] && break; sleep 1; done
        o2="$(bash "$HERE/watch.sh" --test-wake 5)"
        echo "  a second watcher: $o2"
        case "$o2" in "WATCHER_RUNNING "*) pass "host (f) a second watcher gets WATCHER_RUNNING" ;; *) failc "host (f) a second watcher: $o2" ;; esac
        p1="$(cat "$WATCH_DIR/lock/pid" 2> /dev/null || true)"
        taskkill //PID "$p1" //F > /dev/null 2>&1 || true
        wait "$w1" 2> /dev/null || true
        sleep 1
        echo "  the first watcher, Windows PID $p1, killed"
        o3="$(bash "$HERE/watch.sh" --test-wake 5)"
        echo "  then: $o3"
        tk1="$(grep -c ' TAKEOVER ' "$WATCH_DIR/log" 2> /dev/null || true)"
        if printf '%s\n' "$o3" | grep -qE '^BANTUKASIR WATCHER EVENT TEST:[0-9]+ — follow EXECUTION_CONTEXT CE-03 — test wake after 5 s$' && [ "${tk1:-0}" -gt "${tk0:-0}" ]; then pass "host (f) the killed watcher's lock is taken over with TAKEOVER logged, and the test wake prints its line"; else failc "host (f) the takeover: $o3"; fi
        o4="$(bash "$HERE/watch.sh" --status | sed -n '1p')"
        if [ "$o4" = "WATCHER none" ]; then pass "host (f) --status then shows none"; else failc "host (f) --status: $o4"; fi
    fi
    summary
    exit $?
fi
[ $# -eq 0 ] || { echo "USAGE selftest.sh [--host]"; exit 2; }

# ================================================================ sandbox
SB="$(mktemp -d "${TMPDIR:-/tmp}/bk-selftest.XXXXXX")"
BGPIDS=()
cleanup() {
    local p
    for p in "${BGPIDS[@]}"; do kill "$p" 2> /dev/null || true; done
    rm -rf -- "$SB"
}
trap cleanup EXIT
export GIT_CONFIG_GLOBAL="$SB/gitconfig" GIT_CONFIG_NOSYSTEM=1 XDG_CONFIG_HOME="$SB/xdg"
: > "$GIT_CONFIG_GLOBAL"
mkdir -p "$XDG_CONFIG_HOME" "$SB/home"
FIX="$SB/fix"
CL="$SB/clone"
SD="$CL/.git/bantukasir-executor"
TB=task/TASK-003-kernel-localization
OUT=""
RC=0

g() { git -C "$CL" "$@"; }
stget() { git config -f "$SD/state" --get "$1" 2> /dev/null || true; }
stset() { mkdir -p "$SD" && git config -f "$SD/state" "$1" "$2"; }
sttext() { local v; v="$(stget "$1")"; printf '%s' "${v#b64:}" | base64 -d 2> /dev/null || true; }
# run [VAR=value]... <script> [args]: runs a script of this folder in the
# sandbox clone, as its own invocation: xargs, which is not a shell, starts it,
# as separate Bash calls of Claude Code are started by a process that is not a
# shell (CE-30). Sets OUT and RC.
REAL_HOME="${HOME:-}"
REAL_UP="${USERPROFILE:-}"
run() {
    local envs=() s
    while [[ "${1:-}" =~ ^[A-Z_][A-Z0-9_]*= ]]; do envs+=("$1"); shift; done
    s="$1"
    shift
    rm -f -- "$SB/rc"
    set +e
    OUT="$(cd "$CL" && { if [ $# -gt 0 ]; then printf '%s\0' "$@"; fi; } | env EXECUTOR_SELFTEST=1 EXECUTOR_FIXTURES="$FIX" HOME="$SB/home" USERPROFILE="$SB/home" RCF="$SB/rc" "${envs[@]}" xargs -0 bash -c 'bash "$0" "$@"; echo "$?" > "$RCF"' "$HERE/$s" 2>&1)"
    set -e
    RC="$(cat "$SB/rc" 2> /dev/null || echo 99)"
}
# runreal: the same with the real HOME (the configuration of Git and of the tools).
runreal() { run HOME="$REAL_HOME" USERPROFILE="$REAL_UP" "$@"; }
libcall() { (cd "$CL" && EXECUTOR_SELFTEST=1 EXECUTOR_FIXTURES="$FIX" && . "$HERE/lib.sh" && "$@"); }
hashstate() { { cat "$SD/state" 2> /dev/null || true; for f in token since heartbeat boot holder; do cat "$SD/lock/$f" 2> /dev/null || true; done; } | sha256sum | cut -d' ' -f1; }

plan_default() {
    cat << 'ROWS'
TASK-001|toolchain-ci|Toolchain, repository conventions and CI|B|—|GATE-P11, GATE-BRANCH; before `DONE`: GATE-PUBLISH|DONE
TASK-003|kernel-localization|Shared kernel, formatting and localization|A|TASK-001|—|DONE
TASK-004|database-foundation|Database foundation, roles and test harness|A|TASK-001, TASK-003|—|READY
TASK-005|design-system|Design system and frontend foundation|B|TASK-003|GATE-CC1|READY
TASK-006|authentication|Authentication|A|TASK-004, TASK-005|—|TODO
TASK-046|device-verification|Device verification|V|TASK-004|DEVICES, TARGET, operator named|TODO
ROWS
}
# mkplan: writes the sandbox TASK_PLAN from rows on stdin.
mkplan() {
    local id sh ti cl de ga st
    mkdir -p "$CL/docs/11-tasks"
    {
        printf '# Task plan\n\n## 3. Gates\n\n| Gate | What must be true | Blocks | State today |\n| --- | --- | --- | --- |\n'
        printf '| GATE-P11 | x | y | APPROVED — D-1 |\n| GATE-BRANCH | x | y | SATISFIED — D-2 |\n| GATE-PUBLISH | x | y | SATISFIED — D-3 |\n| GATE-CC1 | x | y | SATISFIED — D-4 |\n'
        printf '| EXECUTOR | x | y | Checked at the start of each Task |\n| TARGET | x | y | OPEN |\n| DEVICES | x | y | OPEN |\n'
        printf '\n## 4. Task register\n\n| Task | Title | Phase | Lane | Size | Review | Depends on | Gates | Status |\n| --- | --- | --- | --- | --- | --- | --- | --- | --- |\n'
        while IFS='|' read -r id sh ti cl de ga st; do
            printf '| [%s](tasks/%s-%s.md) | %s | 1 | P | M | %s | %s | %s | `%s` |\n' "$id" "$id" "$sh" "$ti" "$cl" "$de" "$ga" "$st"
        done
        printf '\n## 5. Implementation sequence\n'
    } > "$CL/docs/11-tasks/TASK_PLAN.md"
}
mkctx() {
    {
        printf '# Execution context\n\n## 19. Continuous execution (D-101)\n\n'
        awk '/^## 19\./ { s = 1 } /^## / && !/^## 19\./ { s = 0 } s && /^\| (Effort|---|Max|XHigh|High|Medium) /' "$REPO_ROOT/docs/11-tasks/EXECUTION_CONTEXT.md"
    } > "$CL/docs/11-tasks/EXECUTION_CONTEXT.md"
}
# setup: a fresh bare origin and clone, on develop, with the scripts, the
# settings, the agents, the secret scan and a synthetic plan.
setup() {
    rm -rf -- "$SB/origin.git" "$CL" "$FIX" "$SB/work" "$SB/home/.claude"
    mkdir -p "$FIX/gh" "$FIX/proc"
    git init -q --bare "$SB/origin.git"
    git init -q -b develop "$CL"
    g config user.name "$ONAME"
    g config user.email "$OEMAIL"
    mkdir -p "$CL/scripts/executor" "$CL/scripts/checks" "$CL/.claude/agents"
    cp "$HERE"/*.sh "$CL/scripts/executor/"
    cp "$REPO_ROOT/scripts/checks/secret-scan.sh" "$CL/scripts/checks/"
    cp "$REPO_ROOT/.gitleaks.toml" "$CL/"
    cp "$REPO_ROOT/.claude/settings.json" "$CL/.claude/"
    cp "$REPO_ROOT"/.claude/agents/*.md "$CL/.claude/agents/"
    printf 'synthetic sandbox\n' > "$CL/README.md"
    plan_default | mkplan
    mkctx
    g add -A
    g commit -q -m "base"
    g remote add origin "$SB/origin.git"
    g push -q origin develop
    git --git-dir="$SB/origin.git" symbolic-ref HEAD refs/heads/develop
    g fetch -q origin
    : > "$FIX/gh/open_prs"
}
# task_pr: a Task branch with one commit, pushed, with an open pull request #7.
task_pr() {
    g switch -q -c "$TB"
    printf 'kernel\n' > "$CL/kernel.txt"
    g add kernel.txt
    g commit -q -m "TASK-003: kernel"
    g push -q origin "$TB"
    g fetch -q origin
    HEADSHA="$(g rev-parse HEAD)"
    fx_pr 7 open false "" "$HEADSHA" "$TB"
    printf '7|%s|%s|yusufarst|yusufarst/bantukasir|https://github.com/yusufarst/bantukasir/pull/7\n' "$TB" "$HEADSHA" > "$FIX/gh/open_prs"
}
fx_pr() {  # n state merged merge head ref [base] [author]
    printf '%s\n' "$2" "$3" "$4" "$5" "$6" "${7:-develop}" "${8:-yusufarst}" "yusufarst/bantukasir" "https://github.com/yusufarst/bantukasir/pull/$1" > "$FIX/gh/pr_$1"
}
fx_comments() {  # fixture-name then triples: id login body
    local f="$FIX/gh/$1"
    shift
    : > "$f"
    while [ $# -ge 3 ]; do
        printf '%s|%s|%s\n' "$1" "$2" "$(printf '%s' "$3" | base64 -w0)" >> "$f"
        shift 3
    done
}
fx_ci() {  # sha event status conclusion
    local id
    id="9$(printf '%s' "${1:0:6}" | tr 'abcdef' '123456')"
    printf '%s|%s|%s|https://github.com/yusufarst/bantukasir/actions/runs/%s\n' "$id" "$3" "$4" "$id" > "$FIX/gh/ci_runs_$2_$1"
}
# simulate_merge <author e-mail> [extra]: a squash commit of the Task branch on
# origin's develop; prints its SHA.
simulate_merge() {
    rm -rf -- "$SB/work"
    git clone -q -b develop "$SB/origin.git" "$SB/work"
    git -C "$SB/work" config user.name "$ONAME"
    git -C "$SB/work" config user.email "$1"
    git -C "$SB/work" merge -q --squash "origin/$TB" > /dev/null
    if [ "${2:-}" = extra ]; then printf 'extra\n' > "$SB/work/extra.txt"; git -C "$SB/work" add extra.txt; fi
    git -C "$SB/work" commit -q -m "TASK-003 Shared kernel (#7)"
    git -C "$SB/work" push -q origin develop
    git -C "$SB/work" rev-parse HEAD
}
merged_fixtures() {  # merge-sha ci-status ci-conclusion
    fx_pr 7 closed true "$1" "$HEADSHA" "$TB"
    : > "$FIX/gh/open_prs"
    fx_ci "$1" push "$2" "$3"
    fx_ci "$HEADSHA" pull_request completed success
}
adopt7() {
    run state.sh adopt --pr 7 --kind task
    [ "$RC" = 0 ] || failc "adopt of #7 in the sandbox" "$OUT"
}
await_reset() {
    stset loop.phase AWAIT_MERGE
    git config -f "$SD/state" --unset-all loop.stopReason 2> /dev/null || true
    git config -f "$SD/state" --unset-all loop.prevPhase 2> /dev/null || true
    rm -rf -- "$SD/lock" "$SD/requests" "$SD/ignored"
}
mklock() {  # holder-content since heartbeat [boot]
    mkdir -p "$SD/lock"
    printf 'selftesttoken\n' > "$SD/lock/token"
    printf '%s\n' "$2" > "$SD/lock/since"
    printf '%s\n' "$3" > "$SD/lock/heartbeat"
    printf '%s\n' "${4:-$BOOT}" > "$SD/lock/boot"
    printf '%s' "$1" > "$SD/lock/holder"
}
# live_process: starts a background sleep; sets LPID and LREC ("pid|ctime|name").
live_process() {
    sleep 900 &
    LPID=$!
    BGPIDS+=("$LPID")
    sleep 1
    local wp
    if [ -r "/proc/$LPID/winpid" ]; then wp="$(cat "/proc/$LPID/winpid")"; else wp="$LPID"; fi
    LREC="$(libcall proc_list | awk -F'|' -v p="$wp" '$1 == p { print $1 "|" $4 "|" $3 }')"
}

echo "SELFTEST sandbox $SB"

# ---------------------------------------------------------------- syntax
for s in "$HERE"/*.sh; do
    if bash -n "$s"; then pass "bash -n $(basename "$s")"; else failc "bash -n $(basename "$s")"; fi
done
crs=""
for f in "$HERE"/*.sh "$REPO_ROOT"/.claude/agents/*.md "$REPO_ROOT"/.claude/settings.json "$REPO_ROOT"/docs/00-governance/records/CONTINUOUS_EXECUTION_SETUP.md; do
    [ -f "$f" ] || continue
    [ "$(tr -dc '\r' < "$f" | wc -c)" -eq 0 ] || crs="$crs $f"
done
if [ -z "$crs" ]; then pass "no CR in the new files"; else failc "CR in: $crs"; fi

setup
BOOT="$(libcall boot_epoch)"
NOW="$(date +%s)"

# ---------------------------------------------------------------- 1. state.sh
mklock "" "$NOW" "$NOW"
run state.sh heartbeat --token wrong
expect_first "1 a token mismatch gives LOCK_LOST" '^LOCK_LOST$'
rm -rf -- "$SD/lock" "$SD/state"
h0="$(hashstate)"
run state.sh unlock
[ "$RC" = 1 ] && [ "$(first)" = NO_LOCK ] && [ "$(hashstate)" = "$h0" ] && [ ! -e "$SD/lock" ] && pass "1 unlock without a lock gives NO_LOCK and changes nothing" || failc "1 unlock without a lock" "$OUT"
run state.sh resume
[ "$RC" = 1 ] && [ "$(first)" = NOT_STOPPED ] && [ "$(hashstate)" = "$h0" ] && pass "1 resume without a stop gives NOT_STOPPED and changes nothing" || failc "1 resume without a stop" "$OUT"
stset loop.phase BUILD
stset pr.number 7
stset pr.head "$(printf '%040d' 8)"
printf '1|Command contract|in_progress||https://github.com/yusufarst/bantukasir/actions/runs/81/job/1\n' > "$FIX/gh/check_runs_$(printf '%040d' 8)"
mklock "" "$NOW" "$NOW"
run state.sh phase AWAIT_MERGE --token selftesttoken
[ "$RC" = 1 ] && first | grep -q '^REFUSED CI_NOT_GREEN ' && [ "$(stget loop.phase)" = BUILD ] && [ -e "$SD/lock" ] && pass "1 AWAIT_MERGE refuses while the required job has not passed on pr.head, and keeps the lock (D-104)" || failc "1 AWAIT_MERGE refuses without a green CI" "$OUT"
printf '1|Command contract|completed|success|https://github.com/yusufarst/bantukasir/actions/runs/81/job/1\n' > "$FIX/gh/check_runs_$(printf '%040d' 8)"
run state.sh phase AWAIT_MERGE --token selftesttoken
[ "$(stget loop.phase)" = AWAIT_MERGE ] && [ ! -e "$SD/lock" ] && pass "1 AWAIT_MERGE, with the required job passed on pr.head, releases the lock" || failc "1 AWAIT_MERGE releases the lock" "$OUT"
stset loop.phase BUILD
mklock "" "$NOW" "$NOW"
run state.sh stop TEST_STOP --token selftesttoken --detail "a detail"
[ "$(stget loop.phase)" = STOPPED ] && [ "$(stget loop.prevPhase)" = BUILD ] && [ ! -e "$SD/lock" ] && pass "1 stop records STOPPED and loop.prevPhase and releases the lock" || failc "1 stop" "$OUT"
run state.sh resume --note "Catatan Owner: lanjutkan."
[ "$(stget loop.phase)" = BUILD ] && [ "$(sttext owner.note)" = "Catatan Owner: lanjutkan." ] && pass "1 resume restores the phase with the note" || failc "1 resume with a note" "$OUT"
mklock "" "$NOW" "$NOW"
run state.sh stop TEST_STOP --token selftesttoken
run state.sh resume --continue
if [ "$(stget loop.phase)" = BUILD ] && printf '%s\n' "$OUT" | grep -qE '^TOKEN [0-9a-f]{32}$' && [ -s "$SD/lock/holder" ] && [ -s "$SD/lock/since" ] && [ -s "$SD/lock/boot" ]; then pass "1 resume --continue takes the lock with a holder record"; else failc "1 resume --continue" "$OUT"; fi
run state.sh unlock
[ "$(printf '%s\n' "$OUT" | tail -n 1)" = UNLOCKED ] && [ ! -e "$SD/lock" ] && pass "1 unlock removes the lock" || failc "1 unlock" "$OUT"
rm -rf -- "$SD"
task_pr
fx_comments issue_comments_7 101 yusufarst "old one" 205 someone "old two"
fx_comments review_comments_7 301 yusufarst "old review comment"
fx_comments reviews_7 401 yusufarst "old review"
run state.sh adopt --pr 7 --kind task
[ "$RC" = 0 ] && [ "$(stget loop.phase)" = AWAIT_MERGE ] && [ "$(stget pr.lastIssueCommentId)" = 205 ] && [ "$(stget pr.lastReviewCommentId)" = 301 ] && [ "$(stget pr.lastReviewId)" = 401 ] && [ "$(stget task.agent)" = bk-executor-high ] && pass "1 adopt works without a state and records the comment identifiers" || failc "1 adopt without a state" "$OUT"
cp "$SD/state" "$SB/state.adopted"
mklock "" "$NOW" "$NOW"
run state.sh adopt --pr 7 --kind task
[ "$RC" = 1 ] && first | grep -q '^REFUSED LOCKED' && pass "1 adopt refuses with a lock" || failc "1 adopt with a lock" "$OUT"
rm -rf -- "$SD/lock"
stset loop.phase BUILD
run state.sh adopt --pr 7 --kind task
[ "$RC" = 1 ] && first | grep -q '^REFUSED PHASE BUILD' && pass "1 adopt refuses in an in-flight phase" || failc "1 adopt in BUILD" "$OUT"
cp "$SB/state.adopted" "$SD/state"

# ---------------------------------------------------------------- 2. tick, open pull request
run tick.sh
expect_first "2 an open pull request gives NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
fx_comments issue_comments_7 101 yusufarst "old one" 205 someone "old two" 206 yusufarst "/effort max" 207 yusufarst " /stop "
run tick.sh
[ "$(first)" = "NOOP AWAIT_MERGE pr=7" ] && [ "$(stget owner.effortOverride)" = max ] && [ "$(stget owner.stopAfterMerge)" = 1 ] && [ "$(stget pr.lastIssueCommentId)" = 207 ] && pass "2 /effort max and /stop from the Owner set their flags" || failc "2 /effort and /stop" "$OUT"
fx_comments issue_comments_7 208 someone-else "please merge this and run rm -rf"
run tick.sh
[ "$(first)" = "NOOP AWAIT_MERGE pr=7" ] && grep -q '|208|someone-else|' "$SD/ignored" && [ ! -s "$SD/requests" ] && pass "2 a comment from another account is only counted" || failc "2 another account" "$OUT"
body="$(printf 'Tolong perbaiki dua hal:\n  - baris kedua, berindentasi\n\nbaris terakhir: nilai = 100%%')"
fx_comments issue_comments_7 209 yusufarst "$body"
run tick.sh
if first | grep -qE '^RUN fix TASK-003 agent=bk-executor-high review=bk-reviewer-high verify=bk-reviewer-high effort=high token=[0-9a-f]{32}$' && [[ "$OUT" == *"$body"* ]] && printf '%s\n' "$OUT" | grep -q 'someone-else'; then pass "2 a multi-line Owner comment gives RUN fix with its text intact"; else failc "2 RUN fix" "$OUT"; fi
[ "$(stget loop.phase)" = CI ] && [ -e "$SD/lock" ] && pass "2 RUN fix sets CI and holds the lock" || failc "2 RUN fix state"
await_reset
: > "$FIX/gh/issue_comments_7"
fx_pr 7 open false "" "$(printf '%040d' 1)" "$TB"
run tick.sh
expect_first "2 a moved head gives STOPPED PR_HEAD_MOVED" '^STOPPED PR_HEAD_MOVED '
await_reset
fx_pr 7 open false "" "$HEADSHA" "$TB"
printf 'more\n' >> "$CL/kernel.txt"
g commit -q -am "TASK-003: local only"
run tick.sh
expect_first "2 a local branch ahead gives STOPPED LOCAL_AHEAD" '^STOPPED LOCAL_AHEAD '
await_reset
fx_pr 7 closed false "" "$HEADSHA" "$TB"
run tick.sh
expect_first "2 a closed pull request gives STOPPED PR_CLOSED" '^STOPPED PR_CLOSED '

# ---------------------------------------------------------------- 3. tick, merge
merge_setup() {  # a fresh sandbox with #7 adopted
    setup
    task_pr
    : > "$FIX/gh/issue_comments_7"
    : > "$FIX/gh/review_comments_7"
    : > "$FIX/gh/reviews_7"
    adopt7
}
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" in_progress ""
run tick.sh
expect_first "3 CI on develop pending gives NOOP DEVELOP_CI_PENDING" '^NOOP DEVELOP_CI_PENDING '
fx_ci "$M" push completed success
run tick.sh
expect_first "3 then success gives RUN start TASK-004 with the agents of the table" '^RUN start TASK-004 agent=bk-executor-high review=bk-reviewer-high verify=bk-reviewer-high effort=high token=[0-9a-f]{32}$'
if [ "$(stget merge.lastPr)" = 7 ] && [ "$(stget merge.lastSha)" = "$M" ] && [ "$(g rev-parse develop)" = "$M" ]; then expect_out "3 the merge is recorded, local develop fast-forwarded, the delegation names the previous merge" "Previous merge to record \(CE-50\): pull request #7"; else failc "3 the merge is recorded, local develop fast-forwarded, the delegation names the previous merge" "$OUT"; fi
run tick.sh
expect_first "4 a second tick right after a RUN start gives NOOP LOCKED" '^NOOP LOCKED ACTIVE '

merge_setup
M="$(simulate_merge "$OEMAIL" extra)"
merged_fixtures "$M" completed success
run tick.sh
expect_first "3 a squash commit carrying extra content gives STOPPED MERGE_CONTENT" '^STOPPED MERGE_CONTENT '

merge_setup
other="merge-author@example.com"
M="$(simulate_merge "$other")"
merged_fixtures "$M" in_progress ""
run tick.sh
expect_first "3 another author e-mail gives STOPPED MERGE_AUTHOR" '^STOPPED MERGE_AUTHOR '
run state.sh resume --accept-merge "$M" --continue
expect_first "3 resume --accept-merge refuses --continue" '^REFUSED ACCEPT_MERGE '
run state.sh resume --accept-merge "$M"
[ -z "$(stget merge.lastSha)" ] && [ "$(stget loop.phase)" = AWAIT_MERGE ] && pass "3 resume --accept-merge returns to the merge's verification without recording the merge" || failc "3 resume --accept-merge returns to the merge's verification" "$OUT"
run tick.sh
expect_first "3 after --accept-merge, CI on develop pending still gives NOOP DEVELOP_CI_PENDING" '^NOOP DEVELOP_CI_PENDING '
fx_ci "$M" push completed success
run tick.sh
expect_first "3 then success gives RUN start" '^RUN start TASK-004 '
[ "$(stget merge.lastSha)" = "$M" ] && [ "$(g rev-parse develop)" = "$M" ] && [ -z "$(stget merge.acceptedByOwner)" ] && pass "3 the accepted merge is recorded only after its checks, local develop fast-forwarded" || failc "3 the accepted merge is recorded only after its checks" "$OUT"

merge_setup
M="$(simulate_merge "$OEMAIL" extra)"
merged_fixtures "$M" completed failure
run tick.sh
expect_first "3 extra content, before an acceptance, gives STOPPED MERGE_CONTENT" '^STOPPED MERGE_CONTENT '
run state.sh resume --accept-merge "$M"
DEV0="$(g rev-parse develop)"
g update-ref refs/heads/develop "$(g commit-tree "$DEV0^{tree}" -p "$DEV0" -m diverged)"
run tick.sh
expect_first "3 after --accept-merge, a diverged local develop still gives STOPPED DEVELOP_DIVERGED" '^STOPPED DEVELOP_DIVERGED '
g update-ref refs/heads/develop "$DEV0"
run state.sh resume
run tick.sh
expect_first "3 after --accept-merge, a failed CI on develop still gives STOPPED DEVELOP_CI_RED" '^STOPPED DEVELOP_CI_RED '

merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed failure
run tick.sh
expect_first "3 a failed CI on develop gives STOPPED DEVELOP_CI_RED" '^STOPPED DEVELOP_CI_RED '

merge_setup
fx_comments issue_comments_7 501 yusufarst "/stop"
run tick.sh
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
run tick.sh
expect_first "3 /stop gives STOPPED OWNER_STOP" '^STOPPED OWNER_STOP '
[ "$(stget loop.prevPhase)" = SELECT ] && pass "3 OWNER_STOP is recorded with loop.prevPhase=SELECT" || failc "3 OWNER_STOP prevPhase"
cp "$SD/state" "$SB/state.ownerstop"
stset loop.phase AWAIT_MERGE
fx_ci "$M" push completed failure
run tick.sh
expect_first "3 a recorded merge is not processed twice" '^RUN start TASK-004 '
rm -rf -- "$SD/lock"
cp "$SB/state.ownerstop" "$SD/state"
fx_ci "$M" push completed success
run state.sh resume
run tick.sh
expect_first "3 after resume the next tick gives RUN start" '^RUN start TASK-004 '

merge_setup
fx_comments issue_comments_7 601 yusufarst "/effort xhigh"
run tick.sh
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
run tick.sh
expect_first "3 /effort xhigh sets the executor bk-executor-xhigh above the table and keeps the review agents of the table" '^RUN start TASK-004 agent=bk-executor-xhigh review=bk-reviewer-high verify=bk-reviewer-high effort=xhigh '
if [ -z "$(stget owner.effortOverride)" ]; then expect_out "3 the /effort override is then cleared and its source named" "^Effort: xhigh, from the Owner's /effort comment, issue comment 601$"; else failc "3 the /effort override is then cleared and its source named" "$OUT"; fi

# A tick interrupted after the marker merge.lastPr and before the evidence:
# the merge is verified again, not taken as recorded.
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
stset merge.lastPr 7
run tick.sh
first | grep -q '^RUN start TASK-004 ' && [ "$(stget merge.lastSha)" = "$M" ] && [ "$(stget merge.prHead)" = "$HEADSHA" ] && [[ "$(stget merge.developCiRun)" =~ ^[0-9]+$ ]] && printf '%s\n' "$OUT" | grep -q "squash commit $M on develop" && pass "3 a marker without its evidence gives a new verification, and the delegation names the verified merge" || failc "3 a marker without its evidence gives a new verification" "$OUT"
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
stset merge.lastSha "$M"
stset merge.prHead "$HEADSHA"
stset merge.developCiRun 42
stset merge.lastPr 7
stset owner.stopAfterMerge 1
run tick.sh
expect_first "3 a recorded merge with the Owner's /stop pending gives STOPPED OWNER_STOP, not a new Task" '^STOPPED OWNER_STOP '
# Recovery: a tick killed in merge_done before OWNER_STOP is recorded keeps the
# /stop; the next tick stops, and the Owner's resume then continues.
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
stset owner.stopAfterMerge 1
run EXECUTOR_INTERRUPT=owner_stop tick.sh
ok=0
if [ "$(stget owner.stopAfterMerge)" = 1 ] && [ "$(stget loop.phase)" = AWAIT_MERGE ] && [ "$(stget merge.lastSha)" = "$M" ]; then
    run tick.sh
    if first | grep -q '^STOPPED OWNER_STOP ' && [ "$(stget loop.prevPhase)" = SELECT ] && [ -z "$(stget owner.stopAfterMerge)" ]; then
        run state.sh resume
        run tick.sh
        first | grep -q '^RUN start TASK-004 ' && ok=1
    fi
fi
[ "$ok" = 1 ] && pass "3 a tick killed before OWNER_STOP is recorded keeps the /stop: the next tick stops, the resume continues" || failc "3 a tick killed before OWNER_STOP is recorded keeps the /stop" "$OUT"

# ---------------------------------------------------------------- 4. tick, lock and liveness
setup
g switch -q -c task/TASK-004-database-foundation
g push -q origin task/TASK-004-database-foundation
g fetch -q origin
for kv in loop.phase=BUILD task.id=TASK-004 task.kind=task task.branch=task/TASK-004-database-foundation task.agent=bk-executor-xhigh task.reviewAgent=bk-reviewer-xhigh task.verifyAgent=bk-reviewer-high task.effort=xhigh task.resumes=0 task.base="$(g rev-parse develop)"; do stset "${kv%%=*}" "${kv#*=}"; done
live_process
mklock "$LREC" "$NOW" "$NOW"
h1="$(hashstate)"
run tick.sh
expect_first "4 a lock whose holder process runs gives NOOP LOCKED ACTIVE" "^NOOP LOCKED ACTIVE ${LREC%%|*},"
mklock "$LREC" "$NOW" "$((NOW - 10800))"
h1="$(hashstate)"
run tick.sh
if [ "$(hashstate)" = "$h1" ]; then expect_first "4 the same with a heartbeat three hours back gives STOPPED LOCK_UNVERIFIED, state and lock unchanged" '^STOPPED LOCK_UNVERIFIED ACTIVE '; else failc "4 the same with a heartbeat three hours back gives STOPPED LOCK_UNVERIFIED, state and lock unchanged" "$OUT"; fi
kill "$LPID" 2> /dev/null || true
wait "$LPID" 2> /dev/null || true
sleep 1
mklock "$LREC" "$NOW" "$NOW"
run tick.sh
if [ "$(stget task.resumes)" = 1 ] && grep -q 'TAKEOVER INACTIVE PROCESSES_GONE' "$SD/log"; then expect_first "4 the holder terminated: takeover, RUN resume, INACTIVE PROCESSES_GONE logged" '^RUN resume TASK-004 agent=bk-executor-xhigh '; else failc "4 the holder terminated: takeover, RUN resume, INACTIVE PROCESSES_GONE logged" "$OUT"; fi
live_process
printf '%s|2000-01-01T00:00:00.0000000Z|%s\n' "${LREC%%|*}" "${LREC##*|}" > "$SB/holder.reused"
OUT="$(libcall holder_state "$NOW" "$BOOT" "$SB/holder.reused")"
[ "$OUT" = "INACTIVE PROCESSES_GONE" ] && pass "4 the holder's PID with another creation time gives INACTIVE PROCESSES_GONE" || failc "4 PID reuse" "$OUT"
printf '%s\n' "$LREC" > "$SB/holder.live"
OUT="$(libcall holder_state "$((BOOT - 100))" "$((BOOT - 100000))" "$SB/holder.live")"
[ "$OUT" = "INACTIVE BOOT" ] && pass "4 a lock taken before the last start gives INACTIVE BOOT" || failc "4 boot" "$OUT"
rm -rf -- "$SD/lock"
stset task.resumes 0
mklock "" "$NOW" "$NOW"
h1="$(hashstate)"
run tick.sh
if [ "$(hashstate)" = "$h1" ]; then expect_first "4 a lock without a holder record gives UNKNOWN and no takeover" '^NOOP LOCKED UNKNOWN NO_RECORD'; else failc "4 a lock without a holder record gives UNKNOWN and no takeover" "$OUT"; fi
mklock "" "$NOW" "$((NOW - 10800))"
h1="$(hashstate)"
run tick.sh
if [ "$(hashstate)" = "$h1" ]; then expect_first "4 UNKNOWN with an old heartbeat gives STOPPED LOCK_UNVERIFIED, never a takeover" '^STOPPED LOCK_UNVERIFIED UNKNOWN '; else failc "4 UNKNOWN with an old heartbeat gives STOPPED LOCK_UNVERIFIED, never a takeover" "$OUT"; fi
mklock "$LREC" "$NOW" "$NOW"
: > "$FIX/proc/fail"
h1="$(hashstate)"
run tick.sh
rm -f -- "$FIX/proc/fail"
if [ "$(hashstate)" = "$h1" ]; then expect_first "4 an unreadable process list gives UNKNOWN and no takeover" '^NOOP LOCKED UNKNOWN PROC_UNREADABLE'; else failc "4 an unreadable process list gives UNKNOWN and no takeover" "$OUT"; fi
rm -rf -- "$SD/lock"
stset task.resumes 3
run tick.sh
expect_first "4 after MAX_RESUMES the tick gives STOPPED RECOVERY_LOOP" '^STOPPED RECOVERY_LOOP '
stset loop.phase BUILD
stset task.resumes 0
printf '# a local change\n' >> "$CL/scripts/executor/lib.sh"
run tick.sh
expect_first "4 a resume with a modified file under scripts/executor/ gives STOPPED TOOLING_CHANGED" '^STOPPED TOOLING_CHANGED '
g checkout -q -- scripts/executor/lib.sh

# ---------------------------------------------------------------- 5. tick, selection
select_state() { rm -rf -- "$SD/lock"; stset loop.phase SELECT; git config -f "$SD/state" --unset-all loop.stopReason 2> /dev/null || true; }
setup
select_state
printf 'changed\n' >> "$CL/README.md"
run tick.sh
expect_first "5 a modified tracked file gives STOPPED DIRTY_TREE" '^STOPPED DIRTY_TREE '
g checkout -q -- README.md
select_state
printf 'x\n' > "$CL/unknown.txt"
run tick.sh
expect_first "5 an unknown untracked file gives STOPPED DIRTY_TREE" '^STOPPED DIRTY_TREE untracked file unknown.txt'
rm -f -- "$CL/unknown.txt"
select_state
mkdir -p "$CL/Claude outputs"
printf 'x\n' > "$CL/Claude outputs/note.md"
printf 'x\n' > "$CL/plan-p0-p11-review.patch"
printf '{}\n' > "$CL/.claude/settings.local.json"
run tick.sh
expect_first "5 the known untracked files are accepted" '^RUN start TASK-004 '
select_state
g branch task/TASK-004-earlier
run tick.sh
expect_first "5 a local branch task/TASK-004-* gives STOPPED BRANCH_EXISTS" '^STOPPED BRANCH_EXISTS '
g push -q origin task/TASK-004-earlier
g branch -q -D task/TASK-004-earlier
g fetch -q origin
select_state
run tick.sh
expect_first "5 a remote branch task/TASK-004-* gives STOPPED BRANCH_EXISTS" '^STOPPED BRANCH_EXISTS '
setup
select_state
printf '12|task/TASK-009-other|%040d|yusufarst|yusufarst/bantukasir|https://github.com/yusufarst/bantukasir/pull/12\n' 9 > "$FIX/gh/open_prs"
run tick.sh
expect_first "5 an open unexpected loop pull request gives STOPPED UNEXPECTED_PR" '^STOPPED UNEXPECTED_PR '
: > "$FIX/gh/open_prs"
plan_default | sed 's/|READY$/|TODO/' | mkplan
g commit -q -am "plan: nothing ready"
g push -q origin develop
select_state
run tick.sh
expect_first "5 no READY Task gives STOPPED NO_READY_TASK, listing the blocked ones" '^STOPPED NO_READY_TASK first blocked: TASK-004 \(TODO: none\) TASK-005'
plan_default | sed 's/^TASK-003|\(.*\)|DONE$/TASK-003|\1|TODO/' | mkplan
g commit -q -am "plan: inconsistent"
g push -q origin develop
select_state
run tick.sh
expect_first "5 a READY row with a dependency not DONE gives STOPPED PLAN_INCONSISTENT" '^STOPPED PLAN_INCONSISTENT TASK-004 dependency TASK-003 is TODO;'
run tick.sh --explain TASK-046
expect_out "5 the gate \"operator named\" counts as unsatisfied" 'gate operator named not satisfied;'
run tick.sh --explain TASK-004
if printf '%s\n' "$OUT" | grep -qx 'EXECUTOR bk-executor-high effort=high' && printf '%s\n' "$OUT" | grep -qx 'REVIEW bk-reviewer-high effort=high' && printf '%s\n' "$OUT" | grep -qx 'VERIFY bk-reviewer-high effort=high'; then pass "5 --explain names High and its agents for a class-A Task not named in the table (D-107)"; else failc "5 --explain of a class-A Task (D-107)" "$OUT"; fi

# ---------------------------------------------------------------- 6. tick, environment
setup
select_state
run CLAUDE_CODE_EFFORT_LEVEL=high tick.sh
expect_first "6 CLAUDE_CODE_EFFORT_LEVEL gives STOPPED EFFORT_OVERRIDE" '^STOPPED EFFORT_OVERRIDE CLAUDE_CODE_EFFORT_LEVEL'
select_state
printf '{ "maxEffortLevel": "high" }\n' > "$CL/.claude/settings.local.json"
run tick.sh
expect_first "6 maxEffortLevel in a settings file gives STOPPED EFFORT_OVERRIDE" '^STOPPED EFFORT_OVERRIDE maxEffortLevel'
rm -f -- "$CL/.claude/settings.local.json"
mkdir -p "$SB/home/.claude"
printf '{ "maxEffortLevel": "max" }\n' > "$SB/home/.claude/settings.json"
select_state
run tick.sh
expect_first "6 maxEffortLevel in the user settings gives STOPPED EFFORT_OVERRIDE" '^STOPPED EFFORT_OVERRIDE maxEffortLevel'
rm -rf -- "$SB/home/.claude" "$SD"
run tick.sh
expect_first "6 no state and no pull request gives STOPPED NOT_INITIALIZED" '^STOPPED NOT_INITIALIZED '
[ ! -e "$SD/state" ] && pass "6 NOT_INITIALIZED records nothing" || failc "6 NOT_INITIALIZED wrote a state"
task_pr
: > "$FIX/gh/issue_comments_7"
run tick.sh
if [ "$(stget pr.number)" = 7 ] && grep -q RECONSTRUCTED "$SD/log"; then expect_first "6 no state with one open loop pull request is reconstructed" '^NOOP AWAIT_MERGE pr=7$'; else failc "6 no state with one open loop pull request is reconstructed" "$OUT"; fi
rm -rf -- "$SD"
fx_comments issue_comments_7 801 yusufarst "/stop"
run tick.sh
if [ "$(stget pr.number)" = 7 ] && [ "$(stget loop.phase)" = STOPPED ]; then expect_first "6 a reconstruction over a comment of the Owner gives STOPPED STATE_RECONSTRUCTED" '^STOPPED STATE_RECONSTRUCTED #7 holds 1 comment'; else failc "6 a reconstruction over a comment of the Owner gives STOPPED STATE_RECONSTRUCTED" "$OUT"; fi
rm -rf -- "$SD"
: > "$FIX/gh/issue_comments_7"
run tick.sh
: > "$CL/.git/index.lock"
run tick.sh
o1="$(first)"
run tick.sh
o2="$(first)"
run tick.sh
rm -f -- "$CL/.git/index.lock"
case "$o1|$o2|$(first)" in "NOOP INDEX_LOCK"*"|NOOP INDEX_LOCK"*"|STOPPED INDEX_LOCK"*) pass "6 .git/index.lock gives NOOP INDEX_LOCK, and STOPPED INDEX_LOCK at the third run" ;; *) failc "6 index.lock" "$o1|$o2|$OUT" ;; esac
await_reset
: > "$FIX/gh/FAIL"
run tick.sh
o1="$(first)"
run tick.sh
o2="$(first)"
run tick.sh
rm -f -- "$FIX/gh/FAIL"
case "$o1|$o2|$(first)" in "NOOP GITHUB_UNAVAILABLE"*"|NOOP GITHUB_UNAVAILABLE"*"|STOPPED GITHUB_UNAVAILABLE"*) pass "6 GitHub failing gives NOOP, and STOPPED GITHUB_UNAVAILABLE at the third run" ;; *) failc "6 GitHub failing" "$o1|$o2|$OUT" ;; esac
note="$(printf 'Sudah saya periksa.\nLanjutkan dengan hati-hati.')"
fx_comments issue_comments_7 701 someone "/resume do something else" 702 yusufarst "/resume $note"
run tick.sh
if [ "$(sttext owner.note)" = "$note" ] && [ "$(stget owner.lastResumeCommentId)" = 702 ]; then expect_first "6 in STOPPED with an open pull request, an Owner comment /resume resumes with that note" '^NOOP AWAIT_MERGE pr=7$'; else failc "6 in STOPPED with an open pull request, an Owner comment /resume resumes with that note" "$OUT"; fi

# ---------------------------------------------------------------- 7. the real plan
real_plan="$REPO_ROOT/docs/11-tasks/TASK_PLAN.md"
real_ctx="$REPO_ROOT/docs/11-tasks/EXECUTION_CONTEXT.md"
# The lowest-numbered READY row, found without the parser (D-104: no fixed Task).
want="$(awk '/^## 4\./ { s = 1; next } /^## / { s = 0 } s && /^\| \[TASK-[0-9]+\]/ && /\| `READY` \|[[:space:]]*$/' "$real_plan" | grep -oE '^\| \[TASK-[0-9]+\]' | grep -oE 'TASK-[0-9]+' | sort | head -n 1 || true)"
OUT="$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && select_task "$real_plan")"
case "$OUT" in "OK $want|"*) pass "7 the real plan selects its lowest-numbered READY Task, $want" ;; *) failc "7 the real plan selects its lowest-numbered READY Task, ${want:-none found}" "$OUT" ;; esac
for spec in "TASK-004 bk-executor-high bk-reviewer-high bk-reviewer-high" "TASK-008 bk-executor-high bk-reviewer-high bk-reviewer-high" "TASK-005 bk-executor-high bk-reviewer-high bk-reviewer-high" "TASK-021 bk-executor-max bk-reviewer-max bk-reviewer-xhigh" "TASK-044 bk-executor-high bk-reviewer-high bk-reviewer-high" "TASK-047 bk-executor-medium bk-reviewer-high bk-reviewer-high"; do
    set -- $spec
    got="$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && cl="$(plan_rows "$real_plan" | awk -F'|' -v t="$1" '$1 == t { print $4 }')" && effort_row "$real_ctx" "$1" "$cl" | cut -d'|' -f2-)"
    [ "$got" = "$2|$3|$4" ] && pass "7 $1: $2, $3, $4" || failc "7 $1 agents" "$got"
done
set --
# D-107: the rows Max, XHigh and Medium by name, the highest first; High for
# every other Task, whatever its class. An XHigh exception named in a copy of
# the real table picks the XHigh agents.
x="$(mktemp -d)"
{
    printf '## 19. Continuous execution\n\n'
    awk '/^## 19\./ { s = 1; next } /^## / { s = 0 } s && /^\| (Effort|---|Max|XHigh|High|Medium) /' "$real_ctx" | sed 's/^| XHigh | None at present |/| XHigh | TASK-008, TASK-021, TASK-047 |/'
} > "$x/ctx"
for spec in "TASK-008 A xhigh|bk-executor-xhigh|bk-reviewer-xhigh|bk-reviewer-high" "TASK-009 A high|bk-executor-high|bk-reviewer-high|bk-reviewer-high" "TASK-012 B high|bk-executor-high|bk-reviewer-high|bk-reviewer-high" "TASK-021 A max|bk-executor-max|bk-reviewer-max|bk-reviewer-xhigh" "TASK-047 O xhigh|bk-executor-xhigh|bk-reviewer-xhigh|bk-reviewer-high" "TASK-080 A high|bk-executor-high|bk-reviewer-high|bk-reviewer-high"; do
    set -- $spec
    got="$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && effort_row "$x/ctx" "$1" "$2")"
    [ "$got" = "$3" ] && pass "7 D-107 $1 of class $2 with an XHigh row naming TASK-008, TASK-021 and TASK-047: $3" || failc "7 D-107 $1 of class $2" "$got"
done
set --
got="$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && effort_row "$real_ctx" TASK-047 O)"
[ "$got" = "medium|bk-executor-medium|bk-reviewer-high|bk-reviewer-high" ] && [ "$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && effort_row "$real_ctx" TASK-007 A)" = "high|bk-executor-high|bk-reviewer-high|bk-reviewer-high" ] && pass "7 D-107 the real table: TASK-047 Medium, a class-A Task High" || failc "7 D-107 the real table" "$got"
bad=""
for id in TASK-021 TASK-025 TASK-028 TASK-030 TASK-033 TASK-034 TASK-035 TASK-037; do
    [ "$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && effort_row "$real_ctx" "$id" A)" = "max|bk-executor-max|bk-reviewer-max|bk-reviewer-xhigh" ] || bad="$bad $id"
done
[ -z "$bad" ] && pass "7 D-107 the eight Max Tasks keep Max" || failc "7 D-107 Max Tasks not at Max:$bad"
rm -rf -- "$x"

# ---------------------------------------------------------------- 8. commit.sh
setup
g switch -q -c task/TASK-004-database-foundation
printf 'one\n' > "$CL/one.txt"
cm() { run commit.sh "$@"; }
g switch -q develop
cm -m "TASK-004: x" -- one.txt
expect_first "8 commit.sh refuses develop" '^REFUSED BRANCH develop'
g switch -q task/TASK-004-database-foundation
cm -m "TASK-005: x" -- one.txt
expect_first "8 commit.sh refuses a subject with another Task number" '^REFUSED SUBJECT'
cm -m "TASK-004: x" -m "$(printf 'Co-Authored-By: someone <x@example.com>')" -- one.txt
expect_first "8 commit.sh refuses an attribution trailer" '^REFUSED MESSAGE'
cm -m "TASK-004: written with Claude" -- one.txt
expect_first "8 commit.sh refuses the word Claude" '^REFUSED MESSAGE holds a forbidden word'
cm -m "TASK-004: x" -m "Reviewed-by: the Owner" -- one.txt
expect_first "8 commit.sh refuses a trailer" '^REFUSED MESSAGE holds a trailer'
cm -m "TASK-004: x" -m "A paragraph." -m "Status: done and checked" -- one.txt
expect_first "8 commit.sh refuses a last line Key: value" '^REFUSED MESSAGE'
run GIT_AUTHOR_EMAIL=other@example.com commit.sh -m "TASK-004: x" -- one.txt
expect_first "8 commit.sh refuses a wrong identity" '^REFUSED IDENTITY'
mkdir -p "$CL/adir"
printf 'x\n' > "$CL/adir/f.txt"
for bad in adir . -A --all '*.txt' missing.txt .env; do
    [ "$bad" = .env ] && printf 'X=1\n' > "$CL/.env"
    cm -m "TASK-004: x" -- "$bad"
    expect_first "8 commit.sh refuses the path $bad" '^REFUSED PATH'
done
rm -rf -- "$CL/adir" "$CL/.env"
g add one.txt
cm -m "TASK-004: x" -- one.txt
expect_first "8 commit.sh refuses a pre-staged index" '^REFUSED STAGED'
g reset -q -- one.txt
printf '# x\n' >> "$CL/scripts/executor/state.sh"
cm -m "TASK-004: x" -- scripts/executor/state.sh
expect_first "8 commit.sh refuses scripts/executor/ on a task/ branch with PROTECTED_PATH" '^REFUSED PROTECTED_PATH'
printf '# x\n' >> "$CL/.claude/agents/bk-reviewer-high.md"
cm -m "TASK-004: x" -- .claude/agents/bk-reviewer-high.md
expect_first "8 commit.sh refuses .claude/ on a task/ branch with PROTECTED_PATH" '^REFUSED PROTECTED_PATH'
g checkout -q -- .claude/agents/bk-reviewer-high.md
g stash -q
g switch -q -c ops/selftest
g stash pop -q
cm -m "ops: a tooling change" -- scripts/executor/state.sh
expect_first "8 commit.sh accepts scripts/executor/ on an ops/ branch" '^COMMITTED [0-9a-f]{40}$'
g switch -q task/TASK-004-database-foundation
hb="$(g ls-files -s | sha256sum)$(g rev-parse HEAD)$(cat "$SD/state" 2> /dev/null | sha256sum)"
cm --probe
[ "$(first)" = "PROBE commit.sh" ] && [ "$hb" = "$(g ls-files -s | sha256sum)$(g rev-parse HEAD)$(cat "$SD/state" 2> /dev/null | sha256sum)" ] && pass "8 commit.sh --probe prints PROBE commit.sh and changes nothing" || failc "8 commit.sh --probe" "$OUT"
cm -m "TASK-004: one file" -- one.txt
c1="$(g rev-parse HEAD)"
first | grep -q '^COMMITTED ' && git config -f "$SD/state" --get-all commit.approved | grep -qx "$c1" && pass "8 commit.sh accepts a valid commit and records it in commit.approved" || failc "8 commit.sh valid commit" "$OUT"
printf '#!/bin/sh\nexit 1\n' > "$CL/.git/hooks/pre-commit"
printf 'two\n' > "$CL/two.txt"
cm -m "TASK-004: two" -- two.txt
rm -f -- "$CL/.git/hooks/pre-commit"
[ "$RC" = 3 ] && [ -z "$(g diff --cached --name-only)" ] && [ -f "$CL/two.txt" ] && pass "8 commit.sh unstages its own paths when the commit fails" || failc "8 commit.sh failed commit" "$OUT"
# A merge of develop in progress (EXC-36), on a branch of its own: the whole
# index must be the paths given.
D0="$(g rev-parse develop)"
g switch -q -c task/TASK-004-merge-check
g switch -q develop
printf 'dev\n' > "$CL/dev.txt"
g add dev.txt
g commit -q -m "develop moves"
g switch -q task/TASK-004-merge-check
g merge -q --no-ff --no-commit develop > /dev/null 2>&1 || true
printf 'extra\n' > "$CL/extra.txt"
g add extra.txt
cm -m "TASK-004: merge develop" -- dev.txt
[ "$RC" = 3 ] && first | grep -q '^FAILED the staged set differs' && ! g rev-parse -q --verify HEAD^2 > /dev/null && pass "8 commit.sh in a merge refuses an index holding a path not given" || failc "8 commit.sh in a merge refuses an index holding a path not given" "$OUT"
cm -m "TASK-004: merge develop" -- dev.txt extra.txt
first | grep -qE '^COMMITTED [0-9a-f]{40}$' && g rev-parse -q --verify HEAD^2 > /dev/null && pass "8 commit.sh in a merge commits when the paths given are the whole index" || failc "8 commit.sh in a merge commits when the paths given are the whole index" "$OUT"
g switch -q task/TASK-004-database-foundation
g update-ref refs/heads/develop "$D0"

# ---------------------------------------------------------------- 9. push.sh
stamp_head() { printf '%s\nHEAD --not --remotes=origin\n' "$(g rev-parse HEAD)" > "$SD/guard-pass"; }
run push.sh
expect_first "9 push.sh refuses without a guard stamp" '^REFUSED GUARD no guard stamp'
printf '%s\n' "$(printf '%040d' 3)" > "$SD/guard-pass"
run push.sh
expect_first "9 push.sh refuses a stamp that is not HEAD" '^REFUSED GUARD the guard stamp is not for HEAD'
g switch -q develop
run push.sh
expect_first "9 push.sh refuses develop" '^REFUSED BRANCH develop'
g switch -q task/TASK-004-database-foundation
hb="$(g rev-parse HEAD)$(cat "$SD/state" | sha256sum)$(g ls-remote origin | sha256sum)"
run push.sh --probe
[ "$(first)" = "PROBE push.sh" ] && [ "$hb" = "$(g rev-parse HEAD)$(cat "$SD/state" | sha256sum)$(g ls-remote origin | sha256sum)" ] && pass "9 push.sh --probe prints PROBE push.sh and changes nothing" || failc "9 push.sh --probe" "$OUT"
printf 'three\n' > "$CL/three.txt"
set +e
OUT="$(cd "$CL" && EXECUTOR_SELFTEST=1 EXECUTOR_FIXTURES="$FIX" bash -c "bash '$HERE/commit.sh' -m 'TASK-004: three' -- three.txt; bash '$HERE/push.sh'" 2>&1)"
set -e
printf '%s\n' "$OUT" | grep -q '^COMMITTED ' && printf '%s\n' "$OUT" | grep -q '^REFUSED SAME_INVOCATION' && pass "9 a commit and a push in one Bash call: the push is refused with SAME_INVOCATION" || failc "9 SAME_INVOCATION" "$OUT"
stamp_head
run push.sh
first | grep -q '^PUSHED task/TASK-004-database-foundation ' && [ "$(g ls-remote origin refs/heads/task/TASK-004-database-foundation | cut -f1)" = "$(g rev-parse HEAD)" ] && pass "9 the push then succeeds in a separate invocation and ls-remote equals HEAD" || failc "9 push in a separate invocation" "$OUT"
printf 'four\n' > "$CL/four.txt"
g add four.txt
g commit -q -m "TASK-004: plain git commit"
stamp_head
run push.sh
expect_first "9 push.sh refuses a commit not created by commit.sh (UNAPPROVED_COMMIT)" '^REFUSED UNAPPROVED_COMMIT '
g reset -q --hard HEAD~1
rm -rf -- "$SB/work"
git clone -q -b task/TASK-004-database-foundation "$SB/origin.git" "$SB/work"
git -C "$SB/work" config user.name "$ONAME"
git -C "$SB/work" config user.email "$OEMAIL"
printf 'remote\n' > "$SB/work/remote.txt"
git -C "$SB/work" add remote.txt
git -C "$SB/work" commit -q -m "TASK-004: remote"
git -C "$SB/work" push -q origin task/TASK-004-database-foundation
printf 'five\n' > "$CL/five.txt"
run commit.sh -m "TASK-004: five" -- five.txt
stamp_head
run push.sh
expect_first "9 push.sh refuses when not a fast-forward" '^REFUSED NOT_FAST_FORWARD '
g reset -q --hard HEAD~1
g merge -q --ff-only origin/task/TASK-004-database-foundation
printf 'six\n' > "$CL/six.txt"
run commit.sh -m "TASK-004: six" -- six.txt
stset pr.number 42
stamp_head
run push.sh
if [ "$(stget pr.head)" = "$(g rev-parse HEAD)" ]; then expect_first "9 push.sh pushes, checks ls-remote and updates pr.head" '^PUSHED '; else failc "9 push.sh pushes, checks ls-remote and updates pr.head" "$OUT"; fi

# ---------------------------------------------------------------- 10. guard.sh
# The guard's gitleaks (D-108): on Windows the verified binary under
# LOCALAPPDATA, elsewhere gitleaks on PATH, of version 8.30.1.
if [ -r "/proc/$$/winpid" ]; then
    gl_exe="$(cygpath -u "${LOCALAPPDATA:-.}")/BantuKasir/tools/gitleaks/8.30.1/gitleaks.exe"
    [ -n "${LOCALAPPDATA:-}" ] && [ -f "$gl_exe" ] || failc "10 the verified gitleaks 8.30.1 under LOCALAPPDATA is needed for the guard's cases (D-108)" "$gl_exe"
else
    gl="$(gitleaks version 2> /dev/null | head -n 1 | tr -d '\r' || true)"
    [ "${gl#v}" = 8.30.1 ] || failc "10 gitleaks 8.30.1 on PATH is needed for the guard's cases (D-108)" "${gl:-none}"
fi
setup
gbranch() { g switch -q -c "task/TASK-004-$1" develop; }
gbranch clean
printf 'clean text\n' > "$CL/clean.txt"
g add clean.txt
g commit -q -m "TASK-004: a clean change"
if [ -r "/proc/$$/winpid" ]; then
    mkdir -p "$SB/appdata-empty" "$SB/appdata-fake/BantuKasir/tools/gitleaks/8.30.1"
    runreal LOCALAPPDATA="$(cygpath -w "$SB/appdata-empty")" guard.sh
    if [ "$RC" = 1 ] && [ ! -e "$SD/guard-pass" ]; then expect_out "10 guard FAIL without the user-local gitleaks, without a stamp" '^FAIL GITLEAKS_BINARY .* is missing'; else failc "10 guard FAIL without the user-local gitleaks, without a stamp" "$OUT"; fi
    printf '#!/bin/sh\necho 8.30.1\n' > "$SB/appdata-fake/BantuKasir/tools/gitleaks/8.30.1/gitleaks.exe"
    chmod +x "$SB/appdata-fake/BantuKasir/tools/gitleaks/8.30.1/gitleaks.exe"
    runreal LOCALAPPDATA="$(cygpath -w "$SB/appdata-fake")" guard.sh
    if [ "$RC" = 1 ] && [ ! -e "$SD/guard-pass" ]; then expect_out "10 guard FAIL on a gitleaks with another SHA-256, without a stamp" '^FAIL GITLEAKS_BINARY .* another SHA-256'; else failc "10 guard FAIL on a gitleaks with another SHA-256, without a stamp" "$OUT"; fi
else
    mkdir -p "$SB/oldleaks"
    printf '#!/bin/sh\necho 8.29.0\n' > "$SB/oldleaks/gitleaks"
    chmod +x "$SB/oldleaks/gitleaks"
    runreal PATH="$SB/oldleaks:$PATH" guard.sh
    if [ "$RC" = 1 ] && [ ! -e "$SD/guard-pass" ]; then expect_out "10 guard FAIL with another gitleaks version, without a stamp" '^FAIL GITLEAKS_VERSION'; else failc "10 guard FAIL with another gitleaks version, without a stamp" "$OUT"; fi
fi
runreal guard.sh
[ "$RC" = 0 ] && [ "$(sed -n '1p' "$SD/guard-pass")" = "$(g rev-parse HEAD)" ] && pass "10 guard PASS on a clean commit, with the stamp" || failc "10 guard PASS" "$OUT"
gbranch secret
rnd="$( (LC_ALL=C tr -dc 'A-Z2-7' < /dev/urandom | head -c 16) 2> /dev/null || true)"
printf 'aws_access_key_id = %s%s\n' "AK""IA" "$rnd" > "$CL/config.ini"
g add config.ini
g commit -q -m "TASK-004: a configuration"
runreal guard.sh
if [ "$RC" = 1 ] && [ ! -e "$SD/guard-pass" ]; then expect_out "10 guard FAIL on a generated secret-like value, caught by the default rules" '^FAIL DEFAULT_RULES'; else failc "10 guard FAIL on a generated secret-like value, caught by the default rules" "$OUT"; fi
rm -f -- "$CL/config.ini"
gbranch identity
printf 'x\n' > "$CL/id.txt"
g add id.txt
GIT_AUTHOR_EMAIL=other@example.com g commit -q -m "TASK-004: another author"
runreal guard.sh
if [ "$RC" = 1 ]; then expect_out "10 guard FAIL on a wrong identity" '^FAIL IDENTITY'; else failc "10 guard FAIL on a wrong identity" "$OUT"; fi
gbranch trailer
printf 'x\n' > "$CL/tr.txt"
g add tr.txt
g commit -q -m "TASK-004: a change" -m "Signed-off-by: $ONAME <$OEMAIL>"
runreal guard.sh
if [ "$RC" = 1 ]; then expect_out "10 guard FAIL on a trailer" '^FAIL MESSAGE .* trailer'; else failc "10 guard FAIL on a trailer" "$OUT"; fi
gbranch envfile
printf 'APP_KEY=\n' > "$CL/.env.local"
g add -f .env.local
g commit -q -m "TASK-004: an environment file"
runreal guard.sh
if [ "$RC" = 1 ]; then expect_out "10 guard FAIL on an .env path" '^FAIL PATH .* adds .env.local'; else failc "10 guard FAIL on an .env path" "$OUT"; fi
gbranch review
mail="jane.doe""@""bk-selftest-mail""corp.net"
url="https:/""/intranet.bk-selftest""corp.net/x"
hp="/ho""me/jane/notes.txt"
hpv="/ho""me/jane"
printf 'contact %s\nsee %s\nfile %s\n' "$mail" "$url" "$hp" > "$CL/notes.md"
runreal guard.sh --preview
pv="$OUT"
prc="$RC"
g add notes.md
g commit -q -m "TASK-004: notes"
runreal guard.sh
grc="$RC"
gout="$OUT"
if [ "$grc" = 3 ] && printf '%s\n' "$gout" | grep -q "^email|.*|notes.md|$mail$" && printf '%s\n' "$gout" | grep -q "^url|.*|notes.md|$url$" && printf '%s\n' "$gout" | grep -q "^home|.*|notes.md|$hpv$" && [ ! -e "$SD/guard-pass" ]; then pass "10 guard REVIEW on an e-mail address, a non-public URL and a home-directory path"; else failc "10 guard REVIEW" "$gout"; fi
hash="$(printf '%s\n' "$gout" | sed -n 's/^GUARD REVIEW .*SHA-256 \([0-9a-f]\{64\}\) .*/\1/p')"
runreal guard.sh --accept "$(printf '%064d' 0)"
if [ "$RC" = 1 ] && [ ! -e "$SD/guard-pass" ]; then expect_out "10 guard --accept refuses a wrong hash" '^REFUSED HASH'; else failc "10 guard --accept refuses a wrong hash" "$OUT"; fi
runreal guard.sh --accept "$hash"
[ "$RC" = 0 ] && [ "$(sed -n '1p' "$SD/guard-pass")" = "$(g rev-parse HEAD)" ] && pass "10 guard --accept stamps with the right hash" || failc "10 guard --accept" "$OUT"
pvc="$(printf '%s\n' "$pv" | grep -E '^(email|url|home)\|' | cut -d'|' -f1,3,4 | sort)"
gvc="$(printf '%s\n' "$gout" | grep -E '^(email|url|home)\|' | cut -d'|' -f1,3,4 | sort)"
[ "$prc" = 3 ] && [ -n "$pvc" ] && [ "$pvc" = "$gvc" ] && pass "10 guard --preview on the uncommitted change lists the same candidates" || failc "10 guard --preview" "$pv"

# ---------------------------------------------------------------- 11. snapshot.sh and review-pack.sh
setup
printf '#!/bin/sh\necho tool\n' > "$CL/tool.sh"
g add tool.sh
g update-index --chmod=+x tool.sh
g commit -q -m "an executable file"
g push -q origin develop
base="$(g rev-parse HEAD)"
printf 'echo changed\n' >> "$CL/tool.sh"
printf 'modified\n' >> "$CL/README.md"
printf 'new\n' > "$CL/new.txt"
mkdir -p "$CL/Claude outputs"
printf 'x\n' > "$CL/Claude outputs/o.md"
printf 'x\n' > "$CL/plan-p0-p11-review.patch"
ih="$(g ls-files -s | sha256sum)"
run snapshot.sh --base "$base"
tree="$(first | sed -n 's/^TREE //p')"
manual="$(cd "$CL" && export GIT_INDEX_FILE="$SB/manual-index" && rm -f "$GIT_INDEX_FILE" && git read-tree HEAD && git add -A -- . ':!Claude outputs' ':!plan-p0-p11-review.patch' && git write-tree)"
[ -n "$tree" ] && [ "$tree" = "$manual" ] && [ "$ih" = "$(g ls-files -s | sha256sum)" ] && pass "11 the snapshot tree equals a manual temporary index, and the real index is unchanged" || failc "11 snapshot tree" "$OUT / $manual"
[ -z "$(g ls-tree -r --name-only "$tree" | grep -e '^Claude outputs/' -e '^plan-p0-p11-review.patch$')" ] && g ls-tree -r --name-only "$tree" | grep -qx new.txt && pass "11 the known untracked files are excluded, the others included" || failc "11 known untracked excluded"
run review-pack.sh --base "$base" --round 1
pkg1="$(first | sed -n 's/^PACKAGE //p')"
[ -n "$pkg1" ] && cmp -s "$pkg1/diff.patch" <(g diff "$base" "$tree") && pass "11 diff.patch equals git diff <base> <tree>" || failc "11 diff.patch" "$OUT"
printf 'fixed\n' >> "$CL/new.txt"
run review-pack.sh --base "$base" --round 2 --previous "$pkg1"
pkg2="$(first | sed -n 's/^PACKAGE //p')"
tree2="$(sed -n 's/^Tree: //p' "$pkg2/MANIFEST.txt")"
[ -n "$pkg2" ] && cmp -s "$pkg2/fix.patch" <(g diff "$tree" "$tree2") && [ "$(sed -n 's/^Previous tree: //p' "$pkg2/MANIFEST.txt")" = "$tree" ] && pass "11 fix.patch equals git diff <previous tree> <tree>" || failc "11 fix.patch" "$OUT"
run review-pack.sh --check "$pkg2"
expect_first "11 --check gives UNCHANGED" '^UNCHANGED$'
printf 'again\n' >> "$CL/new.txt"
run review-pack.sh --check "$pkg2"
expect_first "11 --check gives CHANGED after a file changes" '^CHANGED tree'
run review-pack.sh --base "$base" --round 3
pkg3="$(first | sed -n 's/^PACKAGE //p')"
g add new.txt
run review-pack.sh --check "$pkg3"
expect_first "11 --check gives CHANGED after the index changes" '^CHANGED index'
run review-pack.sh --base "$base" --round 4
pkg4="$(first | sed -n 's/^PACKAGE //p')"
g commit -q -m "a commit"
run review-pack.sh --check "$pkg4"
expect_first "11 --check gives CHANGED after HEAD changes" 'HEAD'
run review-pack.sh --base "$base" --round 5
pkg5="$(first | sed -n 's/^PACKAGE //p')"
stset loop.phase BUILD
run review-pack.sh --check "$pkg5"
expect_first "11 --check gives CHANGED after the state changes" '^CHANGED state$'

# ---------------------------------------------------------------- 12. pr.sh
setup
task_pr
stset pr.number 7
for b in "$(printf 'A description.\n\nCo-Authored-By: someone <x@example.com>')" "$(printf 'Made with Copilot.')" "$(printf 'A description.\n\nReviewed-by: the Owner')" "$(printf 'A description.\n\nResult: all green')"; do
    printf '%s\n' "$b" > "$SB/body.md"
    run pr.sh open --title "TASK-003 — Shared kernel" --body-file "$SB/body.md"
    expect_first "12 pr.sh open refuses the body: $(printf '%s' "$b" | tail -n 1)" '^REFUSED TEXT'
done
printf 'A plain description that ends with a sentence.\n' > "$SB/body.md"
: > "$FIX/gh/open_prs"
printf 'https://github.com/yusufarst/bantukasir/pull/7\n' > "$FIX/gh/pr_create"
run pr.sh open --title "TASK-003 — Shared kernel" --body-file "$SB/body.md"
expect_first "12 pr.sh open opens a pull request with a valid body" '^PR 7 https://github.com/yusufarst/bantukasir/pull/7$'
git config -f "$SD/state" --unset-all pr.number
git config -f "$SD/state" --unset-all pr.head
printf '7|%s|%040d|yusufarst|yusufarst/bantukasir|https://github.com/yusufarst/bantukasir/pull/7\n' "$TB" 0 > "$FIX/gh/open_prs"
run pr.sh open --title "TASK-003 — Shared kernel" --body-file "$SB/body.md"
[ -z "$(stget pr.number)" ] && expect_first "12 pr.sh open refuses an open pull request whose head is not the local HEAD" '^REFUSED HEAD ' || failc "12 pr.sh open refuses an open pull request whose head is not the local HEAD" "$OUT"
printf '7|%s|%s|yusufarst|yusufarst/bantukasir|https://github.com/yusufarst/bantukasir/pull/7\n' "$TB" "$HEADSHA" > "$FIX/gh/open_prs"
run pr.sh open --title "TASK-003 — Shared kernel" --body-file "$SB/body.md"
first | grep -qE '^PR 7 https://github.com/yusufarst/bantukasir/pull/7 \(already open\)$' && [ "$(stget pr.number)" = 7 ] && [ "$(stget pr.head)" = "$HEADSHA" ] && pass "12 pr.sh open records the number and head of an already open pull request" || failc "12 pr.sh open records the number and head of an already open pull request" "$OUT"
: > "$FIX/gh/open_prs"
printf '2|another app|completed|success|https://github.com/yusufarst/bantukasir/runs/2\n1|Command contract|in_progress||https://github.com/yusufarst/bantukasir/runs/1\n' > "$FIX/gh/check_runs_$HEADSHA.1"
printf '2|another app|completed|success|https://github.com/yusufarst/bantukasir/runs/2\n1|Command contract|completed|success|https://github.com/yusufarst/bantukasir/runs/1\n' > "$FIX/gh/check_runs_$HEADSHA.2"
run EXECUTOR_POLL_SECONDS=0 pr.sh wait-ci
expect_first "12 wait-ci gives PASS after pending" "^CI PASS $HEADSHA$"
fails_ok=1
for i in 1 2 3 4; do
    h="$(printf '%039d%s' 0 "$i")"
    fx_pr 7 open false "" "$h" "$TB"
    printf '1|Command contract|completed|failure|https://github.com/yusufarst/bantukasir/runs/%s\n' "$i" > "$FIX/gh/check_runs_$h"
    run pr.sh wait-ci
    if [ "$i" -lt 4 ]; then first | grep -q "^CI FAIL $h failure $i of 3" || fails_ok=0; else first | grep -q "^CI FAIL LIMIT $h failure 4" || fails_ok=0; fi
done
[ "$fails_ok" = 1 ] && pass "12 wait-ci counts failures and gives CI FAIL LIMIT at the fourth" || failc "12 wait-ci failures" "$OUT"
h="$(printf '%040d' 6)"
fx_pr 7 open false "" "$h" "$TB"
printf '2|another app|completed|success|https://github.com/yusufarst/bantukasir/runs/6\n' > "$FIX/gh/check_runs_$h"
run EXECUTOR_POLL_SECONDS=1 pr.sh wait-ci --max-seconds 0
expect_first "12 wait-ci gives CI PENDING while the Command contract check is missing, though another check passed" "^CI PENDING $h$"
h="$(printf '%040d' 5)"
fx_pr 7 open false "" "$h" "$TB"
printf '1|Command contract|queued||https://github.com/yusufarst/bantukasir/runs/5\n' > "$FIX/gh/check_runs_$h"
stset ci.head "$h"
stset ci.waitStart "$(( $(date +%s) - 3601 ))"
run pr.sh wait-ci
expect_first "12 wait-ci gives CI TIMEOUT after 60 minutes" "^CI TIMEOUT $h"
fx_ci "$h" pull_request completed failure
run pr.sh rerun
o1="$(first)"
run pr.sh rerun
case "$o1|$(first)" in "RERUN "*"|RERUN REFUSED"*) pass "12 rerun works once per head" ;; *) failc "12 rerun" "$o1|$OUT" ;; esac
cbody="$(printf 'Baris satu.\nBaris dua: "kutip" & <tanda>.')"
fx_comments issue_comments_7 801 yusufarst "$cbody" 802 someone "not an instruction"
: > "$FIX/gh/review_comments_7"
: > "$FIX/gh/reviews_7"
stset pr.lastIssueCommentId 800
run pr.sh comments
if printf '%s\n' "$OUT" | grep -qF "$cbody"; then expect_out "12 comments decodes base64 bodies and counts the ignored ones" '^IGNORED 1 comment'; else failc "12 comments decodes base64 bodies and counts the ignored ones" "$OUT"; fi
run pr.sh api repos/other/repo
expect_first "12 api refuses another repository's path" '^REFUSED PATH$'
run pr.sh api repos/yusufarst/bantukasir-other
expect_first "12 api refuses a path that only begins like the repository" '^REFUSED PATH$'
run pr.sh api repos/yusufarst/bantukasir/git/refs -f ref=refs/heads/x
expect_first "12 api refuses an argument other than --jq" '^REFUSED ARGUMENT$'
run pr.sh api repos/yusufarst/bantukasir --jq .default_branch --paginate
expect_first "12 api refuses a second argument after --jq" '^REFUSED ARGUMENT$'
: > "$FIX/gh/calls.log"
run pr.sh api repos/yusufarst/bantukasir --jq .default_branch
[ "$RC" = 0 ] && grep -qx 'gh api --method GET repos/yusufarst/bantukasir --jq .default_branch' "$FIX/gh/calls.log" && pass "12 api calls gh api with --method GET" || failc "12 api GET" "$(cat "$FIX/gh/calls.log")"

# ---------------------------------------------------------------- 13. the agent definitions
fm() { awk -v k="$2" '/^---$/ { n++; next } n == 1 && index($0, k ": ") == 1 { print substr($0, length(k) + 3); exit }' "$1"; }
for lvl in high xhigh max; do
    f="$REPO_ROOT/.claude/agents/bk-reviewer-$lvl.md"
    d="$(fm "$f" disallowedTools)"
    ok=1
    [ "$(fm "$f" name)" = "bk-reviewer-$lvl" ] && [ "$(fm "$f" tools)" = "Read, Grep, Glob" ] && [ "$(fm "$f" model)" = claude-opus-5-5 ] && [ "$(fm "$f" effort)" = "$lvl" ] || ok=0
    for t in Bash Edit Write NotebookEdit Agent; do printf '%s\n' "$d" | tr ',' '\n' | sed 's/^ *//; s/ *$//' | grep -qx "$t" || ok=0; done
    [ "$ok" = 1 ] && pass "13 bk-reviewer-$lvl: tools Read, Grep, Glob; Bash, Edit, Write, NotebookEdit and Agent disallowed; claude-opus-5-5; effort $lvl" || failc "13 bk-reviewer-$lvl"
done
for lvl in medium high xhigh max; do
    f="$REPO_ROOT/.claude/agents/bk-executor-$lvl.md"
    [ "$(fm "$f" name)" = "bk-executor-$lvl" ] && [ "$(fm "$f" model)" = claude-opus-5-5 ] && [ "$(fm "$f" effort)" = "$lvl" ] && pass "13 bk-executor-$lvl: claude-opus-5-5, effort $lvl" || failc "13 bk-executor-$lvl"
done
missing=""
for a in $(awk '/^## 19\./ { s = 1 } /^## / && !/^## 19\./ { s = 0 } s && /^\| (Max|XHigh|High|Medium) \|/' "$REPO_ROOT/docs/11-tasks/EXECUTION_CONTEXT.md" | grep -oE 'bk-(executor|reviewer)-[a-z]+' | sort -u); do
    [ -f "$REPO_ROOT/.claude/agents/$a.md" ] || missing="$missing $a"
done
[ -z "$missing" ] && pass "13 every agent of the effort table exists" || failc "13 missing agents:$missing"

# ---------------------------------------------------------------- 14. the settings
S="$REPO_ROOT/.claude/settings.json"
if command -v powershell.exe > /dev/null 2>&1; then
    js="$(MSYS_NO_PATHCONV=1 powershell.exe -NoProfile -NonInteractive -Command "Get-Content -Raw -LiteralPath '$(cd "$(dirname "$S")" && pwd -W)/settings.json' | ConvertFrom-Json | Out-Null; 'JSON_OK'" < /dev/null 2>&1 | tr -d '\r')"
    [ "$js" = JSON_OK ] && pass "14 settings.json is valid JSON (ConvertFrom-Json)" || failc "14 settings.json JSON" "$js"
else
    failc "14 settings.json JSON: no Windows PowerShell on this host"
fi
arr() { awk -v k="\"$1\": [" 'index($0, k) { s = 1; next } s && /^[[:space:]]*\]/ { exit } s { gsub(/^[[:space:]]*"|",?[[:space:]]*$/, ""); print }' "$S"; }
ask_have="$(arr ask)"
deny_have="$(arr deny)"
allow_have="$(arr allow)"
miss=""
while IFS= read -r e; do
    [ -n "$e" ] || continue
    printf '%s\n' "$ask_have" | grep -qxF -- "$e" || miss="$miss [ask] $e"
done << 'ASK'
Bash(bash scripts/executor/state.sh resume*)
Bash(bash scripts/executor/state.sh unlock*)
Bash(bash scripts/executor/state.sh adopt*)
Bash(rm *)
Bash(mv *)
ASK
[ -z "$miss" ] && pass "14 settings: every ask entry of the resume, unlock, adopt, rm and mv rules" || failc "14 settings ask:$miss"
# D-105: commit.sh and push.sh are allowed, never asked; raw commits, pushes
# and merges stay denied.
miss=""
for e in "Bash(bash scripts/executor/commit.sh*)" "Bash(bash scripts/executor/push.sh*)"; do
    printf '%s\n' "$allow_have" | grep -qxF -- "$e" || miss="$miss [allow] $e"
    if printf '%s\n' "$ask_have" | grep -qxF -- "$e"; then miss="$miss [ask] $e"; fi
done
[ -z "$miss" ] && pass "14 settings: commit.sh and push.sh are allowed, not asked (D-105)" || failc "14 settings commit.sh and push.sh:$miss"
miss=""
for e in "Edit(.claude/**)" "Edit(scripts/executor/**)"; do printf '%s\n' "$ask_have" | grep -qxF -- "$e" || miss="$miss $e"; done
[ -z "$miss" ] && pass "14 settings: the two Edit ask entries" || failc "14 settings: the two Edit ask entries are missing:$miss"
miss=""
while IFS= read -r e; do
    [ -n "$e" ] || continue
    printf '%s\n' "$deny_have" | grep -qxF -- "$e" || miss="$miss $e"
done << 'DENY'
Read(./.env)
Bash(git commit*)
Bash(git -* commit*)
Bash(git push*)
Bash(git -* push*)
Bash(git -c*)
Bash(git add*)
Bash(git rm*)
Bash(git reset*)
Bash(git clean*)
Bash(git restore*)
Bash(git checkout*)
Bash(git switch -f*)
Bash(git switch --force*)
Bash(git switch --discard-changes*)
Bash(git switch * --discard-changes*)
Bash(git stash)
Bash(git stash push*)
Bash(git stash save*)
Bash(git stash pop*)
Bash(git stash apply*)
Bash(git stash drop*)
Bash(git stash clear*)
Bash(git stash branch*)
Bash(git stash store*)
Bash(git rebase*)
Bash(git cherry-pick*)
Bash(git revert*)
Bash(git pull*)
Bash(git merge --squash*)
Bash(git branch -d*)
Bash(git branch -D*)
Bash(git branch --delete*)
Bash(git branch -m*)
Bash(git branch -M*)
Bash(git branch --move*)
Bash(git branch -f*)
Bash(git branch --force*)
Bash(git tag*)
Bash(git update-ref*)
Bash(git symbolic-ref*)
Bash(git replace*)
Bash(git filter-branch*)
Bash(git filter-repo*)
Bash(git reflog*)
Bash(git gc*)
Bash(git prune*)
Bash(git worktree add*)
Bash(git worktree remove*)
Bash(git worktree prune*)
Bash(git worktree move*)
Bash(git remote add*)
Bash(git remote remove*)
Bash(git remote rm*)
Bash(git remote set-url*)
Bash(git remote rename*)
Bash(git config*)
Bash(git fetch *+*)
Bash(git fetch * -f*)
Bash(git fetch *--force*)
Bash(*--prune*)
Bash(bash -*)
Bash(sh *)
Bash(zsh *)
Bash(env *)
Bash(eval *)
Bash(exec *)
Bash(xargs *)
Bash(source *)
Bash(. *)
Bash(cmd *)
Bash(cmd.exe *)
Bash(powershell*)
Bash(pwsh*)
Bash(wsl*)
Bash(python* -c*)
Bash(py -c*)
Bash(node -e*)
Bash(node -p*)
Bash(node --eval*)
Bash(perl -e*)
Bash(ruby -e*)
Bash(php -r*)
Bash(gh pr merge*)
Bash(gh pr close*)
Bash(gh pr reopen*)
Bash(gh pr review*)
Bash(gh pr edit*)
Bash(gh pr comment*)
Bash(gh pr create*)
Bash(gh pr ready*)
Bash(gh pr lock*)
Bash(gh run rerun*)
Bash(gh run cancel*)
Bash(gh run delete*)
Bash(gh repo*)
Bash(gh release*)
Bash(gh secret*)
Bash(gh variable*)
Bash(gh workflow*)
Bash(gh ruleset*)
Bash(gh cache*)
Bash(gh issue*)
Bash(gh label*)
Bash(gh auth login*)
Bash(gh auth logout*)
Bash(gh auth token*)
Bash(gh auth status -t*)
Bash(gh auth status * -t*)
Bash(*--show-token*)
Bash(gh auth refresh*)
Bash(gh auth setup-git*)
Bash(gh auth switch*)
Bash(gh api *-X*)
Bash(gh api *--method*)
Bash(gh api *-f*)
Bash(gh api *-F*)
Bash(gh api *--field*)
Bash(gh api *--raw-field*)
Bash(gh api *--input*)
Bash(gh api *Method-Override*)
Bash(gh ssh-key*)
Bash(gh gpg-key*)
Bash(gh codespace*)
Bash(gh gist*)
Bash(gh extension*)
Bash(gh alias*)
Bash(gh config set*)
Bash(docker-compose*)
Bash(docker exec *)
Bash(docker compose * git *)
Bash(docker compose * bash*)
Bash(docker compose * sh)
Bash(docker compose * sh -*)
Bash(docker compose * php -r*)
Bash(*tinker*)
Bash(docker compose *down*-v*)
Bash(docker compose *--volumes*)
Bash(docker compose rm*)
Bash(docker volume rm*)
Bash(docker volume prune*)
Bash(docker system prune*)
Bash(docker container prune*)
Bash(docker image prune*)
Bash(docker rm *-v*)
Bash(docker rmi*)
Bash(*migrate:fresh*)
Bash(*migrate:reset*)
Bash(*migrate:refresh*)
Bash(*migrate:rollback*)
Bash(*db:wipe*)
Bash(*DROP DATABASE*)
Bash(*drop database*)
Bash(*DROP SCHEMA*)
Bash(*drop schema*)
Bash(*DROP TABLE*)
Bash(*drop table*)
Bash(*TRUNCATE*)
Bash(*truncate *)
Bash(*dropdb*)
Bash(rm -*)
Bash(rm * -*)
Bash(find * -delete*)
Bash(find * -exec rm*)
Bash(ssh *)
Bash(scp *)
Bash(sftp *)
Bash(rsync *)
PowerShell
DENY
[ -z "$miss" ] && pass "14 settings: every deny entry of 4.12, the bare PowerShell deny among them" || failc "14 settings deny, missing:$miss"
bad="$(printf '%s\n%s\n%s\n' "$allow_have" "$ask_have" "$deny_have" | grep -E '^(Write|NotebookEdit|MultiEdit|Glob)\(' || true)"
[ -z "$bad" ] && pass "14 settings: no path rule for Write, NotebookEdit, MultiEdit or Glob" || failc "14 settings path rules: $bad"
bad="$(printf '%s\n' "$allow_have" | grep -E '^Bash\((docker compose \*|docker run|gh api|bash -|sh )' || true)"
[ -z "$bad" ] && pass "14 settings: no broad allow entry for docker compose *, docker run, gh api, bash - or sh" || failc "14 settings broad allow: $bad"
printf '%s\n' "$allow_have" | grep -qxF 'Bash(bash scripts/executor/watch.sh*)' && pass "14 settings: the allow entry of watch.sh (D-104)" || failc "14 settings: the allow entry of watch.sh is missing"
# The executor agents of D-104 and D-108: one body, the steps, checkpoint runs
# of CI before the pull request, no wait for the pull request's CI.
b0="$(tail -n +7 "$REPO_ROOT/.claude/agents/bk-executor-high.md")"
ok=1
for lvl in medium xhigh max; do [ "$(tail -n +7 "$REPO_ROOT/.claude/agents/bk-executor-$lvl.md")" = "$b0" ] || ok=0; done
for s in '3. Checks (EXECUTION_CONTEXT CE-44 to CE-46):' 'run in a checkpoint run of CI, never in a container on this machine' '4. Review (CE-40), once:' '7. Report and end (CE-41, CE-60):' 'Do not wait for CI.' '- wait for the pull request'"'"'s CI, which the tick follows, or poll GitHub in a loop of model turns;' '- run a container of BantuKasir on this machine (D-108);' 'Never repeat the full review.' 'Mode `fix`: the delegation'"'"'s Reason names a red CI'; do printf '%s\n' "$b0" | grep -qF -- "$s" || ok=0; done
if printf '%s\n' "$b0" | grep -qF -e 'pr.sh wait-ci' -e '8. Green'; then ok=0; fi
[ "$ok" = 1 ] && pass "13 the four executor agents share one body with the steps of D-104 and D-108, without a wait for the pull request's CI" || failc "13 the executor agents' bodies (D-104, D-108)"

# ================================================================ D-104
WD="$SD/watch"
GREEN="11|Command contract|completed|success"
# fx_checks <sha> <id|name|status|conclusion>...: the check runs of a head,
# each with the URL of its job in the workflow run 88<id>.
fx_checks() {
    local f="$FIX/gh/check_runs_$1" c id nm st co
    shift
    : > "$f"
    for c in "$@"; do
        IFS='|' read -r id nm st co <<< "$c"
        printf '%s|%s|%s|%s|https://github.com/yusufarst/bantukasir/actions/runs/88%s/job/%s\n' "$id" "$nm" "$st" "$co" "$id" "$id" >> "$f"
    done
}
# ci_setup: a fresh sandbox with #7 adopted, then in the phase CI without a
# lock, its 60 minutes started now on its head.
ci_setup() {
    merge_setup
    stset loop.phase CI
    stset ci.head "$HEADSHA"
    stset ci.waitStart "$(date +%s)"
}
classify() { run tick.sh --classify; }
expect_class() { if [ "$(printf '%s\n' "$OUT" | wc -l)" -eq 1 ] && first | grep -E -- "$2" > /dev/null; then pass "$1"; else failc "$1" "$OUT"; fi; }
inflight_task004() { for kv in loop.phase=BUILD task.id=TASK-004 task.kind=task task.branch=task/TASK-004-database-foundation task.agent=bk-executor-xhigh task.reviewAgent=bk-reviewer-xhigh task.verifyAgent=bk-reviewer-high task.effort=xhigh task.resumes=0; do stset "${kv%%=*}" "${kv#*=}"; done; }
dead_holder() {  # sets DREC, the record of a process that has ended
    live_process
    kill "$LPID" 2> /dev/null || true
    wait "$LPID" 2> /dev/null || true
    sleep 1
    DREC="$LREC"
}
# fx_classify <line>...: the classification of each poll of the watcher, the
# last one repeating (the fixture watch/classify in place of the tick).
fx_classify() {
    local i=0 l
    mkdir -p "$FIX/watch"
    rm -f -- "$FIX/watch/classify" "$FIX/watch/classify".*
    for l in "$@"; do i=$((i + 1)); printf '%s\n' "$l" > "$FIX/watch/classify.$i"; done
}
no_classify() { rm -rf -- "$FIX/watch"; }
# bg_watch [VAR=value]...: a watcher in the background in the sandbox clone;
# sets BGW and waits for its lock.
bg_watch() {
    local envs=() i
    while [[ "${1:-}" =~ ^[A-Z_][A-Z0-9_]*= ]]; do envs+=("$1"); shift; done
    (cd "$CL" && exec env EXECUTOR_SELFTEST=1 EXECUTOR_FIXTURES="$FIX" HOME="$SB/home" USERPROFILE="$SB/home" "${envs[@]}" bash "$HERE/watch.sh" > "$SB/bgw.out" 2>&1) &
    BGW=$!
    BGPIDS+=("$BGW")
    for i in $(seq 1 60); do [ -s "$WD/lock/ctime" ] && return 0; sleep 1; done
    return 1
}
# kill_watcher: kills the watcher of the lock at once, so that no trap runs.
kill_watcher() {
    local p
    p="$(cat "$WD/lock/pid" 2> /dev/null || true)"
    if [ -r "/proc/$$/winpid" ]; then taskkill //PID "$p" //F > /dev/null 2>&1 || true; else kill -9 "$p" 2> /dev/null || true; fi
    wait "$BGW" 2> /dev/null || true
    sleep 1
}
sdhash() { (cd "$SD" && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum 2> /dev/null) | sha256sum | cut -d' ' -f1; }
WAKE_RE='^BANTUKASIR WATCHER EVENT'
OB=ops/selftest-watch
# ops_pr: an ops/ branch with one commit, pushed, with an open pull request #8.
ops_pr() {
    g switch -q -c "$OB" develop
    printf 'ops\n' > "$CL/ops.txt"
    g add ops.txt
    g commit -q -m "ops: a change"
    g push -q origin "$OB"
    g fetch -q origin
    OHEAD="$(g rev-parse HEAD)"
    fx_pr 8 open false "" "$OHEAD" "$OB"
    printf '8|%s|%s|yusufarst|yusufarst/bantukasir|https://github.com/yusufarst/bantukasir/pull/8\n' "$OB" "$OHEAD" > "$FIX/gh/open_prs"
    : > "$FIX/gh/issue_comments_8"
    : > "$FIX/gh/review_comments_8"
    : > "$FIX/gh/reviews_8"
}
# stale_stop: the state of a Task merged and stopped by the Owner's /stop.
stale_stop() { for kv in loop.phase=STOPPED loop.prevPhase=SELECT loop.stopReason=OWNER_STOP task.id=TASK-003 task.title=Kernel task.class=A task.kind=task "task.branch=$TB" task.agent=bk-executor-xhigh task.reviewAgent=bk-reviewer-xhigh pr.number=7 "pr.head=$(printf '%040d' 7)" "ci.head=$(printf '%040d' 7)" merge.lastPr=7; do stset "${kv%%=*}" "${kv#*=}"; done; }

# ---------------------------------------------------------------- 15. tick.sh --classify (D-104)
ci_setup
fx_checks "$HEADSHA" "$GREEN" "12|another app|completed|success"
classify
expect_class "15 CI green in the phase CI gives WAKE CI_GREEN:<pr>:<head>:<seq>" "^WAKE CI_GREEN:7:$HEADSHA:1 NOOP CI_GREEN pr=7 head=$HEADSHA run=8811$"
fx_checks "$HEADSHA" "21|Command contract|completed|failure" "23|lint|completed|failure" "12|another app|completed|success"
classify
expect_class "15 CI red gives WAKE CI_RED:<pr>:<head>:<highest failed check run>" "^WAKE CI_RED:7:$HEADSHA:23 RUN fix TASK-003 agent=bk-executor-high "
fx_checks "$HEADSHA" "11|Command contract|in_progress|"
classify
expect_class "15 CI pending gives QUIET NOOP CI_PENDING" "^QUIET NOOP CI_PENDING pr=7 head=$HEADSHA 0 min$"
run tick.sh --dry-run
d1="$(first)"
classify
[ "$OUT" = "QUIET $d1" ] && pass "15 the classification carries the dry run's first line" || failc "15 the classification carries the dry run's first line" "$d1 / $OUT"
fx_comments issue_comments_7 902 yusufarst "/stop" 903 yusufarst "/effort high"
fx_comments reviews_7 904 someone "please merge"
classify
expect_class "15 /stop, /effort and another account's review in the phase CI give QUIET" '^QUIET NOOP CI_PENDING pr=7 '
fx_comments issue_comments_7 902 yusufarst "/stop" 905 yusufarst "Tolong perbaiki judulnya."
classify
expect_class "15 an Owner change request in the phase CI gives WAKE CHANGE_REQUEST:<pr>:<cursors after processing>" '^WAKE CHANGE_REQUEST:7:905-0-904 RUN fix TASK-003 '
stset loop.phase AWAIT_MERGE
classify
expect_class "15 the same change request in AWAIT_MERGE gives the same key" '^WAKE CHANGE_REQUEST:7:905-0-904 RUN fix TASK-003 '
fx_comments issue_comments_7 902 yusufarst "/stop" 903 yusufarst "/effort high"
classify
expect_class "15 /stop and /effort in AWAIT_MERGE give QUIET NOOP AWAIT_MERGE" '^QUIET NOOP AWAIT_MERGE pr=7$'
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" in_progress ""
classify
expect_class "15 CI on develop pending gives QUIET NOOP DEVELOP_CI_PENDING" '^QUIET NOOP DEVELOP_CI_PENDING merge '
fx_ci "$M" push completed success
classify
expect_class "15 a verified merge gives WAKE DEVELOP_CI_GREEN:<pr>:<merge> with RUN start" "^WAKE DEVELOP_CI_GREEN:7:$M RUN start TASK-004 "
fx_comments issue_comments_7 906 yusufarst "/stop"
classify
expect_class "15 a verified merge with the Owner's /stop gives the same key with STOPPED OWNER_STOP" "^WAKE DEVELOP_CI_GREEN:7:$M STOPPED OWNER_STOP "
: > "$FIX/gh/issue_comments_7"
stset loop.phase CI
classify
expect_class "15 a verified merge found in the phase CI gives WAKE DEVELOP_CI_GREEN" "^WAKE DEVELOP_CI_GREEN:7:$M RUN start TASK-004 "
merge_setup
stset loop.phase STOPPED
stset loop.prevPhase AWAIT_MERGE
stset loop.stopReason PR_HEAD_MOVED
classify
expect_class "15 an unchanged stop gives QUIET STOPPED" '^QUIET STOPPED PR_HEAD_MOVED$'
fx_comments issue_comments_7 950 yusufarst "/resume Sudah saya cek."
classify
expect_class "15 an Owner /resume comment gives WAKE RESUMED:<pr>:<comment>, whatever follows" '^WAKE RESUMED:7:950 NOOP AWAIT_MERGE pr=7$'
setup
select_state
classify
expect_class "15 the phase SELECT gives WAKE RESUMED:SELECT:<origin/develop>:<seq> with RUN start" "^WAKE RESUMED:SELECT:$(g rev-parse origin/develop):0 RUN start TASK-004 "
inflight_task004
classify
expect_class "15 an in-flight phase without a lock gives WAKE RECOVERY:<task>:<phase>:<resumes>:<seq>" '^WAKE RECOVERY:TASK-004:BUILD:1:0 RUN resume TASK-004 '
merge_setup
fx_pr 7 closed false "" "$HEADSHA" "$TB"
classify
expect_class "15 a new stop gives WAKE STOPPED_NEW:<reason>:<pr>:<head>:<seq>" "^WAKE STOPPED_NEW:PR_CLOSED:7:$HEADSHA:1 STOPPED PR_CLOSED "
run tick.sh
classify
expect_class "15 the same stop, once recorded, gives QUIET" '^QUIET STOPPED PR_CLOSED '
merge_setup
mklock "" "$NOW" "$((NOW - 10800))"
classify
expect_class "15 LOCK_UNVERIFIED gives WAKE STOPPED_NEW keyed by the lock's since" "^WAKE STOPPED_NEW:LOCK_UNVERIFIED:7:$NOW:1 STOPPED LOCK_UNVERIFIED UNKNOWN "
mklock "" "$NOW" "$(date +%s)"
classify
expect_class "15 NOOP LOCKED gives QUIET" '^QUIET NOOP LOCKED UNKNOWN NO_RECORD'
rm -rf -- "$SD/lock"
: > "$FIX/gh/FAIL"
classify
rm -f -- "$FIX/gh/FAIL"
expect_class "15 GitHub unreachable gives QUIET NOOP GITHUB_UNAVAILABLE" '^QUIET NOOP GITHUB_UNAVAILABLE '
: > "$CL/.git/index.lock"
classify
rm -f -- "$CL/.git/index.lock"
expect_class "15 .git/index.lock gives QUIET NOOP INDEX_LOCK" '^QUIET NOOP INDEX_LOCK '
run EXECUTOR_INTERRUPT=tick_error tick.sh --classify
expect_class "15 an internal error still gives one line: WAKE STOPPED_NEW:TICK_ERROR" "^WAKE STOPPED_NEW:TICK_ERROR:7:$HEADSHA:1 STOPPED TICK_ERROR phase$"
rm -rf -- "$SD"
: > "$FIX/gh/open_prs"
classify
expect_class "15 no state gives WAKE STOPPED_NEW:NOT_INITIALIZED:-:-:0" '^WAKE STOPPED_NEW:NOT_INITIALIZED:-:-:0 STOPPED NOT_INITIALIZED '

# ---------------------------------------------------------------- 16. tick.sh, the phase CI without a lock (D-104)
ci_setup
fx_checks "$HEADSHA" "11|Command contract|in_progress|"
h1="$(hashstate)"
run tick.sh
if [ "$(hashstate)" = "$h1" ] && [ ! -e "$SD/lock" ]; then expect_first "16 pending gives NOOP CI_PENDING and changes nothing" "^NOOP CI_PENDING pr=7 head=$HEADSHA 0 min$"; else failc "16 pending gives NOOP CI_PENDING and changes nothing" "$OUT"; fi
fx_checks "$HEADSHA" "$GREEN"
run tick.sh
if [ "$(stget loop.phase)" = AWAIT_MERGE ] && [ ! -e "$SD/lock" ] && grep -q "CI PASS $HEADSHA" "$SD/log"; then expect_first "16 green sets AWAIT_MERGE and gives NOOP CI_GREEN" "^NOOP CI_GREEN pr=7 head=$HEADSHA run=8811$"; else failc "16 green sets AWAIT_MERGE and gives NOOP CI_GREEN" "$OUT"; fi
run tick.sh
expect_first "16 then the tick waits for the merge" '^NOOP AWAIT_MERGE pr=7$'
stset loop.phase CI
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
run tick.sh
if [ "$(stget loop.phase)" = CI ] && [ -e "$SD/lock" ] && [ "$(stget ci.failures)" = 1 ] && [[ "$OUT" == *"Command contract https://github.com/yusufarst/bantukasir/actions/runs/8821/job/21"* ]] && [[ "$OUT" == *"failure 1 of 3 (CE-42)"* ]]; then expect_first "16 red takes the lock and gives RUN fix naming the failing check, failure 1 of 3" '^RUN fix TASK-003 agent=bk-executor-high '; else failc "16 red takes the lock and gives RUN fix naming the failing check, failure 1 of 3" "$OUT"; fi
dead_holder
mklock "$DREC" "$NOW" "$(date +%s)"
run tick.sh
if grep -q 'TAKEOVER INACTIVE PROCESSES_GONE' "$SD/log" && [ "$(stget loop.phase)" = CI ] && [ "$(stget task.resumes)" = 1 ]; then expect_first "16 a lock taken over from a dead holder in the phase CI gives RUN resume" '^RUN resume TASK-003 '; else failc "16 a lock taken over from a dead holder in the phase CI gives RUN resume" "$OUT"; fi
ci_setup
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
stset ci.failures 3
run tick.sh
if [ "$(stget loop.phase)" = STOPPED ] && [ ! -e "$SD/lock" ]; then expect_first "16 the fourth red head gives STOPPED CI_FAILURE_LIMIT" "^STOPPED CI_FAILURE_LIMIT #7 failure 4 on $HEADSHA$"; else failc "16 the fourth red head gives STOPPED CI_FAILURE_LIMIT" "$OUT"; fi
ci_setup
fx_checks "$HEADSHA" "11|Command contract|queued|"
stset ci.waitStart "$(( $(date +%s) - 3600 ))"
run tick.sh
expect_first "16 pending 60 minutes after the push gives STOPPED CI_TIMEOUT" "^STOPPED CI_TIMEOUT #7 head $HEADSHA pending for 60 minutes$"
ci_setup
fx_checks "$HEADSHA" "11|Command contract|in_progress|"
stset ci.head "$(printf '%040d' 9)"
stset ci.waitStart 12345
run tick.sh
if [ "$(stget ci.head)" = "$HEADSHA" ] && [ "$(stget ci.waitStart)" != 12345 ]; then expect_first "16 a new head restarts the 60 minutes" '^NOOP CI_PENDING pr=7 '; else failc "16 a new head restarts the 60 minutes" "$OUT"; fi
fx_comments issue_comments_7 960 yusufarst "Ganti teks tombolnya."
run tick.sh
if [ "$(stget loop.phase)" = CI ] && [ -e "$SD/lock" ] && [[ "$OUT" == *"Ganti teks tombolnya."* ]]; then expect_first "16 an Owner change request in the phase CI gives RUN fix with his words" '^RUN fix TASK-003 '; else failc "16 an Owner change request in the phase CI gives RUN fix with his words" "$OUT"; fi
ci_setup
stset task.kind ops
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
run tick.sh
if [ "$(stget loop.phase)" = STOPPED ] && [ ! -e "$SD/lock" ]; then expect_first "16 a red ops pull request gives STOPPED CI_RED" '^STOPPED CI_RED #7 Command contract https://'; else failc "16 a red ops pull request gives STOPPED CI_RED" "$OUT"; fi
ci_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
run tick.sh
if [ "$(stget merge.lastSha)" = "$M" ] && [ "$(stget merge.lastPr)" = 7 ]; then expect_first "16 a merged pull request in the phase CI goes through the merge path" '^RUN start TASK-004 '; else failc "16 a merged pull request in the phase CI goes through the merge path" "$OUT"; fi

# ---------------------------------------------------------------- 17. state.sh phase CI (D-104)
setup
stset loop.phase PUBLISH
stset pr.number 7
stset pr.head "$(printf '%040d' 7)"
stset ci.head "$(printf '%040d' 6)"
stset ci.waitStart 100
mklock "" "$NOW" "$NOW"
run state.sh phase CI --token selftesttoken
if [ "$(first)" = "PHASE CI" ] && [ ! -e "$SD/lock" ] && [ "$(stget loop.phase)" = CI ] && [ "$(stget ci.head)" = "$(printf '%040d' 7)" ] && [ "$(stget ci.waitStart)" -gt 100 ] && grep -q ' LOCK_RELEASED token=selftesttoken at CI$' "$SD/log"; then pass "17 phase CI releases the lock, sets ci.head and ci.waitStart on a new head, and logs the release by its token"; else failc "17 phase CI on a new head" "$OUT"; fi
stset loop.phase PUBLISH
stset ci.waitStart 100
mklock "" "$NOW" "$NOW"
run state.sh phase CI --token selftesttoken
[ "$(stget ci.waitStart)" = 100 ] && [ "$(stget ci.head)" = "$(printf '%040d' 7)" ] && [ ! -e "$SD/lock" ] && pass "17 phase CI on the same head keeps ci.head and ci.waitStart" || failc "17 phase CI on the same head" "$OUT"
git config -f "$SD/state" --unset-all pr.number
stset loop.phase PUBLISH
mklock "" "$NOW" "$NOW"
run state.sh phase CI --token selftesttoken
if [ "$RC" = 1 ] && [ -e "$SD/lock" ] && [ "$(stget loop.phase)" = PUBLISH ]; then expect_first "17 phase CI without a pull request refuses with REFUSED NO_PR and keeps the lock" '^REFUSED NO_PR'; else failc "17 phase CI without a pull request" "$OUT"; fi
run state.sh stop TEST_STOP --token selftesttoken
grep -q ' LOCK_RELEASED token=selftesttoken at STOPPED TEST_STOP$' "$SD/log" && [ ! -e "$SD/lock" ] && pass "17 stop logs the release by its token" || failc "17 stop logs the release by its token" "$OUT"

# ---------------------------------------------------------------- 18. the dry run and the classification write nothing (D-104)
nowrite() {  # label: --dry-run and --classify leave every file of the executor state as it was
    local h ok=1
    h="$(sdhash)"
    run tick.sh --dry-run
    [ "$(sdhash)" = "$h" ] || ok=0
    run tick.sh --classify
    [ "$(sdhash)" = "$h" ] || ok=0
    run EXECUTOR_INTERRUPT=tick_error tick.sh --classify
    [ "$(sdhash)" = "$h" ] || ok=0
    [ "$ok" = 1 ] && pass "18 --dry-run and --classify write nothing: $1" || failc "18 --dry-run and --classify write nothing: $1" "$OUT"
}
ci_setup
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
fx_comments issue_comments_7 970 yusufarst "Satu permintaan." 971 someone "data only"
printf 'issue comment|1|yusufarst|eA==\n' > "$SD/requests"
printf 'issue comment|2|someone|eQ==\n' > "$SD/ignored"
printf '1|NOOP AWAIT_MERGE pr=7\n' > "$SD/last-tick"
nowrite "the phase CI, red, with comments, requests, ignored and last-tick"
inflight_task004
dead_holder
mklock "$DREC" "$NOW" "$NOW"
nowrite "an in-flight phase with a dead holder's lock"
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
fx_comments issue_comments_7 972 yusufarst "/stop"
nowrite "a verified merge with the Owner's /stop"
stset loop.phase STOPPED
stset loop.stopReason PR_HEAD_MOVED
fx_pr 7 open false "" "$HEADSHA" "$TB"
fx_comments issue_comments_7 973 yusufarst "/resume"
nowrite "a stop with an Owner /resume comment"
rm -f -- "$SD/last-tick"
run tick.sh
lt="$(cat "$SD/last-tick" 2> /dev/null || true)"
[ -n "$(first)" ] && [ "${lt#*|}" = "$(first)" ] && [[ "${lt%%|*}" =~ ^[0-9]+$ ]] && pass "18 a real tick writes last-tick, <epoch>|<first line>" || failc "18 a real tick writes last-tick" "$lt / $OUT"
rm -rf -- "$SD/lock"
run EXECUTOR_INTERRUPT=tick_error tick.sh
lt="$(cat "$SD/last-tick" 2> /dev/null || true)"
[ "${lt#*|}" = "STOPPED TICK_ERROR phase" ] && pass "18 a real tick writes last-tick on TICK_ERROR too" || failc "18 last-tick on TICK_ERROR" "$lt / $OUT"

# ---------------------------------------------------------------- 19. watch.sh --ack and the redelivery (D-104)
ci_setup
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
run watch.sh --ack
expect_first "19 --ack without an event gives NO_EVENT" '^NO_EVENT$'
KEY="CI_RED:7:$HEADSHA:21"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=3 watch.sh
if [ "$OUT" = "BANTUKASIR WATCHER EVENT $KEY — follow EXECUTION_CONTEXT CE-03 — RUN fix TASK-003 agent=bk-executor-high review=bk-reviewer-high verify=bk-reviewer-high effort=high token=DRYRUN" ] && [ ! -e "$WD/lock" ] && grep -q "^[^ ]* EVENT [0-9]* $KEY " "$WD/log" && grep -q "^[0-9]*|$KEY|RUN fix " "$WD/event"; then pass "19 the watcher emits a red CI with exactly its one line, writes the event and releases its lock"; else failc "19 the watcher emits a red CI" "$OUT"; fi
run watch.sh --ack
expect_first "19 --ack before a real tick gives NOT_ACKED no real tick since the event" "^NOT_ACKED $KEY no real tick since the event$"
e="$(cut -d'|' -f1 "$WD/event")"
ok=1
for l in "NOOP GITHUB_UNAVAILABLE git fetch origin, run 1" "NOOP INDEX_LOCK .git/index.lock present, run 1" "NOOP LOCKED ACTIVE 1, heartbeat 0 min ago" "STOPPED TICK_ERROR phase"; do
    printf '%s|%s\n' "$((e + 1))" "$l" > "$SD/last-tick"
    run watch.sh --ack
    [ "$(first)" = "NOT_ACKED $KEY the tick printed $l" ] || ok=0
done
[ "$ok" = 1 ] && pass "19 --ack after a tick that printed NOOP GITHUB_UNAVAILABLE, NOOP INDEX_LOCK, NOOP LOCKED or STOPPED TICK_ERROR gives NOT_ACKED" || failc "19 NOT_ACKED after an unfinished tick" "$OUT"
printf '%s|%s\n' "$((e - 1))" "NOOP AWAIT_MERGE pr=7" > "$SD/last-tick"
run watch.sh --ack
expect_first "19 a last tick older than the event gives NOT_ACKED" "^NOT_ACKED $KEY no real tick since the event$"
run tick.sh
tok="$(first | sed -n 's/.* token=\([0-9a-f]\{32\}\)$/\1/p')"
run watch.sh --ack
expect_first "19 after a RUN whose delegation still holds the executor lock, NOT_ACKED" "^NOT_ACKED $KEY the delegation of token ${tok:0:8} still holds the executor lock$"
run state.sh unlock
run watch.sh --ack
expect_first "19 a delegation whose lock was released by unlock, not by its own token, gives NOT_ACKED" "^NOT_ACKED $KEY the delegation of token ${tok:0:8} did not end through state.sh$"
mklock "" "$NOW" "$NOW"
printf '%s\n' "$tok" > "$SD/lock/token"
run state.sh phase CI --token "$tok"
run watch.sh --ack
expect_first "19 a delegation that ended through state.sh but left the same red CI gives NOT_ACKED: the event is still current" "^NOT_ACKED $KEY the event is still current: RUN fix TASK-003 "
[ ! -s "$WD/ack" ] && pass "19 nothing was acknowledged so far" || failc "19 nothing was acknowledged so far" "$(cat "$WD/ack")"
# A classification that cannot check the state acknowledges nothing (review R-01).
ok=1
for l in "QUIET NOOP GITHUB_UNAVAILABLE open pull requests, run 1" "QUIET NOOP INDEX_LOCK .git/index.lock present, run 1" "QUIET NOOP LOCKED ACTIVE 1, heartbeat 0 min ago" "WAKE STOPPED_NEW:TICK_ERROR:7:$HEADSHA STOPPED TICK_ERROR phase" "QUIET STOPPED LOCK_UNVERIFIED UNKNOWN NO_RECORD" "garbage" "$(printf 'QUIET NOOP CI_PENDING pr=7\nQUIET a second line')"; do
    fx_classify "$l"
    run watch.sh --ack
    first | grep -q "^NOT_ACKED $KEY the event cannot be checked now: " || ok=0
done
no_classify
[ "$ok" = 1 ] && [ ! -s "$WD/ack" ] && pass "19 a fresh classification of GitHub unavailable, an index lock, a held lock, an internal error, an unverified lock or an unreadable result gives NOT_ACKED" || failc "19 NOT_ACKED when the event cannot be checked" "$OUT"
fx_checks "$HEADSHA" "31|Command contract|in_progress|"
run watch.sh --ack
if [ "$(first)" = "ACKED $KEY" ] && grep -qxF "$KEY" "$WD/ack" && grep -q " ACK $KEY " "$WD/log"; then pass "19 a real tick, the release by the delegation's token and the event consumed give ACKED"; else failc "19 ACKED" "$OUT"; fi
run watch.sh --ack
expect_first "19 --ack again gives ALREADY_ACKED" "^ALREADY_ACKED $KEY$"
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=2 watch.sh
if [ "$OUT" = WATCHER_MAX_POLLS ]; then pass "19 an acknowledged key is not emitted again"; else failc "19 an acknowledged key is not emitted again" "$OUT"; fi
stset loop.phase STOPPED
stset loop.stopReason PR_CLOSED
printf '%s|STOPPED_NEW:PR_CLOSED:7:%s|STOPPED PR_CLOSED x\n' "$(date +%s)" "$HEADSHA" > "$WD/event"
printf '%s|STOPPED PR_CLOSED x\n' "$(( $(date +%s) + 1 ))" > "$SD/last-tick"
run watch.sh --ack
expect_first "19 a stop is acknowledged once a real tick printed it" "^ACKED STOPPED_NEW:PR_CLOSED:7:$HEADSHA$"
fx_classify "WAKE TESTKEY:1 NOOP TEST line"
T0="$(date +%s)"
wr() { run EXECUTOR_NOW="$1" EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=2 watch.sh; }
wr "$T0"
expect_class "19 an unacknowledged key is emitted" "$WAKE_RE TESTKEY:1 — follow EXECUTION_CONTEXT CE-03 — NOOP TEST line$"
wr "$((T0 + 299))"
expect_class "19 it is not emitted again within 5 minutes" '^WATCHER_MAX_POLLS$'
wr "$((T0 + 300))"
expect_class "19 it is emitted again after 5 minutes" "$WAKE_RE TESTKEY:1 — "
wr "$((T0 + 600))"
expect_class "19 and a third time after 5 more minutes" "$WAKE_RE TESTKEY:1 — "
wr "$((T0 + 900))"
expect_class "19 after 3 emissions without an acknowledgment, one WATCHER_STALL:REDELIVERY" "$WAKE_RE WATCHER_STALL:REDELIVERY:TESTKEY:1 — "
wr "$((T0 + 1500))"
expect_class "19 then neither the key nor its stall is emitted again" '^WATCHER_MAX_POLLS$'
no_classify

# ---------------------------------------------------------------- 20. watch.sh, the watcher (D-104)
setup
fx_classify "QUIET NOOP AWAIT_MERGE pr=7"
if bg_watch EXECUTOR_POLL_SECONDS=1 EXECUTOR_WATCH_MAX_POLLS=90; then
    run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
    expect_class "20 a second watcher gets WATCHER_RUNNING <pid> since <time>" '^WATCHER_RUNNING [0-9]+ since [0-9T:+-]+$'
    run watch.sh --test-wake 5
    expect_class "20 --test-wake while a watcher runs gets WATCHER_RUNNING" '^WATCHER_RUNNING [0-9]+ since '
    run watch.sh --status
    expect_first "20 --status shows the running watcher" '^WATCHER running [0-9]+ since '
    run watch.sh --once
    expect_class "20 --once prints one classification" '^QUIET NOOP AWAIT_MERGE pr=7$'
    kill_watcher
    run watch.sh --status
    expect_first "20 --status shows a killed watcher dead" '^WATCHER dead [0-9]+$'
    run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
    if grep -q ' TAKEOVER dead ' "$WD/log" && [ ! -e "$WD/lock" ]; then expect_class "20 a dead watcher's lock is taken over, with TAKEOVER logged" '^WATCHER_MAX_POLLS$'; else failc "20 a dead watcher's lock is taken over" "$OUT"; fi
    run watch.sh --status
    expect_first "20 --status then shows none" '^WATCHER none$'
else
    failc "20 the background watcher took no lock" "$(cat "$SB/bgw.out" 2> /dev/null)"
fi
mkdir -p "$WD/lock"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
expect_class "20 a young lock without a record gives WATCHER_RUNNING unverified" '^WATCHER_RUNNING unverified$'
touch -d '2 minutes ago' "$WD/lock"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
if grep -q ' TAKEOVER dead .* INCOMPLETE' "$WD/log"; then expect_class "20 a lock left without a record for a minute is taken over" '^WATCHER_MAX_POLLS$'; else failc "20 an incomplete lock is taken over" "$OUT"; fi
mkdir -p "$WD/lock"
printf '1\n' > "$WD/lock/pid"
printf 'x\n' > "$WD/lock/ctime"
: > "$FIX/proc/fail"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
expect_class "20 a lock whose liveness cannot be read gives WATCHER_RUNNING unverified" '^WATCHER_RUNNING unverified$'
rm -rf -- "$WD/lock"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
rm -f -- "$FIX/proc/fail"
[ -z "$OUT" ] && [ ! -e "$WD/lock" ] && grep -q 'cannot be read' "$WD/log" && pass "20 a watcher that cannot read its own creation time gives the lock back and ends without a line" || failc "20 an unreadable identity" "$OUT"
rm -f -- "$WD/event"
run watch.sh --test-wake 5
if [ ! -e "$WD/lock" ] && [ ! -e "$WD/event" ]; then expect_class "20 --test-wake prints its line, writes no event and releases the lock" '^BANTUKASIR WATCHER EVENT TEST:[0-9]+ — follow EXECUTION_CONTEXT CE-03 — test wake after 5 s$'; else failc "20 --test-wake" "$OUT"; fi
run watch.sh --test-wake 4
[ "$RC" = 2 ] && expect_first "20 --test-wake below 5 seconds is refused" '^USAGE ' || failc "20 --test-wake 4" "$OUT"
GH="QUIET NOOP GITHUB_UNAVAILABLE open pull requests, run 1"
fx_classify "$GH" "$GH" "QUIET NOOP AWAIT_MERGE pr=7" "$GH" "$GH" "$GH"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_STALL_POLLS=3 EXECUTOR_WATCH_MAX_POLLS=10 watch.sh
if [ "$(cat "$FIX/watch/classify.count")" = 6 ] && grep -q ' STALL WATCHER_STALL:GITHUB_UNAVAILABLE:' "$WD/log"; then expect_class "20 NOOP GITHUB_UNAVAILABLE in 30 polls in a row (3 here) gives WATCHER_STALL:GITHUB_UNAVAILABLE:<epoch>" "$WAKE_RE WATCHER_STALL:GITHUB_UNAVAILABLE:[0-9]+ — "; else failc "20 the GitHub stall" "$OUT"; fi
fx_classify "QUIET NOOP INDEX_LOCK .git/index.lock present, run 1"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_STALL_POLLS=3 EXECUTOR_WATCH_MAX_POLLS=10 watch.sh
expect_class "20 NOOP INDEX_LOCK in a row gives WATCHER_STALL:INDEX_LOCK:<epoch>" "$WAKE_RE WATCHER_STALL:INDEX_LOCK:[0-9]+ — "
DM="$(printf '%040d' 5)"
fx_classify "QUIET NOOP DEVELOP_CI_PENDING merge $DM of #7"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_STALL_POLLS=2 EXECUTOR_WATCH_MAX_POLLS=3 watch.sh
if [ "$(grep -c " PR_MERGED 7 $DM$" "$WD/log")" = 1 ]; then expect_class "20 the first NOOP DEVELOP_CI_PENDING of a merge logs PR_MERGED once, without a wake" '^WATCHER_MAX_POLLS$'; else failc "20 PR_MERGED" "$OUT"; fi
fx_classify "QUIET NOOP DEVELOP_CI_PENDING merge $DM of #7"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_STALL_POLLS=2 EXECUTOR_WATCH_MAX_POLLS=10 watch.sh
if [ "$(grep -c " PR_MERGED 7 $DM$" "$WD/log")" = 1 ]; then expect_class "20 NOOP DEVELOP_CI_PENDING for one merge in 60 polls (4 here) gives WATCHER_STALL:DEVELOP_CI_PENDING:<sha>" "$WAKE_RE WATCHER_STALL:DEVELOP_CI_PENDING:$DM — "; else failc "20 the stall of CI on develop" "$OUT"; fi
fx_classify "garbage" "QUIET NOOP AWAIT_MERGE pr=7" "garbage" "$(printf 'QUIET NOOP AWAIT_MERGE pr=7\nQUIET a second line')" "garbage"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=10 watch.sh
pc="$(cat "$FIX/watch/classify.count" 2> /dev/null || true)"
if [ "$pc" = 5 ]; then expect_class "20 three unparseable results in a row, two lines among them, give WATCHER_STALL:UNPARSEABLE:<epoch>" "$WAKE_RE WATCHER_STALL:UNPARSEABLE:[0-9]+ — follow EXECUTION_CONTEXT CE-03 — garbage$"; else failc "20 UNPARSEABLE after ${pc:-no} polls" "$OUT"; fi
no_classify

# ---------------------------------------------------------------- 21. state.sh adopt (D-104)
setup
ops_pr
stale_stop
run state.sh adopt --pr 8 --kind ops
if [ "$RC" = 0 ] && [ "$(stget owner.stopAfterMerge)" = 1 ] && [ "$(stget task.kind)" = ops ] && [ "$(stget task.branch)" = "$OB" ] && [ -z "$(stget task.id)" ] && [ -z "$(stget task.title)" ] && [ -z "$(stget task.agent)" ] && [ -z "$(stget ci.head)" ] && [ "$(stget pr.number)" = 8 ] && [ "$(stget loop.phase)" = AWAIT_MERGE ] && grep -q 'OWNER_STOP_KEPT pr=8' "$SD/log"; then expect_first "21 adopt from OWNER_STOP keeps the stop and drops the stale task, pr and ci fields" '^ADOPTED pr=8 phase=AWAIT_MERGE .* stop kept$'; else failc "21 adopt from OWNER_STOP" "$OUT"; fi
rm -rf -- "$SB/work"
git clone -q -b develop "$SB/origin.git" "$SB/work"
git -C "$SB/work" config user.name "$ONAME"
git -C "$SB/work" config user.email "$OEMAIL"
git -C "$SB/work" merge -q --squash "origin/$OB" > /dev/null
git -C "$SB/work" commit -q -m "ops: a change (#8)"
git -C "$SB/work" push -q origin develop
OM="$(git -C "$SB/work" rev-parse HEAD)"
fx_pr 8 closed true "$OM" "$OHEAD" "$OB"
: > "$FIX/gh/open_prs"
fx_ci "$OM" push completed success
fx_ci "$OHEAD" pull_request completed success
run tick.sh
if [ "$(stget loop.stopReason)" = OWNER_STOP ] && [ "$(stget merge.lastPr)" = 8 ] && [ "$(stget merge.kind)" = ops ]; then expect_first "21 the merge of the adopted pull request then gives STOPPED OWNER_STOP" '^STOPPED OWNER_STOP after the merge of #8'; else failc "21 the merge after adopt" "$OUT"; fi
setup
ops_pr
stale_stop
stset loop.stopReason PR_CLOSED
run state.sh adopt --pr 8 --kind ops
[ "$RC" = 0 ] && [ -z "$(stget owner.stopAfterMerge)" ] && ! grep -qs OWNER_STOP_KEPT "$SD/log" && ! first | grep -q 'stop kept' && pass "21 adopt from STOPPED with another reason keeps no stop" || failc "21 adopt from another stop" "$OUT"
setup
ops_pr
stale_stop
stset loop.phase SELECT
run state.sh adopt --pr 8 --kind ops
[ "$RC" = 0 ] && [ -z "$(stget owner.stopAfterMerge)" ] && ! first | grep -q 'stop kept' && [ -z "$(stget task.id)" ] && pass "21 adopt from SELECT keeps no stop and drops the stale task" || failc "21 adopt from SELECT" "$OUT"

# ---------------------------------------------------------------- 22. push.sh after a Task (D-104)
setup
ops_pr
stale_stop
printf 'more\n' >> "$CL/ops.txt"
run commit.sh -m "ops: more" -- ops.txt
stamp_head
ph0="$(stget pr.head)"
run push.sh
if [ "$(stget pr.head)" = "$ph0" ] && [ "$(g ls-remote origin "refs/heads/$OB" | cut -f1)" = "$(g rev-parse HEAD)" ]; then expect_first "22 on an ops/ branch, the stale task.branch of a Task stopped after its merge restricts nothing, and pr.head stays" "^PUSHED $OB "; else failc "22 the push after a Task" "$OUT"; fi
stset loop.phase BUILD
printf 'again\n' >> "$CL/ops.txt"
run commit.sh -m "ops: again" -- ops.txt
stamp_head
run push.sh
expect_first "22 with a Task in flight on another branch the push is refused" '^REFUSED BRANCH the state.s branch is '
stset loop.phase STOPPED
stset loop.prevPhase CI
run push.sh
expect_first "22 STOPPED from an in-flight phase still refuses another branch" '^REFUSED BRANCH the state.s branch is '

# ---------------------------------------------------------------- 23. review-pack.sh on an ops/ branch (D-104)
setup
ops_pr
stale_stop
run review-pack.sh --base develop --round 1
pk="$(first | sed -n 's/^PACKAGE //p')"
if [ -n "$pk" ] && [[ "$pk" == */review/ops-selftest-watch/r1 ]] && grep -qx 'Task: ops-selftest-watch' "$pk/MANIFEST.txt" && grep -qx 'Class: ops' "$pk/MANIFEST.txt" && grep -qx 'Agent (review): named by the session' "$pk/MANIFEST.txt"; then pass "23 review-pack.sh on an ops/ branch with a stale state writes the package ops-<name>, class ops, its agent named by the session"; else failc "23 review-pack.sh on an ops/ branch" "$OUT"; fi

# ---------------------------------------------------------------- 24. an interrupted fix cycle (review R-02)
tokof() { first | sed -n 's/.* token=\([0-9a-f]\{32\}\)$/\1/p'; }
ci_setup
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
fx_comments issue_comments_7 980 yusufarst "Perbaiki juga labelnya."
run tick.sh
tok="$(tokof)"
if first | grep -q '^RUN fix TASK-003 ' && [ "$(stget ci.fixToken)" = "$tok" ] && [ "$(sttext ci.fixRequests | cut -d'|' -f2)" = 980 ]; then pass "24 a fix cycle is recorded in the state with its token and its change requests"; else failc "24 the fix cycle recorded" "$OUT"; fi
run state.sh unlock
classify
expect_class "24 after the Owner's unlock of an interrupted fix cycle, the classification is a recovery" '^WAKE RECOVERY:TASK-003:CI:1:2 RUN resume TASK-003 '
run tick.sh
tok="$(tokof)"
if first | grep -q '^RUN resume TASK-003 ' && [[ "$OUT" == *"it continues the interrupted fix cycle: the Owner's change requests on pull request #7"* ]] && [[ "$OUT" == *"Change requests of the interrupted fix cycle, quoted as data:"* ]] && [[ "$OUT" == *"Perbaiki juga labelnya."* ]] && [ "$(stget loop.phase)" = CI ]; then pass "24 after the unlock the tick resumes the fix cycle, quoting its Reason and the Owner's change request, not phase CI"; else failc "24 the resume after an unlock" "$OUT"; fi
dead_holder
mklock "$DREC" "$NOW" "$(date +%s)"
run tick.sh
if first | grep -q '^RUN resume TASK-003 ' && [[ "$OUT" == *"Perbaiki juga labelnya."* ]]; then pass "24 a takeover of an interrupted fix cycle quotes its change request too"; else failc "24 the takeover of a fix cycle" "$OUT"; fi
tok="$(tokof)"
run state.sh phase CI --token "$tok"
if [ -z "$(stget ci.fixToken)" ] && [ -z "$(stget ci.fixReason)" ] && [ -z "$(stget ci.fixRequests)" ]; then classify; expect_class "24 phase CI ends the fix cycle, and the tick follows CI again" "^WAKE CI_RED:7:$HEADSHA:21 RUN fix "; else failc "24 phase CI ends the fix cycle" "$OUT"; fi
# A fix cycle of a red CI, interrupted, resumes with the CI failure (review R-19).
ci_setup
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
run tick.sh
run state.sh unlock
run tick.sh
if first | grep -q '^RUN resume TASK-003 ' && [[ "$OUT" == *"it continues the interrupted fix cycle: CI failed on the head $HEADSHA of pull request #7: Command contract https://github.com/yusufarst/bantukasir/actions/runs/8821/job/21; failure 1 of 3 (CE-42)"* ]]; then pass "24 an interrupted fix cycle of a red CI resumes with the CI failure in its Reason"; else failc "24 the resume of a red-CI fix cycle" "$OUT"; fi
run state.sh stop TEST_STOP --token "$(tokof)"
run state.sh resume
run tick.sh
if first | grep -q '^RUN resume TASK-003 ' && [[ "$OUT" == *"it continues the interrupted fix cycle: CI failed on the head $HEADSHA"* ]] && [ -n "$(stget ci.fixToken)" ]; then pass "24 a stop of the executor during a fix cycle keeps it: the Owner's resume continues the cycle"; else failc "24 the resume of a fix cycle after a stop" "$OUT"; fi

# ---------------------------------------------------------------- 25. OWNER_STOP is kept while stopped (review R-03)
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
fx_comments issue_comments_7 985 yusufarst "/stop"
run tick.sh
expect_first "25 the merge with the Owner's /stop gives STOPPED OWNER_STOP" '^STOPPED OWNER_STOP '
: > "$FIX/gh/FAIL"
run tick.sh
run tick.sh
run tick.sh
rm -f -- "$FIX/gh/FAIL"
if first | grep -q '^STOPPED OWNER_STOP kept over GITHUB_UNAVAILABLE .*; a resume resumes the Owner.s stop$' && [ "$(stget loop.stopReason)" = OWNER_STOP ] && [ "$(stget loop.prevPhase)" = SELECT ] && grep -q 'STOP_KEPT OWNER_STOP over GITHUB_UNAVAILABLE' "$SD/log"; then pass "25 a third GitHub failure while STOPPED OWNER_STOP prints the Owner's stop with it as the detail, and the Owner's stop stays recorded (review R-18)"; else failc "25 OWNER_STOP over GITHUB_UNAVAILABLE" "$OUT"; fi
: > "$FIX/gh/FAIL"
classify
rm -f -- "$FIX/gh/FAIL"
expect_class "25 its classification is QUIET: the recorded stop has not changed" '^QUIET STOPPED OWNER_STOP kept over GITHUB_UNAVAILABLE '
: > "$CL/.git/index.lock"
run tick.sh
run tick.sh
run tick.sh
o1="$(first)"
rm -f -- "$CL/.git/index.lock"
run EXECUTOR_INTERRUPT=tick_error tick.sh
if [ "$(stget loop.stopReason)" = OWNER_STOP ] && grep -q 'STOP_KEPT OWNER_STOP over INDEX_LOCK' "$SD/log" && grep -q 'STOP_KEPT OWNER_STOP over TICK_ERROR' "$SD/log" && [[ "$o1" == "STOPPED OWNER_STOP kept over INDEX_LOCK "* ]] && first | grep -q '^STOPPED OWNER_STOP kept over TICK_ERROR phase; '; then pass "25 neither INDEX_LOCK nor TICK_ERROR replaces OWNER_STOP, and each prints the Owner's stop"; else failc "25 OWNER_STOP over INDEX_LOCK and TICK_ERROR" "$o1 / $OUT"; fi
e="$(( $(date +%s) - 5 ))"
mkdir -p "$WD"
printf '%s|RESUMED:7:999|NOOP AWAIT_MERGE pr=7\n' "$e" > "$WD/event"
run watch.sh --ack
o1="$(first)"
mkdir -p "$FIX/watch"
printf 'QUIET STOPPED OWNER_STOP kept over GITHUB_UNAVAILABLE open pull requests, 4 runs in a row; a resume resumes the Owner%ss stop\n' "'" > "$FIX/watch/classify"
printf '%s|NOOP AWAIT_MERGE pr=7\n' "$(date +%s)" > "$SD/last-tick"
run watch.sh --ack
rm -f -- "$FIX/watch/classify" "$WD/event"
if [[ "$o1" == "NOT_ACKED RESUMED:7:999 the tick printed STOPPED OWNER_STOP kept over TICK_ERROR phase; "* ]] && first | grep -q '^NOT_ACKED RESUMED:7:999 the event cannot be checked now: QUIET STOPPED OWNER_STOP kept over GITHUB_UNAVAILABLE '; then pass "25 --ack refuses after a tick of OWNER_STOP kept over TICK_ERROR, and on a fresh classification of OWNER_STOP kept over GITHUB_UNAVAILABLE (review R-23)"; else failc "25 --ack with OWNER_STOP kept" "$o1 / $OUT"; fi
run tick.sh
expect_first "25 with GitHub back the tick still gives STOPPED OWNER_STOP, never RUN start" '^STOPPED OWNER_STOP '

# ---------------------------------------------------------------- 26. a new occurrence has a new key (review R-04)
setup
select_state
inflight_task004
classify
k1="$(printf '%s\n' "$OUT" | awk '{ print $2 }')"
run tick.sh
run state.sh stop TEST_STOP --token "$(tokof)"
run state.sh resume
classify
k2="$(printf '%s\n' "$OUT" | awk '{ print $2 }')"
if [ "$k1" = RECOVERY:TASK-004:BUILD:1:0 ] && [ "$k2" = RECOVERY:TASK-004:BUILD:1:2 ]; then pass "26 a recovery after the Owner's resume has a new key, though task.resumes starts again at 0"; else failc "26 RECOVERY keys" "$k1 / $k2"; fi
setup
select_state
classify
k1="$(printf '%s\n' "$OUT" | awk '{ print $2 }')"
printf 'x\n' > "$CL/unknown.txt"
run tick.sh
rm -f -- "$CL/unknown.txt"
run state.sh resume
classify
k2="$(printf '%s\n' "$OUT" | awk '{ print $2 }')"
case "$k1|$k2" in RESUMED:SELECT:*:0"|"RESUMED:SELECT:*:1) pass "26 a selection resumed after a stop in SELECT has a new key, develop unmoved" ;; *) failc "26 RESUMED:SELECT keys" "$k1 / $k2" ;; esac
ci_setup
fx_checks "$HEADSHA" "$GREEN"
classify
k1="$(printf '%s\n' "$OUT" | awk '{ print $2 }')"
run tick.sh
fx_comments issue_comments_7 986 yusufarst "Ubah komentar kodenya saja."
run tick.sh
run state.sh phase CI --token "$(tokof)"
classify
k2="$(printf '%s\n' "$OUT" | awk '{ print $2 }')"
if [ "$k1" = "CI_GREEN:7:$HEADSHA:1" ] && [ "$k2" = "CI_GREEN:7:$HEADSHA:2" ]; then pass "26 CI green again on the same head after a fix cycle has a new key"; else failc "26 CI_GREEN keys" "$k1 / $k2"; fi

# ---------------------------------------------------------------- 27. --once and --status write nothing (review R-09)
ci_setup
fx_checks "$HEADSHA" "21|Command contract|completed|failure"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=1 watch.sh
h="$(sdhash)"
run watch.sh --once
o1="$OUT"
run watch.sh --status
[ "$(sdhash)" = "$h" ] && [ -s "$WD/event" ] && printf '%s\n' "$o1" | grep -q '^WAKE CI_RED:7:' && printf '%s\n' "$OUT" | grep -q '^ACKNOWLEDGED no$' && pass "27 watch.sh --once and --status write nothing, the watcher's folder included" || failc "27 --once and --status write nothing" "$o1 / $OUT"

# ---------------------------------------------------------------- 28. comments stay with their pull request (review R-10)
merge_setup
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
fx_comments issue_comments_7 990 yusufarst "Komentar setelah merge." 991 someone "data only"
run tick.sh
if first | grep -q '^RUN start TASK-004 ' && [[ "$OUT" != *"Komentar setelah merge."* ]] && [[ "$OUT" != *"data only"* ]]; then pass "28 RUN start after a merge quotes no comment of the merged pull request"; else failc "28 comments of the merged pull request" "$OUT"; fi
setup
ops_pr
stale_stop
run state.sh adopt --pr 8 --kind ops
fx_comments issue_comments_8 992 yusufarst "Ubah satu kalimat."
run tick.sh
if first | grep -q '^STOPPED OPS_CHANGE_REQUEST ' && [ ! -s "$SD/requests" ]; then
    run state.sh resume
    run tick.sh
    if [ "$(first)" = "NOOP AWAIT_MERGE pr=8" ] && [ "$(stget owner.stopAfterMerge)" = 1 ]; then pass "28 an ops change request stops once and is not kept: the resume waits for the merge, the Owner's stop still kept"; else failc "28 the resume after OPS_CHANGE_REQUEST" "$OUT"; fi
else
    failc "28 OPS_CHANGE_REQUEST clears the requests" "$OUT"
fi

# ---------------------------------------------------------------- 29. a stop that recurs after a resume is a new event (D-104 hotfix)
merge_setup
fx_pr 7 closed false "" "$HEADSHA" "$TB"
classify
c1="$OUT"
classify
[ "$OUT" = "$c1" ] && [[ "$c1" == "WAKE STOPPED_NEW:PR_CLOSED:7:$HEADSHA:"* ]] && pass "29 while one stop lasts, its key stays the same from one classification to the next" || failc "29 a stable key" "$c1 / $OUT"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=2 watch.sh
o1="$OUT"
k1="$(cut -d'|' -f2 "$WD/event" 2> /dev/null || true)"
run tick.sh
run watch.sh --ack
a1="$(first)"
fx_pr 7 open false "" "$HEADSHA" "$TB"
run state.sh resume
run tick.sh
r1="$(first)"
fx_pr 7 closed false "" "$HEADSHA" "$TB"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=2 watch.sh
o2="$OUT"
k2="$(cut -d'|' -f2 "$WD/event" 2> /dev/null || true)"
if [ "$k1" = "STOPPED_NEW:PR_CLOSED:7:$HEADSHA:1" ] && [[ "$o1" == "BANTUKASIR WATCHER EVENT $k1 — follow EXECUTION_CONTEXT CE-03 — STOPPED PR_CLOSED "* ]] && [ "$a1" = "ACKED $k1" ] && [ "$r1" = "NOOP AWAIT_MERGE pr=7" ]; then pass "29 a pull request closed is emitted, the host's tick records the stop and --ack acknowledges it; reopened and resumed, the tick waits for the merge"; else failc "29 the first stop" "$o1 / $k1 / $a1 / $r1"; fi
if [ "$k2" = "STOPPED_NEW:PR_CLOSED:7:$HEADSHA:2" ] && [ "$(printf '%s\n' "$o2" | wc -l)" -eq 1 ] && [[ "$o2" == "BANTUKASIR WATCHER EVENT $k2 — follow EXECUTION_CONTEXT CE-03 — STOPPED PR_CLOSED "* ]] && grep -qxF "$k1" "$WD/ack" && ! grep -qxF "$k2" "$WD/ack"; then pass "29 the same stop after the Owner's resume has a new key and is emitted, not suppressed by the earlier acknowledgment"; else failc "29 the recurring stop" "$o2 / $k2"; fi
run tick.sh
t2="$(first)"
run watch.sh --ack
if [[ "$t2" == "STOPPED PR_CLOSED "* ]] && [ "$(first)" = "ACKED $k2" ] && [ "$(stget loop.stopReason)" = PR_CLOSED ]; then pass "29 the host's tick then records the second stop and --ack acknowledges its new key"; else failc "29 the delivery of the recurring stop" "$t2 / $OUT"; fi
# Leaving the stop by an adoption rather than a resume (hotfix verification H-01).
fx_pr 7 open false "" "$HEADSHA" "$TB"
printf '7|%s|%s|yusufarst|yusufarst/bantukasir|https://github.com/yusufarst/bantukasir/pull/7\n' "$TB" "$HEADSHA" > "$FIX/gh/open_prs"
run state.sh adopt --pr 7 --kind task
fx_pr 7 closed false "" "$HEADSHA" "$TB"
: > "$FIX/gh/open_prs"
run EXECUTOR_POLL_SECONDS=0 EXECUTOR_WATCH_MAX_POLLS=2 watch.sh
o3="$OUT"
k3="$(cut -d'|' -f2 "$WD/event" 2> /dev/null || true)"
if [ "$k3" = "STOPPED_NEW:PR_CLOSED:7:$HEADSHA:3" ] && [[ "$o3" == "BANTUKASIR WATCHER EVENT $k3 — "* ]] && grep -qxF "$k2" "$WD/ack"; then pass "29 the same stop after an adoption out of it has a new key too, and is emitted"; else failc "29 the recurring stop after an adoption" "$o3 / $k3"; fi

# ---------------------------------------------------------------- 30. the automated review's window and the merge (D-105)
BOT='chatgpt-codex-connector[bot]'
E0="$(date -u -d 2026-10-26T00:00:00Z +%s)"
# d105_setup: #7 adopted in AWAIT_MERGE with its required job green, opened at
# E0; GitHub shows it open on the first read and merged as M on the next one.
d105_setup() {
    merge_setup
    git config -f "$SD/state" --unset pr.adopted
    : > "$FIX/gh/open_prs"
    printf '1|Command contract|completed|success|https://github.com/yusufarst/bantukasir/actions/runs/8811/job/1\n' > "$FIX/gh/check_runs_$HEADSHA"
    printf '2026-10-26T00:00:00Z\n' > "$FIX/gh/pr_created_7"
    : > "$FIX/gh/reactions_7"
    M="$(simulate_merge "$OEMAIL")"
    fx_pr 7 open false "" "$HEADSHA" "$TB"
    mv "$FIX/gh/pr_7" "$FIX/gh/pr_7.1"
    fx_pr 7 closed true "$M" "$HEADSHA" "$TB"
    mv "$FIX/gh/pr_7" "$FIX/gh/pr_7.2"
    fx_ci "$M" push completed success
    fx_ci "$HEADSHA" pull_request completed success
}
reread() { rm -f -- "$FIX/gh/pr_7.count"; }
merge_called() { grep -qF "gh pr merge 7 --repo yusufarst/bantukasir --squash --match-head-commit $HEADSHA" "$FIX/gh/calls.log" 2> /dev/null; }
tick30() { run EXECUTOR_AUTO_MERGE=1 EXECUTOR_REVIEW_BOTS="$BOT" EXECUTOR_NOW="$1" tick.sh "${@:2}"; }

d105_setup
tick30 $((E0 + 60))
expect_first "30 no sign of the reviewer within 3 minutes of the opening gives NOOP REVIEW_PENDING" '^NOOP REVIEW_PENDING pr=7 no sign of the automated review yet$'
if merge_called; then failc "30 no merge while the review is pending" "$OUT"; else pass "30 no merge while the review is pending"; fi
reread
tick30 $((E0 + 200))
expect_first "30 no sign of the reviewer after 3 minutes: the squash merge, its verification, then RUN start" '^RUN start TASK-004 '
if merge_called && grep -q 'REVIEW_ABSENT pr=7' "$SD/log" && grep -qF "AUTO_MERGE pr=7 head=$HEADSHA review=ABSENT" "$SD/log" && [ "$(stget merge.lastSha)" = "$M" ] && [ "$(stget merge.lastPr)" = 7 ] && [ "$(stget merge.review)" = "the tick's merge, automated review ABSENT" ] && printf '%s\n' "$OUT" | grep -qF "; the tick's merge, automated review ABSENT"; then pass "30 the merge names the head, and the absent review, the merge and its verification are recorded"; else failc "30 the merge and its record" "$OUT"; fi

d105_setup
printf '%s|eyes|2026-10-26T00:00:30Z\n' "$BOT" > "$FIX/gh/reactions_7"
tick30 $((E0 + 300))
expect_first "30 a review started 4 minutes ago gives NOOP REVIEW_PENDING 4 of 10 min" '^NOOP REVIEW_PENDING pr=7 4 of 10 min$'
reread
tick30 $((E0 + 300)) --classify
expect_first "30 the classification of a pending review is QUIET" '^QUIET NOOP REVIEW_PENDING pr=7 '
reread
tick30 $((E0 + 630)) --classify
expect_first "30 ten minutes after the review's start the classification is WAKE AUTO_MERGE" "^WAKE AUTO_MERGE:7:$HEADSHA:[0-9]+ NOOP AUTO_MERGE pr=7 head=$HEADSHA review=TIMEOUT$"
if merge_called; then failc "30 a dry run never merges" "$OUT"; else pass "30 a dry run never merges"; fi
reread
tick30 $((E0 + 630))
expect_first "30 ten minutes after the review's start: the merge, then RUN start" '^RUN start TASK-004 '
if merge_called && grep -q 'REVIEW_TIMEOUT pr=7 after 10 min' "$SD/log"; then pass "30 the timeout is logged and the merge made"; else failc "30 the timeout and the merge" "$OUT"; fi

d105_setup
printf '%s|+1|2026-10-26T00:01:00Z\n' "$BOT" > "$FIX/gh/reactions_7"
tick30 $((E0 + 90))
expect_first "30 the reviewer's +1 reaction ends the window: the merge, then RUN start" '^RUN start TASK-004 '
if merge_called && grep -qF "review=DONE" "$SD/log"; then pass "30 the +1 reaction counts as the review done"; else failc "30 the +1 reaction" "$OUT"; fi

d105_setup
fx_comments review_comments_7 302 "$BOT" '**![P1 Badge](badge)** Validate the token before use'
tick30 $((E0 + 200))
expect_first "30 a P1 finding of the reviewer gives RUN fix before any merge" '^RUN fix TASK-003 '
if ! merge_called && grep -q 'BOT_FINDING review comment 302' "$SD/log" && printf '%s\n' "$OUT" | grep -qF "review comment 302 by $BOT:"; then pass "30 the finding is a change request and nothing is merged"; else failc "30 the finding as a change request" "$OUT"; fi

d105_setup
summary_running='<!-- codex-review-state:v1 {"code_review":{"commit_sha":"x","has_any_findings":null,"has_unresolved_findings":null,"status":"running"},"security_review":{"status":null}} -->'
fx_comments issue_comments_7 210 "$BOT" "$summary_running"
printf '%s|eyes|2026-10-26T00:00:30Z\n' "$BOT" > "$FIX/gh/reactions_7"
tick30 $((E0 + 300))
expect_first "30 a summary comment still running does not end the window: NOOP REVIEW_PENDING" '^NOOP REVIEW_PENDING pr=7 4 of 10 min$'
if ! merge_called && grep -q 'BOT_INFO issue comment 210' "$SD/log"; then pass "30 the running summary is listed and nothing is merged"; else failc "30 the running summary" "$OUT"; fi
reread
fx_comments issue_comments_7 210 "$BOT" "${summary_running/running/completed}"
tick30 $((E0 + 320))
expect_first "30 the same summary edited to completed ends the window: the merge, then RUN start" '^RUN start TASK-004 '
if merge_called && grep -qF "review=DONE" "$SD/log"; then pass "30 the completed summary is the review DONE"; else failc "30 the completed summary" "$OUT"; fi

d105_setup
fx_comments issue_comments_7 211 "$BOT" "You have reached your usage limits."
tick30 $((E0 + 60))
expect_first "30 any other output of the reviewer does not end the window: NOOP REVIEW_PENDING" '^NOOP REVIEW_PENDING pr=7 no sign of the automated review yet$'
if ! merge_called; then pass "30 nothing is merged on a notice of the reviewer"; else failc "30 a notice of the reviewer" "$OUT"; fi

d105_setup
fx_comments reviews_7 402 "$BOT" "Here are some automated review suggestions for this pull request."
tick30 $((E0 + 60))
expect_first "30 a review submitted by the reviewer, without a P0 to P2 finding, ends the window: the merge" '^RUN start TASK-004 '

d105_setup
stset pr.adopted 1
tick30 $((E0 + 200))
expect_first "30 an adopted pull request stays the Owner's merge: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
reread
git config -f "$SD/state" --unset pr.adopted
fx_comments issue_comments_7 212 yusufarst "/stop"
tick30 $((E0 + 200))
expect_first "30 the Owner's /stop withholds the tick's merge: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
if ! merge_called && [ "$(stget owner.stopAfterMerge)" = 1 ]; then pass "30 no merge under /stop or for an adopted pull request"; else failc "30 /stop and adoption" "$OUT"; fi

d105_setup
printf '1|Command contract|in_progress||https://github.com/yusufarst/bantukasir/actions/runs/8812/job/1\n' > "$FIX/gh/check_runs_$HEADSHA"
tick30 $((E0 + 200))
expect_first "30 CI pending again on the head (a re-run) waits: NOOP AWAIT_MERGE" "^NOOP AWAIT_MERGE pr=7 CI pending on $HEADSHA$"
if ! merge_called && [ "$(stget loop.phase)" = AWAIT_MERGE ]; then pass "30 nothing is merged and the phase stays AWAIT_MERGE"; else failc "30 CI pending in AWAIT_MERGE" "$OUT"; fi

d105_setup
stset loop.phase CI
stset ci.head "$HEADSHA"
stset ci.waitStart "$((E0 + 100))"
tick30 $((E0 + 200))
expect_first "30 CI green in the phase CI, with the review absent, merges at once: RUN start" '^RUN start TASK-004 '
if merge_called; then pass "30 the phase CI reaches the merge"; else failc "30 the phase CI reaches the merge" "$OUT"; fi

d105_setup
run EXECUTOR_AUTO_MERGE=0 EXECUTOR_REVIEW_BOTS="$BOT" EXECUTOR_NOW=$((E0 + 200)) tick.sh
expect_first "30 without the merge setting, NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
reread
stset task.class O
tick30 $((E0 + 200))
expect_first "30 a class-O Task stays the Owner's merge: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
reread
stset task.class A
stset task.kind ops
tick30 $((E0 + 200))
expect_first "30 an ops/ pull request stays the Owner's merge: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
if merge_called; then failc "30 no merge without the setting, for class O or for ops/" "$OUT"; else pass "30 no merge without the setting, for class O or for ops/"; fi

d105_setup
touch "$FIX/gh/pr_merge_7.fail"
tick30 $((E0 + 200))
expect_first "30 a refused merge gives STOPPED MERGE_FAILED" "^STOPPED MERGE_FAILED #7 head $HEADSHA"
if [ "$(stget loop.prevPhase)" = AWAIT_MERGE ] && [ -z "$(stget pr.reviewStatus)" ]; then pass "30 a resume returns to AWAIT_MERGE, with no merge of the tick recorded"; else failc "30 a refused merge keeps AWAIT_MERGE to resume" "$OUT"; fi

# A review submitted with its findings between the reading of the comments and
# the window's check: the findings are read again before any merge.
d105_setup
mv "$FIX/gh/reviews_7" "$FIX/gh/reviews_7.1"
fx_comments reviews_7.2 403 "$BOT" "Here are some automated review suggestions for this pull request."
mv "$FIX/gh/review_comments_7" "$FIX/gh/review_comments_7.1"
fx_comments review_comments_7.2 303 "$BOT" '**![P1 Badge](badge)** Check the head before the merge'
tick30 $((E0 + 60))
expect_first "30 findings that arrive with the review's terminal sign give RUN fix, not a merge" '^RUN fix TASK-003 '
if ! merge_called && grep -q 'BOT_FINDING review comment 303' "$SD/log" && [ "$(stget loop.phase)" = CI ]; then pass "30 the late finding is a change request in the phase CI"; else failc "30 the late finding" "$OUT"; fi

# The Owner's /stop written between the first reading of the comments and the
# second, just before the merge, withholds it too.
d105_setup
mv "$FIX/gh/issue_comments_7" "$FIX/gh/issue_comments_7.1"
fx_comments issue_comments_7.2 213 yusufarst "/stop"
tick30 $((E0 + 200))
expect_first "30 a /stop read just before the merge withholds it: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
if ! merge_called && [ "$(stget owner.stopAfterMerge)" = 1 ]; then pass "30 the late /stop is recorded and nothing is merged"; else failc "30 the late /stop" "$OUT"; fi

d105_setup
rm -f -- "$FIX/gh/pr_7.2"
printf '1|Command contract|completed|failure|https://github.com/yusufarst/bantukasir/actions/runs/8813/job/1\n' > "$FIX/gh/check_runs_$HEADSHA"
tick30 $((E0 + 200))
expect_first "30 CI red again on the head in AWAIT_MERGE returns to the phase CI: RUN fix" '^RUN fix TASK-003 '
if ! merge_called && [ "$(stget loop.phase)" = CI ]; then pass "30 nothing is merged on a red CI"; else failc "30 a red CI in AWAIT_MERGE" "$OUT"; fi

d105_setup
stset task.id TASK-049
tick30 $((E0 + 200))
expect_first "30 TASK-049 stays the Owner's merge: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
reread
stset task.id TASK-050
tick30 $((E0 + 200))
expect_first "30 TASK-050 stays the Owner's merge: NOOP AWAIT_MERGE" '^NOOP AWAIT_MERGE pr=7$'
if merge_called; then failc "30 no merge of TASK-049 or TASK-050" "$OUT"; else pass "30 no merge of TASK-049 or TASK-050"; fi

summary
