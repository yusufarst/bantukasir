# TASK-038 evidence — Housekeeping and retention

| | |
| --- | --- |
| Task | [TASK-038](../tasks/TASK-038-housekeeping.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-038-housekeeping`, from `9bdca628892f7e970e930d0bf8cc55d1ce3b81be` (origin/develop, the squash merge of the `ops/` pull request #52, D-133 and D-134) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the one known stash; no branch or pull request of TASK-038 and no open pull request; the executor state at `BUILD` for TASK-038 with this session's lock, the merge of #52 verified; TASK-011, TASK-018 and TASK-019 `DONE`, no gate. PHP and the native `tsc` are not on this machine, so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx eslint`, PHP's checks | Local | — | — | `NOT_RUN`: PHP is not on this machine, and the change holds no TypeScript; the fast run runs them |
| Guard preview, then guard | Local: the working tree against `origin/develop`, then `4d818c6` (checkpoint 1: the command, the cleanups and the tests) | 01:34 to 01:36 | about 2 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files | Fast run 1, 44794418868 on `4d818c6` | 01:36:52 to 01:42:33 | 5 min 41 s for the job | `FAIL`: every step `PASS` but PHPStan, 3 errors, and `HousekeepingTest`, 9 of 10 (F-3); `RetentionInventoryTest` 4 of 4 `PASS` |
| Guard | Local, on `373878e` (checkpoint 2: F-3) | 01:46 | under 1 min | `PASS`, no candidate |
| The steps of fast run 1 | Fast run 2, 44796023234 on `373878e`, the reviewed head | 01:47:07 to 01:52:35 | 5 min 28 s for the job | `PASS`: every step; `HousekeepingTest` 10 of 10, `RetentionInventoryTest` 4 of 4; the retest of F-3, each failure cured |
| Review round 1 (section 10.1) | `bk-reviewer-high`, on the package of tree `0246c72` | 01:53 to 02:01 | about 8 min | `FAIL`: R-01 MUST-FIX, fixed with R-02, R-03, R-05 and R-08 in checkpoint 3 |
| Guard preview, then guard | Local: the working tree against `origin/develop`, then `85d5b52` (checkpoint 3: the review's fixes) | 02:04 to 02:06 | about 2 min | `PASS`, no candidate |
| The steps of fast run 1 | Fast run 3, 44799012415 on `85d5b52`, the review's fixes | 02:06:25 to 02:12:14 | 5 min 49 s for the job | `PASS`: every step; `HousekeepingTest` 12 of 12 with the regression tests of R-01 and R-02, `RetentionInventoryTest` 4 of 4; the one targeted retest of the review's fixes |
| Verification round 2 (section 10.2) | `bk-reviewer-high`, on the package of tree `78b35d5` | 02:07 to 02:09 | about 2 min | `PASS`: R-01 fixed, no MUST-FIX left |
| The whole PostgreSQL suite in four parts, `composer test:race`, the browser suite, `npm run build`, the image checks | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. SC-02 `housekeeping:run`: no option or argument, its bounds from `config/housekeeping.php` only; the database's marker equal to the configured environment before anything; its own no-overlap lock beside the scheduler's; one security log entry per run, actor the scheduled command `housekeeping`; counts only in its lines and the log | `App\Console\Commands\HousekeepingRun`; `config/housekeeping.php`; `App\Domain\Operations\Housekeeping\Housekeeping`, `HousekeepingSettings`, `HousekeepingSummary`; the schedule entry of TASK-012 (`App\Console\ProcessSchedule`), unchanged |
| 1a. Expired one-time links, as the broker judges expiry | `App\Domain\Access\OneTimeLinks::removeExpired` |
| 1b. Resolved request receipts (COMMITTED, REJECTED, NOT_COMMITTED) whose `outcome_at` is more than 30 days old | `App\Domain\Commands\RequestReceipts::removeResolved` |
| 1c. Security log entries older than 8,760 hours, within the policy of T-23 | `App\Domain\Audit\SecurityLog::removeExpired` |
| 1d. Stored files that no `stored_files` row names, after a grace period of at least one hour | `App\Domain\Media\MediaStore::removeUnreferenced` |
| 1e. Temporary CSV import files older than 24 hours, whatever their state | `App\Domain\Catalog\Import\CsvImportFiles::removeExpired` |
| 2. The static inventory: no product path deletes or rewrites a row of classes F, FS, M or S; no deletion, purge or archival function for business records or master data; no deleting route | `tests/Feature/Operations/Housekeeping/RetentionInventoryTest.php` |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| C-1 | **Each cleanup in its owning module** (STACK_AND_MODULES section 2, Operations "each calling the owning module's services"): Access, Commands, Audit, Media and Catalog each remove their own kind; Operations orders the run. The Operations row of section 2.1 now names the Catalog and Access services it calls (STK-13) |
| C-2 | **The ages that the decisions set are constants, not settings**: the link's 60 minutes (config `auth.passwords.users.expire`, the broker's own), 30 days, 8,760 hours and 24 hours. Only the batch size, the number of batches and the grace period (at least one hour, TR-TX-05) are settings, each refused outside its range before anything is removed |
| C-3 | **"Older than" is strict** in every cleanup, as the broker's `deleteExpired()` and the policy of T-23 are: a row or file exactly at its age stays until the next run |
| C-4 | **One instant of the application clock bounds the run** (STK-31), so the tests stand the clock at each boundary. For the security log, the database's policy bounds the same removal by its own `now()` (SCHEMA section 5.4): an entry younger by either clock stays |
| C-5 | **Batches and locks.** Each table batch is one short transaction, its rows chosen oldest first up to the batch size; the links and receipts with `FOR UPDATE SKIP LOCKED`, so the run never waits on, nor blocks, an issue, a consumption, a marking or a settlement; the security log without a row lock, which needs an `UPDATE` privilege that the runtime role does not hold, and which no other path takes. The file areas are walked once per run, up to batch size × batches |
| C-6 | **"Unreferenced" means that no `stored_files` row names the key** (SCHEMA sections 4 and 5.5): a file whose row exists is never removed, also the file of a replaced or removed photo or logo (D-042). Only a file at the root of the disk whose name is a storage key counts; anything else on the disk is left |
| C-7 | **The environment marker first**: a file is removed when the database names it nowhere, so the run refuses unless the database's marker equals the configured environment name (ENVIRONMENTS ENV-04), and the scheduler does not run it in maintenance mode (TASK-014 F-2), RB-01 step 3 |
| C-8 | **No audit event**: the run changes no business data; its record is its security log entry, as SC-01's run without a block (SECURITY_MODEL section 14; AUDIT01 "audit events are never removed") |
| C-9 | **KEEP01's other cleanups** — sign-in sessions and cache rows — are the framework's own expiry (SCHEMA section 4, class E; SEC-SESS-04), not SC-02's list in ENVIRONMENTS section 13, and are unchanged |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Carried for this Task: TASK-010 section 4 item 12 and TASK-018 F-4 — the files that a refused, rolled-back or repeated RC-25 or RC-32 leaves unreferenced, a photo and its thumbnail per RC-25; TASK-019 F-1 — the temporary CSV files that uncertain, held, refused or abandoned imports leave | Settled: `MediaStore::removeUnreferenced` removes a photo and its thumbnail alike once neither has a row, and `CsvImportFiles::removeExpired` every import file after 24 hours whatever its state; both tested at their boundaries |
| F-2 | Carried for this Task: TASK-006 F-8 — `occurred_at` is the application's clock and the policy compares it with the database's `now()`, so a skew shifts the retention | Settled: the removal is bounded by both clocks (C-4); `HousekeepingTest::test_the_database_keeps_a_younger_entry_whatever_the_application_clock_says` runs it two days ahead and two days behind |
| F-3 | Fast run 1: PHPStan — `array_diff()` given the `mixed` values of `pluck()` in `MediaStore::removeUnreferenced`, and a catch of `InvalidOptionException` read as dead in `HousekeepingTest`; and `HousekeepingTest::test_a_run_is_refused_before_anything_on_another_database_or_with_a_setting_out_of_range` erred in the harness, whose observer checks the database's marker against the configured name, which the test had set to `staging` and read before setting it back | Fixed in checkpoint 2: the referenced keys filtered to strings; the refusal caught as a `Throwable` and asserted an `InvalidOptionException`; the test sets the name back before it reads. Fast run 2 is their one retest (D-133) |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 each cleanup with the test clock at its boundaries: one second past, at, and before its age; the unresolved receipts at any age; a younger security log entry whatever the application's clock says; a referenced file, a name that is no key, a file below the root | `tests/Feature/Operations/Housekeeping/HousekeepingTest.php` |
| L2 the retention family: every table of class F, FS, M or S unchanged by a run with the clock three years ahead | `HousekeepingTest::test_a_run_changes_no_row_of_a_retained_class_even_with_the_clock_years_ahead` |
| L2 the standing obligations of a scheduled command (EXECUTION_CONTEXT section 10): no overlap, no option or argument, its security log entry, refusals before anything | `HousekeepingTest` |
| L4 the route inventory: no DELETE route, no route reaching the housekeeping, no operation or console command of deletion, purge or archival; the static inventory of every deletion in the code | `tests/Feature/Operations/Housekeeping/RetentionInventoryTest.php` |
| L1 the schedule: SC-02 exists and runs when due, hourly, without overlap | `tests/Unit/Console/ProcessScheduleTest.php` |

## 6. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-038-01 | `HousekeepingTest`: the link, receipt, security log, file and CSV tests and the retention family | `PASS` in fast run 3 on the final code (section 1); the whole suite on the pull request's final head |
| AC-038-02 | `HousekeepingTest::test_an_unreferenced_file_is_removed_after_its_grace_period_and_never_earlier_nor_a_referenced_one`, `test_referenced_files_never_use_up_the_limit_of_a_run` (R-01), `test_a_temporary_csv_file_is_removed_after_24_hours_whatever_its_state_and_nothing_else_there` | As AC-038-01 |
| AC-038-03 | `RetentionInventoryTest`; the retention family of `HousekeepingTest` | As AC-038-01 |
| AC-038-04 | `HousekeepingTest::test_the_command_never_overlaps_itself_and_takes_no_option_or_argument`; `ProcessScheduleTest` | As AC-038-01 |

## 7. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A |
| EXC-45 | The fixtures through the Actions and services: links by the broker, receipts by `RequestReceipts`, security log entries by `SecurityLog`, the referenced file by RC-32's route, the CSV files by `CsvImportFiles::store` |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged. A removed unreferenced file was never in a backup's file list, which follows `stored_files` |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `PASS` — the criteria of section 6 in fast run 3; the final head's suites before the merge |
| UI and UX | N/A — no page or control |
| Backend | `PASS` — a console command only, no route, FormRequest or DTO, so none of those apply; its bounds from configuration, refused out of range |
| Database | N/A — no migration |
| Security | `PASS` — no option or stored value reaches a process or a statement's text; the marker check before anything; the security log's database bound under both clocks; no secret; the guard's preview and every guard `PASS` |
| Data integrity | `PASS` — the retention family; no unresolved receipt, referenced file or younger entry removed; one short transaction per batch, locked rows skipped; a failed run keeps what it removed (R-02) |
| Performance | `PASS` for what applies — bounded batches and one walk of each file area per run; the budgets are measured at TASK-048, with the receipts' batch without an index on `outcome_at` (R-06) |
| Operations | `PASS` — no new table in the manifest's counts; OPERATIONS_RUNBOOK sections 3 and 9 (SC-02 hourly, never run by hand) unchanged; a failed run prints `HOUSEKEEPING_FAILED` with its class and records its security log entry, and the next hour's run takes what is left |
| Verification | `PASS` for what runs before the pull request: the self-review; the class-A review, round 1 `FAIL` with one MUST-FIX, fixed and verified in round 2 `PASS` (section 10); fast runs 2 and 3 green. The command contract and the image checks run on the pull request's final head before the merge (CE-41) |

## 9. The guard's candidates (CE-31; D-076, D-097, D-098)

| Candidates | Judgment |
| --- | --- |
| None | Every `guard.sh --preview` and `guard.sh` of this Task found no candidate (section 1) |

## 10. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review, on `373878e`, the head of fast run 2: each cleanup against its rule (SCHEMA sections 4, 5.1, 5.3 to 5.5; SEC-ACC-07, SEC-LOG-05; TR-TX-05; ARC-41, ARC-43; D-088), the strict bounds, the privileges each statement needs under the runtime role and row security, the locks against an issue, a consumption, a marking and a settlement, the order of RB-01 and the marker check, the obligations of EXECUTION_CONTEXT section 10 for a scheduled command, the module boundaries, and the findings carried from TASK-006, TASK-010, TASK-018 and TASK-019 (section 4).

### 10.1 Round 1, the full review

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `review`; package `review/TASK-038/r1` of the executor state, base `9bdca62`, tree `0246c72`, HEAD `373878e`; 21 November, 01:53 to 02:01; `review-pack.sh --check` `UNCHANGED` after it. Verdict `FAIL`: one MUST-FIX, one SHOULD-FIX and six NOTEs, decided from the code and the documents, with no test run asked for.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | `MediaStore::removeUnreferenced` stopped collecting at the run's limit counting old key-named files whether referenced or not, and only then read `stored_files`; the disk is listed in the order of names, so with more old referenced files than the limit (10,000 by default) an unreferenced file whose key sorts later would never be removed by any run (AC-038-02, TR-TX-05) | Fixed: one walk of the disk, the rows read per batch of old keys as it goes, and the limit counts removals only. Regression test `HousekeepingTest::test_referenced_files_never_use_up_the_limit_of_a_run`: a limit of 4, five old referenced logos (RC-32, D-042 keeps the replaced ones) and one unreferenced file whose key sorts last; removed in the first run, no referenced file removed |
| R-02 | SHOULD-FIX | The failure path — `HOUSEKEEPING_FAILED`, its security log entry, the lock freed — had no test, though the evidence claimed it (EXECUTION_CONTEXT section 10) | Fixed, within scope and cheap, bearing on the scheduled command's obligations: `HousekeepingTest::test_a_failed_run_keeps_what_it_removed_records_its_entry_and_frees_the_next_run` — the media disk made unavailable after the table batches; exit 1, `HOUSEKEEPING_FAILED`, the entry `REFUSED` of `housekeeping`, the earlier removal kept, the next run completed |
| R-03 | NOTE | The static inventory names a file's tables only where the code names them literally; some forms (`cleanDirectory`, `Prunable`, `detach`, `sync`) were not searched | Hardened: those forms added, with samples; the test's docblock says that the scan supplements the privilege family, which stays the enforcement for tables |
| R-04 | NOTE | The skipping of a locked link or receipt is reasoned, not proven by a lock held from another connection; the contract names no race | Recorded: no race is in the contract (section 5); TASK-043's harness or a later change may add one |
| R-05 | NOTE | `RequestReceipts::removeResolved` refused a surrounding transaction with `InvalidArgumentException`, its siblings with `LogicException` | Fixed: `LogicException` |
| R-06 | NOTE | The receipts' batch has no index on `outcome_at` | Recorded for TASK-048's budgets; the table holds about 30 days of receipts, and a migration is outside this Task |
| R-07 | NOTE | A failure that repeats in the media cleanup ends every run before the CSV cleanup | Recorded for TASK-039: the system status should show repeated `HOUSEKEEPING_FAILED` runs, which their security log entries record |
| R-08 | NOTE | The evidence's checks, criteria and Definition of Done still to be completed; STACK_AND_MODULES' Media row names the temporary CSV area, which the code and the Operations row place in Catalog | The record completes the evidence; the Media row is corrected |

One MUST-FIX was raised, so round 2 verifies its fix.

### 10.2 Round 2, the targeted verification of R-01

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `verify`; package `review/TASK-038/r2`, base `9bdca62`, tree `78b35d5`, previous package `r1` (tree `0246c72`), HEAD `85d5b52`; 21 November, 02:07 to 02:09; `review-pack.sh --check` `UNCHANGED` after it. Verdict `PASS`, no MUST-FIX left.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | As in section 10.1 | `FIXED`: one walk, the rows read per batch, the limit counting removals only; the regression test fails on round 1's code (its first four old key-named files are the referenced logos, so it removed none) and passes on the fix |
| R-02, R-05 | SHOULD-FIX, NOTE | As in section 10.1 | `FIXED` |
| R-09 | NOTE | The cut-off of `removeUnnamed` within a batch, when the removals so far are within one batch of the limit, runs in no test; the code reads correct | Recorded, no change |

No further round is due: the MUST-FIX is fixed, proven by its regression test and this targeted check (CE-40).

## 11. Record (CE-50)

- Guard: `guard.sh` `PASS` with no candidate on every pushed head; `guard.sh --preview` of the record's change before its commit (section 1).
- Backup impact (EXC-47): none; no new table or kind of stored file.
- The previous merge: the `ops/` pull request #52 of D-133 and D-134 (head `8311ad1`, CI 44756500932; squash `9bdca62` by the Owner, CI on `develop` 44785289365), which recovers TASK-037's pull request #51 (squash `8f768bb` by the Owner; CI on `develop` 44698518825 failed) — recorded in [TASK-037's evidence](TASK-037.md) section 13 and in CURRENT_HANDOFF.
- Documents: TASK_PLAN — TASK-038 `DONE`; its one dependent, TASK-043, stays `TODO` (TASK-041 is not `DONE`); the totals TODO 9, READY 3, DONE 38. STACK_AND_MODULES section 2's Media row and section 2.1's Operations row; CURRENT_HANDOFF last, with the Owner's resume of 21 November in its section 0 and D-133 and D-134 in its section 5. No decision is recorded; the next free decision is D-135.
- Fast runs: three of the ten of CE-46 before the pull request; run 1 failed on proven defects of the change, all fixed in one push and cured in run 2 (F-3, D-133); no CI run was repeated.
- Not verified before the pull request's final head: the whole PostgreSQL suite in four parts, `test:race`, the browser suite, the build and the image checks.
- Next eligible Task: TASK-039, System status and monitoring, effort High (agent `bk-executor-high`; review and verification `bk-reviewer-high`).

## 12. Merge

Recorded by TASK-039 (CE-50), from the executor state and GitHub.

| Item | Value |
| --- | --- |
| Pull request | #53, head `d70cd3116a274e2f6d6700ca902d53756f653745`; CI run 44800522582 passed on that head |
| Merge | Squash commit `ad9a5009715cd1251047c4ac3297e36013a78f48` on `develop`, by the tick under D-105; the automated review's status DONE |
| CI on `develop` | Run 44805266626 passed at the merge; verified by the tick on 21 November 2026 (`MERGE_VERIFIED pr=53`) |
| R-07 | Taken by TASK-039 and moved to OPEN_ITEMS T-51: the status's five items have no place for housekeeping without a change control of COPY_DECK |
