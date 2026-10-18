# TASK-037 — Restore tooling and recovery procedures

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 7 Onboarding, reconciliation and operations · lane O, operations and delivery |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-013](TASK-013-independent-backup.md), [TASK-035](TASK-035-onboarding.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-037-restore-tooling` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Operations, Commands, Access, Inventory |
| Baseline | RF-7, D-090 |

## 1. Goal

Everything a restore needs on the application side, and its rehearsal on synthetic data: the after-restore fence OP-04, the Owner's reconciliation OP-05, the drill record OP-08, the sequence advance, the grants on a restored database, and the local rehearsal of the restore runbook and the drill.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- BACKUP_RECOVERY sections 1, 8, 10 (RB-01), 11, 12 and 13 (RB-02)
- SECURITY_MODEL SEC-OP-04, SEC-OP-05, SEC-OP-08 and section 14
- ADR-007 section 6; ADR-009 sections 1 and 3
- SCHEMA sections 5.3, 5.7 (holds), 5.12 (`restore_drills`), SCH-28, G-12 and G-26
- TECHNICAL_RULES section 7.2 (after a restore; restore reconciliation), RACE-12 and RACE-34
- INTERACTION_CONTRACTS sections 2.1 and 2.3
- ROUTE_CONTRACTS OP-04, OP-05 and OP-08
- TEST_STRATEGY TS-24; D-072, D-073, D-089

## 4. Scope

### 4.1 In scope

1. Create `restore_drills`.
2. Implement OP-04 `restore:after`: a recovery epoch never issued before; every sign-in session revoked; an onboarding restore hold when the restored state is not RELEASED; audit; security log.
3. Implement OP-05 `restore:reconcile`: for each reported intent, NOT_COMMITTED with a fence in the current epoch, its `intent_reconciliations` row, the audit event `RESTORE_RECONCILIATION` and the settlement of the intent's PENDING or HELD receipts, in one transaction; for an onboarding hold, "released before the restore" (the release record of kind `RESTORE_RECONCILIATION`; state RELEASED) or "never released" (the period continues).
4. Implement OP-08 `restore:record-drill`.
5. Write the sequence-advance script for the migration role (at least 100,000 above the restored value and above every higher number seen in the gap review; SCH-28).
6. Write the TS-24 helpers that dump and restore a test database inside a test and then run OP-04.
7. Write the operator's scripts and checklists of RB-01 phases C to F and RB-02, and rehearse them locally with synthetic data and the local S3-compatible store: decryption on a separate container standing for the operator's machine, never in the application's container; `pg_restore` through a tunnel to the target; the grants procedure; the privilege test; counts against the manifest; the reconciliation; the application started with `/up`; a receipt and a report rendered through an OP-02 link on the target; the durations measured.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `restore_drills` |
| Keys completed (EXC-44) | — |
| Routes | OP-04, OP-05, OP-08 |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Restores and drills on staging or production (the operator; TASK-045, TASK-050).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CMD-03 | REQ-NFR-05, REQ-INV-07, REQ-OPS-01, REQ-OPS-04 |
| Workflows | WF-32 | — |
| User tasks | — | — |
| Acceptance suites | REC02 (final: TASK-045) | REC01, OPPROC01, OPEN01, SESSION05, STATUS01 |
| Races | RACE-12, RACE-34 | — |
| Guards | — | G-12, G-26 |
| Threats | THR-17 | THR-10 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-037-01** OP-04 sets an epoch never issued before and revokes every session; stale intents are refused `EPOCH_STALE`, also after the same backup is restored twice (RACE-12).
- **AC-037-02** The sequences are advanced so that no number issued after the backup point repeats.
- **AC-037-03** A status check of an earlier-epoch intent whose identity is in the restored data answers COMMITTED; otherwise `EPOCH_STALE` with one report; OP-05 records NOT_COMMITTED with a fence and settles the receipts in the same transaction; a late original is refused `INTENT_ABANDONED`; RACE-34 gives its outcome.
- **AC-037-04** The Owner's user-management requests can be reconciled before the security changes after the backup point are re-applied; the onboarding hold and its two outcomes behave as D-072 says (the restore case of OPEN01).
- **AC-037-05** An earlier-epoch HELD receipt keeps a close refused until it is answered COMMITTED or OP-05 settles it (SESSION05).
- **AC-037-06** The local rehearsal of RB-01 and RB-02 passes and records its durations; no decryption happens in the application's container.
- **AC-037-07** The procedures follow the common rules of SECURITY_MODEL section 14.

## 7. Tests

- L2 and L3 with TS-24 restores: RACE-12, RACE-34, the hold.
- L2 procedure tests of OP-04, OP-05 and OP-08.
- Local L6 rehearsal of RB-01 and RB-02.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-037.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The rehearsal's steps and durations.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | Applies — migrations reviewed and rehearsed (EXC-41 to EXC-46); the constraint, deferred, privilege and retention families for its tables |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A step would need a credential of staging or production (EXC-13).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- BACKUP_RECOVERY (only a correction of a step found wrong in rehearsal, recorded)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-039 shows the drill status; TASK-045 has the operator rehearse on staging; TASK-050 runs the first production drill.
- Tasks that depend on this one directly: [TASK-039](TASK-039-system-status.md).

## 13. Expected files

- `app/Console/Commands/` (`restore:after`, `restore:reconcile`, `restore:record-drill`)
- The sequence-advance script and the operator checklists
- `tests/` TS-24 helpers
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
