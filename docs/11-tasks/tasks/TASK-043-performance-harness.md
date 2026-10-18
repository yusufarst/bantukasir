# TASK-043 — Performance harness and provisional dataset

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 9 Verification · lane V, verification |
| Size and review | M · review class B — self-review and the reviewer's check ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-041](TASK-041-owner-home-daily.md), [TASK-042](TASK-042-audit-views.md), [TASK-038](TASK-038-housekeeping.md), [TASK-014](TASK-014-release-pipeline.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-043-performance-harness` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Test harness |
| Baseline | RF-7, D-090 |

## 1. Goal

The provisional benchmark dataset and the ten-year growth dataset built through the Actions, the load tool and scenarios of PERF01, a local dry run with recorded results, and the rehearsal of every migration on a populated database in CI.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- TECHNICAL_RULES section 13
- ACCEPTANCE_SUITES PERF01
- TEST_STRATEGY section 2 (L7) and section 3
- RELEASE_PROCESS REL-21
- OPEN_ITEMS T-09
- ENVIRONMENTS section 2 (performance target)

## 4. Scope

### 4.1 In scope

1. Write generators for the base dataset of TECHNICAL_RULES section 13.2 (5,000 active and 500 inactive products in 50 categories, about 7,500 active tokens with retired ones, 100,000 movement lines with one product of 10,000 movements, three registers, two cashiers) and for the ten-year growth dataset, synthetic and created through the Actions, runnable only where the marker is `test` or `local`.
2. Choose an open-source load tool and record its licence; script the scenarios of section 13.1 with two cashiers checking out while the Owner runs a report, plus the CSV import of 5,000 rows and the replay of 10,000 movements; record per run the versions, configuration, indexes, host, dataset, run count, p95 and a reconciliation before and after.
3. Run the scenarios locally as a dry run and record the results as `NOT_RUN` for PERF01, never as its pass.
4. Add the CI job that rehearses every migration on the populated base dataset (REL-21).

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

- PERF01 on the intended server (TASK-048).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-NFR-04, REQ-COST-06, REQ-OPS-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | PERF01 (final: TASK-048) | DB01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | T-09 — the provisional dataset; the final one waits for gate G2 (TASK-048) | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-043-01** Both datasets are reproducible from a seed, created through the Actions and reconcile cleanly after generation.
- **AC-043-02** The scenarios and their recording format cover every budget of TECHNICAL_RULES section 13.1.
- **AC-043-03** The local dry run is recorded and labelled as no PERF01 evidence.
- **AC-043-04** The CI rehearsal of migrations on the populated dataset passes.

## 7. Tests

- Dataset generation with a reconciliation after it.
- The dry run of every scenario.
- The CI migration rehearsal.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-043.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The load tool and its licence.
- The dry-run results.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the suites and criteria of section 6 at their final point |
| UI and UX | Applies where the suites of section 5 touch the interface |
| Backend | Applies through the suites; no new code unless change control adds a Task (EXC-82) |
| Database | Applies through the suites; no new migration |
| Security | Applies — no agent reaches staging or production (EXC-13); no identity or secret in evidence |
| Data integrity | Applies through the suites |
| Performance | Applies only where PERF01 is in scope |
| Operations | Applies — operator steps prepared and recorded (EXECUTION_CONTEXT section 16) |
| Verification | Applies — `FINAL_PASS` recorded per TS-30 for every suite of this final point |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- TOOLCHAIN (the load tool)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-048 runs PERF01 with this harness on the intended server.
- Tasks that depend on this one directly: [TASK-044](TASK-044-integrated-verification.md), [TASK-048](TASK-048-performance-verification.md).

## 13. Expected files

- `tests/Performance/` or the equivalent scenario folder
- The dataset generators
- The CI rehearsal job

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
