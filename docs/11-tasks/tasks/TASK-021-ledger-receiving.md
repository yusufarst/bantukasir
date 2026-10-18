# TASK-021 — Stock ledger, valuation and receiving (server)

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 5 Inventory and costing · lane I, inventory and costing |
| Size and review | XL · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-016](TASK-016-barcodes.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-021-ledger-receiving` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Inventory, Costing |
| Baseline | RF-7, D-090 |

## 1. Goal

The immutable stock ledger with its synchronous balance, the moving-weighted-average valuation with acquisition cost evidence and UNKNOWN cost, the posting-block guard, and the first posting command — receiving — on the server.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY sections 1 to 4 and 11 (BR-INV-01 to BR-INV-17, BR-INV-45)
- COSTING sections 1 to 5
- ADR-008 sections 1, 4.1, 4.2, 5, 6, 7, the invariants and the test list
- SCHEMA section 5.7 (`stock_movements`, `stock_balances`, `receiving_documents`, `receiving_lines`, `inventory_posting_blocks`, `inventory_posting_block_reopenings`), section 5.8 (`acquisition_cost_evidence`, `inventory_valuations`), sections 7 and 11, G-02, G-03, G-11 and G-13
- TECHNICAL_RULES sections 7.1 and 7.2 (receiving, manual issue, adjustment family), RACE-01, RACE-05, RACE-24, sections 9 and 11.2
- INTERACTION_CONTRACTS RC-07
- ROUTE_CONTRACTS RC-07
- SECURITY_MODEL CAP-11 and CAP-12
- ARCHITECTURE ARC-20, ARC-21 and ARC-26; D-033, D-047, D-069 (a)

## 4. Scope

### 4.1 In scope

1. Create `stock_movements` with every column of SCHEMA (all source columns and the three presence keys), its keys to `products (id, base_unit, quantity_precision)` with `ON UPDATE RESTRICT`, to `receiving_lines` and to `acquisition_cost_evidence`, and the checks that pair each movement type with its source; and `stock_balances`, `receiving_documents`, `receiving_lines`, `acquisition_cost_evidence`, `inventory_valuations`, `inventory_posting_blocks` and `inventory_posting_block_reopenings`, with their grants. The keys of other source columns are completed by the Tasks that create their tables (EXC-44).
2. Build the Inventory posting service: each movement and the balance in the same transaction; the per-product sequence; stock never negative, with lines of one product added together before the check (G-02); the onboarding share lock with the freeze check (G-10; the state can become ACTIVE only through TASK-035, which proves it); the posting-block guard (G-11: a block without a reopening refuses `POSTING_BLOCKED`); product active; the expected product versions.
3. Build the Costing services that the posting Action calls beside Inventory (STK-11): acquisition evidence KNOWN or UNKNOWN, never zero for UNKNOWN, an explicit zero only with evidence or a reason confirmed by the Owner (BR-COST-04, BR-INV-13); the moving-weighted-average increase with the arithmetic of ADR-008 (aggregate before rounding); UNKNOWN pools; the valuation's publication number.
4. Implement RC-07 `receiving.confirm`: up to 500 lines; a document date stored apart that never backdates a fact (REQ-INV-13); audit; result for RP-406 (TASK-022).
5. Add to RD-01 the stock fields per role (on-hand, minimum and attention state for the Owner and OPERATIONS_ADMIN).
6. Prove RACE-05: with the composite key in place, an edit of unit or precision either commits before the first movement or is refused.
7. Until the reconciliation exists (TASK-036), test fixtures create posting blocks through the Inventory module's own block service (EXC-45).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `stock_movements`, `stock_balances`, `receiving_documents`, `receiving_lines`, `acquisition_cost_evidence`, `inventory_valuations`, `inventory_posting_blocks`, `inventory_posting_block_reopenings` |
| Keys completed (EXC-44) | `stock_movements (product_id, base_unit, quantity_precision)` → `products` with `ON UPDATE RESTRICT` |
| Routes | RC-07 |
| Route elements added | RD-01: stock fields per role |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The receiving and stock pages (TASK-022).
- Issue allocations (TASK-023).
- The reconciliation and reopening (TASK-036).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-01, REQ-INV-03, REQ-INV-04, REQ-INV-13, REQ-CAT-06, REQ-COST-03 | REQ-COST-01, REQ-NFR-08, REQ-INV-05 |
| Workflows | — | WF-19 |
| User tasks | — | — |
| Acceptance suites | INV01 (final: TASK-044) | FIN01, FIN02, AUTH02, SEARCH02 |
| Races | RACE-05 | RACE-01, RACE-24 |
| Guards | G-11 | G-02, G-03, G-10, G-13 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-021-01** Every physical change is one movement; after every command the balance equals the sum of the product's movements; no function edits a balance.
- **AC-021-02** Receiving with KNOWN and with UNKNOWN cost; UNKNOWN is never stored as zero; an explicit zero needs evidence or a reason confirmed by the Owner; nothing posts before confirmation; no draft table or draft endpoint exists (D-069 (a)).
- **AC-021-03** RC-07 meets RACE-01 with the same and with a different key; a failure injected after every write step leaves nothing.
- **AC-021-04** The valuation follows ADR-008 for receipts, including UNKNOWN pools, with the cases of its test list that concern receipts.
- **AC-021-05** RACE-05 gives its outcome in both orders.
- **AC-021-06** A blocked product refuses RC-07 with `POSTING_BLOCKED`; another product posts.
- **AC-021-07** A document date never moves a fact; server time and posting order are facts.
- **AC-021-08** The constraint, deferred and privilege families pass for the eight tables, including a RECEIPT movement without its evidence failing at commit.

## 7. Tests

- L2 families for the eight tables.
- L1 and L2 ADR-008 receipt cases.
- L3 RACE-01 for RC-07, RACE-05.
- L2 failure injection for RC-07.
- L4 RC-07 with the capability matrix and the role DTOs.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-021.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The ADR-008 cases covered.

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

- An ADR-008 case cannot be met without changing a cost rule.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-022 builds the pages; TASK-023 adds issues and allocations on these services; TASK-036 writes blocks and reopenings.
- Tasks that depend on this one directly: [TASK-022](TASK-022-receiving-stock-pages.md).

## 13. Expected files

- `app/Domain/Inventory/`, `app/Domain/Costing/`, `app/Actions/Inventory/ConfirmReceiving`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
