# TASK-026 — Reversal of movements

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 5 Inventory and costing · lane I, inventory and costing |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-025](TASK-025-cost-correction.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-026-reversal` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Inventory, Costing |
| Baseline | RF-7, D-090; corrected under D-091 and D-092 |

## 1. Goal

The Owner's reversal of one eligible OPENING, RECEIPT or MANUAL_ISSUE as its exact inverse, once, with the replay that cancels the source logically, clears the source's cost marks and refuses an unsafe history.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY section 8 (BR-INV-34 to BR-INV-37)
- COSTING section 8
- ADR-008 section 4.3
- SCHEMA section 5.7 (`reversals`), section 5.8 (clearances), G-07 and G-11
- TECHNICAL_RULES section 7.2 (reversal) and RACE-10
- INTERACTION_CONTRACTS RC-10
- ROUTE_CONTRACTS RC-10 and RP-411
- SECURITY_MODEL CAP-17
- COPY_DECK section 9.12
- CRITICAL_FLOWS WF-23; D-087

## 4. Scope

### 4.1 In scope

1. Create `reversals` with its keys to `stock_movements`; complete the keys `stock_movements.reversal_id` → `reversals` and the clearance's key to a reversal.
2. Implement RC-10: the Owner; recent authentication; a reason; an eligible source not yet reversed; the product not blocked; the replay safe (G-07); the active marks of the source's evidence cleared; onboarding off for a RECEIPT or a MANUAL_ISSUE, while an OPENING may be reversed also during onboarding (D-087; proven with OPENING movements by TASK-035).
3. Extend the replay of TASK-025 to REVERSAL.
4. Build RP-411 (the exact inverse; stock now and after; an ineligible source shows why) and the entries on RP-403, RP-406 and RP-409.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `reversals` |
| Keys completed (EXC-44) | `stock_movements.reversal_id` → `reversals`, `cost_incomplete_mark_clearances` → `reversals (id, original_movement_id)` |
| Routes | RC-10, RP-411 |
| Route elements added | — |
| Pages | UXP-411 "Pembatalan mutasi" |
| Controls | 11: UXI-227, UXI-250, UXI-266, UXI-277, UXI-278, UXI-279, UXI-280, UXI-281, UXI-282, UXI-283, UXI-284 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The reversal of an OPENING during onboarding and its replacement (TASK-035).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-09, REQ-COST-08 | REQ-ACC-08 |
| Workflows | WF-23 | — |
| User tasks | UXT-31 | — |
| Acceptance suites | INV03 (final: TASK-044) | FIN03, AUTH03 |
| Races | — | RACE-10 |
| Guards | — | G-07, G-11 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-026-01** A reversal is the exact inverse of one eligible source, once; two concurrent reversals of the same source give one success (RACE-10).
- **AC-026-02** The fixture of COSTING section 8, with a RECEIPT standing in for its OPENING until TASK-035 proves it with a real OPENING, gives quantity 5, value 50 and issue cost 50; the invalid-history fixture is refused with no movement; SALE_ISSUE and RETURN_RECEIPT cannot be reversed.
- **AC-026-03** A RECEIPT or MANUAL_ISSUE is reversed only while onboarding is not active; a blocked product refuses the reversal.
- **AC-026-04** The source's active marks are cleared in the reversal's transaction.
- **AC-026-05** RP-411 follows the approved artifacts.

## 7. Tests

- L2 families for `reversals`.
- L1 and L2 the COSTING section 8 fixtures.
- L3 RACE-10 reversal part.
- L4 RC-10.
- L5 RP-411.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-026.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | Applies — migrations reviewed and rehearsed (EXC-41 to EXC-46); the constraint, deferred, privilege and retention families for its tables |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-035 proves the OPENING case and its replacement.
- Tasks that depend on this one directly: [TASK-028](TASK-028-checkout.md), [TASK-035](TASK-035-onboarding.md).

## 13. Expected files

- `app/Actions/Inventory/ConfirmReversal`
- `resources/js/pages/inventory/Reversal`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
