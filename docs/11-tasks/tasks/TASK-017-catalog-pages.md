# TASK-017 — Catalogue pages

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 4 Catalogue · lane C, catalogue |
| Size and review | L · review class B — self-review and the reviewer's check ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-016](TASK-016-barcodes.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-017-catalog-pages` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Catalog (pages) |
| Baseline | RF-7, D-090 |

## 1. Goal

The product and category pages — Produk, Detail produk, Tambah produk with its search-first step, Ubah produk and Kategori — on the approved artifacts, using Product Search and the catalogue commands, with scanning in the catalogue contexts.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INFORMATION_ARCHITECTURE sections 5 (UXP-301 to UXP-304, UXP-307) and 7
- NAVIGATION_CONTRACTS section 2, A3
- INTERACTION_INVENTORY section 2, A3 (UXP-301 to UXP-304, UXP-307)
- UI_STATES section 6 for these pages
- COPY_DECK sections 8.1 to 8.5 and 8.8
- INTERACTION_CONTRACTS sections 3.1, 3.5 and 9
- ROUTE_CONTRACTS RP-301 to RP-304 and RP-307
- CRITICAL_FLOWS WF-13, WF-14 and WF-15
- DESIGN_SYSTEM sections 7 to 11
- ADAPTIVE_AND_ACCESSIBILITY UXA-10, UXA-11 and section 4

## 4. Scope

### 4.1 In scope

1. Build RP-301 (the list through RD-01 `catalog`, its filters and paging, kept in the history state when the user returns, NC-10), RP-302 (the product's details, price history, barcodes with add, generate and retire, and status; the stock, photo, labels, correction and cost-history sections are added by TASK-022, TASK-018, TASK-020 and TASK-025), RP-303 (the search-first step through RD-06 with "Tetap buat produk baru" for a similar name and scanning into the barcode field, then the form; opened over RP-405 or RP-413 by TASK-022 and TASK-035, NC-11), RP-304 (the form with the product version; unit and precision shown locked after the first movement) and RP-307 (categories with their inline forms).
2. Add the navigation items of these destinations to the shell and scan capture in the catalogue contexts, paused while a dialog is open (UXA-11).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-301, RP-302, RP-303, RP-304, RP-307 |
| Route elements added | — |
| Pages | UXP-301 "Produk", UXP-302 "Detail produk", UXP-303 "Tambah produk", UXP-304 "Ubah produk", UXP-307 "Kategori" |
| Controls | 31: UXI-005, UXI-009, UXI-010, UXI-159, UXI-160, UXI-161, UXI-162, UXI-164, UXI-165, UXI-166, UXI-168, UXI-171, UXI-172, UXI-173, UXI-177, UXI-178, UXI-179, UXI-180, UXI-181, UXI-182, UXI-183, UXI-184, UXI-185, UXI-186, UXI-187, UXI-188, UXI-189, UXI-202, UXI-203, UXI-204, UXI-205 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The sections of RP-302 that other Tasks add.
- The entries of RP-303 from working pages (TASK-022, TASK-035).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CAT-03 | REQ-CAT-01, REQ-CAT-07, REQ-CAT-12, REQ-UX-01 |
| Workflows | WF-14, WF-15 | WF-13 |
| User tasks | UXT-17, UXT-18, UXT-22 | — |
| Acceptance suites | — | SEARCH02, UX01, UX02, SEARCH03 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-017-01** Each page has exactly the controls that TRACEABILITY section 9 assigns to this Task, each routed as INTERACTION_CONTRACTS section 9 says, with the states of UI_STATES section 6 and the words of COPY_DECK.
- **AC-017-02** Creation passes the search-first step: an exact match shows its product, a similar name needs "Tetap buat produk baru", a duplicate SKU or barcode is refused at the field.
- **AC-017-03** No page offers a delete function; inactive records are shown read-only and marked.
- **AC-017-04** The pages pass the accessibility checks at every width and the keyboard paths of their page types.

## 7. Tests

- L5 pages per role, states and widths.
- L5 search-first flow including a scanned code.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-017.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class B (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A page cannot follow the approved artifacts (EXC-50).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-018, TASK-020, TASK-022 and TASK-025 add their sections and controls to RP-302.
- Tasks that depend on this one directly: [TASK-018](TASK-018-photos-labels.md), [TASK-019](TASK-019-csv-import.md), [TASK-020](TASK-020-barcode-correction.md), [TASK-022](TASK-022-receiving-stock-pages.md), [TASK-029](TASK-029-till-pages.md).

## 13. Expected files

- `resources/js/pages/catalog/`
- `app/Http/Controllers/Catalog/`, `app/Queries/Catalog/`
- `lang/id/catalog.php`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
