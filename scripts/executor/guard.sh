#!/usr/bin/env bash
# scripts/executor/guard.sh: the publication guard before every push (D-076,
# D-097, D-098; EXECUTION_CONTEXT CE-31, EXC-31; AGENTS rules 9 and 12).
#
# Usage: bash scripts/executor/guard.sh                  the commits HEAD --not --remotes=origin
#        bash scripts/executor/guard.sh --accept <sha256>  the same, accepting the judged candidates
#        bash scripts/executor/guard.sh --preview        the uncommitted change against origin/develop
# FAIL: the repository's secret scan (scripts/checks/secret-scan.sh) or
# Gitleaks with its default rules only finds anything; a commit's author or
# committer is not the Owner; a commit message holds a trailer, a forbidden word
# or a last line "Key: value"; an added path is excluded (AGENTS rule 9).
# REVIEW: candidates of the privacy and client-data check, from the added lines
# and the new paths: e-mail addresses, IPv4 addresses, URLs outside PUBLIC_HOSTS,
# home-directory paths, Indonesian phone numbers, 16-digit numbers, binary files
# and files above 5 MB. They are written to the executor state with their
# SHA-256; the executor judges each one (D-097 baseline, as D-098 extends it).
# PASS, or --accept with the hash of unchanged candidates, writes the stamp that
# push.sh requires: the HEAD SHA and the range. --preview writes no stamp.
# Both scans run natively, without a container (EXECUTION_CONTEXT CE-31, D-108).
# On Windows the binary is the verified one under
# %LOCALAPPDATA%\BantuKasir\tools\gitleaks\<version>\gitleaks.exe, whose SHA-256
# must equal GITLEAKS_WINDOWS_SHA256; it is put first on this process's PATH,
# so that the secret scan's own gitleaks is the same binary. Elsewhere it is
# gitleaks on PATH. A missing binary, another checksum or another version than
# GITLEAKS_VERSION is a FAIL, and neither scan runs. Secret values are never
# printed (Gitleaks --redact).
# Exit codes: 0 PASS (stamp written; --preview: no candidate); 1 FAIL or a
# refused --accept; 2 usage; 3 REVIEW (no stamp).
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

# The version, and the SHA-256 of gitleaks.exe from the release's Windows x64
# archive, that docs/00-governance/TOOLCHAIN.md pins.
GITLEAKS_VERSION=8.30.1
GITLEAKS_WINDOWS_SHA256=17157e2ee8b76fc8b1d8bee607a250e34b8a8023c8bc81822d4b5ee4d78fcb7c
RANGE="HEAD --not --remotes=origin"
CAND="$STATE_DIR/guard-candidates.txt"
STAMP="$STATE_DIR/guard-pass"
MODE=run
ACCEPT=""
case "${1:-}" in
    "") ;;
    --preview) MODE=preview ;;
    --accept) MODE=accept; ACCEPT="${2:-}"; printf '%s' "$ACCEPT" | grep -qE '^[0-9a-f]{64}$' || { echo "USAGE --accept <sha256>"; exit 2; } ;;
    *) echo "USAGE guard.sh [--preview | --accept <sha256>]"; exit 2 ;;
esac

WORK="$(mktemp -d)"
trap 'rm -rf -- "$WORK"' EXIT
FAILS=0
fail() { echo "FAIL $*"; FAILS=$((FAILS + 1)); }

