#!/usr/bin/env bash
# scripts/executor/watch.sh: the watcher of the host session (D-104;
# EXECUTION_CONTEXT CE-03, CE-23, CE-41, CE-61, CE-62, CE-70, CE-72, CE-80).
# It uses Bash, Git, gh and the scripts of this folder only. It never calls a
# model and has no authority: it classifies with a dry run of the tick, and
# its exit wakes the host session, which runs the real tick.
#
# Usage: bash scripts/executor/watch.sh                 the watcher
#        bash scripts/executor/watch.sh --ack           acknowledges the last event
#        bash scripts/executor/watch.sh --status        read-only: the watcher, its heartbeat, the last event
#        bash scripts/executor/watch.sh --once          one classification, printed; no lock, no event
#        bash scripts/executor/watch.sh --test-wake <5..600>
# The watcher takes its lock (watch/lock/: pid, ctime, since): a lock whose
# recorded process still exists with that identifier and creation time gives
# WATCHER_RUNNING <pid> since <time>, a lock whose process is gone is taken
# over (TAKEOVER), and a lock whose liveness cannot be read gives
# WATCHER_RUNNING unverified. Then, at once and every 60 seconds, it writes its
# heartbeat and runs tick.sh --classify. A WAKE whose key is not acknowledged,
# was not emitted in the last 5 minutes and was emitted fewer than 3 times is
# emitted: watch/event gets "<epoch>|<key>|<line>", the log an EVENT, and the
# watcher prints exactly
#   BANTUKASIR WATCHER EVENT <key> — follow EXECUTION_CONTEXT CE-03 — <line>
# and exits. A key emitted 3 times without an acknowledgment is emitted once
# more as WATCHER_STALL:REDELIVERY:<key>, then never again. It emits a stall of
# its own after 30 polls in a row of NOOP GITHUB_UNAVAILABLE or NOOP INDEX_LOCK
# (WATCHER_STALL:<code>:<epoch of the first>), 60 polls of NOOP
# DEVELOP_CI_PENDING for one merge (WATCHER_STALL:DEVELOP_CI_PENDING:<sha>) and
# 3 polls of output that is neither QUIET nor WAKE
# (WATCHER_STALL:UNPARSEABLE:<epoch>); the first NOOP DEVELOP_CI_PENDING of a
# merge is logged as PR_MERGED, without a wake. It prints nothing else.
# --ack acknowledges the event of watch/event only on evidence: a real tick
# after it (last-tick), whose first line is not NOOP GITHUB_UNAVAILABLE, NOOP
# INDEX_LOCK, NOOP LOCKED or STOPPED TICK_ERROR; for a RUN, the release of the
# executor lock by the delegation's own token through state.sh; and, except for
# a stop or a stall, a classification that could check the state (not GitHub
# unavailable, an index lock, a held lock or an internal error) and no longer
# gives that key. Otherwise
# the event stays redeliverable. It prints NO_EVENT, ALREADY_ACKED <key>,
# NOT_ACKED <key> <reason> or ACKED <key>.
# --test-wake takes the lock as the watcher does, sleeps, prints
#   BANTUKASIR WATCHER EVENT TEST:<epoch> — follow EXECUTION_CONTEXT CE-03 — test wake after <n> s
# releases the lock and exits; it runs no tick.
# It writes only the folder bantukasir-executor/watch/ (lock/, heartbeat, event,
# ack, the last 500 keys, and log: ARMED, EVENT, ACK, NOT_ACKED, PR_MERGED,
# STALL, TAKEOVER, EXIT), never the state, the log, the lock, requests,
# ignored or review/ of the executor.
# In self-test mode only (EXECUTOR_SELFTEST=1 and EXECUTOR_FIXTURES):
# EXECUTOR_POLL_SECONDS, EXECUTOR_WATCH_STALL_POLLS (the 30; the 60 is twice
# it), EXECUTOR_WATCH_MAX_POLLS (then WATCHER_MAX_POLLS ends the loop),
# EXECUTOR_NOW, and the fixture watch/classify in place of the tick.
# Exit codes: 0 always; 2 usage.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/lib.sh"

