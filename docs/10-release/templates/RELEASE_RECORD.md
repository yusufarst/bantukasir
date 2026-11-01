# Release record — `<version>`

The template of RELEASE_PROCESS section 9. One file per release, `docs/10-release/records/RELEASE-<version>.md`, written from the operator's report; the folder is created with the first record (TASK-050). No real identity is written here: no name, address, domain, provider account, credential or backup location (SECURITY_MODEL SEC-SECRET-05).

## Revision

| Field | Value |
| --- | --- |
| Version | |
| Commit | |
| Tag | |
| Image digest | |

## Authorization (REL-01)

| Field | Value |
| --- | --- |
| The Owner's authorization: its date and reference | |
| The target environment | |
| The operation: deploy, the reviewed list of migrations, any operator procedure | |
| The window | |
| The operator, by role only | |

## Migrations (REL-20, REL-23)

The release record's list, as `release:preflight` and the migration job take it (`--migrations`): the names separated by commas, or `none`.

```text
<names, or none>
```

| Migration | Class | Expected duration | Lock impact | Destructive: the Owner's approval of that exact operation (REL-06) |
| --- | --- | --- | --- | --- |
| | | | | none |

The decisions named here reach the job as `--destructive-approvals`.

## The window (RB-20)

| Step | Result | Time (Asia/Jakarta) |
| --- | --- | --- |
| The backup schedule paused | | |
| The pre-release backup's identifier (`backup:run --reason=PRE_RELEASE`) | | |
| Maintenance mode on, with the operator's bypass | | |
| The migration job: its `PREFLIGHT_PASSED` line | | |
| The migration job: the migrations applied | | |
| The migration job: `JOB_PASSED`; the `VERIFY_COUNT` lines hold production's row counts, so they stay in the operator's private record (OPERATIONS_RUNBOOK OPR-03) | | |
| The digest deployed to the application and backup resources, with `APP_RELEASE_IMAGE_DIGEST` set to it and, with maintenance mode on, `APP_START_IN_MAINTENANCE=1` on the application | | |
| The checks of RB-21 (RELEASE_PROCESS section 7) | | |
| Maintenance mode off, and `APP_START_IN_MAINTENANCE` removed | | |
| The backup schedule resumed, and the backup run at once | | |

## Incidents and rollbacks

None, or each with its reference (OPERATIONS_RUNBOOK RB-03, RELEASE_PROCESS RB-22).

## The Owner's acceptance

The reference to DECISIONS, when he accepts the release.

## Integrity report

Attached below: the release workflow's filled copy of `docs/10-release/templates/RELEASE_INTEGRITY_REPORT.md`, completed.
