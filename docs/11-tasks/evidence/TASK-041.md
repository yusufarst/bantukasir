# TASK-041 evidence — Owner home and daily summary

| | |
| --- | --- |
| Task | [TASK-041](../tasks/TASK-041-owner-home-daily.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-041-owner-home-daily`, from `fa4f98b48bac548c9e384b7d6e81e56856d1d344` (origin/develop, the squash merge of TASK-040's pull request #55) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; no branch or pull request of TASK-041 and no open pull request; the executor state at `BUILD` for TASK-041 with this session's lock, the merge of #55 verified; TASK-039, TASK-040 and TASK-032 `DONE`, no gate. PHP, the native `tsc` and ESLint are not on this machine (`npm run typecheck` answers that `tsc` is not recognized), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 11:30 | under 1 min | `NOT_RUN`: `tsc` is not installed natively (`'tsc' is not recognized`), nor ESLint or PHP; the fast run runs them |
| Guard | Local, on `aeadc3d` (checkpoint 1: the pages, the queries, the routes and the tests) | 11:49 to 11:53 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files | Fast run 1, 44868271561 on `aeadc3d` | 11:53 to 12:01 | about 8 min | `FAIL` (F-6): `npm run lint`, `typecheck` and `test:unit` `PASS`; the targeted files `HomePageTest`, `HomeRedirectTest`, `RouteInventoryTest`, `CashPagesTest` and `TillPagesTest` `PASS`; two causes failed the rest — `DailySummaryRequest::date()` overrode `Request::date()` (PHP fatal at its load: `CapabilityMatrixTest` and `DailyPageTest` ended, PHPStan `parameter.missing`), and the helper `DailySummaryTest::session()` overrode `TestCase::session()` (PHP fatal at its load: `composer lint`, `PostgresPartsTest` and `DailySummaryTest`, PHPStan `method.static`); PHPStan also refused `is_string()` of a closure's by-reference null in `HomePageTest` line 101 |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. RP-007, UXP-007 "Beranda" with KS-02's states: three deferred panels, each its own group and failing on its own — today's four figures with the as-of line and "Rekap harian" (UXI-059), the open cash sessions (UXI-060), the attention items (UXI-061 to UXI-065, UXI-064 from `SystemStatus::attention()`); RN-01 sends the Owner to Beranda; "Beranda" (UXI-001) and "Laporan" (UXI-007) in his navigation; the Owner's "Batal" of UXP-104 leads to Beranda (UXI-100) | `App\Queries\Reporting\OwnerHome`, `App\Http\Controllers\Reporting\HomeController`, `App\Domain\Access\Homes`, `CostWorklist::count()`, `routes/web.php`; `resources/js/pages/reporting/Home.tsx`, `resources/js/lib/reporting/home.ts`, `resources/js/lib/navigation.ts`, `resources/js/pages/cash/OpenSession.tsx`; `lang/id/reporting.php`, `lang/id/ux.php` |
| 2. RP-501, UXP-501 "Rekap harian": one read-only snapshot; the day's figures computed once by the finance report's computation; the sessions closed on the date with their full span and own variance, the sessions still open apart; a date after today refused; the date control (UXI-306), the session rows (UXI-307) and the sub-navigation item (UXI-018) | `App\Queries\Reporting\DailySummary`, `FinanceReport::within()`, `App\Http\Controllers\Reporting\DailyController`, `App\Http\Requests\Reporting\DailySummaryRequest`; `resources/js/pages/reporting/Daily.tsx`, `resources/js/lib/reporting/daily.ts`, `resources/js/lib/reporting/Parts.tsx` (the completeness and figures, now shared with `Finance.tsx`), `addDays` of `resources/js/lib/format.ts` |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| C-1 | **Panels that fail on their own.** Each panel is `Inertia::defer` in a group of its own, so the browser reads it by a request of its own after the page; its read is guarded on the server, so a failure of that read is the panel `null` (UXS-22 with "Coba lagi", which reads that prop alone again), its cause in the log only, while the other panels' requests stand. A failure of the request itself — no connection, a server that does not answer — is the shell's, as for every page (UXS-20) |
| C-2 | **Today's figures** are the four that KS-02 shows — net sales, the number of Sales, cash and bank-transfer payments — from `FinanceReport` for today in its own snapshot, with its as-of time as the page's as-of line; no cost reaches Beranda |
| C-3 | **The open sessions** are every OPEN session, by opening time, each with its register's label, its responsible user's frozen name and its opening time (":laci · :nama · dibuka :waktu"), bounded as `OpenSessions` is (500; one per register and per user at most, BR-CASH-06) |
| C-4 | **An attention item appears exactly when its evidence says so** (AC-041-03): a count above zero or a state that holds. INTERACTION_INVENTORY gives UXI-061 "Always"; KS-02's approved `quiet` state shows no restock row and "Tidak ada yang perlu perhatian saat ini.", which is read as the item always being available to the Owner, shown when products need restocking |
| C-5 | **UXI-061** counts the Habis and Menipis products by the one definition that RD-03's badge uses (`StockAttention`, ARC-22; "Count from RD-03"), read with the panel; RP-401's `state` takes one state, so the item leads to Stok filtered to Habis while any product is Habis and otherwise to Menipis |
| C-6 | **OPEN_ITEMS T-53.** UXI-062 counts exactly the worklist's sources (`CostWorklist::count()`, the worklist's own definition), as INTERACTION_INVENTORY words it ("at least one cost is UNKNOWN"); a reversed UNKNOWN source therefore stays in both, so the item and the list it leads to agree. Whether it leaves the worklist is a change of RP-503, outside this contract (section 4.3); T-53 stays OPEN for that change, which then changes both |
| C-7 | **The daily summary's figures** are those of `FinanceReport::within()` for the one day, in the summary's own snapshot: one computation of the figures from the source records for both reports (BR-RPT-01). Sales by business date; refunds and returns by posting date; no session total, opening cash or variance added |
| C-8 | **The sessions of a day** (BR-RPT-02): a CLOSED session is listed on the Asia/Jakarta date of its close, once, with its opening time — the day before for one that crossed midnight, marked "Melewati tengah malam" — its register as its report froze it and its own variance; an OPEN session is listed in "Masih dibuka" for every date from its opening until it closes; a return to work the same day is a session of its own. A session crosses midnight when its opening and its close (for an OPEN one, the snapshot) fall on different business dates. Each list is bounded at 500 |
| C-9 | **The date control.** "Hari sebelumnya" always; "Hari berikutnya" only before today; "Pilih tanggal" with `max` today, a date after today refused at the field with "Periksa isian", the title of UXS-05, and never fetched, as TASK-040 read the same gap (OPEN_ITEMS T-52); the date stands in the address, so it is kept on the way back from a session |
| C-10 | **The Laporan destination** opens Rekap harian (COPY_DECK section 4.3), the first item of its sub-navigation; that sub-navigation now shows on every Laporan page, so `finance.spec.ts` finds the completeness notice's link "Biaya perlu dilengkapi" inside the notice, apart from the sub-navigation's item |
| C-11 | **The Owner's home** is Beranda with or without an OPEN session of his own (NC-12); the browser suite's sign-in waits for it (`OWNER_HOME`), and the tests that used his landing on Akun saya open it themselves |
| C-12 | **No session on a day** says "Belum ada sesi kas.", the existing key `cash.sessions.empty` of COPY_DECK section 7.10, since section 10.2 gives the list no empty text of its own |
| C-13 | **A quiet status.** HomePageTest proves the status item raised without evidence; its quiet case is `SystemStatusTest`'s (`attention()` false with every item proven) and `home.test.ts`'s (no item for `false`) |

## 4. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-041-01 | `DailySummaryTest::test_a_day_counts_its_own_sales_and_refunds_and_lists_each_session_once` (two Sales, not three; the night session once with its full span; nothing counted twice) and `test_the_summary_is_one_read_only_snapshot_and_a_date_after_today_reads_nothing`; `DailyPageTest`; `daily.spec.ts` | See section 1 |
| AC-041-02 | `DailySummaryTest` (23.59 and 00.01 WIB on 31 October and 1 November) | See section 1 |
| AC-041-03 | `HomePageTest::test_each_panel_fails_on_its_own_and_a_failed_panel_is_never_zero` and `test_the_attention_items_follow_their_evidence_by_the_definitions_of_their_pages`; `home.test.ts`; `home.spec.ts` (`failed`, `loading`, `quiet`, "Coba lagi") | See section 1 |
| AC-041-04 | `home.spec.ts` (KS-02's states), `shell.spec.ts` and `profile.spec.ts` (the Owner lands on Beranda); `HomePageTest`, `DailyPageTest`, `CapabilityMatrixTest` RN-01, RP-007 and RP-501, `HomeRedirectTest` | See section 1 |

## 5. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Carried for this Task: TASK-039 F-5, UXI-064 from `SystemStatus::attention()` | Done (C-1; the attention panel) |
| F-2 | Carried for this Task: TASK-040 F-6 — today's figures from `FinanceReport`, the Laporan destination with UXI-019 to UXI-021, and the date after today of OPEN_ITEMS T-52 | Done (C-2, C-7, C-10); the date after today read as C-9, T-52 stays OPEN for the change control of COPY_DECK |
| F-3 | Carried for this Task: OPEN_ITEMS T-53 (TASK-026 F-1, TASK-040 F-5) | Read as C-6; T-53 stays OPEN for the next change of RP-503 or RP-504, its text amended |
| F-4 | Carried for this Task: TASK-035 F-8, the attention item "Masa input stok awal aktif" (UXI-065); TASK-030 R-13, UXP-205 under Laporan for the Owner | Done: UXI-065 while onboarding is ACTIVE; the Laporan destination shows "Sesi kas" (UXI-019) |
| F-6 | Fast run 1 (44868271561 on `aeadc3d`): two names that override the framework's — `DailySummaryRequest::date()` (Laravel's `Request::date()`) and `DailySummaryTest::session()` (`TestCase::session()`) — each a PHP fatal error where its class loads, and a PHPStan finding of an impossible type in `HomePageTest` | Fixed in one push (checkpoint 2): `businessDate()`, `sessionRow()`, and the failure injection's marker a typed property of the test; fast run 2 is their one targeted retest (D-133) |
| F-5 | For later Tasks | TASK-048 — the budgets of the panels and the summary (contract section 9, Performance); the next change control of COPY_DECK — T-52's text, which UXP-501's date then uses |

## 6. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 daily summary fixtures across midnight: Sales at 23.59 and 00.01 WIB of one session on two business dates; a refund on its posting date; the session that crossed midnight once, on its close date, with its full span and variance; a return to work the same day a session of its own; an OPEN session apart, marked crossing midnight the next day; no session total, opening cash or variance in a figure; one read-only REPEATABLE READ snapshot; a date after today refused with nothing read | `tests/Feature/Reporting/DailySummaryTest.php` |
| L4 access: only the Owner reaches RP-007 and RP-501, RN-01 sends him to Beranda, the other roles and the combined account refused, the page and panel props allowlisted on both channels, never stored; RP-501's date refused after today, not in the calendar or with any other parameter, nothing read; each panel failing on its own as null; the summary read again by name and null when its read fails | `tests/Feature/Reporting/HomePageTest.php`, `DailyPageTest.php`; `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`, `HomeRedirectTest.php`; `tests/Feature/Cash/CashPagesTest.php`, `tests/Feature/Sales/TillPagesTest.php`; `tests/Harness/Channels/Allowlists.php` |
| L2 the attention items' evidence follows the records by the definitions of their pages (the restock count equal to RD-03's, the cost count to the worklist's) | `HomePageTest::test_the_attention_items_follow_their_evidence_by_the_definitions_of_their_pages` |
| L1 the attention items shown exactly when their evidence says so; the date control across months, years and a leap day, no day after today, a refused date | `tests/Unit/Reporting/home.test.ts`, `daily.test.ts` |
| L1 UXI-001, UXI-007 and UXI-018 in the registry; `addDays` across a leap day and the years' bounds | `tests/Unit/Shell/navigation.test.ts`, `tests/Unit/Reporting/daily.test.ts` |
| L5 KS-02 `default`, then `failed`, `loading` and `quiet` by the panels' answers, "Coba lagi", UXI-060 to its detail, 1440 px, 320 px, 200 % zoom with the keyboard; UXP-501 from "Laporan", completeness, the session still open, the note, the day before and after, a later date refused with nothing fetched, an empty day, a failed read and "Coba lagi", the date kept on the way back | `tests/Browser/reporting/home.spec.ts`, `daily.spec.ts`; the landing of the Owner in `shell/shell.spec.ts` and `configuration/profile.spec.ts` |

## 7. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A |
| EXC-45 | The fixtures through the Actions: sessions by RC-02 and RC-03, Sales by RC-01, a correction by RC-05 and RC-06, receipts by RC-07, the onboarding start by RC-12 (`TokoContoh`); a posting block through the Inventory module's block service |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `PASS` for what has run — AC-041-01 to AC-041-03 and REQ-RPT-04 by the PHP and unit tests (section 1); the browser cases on the final head |
| UI and UX | `PASS` for what has run — the copy keys verbatim (`CopyDeckTest`, `composer lint`), the controls UXI-001, UXI-007, UXI-018, UXI-059 to UXI-065, UXI-306, UXI-307 and UXI-100's destination; KS-02's states, the widths, 200 % zoom and axe in the browser suite on the final head, `NOT_RUN` until then |
| Backend | N/A — no route or Action beyond RP-007 and RP-501, which this Task builds with their gates and allowlists |
| Database | N/A — no migration |
| Security | `PASS` — CAP-22 only, every other role and the combined account refused on the page and the panels, the props allowlisted on every channel, a failed read's cause on no answer, no secret (the guard) |
| Data integrity | N/A — reads only; the summary and today's figures in read-only transactions always rolled back |
| Performance | `PASS` for what applies — every statement bounded by a date, an aggregate or a limit of 500; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact; the status is read, never changed |
| Verification | See section 1 and section 9 |