W="$WATCH_DIR"
WLOCK="$W/lock"
POLL=60 STALL_POLLS=30 MAX_POLLS="" REPEAT_SECONDS=300 MAX_EMITS=3 ACK_KEEP=500
if selftest_mode; then
    if is_int "${EXECUTOR_POLL_SECONDS:-}"; then POLL="$EXECUTOR_POLL_SECONDS"; fi
    if is_int "${EXECUTOR_WATCH_STALL_POLLS:-}"; then STALL_POLLS="$EXECUTOR_WATCH_STALL_POLLS"; fi
    if is_int "${EXECUTOR_WATCH_MAX_POLLS:-}"; then MAX_POLLS="$EXECUTOR_WATCH_MAX_POLLS"; fi
fi
OWN=0 MY_PID="" MY_CT="" EXIT_WHY="ended"

usage() { echo "USAGE watch.sh [--ack | --status | --once | --test-wake <5..600>]"; exit 2; }
wlog() { mkdir -p "$W" && printf '%s %s\n' "$(stamp)" "$*" >> "$W/log"; }
iso() { date -u -d "@$(( $1 + 25200 ))" +%Y-%m-%dT%H:%M:%S+07:00 2> /dev/null || printf '%s' "$1"; }
# ctime_of <pid>: the creation time that proc_list gives the process, or nothing.
ctime_of() { { proc_list 2> /dev/null || true; } | awk -F'|' -v p="$1" '$1 == p && !d { print $4; d = 1 }'; }

# ---------------------------------------------------------------- the classification
# classify: one line of tick.sh --classify (or of the fixture watch/classify
# in self-test mode); several lines are returned as they are, unparseable.
classify() {
    if selftest_mode && { [ -e "$(fx watch/classify)" ] || [ -e "$(fx watch/classify).1" ]; }; then
        fixture_out watch/classify
        return 0
    fi
    bash "$HERE/tick.sh" --classify 2> /dev/null || true
}

# ---------------------------------------------------------------- the watcher lock
# watcher_state: "running <pid> <since>", "dead <pid> <since>", "none", or
# "unverified <pid>" when the process list cannot be read or a lock is still
# being written.
watcher_state() {
    local pid ct since list mt age
    [ -d "$WLOCK" ] || { echo none; return 0; }
    pid="$(cat "$WLOCK/pid" 2> /dev/null || true)"
    ct="$(cat "$WLOCK/ctime" 2> /dev/null || true)"
    since="$(cat "$WLOCK/since" 2> /dev/null || true)"
    if [ -z "$pid" ] || [ -z "$ct" ]; then
        # A lock is written right after its mkdir: one still incomplete after
        # a minute was left by a watcher that ended in between.
        mt="$(stat -c %Y "$WLOCK" 2> /dev/null || date +%s)"
        age=$(( $(date +%s) - mt ))
        if [ "$age" -lt 60 ]; then echo "unverified ${pid:--}"; else echo "dead ${pid:--} ${since:--} INCOMPLETE"; fi
        return 0
    fi
    list="$(proc_list 2> /dev/null)" && [ -n "$list" ] || { echo "unverified $pid"; return 0; }
    if printf '%s\n' "$list" | awk -F'|' -v p="$pid" -v c="$ct" '$1 == p && $4 == c { f = 1 } END { exit !f }'; then
        echo "running $pid $since"
    else
        echo "dead $pid $since"
    fi
}
# take_watch_lock <mode>: 0 when this process holds the lock; otherwise it has
# printed WATCHER_RUNNING ... (or logged an unreadable identity) and returns 1.
take_watch_lock() {
    local i ws st op oc os stale
    mkdir -p "$W"
    for i in 1 2 3; do
        if mkdir "$WLOCK" 2> /dev/null; then
            OWN=1
            MY_PID="$(self_pid)"
            MY_CT="$(ctime_of "$MY_PID")"
            if [ -z "$MY_CT" ]; then
                # Without its own creation time no other watcher could check
                # it: it gives the lock back and ends without a line.
                OWN=0
                rm -rf -- "$WLOCK"
                wlog "EXIT $1: the creation time of process $MY_PID cannot be read"
                return 1
            fi
            printf '%s\n' "$MY_PID" > "$WLOCK/pid"
            printf '%s\n' "$MY_CT" > "$WLOCK/ctime"
            now > "$WLOCK/since"
            wlog "ARMED $1 pid=$MY_PID ctime=$MY_CT"
            return 0
        fi
        # The record as found, compared below with the lock that is moved away.
        op="$(cat "$WLOCK/pid" 2> /dev/null || true)"
        oc="$(cat "$WLOCK/ctime" 2> /dev/null || true)"
        ws="$(watcher_state)"
        read -r st _ os _ <<< "$ws"
        case "$st" in
            running) echo "WATCHER_RUNNING $op since $(iso "${os:-0}")"; return 1 ;;
            unverified) echo "WATCHER_RUNNING unverified"; return 1 ;;
            dead)
                stale="$W/lock.stale.$$"
                rm -rf -- "$stale"
                if mv "$WLOCK" "$stale" 2> /dev/null; then
                    # Another watcher may have replaced the dead lock meanwhile:
                    # only the lock that was found dead is taken over.
                    if [ "$(cat "$stale/pid" 2> /dev/null || true)" = "$op" ] && [ "$(cat "$stale/ctime" 2> /dev/null || true)" = "$oc" ]; then
                        wlog "TAKEOVER $ws: process $op with creation time ${oc:--} no longer exists"
                        rm -rf -- "$stale"
                    else
                        mv "$stale" "$WLOCK" 2> /dev/null || rm -rf -- "$stale"
                    fi
                fi
                ;;
        esac
    done
    echo "WATCHER_RUNNING unverified"
    return 1
}
release_watch_lock() {
    [ "$OWN" = 1 ] || return 0
    OWN=0
    if [ "$(cat "$WLOCK/pid" 2> /dev/null || true)" = "$MY_PID" ] && [ "$(cat "$WLOCK/ctime" 2> /dev/null || true)" = "$MY_CT" ]; then rm -rf -- "$WLOCK"; fi
}
on_exit() {
    set +e
    if [ "$OWN" = 1 ]; then
        release_watch_lock
        wlog "EXIT $EXIT_WHY"
    fi
}

