# TASK-015 — Categories, products, prices and Product Search (server)

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 4 Catalogue · lane C, catalogue |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-011](TASK-011-user-management.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-015-catalog-core` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for PostgreSQL text search with `pg_trgm` on the pinned version (EXC-03). |
| Modules | Catalog |
| Baseline | RF-7, D-090 |

## 1. Goal

Categories and products with their selling price revisions and lifecycle, the shared Product Search and the search-first check, on the server, with every rule, race and refusal of their commands.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- CATALOG_BARCODE sections 1, 2, 4 and 9 (BR-CAT-01 to BR-CAT-15, BR-CAT-23 to BR-CAT-27)
- SCHEMA section 5.6 (`categories`, `products`, `selling_price_revisions`), section 11, SCH-14 and SCH-32
- TECHNICAL_RULES section 7.2 (product creation; price change and product edit; categories), RACE-04, RACE-05, RACE-23, RACE-29, sections 11.3, 12 and 13.1
- INTERACTION_CONTRACTS RC-17 to RC-24, sections 3.1 and 3.5
- ROUTE_CONTRACTS RC-17 to RC-24, RD-01 and RD-06
- SECURITY_MODEL CAP-02, CAP-04 and CAP-05
- DOMAIN_MODEL section 11; D-037, D-042

## 4. Scope

### 4.1 In scope

1. Create `categories`, `products` and `selling_price_revisions` with their keys, checks, grants and the indexes of SCHEMA section 11 for products (case-insensitive unique SKU, SKU prefix, trigram on the name, the name-and-identifier order, the filters).
2. Implement RC-17 without barcodes (TASK-016 adds them) and RC-18 to RC-24 with the family locks of TECHNICAL_RULES section 7.2, expected versions and audit: SKUs trimmed, kept as typed and unique without regard to letter case, with the database as the only authority (SCH-14); rows inserted in the canonical order of SCH-32; a price change appends a revision; deactivation and reactivation of products and categories; a category deactivation changes no product; nothing is ever deleted.
3. Implement RD-01 Product Search: the order exact barcode (TASK-016), SKU exact and prefix, then name without regard to letter case; within each, name then identifier; 25 per page by default and at most 100; the client sequence header; never a cost; the `catalog` context (CAP-04) with `status`; every other context answers `FORBIDDEN` until its Task adds it. Read INTERACTION_CONTRACTS section 3.1 (which lists `labels` and `stock` contexts) against ROUTE_CONTRACTS RD-01 (which does not) and section 9 (where no control uses them), and bring the two documents in line, recording the choice.
4. Implement RD-06 for SKU and similar names (up to 10); the barcode part is TASK-016's.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `categories`, `products`, `selling_price_revisions` |
| Keys completed (EXC-44) | — |
| Routes | RC-17, RC-18, RC-19, RC-20, RC-21, RC-22, RC-23, RC-24, RD-01, RD-06 |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Barcodes (TASK-016).
- The catalogue pages (TASK-017).
- The unit and precision lock against the first movement, a database guarantee from TASK-021's key (RACE-05).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CAT-01, REQ-CAT-02, REQ-CAT-07, REQ-CAT-08, REQ-CAT-12 | REQ-CAT-03, REQ-SCOPE-01, REQ-ACC-09 |
| Workflows | — | WF-13, WF-14, WF-15 |
| User tasks | — | — |
| Acceptance suites | SEARCH02 (final: TASK-044) | SEARCH01, AUTH03 |
| Races | RACE-29 | RACE-04, RACE-05, RACE-23 |
| Guards | — | — |
| Threats | — | THR-04 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-015-01** Each command validates, locks, refuses (`DUPLICATE_SKU`, `CATEGORY_INACTIVE`, `STALE_VERSION`) and audits as INTERACTION_CONTRACTS section 5 says; two concurrent creations with one SKU give one success; RACE-29 gives its outcome in both orders.
- **AC-015-02** A price change creates a SellingPriceRevision and an audit event; no route changes a price outside RC-17 and RC-18.
- **AC-015-03** Product Search returns the order, paging, filters and role fields of section 3.1 for the `catalog` context, never a cost, and refuses every context not yet provided; injection payloads in `q` are treated as text; the trigram index serves name searches.
- **AC-015-04** A product is created with no movement; a deactivated product or category is refused where it would be posted, sold or assigned, and stays readable.
- **AC-015-05** INTERACTION_CONTRACTS section 3.1 and ROUTE_CONTRACTS RD-01 agree on the contexts after this Task.
- **AC-015-06** The constraint and privilege families pass for the three tables, with the SKU case vectors of SCH-14.

## 7. Tests

- L2 families for the three tables.
- L3 RACE-29 and the concurrent SKU creation.
- L4 commands, RD-01 and RD-06 with the capability matrix.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-015.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The reading of the RD-01 contexts and the documents changed.

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

- Search behaviour would have to change because `pg_trgm` cannot serve it.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- ROUTE_CONTRACTS RD-01 or INTERACTION_CONTRACTS section 3.1 (the contexts)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-016 adds barcodes to RC-17, RD-01 and RD-06; later Tasks add their RD-01 contexts and stock fields.
- Tasks that depend on this one directly: [TASK-016](TASK-016-barcodes.md).

## 13. Expected files

- `app/Domain/Catalog/`, `app/Actions/Catalog/`, `app/Queries/Catalog/`, `app/Http/Controllers/Catalog/`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
