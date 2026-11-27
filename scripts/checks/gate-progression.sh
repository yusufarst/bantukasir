#!/usr/bin/env bash
# The gate progression of TASK-044 to TASK-050 (D-142; TASK_PLAN section 3;
# EXECUTION_CONTEXT CE-10): gate-progression.sh [<TASK_PLAN file>].
#
# It proves, with the selection functions of scripts/executor/lib.sh themselves,
# that no gate of the release path waits for a Task that the gate blocks:
#   - every gate that a row of TASK-044 to TASK-050 names has its own row in
#     section 3, so the tick can read its state;
#   - no row waits at its start for a gate that the row itself or a later Task
#     completes (the gates that the Tasks complete are named after
#     "before `DONE`:");
#   - on a copy of the plan, the external gates satisfied in their order and each
#     Task marked READY only when its dependencies are DONE, the selection of
#     CE-10 starts TASK-044, TASK-048, TASK-045, TASK-046, TASK-047, TASK-049 and
#     TASK-050 in that order, each while the gates that it completes are still
#     open;
#   - the selection refuses each Task while a gate it needs at its start is open.
# It changes nothing: it works on a temporary copy, and it reads lib.sh's
# functions without running the rest of lib.sh. The status is 0 when every case
# passes, 1 when one fails and 2 when the plan or the functions cannot be read.
set -u

here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../.." && pwd)"
plan_src="${1:-$root/docs/11-tasks/TASK_PLAN.md}"
lib="$root/scripts/executor/lib.sh"
[ -f "$plan_src" ] && [ -f "$lib" ] || { echo "GATE_PROGRESSION unreadable plan or lib.sh"; exit 2; }

# The tick's own selection functions, from lib.sh, without its other code.
functions="$(awk '
    /^(plan_rows|gate_state|gate_satisfied|start_gates|row_problems|select_task)\(\) \{/ { keep = 1 }
    keep { print }
    keep && /^\}$/ { keep = 0 }' "$lib")"
for f in plan_rows gate_state gate_satisfied start_gates row_problems select_task; do
    printf '%s\n' "$functions" | grep -q "^$f() {" || { echo "GATE_PROGRESSION lib.sh has no function $f"; exit 2; }
done
eval "$functions"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
plan="$work/TASK_PLAN.md"
cp "$plan_src" "$plan"

passes=0
failures=0
pass() { passes=$((passes + 1)); echo "PASS $1"; }
fail() { failures=$((failures + 1)); echo "FAIL $1: $2"; }

# set_status <TASK-NNN> <status>: the status cell of the row in section 4.
set_status() {
    awk -v t="$1" -v s="$2" '
        /^## 4\./ { sec = 1 } /^## / && !/^## 4\./ { sec = 0 }
        sec && index($0, "| [" t "](") == 1 { sub(/\| `[A-Z_]+` \|[[:space:]]*$/, "| `" s "` |") }
        { print }' "$plan" > "$plan.new" && mv "$plan.new" "$plan"
}
# set_gate <gate> <state>: the "State today" cell of the gate in section 3.
set_gate() {
    awk -v g="$1" -v s="$2" '
        /^## 3\./ { sec = 1; print; next } /^## / { sec = 0 }
        sec && /^\|/ {
            n = split($0, c, "|")
            id = c[2]; gsub(/^[ \t]+|[ \t]+$/, "", id)
            if (id == g) {
                line = "|"
                for (i = 2; i < n - 1; i++) line = line c[i] "|"
                print line " " s " |"
                next
            }
        }
        { print }' "$plan" > "$plan.new" && mv "$plan.new" "$plan"
}
row_of() { plan_rows "$plan" | awk -F'|' -v t="$1" '$1 == t'; }
# expect_selected <case> <TASK-NNN>: select_task chooses that Task.
expect_selected() {
    local out
    out="$(select_task "$plan")"
    case "$out" in "OK $2|"*) pass "$1" ;; *) fail "$1" "$out" ;; esac
}
# expect_blocked <case> <TASK-NNN> <gate>: the row names that gate as unmet.
expect_blocked() {
    local p
    p="$(row_problems "$plan" "$(row_of "$2")")"
    case "$p" in *"gate $3 not satisfied;"*) pass "$1" ;; *) fail "$1" "problems [$p]" ;; esac
}

release_path="TASK-044 TASK-045 TASK-046 TASK-047 TASK-048 TASK-049 TASK-050"

# 1. Every gate of the release path has a row in section 3.
for t in $release_path; do
    cell="$(row_of "$t" | cut -d'|' -f6)"
    [ -n "$cell" ] || { fail "1 $t has a row in section 4" "missing"; continue; }
    for g in $(printf '%s' "$cell" | sed -e 's/before `DONE`://' | tr ',;' '  '); do
        [ -n "$(gate_state "$plan" "$g")" ] && pass "1 $t: gate $g has a row in section 3" || fail "1 $t: gate $g has a row in section 3" "no row"
    done
