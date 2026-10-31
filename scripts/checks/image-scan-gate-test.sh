#!/usr/bin/env bash
# The regression tests of scripts/checks/image-scan-gate.sh (Owner decision D-112),
# on synthetic reports, and of the accepted list that the decision records. They
# need jq only, and run at the start of the scan in scripts/checks/image-checks.sh.
set -uo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
gate="$root/scripts/checks/image-scan-gate.sh"
list="$root/scripts/checks/image-scan-accepted.json"
work="$(mktemp -d "${TMPDIR:-/tmp}/bantukasir-scan-gate.XXXXXX")"
trap 'rm -rf "$work"' EXIT

passes=0
failures=0

check() { # <description> <expected status> <expected text in the output, or empty>
    local output status
    output="$(bash "$gate" "$work/report.json" "${accepted_file:-$work/accepted.json}" 2>&1)"
    status=$?
    if [ "$status" -ne "$2" ]; then
        failures=$((failures + 1))
        echo "FAIL $1: status $status, expected $2"
        echo "$output"
    elif [ -n "$3" ] && ! grep -qF -- "$3" <<<"$output"; then
        failures=$((failures + 1))
        echo "FAIL $1: the output lacks [$3]"
        echo "$output"
    else
        passes=$((passes + 1))
        echo "PASS $1"
    fi
}

# match <identifier> <severity> <package> <version> <fix state> [fix versions, JSON]
match() {
    jq -cn --arg id "$1" --arg severity "$2" --arg name "$3" --arg version "$4" --arg state "$5" --argjson versions "${6:-[]}" \
        '{vulnerability: {id: $id, severity: $severity, fix: {state: $state, versions: $versions}}, relatedVulnerabilities: [], artifact: {name: $name, version: $version, type: "deb"}}'
}

report_of() { # the matches, one JSON object per argument
    printf '%s\n' "$@" | jq -s '{matches: ., source: {type: "image"}, distro: {name: "debian", version: "13"}}' >"$work/report.json"
}

# ---------------------------------------------------------- The fixture of the list

cat >"$work/accepted.json" <<'EOF'
{
  "decision": "synthetic",
  "accepted": [
    {
      "id": "CVE-2000-0001", "severity": "Critical", "decision": "synthetic", "fixState": "wont-fix",
      "packages": [{"name": "alpha", "version": "1.0-1", "type": "deb"}],
      "debianStatus": "synthetic", "rationale": "synthetic", "mitigation": "synthetic", "reviewTrigger": "synthetic"
    },
    {
      "id": "CVE-2000-0002", "severity": "High", "decision": "synthetic", "fixState": "not-fixed",
      "packages": [{"name": "alpha", "version": "1.0-1", "type": "deb"}],
      "debianStatus": "synthetic", "rationale": "synthetic", "mitigation": "synthetic", "reviewTrigger": "synthetic"
    }
  ]
}
EOF

critical="$(match CVE-2000-0001 Critical alpha 1.0-1 wont-fix)"
high="$(match CVE-2000-0002 High alpha 1.0-1 not-fixed)"

# ---------------------------------------------------------------------- The findings

report_of "$critical" "$high"
check 'the accepted findings pass, unfixable, at their exact version' 0 'Scan gate: 2 accepted, 0 refused'

report_of "$(match CVE-2000-0001 Critical alpha 1.0-1 fixed '["1.0-2"]')" "$high"
check 'an accepted critical finding with a fix available is refused' 1 'REFUSED_FIXABLE CVE-2000-0001'

report_of "$critical" "$(match CVE-2000-0002 High alpha 1.0-1 fixed '["1.0-2"]')"
check 'an accepted high finding with a fix available is refused' 1 'REFUSED_FIXABLE CVE-2000-0002'

report_of "$(match CVE-2000-0002 High alpha 1.0-1 not-fixed '["1.0-2"]')"
check 'a fix version under any state is a fix available' 1 'REFUSED_FIXABLE CVE-2000-0002'

report_of "$(match CVE-2000-0003 Critical alpha 1.0-1 fixed '["1.0-2"]')"
check 'a new fixable critical finding is refused' 1 'REFUSED_FIXABLE CVE-2000-0003'

report_of "$(match CVE-2000-0004 High alpha 1.0-1 fixed '["1.0-2"]')"
check 'a new fixable high finding is refused' 1 'REFUSED_FIXABLE CVE-2000-0004'

