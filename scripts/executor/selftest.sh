#!/usr/bin/env bash
# scripts/executor/selftest.sh: the self-test of continuous execution (D-101;
# EXECUTION_CONTEXT section 19: CE-10 to CE-13, CE-20 to CE-24, CE-30 to CE-32,
# CE-40 to CE-43, CE-61, CE-62, CE-70 to CE-72).
#
# Usage: bash scripts/executor/selftest.sh          the cases 1 to 14, in a sandbox
#        bash scripts/executor/selftest.sh --host   the host probes (a) to (e), on Windows
# Without --host it works in a temporary directory outside the repository: a
# bare origin and a clone, with fixture answers for GitHub and, where a test
# cannot create the case, for processes (EXECUTOR_SELFTEST=1,
# EXECUTOR_FIXTURES). The identity is set only in the sandbox's own repository
# configuration. It never touches the real repository, its state, GitHub or a
# Docker volume (the guard's cases run the project image on the sandbox,
# mounted read-only, without network). Secret-looking and personal-looking
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
# runreal: the same with the real HOME (Docker reads its configuration there).
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
mklock "" "$NOW" "$NOW"
run state.sh phase AWAIT_MERGE --token selftesttoken
[ "$(stget loop.phase)" = AWAIT_MERGE ] && [ ! -e "$SD/lock" ] && pass "1 AWAIT_MERGE releases the lock" || failc "1 AWAIT_MERGE releases the lock" "$OUT"
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
[ "$RC" = 0 ] && [ "$(stget loop.phase)" = AWAIT_MERGE ] && [ "$(stget pr.lastIssueCommentId)" = 205 ] && [ "$(stget pr.lastReviewCommentId)" = 301 ] && [ "$(stget pr.lastReviewId)" = 401 ] && [ "$(stget task.agent)" = bk-executor-xhigh ] && pass "1 adopt works without a state and records the comment identifiers" || failc "1 adopt without a state" "$OUT"
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
if first | grep -qE '^RUN fix TASK-003 agent=bk-executor-xhigh review=bk-reviewer-xhigh verify=bk-reviewer-high effort=xhigh token=[0-9a-f]{32}$' && [[ "$OUT" == *"$body"* ]] && printf '%s\n' "$OUT" | grep -q 'someone-else'; then pass "2 a multi-line Owner comment gives RUN fix with its text intact"; else failc "2 RUN fix" "$OUT"; fi
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
expect_first "3 then success gives RUN start TASK-004 with the agents of the table" '^RUN start TASK-004 agent=bk-executor-xhigh review=bk-reviewer-xhigh verify=bk-reviewer-high effort=xhigh token=[0-9a-f]{32}$'
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
fx_comments issue_comments_7 601 yusufarst "/effort high"
run tick.sh
M="$(simulate_merge "$OEMAIL")"
merged_fixtures "$M" completed success
run tick.sh
expect_first "3 /effort high sets the executor bk-executor-high and keeps the review agents of the table" '^RUN start TASK-004 agent=bk-executor-high review=bk-reviewer-xhigh verify=bk-reviewer-high effort=high '
if [ -z "$(stget owner.effortOverride)" ]; then expect_out "3 the /effort override is then cleared and its source named" "^Effort: high, from the Owner's /effort comment, issue comment 601$"; else failc "3 the /effort override is then cleared and its source named" "$OUT"; fi

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
OUT="$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && select_task "$real_plan")"
case "$OUT" in "OK TASK-004|"*) pass "7 the real plan selects TASK-004" ;; *) failc "7 the real plan selects TASK-004" "$OUT" ;; esac
for spec in "TASK-004 bk-executor-xhigh bk-reviewer-xhigh bk-reviewer-high" "TASK-005 bk-executor-high bk-reviewer-high bk-reviewer-high" "TASK-021 bk-executor-max bk-reviewer-max bk-reviewer-xhigh" "TASK-044 bk-executor-high bk-reviewer-high bk-reviewer-high" "TASK-047 bk-executor-medium bk-reviewer-high bk-reviewer-high"; do
    set -- $spec
    got="$(cd "$REPO_ROOT" && . "$HERE/lib.sh" && cl="$(plan_rows "$real_plan" | awk -F'|' -v t="$1" '$1 == t { print $4 }')" && effort_row "$real_ctx" "$1" "$cl" | cut -d'|' -f2-)"
    [ "$got" = "$2|$3|$4" ] && pass "7 $1: $2, $3, $4" || failc "7 $1 agents" "$got"
done
set --

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
command -v docker > /dev/null 2>&1 && docker image inspect bantukasir-app:local > /dev/null 2>&1 || failc "10 the project image bantukasir-app:local is needed for the guard's cases"
setup
gbranch() { g switch -q -c "task/TASK-004-$1" develop; }
gbranch clean
printf 'clean text\n' > "$CL/clean.txt"
g add clean.txt
g commit -q -m "TASK-004: a clean change"
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
Bash(bash scripts/executor/commit.sh*)
Bash(bash scripts/executor/push.sh*)
Bash(bash scripts/executor/state.sh resume*)
Bash(bash scripts/executor/state.sh unlock*)
Bash(bash scripts/executor/state.sh adopt*)
Bash(rm *)
Bash(mv *)
ASK
[ -z "$miss" ] && pass "14 settings: every ask entry of the commit, push, resume, unlock, adopt, rm and mv rules" || failc "14 settings ask:$miss"
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

summary
