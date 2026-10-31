# The S3-compatible test server of the backup's tests (TASK-013 scope item 7), the
# service backup-store of compose.yaml: MinIO (AGPL-3.0), unmodified, for the tests
# only, never in the release image and on no path of staging or production. It is
# built from its source at a pinned release, as age is in docker/app/Dockerfile,
# because its project no longer publishes the image: the Go module system verifies
# the sources against the checksum database. GOTOOLCHAIN is the Go of that release,
# which the module system also verifies.
FROM golang:1.27.3-trixie@sha256:c65ecfe613b539ac74c62a4c3b2c27e8f8a6076384c8f27e8a3c0f3be17cc385

ENV CGO_ENABLED=0 GOTOOLCHAIN=go1.24.2

RUN go install -trimpath github.com/minio/minio@RELEASE.2025-04-22T22-12-26Z

CMD ["/go/bin/minio", "server", "/data"]
