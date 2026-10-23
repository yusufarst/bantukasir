#!/usr/bin/env bash
# scripts/executor/pr.sh: the pull request of a Task or ops/ branch, its CI and
# its comments (D-101; EXECUTION_CONTEXT CE-32, CE-41 to CE-43).
#
# Usage: bash scripts/executor/pr.sh <subcommand>
#   open --title <t> --body-file <f>   opens the branch's pull request into develop
#                                      (or prints the open one): PR <n> <url>
#   body --body-file <f>               replaces the description of that pull request
#   wait-ci [--max-seconds 540]        CI PASS | CI FAIL <runs> | CI FAIL LIMIT | CI PENDING | CI TIMEOUT
#   rerun                              re-runs the failed jobs once per head: RERUN <id> | RERUN REFUSED
#   status                             state, merged flag, head, base and merge commit
#   comments                           the new comments of the Owner and of REVIEW_BOTS, decoded
#   api <path> [--jq <expr>]           a GET of repos/yusufarst/bantukasir...; REFUSED PATH | REFUSED ARGUMENT
# The title is "TASK-NNN — <Title>" on a Task branch or "ops — <title>" on an
# ops/ branch; the title and body hold no forbidden word, the body no trailer
# and no last line "Key: value". Never a draft, reviewer, label or auto-merge.
# Exit codes: 0 done (also CI PASS); 1 refused, CI FAIL, CI FAIL LIMIT or
# CI TIMEOUT; 2 usage; 4 CI PENDING.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

usage() { echo "USAGE pr.sh open|body|wait-ci|rerun|status|comments|api ..."; exit 2; }
refuse() { echo "REFUSED $*"; exit 1; }
sub="${1:-}"
[ $# -gt 0 ] && shift

# The pull request of this branch: from the state, else the open one on GitHub.
branch_pr() {
    local br n
    br="$(cur_branch)"
    n="$(st_get pr.number)"
    if [ -n "$n" ] && { [ -z "$(st_get task.branch)" ] || [ "$(st_get task.branch)" = "$br" ]; }; then printf '%s\n' "$n"; return 0; fi
    gh_open_prs | awk -F'|' -v b="$br" -v r="$REPO" '$2 == b && $5 == r { print $1; exit }'
}
check_text() {  # title-or-body file, 0|1 (1 for the body's last-line rule)
    case "$(msg_problem "$1" 0)" in
        WORD) refuse "TEXT holds a forbidden word (an attribution or a mention of an AI model, agent or tool)" ;;
        TRAILER) refuse "TEXT holds a trailer" ;;
        KEY_VALUE) refuse "TEXT ends with a line of the form Key: value" ;;
    esac
}

