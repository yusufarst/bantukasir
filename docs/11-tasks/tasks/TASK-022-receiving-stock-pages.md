# TASK-022 — Receiving, stock and history pages

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 5 Inventory and costing · lane I, inventory and costing |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-021](TASK-021-ledger-receiving.md), [TASK-017](TASK-017-catalog-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-022-receiving-stock-pages` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Inventory (pages and reads) |
| Baseline | RF-7, D-090 |

## 1. Goal

The receiving pages — the list, a new receiving with its review, the detail with cost by role — and the stock pages — the stock list with attention, the stock history and the movement detail — with the stock-attention badge and the live reads.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY sections 4, 10 (BR-INV-40 to BR-INV-44) and 11
- SECURITY_MODEL CAP-11, CAP-12 and CAP-14; D-033, D-047, D-069 (a)
- INTERACTION_CONTRACTS sections 3.1 to 3.3 and 9 and RC-07
- ROUTE_CONTRACTS RP-401 to RP-406, RD-02 and RD-03
- NAVIGATION_CONTRACTS, INTERACTION_INVENTORY and UI_STATES for UXP-401 to UXP-406
- COPY_DECK sections 9.1 to 9.7
- KEY_SCREENS KS-08 and KS-09
- CRITICAL_FLOWS WF-13, WF-19 and WF-21
- ARCHITECTURE ARC-22

## 4. Scope

### 4.1 In scope

1. Build RP-404, RP-405 (KS-09: a draft in page memory only; scan capture; RD-01 `receiving`; "Buat produk baru" over the page through RP-303, NC-11; review and confirmation) and RP-406 (cost by role: the Owner sees all; OPERATIONS_ADMIN and the combined account see the cost, and after an Owner correction the current cost, "Dikoreksi Pemilik" and the cost at confirmation, never the reason, D-047 — the correction data come from TASK-025, which completes the proof).
2. Build RP-401 (KS-08: the attention states; refreshed at least every 30 seconds while visible, on focus and after a command, as background reads; a failed refresh shows the last data with its time, never empty, zero or "Normal"), RP-402 (keyset paging; posting order within a product) and RP-403 (the movement, its source link by scope, the cost evidence for the Owner).
3. Implement RD-02, the `receiving` and `stock` contexts of RD-01 as TASK-015 aligned them, and the stock-attention count of RD-03 with the badge (UXI-006).
4. Add the stock section of RP-302 and the navigation items of these destinations; switch RN-01 to RP-401 for OPERATIONS_ADMIN and for the combined account without an OPEN session.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-401, RP-402, RP-403, RP-404, RP-405, RP-406, RD-02 |
| Route elements added | RD-01: `receiving` and `stock` contexts, RD-03: stock-attention count, RN-01: home of OPERATIONS_ADMIN and of the combined account without an OPEN session |
| Pages | UXP-401 "Stok", UXP-402 "Riwayat stok", UXP-403 "Detail mutasi stok", UXP-404 "Barang masuk", UXP-405 "Catat barang masuk", UXP-406 "Detail barang masuk" |
| Controls | 37: UXI-006, UXI-012, UXI-013, UXI-014, UXI-056, UXI-167, UXI-213, UXI-214, UXI-215, UXI-216, UXI-218, UXI-219, UXI-220, UXI-222, UXI-223, UXI-224, UXI-225, UXI-229, UXI-230, UXI-231, UXI-232, UXI-233, UXI-234, UXI-235, UXI-236, UXI-237, UXI-238, UXI-239, UXI-240, UXI-241, UXI-242, UXI-243, UXI-245, UXI-246, UXI-247, UXI-248, UXI-251 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Cost correction and its pages (TASK-025).
- Reversal entries (TASK-026).
- Incidents (TASK-036).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-05, REQ-INV-10, REQ-INV-11, REQ-COST-04 | REQ-INV-04, REQ-RPT-05, REQ-UX-01 |
| Workflows | WF-13, WF-19, WF-21 | — |
| User tasks | UXT-13, UXT-14, UXT-16 | — |
| Acceptance suites | STOCK01 (final: TASK-044) | INV01, AUTH02, UX01, UX02 |
| Races | — | — |
| Guards | — | G-02 |
| Threats | — | THR-08 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-022-01** With minimum 5: stock 6 is Normal, 5 and 1 Menipis, 0 Habis; minimum 0 has no Menipis; a new product with stock 0 is Habis; the badge and the list come from one query and agree.
- **AC-022-02** The refresh rules hold, with the background marker; a failed refresh shows stale data with its time.
- **AC-022-03** Receiving has a review and an explicit confirmation; its draft never reaches browser storage.
- **AC-022-04** Payload inspection per role: OPERATIONS_ADMIN gets the purchase cost on receiving documents and never average cost, cost of goods sold, margin, profit or inventory value; CASHIER gets no cost on any of these routes.
- **AC-022-05** Stock and history are paged on the server with keyset order; role payloads are as CAP-14 allows.
- **AC-022-06** KS-08 and KS-09 are reproduced with their states at every width; the accessibility checks pass.

## 7. Tests

- L4 RD-02, RD-03 and the role payloads.
- L5 KS-08 and KS-09 states.
- L5 receiving with review and scanning.
- L2 attention thresholds.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-022.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A page cannot follow the approved artifacts (EXC-50).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-025 completes the corrected-cost display; TASK-026 adds reversal entries; TASK-036 adds incidents.
- Tasks that depend on this one directly: [TASK-023](TASK-023-manual-issue.md), [TASK-027](TASK-027-registers-session-open.md).

## 13. Expected files

- `resources/js/pages/inventory/`
- `app/Http/Controllers/Inventory/`, `app/Queries/Inventory/`
- `lang/id/inventory.php`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
