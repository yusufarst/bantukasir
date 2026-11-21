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
| Guard | Local, on `f6bd5a8` (checkpoint 2: F-6) | 12:03 to 12:07 | about 4 min | `PASS`, no candidate |
| The steps of fast run 1 | Fast run 2, 44869411808 on `f6bd5a8`, the reviewed head | 12:08 to 12:17 | 9 min 39 s for the job | `PASS`: every step, `composer lint`, `analyse` and `test:unit` included, and the eight targeted PostgreSQL files (`DailySummaryTest`, `DailyPageTest`, `HomePageTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `HomeRedirectTest`, `CashPagesTest`, `TillPagesTest`); the retest of F-6, cured |
| Fast checks; image checks; restore rehearsal | CI on the pull request's first head `5a319d0` (fast run 44872200502, ci 44872217699) | 12:43 to 12:53 | 8 min 47 s; 5 min 27 s; 5 min 46 s | `PASS`; the PostgreSQL parts and the command contract still pending when the fix cycle of F-7 began, so their results there are superseded by the fix's head |
| `npm run typecheck`, ESLint, the unit tests of F-7's fix | Local | 12:53 | — | `NOT_RUN`: no native `tsc`, ESLint or PHP on this machine; they run in the fast run and the command contract of the fix's head |
| Fast checks | Fast run 44874114531 on `21007d7`, F-7's fix | 13:08 to 13:17 | 8 min 57 s | `PASS` |
| PostgreSQL (1/4) to (4/4); restore rehearsal; image checks; the command contract's `composer validate`, `lint`, `analyse`, `test:unit`, `test:race`, `npm run lint`, `typecheck`, `test:unit` and `build` | CI 44874117858 on `21007d7` | 13:08 to 13:40 | 20 min 14 s, 9 min 51 s, 13 min 31 s, 14 min 8 s; 5 min 46 s; 6 min 30 s; within the command contract's 31 min 47 s | `PASS`; valid for the fix of F-8, which changes two browser specs only (CE-45) |
| `npm run test:e2e` | CI 44874117858 on `21007d7` | 13:20 to 13:40 | 20.0 min | `FAIL` (F-8): 340 passed, 2 failed — `access/users.spec.ts:294` and `reporting/home.spec.ts:124` — and the 2 tests of the project `maintenance` not run, because it depends on the project `people` |
| ESLint of the two specs of F-8's fix | Local | 13:46 | — | `NOT_RUN`: `'eslint' is not recognized`; the fast run and the command contract of the fix's head run it |
| The browser suite, with `reporting/home.spec.ts`, `access/users.spec.ts` and `operations/maintenance.spec.ts`; the steps of fast checks | CI on the fix's head, the pull request's final head | — | — | `NOT_RUN` until then; the retest of F-8 (CE-42, D-133) |

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
| C-5 | **UXI-061** counts the Habis and Menipis products by the shell's live read of RD-03 — the badge's own store, refreshed as the badge, its last count kept with "Data belum diperbarui" when a refresh fails (BR-INV-41, UXS-21; review R-01) — and, before the shell's first read, by the panel's count of the same definition (`StockAttention`, ARC-22). RP-401's `state` takes one state, so the item leads to Stok filtered to Habis while any product is Habis and otherwise to Menipis, by the panel's split (OPEN_ITEMS T-54; review R-02). RD-03 carries only the total, so the panel is read again in the background with each later read of the badge (`splitStep`, F-7): a Menipis product sold out, the count unchanged, turns the link to Habis; a read again that fails marks the item "Data belum diperbarui" (UXS-21), and an answer whose read failed shows the panel's failed state (UXS-22) |
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
| F-7 | Pull request #56, review comment 4650905251 of the automated reviewer (P2), on head `5a319d0`: when a Menipis product turns Habis while Beranda stays open, RD-03's refresh keeps the count and the link still opens `/stock?state=LOW`, which RP-401 applies exactly, so the list is empty and the Habis product unseen (review R-09's NOTE). Valid; it bears on AC-041-03 and on stock (D-133, CE-43) | Fixed in the fix cycle: `splitStep` in `resources/js/lib/reporting/home.ts` and `useRestockSplit` in `Home.tsx` read the attention panel again with each later read of RD-03, the item marked stale when that read fails (C-5); regression tests in `home.test.ts` (the same count of one, the split read again, the link to Habis; no read again for the panel's own new evidence, before the badge's first read, without evidence or without the item) and `home.spec.ts` (the badge reads 1 twice, the panel's split turns from Menipis to Habis, the link from `LOW` to `OUT`, then a read again that fails keeping the link with "Data belum diperbarui", then an answer whose read failed showing "Gagal memuat", each read again marked `X-Background-Request: 1`). Targeted verification of the class-A fix (CE-43), sections 9.3 and 9.4 |
| F-8 | CI 44874117858 on `21007d7`, the command contract's browser suite, failure 1 of 3 (CE-42): two test defects of this Task's change, each found in the job's log. (a) `access/users.spec.ts:321` waited, after a 401 on a first command, for `/`, `/login` or `HOME` (`/account`); the server still holds the sign-in session, so `/` now leads the Owner to Beranda (`/home`, C-11), which the predicate never accepted: "Test timeout of 120000ms exceeded" after eight navigations to `/home`. C-11 and round 1 missed this spec. (b) `reporting/home.spec.ts:214`, KS-02 `quiet`: the panel's answer was pinned at nothing to attend to, but the restock item counts by the badge's live read of RD-03 (C-5, R-01), which read the shared database's Habis and Menipis products, so "Tidak ada yang perlu perhatian saat ini." never showed | Fixed in one push, tests only: (a) the predicate accepts `OWNER_HOME` in place of `HOME`; (b) the `quiet` case also answers RD-03 with no product to restock, removed after it. The run of the command contract on the fix's head is their one targeted retest (D-133). No production behaviour changes, so no targeted verification (CE-42) |
| F-5 | For later Tasks | TASK-048 — the budgets of the panels and the summary (contract section 9, Performance), the attention panel read again at the shell's cadence while Beranda is open (review R-14), and the silent bound of 500 sessions per list (review R-04); the next change of RP-401 — OPEN_ITEMS T-54 (review R-02); the next change control of COPY_DECK — T-52's text, which UXP-501's date then uses |

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
| Verification | `PASS` for what runs before the pull request: the self-review; the class-A review and the targeted check of its MUST-FIX `PASS` (section 9); fast run 2 green. The command contract and the image checks run on the pull request's final head before the merge (CE-41) |

## 9. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review: the summary against SALES_CASH BR-RPT-01 to BR-RPT-04, INTERACTION_CONTRACTS section 3.4 and TR-TX-07 — one snapshot, the figures by the finance report's computation, each session once on its close date with its span, the open ones apart, nothing added on top, a later date refused before any read; Beranda against KS-02, NAVIGATION_CONTRACTS UXP-007, INTERACTION_INVENTORY UXI-059 to UXI-065, INTERACTION_CONTRACTS section 9 and OPERATIONS_RUNBOOK section 5 — each panel deferred and failing on its own, each attention item by the definition of its page and only for its capability; the routes, gates and allowlists on every channel; the copy keys verbatim; the obligations of EXECUTION_CONTEXT section 10 for two routes, three reads and two pages; the browser specs that depended on the Owner's landing on Akun saya.

### 9.1 Round 1, the full review

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `review`; package `review/TASK-041/r1` of the executor state, base `fa4f98b`, HEAD `f6bd5a8`, snapshot tree `f69f5aa` with the uncommitted record; 21 November, 12:19 to 12:31; `review-pack.sh --check` `UNCHANGED` after it. Verdict `FAIL`: one MUST-FIX, two SHOULD-FIX and four NOTEs, from the code and the documents, with no test run asked for. It tried and found holding: one snapshot and the figures computed once, the two session lists unable to overlap, the dates across midnight, a later date refused before any read, each panel guarded and its failure never reaching the page, the gates, the allowlists on both channels, `no-store`, RN-01, and no browser spec left on the Owner's old landing.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | UXI-061's count was read once with the panel, never refreshed as the badge (BR-INV-41), and a failed refresh could not show UXS-21 | Fixed: the item counts by the shell's live read of RD-03 (`useUnresolved().stockAttention`), the panel's count before the first read, marked stale after a failed refresh (C-5); regression tests in `home.test.ts` (the live count over the panel's, zero hiding the item, stale, no capability) and `home.spec.ts` (an answer of RD-03 of 7 shows "Perlu restok (7)" beside the badge's 7) |
| R-02 | SHOULD-FIX | The item links to one state of RP-401 while counting two | Recorded as OPEN_ITEMS T-54, cited from C-5; the interim link kept |
| R-03 | SHOULD-FIX | No test of the Owner's "Batal" on UXP-104 leading to Beranda (UXI-100) | Fixed: `cash.spec.ts` asserts its `href` `/home` before the Owner opens his session |
| R-04 | NOTE | The session lists and the open-sessions panel stop at 500 rows without a sign | Recorded for TASK-048 with F-5 |
| R-05 | NOTE | The zoom test's comment named Tab but focused the link | The comment reworded |
| R-06 | NOTE | Each session row carries its number, which the page does not show | Kept: allowlisted and harmless; the row is the session's |
| R-07 | NOTE | UXI-062 counts reversed UNKNOWN sources, as the worklist does | None: C-6 and OPEN_ITEMS T-53 |

### 9.2 Round 2, the targeted check of R-01

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `verify`; package `review/TASK-041/r2` with `fix.patch` from round 1's tree; 21 November, 12:34 to 12:37; `review-pack.sh --check` `UNCHANGED` after it. Verdict `PASS`: R-01 FIXED (the item reads the badge's store, refreshed by the shell on load, every 30 s, on focus and after each command, and re-renders on each publish; stale marked after a failed refresh; never a zero), R-02 FIXED as recorded, R-03 FIXED, R-05 FIXED.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-08 | SHOULD-FIX | The browser case of R-01 left the panel's real count free, so it could pass with the old code if the fixtures gave 7 | Fixed: the case pins the panel's answer at nothing to restock, so only the badge's read can show "Perlu restok (7)", and asserts the exact `href` `/stock?state=LOW`. A change of a test only, so no further check (CE-40) |
| R-09 | NOTE | The link's state comes from the panel's split at page load, RD-03 carrying only the total | With OPEN_ITEMS T-54 (C-5); then raised as a P2 on the pull request and fixed as F-7 |
| R-10 | NOTE | A failed RD-04 read after a good RD-03 marks a current count stale | None: the shell's notice says the same, on the safe side |

The fixes change `home.ts`, `Home.tsx` and tests; their TypeScript checks and unit tests run in the fast run of the record's push, the browser cases on the pull request's final head (CE-45).

### 9.3 Round 3, the targeted check of F-7 (fix cycle, CE-43)

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `verify`; package `review/TASK-041/r3` with `fix.patch` from round 2's tree, HEAD `5a319d0`, tree `5936750`; 21 November, 12:54 to 12:58; `review-pack.sh --check` `UNCHANGED` after it. Verdict `FAIL`: F-7 FIXED — no read again on the panel's own new evidence, at most one per read of RD-03 with a new as-of time, a failed read of RD-03 or RD-04 changing nothing, either order at the page's load safe, a failed read again or a null answer honest, R-01 and R-08 and "Coba lagi" unchanged, the tests failing on the old code — with one new MUST-FIX.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-11 | MUST-FIX | The read again was sent without `X-Background-Request: 1`, so the server touched the sign-in session and the shell counted user activity every 30 s: the Owner's idle limit never ran out on Beranda (RT-10, SEC-SESS-02, THR-18) | Fixed: the header on the read again (`Home.tsx`); `home.spec.ts` asserts it on each read again |
| R-12 | SHOULD-FIX | The browser case could change the badge's time before its first read was published, a race | Fixed: the case waits for the badge "1 produk perlu restok" first |
| R-13 | SHOULD-FIX | No failure injection of the read again | Fixed: a read again answered 503 keeps the item and its link with "Data belum diperbarui"; one answered `attention: null` shows "Gagal memuat" and no item |
| R-14 | NOTE | The attention panel is read at the shell's cadence; a split may lag one cycle after a failed first read of RD-03 | Recorded for TASK-048 with F-5 |

### 9.4 Round 4, the targeted check of R-11 alone

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `verify`; package `review/TASK-041/r4` with `fix.patch` from round 3's tree, HEAD `5a319d0`, tree `6c4bdaa`; 21 November, 12:59 to 13:02; `review-pack.sh --check` `UNCHANGED` after it. Verdict `PASS`: R-11 FIXED — the header reaches both the visit that AppShell's `start` listener reads and the request that `EnsureSignInSession` reads (Inertia 3.9.1 `doReload`, `getHeaders`); the assertion fails on round 3's code; the extended segment deterministic (route order, the 503 path through `onHttpException` without the global dialog, the 503 console line allowed by `injected()`, the null path, R-12's wait). No new finding.

The fix cycle's checks: its TypeScript checks, ESLint and unit tests run in the fast run and the command contract of its head, the browser segment in the command contract's browser suite (CE-45); none could run natively.

## 10. Guard's candidates (CE-31; D-076, D-097, D-098)

No candidate on checkpoints 1 and 2, nor in the preview of the record's change before its commit (`GUARD PREVIEW PASS`, 12:41); the guard on the record's head is stated in the pull request. The fix cycle of F-7: `GUARD PREVIEW PASS` at 13:03, no candidate. The fix cycle of F-8: `GUARD PREVIEW PASS` at 13:47, no candidate.

## 11. Record (CE-50)

- Backup impact (EXC-47): none; no new table or kind of stored file.
- The previous merge: TASK-040's pull request #55 (head `ebd16b9`, CI 44857211790; squash `fa4f98b` by the tick, the automated review DONE; CI on `develop` 44861036973) — recorded in [TASK-040's evidence](TASK-040.md) section 12 and in CURRENT_HANDOFF.
- Documents: TASK_PLAN — TASK-041 `DONE`; its one dependent, TASK-043, stays `TODO` (TASK-042 is not `DONE`); the totals TODO 8, READY 1, DONE 41. OPEN_ITEMS T-53 amended, the new T-54; CURRENT_HANDOFF last. No decision is recorded; the next free decision is D-135.
- Fast runs: two of the ten of CE-46 before the record; run 1 failed on F-6's two causes, fixed in one push and cured in run 2 (D-133); no CI run was repeated.
- CI on the pull request: the run on `5a319d0` cancelled by the push of F-7's fix; the run on `21007d7` red on F-8 only (failure 1 of 3, CE-42), fixed in one push of tests only; no rerun asked for.
- Not verified before the pull request's final head: the whole PostgreSQL suite in four parts, `test:race`, the build, the browser suite (`reporting/home.spec.ts`, `daily.spec.ts`, every spec where the Owner signs in) and the image checks; and the review's fixes (R-01, R-03, R-08), whose TypeScript checks and unit tests run first in the record's fast run.
- Next eligible Task: TASK-042, Audit log and security log views, effort High (agent `bk-executor-high`; review and verification `bk-reviewer-high`).
