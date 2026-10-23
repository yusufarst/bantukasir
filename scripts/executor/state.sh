#!/usr/bin/env bash
# scripts/executor/state.sh: the executor state and its lock (D-101,
# EXECUTION_CONTEXT CE-20 to CE-24, CE-71, CE-72, CE-82).
#
# Usage: bash scripts/executor/state.sh <subcommand>
#   show                                       the state, the lock and the log's tail
#   heartbeat --token T                        refreshes the heartbeat
#   phase <BUILD|REVIEW|PUBLISH|CI|AWAIT_MERGE> --token T
#                                              sets the phase; AWAIT_MERGE releases the lock
#   stop <REASON> --token T [--detail "<text>"]  records STOPPED and releases the lock
#   resume [--note "<text>"] [--continue] [--accept-merge <sha>]
#                                              the Owner's resume (an ask rule)
#   unlock                                     the Owner's release of the lock (an ask rule)
#   adopt --pr N --kind ops|task [--task TASK-NNN]
#                                              the Owner's adoption of a pull request (an ask rule)
#   probe-holder                               read-only: the holder record and its state
#   probe-invocation                           read-only: the invocation identity
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
        st_set loop.phase "$ARG"
        lock_heartbeat
        log_event "PHASE $ARG"
        if [ "$ARG" = AWAIT_MERGE ]; then lock_release; log_event "LOCK_RELEASED"; fi
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
        adopt_state "$PR" "$br" "$hsha" || { echo "REFUSED GITHUB_UNAVAILABLE"; exit 1; }
        if [ -n "$TASK" ]; then st_set task.id "$TASK"; fi
        log_event "ADOPTED pr=$PR kind=$KIND branch=$br head=$hsha"
        echo "ADOPTED pr=$PR phase=AWAIT_MERGE head=$hsha comments<=$(st_get pr.lastIssueCommentId)/$(st_get pr.lastReviewCommentId)/$(st_get pr.lastReviewId)"
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
