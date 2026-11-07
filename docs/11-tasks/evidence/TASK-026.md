# TASK-026 evidence — Reversal of movements

| | |
| --- | --- |
| Task | [TASK-026](../tasks/TASK-026-reversal.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-026-reversal`, from `139f99d8d29a3191df96334edfa0e7b3a271d2d0` (origin/develop, the squash merge of TASK-025) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-026; the executor state at `BUILD` for TASK-026 with this session's lock; TASK-025 `DONE`, merged as `139f99d` (pull request #35), no gate; `tsc`, ESLint and PHP do not run on this machine (`node_modules/.bin` absent), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 7 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 09:40 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `4bd8cd8` | 10:10 | under 4 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 1, 42332801319 on `4bd8cd8` | 10:14 to 10:15 | 29 s of `lint` | `FAIL`: Pint's `ordered_imports` in `ConfirmReversal.php`, `Reversals` before `ReversalSource` (finding F-4); the later steps did not run |
| Guard | Local, on `e0a3745` | 10:19 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 2, 42333476278 on `e0a3745` | 10:22 | — | `PASS` |
| `composer analyse` | Same run | 10:22 | 11 s | `FAIL`: five offsets read on `mixed` in `ConfirmReversalTest` and `ReversalPagesTest` (finding F-5); the later steps did not run |
| Guard | Local, on `1b334cc` | 10:26 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` (with `ValuationReplayTest`, `OperationsTest`, `PrivilegeMatrixTest`); `npm run lint`, `typecheck`, `test:unit` | Fast run 3, 42334013917 on `1b334cc` | 10:27 to 10:31 | about 4 min | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes | Same run | 10:31 to 10:34 | 3 min 3 s | `FAIL` in `ReversalPagesTest`: the receiving page of an uncorrected receipt holds no correction, so its props could not equal the Owner's allowlist, and the reversal links, nested and null where absent, could never equal it (finding F-6); `ReversalRaceTest` 3, `CapabilityMatrixTest` 7, `RouteInventoryTest` 8, `ConfirmReversalTest` 9 and `LedgerTablesTest` 19 tests passed before it; `ReversalTablesTest` did not run |
| Guard | Local, on `cf97f49` | 10:39 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 4, 42335070523 on `cf97f49` | 10:43 to 10:47 | about 4 min | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes | Same run | 10:47 to 10:51 | 4 min 6 s | `FAIL` in `ReversalTablesTest`, 5 of 6: its deferred case (finding F-7); `ReversalRaceTest` 3, `CapabilityMatrixTest` 7, `RouteInventoryTest` 8, `ConfirmReversalTest` 9, `LedgerTablesTest` 19 and `ReversalPagesTest` 3 tests passed |
| Guard | Local, on `a93236e` | 10:54 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the seven PostgreSQL and concurrency test files that the branch changes — `ReversalRaceTest` (3 tests), `CapabilityMatrixTest` (7), `RouteInventoryTest` (8), `ConfirmReversalTest` (9), `LedgerTablesTest` (19), `ReversalPagesTest` (3) and `ReversalTablesTest` (6). TASK-025's `CorrectCostTest`, `CostCorrectionRaceTest` and `CostPagesTest`, which cover the changed `CorrectCost::mark()` and `ReplayLedger`, are not among them: they run in `test:postgres` on the final head (review note R-07) | Fast run 5, 42336113311 on `a93236e`, the reviewed head | 10:55 to 11:05 | 10 min 27 s | `PASS` |
| Guard | Local, on `4234f6e`, the review's fixes | 11:22 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the same seven PostgreSQL and concurrency test files, `ConfirmReversalTest` with the review's cases R-04 and R-05 and `ReversalPagesTest` with R-06's | Fast run 6, 42338216200 on `4234f6e` | 11:23 to 11:33 | 10 min 23 s | `PASS` |
| Guard preview of the record | Local, the working tree against origin/develop | 10:30 | under 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the same seven PostgreSQL and concurrency test files | Fast run 7, 42339483187 on `91d87b9`, started by the push of the record | 11:40 to 11:51 | 10 min 29 s | `PASS` |
| `composer validate`, `lint`, `analyse`, `test:unit`, `test:race`; `npm run lint`, `typecheck`, `test:unit`, `build`, the browser suite with `reversal.spec.ts` | CI on the pull request's first head, run 42339492526 on `91d87b9`, job `Command contract` | 11:40 to 12:06 | 25 min 34 s | `PASS` for each of these steps; the job failed only its last step, which requires both parts of `test:postgres` |
| `composer test:postgres:2` | Same run, job `PostgreSQL (2/2)` | 11:40 to 11:57 | 16 min 32 s | `PASS` |
| Image checks | Same run, job `Image checks` | 11:40 to 11:47 | 6 min 48 s | `PASS` |
| `composer test:postgres:1` | Same run, job `PostgreSQL (1/2)` | 11:40 to 12:00 | 19 min 40 s (tests 16 min 3 s) | `FAIL`, 2 of 496: the one-writer scans of `ConfirmManualIssueTest` and `ConfirmReceivingTest` did not list TASK-026's reads (finding F-8, section 11) |
| Guard preview of fix cycle 1 | Local, the working tree against origin/develop | 12:12 | under 1 min | `PASS`, no candidate |
| Guard | Local, on `f7b87dd`, the fix of fix cycle 1 | 12:13 to 12:17 | about 4 min | `PASS`, no candidate |
| Guard | Local, on the commit that corrects this table's run numbers, before the push | — | — | `NOT_RUN` at this writing; required to pass before the push (CE-31), its result in the executor's report |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the PostgreSQL and concurrency test files that the branch changes, now with `ConfirmManualIssueTest` and `ConfirmReceivingTest` | Fast run 8, on the pushed head of fix cycle 1 | — | — | `NOT_RUN` at this writing; awaited before the phase `CI`, its result in the executor's report |
| The whole command contract and the image checks on the final head | CI on the pull request's head after fix cycle 1 | — | — | `NOT_RUN` at this writing (CE-44, D-117); never on this machine (D-108) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. `reversals`, with the keys `stock_movements.reversal_id` → `reversals (id, product_id, inverse_quantity)` and `cost_incomplete_mark_clearances (clearing_reversal_id, evidence_movement_id)` → `reversals (id, original_movement_id)` (EXC-44) | `database/migrations/2026_11_07_000001_create_reversals_table.php`, `database/privileges.php`; `App\Domain\Inventory\Reversals`, `ReversalSource`, `StockLedger::appendReversal` |
| 2. RC-10, in the order of RC-10 under the locks of TR §7.2 "Reversal" | `App\Actions\Inventory\ConfirmReversal`, `ConfirmReversalRequest`, `ConfirmReversalController`, `routes/web.php`, `CommandActions` |
| 3. The replay of TASK-025 extended to REVERSAL: the logical ledger leaves every cancelled pair out of the zero point, the state before the start and the movements from it; a reversed manual issue's projection becomes `CANCELLED`; the mark of a refused correction of a reversed source is written with its clearance `SOURCE_REVERSED` | `ReplayLedger`, `ProductReplay`, `ProjectionChange`, `IssueAllocations::republish`, `CostIncompleteMarks`, `CorrectCost::mark()` |
| 4. RP-411 and the entries on RP-403 (UXI-227 and the reversal links of COPY_DECK 9.4), RP-406 (UXI-250) and RP-409 (UXI-266) | `ReversalsController`, `ReversalPageRequest`, `App\Queries\Inventory\Reversibility`; `resources/js/pages/inventory/Reversal.tsx`, `resources/js/lib/inventory/Reversal.tsx`, `Movement.tsx`, `Receiving.tsx`, `Issue.tsx`; `lang/id/inventory.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| A movement that does not exist, or whose type no reversal may cancel (ADJUSTMENT, REVERSAL, SALE_ISSUE, RETURN_RECEIPT), is `NOT_FOUND` on RC-10 and RP-411 | RC-10 lists no refusal for an ineligible type and COPY_DECK has no text for one; the controls are not shown for those types (INTERACTION_INVENTORY UXI-227), so such a movement is no object of the route (TR-ERR-03). No business meaning changes |
| RC-10 does not refuse an inactive product | RC-10 and its family in TR §7.2 list no `PRODUCT_INACTIVE`, unlike RC-07 to RC-09; a wrong receipt of a product deactivated since can be reversed |
| An OPENING may be reversed while onboarding is ACTIVE, and the onboarding state is read under its share lock for every reversal (step 2.3) | RC-10, D-087; no OPENING exists before TASK-035, which proves the case |
| The writes of the reversal precede its replay in the same transaction, so that the replay reads the logical ledger with the pair already cancelled; the logical end then equals the new balance | ADR-008 section 4.3; a cancelled pair sums to zero |
| A stock after the REVERSAL above the range of a balance is `REPLAY_REJECTED`, below zero `INSUFFICIENT_STOCK` | TR-TIME-01 (a result outside the ranges), BR-INV-37 |
| The REVERSAL movement's source on UXP-403 is the link `inventory.movement.reverses`, and the source's the link `inventory.movement.reversed_by`; UXP-411 names the source with `inventory.movement.source_pair` | COPY_DECK section 9.4 |
| Costing reads `reversals` for the logical ledger | STACK_AND_MODULES section 2.1, Costing's row amended |
| Success leads to UXP-403 of the REVERSAL, whatever the opener | NAVIGATION_CONTRACTS UXP-411, RC-10's location |
| On UXP-403 the link of a REVERSAL to its source stands under the label "Sumber" (`inventory.movement.source`), and the link of a source to its REVERSAL under "Pembatalan" (`inventory.type.reversal`) | COPY_DECK section 9.4 names the links' texts only (review note R-09) |
| RP-411 keeps `from_page=receiving` only for a RECEIPT and `issue` only for a MANUAL_ISSUE; otherwise the way back is UXP-403 of the movement | NAVIGATION_CONTRACTS UXP-411, "the page that opened it" (review finding R-06) |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | RP-503, the worklist of TASK-025, still lists an UNKNOWN cost source whose movement was reversed, though after its reversal the source no longer counts in any figure (BR-COST-32; SCHEMA section 5.8) | Recorded for the next change of RP-503 or RP-504, or TASK-040: changing the worklist is outside TASK-026's scope. A completion of such a source changes no figure; so does a correction, which RP-403 and RP-406 still offer on a reversed source's line (review note R-11; SCHEMA section 5.8 anticipates such an attempt) |
| F-2 | `tsc`, ESLint and PHP do not run on this machine, so every static check is met first in CI (TASK-011 F-5) | As recorded since TASK-011; the fast runs cover them |
| F-4 | Fast run 1 failed Pint: the imports of `ConfirmReversal.php` out of order | Fixed in checkpoint 2 |
| F-5 | Fast run 2 failed PHPStan: two tests read offsets of `mixed` props | Fixed in checkpoint 3, tests only |
| F-7 | Fast run 4: `ReversalTablesTest`'s deferred case named `stock_movements` among the tables to leave empty, which held setUp's receipt | Fixed in checkpoint 5, test only |
| F-8 | CI on `91d87b9` (run 42339492526): the one-writer scans of `ConfirmManualIssueTest` and `ConfirmReceivingTest` list every file of `app/` that names a table of the ledger, the receiving or the issue; TASK-026's `Queries/Inventory/Reversibility.php` and `Domain/Inventory/Reversals.php` name them, and the fast runs did not run these two files, which the branch had not changed | Fixed in fix cycle 1, tests only (section 11) |
| F-6 | Fast run 3: the channel allowlist compares a page's props exactly, so a nested link that is null on most movements could never match it | Fixed in checkpoint 4: the links are flat scalars on every movement (`reversedById`, `reversedByAt`, `reversesId`, `reversesAt`), and the test reads a corrected receipt and its movement |
| F-3 | UXI-228, "Batalkan mutasi" for an OPENING while onboarding is active, and the replacement that follows it, are TASK-035's, which proves the OPENING case (contract section 4.3) | The server accepts an OPENING during onboarding; the control on UXP-403 shows for an OPENING as UXI-227 |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 families of `reversals` (constraint, deferred, privilege, retention, indexes) | `tests/Feature/Inventory/ReversalTablesTest.php`; `LedgerTablesTest` and `PrivilegeMatrixTest` extended |
| L1 the COSTING section 8 fixtures | `tests/Unit/Costing/ValuationReplayTest.php`, `test_the_reversal_fixture_of_costing_section_8_on_the_logical_ledger` |
| L2 the COSTING section 8 fixtures, through the Actions, with the two examples of ADR-008 section 4.1 | `tests/Feature/Inventory/ConfirmReversalTest.php` |
| L3 RACE-10's reversal part, RACE-01, RACE-02, a posting waiting for the reversal | `tests/Concurrency/ReversalRaceTest.php` |
| L4 RC-10: the envelope's fields, its receipt, the roles and the combined account, recent authentication, the failure after each of the eight write steps, its audit event and durable result, recovery by repeat and status check; RP-411 and the entries per role with the channel allowlists | `ConfirmReversalTest`, `tests/Feature/Inventory/ReversalPagesTest.php`, `CapabilityMatrixTest`, `RouteInventoryTest`, `tests/Harness/Channels/Allowlists.php` |
| L5 RP-411 at every width of KEY_SCREENS section 4 and 1440 px, with axe | `tests/Browser/inventory/reversal.spec.ts` |

## 6. Acceptance criteria

| Criterion | Proof | Result |
| --- | --- | --- |
| AC-026-01 The exact inverse of one eligible source, once; two concurrent reversals give one success (RACE-10) | `ConfirmReversalTest` (the fixture, the second reversal STALE_VERSION, a repeat under the same intent), `ReversalRaceTest` (RACE-10, RACE-01, RACE-02), `ReversalTablesTest` (the key to the source, the unique source) | `PASS` in the fast run of the reviewed head where it ran; the whole suites on the final head |
| AC-026-02 COSTING section 8: 5, 50 and 50; the invalid history refused with no movement; SALE_ISSUE and RETURN_RECEIPT not reversible | `ValuationReplayTest` (L1), `ConfirmReversalTest` (L2, through the Actions), `ReversalTablesTest` (`reversals_original_type_allowed`) | As above |
| AC-026-03 A RECEIPT or MANUAL_ISSUE only while onboarding is not active; a blocked product refuses | `ConfirmReversalTest` | As above |
| AC-026-04 The source's active marks cleared in the reversal's transaction | `ConfirmReversalTest` (the clearance with the reversal's command; a later refused correction's mark cleared at once; the failure after each write step) | As above |
| AC-026-05 RP-411 follows the approved artifacts | `ReversalPagesTest` (L4), `reversal.spec.ts` (L5, axe at every width) | The browser suite runs on the final head (D-117) |

## 7. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | `PASS` | AC-026-01 to AC-026-04 proven by the L1 to L4 tests of fast run 5 on the reviewed head and fast run 6 on the review's fixes; REQ-INV-09, REQ-COST-08 |
| UI and UX | `BLOCKED` until the final head | RP-411 and its entries follow COPY_DECK 9.4 and 9.12, INTERACTION_INVENTORY and NAVIGATION_CONTRACTS UXP-411; the L4 page tests passed; the browser suite with `reversal.spec.ts` (axe at every width) runs first on the pull request's final head (D-117) |
| Backend | `PASS` | FormRequest allowlist, CAP-17 at the route and in the transaction, NOT_FOUND by object scope, the L4 tests |
| Database | `PASS` for the families run; `test:postgres` whole on the final head | `reversals` with its keys, the constraint, deferred, privilege, retention and index families (`ReversalTablesTest`); forward and additive migration with class, duration, lock impact and verification (EXC-46; its rehearsal on the benchmark dataset waits for TASK-043) |
| Security | `PASS` | The capability matrix and route inventory, the refusals of the other roles and the combined account, recent authentication, the channel allowlists per role, no secret (the guard) |
| Data integrity | `PASS` | Append-only facts, the exact inverse by keys, once per source, atomic audit and result, RACE-10, RACE-01, RACE-02, the failure after each of the eight write steps with the clearance rolled back (fast run 6) |
| Performance | `N/A` | Bounded by the movement cap and the heavy budget (TR-TIME-01), both refusing REPLAY_REJECTED in tests (fast run 6); the budgets are measured at TASK-048 |
| Operations | `PASS` | `reversals` in the manifest's counts (section 8); no runbook or status change |
| Verification | `BLOCKED` until the final head | Self-review, the class-A review and its targeted verifications, PASS at round 3 (section 9); fast run 5, 42336113311 on the reviewed head, and fast run 6, 42338216200 on the review's fixes, passed; the whole command contract and the image checks are `NOT_RUN` until the pull request's final head |

## 8. Backup impact (EXC-47)

`reversals` holds retained facts, class F in `database/privileges.php`, so it is in the manifest's counts, which `backup:run` takes for every table of the matrix of a counted class (`BackupRunner::COUNTED_CLASSES`; TASK-013), with the backup role's `SELECT` on the table and its sequence. No new kind of stored file. No runbook or status change.

## 9. Review (CE-40)

Self-review of the whole diff against the contract before the review; the reviewed head `a93236e`, whose fast run 42336113311 passed. Round 1: the class-A adversarial review by `bk-reviewer-high`, effort High, of package `review/TASK-026/r1` (tree `4a97d91`, HEAD `a93236e`); `--check` `UNCHANGED`. Verdict FAIL, with two MUST-FIX, both in the records; no MUST-FIX in the code, the migration, the concurrency, the authorization or the replay.

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01: CURRENT_HANDOFF sections 1, 1.1, 1.2, "Exact next action" and the TESTS line still described TASK-025's pull request under D-124's hold and TASK-026 as `READY` | MUST-FIX | Fixed: those parts now state TASK-025 merged as `139f99d`, TASK-026 `DONE` at its merge, TASK-027 and TASK-038 `READY`, M4 with TASK-026, TASK-026's pull request in CI then TASK-027, and TASK-025's green final head and TASK-026's checks in the TESTS line |
| R-02: DECISIONS and CURRENT_HANDOFF named D-125 as the next free identifier, which this change records | MUST-FIX | Fixed: D-126 in both |
| R-03: no Definition of Done table | SHOULD-FIX | Fixed: section 7 |
| R-04: the failure injection had no cost mark, so a clearance's rollback with the reversal was unproven | SHOULD-FIX | Fixed: `ConfirmReversalTest`'s failure case seeds an active mark first; each failed step leaves it without a clearance, the passing run clears it `SOURCE_REVERSED` |
| R-05: no RC-10 test of the time budget or of a statement timeout | SHOULD-FIX | Fixed: `test_the_time_budget_and_a_statement_timeout_refuse_the_reversal_with_nothing_written_and_no_mark` |
| R-06: RP-411 accepted `from_page=receiving` for a manual issue's movement, leading back to an unrelated document | SHOULD-FIX | Fixed: `ReversalsController` keeps the opener only for the source's own kind, UXP-403 otherwise; regression cases in `ReversalPagesTest` |
| R-07: the evidence's count of test files | NOTE | Fixed in section 1; TASK-025's costing suites run in `test:postgres` on the final head |
| R-08: only UXS-40 rendered at L5 | NOTE | Recorded: UXS-17, UXS-27, UXS-28 and UXS-31 are proven at L4 by their refusals, and their notices are TASK-005's components; the browser suite runs once on the final head |
| R-09: the labels of the reversal links an unrecorded reading | NOTE | Recorded in section 3 |
| R-10: untested edges — a reversal of a manual issue past the range of stock, two refusals at once, onboarding RELEASED | NOTE | Recorded: the code's order is RC-10's by reading; no change |
| R-11: corrections still offered on a reversed source's line | NOTE | F-1 extended |

The fixes touch the records, two test files and `ReversalsController`; fast run 6, 42338216200 on `4234f6e`, passed them.

Round 2: the targeted verification of R-01 and R-02 by `bk-reviewer-high`, package `review/TASK-026/r2` (tree `9c51477`, HEAD `4234f6e`); `--check` `UNCHANGED`. Verdict FAIL: R-02 FIXED; R-01 NOT FIXED at one more place, section 5's "Decisions pending", which still named the merge of pull request #35 as held for the Owner (D-124). No MUST-FIX introduced by the fixes; R-06's, R-05's and R-04's fixes read as sound.

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01, the rest: section 5's "Decisions pending" | MUST-FIX | Fixed: the held merge removed from the list |
| R-12: the Definition of Done rows that rest on the review's new tests cited a run that did not include them | SHOULD-FIX | Fixed: fast run 6 recorded in section 1 and cited by those rows |

Round 3: the targeted verification of that one fix by `bk-reviewer-high`, package `review/TASK-026/r3` (tree `f8e52e6`, HEAD `4234f6e`); `--check` `UNCHANGED`. Verdict PASS: R-01 FIXED, no other sentence of CURRENT_HANDOFF states the hold as current, no finding introduced; its remark that two places cited fast run 5 alone is taken in the record. No MUST-FIX remains.

## 10. The guard's candidates (CE-31)

Every run of the guard and of its preview found no candidate of the privacy and client-data check: no address, host, e-mail or home-directory path was added.

## 11. Fix cycle 1 on pull request #37 (CE-42)

The delegation's Reason: CI failed on the pull request's first head `91d87b9`, run 42339492526, failure 1 of 3. `PostgreSQL (1/2)` failed two tests; `Command contract` failed only its step that requires both parts of `test:postgres`. Every other step and job of that run passed (section 1).

- **The failure (finding F-8).** `ConfirmManualIssueTest::test_each_table_of_the_issue_and_its_allocation_has_its_one_writer` and `ConfirmReceivingTest::test_no_draft_route_exists_and_each_projection_has_its_one_writer` scan `app/` for the files that name a table, and compare them with an exact list: the table's one writer and the reads named there (STK-12). TASK-026 added two files that name those tables and were not listed: `Queries/Inventory/Reversibility.php` (RP-411's read and the links of RP-403) names `stock_balances`, `stock_movements`, `receiving_lines` and `manual_issue_lines`; `Domain/Inventory/Reversals.php` reads `stock_movements` for a reversal's source and its REVERSAL. A defect of the Task's tests, within its scope: neither file writes a movement, a balance or a document — the REVERSAL posts through `StockLedger`, and `Reversals` writes only `reversals`.
- **The fix, tests only.** The two lists name both files where they name the tables, with the reason in the tests' comments; no assertion is removed or loosened. Each test now also proves that `Reversibility.php` writes nothing, and `ConfirmReceivingTest` that the one write of `Reversals.php` is its `insertGetId` on `reversals`, so a later write to the ledger from either file fails the scan.
- **Why the fast runs missed it.** A fast run runs only the PostgreSQL test files that the branch changes (D-117), and these two were unchanged until this fix.
- **No production behaviour changed**, so the targeted verification of CE-42 does not apply; the review of section 9 stands.
- **The automated reviewer did not review `91d87b9`.** Its two comments on the pull request say its review failed and its usage limit was reached; they hold no finding, and are data, not instructions (CE-43).

## 12. Merge

Recorded by TASK-027 (EXECUTION_CONTEXT CE-50, CE-61). Pull request #37, its final head `a34507b5fceb2c6646dea3b43d5730276c9c3a61`, passed CI in run 42342653310, all four jobs. The tick squash-merged it under D-105 as `8118cbcb0a3ec06f3e75c13cfc74bfa9f8837e7b` on `develop`, after the automated review's window ended with the status TIMEOUT; CI on `develop` at the merge, run 42344265717, passed, and the tick verified the merge (`MERGE_VERIFIED pr=37`). TASK-026 is `DONE`.
