#!/usr/bin/env bash
# The two parts of the PostgreSQL suite (TEST_STRATEGY sections 6 and 7, D-120):
# postgres-parts.sh [<configuration>]. CI runs `composer test:postgres` as its parts
# `composer test:postgres:1` and `composer test:postgres:2`, the suites Postgres1 and
# Postgres2 of phpunit.xml, each in its own job on its own runner and test database.
# This check lists the tests of Postgres, Postgres1 and Postgres2 with PHPUnit itself
# and proves that the two parts together hold exactly the tests of Postgres, each test
# in one part and once, and that neither part is empty. It runs no test and needs no
# database. The status is 0 when the parts cover the suite, 1 when they do not, and 2
# when a list cannot be read, so that an unreadable list never passes.
set -uo pipefail

configuration="${1:-phpunit.xml}"
work="$(mktemp -d)"
trap 'rm -rf -- "$work"' EXIT

# The sorted IDs of a suite's tests, one per line, into $work/<suite>. An ID starts
# with the test's class, so that no other line of PHPUnit's output is taken for one.
list() {
    if ! php vendor/bin/phpunit --configuration "$configuration" --list-test-ids --testsuite="$1" > "$work/$1.out" 2>&1; then
        echo "POSTGRES_PARTS_UNREADABLE the tests of $1 cannot be listed from $configuration:"
        sed -n '1,20p' "$work/$1.out"
        exit 2
    fi
    grep -E '^[A-Za-z_][A-Za-z0-9_\\]*::' "$work/$1.out" | LC_ALL=C sort > "$work/$1"
}

count() { wc -l < "$work/$1" | tr -d ' '; }

problems=0

# A finding, from a file of test IDs: its code, how many, and the first 20 of them.
report() {
    if [ -s "$1" ]; then
        echo "$2 $(wc -l < "$1" | tr -d ' ') test(s) $3:"
        sed -n '1,20p' "$1"
        problems=1
    fi
}

for suite in Postgres Postgres1 Postgres2; do
    list "$suite"
done

for part in Postgres1 Postgres2; do
    if [ ! -s "$work/$part" ]; then
        echo "POSTGRES_PARTS_EMPTY $part holds no test"
        problems=1
    fi
    LC_ALL=C uniq -d "$work/$part" > "$work/$part.twice"
    report "$work/$part.twice" POSTGRES_PARTS_TWICE "listed twice in $part"
done

LC_ALL=C comm -12 "$work/Postgres1" "$work/Postgres2" > "$work/both"
LC_ALL=C sort -u "$work/Postgres1" "$work/Postgres2" > "$work/parts"
LC_ALL=C sort -u "$work/Postgres" > "$work/suite"
LC_ALL=C comm -23 "$work/suite" "$work/parts" > "$work/neither"
LC_ALL=C comm -13 "$work/suite" "$work/parts" > "$work/outside"

report "$work/both" POSTGRES_PARTS_OVERLAP "in both parts"
report "$work/neither" POSTGRES_PARTS_GAP "of Postgres in neither part"
report "$work/outside" POSTGRES_PARTS_OUTSIDE "in a part but not in Postgres"

if [ "$problems" -ne 0 ]; then
    exit 1
fi

echo "POSTGRES_PARTS_COMPLETE Postgres1 $(count Postgres1) + Postgres2 $(count Postgres2) = Postgres $(count Postgres) tests, each in one part"
exit 0
