# Release integrity report — {{VERSION}}

The template of RELEASE_PROCESS section 4. The release workflow fills what it knows (`scripts/release/integrity-report.sh`); the executor completes each `TO_BE_COMPLETED`, without real identities, and the report is attached to the release record (section 9).

## Revision

| Field | Value |
| --- | --- |
| Version | {{VERSION}} |
| Tag | {{TAG}} |
| Commit | {{COMMIT}} |
| Image digest | {{DIGEST}} |
| CI run of the release workflow | {{RUN}} |

## Base images, by digest

{{BASE_IMAGES}}

## Lockfiles (SHA-256)

{{LOCKFILES}}

## Image checks of RELEASE_PROCESS section 4, on this image

{{CHECKS}}

## Scan results

The gate fails on what section 4 names; the list it reads is a temporary acceptance for development, which authorizes no deployment and does not replace the review of section 12.

{{SCAN}}

## Migrations of this revision, each with its class

The class is what each migration states. The rehearsal evidence of each (REL-21): TO_BE_COMPLETED.

{{MIGRATIONS}}

## Completed by a person

| Field | Value |
| --- | --- |
| The CI run of the commit on `develop` and its results | TO_BE_COMPLETED |
| The suites and their status on this revision (TEST_STRATEGY section 8) | TO_BE_COMPLETED |
| The publication guard's result (D-076) | TO_BE_COMPLETED |
| The compatibility of the previous image with the new schema (RB-22) | TO_BE_COMPLETED |
| Known issues | TO_BE_COMPLETED |
| The cost inventory unchanged, or its approved exceptions (COST_POLICY) | TO_BE_COMPLETED |
