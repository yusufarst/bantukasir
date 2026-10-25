#!/usr/bin/env bash
# scripts/executor/state.sh: the executor state and its lock (D-101, D-104;
# EXECUTION_CONTEXT CE-20 to CE-24, CE-41, CE-71, CE-72, CE-82).
#
# Usage: bash scripts/executor/state.sh <subcommand>
#   show                                       the state, the lock and the log's tail
#   heartbeat --token T                        refreshes the heartbeat
#   phase <BUILD|REVIEW|PUBLISH|CI|AWAIT_MERGE> --token T
#                                              sets the phase; CI and AWAIT_MERGE release the lock.
#                                              both need the pull request (REFUSED NO_PR keeps the
#                                              lock); CI, when ci.head is not pr.head, sets ci.head
#                                              and ci.waitStart, the start of the 60 minutes, and
#                                              AWAIT_MERGE refuses CI_NOT_GREEN unless the required
#                                              job passed on pr.head (CE-41); both end the fix
#                                              cycle that tick.sh recorded (ci.fixToken)
#   stop <REASON> --token T [--detail "<text>"]  records STOPPED and releases the lock
#   resume [--note "<text>"] [--continue] [--accept-merge <sha>]
#                                              the Owner's resume (an ask rule)
#   unlock                                     the Owner's release of the lock (an ask rule)
#   adopt --pr N --kind ops|task [--task TASK-NNN]
#                                              the Owner's adoption of a pull request (an ask rule);
#                                              from OWNER_STOP it keeps the stop for the merge
#   probe-holder                               read-only: the holder record and its state
#   probe-invocation                           read-only: the invocation identity
# A release by the holder of the lock (phase CI or AWAIT_MERGE, stop) logs
# "LOCK_RELEASED token=<token> at <phase>": the evidence that a delegation
# ended through its scripts, which watch.sh --ack requires (D-104).
# Exit codes: 0 done; 1 refused, NOT_STOPPED, NO_LOCK or LOCK_LOST (the calling
# session stops at once on LOCK_LOST); 2 usage.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

usage() { echo "USAGE $*"; exit 2; }
need_token() {
    [ -n "${TOKEN:-}" ] || usage "--token is required"
    lock_check "$TOKEN" || { echo "LOCK_LOST"; exit 1; }
}

cmd="${1:-}"
[ $# -gt 0 ] && shift
TOKEN="" DETAIL="" NOTE="" CONT=0 ACCEPT="" PR="" KIND="" TASK="" ARG=""
while [ $# -gt 0 ]; do
    case "$1" in
        --token) TOKEN="${2:-}"; shift 2 ;;
        --detail) DETAIL="${2:-}"; shift 2 ;;
        --note) NOTE="${2:-}"; shift 2 ;;
        --continue) CONT=1; shift ;;
        --accept-merge) ACCEPT="${2:-}"; shift 2 ;;
        --pr) PR="${2:-}"; shift 2 ;;
        --kind) KIND="${2:-}"; shift 2 ;;
        --task) TASK="${2:-}"; shift 2 ;;
        -*) usage "unknown option $1" ;;
        *) [ -z "$ARG" ] || usage "one argument only"; ARG="$1"; shift ;;
    esac
done