case "$sub" in
    open | body)
        TITLE="" BODY=""
        while [ $# -gt 0 ]; do
            case "$1" in
                --title) TITLE="${2:-}"; shift 2 ;;
                --body-file) BODY="${2:-}"; shift 2 ;;
                *) usage ;;
            esac
        done
        [ -n "$BODY" ] && [ -f "$BODY" ] || usage
        br="$(cur_branch)"
        case "$br" in task/TASK-[0-9][0-9][0-9]-* | ops/?*) ;; *) refuse "BRANCH ${br:-detached HEAD}" ;; esac
        check_text "$BODY"
        if [ "$sub" = open ]; then
            [ -n "$TITLE" ] || usage
            case "$br" in
                task/*) num="$(task_of_branch "$br")"; case "$TITLE" in "$num — "?*) ;; *) refuse "TITLE must be \"$num — <Title>\"" ;; esac ;;
                ops/*) case "$TITLE" in "ops — "?*) ;; *) refuse "TITLE must be \"ops — <title>\"" ;; esac ;;
            esac
            t="$(mktemp)"
            printf '%s\n' "$TITLE" > "$t"
            if grep -qiE "$FORBIDDEN_RE" "$t" || grep -qiwE "$FORBIDDEN_WORDS_RE" "$t"; then rm -f -- "$t"; refuse "TITLE holds a forbidden word"; fi
            rm -f -- "$t"
            existing="$(gh_open_prs)" || refuse "GITHUB_UNAVAILABLE"
            line="$(printf '%s\n' "$existing" | awk -F'|' -v b="$br" -v r="$REPO" '$2 == b && $5 == r { print; exit }')"
            if [ -n "$line" ]; then
                IFS='|' read -r n _ hsha _ _ url <<< "$line"
                echo "PR $n $url (already open)"
                exit 0
            fi
            url="$(gh_pr_create "$br" "$TITLE" "$BODY" | tr -d '\r' | grep -oE 'https://github\.com/[^ ]+/pull/[0-9]+' | tail -n 1)" || refuse "CREATE gh pr create failed"
            n="${url##*/}"
            is_int "$n" || refuse "CREATE no pull request number in the answer"
            if [ -f "$STATE_FILE" ]; then st_set pr.number "$n"; st_set pr.head "$(git rev-parse HEAD)"; fi
            log_event "PR_OPENED $n $br"
            echo "PR $n $url"
        else
            n="$(branch_pr)" || true
            is_int "$n" || refuse "NO_PR no open pull request for $br"
            gh_pr_edit_body "$n" "$BODY" > /dev/null || refuse "EDIT gh pr edit failed"
            log_event "PR_BODY $n"
            echo "PR $n body replaced"
        fi
        ;;
    wait-ci)
        max=540
        case "${1:-}" in "") ;; --max-seconds) max="${2:-}"; is_int "$max" || usage ;; *) usage ;; esac
        n="$(branch_pr)" || true
        is_int "$n" || refuse "NO_PR no pull request for $(cur_branch)"
        info="$(gh_pr "$n")" || refuse "GITHUB_UNAVAILABLE"
        sha="$(printf '%s\n' "$info" | sed -n '4p')"
        [ -n "$sha" ] || refuse "GITHUB_UNAVAILABLE no head"
        if [ "$(st_get ci.head)" != "$sha" ]; then st_set ci.head "$sha"; st_set ci.waitStart "$(now)"; fi
        start="$(st_get ci.waitStart)"
        poll=30
        if selftest_mode && is_int "${EXECUTOR_POLL_SECONDS:-}"; then poll="$EXECUTOR_POLL_SECONDS"; fi
        t0="$(date +%s)"
        while :; do
            runs="$(gh_check_runs "$sha")" || runs="__ERROR__"
            if [ "$runs" != "__ERROR__" ] && [ -n "$runs" ]; then
                res="$(printf '%s\n' "$runs" | awk -F'|' 'NF >= 4 { n++; if ($3 != "completed") p = 1; else if ($4 != "success" && $4 != "neutral" && $4 != "skipped") f = f " " $2 " " $5 } END { if (p) print "PENDING"; else if (f != "") print "FAIL" f; else print "PASS" }')"
                case "$res" in
                    PASS) log_event "CI PASS $sha"; echo "CI PASS $sha"; exit 0 ;;
                    FAIL*)
                        if ! st_all ci.failedHead | grep -qx "$sha"; then
                            st_add ci.failedHead "$sha"
                            fl="$(st_get ci.failures)"
                            st_set ci.failures $(( ${fl:-0} + 1 ))
                        fi
                        fl="$(st_get ci.failures)"
                        log_event "CI FAIL $sha failure $fl"
                        if [ "$fl" -gt "$MAX_CI_FAILURES" ]; then echo "CI FAIL LIMIT $sha failure $fl:${res#FAIL}"; exit 1; fi
                        echo "CI FAIL $sha failure $fl of $MAX_CI_FAILURES:${res#FAIL}"
                        exit 1
                        ;;
                esac
            fi
            if [ $(( $(now) - start )) -ge $(( CI_MAX_MINUTES * 60 )) ]; then log_event "CI TIMEOUT $sha"; echo "CI TIMEOUT $sha pending for $CI_MAX_MINUTES minutes"; exit 1; fi
            if [ $(( $(date +%s) - t0 + poll )) -gt "$max" ]; then echo "CI PENDING $sha"; exit 4; fi
            sleep "$poll"
        done
        ;;
    rerun)
        n="$(branch_pr)" || true
        is_int "$n" || refuse "NO_PR"
        info="$(gh_pr "$n")" || refuse "GITHUB_UNAVAILABLE"
        sha="$(printf '%s\n' "$info" | sed -n '4p')"
        if [ "$(st_get ci.rerunFor)" = "$sha" ]; then echo "RERUN REFUSED $sha was re-run once already (CE-42)"; exit 1; fi
        runs="$(gh_ci_runs "$sha" pull_request)" || refuse "GITHUB_UNAVAILABLE"
        id="$(printf '%s\n' "$runs" | awk -F'|' '$2 == "completed" && $3 != "success" && $3 != "" { print $1; exit }')"
        is_int "$id" || refuse "NO_FAILED_RUN for $sha"
        gh_run_rerun "$id" > /dev/null || refuse "RERUN gh run rerun failed"
        st_set ci.rerunFor "$sha"
        log_event "CI RERUN $id $sha"
        echo "RERUN $id $sha"
        ;;
    status)
        n="$(branch_pr)" || true
        is_int "$n" || refuse "NO_PR"
        info="$(gh_pr "$n")" || refuse "GITHUB_UNAVAILABLE"
        { read -r state; read -r merged; read -r msha; read -r hsha; read -r href; read -r bref; read -r _; read -r _; read -r url; } <<< "$info"
        echo "PR $n state=$state merged=$merged head=$hsha base=$bref branch=$href merge=${msha:-none} $url"
        ;;
    comments)
        n="$(branch_pr)" || true
        is_int "$n" || refuse "NO_PR"
        ign=0
        for kind in issue review-comment review; do
            case "$kind" in
                issue) lines="$(gh_issue_comments "$n")" || refuse "GITHUB_UNAVAILABLE"; after="$(st_get pr.lastIssueCommentId)" ;;
                review-comment) lines="$(gh_review_comments "$n")" || refuse "GITHUB_UNAVAILABLE"; after="$(st_get pr.lastReviewCommentId)" ;;
                review) lines="$(gh_reviews "$n")" || refuse "GITHUB_UNAVAILABLE"; after="$(st_get pr.lastReviewId)" ;;
            esac
            while IFS='|' read -r id login b64 _; do
                is_int "$id" && id_gt "$id" "${after:-0}" || continue
                body="$(b64d "$b64" | tr -d '\r')"
                [ -n "$(printf '%s' "$body" | tr -d '[:space:]')" ] || continue
                if [ "$login" = "$OWNER_LOGIN" ] || { [ -n "$REVIEW_BOTS" ] && in_words "$login" "$REVIEW_BOTS"; }; then
                    printf '%s %s by %s:\n<<<\n%s\n>>>\n' "$kind" "$id" "$login" "$body"
                else
                    ign=$((ign + 1))
                fi
            done < <(printf '%s\n' "$lines" | sort -t'|' -k1,1n)
        done
        echo "IGNORED $ign comment(s) by other accounts"
        ;;
    api)
        path="${1:-}"
        [ $# -gt 0 ] && shift
        case "$path" in
            repos/yusufarst/bantukasir | repos/yusufarst/bantukasir/* | "repos/yusufarst/bantukasir?"*) ;;
            *) echo "REFUSED PATH"; exit 1 ;;
        esac
        case "$path" in *..* | *[[:space:]]*) echo "REFUSED PATH"; exit 1 ;; esac
        jq=()
        if [ $# -gt 0 ]; then
            if [ $# -eq 2 ] && [ "$1" = --jq ]; then jq=(--jq "$2"); else echo "REFUSED ARGUMENT"; exit 1; fi
        fi
        gh_get "$path" "${jq[@]}"
        ;;
    *) usage ;;
esac