report_of "$(match CVE-2000-0005 Critical alpha 1.0-1 wont-fix)"
check 'a new critical finding without a fix is not accepted by itself' 1 'REFUSED_NOT_ACCEPTED CVE-2000-0005'

report_of "$(match CVE-2000-0006 High alpha 1.0-1 not-fixed)"
check 'a new high finding without a fix is not accepted by itself' 1 'REFUSED_NOT_ACCEPTED CVE-2000-0006'

report_of "$(match CVE-2000-0002 Critical alpha 1.0-1 not-fixed)"
check 'an accepted high finding that becomes critical is refused' 1 'REFUSED_NOT_ACCEPTED CVE-2000-0002'

report_of "$(match CVE-2000-0001 Critical alpha 1.0-2 wont-fix)"
check 'an accepted finding in another version of its package is refused' 1 'REFUSED_NOT_ACCEPTED CVE-2000-0001'

report_of "$(match CVE-2000-0001 Critical beta 1.0-1 wont-fix)"
check 'an accepted finding in another package is refused' 1 'REFUSED_NOT_ACCEPTED CVE-2000-0001'

report_of "$(match CVE-2000-0007 CRITICAL alpha 1.0-1 wont-fix)"
check 'a severity in another letter case is still judged' 1 'REFUSED_NOT_ACCEPTED CVE-2000-0007'

report_of "$(match CVE-2000-0001 Critical alpha 1.0-1 unknown)"
check 'an accepted finding with an unknown fix state is refused' 1 'REFUSED_FIX_STATE CVE-2000-0001'

report_of "$(jq -cn --argjson m "$critical" '$m | .vulnerability.knownExploited = [{cve: "CVE-2000-0001"}]')"
check 'an accepted finding known to be exploited is refused' 1 'REFUSED_KNOWN_EXPLOITED CVE-2000-0001'

report_of "$(jq -cn --argjson m "$high" '$m | .relatedVulnerabilities = [{knownExploited: [{cve: "CVE-2000-0002"}]}]')"
check 'exploitation recorded on a related record is refused too' 1 'REFUSED_KNOWN_EXPLOITED CVE-2000-0002'

report_of "$(match CVE-2000-0008 Medium gamma 2.0 fixed '["2.1"]')" "$(match CVE-2000-0009 Unknown gamma 2.0 not-fixed)" "$(match CVE-2000-0010 Negligible gamma 2.0 not-fixed)"
check 'a finding of lower severity is reported and passes' 0 'REPORTED CVE-2000-0008 Medium gamma 2.0 deb fix=fixed:2.1'
check 'every severity is printed' 0 'REPORTED CVE-2000-0010 Negligible'

report_of "$critical"
check 'an entry the scan no longer reports is printed, and refuses nothing' 0 'ACCEPTED_NOT_REPORTED CVE-2000-0002 High alpha 1.0-1 deb'

# ------------------------------------------------------------ What cannot be trusted

echo '{}' >"$work/report.json"
check 'a report without a list of findings cannot pass' 2 'SCAN_GATE_UNUSABLE'

echo '{"matches": [], "source": {"type": "image"}, "distro": {"name": "debian"}}' >"$work/report.json"
check 'a scan that found nothing to look at cannot pass' 2 'SCAN_GATE_UNUSABLE the report has no finding'

report_of "$critical"
jq '.source.type = "directory"' "$work/report.json" >"$work/variant-report.json"
mv "$work/variant-report.json" "$work/report.json"
check 'a report of something else than an image cannot pass' 2 'SCAN_GATE_UNUSABLE the report is not of an image'

report_of "$critical"
jq 'del(.source)' "$work/report.json" >"$work/variant-report.json"
mv "$work/variant-report.json" "$work/report.json"
check 'a report without its source cannot pass' 2 'SCAN_GATE_UNUSABLE the report is not of an image'

report_of "$critical"
jq 'del(.distro)' "$work/report.json" >"$work/variant-report.json"
mv "$work/variant-report.json" "$work/report.json"
check 'a report that found no distribution cannot pass' 2 'SCAN_GATE_UNUSABLE the report did not find Debian'

report_of "$critical"
jq '.distro.name = "alpine"' "$work/report.json" >"$work/variant-report.json"
mv "$work/variant-report.json" "$work/report.json"
check 'a report of another distribution cannot pass' 2 'SCAN_GATE_UNUSABLE the report did not find Debian'

