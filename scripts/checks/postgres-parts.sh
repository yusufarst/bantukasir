#!/usr/bin/env bash
# The parts of the PostgreSQL suite (TEST_STRATEGY sections 6 and 7, D-120, D-131):
# postgres-parts.sh [<configuration>]. CI runs `composer test:postgres` as its parts
# `composer test:postgres:1` to `composer test:postgres:<n>`, the suites Postgres1 to
# Postgres<n> of phpunit.xml, each in its own job on its own runner and test database.
# The parts are the suites named Postgres followed by a number; they are numbered
# from 1 without a gap, and there are at least two. This check lists the tests of
# Postgres and of each part with PHPUnit itself and proves that the parts together
# hold exactly the tests of Postgres, each test in one part and once, and that no
# part is empty. It runs no test and needs no database. The status is 0 when the
# parts cover the suite, 1 when they do not, and 2 when a list cannot be read, so
# that an unreadable list never passes.
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

list Postgres

# The parts, in the order of their numbers: the suites Postgres<number> of the
# configuration, which must be Postgres1 to Postgres<n> with n of at least 2.
numbers="$(grep -oE '<testsuite[[:space:]]+name="Postgres[0-9]+"' "$configuration" | grep -oE '[0-9]+"$' | tr -d '"' | LC_ALL=C sort -n)"
n="$(printf '%s\n' "$numbers" | grep -c .)"
if [ "$n" -lt 2 ] || [ "$numbers" != "$(seq 1 "$n")" ]; then
    echo "POSTGRES_PARTS_NUMBERING the parts of $configuration are not Postgres1 to Postgres<n> with n of at least 2: $(printf '%s ' $numbers)"
    exit 1
fi

parts=()
for k in $(seq 1 "$n"); do
    parts+=("Postgres$k")
done

for part in "${parts[@]}"; do
    list "$part"
    if [ ! -s "$work/$part" ]; then
        echo "POSTGRES_PARTS_EMPTY $part holds no test"
        problems=1
    fi
    LC_ALL=C uniq -d "$work/$part" > "$work/$part.twice"
    report "$work/$part.twice" POSTGRES_PARTS_TWICE "listed twice in $part"
done

# A test of two parts appears once in each part's list, so twice among them all.
for part in "${parts[@]}"; do
    LC_ALL=C sort -u "$work/$part"
done | LC_ALL=C sort | LC_ALL=C uniq -d > "$work/several"
for part in "${parts[@]}"; do cat "$work/$part"; done | LC_ALL=C sort -u > "$work/parts"
LC_ALL=C sort -u "$work/Postgres" > "$work/suite"
LC_ALL=C comm -23 "$work/suite" "$work/parts" > "$work/neither"
LC_ALL=C comm -13 "$work/suite" "$work/parts" > "$work/outside"

report "$work/several" POSTGRES_PARTS_OVERLAP "in more than one part"
report "$work/neither" POSTGRES_PARTS_GAP "of Postgres in no part"
report "$work/outside" POSTGRES_PARTS_OUTSIDE "in a part but not in Postgres"

if [ "$problems" -ne 0 ]; then
    exit 1
fi

sum=""
for part in "${parts[@]}"; do
    sum="${sum:+$sum + }$part $(count "$part")"
done
echo "POSTGRES_PARTS_COMPLETE $sum = Postgres $(count Postgres) tests, each in one part"
exit 0
