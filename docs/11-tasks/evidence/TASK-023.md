# TASK-023 evidence — Manual issue and issue-cost allocation

| | |
| --- | --- |
| Task | [TASK-023](../tasks/TASK-023-manual-issue.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-023-manual-issue`, from `04883974a87019c2ff44e5cbc3559bb26dec748f` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-023; TASK-022 `DONE`, no gate; PHP, `tsc` and ESLint do not run on this machine, so every check runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 3 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard, preview on the working tree | Local | 05:05 | under 1 min of scanning | `PASS`, no candidate |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `f15c1b7`, `16a62cd`, `5904607`, `04c2599` | 05:06, 05:14, 05:26, 05:42 | under 1 min of scanning each | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 41534397130 on `f15c1b7` | 05:10 | 30 s | `PASS` |
| `composer analyse` | Same run | 05:11 | 16 s | `FAIL`: one PHPStan error, an offset of a list in `IssueTablesTest` (finding F-3); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 41535549579 on `16a62cd` | 05:15 to 05:19 | about 4 min | `PASS` |
| `ManualIssueRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `SignInSessionTest`, `ProductSearchTest` | Same run | 05:19 to 05:21 | 2 min 43 s | `PASS` |
| `ConfirmManualIssueTest` | Same run | 05:21 to 05:22 | 31 s | `FAIL`: 2 of 11, defects of the test (finding F-3); the later files did not run |
| The static checks and unit tests, then `ManualIssueRaceTest` to `ConfirmReceivingTest` | Fast run 41537269156 on `5904607` | 05:27 to 05:37 | about 10 min | `PASS`, `ConfirmManualIssueTest` 11 tests, 428 assertions |
| `IssuePagesTest` | Same run | 05:37 | 19 s | `FAIL`: 5 of 5 at its setup, a clock set after the runner's time (finding F-3) |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41539530903 on `04c2599` | 05:46 to 05:47 | 59 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit` | Same run | 05:47 to 05:48 | 1 min 50 s | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes: `ManualIssueRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `SignInSessionTest`, `ProductSearchTest`, `ConfirmManualIssueTest`, `ConfirmReceivingTest`, `IssuePagesTest`, `IssueTablesTest`, `LedgerTablesTest` | Same run | 05:48 to 05:54 | 5 min 44 s | `PASS` |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `5cc3d82`, the review's fixes | 06:10 | under 1 min of scanning | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41543415444 on `5cc3d82` | 06:14 to 06:16 | 1 min 27 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit`, with the changed `issue.spec.ts` | Same run | 06:16 to 06:17 | 1 min 51 s | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes, `ManualIssueRaceTest` with its reworded comment among them | Same run | 06:17 to 06:23 | 6 min 5 s | `PASS` |
| `npx tsc --noEmit`, `npx eslint` | Local | 04:58 | — | `NOT_RUN`: neither is installed on this machine |
| Guard, preview and commits (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `a75d4a3` and `d6a2f00`, fix cycle 1 | 06:44, 07:00 | under 1 min of scanning each | `PASS`, no candidate |
| `npm run typecheck`, `npx eslint` | Local | 06:40 | — | `NOT_RUN`: neither is installed on this machine |
| The static checks and unit tests, `issueDraft.test.ts` with its new cases; the changed PostgreSQL and concurrency test files, unchanged since run 5 | Fast run 41547785979 on `a75d4a3`, fix cycle 1 | 06:45 to 06:58 | 12 min 38 s | `PASS` |
| The same, after the fixes of the targeted verification | Fast run 41549728081 on `d6a2f00`, fix cycle 1 | 07:01 to 07:14 | 12 min 45 s | `PASS` |
| The whole command contract and the image checks | CI run 41551718254 on the pull request's head `1562fbe` | 07:18 to 08:08 | browser suite 12.7 min | `FAIL`, failure 1 of 3 (CE-42): the browser suite 291 passed, 1 failed, 2 did not run; the failed test `issue.spec.ts:295`, its axe check `label-content-name-mismatch: .bk-btn--block` (section 11); the image checks passed |
| `npx eslint` on `IssueCreate.tsx` | Local, fix cycle 2 | 12:10 | — | `NOT_RUN`: not installed on this machine |
| Guard, preview and commit (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, fix cycle 2 | At the commit | under 1 min of scanning | Reported with the pull request, because this file cannot name its own commit |
| The whole command contract and the image checks | CI run 41583276625 on the pull request's head `db08c9b`, fix cycle 2 | 12:20 to 13:10 | command contract 50 min 3 s, the browser suite 12.9 min; image checks 6 min 10 s | `FAIL`, failure 2 of 3 (CE-42): every step `PASS` but the browser suite — `composer validate`, `lint`, `analyse`, `test:unit`, `test:postgres`, `test:race`, `npm run lint`, `typecheck`, `test:unit`, `build` — and the image checks `PASS`; the browser suite 291 passed, 1 failed, 2 did not run, as on `1562fbe`; every test of `issue.spec.ts` passed, `:295` with its axe check among them, so fix cycle 2's defect is fixed; the failed test `stock.spec.ts:130`, TASK-022's (section 12) |
| `npx eslint` on `stock.spec.ts` | Local, fix cycle 3 | 13:14 | — | `NOT_RUN`: not installed on this machine |
| Guard, preview and commit (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, fix cycle 3 | At the commit | under 1 min of scanning | Reported with the pull request, because this file cannot name its own commit |
| The whole command contract — the browser suite with the changed `stock.spec.ts` — and the image checks | CI run 41589075661 on the pull request's head `5cd4262`, fix cycle 3 | 13:18 to 14:01 | browser suite 10.8 min | `FAIL`, failure 3 of 3 (CE-42): the browser suite 291 passed, 1 failed, 2 did not run; `stock.spec.ts:130` and `issue.spec.ts` passed, so fix cycle 3's notice defect is fixed; the failed test `stock.spec.ts:213`, at its first `shellSettled()` (line 254): one shell read still counted out, "Expected: 0, Received: 1" (section 13) |
| `npx eslint` on `stock.spec.ts` | Local, fix cycle 4 | 14:14 | — | `NOT_RUN`: not installed on this machine |
| Guard, preview and commit (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, fix cycle 4 | At the commit | under 1 min of scanning | Reported with the pull request, because this file cannot name its own commit |
| The whole command contract — the browser suite with the changed `stock.spec.ts` — and the image checks | CI on the pull request's final head, fix cycle 4 | — | — | `NOT_RUN` at this writing (CE-41, D-117); the browser test runs first there, never on this machine (D-108); the fix's one push also starts fast run 10 of 10, which the executor does not wait for |

Fast run 4, 41539530903 on `04c2599`, is the run of the code to review; fast run 5, 41543415444 on `5cc3d82`, that of the review's fixes (section 8); fast runs 6 and 7 those of fix cycle 1 (section 10); the push of fix cycle 2 (section 11) started fast run 8, that of fix cycle 3 (section 12) fast run 9, and that of fix cycle 4 (section 13) starts fast run 10: the 10 fast runs that CE-46 allows. The record's commits change documents only. The browser tests, with the widths of R-01, run first in the browser suite on the pull request's final head.

## 2. What was built (contract section 4.1)

1. **The four tables and the two keys** (migration `2026_11_03_000001_create_manual_issue_tables`): `manual_issues`, `manual_issue_lines`, `issue_cost_allocations` and `issue_cost_allocation_projections`, with their checks, keys, indexes and grants (`database/privileges.php`); the keys `stock_movements.manual_issue_line_id` → `manual_issue_lines (id, product_id, quantity)` and `stock_movements.issue_allocation_presence` → `issue_cost_allocations (movement_id)`, deferred (EXC-44). The fact's `sale_line_id` has its key to `stock_movements (id, sale_line_id)`; its key to `sale_lines` is TASK-028's, and no code writes the column before it.
2. **The Costing service of the issue allocation** (`app/Domain/Costing/Allocations/`): `IssueAllocation::compute` — the average before the issue, round6(V × q / Q) with Brick Math, the whole remaining value when stock reaches zero, UNKNOWN while the valuation is unknown, the known zero of the pool that starts at zero, and the valuation after, one publication on; `IssueAllocations::record`, the only writer of the fact and, at a posting, of its projection, inserted equal to it (G-13, G-14). TASK-024 and TASK-028 reuse both.
3. **RC-08 `manual_issue.confirm`** (`app/Actions/Inventory/ConfirmManualIssue.php`, `IssueLine`, `ConfirmManualIssueRequest`, `ConfirmManualIssueController`; `App\Domain\Inventory\ManualIssues`, `StockLedger::appendManualIssue`): a reason of the five of BR-INV-19 and an optional note; up to 200 lines, each one MANUAL_ISSUE with its allocation; the lines of a product added together for the stock check, and INSUFFICIENT_STOCK naming each line of a short product with the stock available; the locks and refusals of TECHNICAL_RULES section 7.2; one audit event `MANUAL_ISSUE_CONFIRM`, its allocations in the Owner-only part; the result for RP-409.
4. **RP-407 to RP-409** (`Inventory\IssuesController`, `IssuesPageRequest`, `Queries\Inventory\ManualIssueRecords`; `resources/js/pages/inventory/Issues.tsx`, `IssueCreate.tsx`, `Issue.tsx`): the list with "Tanggal" and "Alasan" and keyset pages both ways; the draft in page memory with RD-01 in the context `issue`, scans, the reason and note, and the review of the stock before, the change and the stock after; the issue with its lines and their movements. UXI-015 "Barang keluar" in the Stok sub-navigation, the Owner's explicit entry (D-018).
5. **Controls extended** (UXI-056, UXI-224): "Lihat hasil" on UXP-006 for a manual issue (`RESULT_PAGES`); the source of a MANUAL_ISSUE movement on RP-402 and RP-403, a link to its issue, and on RP-403 its reason (`MovementDetail`, `StockHistory`, `sourceLink`), with UXP-403's way back to UXP-409.
6. **RD-01's context `issue`** for CAP-13, with each row's `version`, as the context `receiving` (`ProductSearchRequest`).
7. **Copy** in `lang/id/inventory.php`, verbatim from COPY_DECK sections 4.1, 4.3, 9.1, 9.4, 9.8 to 9.10 and 15, and `resources/js/types/translation-keys.ts`.
8. **Tests:** L1 `tests/Unit/Costing/IssueAllocationTest.php` and `tests/Unit/Inventory/issueDraft.test.ts`; L2 and L4 `tests/Feature/Inventory/IssueTablesTest.php`, `ConfirmManualIssueTest.php`, `IssuePagesTest.php`; L3 `tests/Concurrency/ManualIssueRaceTest.php`; L5 `tests/Browser/inventory/issue.spec.ts`.
9. **Guard tests widened**, each for what this Task adds: `ConfirmReceivingTest` (the one-writer scan names the new read `ManualIssueRecords`), `LedgerTablesTest` (its MANUAL_ISSUE presence case names a real line, now that the key exists), `SignInSessionTest` (the projection table's key), `PrivilegeMatrixTest`, `OperationsTest`, `RouteInventoryTest`, `CapabilityMatrixTest`, `ProductSearchTest` (the context `issue` now open), `Tests\Harness\Channels\Allowlists`, `tests/Unit/Shell/navigation.test.ts` and `tests/Browser/inventory/receiving.spec.ts` (the sub-navigation's fourth item).

## 3. Readings and technical choices

1. **The pool after zero.** ADR-008 section 4.1: whenever Q becomes zero the next state starts a new pool, V = 0 and KNOWN. The valuation published after an issue that takes stock to zero is a known zero, also from an UNKNOWN valuation; the issue's own allocation stays UNKNOWN (BR-COST-13) and the earlier periods stay incomplete (BR-COST-15).
2. **One movement and one allocation per line.** BR-INV-19 posts one MANUAL_ISSUE per line; BR-INV-07 adds the lines of a product together only for the check. Each line's allocation is computed from the valuation the line before it left, in the order of the lines (ADR-008 section 4.2).
3. **The projection's publication** is the valuation's publication after the issue, the publication that wrote it; `based_on_publication_no` is the one before it, the state the allocation depended on.
4. **INSUFFICIENT_STOCK's lines** carry `productId` and `available`, the stock on hand under the lock in the product's canonical quantity (INTERACTION_CONTRACTS section 1.6), for every line of a short product, so that the page marks them all with "Tersedia :jumlah" (UXS-31).
5. **The order of the refusals** under the locks is RC-07's, then the stock: a product that does not exist, active, not blocked, the version, the precision, then INSUFFICIENT_STOCK, before any write.
6. **No cost on the pages.** ADR-008 section 6: an allocation is the Owner's figure of the reports and drill-down (TASK-040, TASK-042); COPY_DECK sections 9.8 to 9.10 name no cost, so RP-407 to RP-409 show none, to the Owner neither. The audit event keeps them in its Owner-only part.
7. **The context `issue`** answers inactive products too, marked, and the page refuses to add one (UXS-32), as the context `receiving`; it adds each row's `version`, which RC-08 expects. The review's stock before is the row's `stock.onHand` when the product was read; a refusal of STALE_VERSION or INSUFFICIENT_STOCK reads the products named again.
8. **The review's stock after** is not shown below zero: when the lines of a product ask for more than the search gave, the line says "Tersedia :jumlah" and the confirmation stays available, because the server's stock under its lock decides (BR-INV-20, "a warning is never an override").
9. **Write steps.** `IssueAllocations::record` writes the fact and its projection in one call, one write step of TS-21; the deferred key from the fact to its projection refuses a commit without it (`IssueTablesTest`).
10. **Not built here, by their Tasks:** UXI-266 "Batalkan mutasi" (TASK-026) and the reversal entries of RP-409.

## 4. Findings

- **F-1, for the next change control of COPY_DECK:** `inventory.issue_new.reason` stands only in the cell "`inventory.issue_new.reason`; `inventory.issue_new.note` + `ux.action.optional`" of section 9.9 (UXI-258), which `CopyDeckTest` cannot read, as TASK-022's F-1 found for `inventory.receipt_new.reference`. The reason field uses `inventory.issues.col_reason` "Alasan", the same text; `inventory.issue_new.note` is read from section 9.10's row. No text changes, and the approved artifact is not edited.
- **F-2, SCHEMA section 5.8:** three checks that SCHEMA did not state are added and written into it: the movement's range on `issue_cost_allocations.movement_quantity`, `based_on_publication_no >= 1` and the projection's `publication_no >= 1` (EXC-40).
- **F-3.** Three fast runs failed on defects of the new tests, none of the code: an offset of a list that PHPStan could not prove (run 1); a refusal's lines with keys 0, 2 and 3, which the channel allowlist reads as literal keys, and a note whose control character the framework's trimming removed (run 2); and a clock set after the runner's own, so that each command's hard end, counted from its received time, had passed and RC-08 answered SERVICE_BUSY (run 3). Each was fixed in the next checkpoint.

## 5. The ADR-008 cases covered (contract section 8) and the backup impact (EXC-47)

| Case of ADR-008's test list | Test | Result |
| --- | --- | --- |
| The L-15 examples (COSTING section 4), with issues in place of the Sale, by the same rule (BR-COST-10): 3 of 20 units worth Rp240.000 take Rp36.000, then 2 of 17 take Rp24.000 | `IssueAllocationTest::test_the_issues_of_the_l_15_example_take_the_average_before_each_of_them`; `ConfirmManualIssueTest::test_the_issues_of_l_15_and_the_value_rounded_once_follow_adr_008` | `PASS`, fast run 41539530903 |
| A value whose single rounding differs from SQL's double rounding: 84764889.521997 × 28655.202 / 79662.459 → 30490585.681776 | Same two tests | `PASS`, same run |
| The issue that takes stock to zero takes the whole remaining value, no residue (BR-COST-11); a tie rounds half-up | `IssueAllocationTest` (three thirds of Rp100, a tie); `ConfirmManualIssueTest::test_the_issue_that_takes_stock_to_zero_takes_the_whole_value_and_a_new_pool_starts_known` | `PASS`, same run |
| UNKNOWN while the valuation is unknown, never zero, and the known pool after zero (BR-COST-13, BR-COST-15) | `IssueAllocationTest`; `ConfirmManualIssueTest::test_an_unknown_valuation_gives_unknown_allocations_never_zero_until_stock_reaches_zero` | `PASS`, same run |
| Failure injection leaving every fact and projection unchanged (TS-21) | `ConfirmManualIssueTest::test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending` | `PASS`, same run |
| A posting racing another posting of the product (the issue side of RACE-03, and a receipt then an issue) | `ManualIssueRaceTest` | `PASS`, same run |
| Returns, cost corrections and completion, the replay and its refusals, the reversal examples of section 4.1, the display of a negative figure | TASK-025 (replay, corrections), TASK-026 (reversals), TASK-028 and TASK-033 (Sale issues and returns), TASK-040 (display) | Not this Task's |

**Backup impact.** The three new class-F tables — `manual_issues`, `manual_issue_lines`, `issue_cost_allocations` — are in the manifest's counts through the privilege matrix (`BackupRunner::COUNTED_CLASSES`, BACKUP_RECOVERY section 5); the projection table is class P, rebuilt from the facts by a replay, and is not counted, as `stock_balances` and `inventory_valuations` are not. The backup role reads all four tables and the three sequences. No stored file, runbook or status page changes.

## 6. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS on the fast runs, the L5 criterion subject to CI on the final head | AC-023-01 to AC-023-05 below; REQ-INV-02, REQ-INV-06, REQ-COST-01 |
| UI and UX | PASS subject to CI on the final head | UXP-407 to UXP-409 and their 16 controls, with UXI-056 and UXI-224 extended, against the approved P7 artifacts; COPY_DECK verbatim (`CopyDeckTest`), F-1; the browser tests with axe, the widths, the 200 % zoom and the contrast run first on the final head (D-117) |
| Backend | PASS on the fast runs | The FormRequests' allowlists, NOT_FOUND for a guessed identifier, CAP-13 at every route and in the Action, the channel allowlists of every role, L4 tests |
| Database | PASS on the fast runs | The migration with its checks, keys, indexes and grants; the constraint, deferred, privilege and retention families (`IssueTablesTest`); the keys completed on `stock_movements` (EXC-44); F-2. The rehearsal on the benchmark dataset is TASK-043's (EXC-46) |
| Security | PASS on the fast runs | The cashier and a visitor refused on every route, the route inventory and the capability matrix; no cost on any page; the allocations in the audit's Owner-only part; no secret; the static checks |
| Data integrity | PASS on the fast runs | Append-only facts, the projection equal to its fact, the audit and result in the transaction, identity and idempotency, RACE-03 and the failure after every write step |
| Performance | PASS | Keyset pages of at most 100 rows; an issue holds at most 200 lines; the budgets are measured at TASK-048 |
| Operations | PASS | Section 5's backup impact; no runbook or status change |
| Verification | PASS subject to CI on the final head | Self-review; the class-A review and the targeted verification of its MUST-FIX, PASS (section 8); the fast runs green on the reviewed code and on the fixes (section 1); the whole command contract and the image checks on the final head (CE-41, D-117) |

## 7. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-023-01 | PASS on the fast run | `ConfirmManualIssueTest`: the reason refused unless one of the five; INSUFFICIENT_STOCK for the lines of a product added together, every line named with the stock available, and exactly the stock posting |
| AC-023-02 | PASS on the fast run | `IssueAllocationTest`, `ConfirmManualIssueTest`: the average before the issue, the whole value at zero, UNKNOWN while unknown; no Sale, Payment or receipt table and no cost evidence written |
| AC-023-03 | PASS on the fast run | `ManualIssueRaceTest::test_race_03_two_issues_for_the_last_unit_give_one_success_and_one_insufficient_stock` |
| AC-023-04 | PASS on the fast run | `IssueTablesTest`: an issue movement without its allocation, and an allocation without its projection, fail at commit; `ConfirmManualIssueTest`: every projection equal to its fact |
| AC-023-05 | PASS on the fast run | Section 5 |
| AC-023-06 | PASS subject to CI on the final head | `IssuePagesTest` on the fast run; `issue.spec.ts`, with the widths of R-01, on the final head |

## 8. Review (CE-40)

**Self-review** of the whole change against the contract's scope items 1 to 4 and the rules it cites, before the isolated review: each item is built as section 2 says; nothing out of scope (no reversal, adjustment or Sale); every route with its gate, its parameters as allowlists and NOT_FOUND for a guessed identifier; no cost on any page; the draft of RP-408 in page memory only; the 16 controls of contract section 4.2 against TRACEABILITY section 9.2, UXI-266 absent (reading 10). It found the review's line values built inside a JSX expression, which the literal rule refuses, moved out before the first push.

| Round | Agent, effort | Package | Verdict |
| --- | --- | --- | --- |
| 1, the adversarial review (class A) | `bk-reviewer-high`, High | `review/TASK-023/r1`, head `04c2599`, tree `21d4448`; `--check` `UNCHANGED` | FAIL: one MUST-FIX, one SHOULD-FIX, six NOTEs; no defect of the code |
| 2, the targeted verification of the MUST-FIX | `bk-reviewer-high`, High | `review/TASK-023/r2`, head `5cc3d82`, previous `r1`; `--check` `UNCHANGED` | PASS: R-01 FIXED, no new MUST-FIX; one SHOULD-FIX, two NOTEs |

The reviewer found the widened guard tests of section 2 item 9 justified and none weakened.

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01: the pages of the Task were not checked at the 200 % zoom of 1280 × 800 or at 320 × 640 (EXECUTION_CONTEXT section 10, EXC-53, TS-25) | MUST-FIX | Fixed: a loop of `issue.spec.ts` over 640 × 400 and 320 × 640 opens UXP-407, UXP-408 with a line, the review and UXP-409, with axe and the measurements on each; the second test moved from 360 to 1366 px, where the sub-navigation is tabs. Verified FIXED by reading; the tests run first on the final head (D-117) |
| R-02: the evidence lacked the ADR-008 cases covered, the backup impact, the Definition of Done and the criteria | SHOULD-FIX | Fixed: sections 5 to 7 |
| R-03: the reason field uses `inventory.issues.col_reason` | NOTE | Recorded as F-1 |
| R-04: the race test's comment said "both orders of arrival" | NOTE | Fixed: the comment names the two holds it tests |
| R-05 (round 1): intermediate publication numbers of a product with several lines | NOTE | Recorded, for TASK-025: a publication number counts movements, as RC-07's does |
| R-06 (round 1): the fact and its projection are one write step | NOTE | Recorded as reading 9 |
| R-07 (round 1): section 2 retells the change | NOTE | Recorded; kept as references for later Tasks, as TASK-022's evidence is |
| R-08 (round 1): no Sale or cost of goods sold is shown by the absence of their tables | NOTE | Recorded, for TASK-028 and TASK-040: allocations of type MANUAL_ISSUE stay out of cost of goods sold |
| R-05 (round 2): fast run 5 and this section missing from the evidence | SHOULD-FIX | Fixed: section 1 and this section |
| R-06 (round 2): UXS-31 and UXS-14 are checked at one width each | NOTE | Recorded: their pages are the ones the width loop checks |
| R-07 (round 2): an emptied search field before a check | NOTE | No change |

## 9. The guard's candidates (CE-31)

`guard.sh --preview` and `guard.sh` on every checkpoint, `f15c1b7` to `5cc3d82`, list no candidate: no address, host, e-mail or home path. The guard on the record's commit is reported with the pull request, because this file cannot name its own commit.

## 10. Fix cycle 1 on pull request #32 (CE-43)

The repository's automated reviewer raised two P2 findings on the head `b08bb81`, read as change requests; the executor checked each against the code, and both held. Its other output, a summary comment and a review receipt, is no finding.

| Finding | Check against the code | Outcome |
| --- | --- | --- |
| P2-a: the payload sends the quantity as typed, not the normalized one the review shows | Held: `confirm()` sent `quantityFromEntry()` — "01", or "1.0" of a whole unit — while the page's check and the review read `draftQuantity()`; `QuantityRule` refuses a quantity that is not canonical and counts its decimals as written, so a reviewed confirmation got VALIDATION_FAILED | Fixed: `sentQuantity()` in `resources/js/lib/inventory/stock.ts`, the review's canonical quantity, sent by `IssueCreate.tsx`. Regression tests: `issueDraft.test.ts` "the quantity that a line sends"; `issue.spec.ts`, the test of the lost answer, now drafts "1,0" and asserts the payload's "1" and the commit after "Kirim ulang" |
| P2-b: the search results stay active while the request is frozen | Held: the result buttons had no `unavailable`, and a search still out could add a line from its answer after the freeze, a line that "Kirim ulang" would not send | Fixed: the results `unavailable` while frozen; `add()` and `runSearch()` do nothing then; the confirmation and the freeze end every search still out. Regression tests: `issue.spec.ts`, the result button disabled after the lost answer, and the new test of a scan still out when the request is sent (review finding V-01) |

The same two defects stand in UXP-405 of TASK-022, outside this Task's scope: OPEN_ITEMS T-40.

**Targeted verification** (CE-43, class A, production behaviour changed) by `bk-reviewer-high`, High:

| Round | Package | Verdict |
| --- | --- | --- |
| 3 | `review/TASK-023/r3`, head `a75d4a3`, previous `r2`; `--check` `UNCHANGED` | FAIL: P2-a FIXED; P2-b FIXED in code, its regression test partial (V-01, MUST-FIX); two SHOULD-FIX, two NOTEs |
| 4, the check of V-01's fix alone | `review/TASK-023/r4`, head `d6a2f00`, previous `r3`; `--check` `UNCHANGED` | PASS: V-01, V-02 and V-03 FIXED, by reading, none regressed; two NOTEs (V-06, V-07) |

| Finding | Severity | Outcome |
| --- | --- | --- |
| V-01: no regression test of a search still out at the confirmation, whose answer would add to a frozen line | MUST-FIX | Fixed: the new browser test holds the scan's search, sends the request and loses its answer, then releases the search: one line, its quantity "1", the field its code, and "Kirim ulang" sends the same payload |
| V-02: a search ended by the confirmation or the freeze left its loading state on | SHOULD-FIX | Fixed: `endSearches()` turns a loading or failed search into none, and leaves the results shown; the new test asserts the loading state gone |
| V-03: "Coba lagi" of a failed search looked available while frozen and did nothing | SHOULD-FIX | Fixed: not shown while frozen, and ended with the freeze |
| V-04: for a freeze adopted from another tab, the search ends in an effect after the render, a short window | NOTE | Recorded: harmless, because an adopted request has no "Kirim ulang" of this draft |
| V-05: the clearing action of an empty result still empties the disabled field while frozen | NOTE | Recorded: the lines are untouched |
| V-06 (round 4): the two calls of `endSearches()`, at the confirmation and at the freeze, guard the same path, and the test proves the pair, not each | NOTE | Recorded: both kept |
| V-07 (round 4): the new browser test is verified by reading; it runs first on the final head | NOTE | Recorded: its result is CI's on the final head (D-117); a failure there is a new fix cycle |

## 11. Fix cycle 2 on pull request #32 (CE-42, failure 1 of 3)

CI run 41551718254 on the head `1562fbe` failed in `Command contract`: of 294 browser tests, 291 passed, 1 failed and 2 did not run; the image checks passed. The failed test, `issue.spec.ts:295`, met one violation at its axe check on line 334, after the lost answer, with a search result shown and frozen: `label-content-name-mismatch` on the result button (`.bk-btn--block`) of UXP-408. Its tags are those of every check of the file; no other rule failed on the Task's pages.

- **Cause.** axe reads a button's visible text by joining its text nodes with no separator (`visibleVirtual`), so the label span and the SKU span read "<name><SKU>" as one word, which the accessible name "<name> <SKU>" does not contain (WCAG 2.5.3). An inactive row showed "Nonaktif" in place of the SKU while its name still ended in the SKU. Fix cycle 1's test was the first axe check with results on the page.
- **Fix** (`resources/js/pages/inventory/IssueCreate.tsx`, the results list): the button's `rowName` is the text it shows, the SKU or `ux.state.inactive.label`, and a space separates the two spans. The space between flex items is not drawn, so the page looks as before; the active row's name stays "<name> <SKU>", which the test finds by its exact name; adding a line and the frozen state are unchanged. The axe check at `issue.spec.ts:334`, which failed on `1562fbe`, is its regression test.
- **The other controls of the Task's pages** (`IssueCreate.tsx`, `Issues.tsx`, `Issue.tsx`, the changed parts of `Movement.tsx` and `History.tsx`): every aria-label holds its visible text ("Hapus baris <name>", "Detail mutasi stok <product>", "Tinjau" with its shortcut), and the other links and buttons have none.

**Targeted verification** (CE-42, class A, production code changed) by `bk-reviewer-high`, High:

| Round | Package | Verdict |
| --- | --- | --- |
| 5, the check of fix cycle 2 alone | `review/TASK-023/r5`, tree `24f8ff6` on `1562fbe`, previous `r4`; `--check` `UNCHANGED` | PASS: the defect FIXED, by reading, for active and inactive rows; nothing regressed; lint and TypeScript accept the change |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01 (round 5): no browser test shows an inactive result under axe; the `issue` context of RD-01 returns inactive products | NOTE | Recorded: the name and the visible text are built from the same string, so the rule holds whatever it is |
| R-02 (round 5): the same latent mismatch in UXP-405's results (`ReceivingCreate.tsx`) and RP-402's product filter (`History.tsx`), outside this Task's diff and its axe checks | SHOULD-FIX | Recorded in OPEN_ITEMS T-40 (c); not fixed in TASK-023 |
| R-03 (round 5): this section missing | NOTE | Fixed: this section |

## 12. Fix cycle 3 on pull request #32 (CE-42, failure 2 of 3)

CI run 41583276625 on the head `db08c9b` failed in `Command contract`, at its browser suite alone: of 294 browser tests, 291 passed, 1 failed and 2 did not run, as on `1562fbe`; every other step and the image checks passed. The whole log holds one failure. The failed test, `stock.spec.ts:130` of TASK-022 (AC-022-02), which this Task does not change, met a strict-mode violation at line 166: `getByText('Data belum diperbarui')` resolved to two notices, the list's and a second one.

- **Cause.** The second notice is the shell's own (`IndicatorsNotice` in `AppShell.tsx`, UXS-21): its read of RD-03, and RD-04 after it while the user has an unresolved request, waits 30 seconds for its answer (`requestJson`'s `waitMs`), on the page's fake clock. The test jumps the clock 30 seconds while the shell's real read, started by the focus or the previous jump, may still be out, so the jump ends that read as failed: the race that TASK-022's review finding R-14 met for the list's own read. It depends on the server's speed, so the test passed on `develop` and on `1562fbe`. A probable trigger is this Task's `issue.spec.ts`, which runs before `stock.spec.ts` and sends requests as Dewi, whose RD-03 then counts a request and adds the read of RD-04; this is not proven, and the fix does not rely on it.
- **Fix** (`tests/Browser/inventory/stock.spec.ts`, test only): `quietShell()` answers the shell's RD-03 at once, with no unresolved request and no attention, as the badge test of the same file does, and returns a wait for none of the shell's reads to be out, counted from the browser's request events; each of the two clock-moving tests of the file calls it before every jump. The list, the subject of both tests, is still read and refreshed as before; no assertion is removed or weakened, and axe runs as before. The second test (line 182), which passed, has the same race on its three jumps and its assertions of "Data belum diperbarui", so it is fixed with it, to leave no second failure for the last cycle.
- **Regression test.** The failed test itself, on the fix's head; no production code changes, so no targeted verification (CE-40, CE-42).

## 13. Fix cycle 4 on pull request #32 (CE-42, failure 3 of 3, the Owner's Option A)

CI run 41589075661 on the head `5cd4262` failed in `Command contract`, at its browser suite alone: of 294 browser tests, 291 passed, 1 failed and 2 did not run. `stock.spec.ts:130`, fix cycle 3's subject, passed, and so did `issue.spec.ts`. The failed test, `stock.spec.ts:213`, waited in its first `shellSettled()` (line 254), before any clock jump, for a shell read that stayed counted out: "Expected: 0, Received: 1". The executor of the fix cycle stopped at `OWNER_DECISION`; the Owner, on 3 November 2026, authorized Option A as the final permitted correction of TASK-023, with no reset of the failure counter and no extension of the limit, and a stop at `CI_FAILURE_LIMIT` if it fails.

- **Cause.** `quietShell()` is set up after the sign-in, on the page the sign-in leaves, and before `page.goto('/stock')`. A read of RD-03 that the shell of that page starts just before the navigation is counted, and once the navigation replaces its document the browser may never report its end, so the count stays at 1 on the new page. It depends on the timing of the shell's read against the navigation, so the first test of the two passed on the same head.
- **Fix** (`tests/Browser/inventory/stock.spec.ts`, `quietShell()`, test only): when the main frame commits a new document — a `framenavigated` of the main frame after a navigation request of the main frame — the reads still out are discarded; they belong to the document before it, since the new document's scripts start only after the commit. A navigation within the document, as the chips' and the search's address changes, keeps them. A late `requestfinished` or `requestfailed` of a discarded read deletes nothing from the set, so the count of the new document can be neither stale nor negative; the new document's own reads are counted as before.
- **The other users.** `quietShell()` is used only by the two clock-moving tests, lines 158 and 213 at fix cycle 3's head; in both it is set up before their one `page.goto('/stock')`, and every later `shellSettled()` follows navigations within the document or none, so each still waits for the shell's real reads before each jump. No assertion, timeout, test or production behaviour changes, and no test is skipped.
- **Regression test.** The failed test itself, on the fix's head, its first run in CI (D-108); no production code changes, so no targeted verification (CE-40, CE-42), and no repeated review.
