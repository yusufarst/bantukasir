#!/usr/bin/env bash
# Fills the release integrity report (RELEASE_PROCESS section 4; TASK-014 scope item 2)
# from docs/10-release/templates/RELEASE_INTEGRITY_REPORT.md with what the workflow
# knows; what only a person knows stays TO_BE_COMPLETED in the template. Run from the
# repository's root.
#
# Usage: integrity-report.sh <log of the image checks> <output file>
# Needs: RELEASE_VERSION, RELEASE_COMMIT, RELEASE_TAG, RELEASE_DIGEST, RELEASE_RUN.
set -euo pipefail

log="$1"
output="$2"
template=docs/10-release/templates/RELEASE_INTEGRITY_REPORT.md

# The migrations of this revision, each with the class its own comment states.
migrations() {
    local file class
    for file in database/migrations/*.php; do
        class="$(grep -m 1 -oE 'Class: [a-z]+' "$file" | cut -d' ' -f2 || true)"
        printf -- '- `%s`: %s\n' "$(basename "$file" .php)" "${class:-no class stated}"
    done
}

export REPORT_VERSION="$RELEASE_VERSION"
export REPORT_COMMIT="$RELEASE_COMMIT"
export REPORT_TAG="$RELEASE_TAG"
export REPORT_DIGEST="$RELEASE_DIGEST"
export REPORT_RUN="$RELEASE_RUN"
REPORT_BASE_IMAGES="$(grep -E '^FROM [^ ]+@sha256:' docker/app/Dockerfile | sed -E 's/^FROM ([^ ]+).*/- `\1`/')"
REPORT_LOCKFILES="$(sha256sum composer.lock package-lock.json | sed -E 's/^([0-9a-f]+) +(.*)$/- `\2`: `\1`/')"
REPORT_MIGRATIONS="$(migrations)"
REPORT_CHECKS="$(grep -E '^(Image checks|Scan gate tests): ' "$log" | sed -E 's/^/- /' || true)"
REPORT_SCAN="$(grep -E '^(Database|Critical|High|Medium|Low|Negligible|Unknown): ' "$log" | sed -E 's/^/- /' || true)"
export REPORT_BASE_IMAGES REPORT_LOCKFILES REPORT_MIGRATIONS REPORT_CHECKS REPORT_SCAN

awk '
    BEGIN { count = split("VERSION COMMIT TAG DIGEST RUN BASE_IMAGES LOCKFILES MIGRATIONS CHECKS SCAN", names, " ") }
    {
        line = $0
        for (i = 1; i <= count; i++) {
            token = "{{" names[i] "}}"
            value = ENVIRON["REPORT_" names[i]]
            while ((at = index(line, token)) > 0) {
                line = substr(line, 1, at - 1) value substr(line, at + length(token))
            }
        }
        print line
    }
' "$template" >"$output"

if grep -q '{{' "$output"; then
    echo "REPORT_FAILED a field of the template was not filled"
    exit 1
fi

echo "REPORT_FILLED $output"
