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
| `npx tsc --noEmit`, `npx eslint` | Local | 04:58 | — | `NOT_RUN`: neither is installed on this machine |
| The whole command contract — `test:postgres`, `test:race`, `build`, the browser suite with `tests/Browser/inventory/issue.spec.ts` and the changed browser files — and the image checks | CI on the pull request's final head | — | — | `NOT_RUN` at this writing (CE-41, D-117) |

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
| Verification | PASS subject to CI on the final head | Self-review; the class-A review and the targeted verification of its MUST-FIX (section 8); the fast runs green on the reviewed code and on the fixes (section 1); the whole command contract and the image checks on the final head (CE-41, D-117) |

## 7. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-023-01 | PASS on the fast run | `ConfirmManualIssueTest`: the reason refused unless one of the five; INSUFFICIENT_STOCK for the lines of a product added together, every line named with the stock available, and exactly the stock posting |
| AC-023-02 | PASS on the fast run | `IssueAllocationTest`, `ConfirmManualIssueTest`: the average before the issue, the whole value at zero, UNKNOWN while unknown; no Sale, Payment or receipt table and no cost evidence written |
| AC-023-03 | PASS on the fast run | `ManualIssueRaceTest::test_race_03_two_issues_for_the_last_unit_give_one_success_and_one_insufficient_stock` |
| AC-023-04 | PASS on the fast run | `IssueTablesTest`: an issue movement without its allocation, and an allocation without its projection, fail at commit; `ConfirmManualIssueTest`: every projection equal to its fact |
| AC-023-05 | PASS on the fast run | Section 5 |
| AC-023-06 | PASS subject to CI on the final head | `IssuePagesTest` on the fast run; `issue.spec.ts`, with the widths of R-01, on the final head |