# ---------------------------------------------------------------- events
acked() { [ -f "$W/ack" ] && grep -qxF -- "$1" "$W/ack"; }
# last_emit <key>, emit_count <key>: from the EVENT lines of the log,
# "<stamp> EVENT <epoch> <key> <line>".
last_emit() { if [ -f "$W/log" ]; then awk -v k="$1" '$2 == "EVENT" && $4 == k { e = $3 } END { print e + 0 }' "$W/log"; else echo 0; fi; }
emit_count() { if [ -f "$W/log" ]; then awk -v k="$1" '$2 == "EVENT" && $4 == k { n++ } END { print n + 0 }' "$W/log"; else echo 0; fi; }
emit() {  # key line
    local e
    e="$(now)"
    printf '%s|%s|%s\n' "$e" "$1" "$2" > "$W/event.$$"
    mv -f -- "$W/event.$$" "$W/event"
    wlog "EVENT $e $1 $2"
    EXIT_WHY="event $1"
    printf 'BANTUKASIR WATCHER EVENT %s — follow EXECUTION_CONTEXT CE-03 — %s\n' "$1" "$2"
    exit 0
}
# maybe_emit <key> <line>: emits unless the key is acknowledged, was emitted
# less than 5 minutes ago, or was emitted MAX_EMITS times (then one stall).
maybe_emit() {
    local k="$1" line="$2" last n lim="$MAX_EMITS"
    if acked "$k"; then return 0; fi
    last="$(last_emit "$k")"
    if [ "$last" -gt 0 ] && [ $(( $(now) - last )) -lt "$REPEAT_SECONDS" ]; then return 0; fi
    case "$k" in WATCHER_STALL:REDELIVERY:*) lim=1 ;; esac
    n="$(emit_count "$k")"
    if [ "$n" -ge "$lim" ]; then
        [ "$lim" = 1 ] || maybe_emit "WATCHER_STALL:REDELIVERY:$k" "$k was emitted $n times without an acknowledgment; the last: $line"
        return 0
    fi
    case "$k" in WATCHER_STALL:*) wlog "STALL $k" ;; esac
    emit "$k" "$line"
}

