# TASK-045 — Operations verification

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 9 Verification · lane V, verification |
| Size and review | L · review class V — verification results accepted by the Owner ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-044](TASK-044-integrated-verification.md) |
| Gates | TARGET, G4 (store account, buckets and keys), The operator named by the Owner ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-045-operations-verification` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | Operations |
| Baseline | RF-7, D-090 |

## 1. Goal

`FINAL_PASS` of the operational suites on staging, a drill target off the VPS and the intended server, run by the operator from scripts the executor prepares: migrations and privileges, retention, backup and isolated restore, the after-restore procedures, operator procedures, system status, infrastructure exposure, and the readiness checks available before a release.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- TEST_STRATEGY section 12
- ACCEPTANCE_SUITES DB01, KEEP01, REC01, REC02, OPPROC01, STATUS01, SEC02 and READY01
- ENVIRONMENTS sections 5 to 17
- BACKUP_RECOVERY sections 8, 10, 12 and 13
- OPERATIONS_RUNBOOK (all)
- RELEASE_PROCESS RB-20, RB-21 and RB-23 steps 1 to 6
- UAT_PILOT UAT-20, UAT-23 and UAT-24
- EXECUTION_CONTEXT section 16

## 4. Scope

### 4.1 In scope

1. Prepare for the operator the checklists, scripts and expected results of every step below; never hold a credential (EXC-13).
2. Operator: the record of gate TARGET (ENVIRONMENTS section 17); staging and production environments created as section 6 says; the staging deployment of the candidate with RB-20, the migration job and the preflight; the checks of RB-21; the privilege test on the target.
3. Operator: backups on staging; a restore drill RB-02 of a staging backup on a drill target off the VPS; the rehearsal of RB-01 phases D and E with the Owner's time measured (UAT-24); every operator procedure OP-01 to OP-08 rehearsed on staging, with RB-10 and RB-11 (OPPROC01); UAT-20 and UAT-23 with the Owner.
4. Operator: SEC02 from outside the server; each heartbeat alert received once; the status evidence on the target (STATUS01).
5. READY01 on the candidate: the release integrity report of TASK-014, the image checks, the scans clean or each finding accepted with its reason, the publication guard, the cost inventory and the free-tier record checked again, staging on the same digest, no `FINAL_PASS` invalidated, and the Owner's approval of OPERATIONS_RUNBOOK, BACKUP_RECOVERY and RELEASE_PROCESS after their rehearsal.
6. Record the results without real identities (EXC-62), and the measured times that gate G4 needs (BACKUP_RECOVERY section 12; the production measurements follow in TASK-050).

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

- The production drill and gate G4's acceptance (TASK-050).
- UAT by the shop's people other than UAT-20, UAT-23 and UAT-24 (TASK-049).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-OPS-06, REQ-NFR-05 | REQ-OPS-01, REQ-OPS-02, REQ-OPS-04, REQ-OPS-05, REQ-NFR-07 |
| Workflows | — | WF-32 |
| User tasks | — | — |
| Acceptance suites | SEC02 (final: TASK-045), READY01 (final: TASK-045) | — |
| Races | — | — |
| Guards | — | — |
| Threats | THR-16 | THR-15, THR-17 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-045-01** DB01, KEEP01, REC01, REC02, OPPROC01, STATUS01, SEC02 and READY01 — the suites whose final point is operations verification — are `FINAL_PASS` on the candidate, each with the operator's report summarized without identities.
- **AC-045-02** Staging and production share no database, volume or secret, compared by fingerprint without revealing a value; staging is behind its access gate and answers `noindex`.
- **AC-045-03** The measured restore time of the drill and the Owner's reconciliation time on staging are recorded for gate G4.

## 7. Tests

- The operational suites at L6 on the targets named.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-045.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The operator's reports, summarized without identities.

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

- A step would need an agent to reach staging or production (EXC-13).
- A finding needs a business decision.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-049 runs READY01 again if the candidate changes afterwards (TS-32); TASK-050 runs the production drill.
- Tasks that depend on this one directly: [TASK-049](TASK-049-release-readiness.md).

## 13. Expected files

- The operator checklists and scripts
- The evidence file

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
