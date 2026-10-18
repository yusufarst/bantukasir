# TASK-014 — Release workflow, preflight and migration job

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 3 Delivery infrastructure · lane O, operations and delivery |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-013](TASK-013-independent-backup.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-014-release-pipeline` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Operations, CI |
| Baseline | RF-7, D-090 |

## 1. Goal

The controlled path from an authorized tag to a published image and an applied migration: the release workflow with its image checks and integrity report, the release preflight, the migration job, the behaviour of commands under maintenance mode, and the templates of the release record.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- ADR-011 items 3 to 7
- RELEASE_PROCESS sections 1 to 9 (REL-01 to REL-10, REL-20 to REL-24, RB-20 to RB-22)
- TEST_STRATEGY sections 6 and 7
- PRODUCTION_DATA_SAFETY sections 1, 2 and 5
- SCHEMA SCH-50, SCH-51 and SCH-54
- ENVIRONMENTS ENV-04, ENV-31 and ENV-32
- INTERACTION_CONTRACTS section 2 and RELEASE_PROCESS section 6 (commands under maintenance mode)

## 4. Scope

### 4.1 In scope

1. Write the `release` workflow of ADR-011 item 5: it runs only for a release tag `vMAJOR.MINOR.PATCH`, and for the candidate tags whose scheme this Task fixes and records for staging candidates; builds the image of TASK-012 from the tagged commit; runs the image checks; publishes to the GitHub Container Registry with version and commit labels using only the token GitHub issues to the workflow; never publishes from a fork; outputs the digest. A dry run that builds and checks without publishing runs in CI when release files change.
2. Write the release integrity report of RELEASE_PROCESS section 4 as a template the workflow fills, and the release record template for `docs/10-release/records/`, whose folder is created with the first record (TASK-050).
3. Implement `release:preflight` (REL-23): the operator's typed environment name equals the configuration and the database marker; in production a successful `backup_runs` row less than one hour old exists; the pending migrations equal the release record's list; no migration is marked destructive without its approval reference (REL-06); `synchronous_commit` is on; the backup schedule is paused, confirmed by the operator; any failure stops before the first statement.
4. Define the migration job of ADR-011 item 7: a one-off container of the release image on the private network, the migration credential supplied at run time, the preflight first, then the migrations, then the verification queries of REL-20 (row counts, constraints valid, `db:check-privileges`, and `reconciliation:run` once TASK-036 provides it).
5. Make commands under maintenance mode answer before any route runs, leaving no receipt and no fact, with an operator bypass from configuration; the client reads them as UNCERTAIN (RELEASE_PROCESS section 6), proven in the browser with the probe operation.
6. Complete the migration review check with the REL-06 approval reference and require each migration's REL-20 metadata — class, expected duration, lock impact, verification queries.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | — |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Tagging, pushing and deploying any release (the Owner's authorization and the operator; TASK-044, TASK-049, TASK-050).
- The rehearsal of migrations on the populated dataset (TASK-043).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-OPS-02, REQ-OPS-03, REQ-OPS-05 |
| Workflows | — | WF-32 |
| User tasks | — | — |
| Acceptance suites | — | DB01, READY01, SEC01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-14 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-014-01** The release workflow cannot run for anything but a release or candidate tag, holds no environment secret, never publishes from a fork, and labels the image with version and commit; its dry run passes in CI.
- **AC-014-02** The preflight refuses each condition of REL-23 in a test that induces it, before the first statement.
- **AC-014-03** The migration job runs the preflight, the migrations and the verification queries in that order; the migration credential is never stored in an environment of the application or backup container.
- **AC-014-04** Under maintenance mode a command leaves no receipt and no fact, and the client shows UXS-14 for it.
- **AC-014-05** The migration review check refuses a destructive statement without its approval reference and a migration without its REL-20 metadata.

## 7. Tests

- Workflow dry run (CI).
- L2 preflight refusals.
- L5 maintenance mode with the probe operation.
- Static migration review with negative samples.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-014.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The candidate tag scheme chosen.
- The dry run's output and the template of the integrity report.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | N/A — no request path |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Publishing would need any credential other than the workflow's own token.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- RELEASE_PROCESS section 3 (the candidate tag scheme)
- TEST_STRATEGY section 6 (the migration review check's exact form, if it differs)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-044 tags the first candidate under its own authorization; the operator uses the job and the preflight from TASK-045 on.
- Tasks that depend on this one directly: [TASK-043](TASK-043-performance-harness.md).

## 13. Expected files

- `.github/workflows/release.yml`
- `app/Console/Commands/` (`release:preflight`)
- The release integrity report and release record templates
- The migration job script

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