case "$cmd" in
    show)
        echo "STATE $STATE_FILE"
        if [ -f "$STATE_FILE" ]; then
            git config -f "$STATE_FILE" --list | while IFS= read -r l; do
                case "$l" in *=b64:*) printf '%s=%s\n' "${l%%=*}" "$(b64d "${l#*=b64:}")" ;; *) printf '%s\n' "$l" ;; esac
            done
        else
            echo "(no state)"
        fi
        if lock_exists; then
            echo "LOCK token=$(lock_read token | cut -c1-8)... since=$(lock_read since) heartbeat=$(lock_read heartbeat) boot=$(lock_read boot)"
            sed 's/^/  holder /' "$LOCK_DIR/holder" 2> /dev/null || true
        else
            echo "LOCK none"
        fi
        if [ -f "$LOG_FILE" ]; then echo "LOG (last 10)"; tail -n 10 "$LOG_FILE"; fi
        ;;
    heartbeat)
        need_token
        lock_heartbeat
        echo "HEARTBEAT $(lock_read heartbeat)"
        ;;
    phase)
        case "$ARG" in BUILD | REVIEW | PUBLISH | CI | AWAIT_MERGE) ;; *) usage "phase BUILD|REVIEW|PUBLISH|CI|AWAIT_MERGE" ;; esac
        need_token
        case "$ARG" in CI | AWAIT_MERGE) [ -n "$(st_get pr.number)" ] && [ -n "$(st_get pr.head)" ] || { echo "REFUSED NO_PR the state has no pull request; the lock is kept"; exit 1; } ;; esac
        if [ "$ARG" = CI ]; then
            # The tick follows CI without the lock (CE-41): the 60 minutes
            # start with each new head.
            if [ "$(st_get ci.head)" != "$(st_get pr.head)" ]; then
                st_set ci.head "$(st_get pr.head)"
                st_set ci.waitStart "$(now)"
            fi
        fi
        if [ "$ARG" = AWAIT_MERGE ]; then
            # AWAIT_MERGE only for the head on which the required job passed
            # (CE-41, D-104).
            res="$(ci_result "$(st_get pr.head)")" || { echo "REFUSED GITHUB_UNAVAILABLE the lock is kept"; exit 1; }
            case "$res" in "PASS "*) ;; *) echo "REFUSED CI_NOT_GREEN $(st_get pr.head): ${res%%|*}; the lock is kept"; exit 1 ;; esac
        fi
        st_set loop.phase "$ARG"
        lock_heartbeat
        log_event "PHASE $ARG"
        case "$ARG" in
            CI | AWAIT_MERGE)
                # The end of a run, or of a fix cycle (tick.sh run_line fix).
                st_unset ci.fixToken
                st_unset ci.fixReason
                st_unset ci.fixRequests
                lock_release
                log_event "LOCK_RELEASED token=$TOKEN at $ARG"
                ;;
        esac
        echo "PHASE $ARG"
        ;;
    stop)
        [ -n "$ARG" ] || usage "stop <REASON>"
        need_token
        ph="$(st_get loop.phase)"
        [ "$ph" = STOPPED ] || st_set loop.prevPhase "${ph:-SELECT}"
        st_set loop.phase STOPPED
        st_set loop.stopReason "$ARG"
        st_set_text loop.stopDetail "$DETAIL"
        lock_release
        log_event "STOPPED $ARG $DETAIL"
        log_event "LOCK_RELEASED token=$TOKEN at STOPPED $ARG"
        echo "STOPPED $ARG${DETAIL:+ $DETAIL}"
        ;;
    resume)
        if [ "$(st_get loop.phase)" != STOPPED ]; then echo "NOT_STOPPED"; exit 1; fi
        reason="$(st_get loop.stopReason)"
        if [ -n "$ACCEPT" ]; then
            case "$reason" in MERGE_AUTHOR | MERGE_CONTENT) ;; *) echo "REFUSED ACCEPT_MERGE the stop is $reason, not MERGE_AUTHOR or MERGE_CONTENT"; exit 1 ;; esac
            pending="$(st_get merge.pendingSha)"
            if [ -z "$pending" ] || [ "$ACCEPT" != "$pending" ]; then echo "REFUSED ACCEPT_MERGE the stop concerns ${pending:-no merge commit}"; exit 1; fi
            # The acceptance waives only that check; the tick completes the
            # merge's verification (local develop, CI on develop), so no
            # session takes the lock here.
            if [ "$CONT" = 1 ]; then echo "REFUSED ACCEPT_MERGE --continue: the next tick completes the merge's verification"; exit 1; fi
            accepted=""
            [ "$(st_get merge.acceptedSha)" = "$ACCEPT" ] && accepted="$(st_get merge.acceptedByOwner)"
            case " $accepted " in *" $reason "*) ;; *) accepted="${accepted:+$accepted }$reason" ;; esac
            st_set merge.acceptedSha "$ACCEPT"
            st_set merge.acceptedByOwner "$accepted"
            st_unset merge.pendingSha
            st_set loop.prevPhase AWAIT_MERGE
            resume_state "$NOTE"
            log_event "MERGE_ACCEPTED_BY_OWNER $ACCEPT after $reason; the verification continues"
        else
            if [ "$CONT" = 1 ] && lock_exists; then echo "REFUSED LOCKED"; exit 1; fi
            resume_state "$NOTE"
            log_event "RESUMED after $reason${NOTE:+ with a note}"
        fi
        echo "RESUMED phase=$(st_get loop.phase)"
        if [ "$CONT" = 1 ]; then
            tok="$(lock_take)" || { echo "REFUSED LOCKED"; exit 1; }
            echo "TOKEN $tok"
        fi
        ;;
    unlock)
        if ! lock_exists; then echo "NO_LOCK"; exit 1; fi
        echo "HOLDER since=$(lock_read since) heartbeat=$(lock_read heartbeat) boot=$(lock_read boot)"
        sed 's/^/  /' "$LOCK_DIR/holder" 2> /dev/null || true
        hs="$(holder_state "$(lock_read since)" "$(lock_read boot)")"
        echo "HOLDER_STATE $hs"
        log_event "UNLOCKED_BY_OWNER $hs holder=[$(tr '\n' ';' < "$LOCK_DIR/holder" 2> /dev/null || true)]"
        lock_release
        echo "UNLOCKED"
        ;;
    adopt)
        is_int "$PR" || usage "adopt --pr N --kind ops|task [--task TASK-NNN]"
        case "$KIND" in ops | task) ;; *) usage "--kind ops|task" ;; esac
        ph="$(st_get loop.phase)"
        if lock_exists; then echo "REFUSED LOCKED"; exit 1; fi
        case "$ph" in '' | STOPPED | SELECT) ;; *) echo "REFUSED PHASE $ph"; exit 1 ;; esac
        br="$(cur_branch)"
        info="$(gh_pr "$PR")" || { echo "REFUSED GITHUB_UNAVAILABLE"; exit 1; }
        { read -r state; read -r _; read -r _; read -r hsha; read -r href; read -r bref; read -r author; read -r hrepo; read -r _; } <<< "$info"
        [ "$state" = open ] || { echo "REFUSED NOT_OPEN #$PR is $state"; exit 1; }
        [ "$href" = "$br" ] && [ "$hrepo" = "$REPO" ] || { echo "REFUSED BRANCH #$PR is from $hrepo:$href, the current branch is $br"; exit 1; }
        [ "$bref" = "$BASE" ] || { echo "REFUSED BASE #$PR is into $bref"; exit 1; }
        [ "$author" = "$OWNER_LOGIN" ] || { echo "REFUSED AUTHOR #$PR is by $author"; exit 1; }
        [ "$hsha" = "$(git rev-parse HEAD)" ] || { echo "REFUSED HEAD #$PR head $hsha differs from HEAD"; exit 1; }
        case "$KIND:$br" in ops:ops/* | task:task/TASK-*) ;; *) echo "REFUSED KIND $KIND does not match $br"; exit 1 ;; esac
        # The Owner's /stop holds through the merge of a pull request adopted
        # in it: the loop stops again until he resumes it (CE-82, D-104).
        keep=0
        if [ "$ph" = STOPPED ] && [ "$(st_get loop.stopReason)" = OWNER_STOP ]; then keep=1; fi
        adopt_state "$PR" "$br" "$hsha" || { echo "REFUSED GITHUB_UNAVAILABLE"; exit 1; }
        if [ -n "$TASK" ]; then st_set task.id "$TASK"; fi
        if [ "$keep" = 1 ]; then
            st_set owner.stopAfterMerge 1
            log_event "OWNER_STOP_KEPT pr=$PR"
        fi
        log_event "ADOPTED pr=$PR kind=$KIND branch=$br head=$hsha"
        kept=""
        [ "$keep" = 0 ] || kept=" stop kept"
        echo "ADOPTED pr=$PR phase=AWAIT_MERGE head=$hsha comments<=$(st_get pr.lastIssueCommentId)/$(st_get pr.lastReviewCommentId)/$(st_get pr.lastReviewId)$kept"
        ;;
    probe-holder)
        rec="$(holder_record)"
        tmp="$(mktemp)"
        trap 'rm -f -- "$tmp"' EXIT
        printf '%s\n' "$rec" > "$tmp"
        echo "SELF $(self_pid)"
        echo "HOLDER_RECORD"
        printf '%s\n' "${rec:-(empty)}" | sed 's/^/  /'
        b="$(boot_epoch || echo unreadable)"
        echo "BOOT $b ($(date -u -d "@$(( ${b:-0} + 25200 ))" +%Y-%m-%dT%H:%M:%S+07:00 2> /dev/null || echo '?'))"
        if [ -n "$rec" ]; then echo "HOLDER_STATE $(holder_state "$(now)" "$b" "$tmp")"; else echo "HOLDER_STATE UNKNOWN NO_RECORD"; fi
        ;;
    probe-invocation)
        echo "INVOCATION $(invocation_id)"
        ;;
    *)
        usage "show|heartbeat|phase|stop|resume|unlock|adopt|probe-holder|probe-invocation"
        ;;
esac
