#!/usr/bin/env bash
# The targeted tests of a fast run (EXECUTION_CONTEXT CE-46, D-117): targeted-tests.sh
# <base>. Each PHP test file under tests/Feature and tests/Concurrency that the branch
# adds or changes since <base> runs on its own in the application container, stopping
# at the first failure. It replaces no suite: the whole suites run on the pull
# request's final head. The status is 0 when every file passed or none changed, 1 on a
# failure, and 2 when the change cannot be read, so that an unknown change never passes.
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
while IFS= read -r file; do
    case "$file" in
        *Test.php) ;;
        *) continue ;;
    esac
    echo "Targeted: $file"
    if ! docker compose exec -T app php vendor/bin/phpunit "$file" < /dev/null; then
        echo "Targeted tests: FAIL $file after $passed passed"
        exit 1
    fi
    passed=$((passed + 1))
done <<< "$changed"

echo "Targeted tests: $passed passed, 0 failed"
exit 0
