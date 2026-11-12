# TASK-030 evidence — Cash session close and session pages

| | |
| --- | --- |
| Task | [TASK-030](../tasks/TASK-030-session-close.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-030-session-close`, from `ad16a628e4d5ddec976c734d4c56bd7efae40340` (origin/develop, the squash merge of TASK-029) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; no branch or pull request of TASK-030 and no open pull request; the executor state at `BUILD` for TASK-030 with this session's lock; TASK-029 `DONE`, no gate. PHP does not run on this machine and the `.bin` links of `node_modules` are absent, so `tsc` and ESLint do not run either: every check but the guard runs in CI (D-108, D-117, F-1) |
| Owner's directives in force | The host forwarded them at the start (11 November 2026): no repeat of valid work, fix only proven failures, every quality gate kept; a new CVE or advisory is a stop, never accepted here; no change of executor scripts, baselines, assertions or timeouts; the next free decision D-129 (none taken) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint`, PHP's checks | Local | — | — | `NOT_RUN`: neither PHP nor `tsc` and `eslint` are on the path of this machine (F-1); the fast runs run them |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `e2cf677` | 12 Nov, 00:00 | about 3 min | `PASS`, no candidate |
| `composer validate`; `composer lint`, its first part Pint | Fast run 1, 43063906690 on `e2cf677` | 12 Nov, 00:00 to 00:03 | 3 min 0 s for the run | `FAIL`: two files with their imports out of Pint's order (F-5); the later steps did not run |
| Guard | Local, on `539c46c` | 12 Nov, 00:05 to 00:10 | about 5 min | `PASS`, no candidate |
| `composer validate`, `lint` (Pint, the translation keys and the generated key types, the migration review, the parts of `test:postgres`, the secret scan, the audits) | Fast run 2, 43065539204 on `539c46c` | 12 Nov, 00:10 to 00:15 | about 5 min | `PASS` (F-5 fixed) |
| `composer analyse` | Same run | 00:15 | 17 s | `FAIL`: one finding of PHPStan's raw-SQL rule in a test helper (F-7); the later steps did not run |
| Guard | Local, on `cb9041c` | 12 Nov, 00:16 to 00:19 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse` (F-7 fixed), `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 3, 43067119540 on `cb9041c` | 12 Nov, 00:20 to 00:25 | about 6 min | `PASS` |
| Changed PostgreSQL and concurrency files: `CheckoutRaceTest` (12), `CloseRaceTest` (3), `CapabilityMatrixTest` (7), `RouteInventoryTest` (8), `SignInSessionTest` (10) | Same run | 00:25 to 00:29 | about 4 min | `PASS` |
| `CashCloseTablesTest` | Same run | 00:29 | 8 s | `FAIL`: 5 of 6; the refusal of a CLOSED session set OPEN again came with SQLSTATE 23001, PostgreSQL's `restrict_violation` for ON UPDATE RESTRICT, where the test expected 23503 (F-9); the files after it did not run |
| Guard | Local, on `b28cfb6` | 12 Nov, 00:30 to 00:33 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 4, 43069406323 on `b28cfb6` | 12 Nov, 00:36 to 00:39 | about 2.5 min | `PASS` |
| The ten changed PostgreSQL and concurrency files: `CheckoutRaceTest`, `CloseRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `SignInSessionTest`, `CashCloseTablesTest` (F-9 fixed), `CashTablesTest`, `CloseCashSessionTest`, `SessionPagesTest`, `CheckoutSaleTest` | Same run | 00:39 to 00:44 | 4 min 43 s | `PASS`, every file; the run 10 min in all |
| The review, round 1 (section 9.1) | On `b28cfb6`, tree `3ba0801` | 12 Nov, 00:45 to 01:11 | 26 min | `FAIL`, five MUST-FIX; fixed in checkpoint 5 |
| Guard | Local, on `c078966` (checkpoint 5, the review's fixes) | 12 Nov, 14:30 to 14:35 | about 5 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` (the unit tests of `sessionsListFrom`, `closedMeanwhile`, the page size and the tone among them) | Fast run 5, 43176521055 on `c078966` | 12 Nov, 14:39 to 14:43 | about 3.5 min | `PASS` |
| The ten changed PostgreSQL and concurrency files, with the review's regression tests of `CashCloseTablesTest`, `CloseCashSessionTest` and `SessionPagesTest` | Same run | 14:43 to 14:50 | 6 min 46 s | `PASS`, every file; the run 14 min in all |
| The whole of `test:postgres` and `test:race`, the browser suite with `tests/Browser/cash/close.spec.ts` and `sessions.spec.ts`, the build, the image checks | CI on the pull request's final head (CE-44, D-117) | — | — | `NOT_RUN` until then |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. `cash_session_closes` (class F): one close per session, its key the session's, the session CLOSED through the composite key to `cash_sessions (id, status)` ON UPDATE RESTRICT, the kind OWN or OWNER_ASSISTED with the reason the second needs, the actor and the name frozen, the cutoff, the physical and expected cash and the variance held to be their difference, the command unique; `cash_session_reports` (class F): the CashSessionReportSnapshot of BR-CASH-20, tied by composite keys to its session's number, opening cash, opened time and responsible name and to the close as recorded (G-17), the shop identity by reference to its revision and logo, the template version, the register frozen, the completed Sales, CASH and BANK_TRANSFER apart, the drawer-funded refunds and their total, expected cash and the variance held to their arithmetic (G-05), the source keys; the deferred keys close → report and `cash_sessions.close_presence` → `cash_session_closes` (EXC-44); the indexes of SCHEMA section 11, that of `cash_sessions.close_presence` among them (sections 3.6 and 11; review R-02); the grants of section 10 and the privilege matrix | `database/migrations/2026_11_11_000001_create_cash_close_tables.php`; `database/privileges.php` |
| 2. RC-03 `cash_session.close`: the identity; the actor share-locked (2.1); the BusinessProfile pointer share-locked (2.4); the session FOR UPDATE (4), found only when the actor is its responsible user, else NOT_FOUND; under that lock the session OPEN (`SESSION_NOT_OPEN`), the activity version the one reviewed (`RECOUNT_REQUIRED`), then the user's `sale.checkout` receipts read, any PENDING or HELD refusing `RECOUNT_REQUIRED` with its own message (G-27, RACE-35); expected cash from the session's facts under the lock — the opening cash plus the applied CASH that the Sales module's query gives (STK-10), minus the drawer-funded refunds, none before TASK-034 — and the variance; at step 8 the session CLOSED, the close, the report, the audit event `CASH_SESSION_CLOSE` and the result, all or nothing; no CLOSING state; the result `cash_session_close` with the number and RP-206 as its location | `App\Actions\Cash\CloseCashSession`; `App\Domain\Cash\Sessions` (`lockForClose`, `close`), `CashCount`, `SessionSales`, `SessionReports`, `ClosingSession`, `Registers::find`; `App\Domain\Sales\Sales::ofSession`; `App\Domain\Commands\RequestReceipts::unresolvedOf`; `App\Http\Requests\Cash\CloseCashSessionRequest`; `App\Http\Controllers\Cash\CloseCashSessionController`; `App\Actions\Commands\CommandActions` |
| 3. RP-105 (KS-07): the session's header and the close preview as a deferred prop — the activity version, the running figures with expected cash, the drawer-funded refunds' total, the unresolved checkouts — "Lanjut hitung" disabled with its reason and "Cek permintaan yang belum pasti" while a checkout is unresolved in this browser or on the server; the count "Uang fisik di laci" with Rp0 valid and a blank amount refused; "Tinjau" (Ctrl+Enter, never Enter); the review with physical cash, expected cash and the variance in words; "Tutup sesi"; `RECOUNT_REQUIRED` back to the preview, reloaded, with UXS-19 or its variant; UXS-30 for a session closed meanwhile; the uncertain and held panels; the report on success; "Batal" and "Kembali ke Kasir" with the unsaved-changes guard. RP-205: the keyset list in the viewer's scope, newest first, with "Status", "Tanggal" and the Owner's "Laci kas" and "Penanggung jawab", the paging both ways. RP-206: an OPEN session's running figures with "Tutup sesi kas" for its responsible user, a CLOSED session's immutable report | `resources/js/pages/cash/CloseSession.tsx`, `Sessions.tsx`, `Session.tsx`; `resources/js/lib/cash/sessions.ts`, `Parts.tsx`; `App\Http\Controllers\Cash\CloseSessionController`, `SessionsController`; `App\Http\Requests\Cash\SessionsPageRequest`; `App\Queries\Cash\SessionRecords` |
| 4. "Tutup sesi kas" in the till's header (UXI-077) and on UXP-206 (UXI-139), disabled with its reason while the user's close request is unresolved; the link of UXP-604 to the account's OPEN session (UXI-340); "Sesi kas" in the navigation of a cashier and the combined account (UXI-004) and the Owner's Laporan (UXI-019); UXI-056 "Lihat hasil" for a close | `resources/js/pages/sales/Till.tsx`; `resources/js/pages/access/User.tsx` and `App\Http\Controllers\Access\UsersController`; `resources/js/lib/navigation.ts`; `resources/js/pages/commands/Requests.tsx` |
| Copy | `lang/id/cash.php` (COPY_DECK sections 7.5, 7.10, 7.11 and 15), `lang/id/documents.php` (section 17.2), `lang/id/ux.php` (`ux.disabled.unresolved_checkouts`), `lang/id/access.php` (UXI-340), `resources/js/types/translation-keys.ts` |
| Routes | RP-105 `GET /till/close-session` and RC-03 `POST /commands/cash_session.close` (CAP-21); RP-205 `GET /cash-sessions` and RP-206 `GET /cash-sessions/{session}` (CAP-21 own, CAP-22) in `routes/web.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| The controls UXI-143, UXI-144, UXI-145 and UXI-147 are listed in section 4.2, but their sections are the Owner's review notes (UXI-144 to UXI-147, whose command RC-43 TRACEABILITY gives TASK-032) and the drawer-funded refunds beside the variance (UXI-143, shown only when the session has some). Scope item 3 gives the Owner's sections of RP-206 to TASK-032, and section 4.3 the drawer-funded refunds to TASK-034. TASK-032's contract creates `cash_session_review_notes` (its scope item 1) and adds the review notes (its scope item 5), which holds for UXI-144, UXI-145 and UXI-147. TASK-034's contract names DC-02 and RP-206 for the Owner, so UXI-143 for the other roles and UXI-101's list of the refunds in the close preview, with the reminder of D-068, have no owning contract yet (review R-09). Here the preview and the report show the refunds' total, Rp0 until TASK-034 pays one | The contract's scope prevails over its control list (EXC-52); the hand-over is OPEN_ITEMS T-45, for the next change control of TRACEABILITY section 9.2 and of the contracts of TASK-032 and TASK-034 |
| RP-206 has no `from_page` in ROUTE_CONTRACTS, so "Kembali ke …" (UXI-148) leads to UXP-205, its default and the destination after the close's success; RP-207's opener is TASK-032's. Opened from UXP-205, the way back is the list's own address with its filters and page, read from the opener as UXP-302's and RP-402's ways back read theirs (review R-03) | NC-01, NC-10; ROUTE_CONTRACTS RP-206; NAVIGATION_CONTRACTS UXP-205 |
| The close preview reads the activity version before the Sales module's totals: a Sale committed between the two reads is counted in the figures but not in the version, so the close is refused `RECOUNT_REQUIRED` rather than recorded against figures the user did not review | BR-CASH-13, BR-CASH-14 |
| RC-03's object scope is applied in the session's lock: another user's session, the Owner's own close of a cashier's included, is never locked and answers NOT_FOUND like a missing one; the Owner's close of another user's session is RC-04 of TASK-032 | SEC-AZ-03, TR-ERR-03, G-19 |
| The report's cutoff is the activity version that the close counted against; DC-02's "Dihitung sampai" shows the time of the close, at which that version was read under the lock | BR-CASH-13 |
| The report's source keys are `saleIds`, `paymentIds` and `drawerRefundIds`; SCHEMA names no keys of `source_ids` | SCHEMA section 5.10 |
| RP-205's cursor is the list's own, base64url of the opening time and the key, read again under the viewer's scope and refused `VALIDATION_FAILED` when it is not an instant of the calendar. The page's size `perPage` is read back among the filters, so that paging keeps a size that the address names, as UXP-401's does | TR-PAGE-01 to TR-PAGE-03; NC-10 |
| The preview is an Inertia deferred prop (`Inertia::defer`, present in the pinned inertia-laravel), reloaded by name after `RECOUNT_REQUIRED` with `router.reload({ only: ['preview'] })`. "Lanjut hitung" waits for the reload, and a failed reload shows UXS-22 in place with "Coba lagi" (review R-06); a failed first load is the shell's, which shows UXS-22 with "Coba lagi" or UXS-20, while the count stays closed. The page's own read of its preview after the session closed answers a null preview, so that the page shows UXS-30 rather than being led away by RP-105's redirect (F-10) | ROUTE_CONTRACTS RP-105; NAVIGATION_CONTRACTS UXP-105; UI_STATES UXS-22; EXC-03 |
| The count is kept within its step and its review only: back at the preview, by "Kembali ke pratinjau" or by `RECOUNT_REQUIRED`, it is discarded (review R-01) | NAVIGATION_CONTRACTS UXP-105 ("None across steps except the physical cash amount while the review dialog is open"); BR-CASH-14 |
| A variance, short or over, has the warning tone and an even one the success tone, as the approved KS-07 preview gives them; danger is for errors (review R-08) | `previews/assets/bk-app.js`; DESIGN_SYSTEM section 3.2 |
| A session CLOSED now needs its close, so the tests of earlier Tasks that wrote CLOSED directly close through RC-03's Action: `TokoContoh::closeSession`, `SessionCloseScenario`, `CashTablesTest`. `CheckoutSaleTest` sends its EPOCH_STALE probe as Dewi and settles the Sale's held repeats by a status check before the close, since G-27 refuses a close while the responsible user holds an unresolved checkout | EXC-44, EXC-45; G-27, D-089 |
| The browser suite's cash sessions come from the fixture `session` (RC-03's, RC-02's and RC-01's Actions): `cash.spec.ts` closes the Owner's session first, so it runs on any database (TASK-027 F-3); the cashier opens and closes her own sessions in `close.spec.ts`, so her home stays Buka sesi kas (TASK-029 R-07) | EXC-45; `tests/Browser/support/access.ts` |
| The Cash module never reads the Sales module (STK-10): the close's Action and the controllers of RP-105 and RP-206, which orchestrate as the Action does, read the Sales module's query `Sales::ofSession` and pass its totals to `App\Domain\Cash\SessionSales`; `SessionRecords` reads only the Cash tables. No module dependency is added to STACK_AND_MODULES section 2.1 | STK-10, STK-13 |
| UXP-604's controller reads the account's OPEN session through `App\Queries\Cash\OpenSessions`, as UXP-602's has since TASK-027, whose review left open whether section 2.1 records the Access controllers' reads of the Cash module (TASK-027 R-10, for the Owner). This Task's review raised the same question of the controllers of RP-105 and RP-206 (R-11); both are OPEN_ITEMS T-46 | STK-13, ARC-01; TASK-027 evidence |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | PHP does not run on this machine and `node_modules` has no `.bin` links, so `tsc` and ESLint do not run locally either; every static check is met first in CI | As recorded since TASK-011; the fast runs cover them |
| F-2 | Self-review: the report's source keys `sales` and `payments`, quoted in `SessionReports`, would have made it a file naming the Sales module's tables for TASK-028's one-writer scans | Fixed before the first push: the keys are `saleIds`, `paymentIds` and `drawerRefundIds` |
| F-3 | TASK-027 finding F-3: `tests/Browser/cash/cash.spec.ts` ran only on a fresh database, since the Owner's session could not be closed | Fixed: the test closes the Owner's OPEN session first through the fixture `session` |
| F-4 | TASK-029 review R-07: no L5 run as the cashier | Fixed: `close.spec.ts` signs in the cashier at every width, opens her session (KS-03 `opened`), sells once in cash at the first width (KS-03 to KS-05) and closes it with KS-07 |
| F-5 | Fast run 1, Pint: in `CloseCashSession` and `SessionsController` the import of `Sessions` stood after `SessionSales`, whereas Pint's `ordered_imports` compares without regard to letter case, which puts the shorter name first | Fixed in checkpoint 2: the two imports swapped; nothing else changed |
| F-6 | Self-review: RP-105's preview, reloaded after `RECOUNT_REQUIRED`, is the user's OPEN session at that moment; had this session closed and another opened elsewhere in between, the page would have shown the other session's figures under this one's header, and "Tutup sesi" would have named the other session | Fixed in checkpoint 3: a preview of another session is UXS-30, the session closed meanwhile, as a null preview is |
| F-7 | Fast run 2, PHPStan's rule `bantukasir.rawSql` (TR-VAL-07): a helper of `CloseCashSessionTest` built its SQL with `sprintf` from a table's and a column's name | Fixed in checkpoint 3: one constant query reads the close's and the report's instants |
| F-8 | EXC-60 asks for the evidence file in the Task's first commit; this file first entered the branch in checkpoint 3, with TASK-029's record of its merge | Recorded; nothing of the record was lost, the checks of checkpoints 1 and 2 are entered above as they ran |
| F-9 | Fast run 3, `CashCloseTablesTest`: the key that holds the session CLOSED refused the update to OPEN, as intended, with SQLSTATE 23001 (`restrict_violation`, the code of ON UPDATE RESTRICT) rather than the 23503 the test expected | Fixed in checkpoint 4, in the test's expected code only; the refusal and its constraint name are asserted as before |
| F-10 | Found while fixing review R-05: RP-105 answered the page's own reload of its preview with its redirect to the till once the user had no OPEN session, so a session closed elsewhere between the refusal `RECOUNT_REQUIRED` and the reload led the page away to Buka sesi kas without UXS-30; the null preview that the page shows as UXS-30 came only in a narrower race | Fixed in checkpoint 5: a partial reload of `cash/CloseSession` without an OPEN session answers `preview: null`, the header never read; a full request still redirects. `SessionPagesTest` "the preview read again is null once the session closed…" |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L1 the cash arithmetic: AC-030-01's example, over, even, a refund, the range of a stored amount, the running figures | `tests/Unit/Cash/CashCountTest.php` |
| L1 the pages' helpers: the variance with its sign and tone, the list's address with its filters, page size and cursor, UXP-206's way back to it, a session closed meanwhile, the keys of a choice, the shells, RC-03's family | `tests/Unit/Cash/sessions.test.ts` |
| L2 the two tables: the constraint family — every check and key refusing its violation, the composite keys of G-17; the deferred family — a close without its report and a CLOSED session without its close failing at commit and leaving nothing; the privilege family, a CLOSED session never OPEN again; the retention family; the indexes, that of `cash_sessions.close_presence` among them; the keys as declared | `tests/Feature/Cash/CashCloseTablesTest.php`; `CashTablesTest` (the presence key) |
| L2 failure injection after each of the five write steps, the session OPEN and nothing written | `tests/Feature/Cash/CloseCashSessionTest.php` |
| L3 RACE-08 in both orders with the report, RACE-09's own part with RACE-01 and RACE-02, RACE-35 with the settlement against a close | `tests/Concurrency/CloseRaceTest.php`; `CheckoutRaceTest` (RACE-08, the Sale's side, through RC-03's Action) |
| L4 RC-03: AC-030-01's close and its report agreeing with the facts, never changed; validation; scope and states, another user's session NOT_FOUND for the Owner, a cashier and the combined account; G-27 with PENDING, HELD and earlier-epoch HELD receipts settled by the user and by the Owner; repeats; two sessions on one date and one across midnight; the roles | `CloseCashSessionTest` |
| L4 RP-105, RP-205, RP-206 and UXP-604: per role with exactly their props, the deferred preview's own channel for the cashier, the combined account and the Owner (`assertDeferredChannel`, `Allowlists::DEFERRED`), the preview read again null once the session closed and naming the next one once it opened, the filters, page size and pages, NOT_FOUND outside the scope, each page only its parameters | `tests/Feature/Cash/SessionPagesTest.php`; `CapabilityMatrixTest`, `RouteInventoryTest`, `tests/Harness/Channels/Allowlists.php`, `ChannelInspector.php` |
| L5 KS-07 and the critical path of the close (EXC-34): the Owner's close after a Sale in cash and one by bank transfer, with the unsaved-changes guard and the count discarded back at the preview; the cashier's at every width with `default`, `count` and `review`, and at the first width `recount`, with the count discarded and a failed reload of the preview, and `unresolved-checkout`; a close whose answer is lost (UXS-14) or held (UXS-15), checked to its report, and a session closed meanwhile (UXS-30) | `tests/Browser/cash/close.spec.ts` |
| L5 UXP-205, UXP-206 and UXP-604: the cashier's own sessions at every width, a filter, pages both ways, a session and back to the same filter and page, her OPEN session with "Tutup sesi kas"; the Owner's list at every width with his filters, UXS-03 and "Hapus filter", her OPEN session without the close, and his page of her account leading to it | `tests/Browser/cash/sessions.spec.ts` |
| Fixtures through the Actions (EXC-45) | `TokoContoh::closeSession`; `FixturesController::session`; `SessionCloseScenario` |

## 6. Acceptance criteria

The PostgreSQL files that this branch changes run in its fast runs; the whole suites and the browser suite run in CI on the pull request's final head (CE-44); until they have run there, each criterion that rests on them is met by its test as written, and its result is that run's.

| Criterion | Evidence | Result |
| --- | --- | --- |
| AC-030-01 Opening Rp20.000, CASH Rp100.000, BANK_TRANSFER Rp50.000, Rp118.000 counted: expected Rp120.000, variance −Rp2.000; per-method totals apart; opening cash and variance never sales | `CloseCashSessionTest` "a cashier closes her session…" (the close, the report, the audit, G-17 against the facts); `CashCountTest`; `close.spec.ts` "the Owner closes his session…" | L1 and L4 in the fast runs; L5 on the final head |
| AC-030-02 RACE-08 in both orders; no successful Sale missing from its report; no lock while cash is counted | `CloseRaceTest` RACE-08; `CheckoutRaceTest` RACE-08 through RC-03's Action; the preview a read without a lock, the count a page step (`CloseSessionController`, `CloseSession.tsx`) | L3 in the fast runs |
| AC-030-03 G-27 and RACE-35: refused while a PENDING or HELD checkout receipt exists, an earlier-epoch HELD one included; the close succeeds after the status check of the user or of the Owner; the server's refusal when the client's list is stale | `CloseCashSessionTest` "an unresolved checkout refuses the close…"; `CloseRaceTest` RACE-35; `close.spec.ts` (`unresolved-checkout`, the Owner's settlement); `SessionPagesTest` (the preview's count) | L3 and L4 in the fast runs; L5 on the final head |
| AC-030-04 A repeated or lost close, same or another key: one CLOSED session, one snapshot, one result; another count never overwrites; a failure at any write keeps the session OPEN; the result recoverable | `CloseCashSessionTest` "a failure after every write step…", "another user's session…"; `CloseRaceTest` RACE-09; `CashCloseTablesTest` (one close per session and per command) | L2 to L4 in the fast runs |
| AC-030-05 Two sessions on one business date, two reports; a session across midnight one document | `CloseCashSessionTest` "two sessions on one business date…" | L4 in the fast runs |
| AC-030-06 The snapshot agrees with its facts (G-17) and never changes; no reopening | `CloseCashSessionTest` (G-17, a later session leaving the report as frozen); `CashCloseTablesTest` (the composite keys, class F, the CLOSED status refused back to OPEN, the retention family) | L2 and L4 in the fast runs |
| AC-030-07 KS-07 with its states, the disabled "Lanjut hitung" while a checkout is unresolved | `close.spec.ts`: `default`, `count` and `review` at every width of KEY_SCREENS section 4 and 1440 px, `recount` and `unresolved-checkout` at the first, with axe, targets, contrast and sideways scrolling; `SessionPagesTest` (the preview) | L5 on the final head |

## 7. Database (EXC-41 to EXC-47)

| Item | Evidence |
| --- | --- |
| Forward and additive (EXC-41) | One new migration, `2026_11_11_000001_create_cash_close_tables`, of class additive: two new tables, two keys and the index of `cash_sessions.close_presence` (SCHEMA sections 3.6 and 11; review R-02); no row written, changed or removed; the migration review passes it |
| Raw SQL proven on PostgreSQL (EXC-42) | The checks, the composite keys, the two deferred keys and the grants as explicit SQL, each proven by `CashCloseTablesTest` and `CashTablesTest` on PostgreSQL 18.6 |
| Grants with the tables (EXC-43) | Runtime `SELECT, INSERT`, backup `SELECT` on both tables; `database/privileges.php` (class F) and `PrivilegeMatrixTest`; `PrivilegeCheck` empty in `CashCloseTablesTest` |
| The key of an earlier Task (EXC-44) | `cash_sessions.close_presence` → `cash_session_closes`, deferred, which TASK-027 left: `CashTablesTest` proves a CLOSED session without its close refused at commit |
| Fixtures through the Actions (EXC-45) | Section 5 |
| REL-20 metadata (EXC-46) | Class, expected duration, lock impact and verification in the migration's header; the rehearsal on the provisional benchmark dataset waits for TASK-043 (REL-21) |
| Backup impact (EXC-47) | Both tables are class F, so the manifest's counts include them through `database/privileges.php` (`BackupRunner::COUNTED_CLASSES`); no stored file and no new kind of file |

## 8. Definition of Done

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS, subject to CI on the final head | Section 6; BR-CASH-12 to BR-CASH-21 in L1 to L4, which passed in the fast runs; AC-030-07's L5 `NOT_RUN` until the final head's CI |
| UI and UX | PASS, subject to CI on the final head | KS-07, UXP-205 and UXP-206 built from the approved P7 artifacts with the components of the catalogue; the copy verbatim from COPY_DECK; the widths, axe and the targets in L5 (`close.spec.ts`, `sessions.spec.ts`), `NOT_RUN` until the final head's CI; the controls of section 3's first reading are TASK-032's and TASK-034's (OPEN_ITEMS T-45) |
| Backend | PASS | FormRequest allowlists (`CloseCashSessionRequest`, `SessionsPageRequest`, `input.allow`), the object scope in the session's lock and in every read, the in-transaction guards, the props allowlisted per channel, L4 |
| Database | PASS | Section 7: the constraint, deferred, privilege and retention families of both tables |
| Security | PASS | RC-03 and RP-105 under CAP-21, RP-205 and RP-206 under CAP-21 own and CAP-22, NOT_FOUND outside the scope, Operations refused at the gate and by the guard (L4); no secret; the guard |
| Data integrity | PASS | The close, the report, the audit event and the result in one transaction; one close per session and per command; class F facts; RACE-08, RACE-09, RACE-35 and the failure injection |
| Performance | PASS | The close reads one session's Sales under its lock; RP-205 keyset-paged with its indexes; the budgets at TASK-048 |
| Operations | PASS | Section 7, backup impact; no runbook or status change |
| Verification | PASS, subject to CI on the final head | Fast run 4 green on the reviewed head and fast run 5 on the review's fixes (section 1); the self-review; the review of class A, round 1 `FAIL` with five MUST-FIX, all fixed, and the targeted verification `PASS` (section 9); the whole suites, the browser suite, the build and the image checks on the final head's CI |

## 9. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review: F-2 and F-6 (section 4), and the readings of section 3.

### 9.1 Round 1, the full review

| | |
| --- | --- |
| Reviewer | `bk-reviewer-max`, isolated and read-only (Read, Grep, Glob), mode `review`, class A |
| Package | `.git/bantukasir-executor/review/TASK-030/r1`: base `ad16a62`, snapshot tree `3ba0801`, HEAD `b28cfb6`, whose fast run 4 was green; written 12 November 2026, 00:45 |
| Duration | 26 min; `review-pack.sh --check` then printed `UNCHANGED` |
| Verdict | FAIL: five MUST-FIX (R-01 to R-05), five SHOULD-FIX (R-06 to R-10) and five NOTEs (R-11 to R-15), all within the Task's scope but R-11 and R-13; no test run asked for |

The adversarial review of the close transaction (contract section 8), as the reviewer gave it: the lock order — identity, epoch, the actor `FOR SHARE` (2.1), the BusinessProfile pointer share-locked (2.4), the session `FOR UPDATE` (4) only when the actor is its responsible user — holds, another user's session never locked and NOT_FOUND; under the lock `SESSION_NOT_OPEN`, then the activity version, then G-27, before any write, so that in RACE-08 with the Sale first the close is refused whatever the checkout's receipt; G-27 and RACE-35 hold, a receipt written before the close's read refusing it, the safe direction, and the settlement by the user and the Owner, the earlier-epoch HELD case included, proven at L3 and L4; G-05 holds, expected cash from `Sales::ofSession` read after the lock, BANK_TRANSFER apart, backed by the checks; the five write steps are all or nothing, with the deferred keys and ON UPDATE RESTRICT (23001) proven and no CLOSING state; RACE-01, RACE-02 and RACE-09's own part hold; CAP-21 at the gate and in the transaction, no recent authentication; the names, the revision and the register frozen as they should be; nothing locked while the cash is counted. The one way found for a wrong count to commit was on the client, R-01.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | The count stayed after `RECOUNT_REQUIRED` and after "Kembali ke pratinjau", so that "Tinjau" and "Tutup sesi" could commit the old count against the new version, a permanent variance; NAVIGATION_CONTRACTS UXP-105 keeps the amount only while the review dialog is open | Fixed: the count is discarded at every return to the preview (`CloseSession.tsx`); the `recount` case of `close.spec.ts` asserts the field empty and counts again, and the Owner's test asserts that "Kembali ke pratinjau" discards it |
| R-02 | MUST-FIX | No index on `cash_sessions.close_presence`, a foreign-key column since this Task's key (SCHEMA sections 3.6 and 11), as every earlier presence key has | Fixed: `cash_sessions_close_presence_index` in this Task's migration, with its lock impact in the header; asserted in `CashCloseTablesTest` |
| R-03 | MUST-FIX | UXI-148 led to `/cash-sessions` alone, losing the list's filters and page (NAVIGATION_CONTRACTS UXP-205) | Fixed: `sessionsListFrom()` of `resources/js/lib/cash/sessions.ts` reads the opener, UXP-205 with its query, else the whole list; its unit test; the round trip in `sessions.spec.ts` |
| R-04 | MUST-FIX | No L5 of UXP-205, of UXP-206 OPEN with and without UXI-139, or of UXP-604's section | Fixed: `tests/Browser/cash/sessions.spec.ts`; the page's size is kept in the list's address so that its pages are tested with a size of one (section 3) |
| R-05 | MUST-FIX | No L5 of the close page's recovery — a lost answer (UXS-14), a held close (UXS-15), a session closed meanwhile (UXS-30) — and no L4 of F-6 | Fixed: the third test of `close.spec.ts`; in `SessionPagesTest` the preview read again null once the session closed and naming the next session once it opened; `closedMeanwhile()` and its unit test. The fix found F-10 (section 4), fixed with it |
| R-06 | SHOULD-FIX | A failed reload of the preview left the earlier figures with "Lanjut hitung" enabled; a failed first load had no message in the page | Fixed for the reload: "Lanjut hitung" waits for it, and a failure shows UXS-22 in place with "Coba lagi"; the `recount` case fails the reload once. The first load's failure is the shell's, which shows UXS-22 with "Coba lagi", or UXS-20, while the count stays closed (section 3) |
| R-07 | SHOULD-FIX | The deferred preview, its own channel, had no allowlist, and was inspected for the cashier only | Fixed: `Allowlists::DEFERRED` and `ChannelInspector::assertDeferredChannel`, for the cashier, the combined account and the Owner in `SessionPagesTest` |
| R-08 | SHOULD-FIX | A short variance had the danger tone; the approved KS-07 gives every variance other than zero the warning tone | Fixed: `varianceTone`, and its unit test |
| R-09 | SHOULD-FIX | The first reading of section 3 held for UXI-144, UXI-145 and UXI-147 but not for UXI-101 and only in part for UXI-143 | Fixed: the reading corrected; the hand-over is OPEN_ITEMS T-45, for the next change control of TRACEABILITY and of the contracts of TASK-032 and TASK-034 |
| R-10 | SHOULD-FIX | The unsaved-changes guard had no test | Fixed: the Owner's test in `close.spec.ts`: without a count "Batal" leaves at once; with one, "Batal" and "Kembali ke Kasir" ask first and the page stays |
| R-11 | NOTE | The controllers of RP-105 and RP-206 compose two modules' reads, against ARC-01's one Action or Query, and STACK_AND_MODULES section 2.1 records neither those reads nor UXP-604's of the Cash module; the class of TASK-027 R-10 | Recorded: OPEN_ITEMS T-46, for the planning agent or the Owner, with TASK-027 R-10 |
| R-12 | NOTE | Section 5 said `recount` and `unresolved-checkout` ran at every width, and the Definition of Done read PASS on CI not yet run | Fixed in sections 5, 6 and 8 |
| R-13 | NOTE | Until TASK-041 adds Laporan, the Owner has no navigation entry to UXP-205 | Recorded, for TASK-041 |
| R-14 | NOTE | No assertion of an earlier Task's tests or harness was weakened | None needed |
| R-15 | NOTE | The combined account was tried against a guessed session only | Fixed: Dewi against Rina's session on RP-206 (`SessionPagesTest`) and RC-03 (`CloseCashSessionTest`) |

### 9.2 Round 2, the targeted verification

| | |
| --- | --- |
| Reviewer | `bk-reviewer-xhigh`, isolated and read-only, mode `verify`: the MUST-FIX fixes of round 1 and their regression tests, the other fixes only for new defects |
| Package | `.git/bantukasir-executor/review/TASK-030/r2`: HEAD `c078966`, snapshot tree `c563e45`, previous tree `3ba0801` (round 1), with fast run 5 green on that head; written 12 November 2026, 14:51 |
| Duration | 10 min; `review-pack.sh --check` then printed `UNCHANGED` |
| Verdict | PASS: R-01 to R-05 FIXED, F-10 with R-05; no new MUST-FIX or SHOULD-FIX; two NOTEs; no test run asked for |

The verifier judged the browser tests by reading, since they run first on the final head: each waits on a condition before it acts, uses the copy of COPY_DECK, and would fail on the code before its fix.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-16 | NOTE | The Owner's test of `sessions.spec.ts` filters by Rina, whom the file's first test gave sessions: run alone on a fresh database it would fail; the suite's `people` project runs its files one at a time, in order, without retries, so the final head's run is not affected | Recorded; no change. The Task that next changes the file may give her a session inside that test |
| R-17 | NOTE | F-10's bypass of the redirect reads the partial-component header alone, which inertia-laravel honours without `X-Inertia`: a user's own hand-made request with that header and no OPEN session would get the HTML page with a null header, which the page cannot show; no data is exposed, and a browser never sends the header on a navigation | Recorded; no change. A later change of RP-105 may also require `X-Inertia` |

## 10. Record (CE-50)

- Guard: `guard.sh --preview` of the uncommitted record against origin/develop `PASS`, no candidate to judge; the guard `PASS` with no candidate on every checkpoint (section 1).
- Backup impact (EXC-47): both new tables are class F, so the manifest's counts include them through `database/privileges.php`; no stored file and no procedure; the index changes nothing of the backup (section 7).
- The previous merge, TASK-029's pull request #40, is recorded in [its evidence](TASK-029.md) section 13.
- TASK_PLAN: TASK-030 `DONE`, TASK-031 and TASK-032 `READY`, the totals TODO 17, READY 3, DONE 30; OPEN_ITEMS T-45 and T-46 added; CURRENT_HANDOFF last.
- After round 1 the run paused from 01:11 to 14:15 on 12 November at the service's rate limit, its lock and its phase `REVIEW` kept; it resumed where it stood, on the host's message, with nothing redone.
- For later Tasks: TASK-031 prints and exports the report (DC-02) from `cash_session_reports`; TASK-032 adds RC-04, the Owner-assisted close, and the review notes (OPEN_ITEMS T-45); TASK-034 pays the first drawer-funded refund, lists them in the preview and the report and fills the report's `drawerRefundIds` (T-45); TASK-041 adds UXP-205's entry under Laporan (R-13); the planning agent or the Owner, OPEN_ITEMS T-46 (R-11, with TASK-027 R-10).

## 11. Merge

Recorded by TASK-031 (EXECUTION_CONTEXT CE-50, CE-61). Pull request #41, its final head `6848b96199ef06857c5006617d0af81486744b3f`, passed CI in run 43180373533. The tick squash-merged it under D-105 as `973f190484488f21bac076080d52f547ae92dc02` on `develop` on 12 November 2026, after the automated review's window ended with the status DONE; the squash commit's tree, `27d10a2`, equals the head's, its parent is `ad16a62` and its author the Owner's identity. CI on `develop` at the merge, run 43185133824, passed, and the tick verified the merge. TASK-030 is `DONE`.