done

# 2. No row waits at its start for a gate that it or a later Task completes.
completed_by() {
    case "$1" in
        STAGING-DEPLOY) echo "TASK-044" ;;
        DEVICES) echo "TASK-046" ;;
        GATE-CC2) echo "TASK-047" ;;
        TARGET) echo "TASK-048" ;;
        G1 | G2 | G3 | G4-SEALED) echo "TASK-049" ;;
        G4 | RELEASE) echo "TASK-050" ;;
        *) echo "" ;;
    esac
}
for t in $release_path; do
    while IFS= read -r g; do
        [ -n "$g" ] || continue
        by="$(completed_by "$g")"
        if [ -n "$by" ] && [ ! "$by" \< "$t" ]; then
            fail "2 $t does not wait at its start for $g" "completed by $by"
        else
            pass "2 $t does not wait at its start for ${g}${by:+, completed by $by}"
        fi
    done < <(start_gates "$(row_of "$t" | cut -d'|' -f6)")
done

# 3. The progression on a copy: every gate of the path open, every Task TODO.
for g in OPERATOR TARGET-STAGING STAGING-DEPLOY TARGET TARGET-PRODUCTION DEVICES-AVAILABLE DEVICES G1 G2 G3 G4-STORE G4-SEALED G4 GATE-CC2 RELEASE-AUTH RELEASE; do set_gate "$g" "OPEN"; done
for t in $release_path; do set_status "$t" TODO; done
set_status TASK-043 DONE
set_status TASK-051 DONE

set_status TASK-044 READY
expect_blocked "3 TASK-044 waits for OPERATOR" TASK-044 OPERATOR
expect_blocked "3 TASK-044 waits for TARGET-STAGING" TASK-044 TARGET-STAGING
set_gate OPERATOR "SATISFIED — the operator named"
set_gate TARGET-STAGING "SATISFIED — phase A recorded"
expect_selected "3 TASK-044 starts with STAGING-DEPLOY still open" TASK-044
set_gate STAGING-DEPLOY "SATISFIED — the candidate's deployment authorized"
set_status TASK-044 DONE

set_status TASK-048 READY
expect_selected "3 TASK-048 starts with G2 and TARGET still open" TASK-048
set_status TASK-048 DONE

set_status TASK-045 READY
expect_blocked "3 TASK-045 waits for G4-STORE" TASK-045 G4-STORE
set_gate G4-STORE "SATISFIED — the store, buckets and keys"
expect_selected "3 TASK-045 starts with G4-STORE" TASK-045
set_status TASK-045 DONE
set_gate TARGET "SATISFIED — phases A and B"

set_status TASK-046 READY
expect_blocked "3 TASK-046 waits for DEVICES-AVAILABLE" TASK-046 DEVICES-AVAILABLE
set_gate DEVICES-AVAILABLE "SATISFIED — the devices available"
expect_selected "3 TASK-046 starts with DEVICES still open" TASK-046
set_gate DEVICES "SATISFIED — the Owner's decision on DEVICE01"
set_status TASK-046 DONE

set_status TASK-047 READY
expect_selected "3 TASK-047 starts with GATE-CC2 still open" TASK-047
set_gate GATE-CC2 "SATISFIED — the values confirmed"
set_status TASK-047 DONE

set_status TASK-049 READY
expect_selected "3 TASK-049 starts with G1, G2, G3 and G4-SEALED still open" TASK-049
for g in G1 G2 G3; do set_gate "$g" "SATISFIED — recorded by the Owner"; done
set_gate G4-SEALED "SATISFIED — the sealed copy handed over"
set_status TASK-049 DONE

set_status TASK-050 READY
expect_blocked "3 TASK-050 waits for TARGET-PRODUCTION" TASK-050 TARGET-PRODUCTION
expect_blocked "3 TASK-050 waits for RELEASE-AUTH" TASK-050 RELEASE-AUTH
set_gate TARGET-PRODUCTION "SATISFIED — phase C recorded"
set_gate RELEASE-AUTH "SATISFIED — the Owner's exact authorization"
expect_selected "3 TASK-050 starts with G4 and RELEASE still open" TASK-050

# 4. A gate that a Task needs at its start, open again, blocks it.
set_gate G4-SEALED "OPEN"
expect_blocked "4 TASK-050 waits for G4-SEALED" TASK-050 G4-SEALED
set_gate G4-SEALED "SATISFIED — the sealed copy handed over"
set_gate TARGET "OPEN"
set_status TASK-050 TODO
set_status TASK-049 READY
expect_blocked "4 TASK-049 waits for TARGET" TASK-049 TARGET

echo "Gate progression: $passes passed, $failures failed"
[ "$failures" -eq 0 ]