# extract: reads "commit <sha>", "+++ b/<path>", "+<added line>" and
# "NEWPATH <path>" lines; prints "kind|commit|path|value" candidates.
extract() {
    awk -v hosts="$PUBLIC_HOSTS" -v sfx="$PUBLIC_SUFFIXES" -v owner="$OWNER_EMAIL" '
        BEGIN { n = split(hosts, h, " "); for (i = 1; i <= n; i++) H[h[i]] = 1; ns = split(sfx, S, " "); commit = "-"; file = "-" }
        function out(kind, v) { k = kind "|" commit "|" file "|" v; if (!(k in seen)) { seen[k] = 1; print k } }
        function suffix_ok(d,   i, l) { for (i = 1; i <= ns; i++) { l = length(S[i]); if (length(d) > l && substr(d, length(d) - l + 1) == S[i]) return 1 } return 0 }
        function host_ok(d) { d = tolower(d); sub(/\.$/, "", d); return (d in H) || suffix_ok(d) }
        function email_ok(v,   d) { v = tolower(v); if (v == owner) return 1; d = v; sub(/^.*@/, "", d); return host_ok(d) }
        function ipv4(v, pre, post,   q, i) {
            if (pre ~ /[0-9.]/ || post ~ /[0-9.]/) return
            if (split(v, q, ".") != 4) return
            for (i = 1; i <= 4; i++) if (q[i] + 0 > 255) return
            if (v != "127.0.0.1" && v != "0.0.0.0") out("ipv4", v)
        }
        function each(s, re, kind,   off, v, pre, post, d, t) {
            off = 0
            while (match(substr(s, off + 1), re)) {
                v = substr(s, off + RSTART, RLENGTH)
                pre = (off + RSTART > 1) ? substr(s, off + RSTART - 1, 1) : ""
                post = substr(s, off + RSTART + RLENGTH, 1)
                if (kind == "email") { if (!email_ok(v)) out("email", v) }
                else if (kind == "ipv4") ipv4(v, pre, post)
                else if (kind == "url") { d = v; sub(/^[A-Za-z]+:\/\//, "", d); sub(/[\/:?#].*$/, "", d); if (!host_ok(d)) out("url", v) }
                else if (kind == "home") out("home", v)
                else if (kind == "phone") { t = v; gsub(/[^0-9]/, "", t); if (length(t) >= 9 && length(t) <= 13 && pre !~ /[0-9A-Za-z]/ && post !~ /[0-9A-Za-z]/) out("phone", v) }
                else if (kind == "num16") { if (length(v) == 16 && pre !~ /[0-9A-Za-z]/ && post !~ /[0-9A-Za-z]/) out("number16", v) }
                off = off + RSTART + RLENGTH - 1
                if (RLENGTH < 1) off++
            }
        }
        function scan(s) {
            each(s, "[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+[.][A-Za-z][A-Za-z]+", "email")
            each(s, "[0-9]+[.][0-9]+[.][0-9]+[.][0-9]+", "ipv4")
            each(s, "[Hh][Tt][Tt][Pp][Ss]?://[^] \t\"\047<>()`[]+", "url")
            each(s, "[A-Za-z]:[\\\\/]+[Uu][Ss][Ee][Rr][Ss][\\\\/]+[^\\\\/ \t\"\047<>]+", "home")
            each(s, "/(home|Users)/[A-Za-z0-9._-]+", "home")
            each(s, "(\\+62|08)[0-9-]+", "phone")
            each(s, "[0-9]+", "num16")
        }
        /^commit [0-9a-f]+$/ { commit = substr($2, 1, 12); file = "-"; next }
        /^\+\+\+ / { file = substr($0, 5); sub(/^b\//, "", file); next }
        /^NEWPATH / { file = substr($0, 9); scan(file); next }
        /^\+/ { scan(substr($0, 2)); next }
    '
}
# large_or_binary_commit <commit>: binary and large files the commit adds or changes.
large_or_binary_commit() {
    local c="$1" p a d path size
    p="$(git rev-parse -q --verify "$c^1" || true)"
    [ -n "$p" ] || p="$(git hash-object -t tree /dev/null)"
    git diff --numstat --no-renames --diff-filter=AM "$p" "$c" | while IFS=$'\t' read -r a d path; do
        if [ "$a" = - ] && [ "$d" = - ]; then printf 'binary|%s|%s|binary file\n' "${c:0:12}" "$path"; fi
        size="$(git cat-file -s "$c:$path" 2> /dev/null || echo 0)"
        if [ "$size" -gt 5242880 ]; then printf 'large|%s|%s|%s bytes\n' "${c:0:12}" "$path" "$size"; fi
    done
}
check_added_path() {  # <commit or -> <path>
    if excluded_path "$2"; then fail "PATH $1 adds $2, which is never published (AGENTS rule 9)"; fi
}

# ---------------------------------------------------------------- --preview
if [ "$MODE" = preview ]; then
    base="origin/$BASE"
    echo "GUARD PREVIEW of the uncommitted change against $base"
    {
        git diff -U0 --no-color --no-renames "$base"
        while IFS= read -r -d '' f; do
            known_untracked "$f" && continue
            printf 'NEWPATH %s\n+++ b/%s\n' "$f" "$f"
            if [ -f "$f" ] && grep -qI . "$f" 2> /dev/null; then sed 's/^/+/' "$f"; fi
        done < <(git ls-files -z --others --exclude-standard)
        git diff --name-only --no-renames --diff-filter=A "$base" | sed 's/^/NEWPATH /'
    } | extract | LC_ALL=C sort -u > "$WORK/cand"
    while IFS= read -r f; do check_added_path - "$f"; done < <(git diff --name-only --no-renames --diff-filter=A "$base")
    while IFS= read -r -d '' f; do
        known_untracked "$f" && continue
        check_added_path - "$f"
        if [ -s "$f" ] && ! grep -qI . "$f" 2> /dev/null; then printf 'binary|-|%s|binary file\n' "$f" >> "$WORK/cand"; fi
        if [ "$(wc -c < "$f")" -gt 5242880 ]; then printf 'large|-|%s|%s bytes\n' "$f" "$(wc -c < "$f")" >> "$WORK/cand"; fi
    done < <(git ls-files -z --others --exclude-standard)
    while IFS=$'\t' read -r a d path; do
        if [ "$a" = - ] && [ "$d" = - ]; then printf 'binary|-|%s|binary file\n' "$path" >> "$WORK/cand"; fi
    done < <(git diff --numstat --no-renames --diff-filter=AM "$base")
    LC_ALL=C sort -u -o "$WORK/cand" "$WORK/cand"
    n="$(grep -c . "$WORK/cand" || true)"
    if [ "$n" -gt 0 ]; then echo "CANDIDATES $n"; cat "$WORK/cand"; fi
    if [ "$FAILS" -gt 0 ]; then echo "GUARD PREVIEW FAIL ($FAILS)"; exit 1; fi
    if [ "$n" -gt 0 ]; then echo "GUARD PREVIEW REVIEW $n candidate(s)"; exit 3; fi
    echo "GUARD PREVIEW PASS"
    exit 0
fi

# ---------------------------------------------------------------- the commits to publish
rm -f -- "$STAMP"
mkdir -p "$STATE_DIR"
head="$(git rev-parse HEAD)"
# shellcheck disable=SC2086
commits="$(git rev-list $RANGE)"
count="$(printf '%s' "$commits" | grep -c . || true)"
echo "GUARD HEAD $head, range $RANGE: $count commit(s)"
if [ "$count" = 0 ]; then
    printf '%s\n%s\n' "$head" "$RANGE" > "$STAMP"
    log_event "GUARD PASS $head nothing to publish"
    echo "GUARD PASS nothing to publish"
    exit 0
fi

# gitleaks_ready: on Windows, puts the verified user-local binary first on
# this process's PATH; then the gitleaks that PATH resolves must be of the
# pinned version. Sets GL to the result or the reason, and returns 1 on a
# reason.
GL=""
gitleaks_ready() {
    local dir exe have
    if is_windows; then
        [ -n "${LOCALAPPDATA:-}" ] || { GL="GITLEAKS_BINARY LOCALAPPDATA is not set"; return 1; }
        dir="$(cygpath -u "$LOCALAPPDATA")/BantuKasir/tools/gitleaks/$GITLEAKS_VERSION"
        exe="$dir/gitleaks.exe"
        [ -f "$exe" ] || { GL="GITLEAKS_BINARY $exe is missing"; return 1; }
        [ "$(sha256sum < "$exe" | cut -c1-64)" = "$GITLEAKS_WINDOWS_SHA256" ] || { GL="GITLEAKS_BINARY $exe has another SHA-256 than the pinned one"; return 1; }
        PATH="$dir:$PATH"
        export PATH
        # Git Bash resolves gitleaks.exe as <dir>/gitleaks; the directory must be the verified one.
        [ "$(dirname "$(command -v gitleaks || echo /none/x)")" = "$dir" ] || { GL="GITLEAKS_BINARY PATH resolves $(command -v gitleaks || echo nothing), not the binary in $dir"; return 1; }
    fi
    command -v gitleaks > /dev/null 2>&1 || { GL="GITLEAKS_BINARY gitleaks is not on PATH"; return 1; }
    have="$(gitleaks version 2> /dev/null | head -n 1 | tr -d '\r' || true)"
    [ "${have#v}" = "$GITLEAKS_VERSION" ] || { GL="GITLEAKS_VERSION gitleaks ${have:-without a version} at $(command -v gitleaks), not $GITLEAKS_VERSION"; return 1; }
    GL="gitleaks $GITLEAKS_VERSION at $(command -v gitleaks)"
}

echo "== Gitleaks $GITLEAKS_VERSION"
if ! gitleaks_ready; then
    fail "$GL"
else
    echo "  $GL"
    echo "== The repository's secret scan"
    if SECRET_SCAN_LOG_OPTS="$RANGE" bash "$ROOT/scripts/checks/secret-scan.sh" "$ROOT" > "$WORK/scan" 2>&1; then sed 's/^/  /' "$WORK/scan"; else sed 's/^/  /' "$WORK/scan"; fail "SECRET_SCAN the repository's secret scan found something or failed"; fi
    echo "== Gitleaks, default rules only"
    printf '[extend]\nuseDefault = true\n' > "$WORK/default-rules.toml"
    if gitleaks git --config "$WORK/default-rules.toml" --redact --no-banner --no-color --log-opts="$RANGE" "$ROOT" > "$WORK/scan" 2>&1; then sed 's/^/  /' "$WORK/scan"; else sed 's/^/  /' "$WORK/scan"; fail "DEFAULT_RULES Gitleaks with its default rules found something or failed"; fi
fi

echo "== Identity, messages and paths"
want="$OWNER_NAME|$OWNER_EMAIL|$OWNER_NAME|$OWNER_EMAIL"
for c in $commits; do
    idn="$(git log -1 --format='%an|%ae|%cn|%ce' "$c")"
    [ "$idn" = "$want" ] || fail "IDENTITY $c is by $idn"
    git cat-file commit "$c" | sed '1,/^$/d' > "$WORK/msg"
    case "$(msg_problem "$WORK/msg" 1)" in
        WORD) fail "MESSAGE $c holds a forbidden word" ;;
        TRAILER) fail "MESSAGE $c holds a trailer" ;;
        KEY_VALUE) fail "MESSAGE $c ends with a line of the form Key: value" ;;
    esac
    p="$(git rev-parse -q --verify "$c^1" || true)"
    [ -n "$p" ] || p="$(git hash-object -t tree /dev/null)"
    while IFS= read -r f; do check_added_path "$c" "$f"; done < <(git diff --name-only --no-renames --diff-filter=A "$p" "$c")
done

echo "== Candidates of the privacy and client-data check"
{
    # shellcheck disable=SC2086
    git log -p -U0 --no-color --no-renames --diff-merges=first-parent --format='commit %H' $RANGE
    for c in $commits; do
        p="$(git rev-parse -q --verify "$c^1" || true)"
        [ -n "$p" ] || p="$(git hash-object -t tree /dev/null)"
        printf 'commit %s\n' "$c"
        git diff --name-only --no-renames --diff-filter=A "$p" "$c" | sed 's/^/NEWPATH /'
    done
} | extract > "$WORK/cand"
for c in $commits; do large_or_binary_commit "$c" >> "$WORK/cand"; done
LC_ALL=C sort -u -o "$WORK/cand" "$WORK/cand"
n="$(grep -c . "$WORK/cand" || true)"
{ printf 'HEAD %s\nRANGE %s\n' "$head" "$RANGE"; cat "$WORK/cand"; } > "$WORK/candfile"
hash="$(sha256_of < "$WORK/candfile")"

if [ "$FAILS" -gt 0 ]; then
    [ "$n" -eq 0 ] || { echo "CANDIDATES $n"; cat "$WORK/cand"; }
    log_event "GUARD FAIL $head ($FAILS)"
    echo "GUARD FAIL ($FAILS)"
    exit 1
fi
if [ "$n" -eq 0 ]; then
    printf '%s\n%s\n' "$head" "$RANGE" > "$STAMP"
    log_event "GUARD PASS $head $count commit(s)"
    echo "GUARD PASS"
    exit 0
fi
echo "CANDIDATES $n"
cat "$WORK/cand"
if [ "$MODE" = accept ]; then
    if [ "$ACCEPT" = "$hash" ] && [ -f "$CAND" ] && cmp -s "$CAND" "$WORK/candfile"; then
        printf '%s\n%s\naccepted %s\n' "$head" "$RANGE" "$hash" > "$STAMP"
        log_event "GUARD ACCEPTED $head candidates $hash"
        echo "GUARD ACCEPTED $hash"
        exit 0
    fi
    cp "$WORK/candfile" "$CAND"
    echo "REFUSED HASH the candidates now hash to $hash; judge them again"
    exit 1
fi
cp "$WORK/candfile" "$CAND"
log_event "GUARD REVIEW $head $n candidate(s) $hash"
echo "GUARD REVIEW $n candidate(s); SHA-256 $hash (file $CAND)"
exit 3
