# TASK-018 — Product photos and barcode labels

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 4 Catalogue · lane C, catalogue |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-017](TASK-017-catalog-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-018-photos-labels` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for the Code 128 library chosen (EXC-03). |
| Modules | Catalog, Media, Documents |
| Baseline | RF-7, D-090 |

## 1. Goal

One optional primary photo per product with thumbnails for the till, and Code 128 barcode labels printed through the browser from a recorded print request.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- CATALOG_BARCODE sections 6 and 8
- SECURITY_MODEL section 13 (the product photo column), CAP-03, CAP-08 and CAP-09
- SCHEMA section 5.5 and G-20
- TECHNICAL_RULES section 7.2 (photo; print request) and TR-TX-05
- INTERACTION_CONTRACTS RC-25, RC-26 and RC-47
- ROUTE_CONTRACTS RC-25, RC-26, RC-47, RP-306, RP-306P and RD-07
- DOCUMENT_CONTRACTS DC-05
- ARCHITECTURE ARC-47
- COPY_DECK sections 8.3, 8.7 and 17.3
- CRITICAL_FLOWS WF-16

## 4. Scope

### 4.1 In scope

1. Implement RC-25 and RC-26: the file validated and stored before the transaction (TR-TX-05) through the TASK-010 pipeline with the photo limits (8 MiB, 24 megapixels), normalized to at most 2,048 pixels on the longest side with thumbnails for the till; the product's update lock; a replaced or removed file kept and no longer shown (D-042); the `photo` bucket.
2. Extend RD-07 to photos and thumbnails for every role with CAP-03.
3. Choose an open-source Code 128 library with a verified licence (ARC-47) that renders SVG from the token; build the label of DC-05 with code, name and SKU and never a cost; implement RC-47 (1 to 100 labels per request; audited; `STALE_VERSION` when the token is no longer active), RP-306 and the print view RP-306P (a read).
4. Add the photo and labels sections to RP-302.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RC-25, RC-26, RC-47, RP-306, RP-306P |
| Route elements added | RD-07: product photos and thumbnails |
| Pages | UXP-306 "Cetak label barcode" |
| Controls | 7: UXI-163, UXI-169, UXI-170, UXI-175, UXI-199, UXI-200, UXI-201 |
| Controls extended with a destination | — |
| Document contracts | DC-05 |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The cleanup of unreferenced files after the grace period (TASK-038).
- Scanning the printed labels on real scanners (TASK-046).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CAT-10, REQ-CAT-11 | REQ-CFG-03 |
| Workflows | WF-16 | — |
| User tasks | UXT-19, UXT-20 | — |
| Acceptance suites | MEDIA01 (final: TASK-044) | SESSION08, SEC01 |
| Races | — | — |
| Guards | — | G-20 |
| Threats | — | THR-09, THR-12 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-018-01** JPEG, PNG and WebP are accepted by decoding; every refusal case of SECURITY_MODEL section 13 for photos is refused; animated images are refused; the result is at most 2,048 pixels with thumbnails and without metadata.
- **AC-018-02** A replaced or removed photo's file is kept and no longer shown; a cashier cannot upload; the till placeholder is shown without a photo (G-20 holds for the purpose of every reference).
- **AC-018-03** A file left by a rolled-back command stays unreferenced and is never served.
- **AC-018-04** Media are served only through RD-07 with `Cache-Control: private`; a guessed identifier answers `NOT_FOUND`.
- **AC-018-05** Labels carry code, name and SKU and never a cost; 1 to 100 per request; each print request is audited and creates no fact.

## 7. Tests

- L4 uploads and refusals.
- L2 the rollback leaving an unreferenced file.
- L4 RC-47 limits and audit.
- L5 RP-306 and the print view.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-018.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The Code 128 library, version and licence.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- No Code 128 library has a licence compatible with COST_POLICY.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- TOOLCHAIN (the Code 128 library)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-029 shows thumbnails at the till; TASK-038 removes unreferenced files; TASK-046 scans labels.
- Tasks that depend on this one directly: [TASK-038](TASK-038-housekeeping.md).

## 13. Expected files

- `app/Actions/Catalog/`, `app/Domain/Media/`, `app/Domain/Documents/Labels/`
- `resources/js/pages/catalog/`, `resources/js/pages/documents/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
