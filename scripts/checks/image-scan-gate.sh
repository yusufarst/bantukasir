#!/usr/bin/env bash
# The gate of the vulnerability scan of the image (TASK-012 contract section 10;
# Owner decision D-112): image-scan-gate.sh <Grype's JSON report> <accepted list>.
#
# Every finding of every severity is printed. A high or critical finding passes
# only when it is exactly an entry of the accepted list: the same identifier, the
# same severity, and the same package, version and package type. So a new critical
# or high finding, a raised severity or another package version is refused until
# someone assesses it, and a critical one needs its own decision of the Owner. Even
# an accepted finding is refused when a fix has become available, when its fix
# state is anything but `wont-fix` or `not-fixed`, or when it is known to be
# exploited. There is no suppression by pattern, and the scan itself is never
# skipped. The status is 0 when nothing is refused, 1 when something is, and 2 when
# the report or the list cannot be trusted. A result is never assumed from a file
# that was not read: an unusable input ends here.
set -uo pipefail

report="${1:-}"
accepted="${2:-}"

if [ ! -f "$report" ] || [ ! -f "$accepted" ]; then
    echo "SCAN_GATE_UNUSABLE the report or the accepted list is missing"
    exit 2
fi

if ! jq -e '(.matches | type) == "array"' "$report" >/dev/null 2>&1; then
    echo "SCAN_GATE_UNUSABLE the report has no list of findings"
    exit 2
fi

# Each entry of the list states what the Owner's decision requires, and names exact
# packages and versions only.
invalid="$(jq -r '
    def text: type == "string" and test("\\S");
    def exact: text and (test("[*?\\s]") | not);
    if (.accepted | type) != "array" then
        "the list has no entries"
    else
        .accepted[]
        | select(
            (((.id | exact) and (.id | test("\\ACVE-[0-9]{4}-[0-9]+\\z"))) | not)
            or ((.severity == "High" or .severity == "Critical") | not)
            or ((.fixState == "wont-fix" or .fixState == "not-fixed") | not)
            or ((.decision | text) | not)
            or ((.debianStatus | text) | not)
            or ((.rationale | text) | not)
            or ((.mitigation | text) | not)
            or ((.reviewTrigger | text) | not)
            or ((.packages | type) != "array")
            or ((.packages | length) == 0)
            or (any(.packages[]; ((.name | exact) and (.version | exact) and (.type | exact)) | not))
        )
        | "entry \(.id // "without an identifier") is incomplete or not exact"
    end' "$accepted" 2>&1)"
if [ "$?" -ne 0 ] || [ -n "$invalid" ]; then
    echo "SCAN_GATE_UNUSABLE the accepted list: ${invalid:-it cannot be read}"
    exit 2
fi

if ! lines="$(jq -r --slurpfile list "$accepted" '
    def severe: (.vulnerability.severity // "" | ascii_downcase) as $s | $s == "high" or $s == "critical";
    def exploited:
        ((.vulnerability.knownExploited // []) | length > 0)
        or any(.relatedVulnerabilities[]?; (.knownExploited // []) | length > 0);
    def state: .vulnerability.fix.state // "unknown";
    def fixes: .vulnerability.fix.versions // [];
    def fixable: state == "fixed" or (fixes | length > 0);
    def key: "\(.vulnerability.id) \(.vulnerability.severity) \(.artifact.name) \(.artifact.version) \(.artifact.type)";
    def epss: [(.vulnerability.epss // [])[]?.epss, (.relatedVulnerabilities[]?.epss // [])[]?.epss] | max // "none";
    ($list[0].accepted | map(. as $entry | .packages[] | "\($entry.id) \($entry.severity) \(.name) \(.version) \(.type)")) as $known
    | .matches[]
    | (if severe | not then "REPORTED"
       elif exploited then "REFUSED_KNOWN_EXPLOITED"
       elif fixable then "REFUSED_FIXABLE"
       elif (state == "wont-fix" or state == "not-fixed") | not then "REFUSED_FIX_STATE"
       elif (key as $key | $known | index($key)) == null then "REFUSED_NOT_ACCEPTED"
       else "ACCEPTED" end) as $verdict
    | "\($verdict) \(key) fix=\(state):\(fixes | join(",")) exploited=\(if exploited then "yes" else "no" end) epss=\(epss)"' "$report" | sort -u)"; then
    echo "SCAN_GATE_UNUSABLE the report could not be judged"
    exit 2
fi

if [ -n "$lines" ]; then
    printf '%s\n' "$lines"
fi

# An entry the scan no longer reports is printed, so that the list can be reduced;
# it passes nothing and refuses nothing.
jq -r --slurpfile report "$report" '
    ($report[0].matches | map("\(.vulnerability.id) \(.vulnerability.severity) \(.artifact.name) \(.artifact.version) \(.artifact.type)")) as $seen
    | .accepted[] | . as $entry | .packages[]
    | "\($entry.id) \($entry.severity) \(.name) \(.version) \(.type)"
    | select(. as $key | ($seen | index($key)) == null)
    | "ACCEPTED_NOT_REPORTED \(.)"' "$accepted"

count() {
    if [ -z "$lines" ]; then
        echo 0
    else
        grep -c "$1" <<<"$lines"
    fi
}

accepted_count="$(count '^ACCEPTED ')"
refused_count="$(count '^REFUSED_')"
reported_count="$(count '^REPORTED ')"
echo "Scan gate: $accepted_count accepted, $refused_count refused, $reported_count of lower severity reported"

if [ "$refused_count" -gt 0 ]; then
    exit 1
fi
exit 0
