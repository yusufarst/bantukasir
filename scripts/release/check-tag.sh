#!/usr/bin/env bash
# Refuses anything but a release or candidate tag (ADR-011 items 3 and 5;
# RELEASE_PROCESS section 3; TASK-014 scope item 1), whatever GitHub's filter let
# through, and prints which it is:
#
#   vMAJOR.MINOR.PATCH         a release, whose commit is on main
#   vMAJOR.MINOR.PATCH-rc.N    a candidate, whose commit is on develop or main
#
# Usage: check-tag.sh <tag> [<commit>]. With a commit it also checks the branches,
# which need the history of the remote's branches; without one, only the form.
set -euo pipefail

tag="${1:-}"
commit="${2:-}"
number='(0|[1-9][0-9]*)'

if [[ "$tag" =~ ^v$number\.$number\.$number$ ]]; then
    kind=release
    branches=(origin/main)
elif [[ "$tag" =~ ^v$number\.$number\.$number-rc\.[1-9][0-9]*$ ]]; then
    kind=candidate
    branches=(origin/develop origin/main)
else
    echo "TAG_REFUSED the tag is neither vMAJOR.MINOR.PATCH nor vMAJOR.MINOR.PATCH-rc.N"
    exit 1
fi

if [ -n "$commit" ]; then
    on_branch=no
    for branch in "${branches[@]}"; do
        if git merge-base --is-ancestor "$commit" "$branch"; then
            on_branch=yes
        fi
    done

    if [ "$on_branch" != yes ]; then
        echo "TAG_REFUSED the commit of this $kind is not on ${branches[*]}"
        exit 1
    fi
fi

echo "TAG_ACCEPTED $kind"
