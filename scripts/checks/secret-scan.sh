#!/usr/bin/env bash
# Secret scan (TEST_STRATEGY section 6, SEC-SECRET-01, D-076): Gitleaks, with the
# configuration .gitleaks.toml, on the tree — every tracked file and every
# untracked file that Git does not ignore — and on the commits a push would add.
#
# Usage: bash scripts/checks/secret-scan.sh [REPOSITORY]
#   SECRET_SCAN_LOG_OPTS  the git log options that select the commits to scan;
#                         default "HEAD --not --remotes", the commits that are on
#                         no remote-tracking branch. CI passes its event's range.
# Findings are printed redacted. The tree is copied to a temporary directory
# outside the repository, which this script removes on exit.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
repository="$(cd "${1:-$root}" && pwd)"
config="${SECRET_SCAN_CONFIG:-$root/.gitleaks.toml}"
log_opts="${SECRET_SCAN_LOG_OPTS:-HEAD --not --remotes}"

tree="$(mktemp -d "${TMPDIR:-/tmp}/bantukasir-secret-scan-tree.XXXXXX")"
trap 'rm -rf -- "$tree"' EXIT

while IFS= read -r -d '' path; do
    if [ -f "$repository/$path" ] && [ ! -L "$repository/$path" ]; then
        mkdir -p "$tree/$(dirname "$path")"
        cp -- "$repository/$path" "$tree/$path"
    fi
done < <(git -C "$repository" ls-files -z --cached --others --exclude-standard)

status=0

echo "Secret scan of the tree:"
gitleaks dir --config "$config" --redact --no-banner --no-color "$tree" || status=1

echo "Secret scan of the commits ($log_opts):"
gitleaks git --config "$config" --redact --no-banner --no-color --log-opts="$log_opts" "$repository" || status=1

exit "$status"
