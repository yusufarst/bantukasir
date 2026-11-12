# TASK-031 evidence — Receipt and session documents, sales lookup, print and export

| | |
| --- | --- |
| Task | [TASK-031](../tasks/TASK-031-documents-sales-lookup.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-031-documents-sales-lookup`, from `973f190484488f21bac076080d52f547ae92dc02` (origin/develop, the squash merge of TASK-030) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; no branch or pull request of TASK-031 and no open pull request; the executor state at `BUILD` for TASK-031 with this session's lock; TASK-030 `DONE`, no gate. PHP, `tsc` and ESLint do not run on this machine: every check but the guard runs in CI (D-108, D-117) |
| Owner's directives in force | The host's standing directives and the throughput directive of 12 November 2026: the contract's scope only, one review, fixes of proven findings only, no repeat of valid work, every gate kept; a new CVE is a stop; no change of executor scripts, baselines, assertions or timeouts; the next free decision D-129 (none taken). The run paused once at the service's usage limit and resumed where it stood, with nothing redone |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint`, PHP's checks | Local | — | — | `NOT_RUN`: not on the path of this machine; the fast runs run them |
| The whole of `test:postgres` and `test:race`, the browser suite with `tests/Browser/sales/documents.spec.ts`, the build, the image checks | CI on the pull request's final head (CE-44, D-117) | — | — | `NOT_RUN` until then |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The templates as versions in code: `receipt.1` (58 mm, 80 mm, A4 "Nota Penjualan") and `session_report.1` (the report and its CSV), each rendered only by the version its snapshot names; an unknown version is a defect, never rendered by another (ARC-45) | `App\Domain\Documents\Templates`; `resources/js/lib/documents/ReceiptDocument.tsx` (`RECEIPT_TEMPLATES`), `SessionReportDocument.tsx` (`SESSION_REPORT_TEMPLATES`); `App\Domain\Documents\SessionReports\SessionReportCsv` |
| 2. RC-44 `document.print_receipt`: the Sale in scope (its cashier with CAP-21, the Owner with CAP-22), else NOT_FOUND; whether the request is a copy — every print request of the Sale after its first, by anyone — decided under a transaction-level advisory lock of the Sale's prints; audit `PRINT` (subject `sale`, reference the Sale's number, the session's filter) with the size and the copy flag; the result with `copy` and RP-103P. RP-103P: the receipt from its snapshot in the size asked for, "Salinan" first at the top of a copy, the print request the viewer's own of this Sale. RC-45 and RP-206P: a CLOSED session's report in scope, audit `PRINT` (subject `cash_session_report`), the report on A4 without a copy marker | `App\Actions\Documents\RecordReceiptPrint`, `RecordSessionReportPrint`; `App\Domain\Documents\Receipts\ReceiptPrints`; `App\Domain\Documents\SessionReports\SessionReportPrints`, `SessionReportDocuments`; `App\Http\Controllers\Documents\ReceiptPrintController`, `SessionReportPrintController`, `RecordReceiptPrintController`, `RecordSessionReportPrintController`; `App\Http\Requests\Documents\*`; `resources/js/pages/documents/ReceiptPrint.tsx`, `SessionReportPrint.tsx` |
| 3. RC-46 `document.export_session_report`: audit `EXPORT`; the answer the CSV of DC-03 rendered from the snapshot for the actor's scope now, `text/csv; charset=utf-8`, attachment `laporan-sesi-<number>.csv`, the same file for a repeat or a retry | `App\Actions\Documents\ExportSessionReport`; `App\Http\Controllers\Documents\ExportSessionReportController`; `SessionReportCsv`; `resources/js/lib/documents/requests.ts` |
| 4. RP-201 (keyset list in scope with "Tanggal", "Nomor penjualan", the Owner's "Kasir" and "Laci kas", both ways) and RP-202 (frozen lines one per product, the payment, the session link, the reprint with its size); the print controls of RP-103 (UXI-090 with its hint, UXI-092) and RP-206 (UXI-141, UXI-142); UXI-003 "Penjualan" in the navigation; UXI-100 "Batal" of UXP-104 to Penjualan for a cashier; UXI-224 a SALE_ISSUE's Sale on RP-403, a link within the viewer's scope and its number as text outside it | `App\Http\Controllers\Sales\SalesController`; `App\Http\Requests\Sales\SalesPageRequest`; `App\Queries\Sales\SaleRecords`; `resources/js/pages/sales/Index.tsx`, `Show.tsx`, `Receipt.tsx`; `resources/js/pages/cash/Session.tsx`, `OpenSession.tsx`; `resources/js/lib/documents/Printing.tsx`; `resources/js/lib/sales/sales.ts`; `resources/js/lib/navigation.ts`; `App\Queries\Inventory\MovementDetail`; `resources/js/pages/inventory/Movement.tsx` |
| 5. A print follows the commit; a failed print request is UXS-44 with "Coba lagi", the same request after an uncertain answer; the Sale is never touched | `Printing.tsx`; RC-44's writes are its audit event and result only |
| Copy | `lang/id/documents.php`, `sales.php`, `cash.php`, `inventory.php` (COPY_DECK sections 7.3, 7.6, 7.7, 7.11, 9.4, 17.1, 17.2); `resources/js/types/translation-keys.ts` |
| Routes | RP-201 `GET /sales`, RP-202 `GET /sales/{sale}`, RP-103P, RP-206P, RC-44 to RC-46 (CAP-21 own, CAP-22; the buckets `command` and `print`) in `routes/web.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| Whether a print request is the first of its Sale is decided under `pg_advisory_xact_lock(hashtextextended('document.print_receipt', sale))`, in the request's transaction, then by the committed PRINT events of the Sale (the audit's index of references). TECHNICAL_RULES section 7.2 names the steps 1, 2.1 and 8 for a print request; the lock adds no business lock and guards no fact, only that two first requests at the same moment never both print as the original (BR-DOC-04). A wait beyond the lock timeout is SERVICE_BUSY | BR-DOC-04, DC-01; `DocumentRaceTest` |
| RC-44's answer carries `copy` beside its type, key and location, as INTERACTION_CONTRACTS gives it (`printRequestId` is the result's key); the platform's result shape gains an optional `copy`, allowlisted in `CommittedData` | INTERACTION_CONTRACTS "RC-44 to RC-47"; ROUTE_CONTRACTS RC-44 |
| RP-103P's print request must be the viewer's own (ROUTE_CONTRACTS: "the committed RC-44 of this Sale and actor"); RP-206P's any committed RC-45 of the session, under the viewer's scope now | ROUTE_CONTRACTS RP-103P, RP-206P |
| RC-46's command runs through the pipeline of every command; once committed, the controller answers the file instead of the JSON, rendered from the immutable snapshot, so a repeat of the same identity is byte for byte the same file; a refusal stays the JSON refusal | INTERACTION_CONTRACTS RC-46; BR-CASH-22 |
| DC-03's CSV: each label and text quoted; the register as the session pages name it, code and label; the count of Sales a plain integer; the reason and the notes only when the report holds them; the time-zone line last. DC-03's table of drawer-funded refunds has a header line (Penjualan; Referensi; Jumlah (Rp); Waktu; Oleh) whose texts have no key in COPY_DECK section 17.2, and no refund can exist before TASK-034, which pays the first: the table, and the refund rows of the print view, are TASK-034's, and a report holding a refund is refused here (F-1) | DOCUMENT_CONTRACTS DC-03; COPY_DECK section 1.1; EXC-51 |
| DC-01's A4 table names the columns Produk, Jumlah, Harga and Subtotal, which section 17.1 has no keys for; the table uses the keys of exactly these words, `catalog.products.title`, `sales.detail.quantity`, `sales.detail.unit_price` and `documents.receipt.line_total` (F-2) | DC-01 row 9; EXC-51 |
| The copy marker is the paper's first element, centred on thermal paper and at the right of the A4 header (`.bk-paper__copy-line`) | DC-01 row 1; DESIGN_SYSTEM section 12 |
| RP-201's `number` matches a Sale's number exactly; `from` and `to` are business dates (G-18); the page offers one day, as UXP-205 does; the cursor is `Keyset`'s, read again under the scope | ROUTE_CONTRACTS RP-201; TR-PAGE-01 to TR-PAGE-03 |
| RP-202's lines are its receipt snapshot's, the names, SKUs and units frozen at the Sale (D-067); UXI-118 and UXI-120, the Owner's refunds and returns, are TASK-033's and TASK-034's records: scope item 4 gives the linked corrections to TASK-034 (F-3) | Contract scope item 4; EXC-52 |
| UXP-202's way back names the page it was opened from by the referrer: the list with its filters, the receipt, or a stock movement; else Penjualan | NC-01, NC-10; NAVIGATION_CONTRACTS UXP-202 |
| UXI-224: every source of RP-403 now carries `number`, null but for a Sale's; RP-402's history column keeps its two kinds of source (F-4) | INTERACTION_INVENTORY UXI-224; SEC-AZ-03 |
| The Sales controller reads the registers of the Cash module for the Owner's filter, as RP-205's does: OPEN_ITEMS T-46 | STK-13 |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | DC-03's refunds' table header and DC-02 row 8's refund rows need keys of COPY_DECK, and no refund exists before TASK-034 | For TASK-034 and the next change control of COPY_DECK; `SessionReportDocuments` refuses a report with a refund until then |
| F-2 | DC-01's A4 column headers have no keys of section 17.1 | Keys of the same words used; for the next change control of COPY_DECK |
| F-3 | UXI-120 is in the control list, its records TASK-033's | Scope item 4 gives it to TASK-034; as OPEN_ITEMS T-45 does for TASK-030's |
| F-4 | TASK-028's hand-over named RP-402's SALE_ISSUE source too; UXI-224 is RP-403's | RP-402 unchanged; for the Task that next changes RP-402 |

## 5. Tests (contract section 7)

| Level | Test |
| --- | --- |
| L1 CSV dialect | `tests/Unit/Documents/SessionReportCsvTest.php` |
| L2 snapshot rendering and copy marker | `tests/Feature/Documents/ReceiptPrintTest.php` (the print view's props per size and copy), `SessionReportDocumentsTest.php` |
| L4 print and export with scopes | `ReceiptPrintTest`, `SessionReportDocumentsTest`, `tests/Feature/Sales/SalesPagesTest.php`; `CapabilityMatrixTest`, `RouteInventoryTest`; races and RACE-01/RACE-02 in `tests/Concurrency/DocumentRaceTest.php`; failure injection after each write step |
| L5 print views at each size | `tests/Browser/sales/documents.spec.ts` |

## 6. Acceptance criteria

| Criterion | Evidence | Status |
| --- | --- | --- |
| AC-031-01 | `ReceiptPrintTest` (the three sizes from one snapshot, its template), `documents.spec.ts` (A4 "Nota Penjualan" with its table); no tax line in the templates | Pending CI |
| AC-031-02 | `ReceiptPrintTest`, `SessionReportDocumentsTest` (the fact tables and the session's activity version unchanged, the audit events), `DocumentRaceTest` | Pending CI |
| AC-031-03 | `SessionReportCsvTest`, `SessionReportDocumentsTest` (the exact file, a repeat and a retry the same file) | Pending CI |
| AC-031-04 | `ReceiptPrintTest` (a profile change, a deactivation and an anonymization, the reprint identical) | Pending CI |
| AC-031-05 | `ReceiptPrintTest`, `SessionReportDocumentsTest`, `SalesPagesTest`, `CapabilityMatrixTest` | Pending CI |
| AC-031-06 | `ReceiptPrintTest` (failure after each write step, the next print the original), `documents.spec.ts` (a failed print, then "Coba lagi") | Pending CI |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | Pending CI — the criteria of section 6 |
| UI and UX | Pending CI — the pages and controls of section 2, `documents.spec.ts` |
| Backend | Pending CI |
| Database | N/A — no migration |
| Security | Pending CI — scopes, refusals, channels, the guard |
| Data integrity | Pending CI — audit only, identity and idempotency, the races, failure injection |
| Performance | N/A here — bounded keyset queries on the indexes of SCHEMA section 11; the budgets are TASK-048's |
| Operations | N/A — no backup, runbook or status impact; no stored file |
| Verification | Pending — the review (section 9) and CI |

## 9. Review (EXECUTION_CONTEXT CE-40)

Pending.

## 10. Record (CE-50)

Pending.