# ---------------------------------------------------------------- the watcher
watch_loop() {
    local npoll=0 last="-" res rest key line bad=0 badfirst="" stn=0 stcode="" stfirst="" dvn=0 dvsha="" sha pr code
    while :; do
        npoll=$((npoll + 1))
        printf '%s|%s\n' "$(now)" "$last" > "$W/heartbeat"
        res="$(classify)"
        last="$res"
        printf '%s|%s\n' "$(now)" "$res" > "$W/heartbeat"
        case "$res" in
            *$'\n'*) res="UNPARSEABLE" ;;
        esac
        case "$res" in
            "WAKE "*)
                bad=0 stn=0 dvn=0
                rest="${res#WAKE }"
                key="${rest%% *}"
                line=""
                [ "$rest" = "$key" ] || line="${rest#* }"
                maybe_emit "$key" "$line"
                ;;
            "QUIET "*)
                bad=0
                line="${res#QUIET }"
                case "$line" in
                    "NOOP GITHUB_UNAVAILABLE"* | "NOOP INDEX_LOCK"*)
                        dvn=0
                        code="${line#NOOP }"
                        code="${code%% *}"
                        if [ "$stn" = 0 ]; then stcode="$code"; stfirst="$(now)"; fi
                        stn=$((stn + 1))
                        if [ "$stn" -ge "$STALL_POLLS" ]; then maybe_emit "WATCHER_STALL:$stcode:$stfirst" "$line"; fi
                        ;;
                    "NOOP DEVELOP_CI_PENDING merge "*)
                        stn=0
                        read -r _ _ _ sha _ pr _ <<< "$line"
                        pr="${pr#\#}"
                        if [ "$sha" != "$dvsha" ]; then dvsha="$sha"; dvn=0; fi
                        dvn=$((dvn + 1))
                        if ! { [ -f "$W/log" ] && grep -q " PR_MERGED $pr $sha\$" "$W/log"; }; then wlog "PR_MERGED $pr $sha"; fi
                        if [ "$dvn" -ge $((2 * STALL_POLLS)) ]; then maybe_emit "WATCHER_STALL:DEVELOP_CI_PENDING:$sha" "$line"; fi
                        ;;
                    *) stn=0 dvn=0 ;;
                esac
                ;;
            *)
                stn=0 dvn=0
                if [ "$bad" = 0 ]; then badfirst="$(now)"; fi
                bad=$((bad + 1))
                if [ "$bad" -ge 3 ]; then maybe_emit "WATCHER_STALL:UNPARSEABLE:$badfirst" "$(printf '%s' "$res" | head -n 1 | cut -c1-200)"; fi
                ;;
        esac
        if [ -n "$MAX_POLLS" ] && [ "$npoll" -ge "$MAX_POLLS" ]; then
            EXIT_WHY="WATCHER_MAX_POLLS after $npoll polls"
            echo "WATCHER_MAX_POLLS"
            exit 0
        fi
        sleep "$POLL" &
        wait $!
    done
}

