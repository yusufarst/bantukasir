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
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `eeb9fb8` | 12 Nov, 19:27 to 19:31 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 1, 43218659843 on `eeb9fb8` | 12 Nov, 19:31 to 19:35 | 4 min 9 s for the run | `PASS` |
| `composer analyse` | Same run | 19:35 | 18 s | `FAIL`: a boolean in a result's detail, `json_decode` in Domain code (the rule of exact values), two redundant checks (F-5); the later steps did not run |
| Guard | Local, on `d00a841` | 12 Nov, 19:36 to 19:39 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse` (F-5 fixed), `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 2, 43219918178 on `d00a841` | 12 Nov, 19:39 to 19:45 | about 6 min | `PASS` |
| Changed PostgreSQL and concurrency files: `DocumentRaceTest` (2), `CapabilityMatrixTest` (7), `RouteInventoryTest` (8), `ReceiptPrintTest` (6), `SessionReportDocumentsTest` (4), `IssuePagesTest` (5), `StockPagesTest` (7) | Same run | 19:45 to 19:49 | about 4 min | `PASS` |
| `CheckoutSaleTest` | Same run | 19:49 to 19:50 | about 1 min | `FAIL`: 1 of 13, the one-writer scan found RP-201's controller, whose page prop is named `sales` (F-6); `SalesPagesTest` did not run |
| Guard | Local, on `0719394` | 12 Nov, 19:51 to 19:55 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the nine changed PostgreSQL and concurrency files, `CheckoutSaleTest` (F-6 fixed) and `SalesPagesTest` among them | Fast run 3, 43222387296 on `0719394`, the reviewed head | 12 Nov, 19:55 to 20:07 | 12 min 26 s | `PASS` |
| Self-review of the whole change against the contract | Local, on `0719394` | 12 Nov, 20:07 | — | Done; nothing beyond the fast runs' findings |
| The review, round 1 (section 8.1) | On `0719394` with the record, package `r1` | 12 Nov, 20:08 to 20:15 | about 7 min | `FAIL`, two MUST-FIX; fixed in checkpoint 5 |
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
| DC-03's CSV: each label and text quoted; the register as the session pages name it, code and label; the count of Sales a plain integer; the reason and the notes only when the report holds them; the time-zone line; an empty line; the header line of the drawer-funded refunds' table, also without a row (review R-01). Its texts — Penjualan, Referensi, Jumlah (Rp), Waktu, Oleh — have no key in COPY_DECK section 17.2; the file uses the keys of exactly these words, `sales.list.title`, `sales.detail.reference`, `sales.detail.quantity` with `documents.session_report.money_header`, `sales.list.col_time` and `inventory.history.col_actor`, as the A4 table does (F-1, F-2). No refund can exist before TASK-034, which pays the first: the table's rows and the print view's rows of DC-02 row 8 (whose key `documents.session_report.drawer_refund_row` COPY_DECK names) are TASK-034's, and a report holding a refund is refused until then, so `session_report.1` renders every report that exists now exactly as it will after TASK-034 | DOCUMENT_CONTRACTS DC-02, DC-03; COPY_DECK section 1.1; EXC-51; ARC-45 |
| RP-103's on-screen receipt is marked not to print (`bk-no-print`): only a print request's view prints, with its copy marker (review R-02). RP-103P and RP-206P open the print dialog by themselves only on the navigation that follows the request, never on a reload or a move back (`openedForPrinting`, review R-03); a print through the browser's own menu cannot be prevented | BR-DOC-04, DC-01 |
| RP-403's SALE_ISSUE source is read by the Sales module (`App\Queries\Sales\SaleSources`) from the movement's Sale line, which the Inventory module gives (`MovementDetail::saleLineOf`), and composed by the movement's controller: no module dependency is added (review R-05; STK-10, STK-13). RP-201's controller reads the Cash module's registers for the Owner's filter, a further instance of OPEN_ITEMS T-46, which this Task does not reword (EXC-35) | STK-13; OPEN_ITEMS T-46 |
| TECHNICAL_RULES section 7.2 names the lock of a receipt's prints for RC-44, a technical correction (EXC-81; review R-04) | EXC-81 |
| RP-103P renders the size its address names, which may differ from the size the request recorded; the contract leaves `size` to the address (review R-07, a NOTE) | ROUTE_CONTRACTS RP-103P |
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
| F-1 | DC-03's refunds' table header has no keys of section 17.2; its rows, and DC-02 row 8's, need refunds, which TASK-034 pays first | The header written with keys of the same words (section 3), for the next change control of COPY_DECK; the rows TASK-034's; `SessionReportDocuments` refuses a report with a refund until then |
| F-2 | DC-01's A4 column headers have no keys of section 17.1 | Keys of the same words used; for the next change control of COPY_DECK |
| F-3 | UXI-120 is in the control list, its records TASK-033's | Scope item 4 gives it to TASK-034; as OPEN_ITEMS T-45 does for TASK-030's |
| F-4 | TASK-028's hand-over named RP-402's SALE_ISSUE source too; UXI-224 is RP-403's | RP-402 unchanged; for the Task that next changes RP-402 |
| F-5 | PHPStan: a result's detail holds whole numbers and texts, so the copy flag is stored as 1 or 0; `json_decode` is refused in Domain code, so the report's refunds are counted by `jsonb_array_length` | Fixed in checkpoint 4 |
| F-6 | `CheckoutSaleTest`'s one-writer scan names files by a quoted table name, which RP-201's page prop `sales` matches | The scan names the controller, and every reader of TASK-031 is held to write nothing; test only |

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

