# TASK-023 — Manual issue and issue-cost allocation

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 5 Inventory and costing · lane I, inventory and costing |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-022](TASK-022-receiving-stock-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-023-manual-issue` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Inventory, Costing |
| Baseline | RF-7, D-090 |

## 1. Goal

Recording goods that leave for a reason other than a sale, with the issue-cost allocation fact and its projection that every later issue — Sales and negative adjustments — reuses.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY section 5 (BR-INV-18 to BR-INV-22)
- COSTING sections 4 to 6
- ADR-008 sections 1, 2, 5, 6, 7, the invariants and the test list
- SCHEMA section 5.7 (`manual_issues`, `manual_issue_lines`), section 5.8 (`issue_cost_allocations`, `issue_cost_allocation_projections`), G-02, G-13 and G-14
- TECHNICAL_RULES section 7.2 (receiving, manual issue, adjustment family), RACE-03 and section 11.2
- INTERACTION_CONTRACTS RC-08
- ROUTE_CONTRACTS RC-08 and RP-407 to RP-409
- SECURITY_MODEL CAP-13; D-018
- COPY_DECK sections 9.8 to 9.10
- CRITICAL_FLOWS WF-20

## 4. Scope

### 4.1 In scope

1. Create `manual_issues`, `manual_issue_lines`, `issue_cost_allocations` and `issue_cost_allocation_projections`; complete the keys `stock_movements.manual_issue_line_id` → `manual_issue_lines` and `stock_movements.issue_allocation_presence` → `issue_cost_allocations` (deferred). The column `issue_cost_allocations.sale_line_id` is created here with its key to `stock_movements (id, sale_line_id)`; its key to `sale_lines` is completed by TASK-028 (EXC-44).
2. Build the Costing service of the issue allocation (ADR-008 section 2): the average before the issue, the remaining value when stock reaches zero, UNKNOWN while the valuation is unknown; the fact recorded at commit and its projection inserted equal to it (G-13, G-14).
3. Implement RC-08: a reason of the five of BR-INV-19; up to 200 lines added together per product; insufficient stock refused; no Sale and no cost of goods sold; audit.
4. Build RP-407, RP-408 (a draft; RD-01 `issue`; review) and RP-409; add the Owner's explicit "Barang keluar" entry (D-018).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `manual_issues`, `manual_issue_lines`, `issue_cost_allocations`, `issue_cost_allocation_projections` |
| Keys completed (EXC-44) | `stock_movements.manual_issue_line_id` → `manual_issue_lines`, `stock_movements.issue_allocation_presence` → `issue_cost_allocations` (deferred) |
| Routes | RC-08, RP-407, RP-408, RP-409 |
| Route elements added | RD-01: `issue` context |
| Pages | UXP-407 "Barang keluar", UXP-408 "Catat barang keluar", UXP-409 "Detail barang keluar" |
| Controls | 16: UXI-015, UXI-252, UXI-253, UXI-254, UXI-255, UXI-256, UXI-257, UXI-258, UXI-259, UXI-260, UXI-261, UXI-262, UXI-263, UXI-264, UXI-265, UXI-267 |
| Controls extended with a destination | UXI-056, UXI-224 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The Sale side of RACE-03 (TASK-028).
- Reversal entries on RP-409 (TASK-026).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-02, REQ-INV-06, REQ-COST-01 | REQ-COST-03, REQ-COST-10, REQ-INV-03 |
| Workflows | WF-20 | — |
| User tasks | UXT-15 | — |
| Acceptance suites | INV02 (final: TASK-044), FIN01 (final: TASK-044) | INV01 |
| Races | — | RACE-03 |
| Guards | G-02, G-13, G-14 | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-023-01** A reason from the five of BR-INV-19 is required; insufficient stock is refused; lines of one product are added together before the check.
- **AC-023-02** The issue's valuation follows ADR-008: the average before the issue, the remaining value when stock reaches zero, UNKNOWN while the valuation is unknown; no cost of goods sold and no Sale is created.
- **AC-023-03** Two manual issues for the last unit give one success and one `INSUFFICIENT_STOCK` (RACE-03, issue part).
- **AC-023-04** An issue movement without its allocation cannot commit; the projection equals the fact when inserted.
- **AC-023-05** The cases of the ADR-008 test list that concern issues pass.
- **AC-023-06** The pages follow the approved artifacts, with the Owner's explicit entry.

## 7. Tests

- L2 families for the four tables.
- L1 and L2 ADR-008 issue cases.
- L3 RACE-03 issue part.
- L2 failure injection.
- L5 pages.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-023.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The ADR-008 cases covered.

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

- TASK-024 and TASK-028 reuse the allocation service; TASK-025 replays these allocations.
- Tasks that depend on this one directly: [TASK-024](TASK-024-stock-adjustment.md), [TASK-028](TASK-028-checkout.md).

## 13. Expected files

- `app/Domain/Costing/Allocations/`, `app/Actions/Inventory/ConfirmManualIssue`
- `resources/js/pages/inventory/`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
