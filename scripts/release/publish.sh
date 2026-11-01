#!/usr/bin/env bash
# Publishes the image that the image checks just passed to the GitHub Container
# Registry (ADR-011 item 5; TASK-014 scope item 1), with the token GitHub issues to the
# workflow and no other credential, under its version only, never a floating tag, and
# reports its digest. Every released image is kept for rollback, so a version that is
# already published is refused, never replaced. It prints no token.
#
# Needs: REGISTRY_TOKEN, REGISTRY_USER, RELEASE_IMAGE, RELEASE_VERSION, GITHUB_OUTPUT.
set -euo pipefail

checked=bantukasir-release:check
target="${RELEASE_IMAGE,,}:$RELEASE_VERSION"

trap 'docker logout ghcr.io >/dev/null 2>&1 || true' EXIT
printf '%s' "$REGISTRY_TOKEN" | docker login ghcr.io --username "$REGISTRY_USER" --password-stdin

if docker manifest inspect "$target" >/dev/null 2>&1; then
    echo "PUBLISH_REFUSED $RELEASE_VERSION is already published, and a released image is never replaced"
    exit 1
fi

docker tag "$checked" "$target"
docker push "$target"

reference="$(docker inspect --format '{{index .RepoDigests 0}}' "$target")"
digest="${reference#*@}"

if [[ ! "$digest" =~ ^sha256:[0-9a-f]{64}$ ]]; then
    echo "PUBLISH_FAILED no digest was reported"
    exit 1
fi

echo "digest=$digest" >>"$GITHUB_OUTPUT"
echo "PUBLISHED $target $digest"
