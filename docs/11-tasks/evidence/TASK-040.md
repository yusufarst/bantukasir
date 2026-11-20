# TASK-040 evidence — Finance report

| | |
| --- | --- |
| Task | [TASK-040](../tasks/TASK-040-finance-report.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-040-finance-report`, from `f46d215b9d02f268991f7983afa5ca585e3a6b27` (origin/develop, the squash merge of TASK-039's pull request #54) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; no branch or pull request of TASK-040 and no open pull request; the executor state at `BUILD` for TASK-040 with this session's lock, the merge of #54 verified; TASK-034 and TASK-025 `DONE`, no gate. PHP and the native `tsc` are not on this machine (`npm run typecheck` answers that `tsc` is not found), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, PHP's checks | Local | 06:12 | under 1 min | `NOT_RUN`: `tsc` is not installed natively and PHP is not on this machine; the fast run runs them |
| Guard | Local, on `edd8ca4` (checkpoint 1: the query, the page and the tests) | 06:29 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files | Fast run 1, 44837203417 on `edd8ca4` | 06:29 to 06:37 | 7 min 55 s for the job | `FAIL` (F-7): every step `PASS` — `composer test:unit` 971 tests, the targeted files `CapabilityMatrixTest` 7, `RouteInventoryTest` 8, `FinancePageTest` 3 and `FinanceReportTest` 4 tests — but `composer analyse`, 1 error: `FinancePageTest.php` line 66, "Cannot cast mixed to string" |
| The steps of fast run 1 | Fast run 2 | — | — | `NOT_RUN` until then |
| The whole PostgreSQL suite in four parts, `composer test:race`, the browser suite with `tests/Browser/reporting/finance.spec.ts`, `npm run build`, the image checks | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The report query in one read-only REPEATABLE READ transaction whose snapshot is the as-of time; the five periods as half-open Asia/Jakarta ranges with the custom refusals; figures from the allocation projections; the counts of lines with UNKNOWN cost and of products with an active mark; margin only when cost is complete; never net profit | `App\Queries\Reporting\FinanceReport`; the periods of TASK-003, `App\Support\Time\Period`; the refusals in `App\Http\Requests\Reporting\FinanceReportRequest` |
| 2. RP-502, UXP-502 with KS-10's states: the period choice (UXI-308, UXI-309), completeness, the figures, the trend (UXI-310) and its table (UXI-311), the link "Biaya perlu dilengkapi" (UXI-312), the sub-navigation item UXI-020; the percent format of CC-03 (TASK-003 F-8, D-100) in both formatters with shared cases | `App\Http\Controllers\Reporting\FinanceController`, `routes/web.php`; `resources/js/pages/reporting/Finance.tsx`, `resources/js/lib/reporting/finance.ts`, `resources/js/lib/navigation.ts`; `App\Support\Formatting\Format::percent`, `formatPercent` of `resources/js/lib/format.ts`, `tests/Unit/Support/cases/formatting.json`; `lang/id/reporting.php`, `lang/id/ux.php` |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| C-1 | **The snapshot and the as-of time.** The report is a transaction of its own on the application's connection: `set transaction isolation level repeatable read, read only`, then `select 1`, the first statement, which takes the snapshot; the application's clock read right after it is the as-of time, from which the period is computed. A row committed after the snapshot is seen by no statement of the report, whatever its time; the transaction is always rolled back, since it writes nothing |
| C-2 | **Dates, not instants.** Sales, their payments, quantities and issue projections are read by `sales.business_date`, the Asia/Jakarta date the server set at commit (G-18); refunds, payouts, returns and their projections by `sale_corrections.business_date`, the posting date (BR-COST-36, BR-RPT-01). A period's dates are those of `Period`; "until now" needs no upper instant, because nothing after the snapshot is visible |
| C-3 | **Cost of goods sold** is the period's SALE_ISSUE projections less the projections of its saleable returns (BR-COST-35, ADR-008 section 6), never the facts at commit. Each sum is in the statement that counts its rows whose cost is not KNOWN, and only KNOWN rows are summed; a Sale line or a saleable return line without a projection counts as not KNOWN, so a missing row can never make the report look complete. MANUAL_ISSUE and negative ADJUSTMENT allocations are never read (BR-COST-39; TASK-023 R-08) |
| C-4 | **Incomplete figures shown with their mark.** While cost is incomplete, HPP and gross profit show the known part with "Data biaya belum lengkap" beside it and no help line, as KS-10's `incomplete` state draws them, and the margin is hidden; the sales and payment totals stand (BR-COST-38, UXS-25). ADR-008 section 6 forbids showing such a total as complete, which the mark prevents |
| C-5 | **The marks counted.** The report counts the products with an active mark whose cost source's movement was recorded before the period's end (SCHEMA section 5.8, "every report period that ends after"), whether or not the product sold in the period: the reading that never shows a marked state as complete. The worklist of UXP-503 lists them |
| C-6 | **The margin** is gross profit × 100 / net sales, rounded half-up once to one decimal on the server (ADR-008 section 5), sent as that decimal, formatted by `formatPercent` without a second rounding; null unless cost is complete and net sales are above zero, when "Margin tidak ditampilkan karena penjualan neto Rp0 atau kurang." shows if cost is complete. A gross loss gives a negative margin, shown with the minus sign |
| C-7 | **The percent format** (CC-03, COPY_DECK section 1.3): one decimal, decimal comma, percent sign; half-up, ties away from zero, once; a figure that rounds to zero has no sign; more than six decimals refused; on both sides from one table of cases |
| C-8 | **Quantities** sold and returned are the sums of the lines' quantities over every product, whatever its unit, as BR-COST-34 names them; they are formatted with up to three decimals, the largest precision (BR-NUM-02) |
| C-9 | **Empty** (UXS-02) is a period with no Sale and no correction: the page says "Belum ada penjualan pada periode ini." in place of the figures, the completeness and the trend. A period with corrections and no Sale shows its figures, net sales then below zero |
| C-10 | **The custom range at its fields.** "Terapkan" checks both dates, the start not after the end (`ux.validation.date_order`, at "Dari tanggal"), the end not after today (at "Sampai tanggal") and at most 366 days (`ux.validation.date_range_max`, at "Sampai tanggal"), with the summary of UXS-05; a refused range is never fetched. The server refuses the same ranges, and a period's dates with any other period, `VALIDATION_FAILED`. COPY_DECK has no text for an end after today: that field says "Periksa isian", the title of UXS-05, and both fields carry `max` = today, so the date picker offers no later date (finding F-2) |
| C-11 | **The applied period** stands in the address and is kept in the session storage, which the shell clears at sign-out (SEC-CH-05), so that "Laporan keuangan" opened without a period shows the period applied last in the sign-in session (NAVIGATION_CONTRACTS UXP-502); Bulan Ini otherwise |
| C-12 | **A failed read** is `report: null`, on the page and on "Coba lagi", shown as UXS-22 with no figure, its cause in the log only; the period choice stays usable |
| C-13 | **UXI-020** is registered in the sub-navigation of Laporan, which shows once the Laporan destination exists (TASK-041), as UXI-019 and UXI-021 are |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Carried for this Task: TASK-003 F-8 — the percent format of CC-03 in both formatters with shared cases (D-100) | Done (C-7) |
| F-2 | COPY_DECK section 16.1 has no text for a custom range that ends after today, which INTERACTION_CONTRACTS section 3.4 refuses at its date field | Read as C-10; recorded as OPEN_ITEMS T-52 for the change control of COPY_DECK (TASK-047, or one before it) |
| F-3 | Carried for this Task: TASK-023 R-08 and TASK-028 — MANUAL_ISSUE allocations out of cost of goods sold | Done (C-3) |
| F-4 | Carried for this Task: TASK-033 F-3 — a refund on its correction's business date and the restored cost from the return projections | Done (C-2, C-3) |
| F-5 | Carried for this Task: TASK-026 F-1, the worklist listing an UNKNOWN source whose movement was reversed | Not taken: it is RP-503's, outside this contract's scope, and the report reads no source; recorded as OPEN_ITEMS T-53 for TASK-041 or the next change of RP-503 or RP-504 |
| F-7 | Fast run 1: PHPStan refused a cast of a mixed prop to a string in `FinancePageTest` | Fixed in checkpoint 2: the as-of time asserted a string first; fast run 2 is its one retest (D-133). In the same push, the browser test at 200 % zoom counts the trend's rows by their markup, since a narrow container shows them as cards (DESIGN_SYSTEM 8.11) |
| F-6 | For later Tasks | TASK-041 — today's figures from `FinanceReport` and the period functions of `Period` (contract section 12); the daily summary's date after today meets the same gap as F-2 (OPEN_ITEMS T-52); the Laporan destination shows UXI-020 |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 report figures on fixtures with corrections and returns: Sales by business date and corrections by posting date; refunds by method, a drawer-funded one included; saleable returns restoring cost; UNKNOWN issue and return projections counted and never summed; corrected figures after two cost corrections, an earlier period included; the margin and its rounding; net sales below zero; the half-open periods at Asia/Jakarta midnight; 366 days; an active mark, its scope and its clearance; one read-only REPEATABLE READ snapshot | `tests/Feature/Reporting/FinanceReportTest.php` |
| L4 period validation and access: only the Owner, with only the allowlisted props, never stored; every other role and the combined account refused; each period of RP-502 read; every refused range and parameter `VALIDATION_FAILED` with no figure read; the report reloaded by name; a failed read null, with its cause on neither answer | `tests/Feature/Reporting/FinancePageTest.php`; `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`; `tests/Harness/Channels/Allowlists.php` |
| L4 the custom range's checks in the page and the kept period | `tests/Unit/Reporting/finance.test.ts` |
| L1 the percent format on both sides from one table | `tests/Unit/Support/cases/formatting.json` with `FormatCasesTest.php` and `format.test.ts` |
| L1 UXI-020 in the registry | `tests/Unit/Shell/navigation.test.ts` |
| L5 KS-10 and the chart (the figure values of the `default` state are FinanceReportTest's, since the local database's other Sales make them vary): `incomplete` with the counts, the link and no margin; the chart by keyboard with its point; the table and back; the three refusals at the fields with nothing fetched; "Batal"; an empty range; the kept period; a preset; axe, contrast, targets, 1366 px, 320 px and 200 % zoom | `tests/Browser/reporting/finance.spec.ts`, with the fixture `finance` of `tests/Harness/Browser/FixturesController.php` |

## 6. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-040-01 | `FinanceReportTest::test_every_figure_comes_from_one_read_only_repeatable_read_snapshot` and its figures; `FinancePageTest::test_the_report_is_read_again_by_name_and_a_failed_read_is_never_zero`; `finance.spec.ts` | `NOT_RUN` until the fast run and the final head |
| AC-040-02 | `FinanceReportTest::test_the_periods_are_half_open_asia_jakarta_ranges`; `FinancePageTest::test_the_periods_of_rp_502_are_read_and_a_custom_range_is_refused_before_anything_is_read`; `finance.test.ts`; `finance.spec.ts` | `NOT_RUN` until then |
| AC-040-03 | `FinanceReportTest` (incomplete by UNKNOWN rows and by a mark; the margin only when complete and net sales above zero); `finance.spec.ts` (`incomplete`); no figure of net profit exists | `NOT_RUN` until then |
| AC-040-04 | `FinanceReportTest::test_the_figures_follow_the_dates_the_projections_and_the_corrections` (two corrections, October corrected; refunds on 5 November) | `NOT_RUN` until then |
| AC-040-05 | `finance.spec.ts` (KS-10's three states, UXA-19); `FinancePageTest` and `CapabilityMatrixTest` RP-502 (only the Owner) | `NOT_RUN` until then |

## 7. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A |
| EXC-45 | The fixtures through the Actions: Sales by RC-01, corrections by RC-05 and RC-06, receipts by RC-07, cost corrections by RC-15 (`TokoContoh`); a mark by a refused RC-15 under a small movement cap; the snapshot test's concurrent publication written by the migration role as another session |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `NOT_RUN` until the checks of section 1 — the criteria of section 6 and REQ-RPT-01 to REQ-RPT-03, REQ-RPT-06, REQ-COST-09 |
| UI and UX | `NOT_RUN` until the browser suite on the final head — KS-10's states, UXI-308 to UXI-312, the copy keys verbatim (`CopyDeckTest`), UXA-19, 320 px and 200 % zoom |
| Backend | N/A — no route or Action beyond RP-502 |
| Database | N/A — no migration |
| Security | `NOT_RUN` until then — CAP-20 only, every other role and the combined account refused, the props allowlisted on both channels, a failed read's cause on no answer, the guard |
| Data integrity | N/A — a read only, in a read-only transaction that is always rolled back |
| Performance | `PASS` for what applies — every statement bounded by the period's dates through the indexes on `business_date`, the trend at most 366 points; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | `NOT_RUN` until the review and the checks |
