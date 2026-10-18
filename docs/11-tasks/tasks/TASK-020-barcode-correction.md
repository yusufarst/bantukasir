# TASK-020 — Barcode misassignment correction

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 4 Catalogue · lane C, catalogue |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-017](TASK-017-catalog-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-020-barcode-correction` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Catalog |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's correction of a manufacturer barcode attached to the wrong product — moving an active token or re-attaching one retired as a misassignment — with its races against attaches and scans.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- CATALOG_BARCODE BR-CAT-19, BR-CAT-22 and BR-CAT-50 to BR-CAT-52; D-051, D-052
- SCHEMA section 5.6 and G-09
- TECHNICAL_RULES section 7.2 (barcode misassignment correction), RACE-17, RACE-18 and RACE-19
- INTERACTION_CONTRACTS RC-30 and sections 3.1 (the `correction` context) and 3.5
- ROUTE_CONTRACTS RC-30, RD-05 and RP-305
- SECURITY_MODEL CAP-07
- COPY_DECK sections 8.6 and 13.2
- CRITICAL_FLOWS WF-17

## 4. Scope

### 4.1 In scope

1. Implement RD-05 (the token's kind, active product, retirement records and whether it is correctable) and the `correction` context of RD-01 (active products only; CAP-07).
2. Implement RC-30: the Owner; recent authentication; a reason; both products' guards in ascending order; a manufacturer token only; a traceable prior mapping; for a re-attachment, a retirement recorded as a misassignment; at most one active mapping after commit.
3. Build RP-305 and the entry on RP-302.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RC-30, RD-05, RP-305 |
| Route elements added | RD-01: `correction` context |
| Pages | UXP-305 "Koreksi salah pasang barcode" |
| Controls | 10: UXI-174, UXI-190, UXI-191, UXI-192, UXI-193, UXI-194, UXI-195, UXI-196, UXI-197, UXI-198 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The Sale side of RACE-18 (TASK-028).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CAT-13 | REQ-ACC-08 |
| Workflows | WF-17 | — |
| User tasks | UXT-32 | — |
| Acceptance suites | BARCODE01 (final: TASK-044) | AUTH03 |
| Races | RACE-17, RACE-18, RACE-19 | — |
| Guards | — | G-09 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-020-01** Only the Owner corrects, with a reason and recent authentication; OPERATIONS_ADMIN, CASHIER and the combined account are refused.
- **AC-020-02** An active manufacturer token moves; a token retired as a misassignment is re-attached; a token retired for another reason and every `BK-P-…` code are refused.
- **AC-020-03** RACE-17 and RACE-19 leave exactly one active mapping; during RACE-18 a lookup resolves to one product, never none or both.
- **AC-020-04** RP-305 follows the approved artifacts.

## 7. Tests

- L3 RACE-17, RACE-18 (lookup side), RACE-19.
- L4 RC-30, RD-05 and the `correction` context.
- L5 RP-305.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-020.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-028 proves that a cart line taken before a move keeps its product.
- Tasks that depend on this one directly: [TASK-036](TASK-036-reconciliation-repair.md).

## 13. Expected files

- `app/Actions/Catalog/CorrectBarcodeMisassignment`
- `resources/js/pages/catalog/BarcodeCorrection`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
