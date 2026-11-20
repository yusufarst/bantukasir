# TASK-039 evidence — System status and monitoring

| | |
| --- | --- |
| Task | [TASK-039](../tasks/TASK-039-system-status.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-039-system-status`, from `ad9a5009715cd1251047c4ac3297e36013a78f48` (origin/develop, the squash merge of TASK-038's pull request #53) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the one known stash; no branch or pull request of TASK-039 and no open pull request; the executor state at `BUILD` for TASK-039 with this session's lock, the merge of #53 verified; TASK-036 and TASK-037 `DONE`, no gate. PHP and the native `tsc` are not on this machine (`npm run typecheck` and `npx tsc` answer that `tsc` is not found), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, PHP's checks | Local | 03:47 | under 1 min | `NOT_RUN`: `tsc` is not installed natively and PHP is not on this machine; the fast run runs them |
| Guard | Local, on `ff7c5c3` (checkpoint 1: the status, RP-611, SC-04 and their tests) | 03:48 to 03:51 | about 3 min | `PASS` after judgment: 3 candidates, none a finding (section 9), accepted by their hash |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files | Fast run 1, 44815085808 on `ff7c5c3` | 03:51 to 04:03 | about 12 min for the job | `FAIL` (F-6): `npm run lint`, `typecheck` and `test:unit` `PASS`; the targeted files 4 of 5 `PASS` (`CapabilityMatrixTest`, `RouteInventoryTest`, `SettingsPagesTest`, `StatusPageTest`); `SystemStatusTest` could not load, which also failed `composer lint`'s listing of the PostgreSQL parts and one unit test of it; PHPStan 8 errors; 5 unit tests of `MonitorHeartbeatTest` erred |
| Guard | Local, on `bd70372` (checkpoint 2: F-6) | 04:08 | under 1 min | `PASS`, no candidate |
| The steps of fast run 1 | Fast run 2, 44817616207 on `bd70372`, the reviewed head | 04:08:23 to 04:16:48 | 8 min 25 s for the job | `PASS`: every step, the five targeted PostgreSQL files `SystemStatusTest` included; the retest of F-6, each failure cured |
| Review round 1 (section 10.1) | `bk-reviewer-high`, on the package `r1` of HEAD `bd70372` | 04:17 to 04:29 | about 12 min | `PASS`: no MUST-FIX; R-01 to R-05 fixed in the record's commit |
| Guard preview | Local: the working tree against `origin/develop`, the record with the review's fixes | 04:35 | under 1 min | `PASS`, no candidate after the samples of section 9 were replaced |
| The steps of fast run 1, for the review's test fixes (R-03 to R-05) | The fast run and the CI of the pull request's final head | — | — | `NOT_RUN` until then (CE-45) |
| The whole PostgreSQL suite in four parts, `composer test:race`, the browser suite, `npm run build`, the image checks | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The status query: the five items of OPERATIONS_RUNBOOK section 5 in one read, each `OK`, `FAILED` or `UNVERIFIED` against its threshold — 7 hours and the weekly re-verification within 8 days, 35 days, 20 % free, 26 hours, 80 % of the store's 10 GB — by the application's clock; every case of "Belum Terverifikasi"; whether an incident is open; the attention signal | `App\Queries\Operations\SystemStatus` (`read()`, `attention()`); the volume measured behind `App\Domain\Operations\VolumeSpace` by `App\Console\LocalVolumeSpace` |
| 2. RP-611 UXP-611 with UXI-374 to UXI-376 and the settings entry UXI-329; a failed read UXS-22; the attention signal for Beranda (UXI-064, shown by TASK-041) | `App\Http\Controllers\Operations\StatusController`, `routes/web.php`, `App\Http\Controllers\Access\SettingsController::ENTRIES`; `resources/js/pages/operations/Status.tsx`, `resources/js/lib/operations/status.ts`, `resources/js/pages/access/Settings.tsx`; `lang/id/config.php`; `SystemStatus::attention()` |
| 3. SC-04 `monitor:heartbeat`: the public `/up` at the configured address, the web check pinged only on 200, no address printed | `App\Console\Commands\MonitorHeartbeat`, `App\Domain\Operations\PublicHealth`, with the ping client of TASK-013 (`Heartbeat`); the schedule entry of TASK-012, now also in maintenance mode (`App\Console\ProcessSchedule`) |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| C-1 | **Five items, not four.** The contract names four items "and the presentation of the store's usage as TASK-002 decided"; COPY_DECK section 11.11 and OPERATIONS_RUNBOOK section 5 give the fifth, "Penyimpanan cadangan" (CC-14, the Owner's answer of 19 October 2026; COPY_DECK section 22.2; OPEN_ITEMS T-25) |
| C-2 | **The thresholds are applied, not written.** COPY_DECK has no copy for a threshold; each item's value is its evidence ("Berhasil :waktu", ":durasi yang lalu", "Gagal :waktu", "Tersisa :ukuran dari :total", "Terpakai :ukuran dari :total") and its state is the threshold's verdict, shown by the status's tone and, when not proven, by "Belum Terverifikasi" (UXS-43) with no passing look |
| C-3 | **The newest row decides, the failure first.** An item's newest row that failed is `FAILED` even if an earlier one passed, and even with no success ever (backup) — both raise the attention signal. A stale success is `UNVERIFIED` with its age only, never "Berhasil" |
| C-4 | **The weekly re-verification** (TASK-013 F-3): a run with `reverified_backup_id` passed it; a run with the weekly tier that failed `REMOTE_MISMATCH` failed it, the code that BackupRunner gives only to a weekly copy gone or changed in the store (a size it cannot confirm at upload is `STORE_REFUSED`). The backup is `OK` only when the newest such run of the last 8 days passed; "has not run for 8 days" is strict, so a re-verification exactly 8 days old no longer counts. A new installation is therefore "Belum Terverifikasi" until its second Sunday |
| C-5 | **The daily reconciliation is the run of scope ALL.** A run for one product (RB-04, `PRODUCT`) never makes the item pass, because it checked one product; a run of either scope that failed or found a mismatch since the newest daily run makes it `FAILED`. This is never more passing than reading the newest row alone |
| C-6 | **Evidence dated ahead of the clock.** A row more than five minutes after the application's clock proves nothing and is `UNVERIFIED`; within five minutes, the drift of two synchronized clocks, it counts with an age of zero |
| C-7 | **The age in minutes.** `:durasi` holds a duration of COPY_DECK section 1.3, whose units are minutes and seconds (CC-21); a larger unit needs its own keys through a change control (COPY_DECK section 22.2, OPEN_ITEMS T-25), which has not happened, so an age is written in minutes, "1.560 menit yang lalu" |
| C-8 | **Sizes in decimal units.** `:ukuran` and `:total` are written in GB from a gigabyte on and in MB below, with one decimal rounded half up and dropped when zero; the store's allowance of 10 GB is decimal, as the store counts it (BACKUP_RECOVERY section 6). The unit symbols are SI symbols, as "Rp" is in `resources/js/lib/format.ts`, not copy |
| C-9 | **The disk is that of the disk `media`**, the file volume of ENVIRONMENTS section 6, whose folder the image creates; a folder that does not exist, or a file system that answers nothing, is "the measurement could not be made" |
| C-10 | **A failed read.** A read that throws — a query, or the volume's measure — is `status: null` on the page and on "Muat ulang", shown as UXS-22 with "Coba lagi" and no item, its cause reported to the log only; a reload that fails in the browser (an HTTP exception or a network error) is the same. While "Muat ulang" reads, the items wait (UXS-01), so a failed reload never leaves the earlier items looking current |
| C-11 | **UXI-375** shows while an incident is open, under the reconciliation item, whatever its state (AC-039-02), and only for a holder of CAP-18; the Owner holds it with CAP-32, so the check is defensive. It is a button-shaped link, a 44 px target |
| C-12 | **SC-04's address** is `APP_URL`, the environment's public hostname, which resolves to Cloudflare; role web requires it to be HTTPS at start (`settings:check`). The command accepts any absolute HTTP or HTTPS address without user, password, query or fragment, so that the stand-in of the test is local; only exactly 200 is success, no redirect is followed (OutboundHttp) |
| C-13 | **SC-04 writes no audit event and no security log entry.** It reads and changes no data; SCHEMA section 3.4 lists `reconciliation` and `housekeeping` as the scheduled commands' actors and no heartbeat; an entry every 5 minutes would bury the security log that TASK-042 shows. Its record is the external monitor; its failures go to the container's log with their outcome only |
| C-14 | **SC-04 in maintenance mode** (OPEN_ITEMS T-39 (b), resolved): RH-01 answers in maintenance mode (bootstrap/app.php), so SC-04 alone of the scheduled commands runs in it, and a release's window raises no web alert while the application and its database answer; the other scheduled commands write data and still wait (OPERATIONS_RUNBOOK section 4) |
| C-15 | **The Operations module reads the Inventory module's open incidents** (`App\Queries\Inventory\Incidents::openCount`), recorded in STACK_AND_MODULES section 2.1 (STK-13) |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Carried for this Task: TASK-038 review R-07 — the status should show repeated `HOUSEKEEPING_FAILED` runs | Not built: UXP-611's five items are fixed by COPY_DECK section 11.11 and OPERATIONS_RUNBOOK section 5, with no item, key or threshold for housekeeping, and approved copy changes only by a change control approved by hash. Recorded as OPEN_ITEMS T-51 for the planning agent or the Owner |
| F-2 | Carried for this Task: TASK-037 — the system status reads the newest `restore_drills` row | Done (C-3, AC-039-04) |
| F-3 | Carried for this Task: TASK-013 F-3 — `reverified_backup_id` shows the Sunday re-verification | Done (C-4) |
| F-4 | Carried for this Task: OPEN_ITEMS T-39 (b) — the heartbeat in a maintenance window | Resolved (C-14) |
| F-5 | For later Tasks | TASK-041 — UXI-064 from `SystemStatus::attention()`, whose failed read throws for Beranda's own UXS-22; TASK-045 — each alert once on the target (contract section 12); TASK-047 or a change control the planning agent names — T-25's remainder: a unit above minutes, a size format, the contract's and RELEASE_PROCESS's five items (review R-02); the planning agent or the Owner — T-51 (F-1) |
| F-6 | Fast run 1: `SystemStatusTest` declared a private `status()`, which PHPUnit's `TestCase` holds as a final method, so the file could not load (and `composer lint`'s listing of the PostgreSQL parts with it); PHPStan — the query's SQL passed through a helper as a non-constant string (`bantukasir.rawSql`, TR-VAL-07), two needless `??` in `StatusPageTest`, `Log::shouldHaveReceived` and a catch it read as dead in `MonitorHeartbeatTest`; and `Log::spy()` needs Mockery, which the project does not install | Fixed in checkpoint 2: the helper renamed `read()`; each query a constant string at its call; the flag read by a helper; the log read through the framework's `MessageLogged` event, the refusal caught as a `Throwable` and asserted an `InvalidOptionException`. Fast run 2 is their one retest (D-133) |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 thresholds with the test clock: each item at its threshold and one second past it; every "Belum Terverifikasi" case; a failed newest row; the weekly re-verification; a run for one product; evidence ahead of the clock; the open incidents; the attention signal; the read writes nothing | `tests/Feature/Operations/Status/SystemStatusTest.php` |
| L4 RP-611 by role: the Owner with only the allowlisted props, never stored; every other role and the combined account refused; any parameter refused; the status reloaded by name; a failed read null, with its cause on neither answer; the incident flag; the settings entry | `tests/Feature/Operations/Status/StatusPageTest.php`; `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`; `tests/Feature/Configuration/SettingsPagesTest.php`; `tests/Harness/Channels/Allowlists.php` |
| L5 RP-611 states: the entry on "Pengaturan", UXS-43, "Muat ulang" with what passed and what failed, UXS-22 and "Coba lagi", UXI-375 to the incidents, UXI-376, axe, contrast, 44 px targets and 320 px | `tests/Browser/operations/status.spec.ts` with the fixture `status` of `tests/Harness/Browser/FixturesController.php`; `tests/Browser/configuration/profile.spec.ts` (four entries) |
| L2 SC-04 against a local stand-in of the public address: 200 pings after the public check; any other answer or none, no ping; a refused ping; no usable address; no address in a line or the log; no option or argument | `tests/Unit/Operations/MonitorHeartbeatTest.php` |
| L1 the schedule: SC-04 exists and runs when due, every 5 minutes, without overlap, and alone in maintenance mode | `tests/Unit/Console/ProcessScheduleTest.php` |
| L1 the page's words: the items' order, sizes, values and ages | `tests/Unit/Operations/status.test.ts` |
| L1 the measure of the file volume: whole bytes within the total, a missing folder no measurement, the application's binding (review R-04) | `tests/Unit/Console/LocalVolumeSpaceTest.php` |
| L5 the 200 % zoom and the keyboard path of "Muat ulang" and "Kembali ke Pengaturan" (review R-03) | `tests/Browser/operations/status.spec.ts`, its second test |
| The backup and reconciliation checks pinged on success by their commands (AC-039-03) | Unchanged: `tests/Feature/Operations/Backup/BackupRunTest.php` (TASK-013), `tests/Feature/Operations/Reconciliation/ReconciliationTest.php` (TASK-036) |

## 6. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-039-01 | `SystemStatusTest`; `StatusPageTest`; `CapabilityMatrixTest` RP-611; `LocalVolumeSpaceTest`; `status.spec.ts` | `PASS` in fast run 2 for the PHP tests; `LocalVolumeSpaceTest` and `status.spec.ts` run first on the final head, `NOT_RUN` until then |
| AC-039-02 | `SystemStatusTest::test_an_open_incident_goes_with_the_read_until_it_is_reopened`; `StatusPageTest::test_the_incident_flag_follows_the_open_incidents`; `status.spec.ts` | `PASS` in fast run 2; the browser case on the final head |
| AC-039-03 | `MonitorHeartbeatTest`; for the backup and reconciliation checks, `BackupRunTest` and `ReconciliationTest`, unchanged | `PASS` in fast run 2 (`MonitorHeartbeatTest`, its sample of R-05 changed after it, so again on the final head); `BackupRunTest` and `ReconciliationTest` in the whole PostgreSQL suite on the final head |
| AC-039-04 | `SystemStatusTest`: `backup_runs` as the backup role writes it, `restore_drills` and `reconciliation_runs` through their own services | `PASS` in fast run 2 |

## 7. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A |
| EXC-45 | The fixtures as each writer writes them: `backup_runs` as the backup role inserts its rows (as `ReleasePreflightTest` does), `restore_drills` by `RestoreDrills::record`, `reconciliation_runs` by `ReconciliationRuns::record`, the incidents by the Inventory module's block service (`TokoContoh::postingBlock`) |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `PASS` — the criteria of section 6 in fast run 2, with the final head's suites before the merge |
| UI and UX | `PASS` for what has run — the copy keys verbatim (`CopyDeckTest`, `composer lint`), the states UXS-01, UXS-22 and UXS-43, the controls UXI-329 and UXI-374 to UXI-376; the browser cases at 1440 px, 320 px and 200 % zoom with the keyboard path, axe, contrast and 44 px targets run on the final head, `NOT_RUN` until then |
| Backend | `PASS` — one route with its gate and input allowlist, no FormRequest because it takes no input, the page's props allowlisted on both channels (`Allowlists`), no write by the read |
| Database | N/A — no migration |
| Security | `PASS` — CAP-32 only, every other role and the combined account refused, CAP-18 for the incident link; no address or secret printed or logged by SC-04; a failed read's cause on no answer; the guard `PASS`. SC-04 writes no security log entry or audit event, N/A with the reason of C-13 (review R-06) |
| Data integrity | `PASS` — a read only, no lock and no write (`test_the_read_writes_nothing`); never a passing look without evidence |
| Performance | `PASS` for what applies — six bounded reads by the indexes on `finished_at`, the re-verification bounded to 8 days; the budgets are measured at TASK-048 |
| Operations | `PASS` — no new table in the manifest's counts; OPERATIONS_RUNBOOK sections 4 and 5 and RB-04 updated (C-14, review R-01); SC-04 in maintenance mode; its audit event and security log entry N/A (C-13) |
| Verification | `PASS` for what runs before the pull request: the self-review; the class-A review `PASS` with no MUST-FIX (section 10); fast run 2 green. The command contract and the image checks run on the pull request's final head before the merge (CE-41) |

## 9. The guard's candidates (CE-31; D-076, D-097, D-098)

| Candidates | Judgment |
| --- | --- |
| The loopback address on port 18080, with and without `/up`, in `tests/Unit/Operations/MonitorHeartbeatTest.php` (checkpoint 1) | The loopback address of a test of `PublicHealth::address()`, no host of any environment and no secret: not a finding. Replaced in the record by the test's synthetic `.test` host with a port and a path, so that no committed sample needs an acceptance (R-05's reasoning) |
| An address of the synthetic `.test` host of the same test with the placeholder words "user" and "pass" before the host (checkpoint 1) | The case of an address with a user, which the command refuses: no credential, not a finding. Replaced after the review by an address with a user name alone, because EXC-63 wants no committed sample that the guard must accept (review R-05) |

## 10. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review, on `bd70372`, the head of fast run 2: each item against OPERATIONS_RUNBOOK section 5 and SCHEMA section 5.12, the boundaries of each threshold, the precedence of a failure over a stale success, the writers of each table (BackupRunner, OP-08, SC-01), the page's states and props per role, SC-04 against ROUTE_CONTRACTS SC-04 and RH-01 and its secrecy, the schedule in maintenance mode, the module boundaries and the obligations of EXECUTION_CONTEXT section 10.

### 10.1 Round 1, the full review

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `review`; package `review/TASK-039/r1` of the executor state, base `ad9a500`, HEAD `bd70372`, with the uncommitted record in its snapshot tree; 21 November, 04:17 to 04:29; `review-pack.sh --check` `UNCHANGED` after it. Verdict `PASS`: no MUST-FIX, five SHOULD-FIX and five NOTEs, decided from the code and the documents, with no test run asked for.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | OPERATIONS_RUNBOOK section 5 read reconciliation from "the newest row", while the code judges the newest run of every product and every run since (C-5); after RB-04 step 6 the item stays failed until a run of every product, which RB-04 did not say; C-4's new installation and C-6's drift lived only in the evidence | Fixed: section 5's rows of the backup and the reconciliation, a paragraph on the clock, the drift and the age in minutes, and RB-04 step 8 |
| R-02 | SHOULD-FIX | OPEN_ITEMS T-25, TASK_PLAN section 7 and CURRENT_HANDOFF still planned a change control before TASK-039, which ran without it; ages in minutes and the size symbols GB and MB lack a format of COPY_DECK section 1.3 | Fixed in the documents: T-25 says what TASK-039 applied and moves the rest — the contract's and RELEASE_PROCESS's five items, a unit above minutes, a size format — to TASK-047 or a change control the planning agent names; TASK_PLAN section 7 and CURRENT_HANDOFF follow. The age in minutes is reported to the Owner as a known limit |
| R-03 | SHOULD-FIX | No 200 % zoom and no keyboard path in the browser test (EXECUTION_CONTEXT section 10, a page) | Fixed: a second test at `WIDTHS[4]` with axe, contrast and targets, "Muat ulang" and "Kembali ke Pengaturan" by Enter |
| R-04 | SHOULD-FIX | `LocalVolumeSpace`, the production measure of "Ruang penyimpanan", had no test | Fixed: `tests/Unit/Console/LocalVolumeSpaceTest.php` |
| R-05 | SHOULD-FIX | The committed sample address with a user and a password needed the guard's acceptance, which EXC-63 rules out | Fixed: an address with a user name alone, refused by the same rule (section 9) |
| R-06 | NOTE | SC-04 writes no audit event or security log entry; SCHEMA section 3.4 names no heartbeat actor | Recorded as N/A with C-13's reason in the Definition of Done |
| R-07 | NOTE | The stand-in is an in-process handler; OutboundHttp's redirect and verification settings on SC-04's path are TASK-013's | Recorded, no change: `ObjectStoreServerTest` (TASK-013) proves the client against a real address |
| R-08 | NOTE | The REMOTE_MISMATCH rows of the fixtures carry no object hashes, which BackupRunner sets on that failure | Recorded, no change: the read uses no hash |
| R-09 | NOTE | ProcessSchedule's skip of an absent command is now untested and unused, every command existing | Recorded, no change: TASK-012's code, outside this Task's scope |
| R-10 | NOTE | F-6 before F-5; the evidence's placeholders | Fixed in the record |

No MUST-FIX was raised, so no verification round is due (CE-40). The fixes change tests and documents only, and no production behaviour; the checks they touch — the unit suite, PHPStan, ESLint, the typecheck and the browser suite — run on the final head, in its fast run and in the pull request's CI (CE-45).

## 11. Record (CE-50)

- Guard: `guard.sh` on every pushed head, `PASS` (checkpoint 1 after the judgment of section 9); `guard.sh --preview` of the record's change before its commit.
- Backup impact (EXC-47): none; no new table or kind of stored file.
- The previous merge: TASK-038's pull request #53 (head `d70cd31`, CI 44800522582; squash `ad9a500` by the tick, the automated review DONE; CI on `develop` 44805266626) — recorded in [TASK-038's evidence](TASK-038.md) section 12 and in CURRENT_HANDOFF.
- Documents: TASK_PLAN — TASK-039 `DONE`; its one dependent, TASK-041, stays `TODO` (TASK-040 is not `DONE`); the totals TODO 9, READY 2, DONE 39; section 7's row of T-25. OPEN_ITEMS T-25 and T-39 (b), the new T-51; OPERATIONS_RUNBOOK sections 4, 5 and RB-04; STACK_AND_MODULES sections 2.1 and 3; CURRENT_HANDOFF last. No decision is recorded; the next free decision is D-135.
- Fast runs: two of the ten of CE-46 before the pull request; run 1 failed on proven defects of the change, all fixed in one push and cured in run 2 (F-6, D-133); no CI run was repeated.
- Not verified before the pull request's final head: the whole PostgreSQL suite in four parts, `test:race`, the browser suite, the build and the image checks; and the review's test fixes (R-03 to R-05), which run first there.
- Next eligible Task: TASK-040, Finance report, effort High (agent `bk-executor-high`; review and verification `bk-reviewer-high`).
