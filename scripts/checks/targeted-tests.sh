#!/usr/bin/env bash
# The targeted tests of a fast run (EXECUTION_CONTEXT CE-46, D-117, D-133):
# targeted-tests.sh <base>. Each PHP test file under tests/Feature and tests/Concurrency
# that the branch adds or changes since <base> runs on its own in the application
# container, every file even after an earlier one failed, so that one run reports every
# failure (D-133). It replaces no suite: the whole suites run on the pull request's
# final head. The status is 0 when every file passed or none changed, 1 when any file
# failed, and 2 when the change cannot be read, so that an unknown change never passes.
set -uo pipefail

base="${1:-}"
if [ -z "$base" ] || ! git rev-parse --verify --quiet "$base^{commit}" > /dev/null; then
    echo "TARGETED_UNUSABLE the base ${base:-(none)} cannot be read"
    exit 2
fi

if ! changed="$(git diff --name-only --diff-filter=ACMR "$base...HEAD" -- tests/Feature tests/Concurrency)"; then
    echo "TARGETED_UNUSABLE the change since $base cannot be read"
    exit 2
fi

passed=0
failed=0
failures=""
while IFS= read -r file; do
    case "$file" in
        *Test.php) ;;
        *) continue ;;
    esac
    echo "Targeted: $file"
    if docker compose exec -T app php vendor/bin/phpunit "$file" < /dev/null; then
        passed=$((passed + 1))
    else
        failed=$((failed + 1))
        failures="$failures $file"
        echo "Targeted tests: FAIL $file"
    fi
done <<< "$changed"

if [ "$failed" -ne 0 ]; then
    echo "Targeted tests: $passed passed, $failed failed:$failures"
    exit 1
fi

echo "Targeted tests: $passed passed, 0 failed"
exit 0
