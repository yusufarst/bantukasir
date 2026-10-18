# TASK-019 — CSV product import

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 4 Catalogue · lane C, catalogue |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-017](TASK-017-catalog-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-019-csv-import` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Catalog, Media (temporary area) |
| Baseline | RF-7, D-090 |

## 1. Goal

Creation of new products from a CSV file, with a preview, the errors of every row and an all-or-nothing confirmation tied to the previewed file, never creating stock, cost or a category.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- CATALOG_BARCODE section 7 (BR-CAT-38 to BR-CAT-42)
- DOCUMENT_CONTRACTS DC-04
- SECURITY_MODEL section 13 (the CSV column), SEC-UP-04, SEC-UP-05 and CAP-10
- SCHEMA section 5.6 (`csv_product_imports`)
- TECHNICAL_RULES section 7.2 (CSV import confirmation), RACE-22, TR-TIME-02 and section 11.2
- INTERACTION_CONTRACTS RC-31
- ROUTE_CONTRACTS RR-01, RR-02, RC-31 and RP-308
- ARCHITECTURE ARC-43; D-037, D-054, D-069 (h)
- COPY_DECK sections 8.9 and 16.3 and the DC-04 headers added by TASK-002
- CRITICAL_FLOWS WF-18

## 4. Scope

### 4.1 In scope

1. Create `csv_product_imports`.
2. Implement RR-01: the file judged by its content; stored in the private temporary area under a random key; parsed in the dialect of DC-04 (header; semicolon or comma separator read from the header; optional byte-order mark; decimal commas and thousands full stops; barcodes and SKUs as text; UTF-8 only); refused before any commit for a formula cell, a control character, an unknown or missing column, more than 5,000 rows or 2 MiB, a category name that is missing, ambiguous or inactive (never creating a category), and duplicates within the file or against the database; the answer holds the import token, the file's SHA-256, the row count and every row's errors.
3. Implement RR-02 (the temporary file removed; `204` also for a missing or expired import).
4. Implement RC-31: the whole file validated again against the previewed checksum; the categories it names under share locks; all rows or none through the catalogue services, with prices and barcodes; the audit holding the result and the checksum; the temporary file removed at the final outcome; a repeated confirmation returning the original result; bounded by the 30-second budget.
5. Build RP-308 with the column list of DC-04 and the preview.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `csv_product_imports` |
| Keys completed (EXC-44) | — |
| Routes | RR-01, RR-02, RC-31, RP-308 |
| Route elements added | — |
| Pages | UXP-308 "Impor produk (CSV)" |
| Controls | 8: UXI-011, UXI-206, UXI-207, UXI-208, UXI-209, UXI-210, UXI-211, UXI-212 |
| Controls extended with a destination | — |
| Document contracts | DC-04 |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Removal of temporary files older than 24 hours (TASK-038).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CAT-09 | — |
| Workflows | WF-18 | — |
| User tasks | UXT-21 | — |
| Acceptance suites | IMPORT01 (final: TASK-044) | SEC01 |
| Races | RACE-22 | — |
| Guards | — | — |
| Threats | — | THR-09, THR-10 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-019-01** The dialect of DC-04 is parsed exactly, including both separators, the byte-order mark and Indonesian number formats.
- **AC-019-02** Each refusal of IMPORT01 happens before any commit, at the row concerned, and never creates a category.
- **AC-019-03** All rows or none; the checksum ties the confirmation to the previewed file; a repeated confirmation returns the original result; RACE-22 gives its outcome; no stock or cost is created; a cashier is refused.
- **AC-019-04** The temporary file is removed at commit, refusal and cancellation, is never served and is never backed up; the audit keeps the result with the checksum.
- **AC-019-05** A file name or cell holding shell metacharacters never reaches a process.

## 7. Tests

- L1 parser cases.
- L2 all-or-nothing and failure injection.
- L3 RACE-22.
- L4 RR-01, RR-02, RC-31.
- L5 RP-308 preview.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-019.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- TASK-038 removes temporary files older than 24 hours.
- Tasks that depend on this one directly: [TASK-038](TASK-038-housekeeping.md).

## 13. Expected files

- `app/Domain/Catalog/Import/`, `app/Actions/Catalog/`
- `resources/js/pages/catalog/Import`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
