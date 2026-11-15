# TASK-036 evidence — Reconciliation, incidents and data-repair procedures

| | |
| --- | --- |
| Task | [TASK-036](../tasks/TASK-036-reconciliation-repair.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-036-reconciliation-repair`, from `95f2dd0263079f6ecc7750fa14575b13464a867d` (origin/develop, the squash merge of the `ops/` pull request #49, D-132) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; no branch or pull request of TASK-036 and no open pull request; the executor state at `BUILD` for TASK-036 with this session's lock, the merge of #49 verified; TASK-013, TASK-020 and TASK-034 `DONE`, no gate. PHP is not on this machine, and its `node_modules` has no `.bin` links, so `tsc` and ESLint do not run here either: every check but the guard runs in CI (D-108, D-117) |
| Owner's directives in force | Forwarded by the host, adding no permission: FULL V1 (D-130), the contract built directly with the cheap relevant checks before each push, evidence reused, one review; the safety boundary unchanged — no repair, reconciliation or destructive operation against a real, staging or production database, no weakened test, timeout or baseline, a new CVE a stop; PostgreSQL in four parts (D-131), a new `tests/Feature` directory joining part 1; the known flakes classified only on log evidence; commit and pull request texts without tool or vendor names, attribution or agent wording |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 15 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint`, PHP's checks | Local | 16:04 | — | `NOT_RUN`: PHP is not on this machine, and `node_modules` has no `.bin` links (`tsc` not found); the fast runs run them |
| Guard preview | Local, on the working tree against `origin/develop` | 16:04 | under 1 min | `PASS`, no candidate |
| Guard (Gitleaks 8.30.1, the secret scan of the tree and the commits, identity, candidates) | Local, on `e9ab7d5` (checkpoint 1: the whole build) | 16:05 to 16:10 | about 5 min | `PASS`, no candidate |
| `composer validate`, `lint`, then `composer analyse` | Fast run 1, 43698056629 on `e9ab7d5` | 16:11 to 16:15 | 4 min 7 s for the job | `FAIL`: `validate` and `lint` (Pint, the translation keys and their types, the migration review, the parts of the PostgreSQL suite) `PASS`; PHPStan six errors in three test files (F-16); the later steps did not run |
| Guard | Local, on `01a61e6` (checkpoint 2: F-16) | 16:16 to 16:20 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 2, 43698779345 on `01a61e6` | 16:20 to 16:27 | about 7 min, the start of the environment included | `PASS` |
| The changed PostgreSQL and concurrency files, in the order of their paths: `ReconciliationRaceTest` (2 tests), `RouteInventoryTest` (8), `ShellDataTest` (3), `IncidentsTest` (5), `ReconciliationRunsTableTest` (3), then `ReconciliationTest` | Same run | 16:27 to 16:29 | 1 min 15 s; the job 8 min 44 s | `FAIL`: the first five `PASS`, RACE-24 among them; `ReconciliationTest` two failures of 7 tests, a defect of the tests (F-17); `ReleaseMigrateTest` and `RepairProceduresTest` did not run |
| Guard | Local, on `387706b` (checkpoint 3: F-17) | 16:30 to 16:34 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse` (no error), `test:unit` (937 tests); `npm run lint`, `typecheck` (the browser spec among the files), `test:unit` | Fast run 3, 43699919375 on `387706b`, the reviewed head | 16:34 to 16:39 | about 5 min, the start of the environment included | `PASS` |
| The changed PostgreSQL and concurrency files, in the order of their paths: `ReconciliationRaceTest` (2 tests), `RouteInventoryTest` (8), `ShellDataTest` (3), `IncidentsTest` (5), `ReconciliationRunsTableTest` (3), `ReconciliationTest` (7), `RepairProceduresTest` (7), `ReleaseMigrateTest` (10) | Same run | 16:39 to 16:41 | about 2 min; the job 6 min 21 s in all | `PASS`: 8 files, 45 tests |
| Guard | Local, on `0f6f3e0` (checkpoint 4: the review's fixes, section 9.1) | 17:00 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL and concurrency files — the eight of fast run 3, with the review's tests in `ReconciliationRaceTest` (3 tests), `ReconciliationTest` (8) and `RepairProceduresTest` (8) | Fast run 4, 43702030161 on `0f6f3e0`, the review's fixes | 17:01 to 17:07 | 6 min 15 s for the job | `PASS` |
| Whole PostgreSQL suite in four parts, `test:race`, `npm run build`, the browser suite with `inventory/incidents.spec.ts`, the image checks | CI on the pull request's final head (CE-41, D-117) | — | — | `NOT_RUN` until then |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. `reconciliation_runs`, class F, with its checks, its index on `finished_at` and its grants: the runtime role `SELECT` and `INSERT`, the backup role `SELECT` | `database/migrations/2026_11_16_000001_create_reconciliation_runs_table.php`; `database/privileges.php` |
| 2. SC-01 `reconciliation:run`: every product in key order and in batches, each read in a REPEATABLE READ, READ ONLY snapshot of its own; its stock on hand and last place from the sums of its movements, its valuation and every issue and return projection replayed from its first movement by the application's own arithmetic (`ValuationReplay`, ADR-008 section 5); a product that differs gets the enveloped `posting_block.block` of the process `reconciliation`, whose intent is derived from the run and the product, which checks it again under the product's guard and writes the block with its detail code, the audit event `RECONCILIATION_BLOCK` and its result; for a run of every product, the running totals of every Sale and the active mapping of every token compared, a difference reported, never blocked; one row per run, `CLEAN`, `MISMATCH` or `FAILED`; the heartbeat's reconciliation check pinged only after a completed run of every product; bounded by its run budget; its own no-overlap lock beside the scheduler's | `App\Console\Commands\ReconciliationRun`; `App\Actions\Operations\RunReconciliation`, `BlockPostings`; `App\Domain\Operations\Reconciliation\` — `ProductReconciliation`, `ProductRecomputation`, `RunningTotalsReconciliation`, `MappingReconciliation`, `ReconciliationRuns`, `Snapshot`, `BlockOutcome`, `NoBlock`, `RunSummary`; `config/reconciliation.php`; `App\Console\Commands\ReleaseMigrate` (the migration job's verification) |
| 3. RC-16 `posting_block.reopen`: CAP-18 with recent authentication; the block's product under its guard (step 6); NOT_FOUND for no block, STALE_VERSION for one already reopened; the reopening with an optional reason, the audit event `POSTING_REOPEN`, the result leading to RP-415. RP-414 "Insiden rekonsiliasi" with the filter "Status" and paging forward, RP-415 "Detail insiden" with "Yang tidak cocok", "Produk yang diblokir", "Riwayat stok" and "Buka kembali mutasi" with its confirmation; RD-03's open incidents for the Owner and the count of UXI-017; UXI-223's way back to UXP-415 | `App\Actions\Inventory\ReopenPostings`; `App\Http\Requests\Inventory\ReopenPostingsRequest`, `IncidentsPageRequest`, `HistoryPageRequest`; `App\Http\Controllers\Inventory\ReopenPostingsController`, `IncidentsController`; `App\Queries\Inventory\Incidents`; `App\Domain\Inventory\PostingBlocks`; `App\DTO\Access\ShellIndicatorsData`, `App\Http\Controllers\Access\ShellDataController`; `routes/web.php`; `resources/js/pages/inventory/Incidents.tsx`, `Incident.tsx`, `History.tsx`; `resources/js/lib/inventory/incidents.ts`, `stock.ts`; `resources/js/lib/navigation.ts`, `resources/js/layouts/AppShell.tsx`, `resources/js/lib/commands/unresolved.ts`; `lang/id/inventory.php`, `lang/id/ux.php`; `resources/js/types/translation-keys.ts` |
| 4. OP-07 `projection:rebuild`: exactly one of a product, a Sale or a token; under the guards of the commands that write them — the product (step 6) then its balance, valuation and projections (step 7); the original Sale's lock (step 5); the products of a token's rows (step 6) — the projections recomputed from the facts and written through their modules' own services, `PROJECTION_REBUILT` with the procedure as actor and cost only in its Owner-only data; never a fact changed; refused with no change when the facts fail validation; nothing written when every projection already equals its facts | `App\Console\Commands\ProjectionRebuild`; `App\Actions\Operations\RebuildProjection`; `App\Domain\Sales\Corrections\SaleCorrections::refundsAgreeWithLines`, `rebuildTotals`; `App\Domain\Catalog\Barcodes\Barcodes::activeRowOf`, `rebuildActive` |
| 5. OP-06 `costing:recover`: on the Owner's written decision, the mark, its cost source, the corrected amount and the reason as typed options; never a mark of `UNSAFE_HISTORY`; RC-15's validation and replay with a movement cap of ten times RC-15's and a transaction of at most 10 minutes, under the product's guard; the correction with the Owner as its actor, the valuation and projections published, every active mark of that source cleared with `RECOVERY_PROCEDURE`, the audit event `COST_CORRECTION` with the procedure as actor and the operator's note, the result; its identity's intent derived from the mark, so a repeated run returns the first result; or refused with no change | `App\Console\Commands\CostingRecover`; `App\Actions\Operations\RecoverCost`; `App\Domain\Costing\CostIncompleteMarks::markOf`, `clearByRecovery`; `App\Domain\Costing\Replay\ProductReplay` (the cap of the run), `ReplayBudget::lasting` |
| 6. The staging rehearsal script of UAT-20, for the operator | `docs/10-release/UAT_20_REHEARSAL.md` |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| F-1 | **Two passes, so that a block and a posting serialize (RACE-24).** The comparison of the run reads each product in a snapshot of its own and takes no lock, so the daily run blocks no posting of a product that agrees. Only a product that differs gets `posting_block.block`, which takes the product's guard and compares again under it, at READ COMMITTED with every writer of the product held back: a posting that took the guard first is among the facts it reads (posting first: the reconciliation sees it), and one that waits finds the block (block first: POSTING_BLOCKED). A product found to agree under the guard writes nothing, its identity included |
| F-2 | **A run's identity and one block per incident.** A run's key is a random UUID; the intent of each block is the UUIDv5 of the run and the product, so a repeat of the same run's block writes once (SCHEMA section 5.3). A product already blocked — by an earlier run that the Owner has not reopened — gets no second block: the run counts it as a mismatch, writes nothing for it and stays `MISMATCH` |
| F-3 | **The detail codes** (`^[A-Z][A-Z0-9_]{0,63}$`, SCHEMA section 5.7), the first that holds for a product in this order: `BALANCE_MISMATCH` — the balance's stock on hand or last place differs from the movements, or a balance stands without a movement or is missing with one; `HISTORY_INVALID` — the facts cannot be replayed as a valid history (an issue beyond its stock, a value out of range); a projection missing or of a status its movement cannot have is a projection, compared and never used, so it is reported as its projection's mismatch and rebuilt (review R-01); `VALUATION_MISMATCH`; `ISSUE_PROJECTION_MISMATCH`, a cancelled manual issue's projection not CANCELLED included; `RETURN_PROJECTION_MISMATCH`. RP-415 shows the code under "Yang tidak cocok" as the record holds it: COPY_DECK section 9.16 names the field but no word per code, so a label per code is for the next change control of COPY_DECK, recorded as OPEN_ITEMS T-47 (review R-07) |
| F-4 | **A running total or an active mapping that differs is an incident reported, never blocked** (G-03, BR-INV-46): it counts in `other_differences` and makes the run `MISMATCH`, which the system status of TASK-039 shows; the run's output and the application log name the Sales and tokens by internal key for RB-04, never an amount or a name. INTERACTION_CONTRACTS section 8 names no audit action for it, so none is written; RP-414 lists the posting blocks alone, as ROUTE_CONTRACTS RP-414 says |
| F-5 | **The exit status.** A run that completes `CLEAN` succeeds; `MISMATCH` and `FAILED` fail, so that the migration job's verification needs a clean run (RELEASE_PROCESS REL-20). The heartbeat is pinged after every completed run of every product, `CLEAN` or `MISMATCH`, since the monitor watches whether the daily reconciliation ran (OPERATIONS_RUNBOOK section 4); a failed run and a run for one product ping nothing (AC-036-07) |
| F-6 | **The operator's run** (OPERATIONS_RUNBOOK section 3, RB-04 step 6): `reconciliation:run --operator --environment=<name> [--product=<key>]`, the environment typed as OPR-02 asks of a procedure, trigger `OPERATOR`, scope `PRODUCT` for one product. The scheduler's run takes no option (ENVIRONMENTS section 13); an option of the operator's without `--operator` is refused |
| F-7 | **The migration job** (OPEN_ITEMS T-39 (a)): its container holds the migration role's credential only, so `release:migrate` runs `reconciliation:run --operator` with the environment that the operator typed and the preflight checked, on the job's own connection, which owns the schema; a mismatch fails the job's verification. On a mismatch the job's run writes its blocks, their identities and audit events and its row as the migration role, which owns every table; its no-overlap lock is taken in the cache store of the job's configuration, resolved on that connection. Only the clean path is proven here (`ReleaseMigrateTest`); the mismatch in the job is for TASK-044's rehearsal of the migration job (review R-06) |
| F-8 | **The record of a run** (SECURITY_MODEL section 14, EXECUTION_CONTEXT section 10): its row of `reconciliation_runs` and a security log entry `OPERATOR_PROCEDURE_RUN` whose actor is the scheduled command `reconciliation` — the event list of SCHEMA section 5.4 has no event of its own for a scheduled command, and section 14 says that the events of scheduled commands name their command in the same way. Each block has its audit event; a run itself writes none, since section 8 names no action for it |
| F-9 | **The bound.** A run passes its budget of `config('reconciliation.run_budget')`, 2,700 seconds, below the scheduler's lock of an hour, and then ends `FAILED`; each snapshot and each block is bounded by the runtime role's own transaction budget. A product's comparison replays its whole history, outside the movement cap, as the state before a replay's start does (ADR-008 section 4.3). Whether a run finishes within its bound on the provisional dataset — the contract's stop condition — cannot be judged before TASK-043 provides that dataset: PERF01 measures it (TASK-048) |
| F-10 | **OP-06's identity and actor.** Its identity is of the operation of RC-15, `cost_correction.confirm`, with the actor `OPERATOR_PROCEDURE` `cost_recovery` and the intent and key the UUIDv5 of the mark, so a repeated run returns the first correction and writes nothing. The correction's actor is the one active Owner account, under the share lock of step 2.1 (G-16); the audit event's actor is the procedure, with the operator's note, the values and the reason in its Owner-only data. The run's cap is ten times RC-15's (`config('reconciliation.recovery_factor')`, never above 10) and its transaction and statements are bounded at 600 seconds, set for the session before BEGIN and restored after it |
| F-11 | **OP-07's writes.** Each projection is written through the module that owns it (STK-12): the balance through `StockLedger`, the valuation and projections through `InventoryValuations`, `IssueAllocations` and `ReturnAllocations` with the valuation's next publication number, the running totals through `SaleCorrections` by a relative increment of each difference (SCH-33), the active row through `Barcodes`. A rebuild that finds every projection equal writes no audit event |
| F-12 | **RC-16's reason** is optional (INTERACTION_CONTRACTS RC-16); UXP-415's dialog has no field for it in COPY_DECK section 9.16, so the page sends none, and the reason stays the Owner's in the audit's Owner-only data |
| F-13 | **UXI-223 to UXP-415** (TRACEABILITY section 9.2, "Extended by"): RP-402's `from_page=incident`, which UXI-303 sends; "Kembali ke Detail insiden" leads back to the incident that opened the history, or to RP-414 when the history was opened otherwise |
| F-14 | **The migration's name** is dated 16 November, after TASK-035's of 15 November, so that the order of the migrations is the order of the Tasks |
| F-15 | **The fixtures** `TokoContoh::postingBlock` and `reopenPostings` stay: they write through the same Inventory service as the commands, and the tests of earlier Tasks use them. The browser fixture `incident` uses the first |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-16 | Fast run 1: PHPStan found six errors in three new test files — a `catch (CommandRefused)` around a fixture that declares only `DatabaseRefused`, so the catch was dead and the `fail()` before it unreachable, three times, and `factsOf()`'s return type | Fixed in checkpoint 2: each refusal caught as `Throwable` and asserted to be the CommandRefused of its code; the return type widened. Test code only |
| F-17 | Fast run 2: every static check and unit test passed, and `ReconciliationRaceTest`, `RouteInventoryTest`, `ShellDataTest`, `IncidentsTest` and `ReconciliationRunsTableTest` passed; two tests of `ReconciliationTest` failed because they read `Artisan::output()` twice, and the framework's buffer is empty at the second read | Fixed in checkpoint 3: the output read once, in both tests and in the same pattern of `RepairProceduresTest`. Test code only |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 reconciliation family | `tests/Feature/Operations/Reconciliation/ReconciliationTest.php`: a clean shop; each induced mismatch — balance, valuation, issue projection, return projection — blocking its product, whose postings are refused POSTING_BLOCKED while another product posts; a valid UNKNOWN cost no mismatch; a repeated run blocking once; a running total and an active mapping reported, never blocked; the operator's run and its typed options; a failed run recorded, never pinging; the block's failure after each write step; one writer per table. `ReconciliationRunsTableTest.php`: the constraint, privilege and retention families |
| L3 RACE-24 | `tests/Concurrency/ReconciliationRaceTest.php`: the block against a posting in both orders, a product that agrees under the guard, and RC-16's reopening against a posting in both orders |
| L2 procedure tests of OP-06 and OP-07 | `tests/Feature/Operations/Reconciliation/RepairProceduresTest.php` |
| L4 RC-16 | `tests/Feature/Inventory/IncidentsTest.php`: recent authentication, the Owner alone at the gate and in the transaction, STALE_VERSION and NOT_FOUND, the repeat and the status check, the fields, the failure after each write step |
| L5 RP-414 and RP-415 | `tests/Feature/Inventory/IncidentsTest.php` (the pages' answers and RD-03) and `tests/Browser/inventory/incidents.spec.ts` |
| Changed tests of earlier Tasks | `RouteInventoryTest`, `ShellDataTest`, `ReleaseMigrateTest`; the unit tests `OperationsTest`, `PrivilegeMatrixTest`, `ProcessScheduleTest`, `commandClient.test.ts`, `navigation.test.ts` |

## 6. Acceptance criteria

| Criterion | Proof | Result |
| --- | --- | --- |
| AC-036-01 | `ReconciliationTest`: the four induced inventory and cost mismatches each block their product with their detail code; a posting of a blocked product refused POSTING_BLOCKED, another product posting; the running total and the active mapping reported | `PASS` in the fast run on the reviewed head, subject to CI on the final head |
| AC-036-02 | `ReconciliationRaceTest`: RACE-24 in both orders, with the block and with RC-16 | `PASS` in fast run 3 on the reviewed head, subject to CI on the final head |
| AC-036-03 | `IncidentsTest` (recent authentication, the Owner alone, the pages' props) and `incidents.spec.ts` (L5) | PHP tests in the fast runs; L5 `NOT_RUN` until the final head |
| AC-036-04 | `RepairProceduresTest`: the rebuild of a product, a Sale and a token from facts unchanged, `PROJECTION_REBUILT`, the refusals on invalid facts | As AC-036-01 |
| AC-036-05 | `RepairProceduresTest`: the recovery published with the larger bound, the marks cleared `RECOVERY_PROCEDURE`, the repeat returning the first result, the refusals | As AC-036-01 |
| AC-036-06 | `RepairProceduresTest::test_both_procedures_follow_the_common_rules_of_the_operator_procedures` and the security log entries asserted in the procedure tests | As AC-036-01 |
| AC-036-07 | `ReconciliationTest`: one row per run, the ping after a completed run of every product only, none after a failed run or a run for one product; `ReconciliationRunsTableTest` | As AC-036-01 |

## 7. Database (EXC-41 to EXC-47)

| Item | Record |
| --- | --- |
| Migration | `2026_11_16_000001_create_reconciliation_runs_table`, class additive, with its REL-20 metadata, which the migration review in `composer lint` checks; forward only (EXC-41) |
| Constraints | The checks of SCHEMA section 5.12, each named; the index on `finished_at` for "the newest row" of OPERATIONS_RUNBOOK section 5 |
| Privileges | Class F in `database/privileges.php`: the runtime role `SELECT` and `INSERT`, the backup role `SELECT`, with its `SELECT` on the identity sequence; `PrivilegeMatrixTest` and `ReconciliationRunsTableTest` |
| Tests on the database (EXC-45) | Fixtures through the Actions; the induced mismatches written by the schema's owner on the test database only, as the induced mismatch of UAT-20 is on staging; the constraint family's rows as the runtime role |
| Backup impact (EXC-47) | `reconciliation_runs` joins the backup manifest's counts through its class in the privilege matrix; no stored file or store changes |

## 8. Definition of Done

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS, subject to CI on the final head | AC-036-01 to AC-036-07 by their tests (section 6); REQ-INV-12 built, with its shares of REQ-COST-06, REQ-OPS-04 and REQ-CMD-03 |
| UI and UX | PASS, subject to CI on the final head | UXP-414 and UXP-415, UXI-017, UXI-301 to UXI-305 and UXI-223's destination from the approved P7 artifacts, the copy verbatim from COPY_DECK sections 4.1, 4.3, 9.15 and 9.16; L5 in `incidents.spec.ts`, `NOT_RUN` until the final head |
| Backend | PASS | The FormRequest allowlists of RC-16 and RP-414, CAP-18 at the gate with recent authentication for RC-16, the guard under the product's lock, the result rebuilt per viewer; L4 tests in the fast runs |
| Database | PASS | The migration reviewed (section 7); the constraint, privilege and retention families of `reconciliation_runs` |
| Security | PASS | CAP-18 the Owner's alone, every other role and the combined account refused at the gate and in the transaction; the procedures' typed environment and options, their audit and security log; channel allowlists exact; no secret; the guard `PASS` on every pushed head |
| Data integrity | PASS, subject to CI on the final head | Facts never changed by the reconciliation or the procedures; each write all or nothing with failure injection after every write step of the block, RC-16, OP-06 and OP-07; RACE-24 in both orders; the block's and OP-06's identities |
| Performance | PASS | The run reads in batches with a snapshot per product and a budget of its own; RP-414 pages with a keyset cursor; the budgets are measured at TASK-048, the run's on the provisional dataset among them (F-9) |
| Operations | PASS | The table in the backup manifest's counts (section 7); the operator's run and the two procedures for RB-04 and RB-12, the migration job's verification (F-7) and the UAT-20 script for TASK-045 |
| Verification | PASS, subject to CI on the final head | The fast runs and the review of section 9; the heavy checks on the final head |

## 9. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review, on `387706b`, the head of fast run 3: the lock order of the block, RC-16, OP-06 and OP-07 against TR §7.1 and the writers of the same rows; the two passes of F-1 against RACE-24; the one-writer scans; the FormRequest and parameter allowlists against INTERACTION_CONTRACTS RC-16 and ROUTE_CONTRACTS RP-414; the channel allowlists of the two pages and RD-03; the copy against COPY_DECK sections 4.1, 4.3, 9.15 and 9.16; the standing obligations of EXECUTION_CONTEXT section 10 for each command, page, read, table and procedure.

### 9.1 Round 1, the full review

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `review`; package `review/TASK-036/r1` of the executor state, base `95f2dd0`, tree `ecfa50f`, HEAD `387706b`; 15 November, 16:42 to 16:53; `review-pack.sh --check` `UNCHANGED` after it. Verdict `FAIL`: three MUST-FIX, four SHOULD-FIX and five NOTEs. What held under its attack: the lock order and atomicity of the block, RC-16, OP-06 and OP-07; RACE-24 in both orders; the replay's arithmetic against the posting's; CAP-18 at the route, the gate and the transaction; RD-03's count for the Owner alone; the channels without cost; the copy keys; the migration and its grants.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | A CANCELLED projection on a manual issue never reversed — a projection, not a fact — was reported `HISTORY_INVALID` and refused by OP-07; the test of invalid facts used exactly that corruption | Fixed: `ReplayLedger::from()` takes `$compared`, which the reconciliation's full replay sets, so a stored projection that cannot be read is read as none — a projection to rewrite — while RC-15 and the reversal keep refusing it; `LedgerEntry` takes a null projection. Regression test `RepairProceduresTest::test_a_projection_of_a_status_its_movement_cannot_have_is_reported_and_rebuilt` (blocked `ISSUE_PROJECTION_MISMATCH`, rebuilt, clean); the invalid-fact case is now a real fact — the receipt's movement moved behind the issues by the schema's owner |
| R-02 | MUST-FIX | No RACE-01 or RACE-02 for RC-16 | Fixed, test only: `ReconciliationRaceTest::test_race_01_and_race_02_a_reopening_runs_once_for_the_same_key_or_the_same_intent` |
| R-03 | MUST-FIX | The clean shop had no OPENING, reversal, adjustment, correction, damaged return or pool taken to zero: a false positive there would block sales daily | Fixed, test only: `ReconciliationTest::test_a_valid_history_of_every_movement_type_reconciles_clean` with each of them |
| R-04 | SHOULD-FIX | The command's own no-overlap lock was not exercised | Fixed, test only: the lock held, `RECONCILIATION_ALREADY_RUNNING`, no row, no block |
| R-05 | SHOULD-FIX | OP-06's time bound untested; the session's limits restored by `reset`, and the idle limit of 30 s not raised | Fixed: the idle limit raised with the other two, and all three put back as they were read before; test of TIME_BUDGET refused with nothing written and the limits back |
| R-06 | SHOULD-FIX | No test of a mismatch in the migration job | Recorded in F-7 for TASK-044's rehearsal of the migration job; the job's writes as the migration role and its lock's store stated there |
| R-07 | SHOULD-FIX | F-3 pointed at an open item that does not cover the codes of "Yang tidak cocok" | Fixed: OPEN_ITEMS T-47, and F-3 corrected |
| R-08 | NOTE | One product that cannot be blocked in time fails the whole run | Recorded for PERF01, TASK-048 (F-9) |
| R-09 | NOTE | `publicationNo` in the audit when only the balance changed | Fixed: recorded only when a publication was made |
| R-10 | NOTE | A repeat of OP-06 with other options reports the first result without a warning | Recorded; the first result is what SEC-OP-06 asks for |
| R-11 | NOTE | The operator's refused options of `reconciliation:run` write no security log entry | Recorded: those refusals precede the run, as a refused option of `backup:run` does |
| R-12 | NOTE | A FAILED row may be lost when PostgreSQL ended the session | Recorded: the failure is then reported and the run not pinged, which the monitor alerts on |

The fixes change `ReplayLedger`, `LedgerEntry`, `ProductReconciliation`, `RecoverCost` and `RebuildProjection` with their tests; fast run 4 (43702030161 on `0f6f3e0`) passed them (section 1). Three MUST-FIX were raised, so round 2 verifies their fixes.

### 9.2 Round 2, the targeted verification of R-01 to R-03

Agent `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode `verify`; package `review/TASK-036/r2`, base `95f2dd0`, tree `50111f9`, previous package `r1`, HEAD `0f6f3e0`; 15 November, 17:09 to 17:12; `review-pack.sh --check` `UNCHANGED` after it. Verdict `PASS`, no MUST-FIX left.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | As in section 9.1 | `FIXED`: only `ProductReconciliation::recompute` sets `$compared`, so RC-15, the reversal and OP-06 still refuse an unreadable projection; the CANCELLED projection now gives `ISSUE_PROJECTION_MISMATCH` and is rewritten; the invalid-fact case is a fact |
| R-02 | MUST-FIX | As in section 9.1 | `FIXED`: RACE-01 and RACE-02 of RC-16, one identity, reopening and audit event per intent |
| R-03 | MUST-FIX | As in section 9.1 | `FIXED`: every movement type of SCHEMA section 5.7 in a valid history reconciles clean |
| N-01 | NOTE | A comment in `ProductReconciliation::recompute` still names the projections | Recorded for the next change of that file |
| N-02 | NOTE | The other-payload half of RACE-01 for RC-16 is the pipeline's, proven by `CommandRaceTest` | Recorded |
| N-03 | NOTE | A missing projection would end OP-07 with a LogicException, rolled back, which the deferred presence keys make unreachable | Recorded |

No further round is due: each MUST-FIX is fixed, proven by its regression test and this targeted check (CE-40).

## 10. Record (CE-50)

- Guard: `guard.sh` `PASS` with no candidate on every pushed head (section 1); `guard.sh --preview` of the uncommitted record `PASS` with no candidate, so nothing to judge.
- Backup impact (EXC-47): `reconciliation_runs` joins the backup manifest's counts through its class in the privilege matrix (section 7).
- The previous merge: TASK-035's pull request #47 and its recovery under D-131 and D-132, recorded in [its evidence](TASK-035.md) section 12 and in CURRENT_HANDOFF; `ops/` pull request #49 (head `0c128b6`, CI 43684839528; squash `95f2dd0` by the Owner, CI on `develop` 43691779711) was the last merge verified.
- Documents: TASK_PLAN: TASK-036 `DONE`, TASK-042 `READY` — its dependencies TASK-035 and TASK-036 `DONE`, no gate — while TASK-039 waits for TASK-037; the totals TODO 10, READY 4, DONE 36. OPEN_ITEMS T-39 (a) resolved and T-47 added. The UAT-20 script `docs/10-release/UAT_20_REHEARSAL.md`. CURRENT_HANDOFF last, with D-131 and D-132. No decision is recorded; the next free decision is D-133.
- Fast runs: four of the ten of CE-46 before the pull request, two failing on proven defects of this change's tests (F-16, F-17), each fixed; no CI run was repeated.
- For later Tasks: TASK-039 — the system status reads `reconciliation_runs`; T-47, the next change control of COPY_DECK — a word per detail code; TASK-043 and TASK-048 — the run's bound on the provisional dataset (F-9, R-08); TASK-044 — a mismatch in the migration job (R-06); TASK-045 — UAT-20 and the rehearsal of OP-06 and OP-07.
- Next Task: TASK-037, Restore tooling and recovery procedures, effort Max (agent `bk-executor-max`; review `bk-reviewer-max`; verification `bk-reviewer-xhigh`).