# ---------------------------------------------------------------- --ack
not_acked() {  # key reason
    wlog "NOT_ACKED $1 $2"
    echo "NOT_ACKED $1 $2"
    exit 0
}
ack() {
    local e k line lt lte ltl tok cur
    [ -s "$W/event" ] || { echo "NO_EVENT"; exit 0; }
    IFS='|' read -r e k line < "$W/event"
    if acked "$k"; then echo "ALREADY_ACKED $k"; exit 0; fi
    lt="$(cat "$LAST_TICK_FILE" 2> /dev/null || true)"
    lte="${lt%%|*}"
    ltl="${lt#*|}"
    if ! is_int "$lte" || ! is_int "$e" || [ "$lte" -lt "$e" ]; then not_acked "$k" "no real tick since the event"; fi
    case "$ltl" in
        "NOOP GITHUB_UNAVAILABLE"* | "NOOP INDEX_LOCK"* | "NOOP LOCKED"* | "STOPPED TICK_ERROR"* | "STOPPED OWNER_STOP kept over TICK_ERROR"*) not_acked "$k" "the tick printed $ltl" ;;
    esac
    case "$ltl" in
        "RUN "*)
            # The delegation completed only when its holder released the
            # executor lock through state.sh with the tick's own token.
            tok="$(printf '%s\n' "$ltl" | sed -n 's/.* token=\([0-9a-f]\{32\}\)$/\1/p')"
            [ -n "$tok" ] || not_acked "$k" "the tick printed RUN without a token"
            [ "$(lock_read token)" != "$tok" ] || not_acked "$k" "the delegation of token ${tok:0:8} still holds the executor lock"
            { [ -f "$LOG_FILE" ] && grep -q " LOCK_RELEASED token=$tok at " "$LOG_FILE"; } || not_acked "$k" "the delegation of token ${tok:0:8} did not end through state.sh"
            ;;
    esac
    case "$k" in
        STOPPED_NEW:* | WATCHER_STALL:*) ;;
        *)
            # An event that the tick did not consume stays redeliverable, and so
            # does one that a classification cannot check now.
            cur="$(classify)"
            case "$cur" in "WAKE $k" | "WAKE $k "*) not_acked "$k" "the event is still current: ${cur#WAKE "$k" }" ;; esac
            case "$cur" in
                *$'\n'*) cline="" ;;
                "QUIET "*) cline="${cur#QUIET }" ;;
                "WAKE "*) cline="${cur#WAKE * }" ;;
                *) cline="" ;;
            esac
            case "$cline" in
                "" | "NOOP GITHUB_UNAVAILABLE"* | "NOOP INDEX_LOCK"* | "NOOP LOCKED"* | "STOPPED TICK_ERROR"* | "STOPPED GITHUB_UNAVAILABLE"* | "STOPPED INDEX_LOCK"* | "STOPPED LOCK_UNVERIFIED"* | "STOPPED OWNER_STOP kept over TICK_ERROR"* | "STOPPED OWNER_STOP kept over GITHUB_UNAVAILABLE"* | "STOPPED OWNER_STOP kept over INDEX_LOCK"*)
                    not_acked "$k" "the event cannot be checked now: $(printf '%s' "${cur:-no classification}" | head -n 1)"
                    ;;
            esac
            ;;
    esac
    { cat "$W/ack" 2> /dev/null || true; printf '%s\n' "$k"; } | tail -n "$ACK_KEEP" > "$W/ack.$$"
    mv -f -- "$W/ack.$$" "$W/ack"
    wlog "ACK $k event=$e tick=$lte $ltl"
    echo "ACKED $k"
}

# ---------------------------------------------------------------- --status
status() {
    local ws st p s hb he hc e k line
    ws="$(watcher_state)"
    read -r st p s _ <<< "$ws"
    case "$st" in
        running) echo "WATCHER running $p since $(iso "${s:-0}")" ;;
        dead) echo "WATCHER dead $p" ;;
        unverified) echo "WATCHER unverified $p" ;;
        *) echo "WATCHER none" ;;
    esac
    hb="$(cat "$W/heartbeat" 2> /dev/null || true)"
    if [ -n "$hb" ]; then
        he="${hb%%|*}"
        hc="${hb#*|}"
        if is_int "$he"; then echo "HEARTBEAT $(( $(now) - he )) s ago"; else echo "HEARTBEAT unreadable"; fi
        echo "CLASSIFICATION $hc"
    else
        echo "HEARTBEAT none"
    fi
    if [ -s "$W/event" ]; then
        IFS='|' read -r e k line < "$W/event"
        echo "EVENT $(iso "${e:-0}") $k $line"
        if acked "$k"; then echo "ACKNOWLEDGED yes"; else echo "ACKNOWLEDGED no"; fi
    else
        echo "EVENT none"
    fi
}

# ---------------------------------------------------------------- main
case "${1:-}" in
    "")
        [ $# -eq 0 ] || usage
        exec 2> /dev/null
        trap on_exit EXIT
        trap 'EXIT_WHY="signal"; exit 0' INT TERM HUP
        take_watch_lock watch || exit 0
        watch_loop
        ;;
    --ack) [ $# -eq 1 ] || usage; ack ;;
    --status) [ $# -eq 1 ] || usage; status ;;
    --once) [ $# -eq 1 ] || usage; classify ;;
    --test-wake)
        [ $# -eq 2 ] && is_int "$2" && [ "$2" -ge 5 ] && [ "$2" -le 600 ] || usage
        trap on_exit EXIT
        trap 'EXIT_WHY="signal"; exit 0' INT TERM HUP
        take_watch_lock "test-wake $2" || exit 0
        sleep "$2" &
        wait $!
        e="$(now)"
        wlog "EVENT $e TEST:$e test wake after $2 s"
        EXIT_WHY="test wake after $2 s"
        printf 'BANTUKASIR WATCHER EVENT TEST:%s — follow EXECUTION_CONTEXT CE-03 — test wake after %s s\n' "$e" "$2"
        ;;
    *) usage ;;
esac
exit 0
