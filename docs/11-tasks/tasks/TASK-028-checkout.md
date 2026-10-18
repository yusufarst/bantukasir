# TASK-028 — Sale checkout (server)

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | XL · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-027](TASK-027-registers-session-open.md), [TASK-023](TASK-023-manual-issue.md), [TASK-016](TASK-016-barcodes.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-028-checkout` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Sales, Inventory, Costing, Cash, Configuration |
| Baseline | RF-7, D-090 |

## 1. Goal

The atomic Sale on the server: one full CASH or BANK_TRANSFER payment, lines priced from the current revisions, stock issues with their cost allocations, the receipt snapshot with the frozen shop identity, the Sale number, the session's activity version and the guard rows of later corrections — in one transaction, with every refusal and race.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH sections 1 to 5 (BR-SALE-01 to BR-SALE-23, BR-PAY-01 to BR-PAY-07, BR-DOC-01 to BR-DOC-06) and BR-CASH-10
- INVENTORY BR-INV-07
- COSTING section 6
- ADR-008 section 2
- SCHEMA section 5.9 (`sales`, `sale_lines`, `payments`, `receipt_snapshots`, `sale_correction_totals`, `sale_line_correction_totals`), section 6, G-01, G-04, G-17 and G-18
- TECHNICAL_RULES section 7.2 (Sale checkout), RACE-01 to RACE-03, RACE-08, RACE-18, RACE-23, RACE-25 and sections 11.2 and 13.1
- INTERACTION_CONTRACTS RC-01 and section 3.1 (the `till` context)
- ROUTE_CONTRACTS RC-01
- ARCHITECTURE section 5 (ARC-30 to ARC-33) and ARC-25
- SECURITY_MODEL CAP-21
- DOCUMENT_CONTRACTS DC-01 (the snapshot's fields)
- D-036, D-040, D-067, D-077
- CRITICAL_FLOWS WF-05

## 4. Scope

### 4.1 In scope

1. Create the six tables and `sale_number_seq`; complete the keys `stock_movements.sale_line_id` → `sale_lines` and `issue_cost_allocations (sale_line_id, issued_quantity)` → `sale_lines (id, quantity)`, the deferred key from each Sale line to its movement, and the deferred keys from a Sale to its Payment, receipt snapshot and correction-totals row.
2. Implement RC-01 with the locks of the Sale checkout family: the identity; the actor (share); onboarding (share); the BusinessProfile pointer (share); the actor's own OPEN session (update; activity version + 1); the products in ascending order; balances and valuations; then the append. Checked under the locks: the session is OPEN and the actor's; onboarding off; each product active and not blocked, its price revision as reviewed (`PRICE_CHANGED` with the current prices); the stock of the combined lines (`INSUFFICIENT_STOCK` with the available stock); the method allowed (CASH or BANK_TRANSFER; any other, STATIC_QRIS included, `METHOD_NOT_ALLOWED`); for CASH, tendered at least the total (`TENDER_INSUFFICIENT`), change and the applied amount equal to the total; for BANK_TRANSFER the exact total, an optional reference and the funds-received confirmation, never a pending state.
3. Refuse before any lock a request naming a product twice or holding more than 200 lines (`VALIDATION_FAILED`, D-067).
4. Compute line totals half-up once and the Sale total as their sum; read the business date and allocate the number at step 8, immediately before the insert (SCH-21); write the lines, the Payment, the SALE_ISSUE movements and balances, the allocation of every line (UNKNOWN when the valuation is unknown), the receipt snapshot with the frozen identity, template version, display names, items, totals, method, tendered amount and change (DC-01), the correction-totals rows, the audit event and the durable result.
5. Keep the payment boundary of ARC-30 to ARC-33: one place defines the methods and their validation; no provider record, table, controller, callback, webhook, polling or asynchronous state.
6. Implement the `till` context of RD-01: it needs the actor's own OPEN session (`NO_OPEN_SESSION`) and returns the stock that checkout needs.
7. Extend the replay of TASK-025 to SALE_ISSUE.
8. Prove the Sale side of RACE-18 (a cart line taken before a barcode move keeps its product) and of RACE-25 (each receipt snapshot uses the revision current when its lock was taken).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `sales`, `sale_lines`, `payments`, `receipt_snapshots`, `sale_correction_totals`, `sale_line_correction_totals` |
| Keys completed (EXC-44) | `stock_movements.sale_line_id` → `sale_lines`, `issue_cost_allocations (sale_line_id, issued_quantity)` → `sale_lines (id, quantity)`, `sale_lines.id` → `stock_movements (sale_line_id)` (deferred), `sales.id` → `payments`, `receipt_snapshots` and `sale_correction_totals` (deferred) |
| Routes | RC-01 |
| Route elements added | RD-01: `till` context |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The till pages (TASK-029).
- Rendering and printing the receipt (TASK-031).
- Refunds and returns (TASK-033, TASK-034).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-SALE-01, REQ-SALE-02, REQ-SALE-03, REQ-SALE-05, REQ-SALE-09, REQ-SALE-10, REQ-PAY-01, REQ-PAY-02, REQ-PAY-03, REQ-PAY-05, REQ-COST-10, REQ-CASH-04, REQ-CASH-13, REQ-DOC-01 | REQ-SCOPE-01, REQ-SCOPE-02, REQ-INV-02, REQ-INV-03, REQ-NFR-08, REQ-CAT-08 |
| Workflows | — | WF-05 |
| User tasks | — | — |
| Acceptance suites | POS01 (final: TASK-044), POS02 (final: TASK-044), PAYEXT01 (final: TASK-044) | POS03, FIN01, SESSION03, INV02, BARCODE01, IDENTITY01 |
| Races | RACE-03, RACE-23, RACE-25 | RACE-01, RACE-02, RACE-08, RACE-18 |
| Guards | G-01, G-04, G-17, G-18 | G-02, G-10, G-11, G-13 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-028-01** Exactly one Sale with its lines, Payment, issues, balances, allocations, receipt snapshot, audit event and result per intent; RACE-01 and RACE-02 give their outcomes; a failure injected after every write step leaves nothing.
- **AC-028-02** Checkout needs the actor's own eligible OPEN session for every role and method; an account with OPERATIONS_ADMIN only is refused; a request naming a product twice or with more than 200 lines is refused before any lock; the activity version increases by one.
- **AC-028-03** Total Rp75.000 with Rp100.000 tendered gives Rp25.000 change and Rp75.000 applied; Rp70.000 is refused `TENDER_INSUFFICIENT`; BANK_TRANSFER takes the exact total with no pending state; mixed, partial and unknown methods, STATIC_QRIS included, are refused; a tampered price is refused.
- **AC-028-04** Each definite rejection — stale price, insufficient stock, inactive product, a closed or foreign session, a method not allowed — writes nothing.
- **AC-028-05** The FIN01 example gives sales Rp60.000, cost of goods sold Rp36.000 and gross profit Rp24.000; each line's allocation, or UNKNOWN, is recorded in the Sale's transaction.
- **AC-028-06** Concurrent checkouts in different sessions do not wait on each other for a number; numbers are unique, follow allocation order, leave a gap after a rollback and carry the right date part across midnight.
- **AC-028-07** RACE-03 (a last-unit Sale against a Sale or a manual issue), RACE-23 and RACE-25 give their outcomes; a cart line taken before a barcode move keeps its product (RACE-18).
- **AC-028-08** No provider record, controller, callback, webhook or asynchronous payment state exists in code or schema (PAYEXT01, by review).
- **AC-028-09** The constraint, deferred and privilege families pass for the six tables and the sequence.

## 7. Tests

- L2 families for the six tables.
- L3 RACE-01 to RACE-03, RACE-23, RACE-25, RACE-18 Sale side, numbering.
- L2 failure injection after every write step.
- L4 RC-01 with every refusal and the capability matrix.
- Code and schema review for PAYEXT01.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-028.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The adversarial review of the checkout transaction.

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

- A rule of SALES_CASH sections 1 to 5 cannot be met within the lock order of TECHNICAL_RULES section 7.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-029 builds the till on RC-01; TASK-030 closes against its activity version; TASK-031 renders its snapshot; TASK-033 corrects its Sales.
- Tasks that depend on this one directly: [TASK-029](TASK-029-till-pages.md), [TASK-033](TASK-033-refunds-core.md).

## 13. Expected files

- `app/Domain/Sales/`, `app/Actions/Sales/CheckoutSale`
- `database/migrations/`
- `tests/Concurrency/` checkout races

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