report_of "$(jq -cn --argjson m "$(match CVE-2000-0013 Low gamma 2.0 not-fixed)" '$m | .artifact.type = "binary"')"
check 'a report that catalogued no Debian package cannot pass' 2 'SCAN_GATE_UNUSABLE the report found no Debian package'

report_of "$(jq -cn --argjson m "$(match CVE-2000-0011 High gamma 2.0 fixed '["2.1"]')" '$m | del(.vulnerability.severity)')"
check 'a finding without a severity cannot pass' 2 'SCAN_GATE_UNUSABLE a finding has no known severity'

report_of "$(match CVE-2000-0012 Severe gamma 2.0 fixed '["2.1"]')"
check 'a finding with an unknown severity word cannot pass' 2 'SCAN_GATE_UNUSABLE a finding has no known severity'

echo 'not JSON' >"$work/report.json"
check 'an unreadable report cannot pass' 2 'SCAN_GATE_UNUSABLE'

rm -f "$work/report.json"
check 'a missing report cannot pass' 2 'SCAN_GATE_UNUSABLE'

report_of "$critical"
accepted_file="$work/variant.json"
for field in decision debianStatus rationale mitigation reviewTrigger fixState; do
    jq --arg field "$field" 'del(.accepted[0][$field])' "$work/accepted.json" >"$accepted_file"
    check "an entry without its $field is refused" 2 'SCAN_GATE_UNUSABLE'
done
jq '.accepted[0].id = "CVE-*"' "$work/accepted.json" >"$accepted_file"
check 'an identifier by pattern is refused' 2 'SCAN_GATE_UNUSABLE'
jq '.accepted[0].packages[0].version = "*"' "$work/accepted.json" >"$accepted_file"
check 'a version by pattern is refused' 2 'SCAN_GATE_UNUSABLE'
jq '.accepted[0].packages = []' "$work/accepted.json" >"$accepted_file"
check 'an entry without a package is refused' 2 'SCAN_GATE_UNUSABLE'
jq '.accepted[0].severity = "Medium"' "$work/accepted.json" >"$accepted_file"
check 'an entry of another severity is refused' 2 'SCAN_GATE_UNUSABLE'
jq '.accepted[0].fixState = "fixed"' "$work/accepted.json" >"$accepted_file"
check 'an entry with a fix is refused' 2 'SCAN_GATE_UNUSABLE'
echo '[]' >"$accepted_file"
check 'a list of another shape is refused' 2 'SCAN_GATE_UNUSABLE'
unset accepted_file

# -------------------------------------------------------- The list of the decision

record() { # <description> <expected> <actual>
    if [ "$2" = "$3" ]; then
        passes=$((passes + 1))
        echo "PASS $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1: expected [$2], got [$3]"
    fi
}

report_of "$(match CVE-2026-6653 Critical libxml2 2.12.7+dfsg+really2.9.14-2.1+deb13u3 wont-fix)"
accepted_file="$list"
check 'the accepted list of D-112 is complete and exact, and accepts one of its findings' 0 'Scan gate: 1 accepted, 0 refused'
unset accepted_file

record 'the list names the 43 assessed CVEs' 43 "$(jq '.accepted | length' "$list")"
record 'each CVE is listed once' 43 "$(jq '[.accepted[].id] | unique | length' "$list")"
record 'the list holds the 112 findings of the assessed run' 112 "$(jq '[.accepted[].packages[]] | length' "$list")"
record 'every entry was accepted by D-112' 43 "$(jq '[.accepted[] | select(.decision == "D-112")] | length' "$list")"
record 'every entry is a Debian package' 112 "$(jq '[.accepted[].packages[] | select(.type == "deb")] | length' "$list")"
# A critical finding enters only by its own decision of the Owner, which adds its
# identifier here.
record 'the critical findings are exactly the seven that D-112 assessed' \
    'CVE-2026-10536 CVE-2026-11856 CVE-2026-18924 CVE-2026-19931 CVE-2026-6653 CVE-2026-8926 CVE-2026-9079' \
    "$(jq -r '[.accepted[] | select(.severity == "Critical") | .id] | sort | join(" ")' "$list")"
record 'each critical finding has its individual assessment' 7 \
    "$(jq '[.accepted[] | select(.severity == "Critical") | select((.critical.exposure | type) == "string" and (.critical.knownExploited | type) == "string" and (.critical.upstreamSeverity | type) == "string")] | length' "$list")"

echo "Scan gate tests: $passes passed, $failures failed"
[ "$failures" -eq 0 ]
