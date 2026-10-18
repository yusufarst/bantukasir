# TASK-049 — Release readiness: staging, UAT and first installation

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 10 Release · lane R, reports, audit views and release |
| Size and review | L · review class V — verification results accepted by the Owner ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-045](TASK-045-operations-verification.md), [TASK-047](TASK-047-p7-change-control-2.md), [TASK-048](TASK-048-performance-verification.md) |
| Gates | G1, G2, G3, G4 (the sealed copy handed over), DEVICES, TARGET, GATE-PUBLISH ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-049-release-readiness` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | All |
| Baseline | RF-7, D-090 |

## 1. Goal

Everything before the Owner's release authorization: the final candidate with every `FINAL_PASS` still valid, UAT by the shop's own people on staging, training and the shop's guidance, the rehearsal of the cutover, the business facts of gates G1 to G3, the backup keys and sealed copy, the naming of the operator, and the request for gate RELEASE.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- RELEASE_PROCESS sections 3, 4, 10 (RB-23 steps 1 to 6 and 10), 11 and 12
- UAT_PILOT sections 1, 2, 4 and 5
- ACCEPTANCE_SUITES READY01
- OPEN_ITEMS section 3
- BACKUP_RECOVERY BKP-20
- COST_POLICY section 3
- ENVIRONMENTS section 15
- EXECUTION_CONTEXT section 16

## 4. Scope

### 4.1 In scope

1. Confirm that every `FINAL_PASS` is still valid on the final candidate (TS-32); when the candidate changed after TASK-045 (for example through TASK-047), complete its release integrity report and run READY01 and every invalidated suite again.
2. With the operator present, the shop's people run UAT-01 to UAT-19, UAT-21 and UAT-22 on staging (UAT-20, UAT-23 and UAT-24 ran in TASK-045), each script recorded by the person who ran it and checked by the operator (UPR-02).
3. Train the cashiers, OPERATIONS_ADMIN and the Owner as UAT_PILOT section 4 says; finalize the guidance UG-01 to UG-13 with the Owner.
4. Rehearse the cutover RB-24 on staging with synthetic data.
5. Have the Owner record the facts of gates G1, G2 and G3; have the operator create the production backup keys and hand the Owner the tested sealed copy (BKP-20).
6. Check the cost inventory and the free-tier record again (REQ-NFR-06).
7. Confirm that the Owner has named the operator and approved the runbooks (TASK-045); prepare the request for gate RELEASE naming the exact target, revision, operation and window (REL-01).

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

- The production release and the pilot (TASK-050).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-NFR-06 | REQ-OPS-03, REQ-OPS-06, REQ-NFR-10 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | READY01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-14 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-049-01** Every `FINAL_PASS`, READY01 included, is valid on the final candidate; when the candidate changed after TASK-045, READY01 and every invalidated suite are `FINAL_PASS` again on it.
- **AC-049-02** Every UAT script is accepted by the person who ran it.
- **AC-049-03** Gates G1, G2 and G3 are recorded by the Owner; the sealed copy is handed over and tested.
- **AC-049-04** The request for gate RELEASE is ready for the Owner.

## 7. Tests

- READY01 again when the candidate changed (L6).
- UAT-01 to UAT-19, UAT-21 and UAT-22 at L9 on staging.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-049.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The UAT and training records, summarized without identities.

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

- A business fact of G1 to G3 changes a rule.
- A UAT finding is S1 or S2 (UPR-05).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- OPEN_ITEMS section 3 (the gates' facts)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-050 starts only with the Owner's authorization of gate RELEASE.
- Tasks that depend on this one directly: [TASK-050](TASK-050-production-pilot.md).

## 13. Expected files

- The release integrity report
- The evidence file

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