## 7. Definition of Done

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

## 8. Review (EXECUTION_CONTEXT CE-40)

### 8.1 Round 1, the full review

Agent `bk-reviewer-high` (Claude Opus 5.5, effort High), package `r1` (base `973f190`, head `0719394` with the record, tree `ad63e5b`), 12 November 2026, 20:08 to 20:15; the working tree, index, HEAD and state `UNCHANGED` after it. Verdict `FAIL`, two MUST-FIX.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | DC-03's file ended at the time-zone line, without the empty line and the refunds' table header; adding them later would change `session_report.1` in place; F-1 wrongly said DC-02 row 8 had no key | Fixed: the empty line and the header written now with keys of the same words (section 3, F-1); `SessionReportCsvTest` and `SessionReportDocumentsTest` assert the whole file, `documents.spec.ts` its end; F-1 corrected |
| R-02 | MUST-FIX | RP-103's on-screen receipt printed through the browser's menu as an unmarked, unaudited original | Fixed: the preview is `bk-no-print`; `documents.spec.ts` emulates print media on RP-103 and finds no paper |
| R-03 | SHOULD-FIX | The print views opened the print dialog on every load, a reload printing the original again unaudited | Fixed: only on a new navigation (`openedForPrinting`); `documents.spec.ts` reloads an original's view and counts no print; the browser's own menu stays a limit |
| R-04 | SHOULD-FIX | The lock of a receipt's prints not in TECHNICAL_RULES section 7.2 | Fixed: the row names it (EXC-81) |
| R-05 | SHOULD-FIX | The Inventory module's query read `sales` and `sale_lines`, a cycle against STK-13; RP-201's read of Cash not in T-46 | Fixed: `SaleSources` in the Sales module, composed by the movement's controller; RP-201 recorded in section 3 as a further instance of T-46, which this Task does not reword (EXC-35) |
| R-06 | SHOULD-FIX | The checks table lacked the fast runs and the self-review; the sections jumped from 6 to 8 | Fixed: section 1; sections renumbered |
| R-07 | NOTE | RP-103P renders the address's size, not the recorded one | Recorded (section 3); the contract leaves the size to the address |

## 9. Record (CE-50)

Pending.
