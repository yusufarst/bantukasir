# TASK-033 — Refunds and returns: correction core (server)

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | XL · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-031](TASK-031-documents-sales-lookup.md), [TASK-025](TASK-025-cost-correction.md), [TASK-028](TASK-028-checkout.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-033-refunds-core` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Sales, Inventory, Costing |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's correction of a Sale on the server — a refund, a return or both, paid from the Owner's own funds or by bank transfer — with separate, cumulative money and quantity bounds, saleable returns to stock and the cost they restore exactly once, linked to the original and reported on its posting date.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH section 8 (BR-RFD-01 to BR-RFD-17)
- COSTING section 6
- ADR-008 sections 3 and 4.3; D-035, D-061
- SCHEMA section 5.9 (`sale_corrections`, `refunds`, `refund_lines`, `return_lines`, the running totals), section 5.8 (`return_cost_allocations` and projections), G-06 and SCH-33
- TECHNICAL_RULES section 7.2 (Owner-funded or BANK_TRANSFER refund or return), RACE-10 and RACE-11
- INTERACTION_CONTRACTS RC-06
- ROUTE_CONTRACTS RC-06
- SECURITY_MODEL CAP-24 and THR-19
- CRITICAL_FLOWS WF-11

## 4. Scope

### 4.1 In scope

1. Create `sale_corrections`, `refunds`, `refund_lines`, `return_lines`, `return_cost_allocations` and `return_cost_allocation_projections`; complete the keys `stock_movements.return_line_id` → `return_lines`, `stock_movements.return_allocation_presence` → `return_cost_allocations` (deferred) and the presence key of a saleable return line to its movement (deferred).
2. Build the correction engine shared with RC-05 (TASK-034): the original Sale's guard row locked; the cumulative bounds by relative increments under check constraints (SCH-33) — refunds never above the applied payment, a line's refunds never above its line total, a line's returns never above its issued quantity; one command holding a refund, a return or both; a refund without return adds no stock; a damaged return adds no saleable stock; a saleable return posts RETURN_RECEIPT with a return allocation linked to the original issue allocation, restoring its cost once (D-061); onboarding off and the product not blocked when a saleable return posts; the business date; audit; idempotent.
3. Implement RC-06 for an Owner-funded or BANK_TRANSFER refund: the Owner; recent authentication; a reason; the payout recorded as made (BR-RFD-05); no session touched.
4. Extend the replay to RETURN_RECEIPT, recomputing each linked return projection together with its issue (ADR-008 section 4.3).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `sale_corrections`, `refunds`, `refund_lines`, `return_lines`, `return_cost_allocations`, `return_cost_allocation_projections` |
| Keys completed (EXC-44) | `stock_movements.return_line_id` → `return_lines`, `stock_movements.return_allocation_presence` → `return_cost_allocations` (deferred), `return_lines.movement_presence` → `stock_movements (return_line_id)` (deferred) |
| Routes | RC-06 |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Drawer-funded refunds and the refund pages (TASK-034).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-RFD-01, REQ-RFD-02, REQ-RFD-05, REQ-RFD-06, REQ-RFD-07, REQ-COST-07 | REQ-RFD-03, REQ-ACC-08, REQ-CMD-02 |
| Workflows | — | WF-11 |
| User tasks | — | — |
| Acceptance suites | RET01 (final: TASK-044) | FIN03, AUTH01 |
| Races | RACE-10 | RACE-11 |
| Guards | G-06 | G-10, G-11, G-13 |
| Threats | THR-19 | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-033-01** Only the Owner corrects; CASHIER, OPERATIONS_ADMIN and the combined account are refused on the server; a reason and recent authentication are required.
- **AC-033-02** The money and quantity bounds are separate and cumulative and hold under RACE-10: a CASH Sale of Rp75.000 paid with Rp100.000 is refunded at most Rp75.000; a partial line refund stays within its bound.
- **AC-033-03** A refund without return adds no stock; a damaged return adds no saleable stock; a saleable return posts RETURN_RECEIPT with its cost recovery.
- **AC-033-04** A refund without return keeps cost of goods sold; a saleable return restores the cost of its sale issue once; a correction after a return recovers cost exactly once.
- **AC-033-05** The command is atomic and idempotent; a failure injected after every write step leaves nothing; the original Sale, Payment, receipt and any closed report stay unchanged; the correction is linked and dated by its posting.
- **AC-033-06** The constraint, deferred and privilege families pass for the six tables.

## 7. Tests

- L2 families for the six tables.
- L3 RACE-10 and RACE-11 return side.
- L2 failure injection.
- L4 RC-06 and the capability matrix.
- L1 and L2 ADR-008 return cases.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-033.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The adversarial review of the correction engine.

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

- A bound cannot be held by the check constraints of SCHEMA section 5.9.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-034 uses the engine for RC-05 and builds the pages.
- Tasks that depend on this one directly: [TASK-034](TASK-034-drawer-refunds.md).

## 13. Expected files

- `app/Domain/Sales/Corrections/`, `app/Actions/Sales/RecordCorrection`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
