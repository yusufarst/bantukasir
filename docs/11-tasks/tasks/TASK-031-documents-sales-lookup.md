# TASK-031 — Receipt and session documents, sales lookup, print and export

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-030](TASK-030-session-close.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-031-documents-sales-lookup` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Documents, Sales (pages) |
| Baseline | RF-7, D-090 |

## 1. Goal

The documents of a Sale and of a closed session — the receipt in 58 mm, 80 mm and A4 "Nota Penjualan", the session report and its CSV export — rendered from their snapshots through recorded print and export requests, and the pages to find Sales and reprint them.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- DOCUMENT_CONTRACTS DC-01, DC-02 and DC-03
- SALES_CASH section 5 (BR-DOC-01 to BR-DOC-06) and BR-CASH-20 to BR-CASH-22
- ARCHITECTURE ARC-44 to ARC-46
- DESIGN_SYSTEM sections 8.16 and 12; UXA-23
- INTERACTION_CONTRACTS RC-44 to RC-46
- ROUTE_CONTRACTS RC-44 to RC-46, RP-103P, RP-206P, RP-201 and RP-202
- SECURITY_MODEL SEC-CH-07, CAP-21 and CAP-22
- COPY_DECK sections 7.3, 7.6, 7.7, 17.1 and 17.2
- KEY_SCREENS KS-05
- CRITICAL_FLOWS WF-06 and WF-12

## 4. Scope

### 4.1 In scope

1. Keep the receipt templates (58 mm, 80 mm, A4) and the session report template as versions in code; a template referenced by a snapshot is never changed in place (ARC-45).
2. Implement RC-44 (a print request of a receipt; every print after the first carries "Salinan" first at the top; audited; creates no fact) with the print view RP-103P (a read), and RC-45 with RP-206P for the session report.
3. Implement RC-46: the CSV of DC-03 (semicolons, byte-order mark, plain integers, formula and control prefixes neutralized, a negative variance kept numeric, time zone and session context); a repeated identity returns the same file; audited.
4. Build RP-201 (keyset list with its filters; own scope for a cashier) and RP-202 (lines one per product, the payment, reprint); add the print controls of RP-103 and RP-206. The Owner's linked corrections on RP-202 are added by TASK-034.
5. Printing follows the commit; a printer failure leaves the Sale intact and reprintable (REQ-SALE-11).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RC-44, RC-45, RC-46, RP-103P, RP-206P, RP-201, RP-202 |
| Route elements added | — |
| Pages | UXP-201 "Penjualan", UXP-202 "Detail penjualan" |
| Controls | 14: UXI-003, UXI-090, UXI-092, UXI-112, UXI-113, UXI-114, UXI-115, UXI-116, UXI-117, UXI-119, UXI-120, UXI-121, UXI-141, UXI-142 |
| Controls extended with a destination | UXI-100, UXI-224 |
| Document contracts | DC-01, DC-02, DC-03 |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Labels (TASK-018).
- Real printers and paper (TASK-046).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-DOC-02, REQ-DOC-03, REQ-DOC-04, REQ-SALE-11, REQ-SALE-12, REQ-CASH-12, REQ-NFR-10, REQ-CFG-02 | REQ-DOC-01, REQ-RPT-05 |
| Workflows | WF-06, WF-12 | — |
| User tasks | UXT-06, UXT-10 | — |
| Acceptance suites | SESSION08 (final: TASK-044) | POS03, IDENTITY01, ACC01, SESSION10, AUTH02 |
| Races | — | RACE-16, RACE-25 |
| Guards | — | G-17 |
| Threats | — | THR-02, THR-08 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-031-01** The receipt snapshot holds the fields of BR-DOC-01 and DC-01; the 58 mm, 80 mm and A4 documents render from one snapshot; no tax line and no tax-invoice wording appear.
- **AC-031-02** Every print after the first carries "Salinan" first at the top; print, reprint and export create no Sale, Payment, movement or session change, and are audited.
- **AC-031-03** The session report CSV follows DC-03 exactly; a failed export retries the same snapshot.
- **AC-031-04** After a BusinessProfile change, reprints of earlier Sales and reports show the identity they froze; after an anonymization a reprint is identical (RACE-16).
- **AC-031-05** A cashier cannot read, print or export another cashier's Sale, receipt or session, also with guessed identifiers; OPERATIONS_ADMIN has no access to these documents.
- **AC-031-06** A printer failure after commit leaves the Sale intact and reprintable.

## 7. Tests

- L2 snapshot rendering and copy marker.
- L1 CSV dialect.
- L4 print and export requests with scopes.
- L5 print views at each size.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-031.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

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

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-034 adds linked corrections to RP-202; TASK-046 prints on real devices.
- Tasks that depend on this one directly: [TASK-033](TASK-033-refunds-core.md).

## 13. Expected files

- `app/Domain/Documents/`, `app/Actions/Documents/`
- `resources/js/pages/documents/`, `resources/js/pages/sales/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
