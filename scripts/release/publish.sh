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

# Only the registry's own answer that the version does not exist lets it go on: any
# other failure says nothing about what is published, so it refuses.
if answer="$(docker manifest inspect "$target" 2>&1)"; then
    echo "PUBLISH_REFUSED $RELEASE_VERSION is already published, and a released image is never replaced"
    exit 1
fi

case "$answer" in
    *"no such manifest"* | *"manifest unknown"* | *"name unknown"*) ;;
    *)
        echo "PUBLISH_REFUSED the registry could not say whether $RELEASE_VERSION is published"
        exit 1
        ;;
esac

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
