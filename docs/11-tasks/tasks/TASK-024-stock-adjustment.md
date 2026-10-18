# TASK-024 — Stock adjustment

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 5 Inventory and costing · lane I, inventory and costing |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-023](TASK-023-manual-issue.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-024-stock-adjustment` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Inventory, Costing |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's adjustment of stock to a count, with signed quantities, a reason, the count reference and recent authentication; a positive adjustment carries cost evidence and a negative one an issue allocation.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY section 7 (BR-INV-31 to BR-INV-33)
- COSTING sections 2 and 4
- ADR-008 sections 2 and 4.2
- SCHEMA section 5.7 (`adjustments`, `adjustment_lines`)
- TECHNICAL_RULES section 7.2 (the family)
- INTERACTION_CONTRACTS RC-09 and section 1.5
- ROUTE_CONTRACTS RC-09 and RP-410
- SECURITY_MODEL CAP-16
- COPY_DECK section 9.11
- CRITICAL_FLOWS WF-22

## 4. Scope

### 4.1 In scope

1. Create `adjustments` and `adjustment_lines`; complete the key `stock_movements.adjustment_line_id` → `adjustment_lines`.
2. Implement RC-09: the Owner; recent authentication; up to 200 lines with signed quantities; a reason, the count and a reference; a positive line with acquisition evidence KNOWN or UNKNOWN; a negative line with its issue allocation; never negative stock; audit.
3. Build RP-410 (a draft in page memory; an optional first product; RD-01 `adjustment`; review; the result at RP-402 filtered to its products) and its entries.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `adjustments`, `adjustment_lines` |
| Keys completed (EXC-44) | `stock_movements.adjustment_line_id` → `adjustment_lines` |
| Routes | RC-09, RP-410 |
| Route elements added | RD-01: `adjustment` context |
| Pages | UXP-410 "Penyesuaian stok" |
| Controls | 11: UXI-217, UXI-221, UXI-268, UXI-269, UXI-270, UXI-271, UXI-272, UXI-273, UXI-274, UXI-275, UXI-276 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Reversal (TASK-026), which never reverses an adjustment.

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-08 | REQ-ACC-08, REQ-INV-03 |
| Workflows | WF-22 | — |
| User tasks | UXT-30 | — |
| Acceptance suites | — | INV03, AUTH03 |
| Races | — | — |
| Guards | — | G-02 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-024-01** Only the Owner adjusts, with a signed quantity, a reason, a count, a reference and recent authentication; every other role and the combined account are refused.
- **AC-024-02** A positive adjustment carries cost evidence; a negative one carries its allocation; no adjustment makes stock negative.
- **AC-024-03** A failure injected after every write step leaves nothing; RACE-01 holds for RC-09.
- **AC-024-04** RP-410 follows the approved artifacts; its draft never reaches browser storage.

## 7. Tests

- L2 families for the two tables.
- L2 failure injection.
- L4 RC-09 and recent authentication.
- L5 RP-410.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-024.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- TASK-025 replays adjustments.
- Tasks that depend on this one directly: [TASK-025](TASK-025-cost-correction.md).

## 13. Expected files

- `app/Actions/Inventory/ConfirmAdjustment`
- `resources/js/pages/inventory/AdjustmentCreate`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
