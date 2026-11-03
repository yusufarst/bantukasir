# TASK-024 evidence — Stock adjustment

| | |
| --- | --- |
| Task | [TASK-024](../tasks/TASK-024-stock-adjustment.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-024-stock-adjustment`, from `25d9474bd08540eaacbbbbaecf4fc8347e8b3432` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-024; the executor state at `BUILD` for TASK-024 with this session's lock; TASK-023 `DONE`, no gate; `tsc` and ESLint do not run on this machine (no `node_modules/.bin`), PHP neither, so every check runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 3 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit` | Local | 20:23 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `b75ac85`, `1662fc3`, `142a2c3`, `aa93aad` | 20:12, 20:19, 20:27, 20:39 | under 1 min of scanning each | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 41642332926 on `b75ac85` | 20:16 | 36 s | `PASS` |
| `composer analyse` | Same run | 20:16 | 12 s | `FAIL`: 8 PHPStan errors — a nullable result reference, a needless `array_values`, a helper missing from a test, an assertion PHPStan judged always true (finding F-1); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41643442045 on `1662fc3` | 20:23 to 20:24 | about 1 min | `PASS` |
| `npm run lint` | Same run | 20:24 | 4 s | `FAIL`: one template literal with letters inside JSX, an id of `Menu.tsx` (finding F-1); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 41644529667 on `142a2c3` | 20:30 to 20:33 | about 3 min | `PASS` |
| `AdjustmentRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest` | Same run | 20:33 to 20:36 | 3 min 8 s | `PASS` |
| `AdjustmentPagesTest` | Same run | 20:36 | 12 s | `FAIL`: 1 of 3, a defect of the test, `?? 'absent'` on a prop that is null (finding F-1); the later files did not run |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41646371938 on `aa93aad` | 20:43 to 20:44 | 1 min 11 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit`, `adjustmentDraft.test.ts` among them | Same run | 20:45 to 20:46 | 1 min 51 s | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes: `AdjustmentRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest`, `AdjustmentPagesTest`, `AdjustmentTablesTest`, `ConfirmAdjustmentTest`, `IssueTablesTest`, `LedgerTablesTest`, `StockPagesTest` | Same run | 20:46 to 20:51 | 5 min 18 s | `PASS` |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `fe3e595`, the review's fixes | 21:03 | under 1 min of scanning | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint` (`Menu.tsx`), `typecheck`, `test:unit` | Fast run 41650066390 on `fe3e595`, the review's fixes | 21:07 to 21:10 | about 3 min | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes, `AdjustmentRaceTest` with its RACE-24 case among them | Same run | 21:10 to 21:16 | 5 min 43 s | `PASS` |
| `fast-checks` on the record's commit | Fast run 41652888572 on `9bf90f5` | 21:21 to 21:32 | 11 min 3 s | `PASS` |
| Image checks | CI run 41652982248 on `9bf90f5`, pull request #34 | 21:22 to 21:29 | 7 min 7 s | `PASS`; invalidated by fix cycle 1 (it changes the bundle), so it runs again on the next head |
| The whole command contract: static checks, unit, `test:postgres`, `test:race`, `build`, the browser suite | Same CI run | 21:22 to 22:18 | 56 min 19 s | `FAIL` at `npm run test:e2e`: 292 passed, 6 failed, 2 did not run; the six all in `adjustment.spec.ts` (fix cycle 1, section 10) |
| `npm run typecheck` | Local, fix cycle 1 | 4 Nov 00:49 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Image checks, after fix cycle 1 | CI run 41689038370 on `1486ba3`, pull request #34 | 4 Nov 00:56 to 01:03 | 7 min 16 s | `PASS`; still valid after fix cycle 2, which changes one PHP test only |
| The whole command contract, after fix cycle 1 | Same CI run | 4 Nov 00:56 to 01:24 | 27 min 39 s | `FAIL` at `composer test:postgres`: 716 of 717 passed, one error in `ChangeBusinessProfileTest` (fix cycle 2, section 11); validate, lint, analyse and `test:unit` passed; `test:race` and every npm step did not run |
| The whole command contract, after fix cycle 2 | CI on the pull request's next head | — | — | `NOT_RUN` at this writing (CE-41, D-117); never on this machine (D-108) |

Fast run 4, 41646371938 on `aa93aad`, is the run of the code to review; fast run 5, 41650066390 on `fe3e595`, that of the review's fixes. The record's commit changes documents only; its push starts fast run 6 of 10, which the executor does not wait for.

## 2. What was built (contract section 4.1)

1. **The two tables and the key** (migration `2026_11_03_000002_create_adjustment_tables`): `adjustments` (reason 1–1,000, count reference and reference 1–100 each, all mandatory; 1–200 lines; the command unique) and `adjustment_lines` (a signed quantity, never zero, at most the range of a movement either way; `UNIQUE (id, product_id, quantity)`), with their checks, keys, indexes and grants (`database/privileges.php`); the line's deferred key to its movement; and the key `stock_movements.adjustment_line_id` → `adjustment_lines (id, product_id, quantity)` (EXC-44). The movement's cost evidence and allocation are held by the presence keys of TASK-021 and TASK-023.
2. **RC-09 `adjustment.confirm`** (`app/Actions/Inventory/ConfirmAdjustment.php`, `AdjustmentLine`, `ConfirmAdjustmentRequest`, `ConfirmAdjustmentController`; `App\Domain\Inventory\Adjustments`, `StockLedger::appendAdjustment`): CAP-16, the Owner's alone, with recent authentication at the route (`password.confirm`); a reason, a count reference and a reference; up to 200 lines, each `INCREASE` or `DECREASE` of a positive quantity, one ADJUSTMENT of the signed quantity each, in the order of the lines; an increase with its acquisition cost evidence, KNOWN or UNKNOWN, an explicit zero with its evidence or reason (`CostEvidence`, type ADJUSTMENT, the adjustment's reference); a decrease with its allocation through TASK-023's `IssueAllocation::compute` and `IssueAllocations::record` (type ADJUSTMENT); INSUFFICIENT_STOCK for the decreases of a product added together; the locks and refusals of TECHNICAL_RULES section 7.2; one audit event `ADJUSTMENT_CONFIRM`, the costs and allocations in its Owner-only part; the result `location` RP-402 filtered to the adjusted products.
3. **RP-410** (`Inventory\AdjustmentsController`, `AdjustmentPageRequest`; `resources/js/pages/inventory/AdjustmentCreate.tsx`): the draft in page memory; `product` as its first line, read as RD-01 reads it; `from_page` (`stock`, `history`) for "Kembali ke …" and "Batal"; RD-01's context `adjustment` with each row's version; "Tambah" / "Kurangi", the quantity, an increase's cost; the three fields; the review of the stock before, the change and the stock after; the password asked before the first request (UXS-11); refusals at their lines; UXS-14 with "Kirim ulang" and "Cek status".
4. **The entries**: UXI-217, the row overflow "Sesuaikan stok" of UXP-401 (`Stock.tsx`), and UXI-221, the button of UXP-402 with one product filtered (`History.tsx`), each the Owner's, unavailable with its reason under the onboarding freeze or an unresolved posting request; `postingFrozen` on RP-401 and RP-402. The menu of DESIGN_SYSTEM 8.9 gains an `unavailable` item (UXS-13 in a menu).
5. **RP-402 filtered to several products**: its `product` takes one key or up to 200 separated by commas, each once (`HistoryPageRequest`, `StockHistory`, `HistoryController`, `lib/inventory/stock.ts`); one product keeps its posting order, several are by time; ROUTE_CONTRACTS RP-402 records it (EXC-81).
6. **Copy** in `lang/id/inventory.php`, verbatim from COPY_DECK sections 4.1, 9.2, 9.3, 9.11 and 15, and `resources/js/types/translation-keys.ts`.
7. **Tests:** L1 `tests/Unit/Inventory/adjustmentDraft.test.ts`; L2 and L4 `tests/Feature/Inventory/AdjustmentTablesTest.php`, `ConfirmAdjustmentTest.php`, `AdjustmentPagesTest.php`; L3 `tests/Concurrency/AdjustmentRaceTest.php`; L5 `tests/Browser/inventory/adjustment.spec.ts`. The fixture `TokoContoh::adjust()` goes through RC-09's Action.
8. **Guard tests widened**, each for what this Task adds: `LedgerTablesTest` and `IssueTablesTest` (their ADJUSTMENT movements now name real lines, or move to `AdjustmentTablesTest`), `StockPagesTest` (the products of the filter), `PrivilegeMatrixTest`, `OperationsTest`, `RouteInventoryTest`, `CapabilityMatrixTest`, `ProductSearchTest` (the context `adjustment` now open to the Owner), `Tests\Harness\Channels\Allowlists`.

## 3. Readings and technical choices

1. **The stock check of an adjustment.** RC-09 checks, "for issues and decreases, stock enough for the lines of each product added together". The decreases of a product are added together and compared with its stock before the adjustment, whatever increases of it the adjustment also holds, so that no order of the lines can make stock negative at any point (BR-INV-33). Every decrease of a short product is named with the stock available.
2. **Signed lines.** The payload names a direction and a positive quantity (INTERACTION_CONTRACTS RC-09); the line and its movement hold the signed quantity (SCHEMA section 5.7).
3. **A decrease's allocation** is computed from the valuation the line before it left, in the order of the lines, as a manual issue's (BR-INV-32, ADR-008 section 4.2); an increase changes the valuation as a receipt does.
4. **The result** is RP-402 filtered to the adjusted products, each once and ascending. RP-402 took one product; it now takes several (section 2 item 5). Its prop `product` became `products`, a list, and `filters.product` became `filters.products`.
5. **The first line** given by the address has no direction and no quantity, so that the Owner chooses both; it does not count as an unsaved change until it is edited.
6. **The direction** is a segmented control of DESIGN_SYSTEM 8.4 whose legend is "Perubahan" (`inventory.line.change`), the review's word for the change; COPY_DECK names no other legend.
7. **UXI-217 unavailable**: a menu item cannot hold a link, so the reason for an unresolved request is the item's text, and "Buka Permintaan belum pasti" is a second item of the same menu.
8. **Not built here:** "Lihat hasil" of UXP-006 for an adjustment (UXI-056 is not among this Task's controls); a source link of an ADJUSTMENT on RP-402 and RP-403 (no page of an adjustment exists). The movement's cost evidence of a positive ADJUSTMENT already shows on RP-403 for the Owner (UXI-225).
9. **No decision entry** for RP-402's list of products (review NOTE R-06): it carries out RC-09's own location and changes no business meaning; ROUTE_CONTRACTS, the authoritative document, records it (EXC-81).

## 4. Findings

- **F-1.** Three fast runs failed on defects of the new code's static form and of a new test, none of behaviour: PHPStan's nullable result reference, a needless `array_values`, a missing test helper and an assertion it judged always true (run 1); a template literal with letters inside JSX, which the copy rule of ADR-005 refuses (run 2); `?? 'absent'` on a prop that is null (run 3). Each was fixed in the next checkpoint.
- **F-2, for the next change control of COPY_DECK:** UXI-269's direction control has no legend of its own; the page uses `inventory.line.change` "Perubahan" (reading 6).
- **F-3, for TASK-026 and the Task that next touches UXP-006:** an ADJUSTMENT movement has no source link and UXP-006 has no "Lihat hasil" for `adjustment.confirm` (reading 8); RC-09's result is RP-402 filtered to its products, whose address RC-50 also gives.

## 5. The ADR-008 cases and the backup impact (EXC-47)

| Case | Test | Result |
| --- | --- | --- |
| A decrease takes the average before it, rounded once, half-up; an increase adds its cost | `ConfirmAdjustmentTest::test_an_adjustment_posts_each_signed_line_…` (1 of 13 pens worth Rp136.000 takes Rp10.461,538462) | `PASS`, fast run 41646371938 |
| The decrease that takes stock to zero takes the whole remaining value; UNKNOWN never zero; a known pool after zero; an explicit zero with its evidence | `ConfirmAdjustmentTest::test_an_increase_carries_known_unknown_or_an_explicit_zero_and_a_decrease_follows_adr_008` | `PASS`, same run |
| Postings of one product serialize on its guard: the decrease that waited takes the valuation the increase published | `AdjustmentRaceTest::test_an_increase_and_a_decrease_of_one_product_serialize_on_its_guard` | `PASS`, same run |
| Corrections, the replay, reversal | TASK-025, TASK-026 | Not this Task's |

**Backup impact.** The two new class-F tables, `adjustments` and `adjustment_lines`, are in the manifest's counts through the privilege matrix (`BackupRunner::COUNTED_CLASSES`, BACKUP_RECOVERY section 5); the backup role reads both tables and their sequences. No stored file, runbook or status page changes.

## 6. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS on the fast runs, the L5 criterion subject to CI on the final head | AC-024-01 to AC-024-04 below; REQ-INV-08, WF-22 |
| UI and UX | PASS subject to CI on the final head | UXP-410 and its 11 controls against the approved P7 artifacts; COPY_DECK verbatim; F-2; the browser tests with axe, the widths and the 200 % zoom run first on the final head (D-117) |
| Backend | PASS on the fast runs | The FormRequests' allowlists, NOT_FOUND for a guessed identifier, CAP-16 at the route and in the Action, recent authentication, the channel allowlists, L4 tests |
| Database | PASS on the fast runs | The migration with its checks, keys, indexes and grants; the constraint, deferred, privilege and retention families (`AdjustmentTablesTest`); the key completed on `stock_movements` (EXC-44). The rehearsal on the benchmark dataset is TASK-043's (EXC-46) |
| Security | PASS on the fast runs | OPERATIONS_ADMIN, the cashier, the combined account and a visitor refused on every route and in the Action; 423 without recent authentication; costs only in the audit's Owner-only part; no secret; the static checks |
| Data integrity | PASS on the fast runs | Append-only facts; the evidence or allocation of every movement at commit; the audit and result in the transaction; identity and idempotency; RACE-01, RACE-02, RACE-24 and the last unit; the failure after every write step |
| Performance | PASS | An adjustment holds at most 200 lines; RP-402's filter at most 200 keys; the budgets are measured at TASK-048 |
| Operations | PASS | Section 5's backup impact; no runbook or status change |
| Verification | PASS subject to CI on the final head | Self-review; the class-A review PASS with no MUST-FIX (section 8); the fast runs green on the reviewed code and on the review's fixes (section 1); the whole command contract and the image checks on the final head (CE-41, D-117) |

## 7. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-024-01 | PASS on the fast run | `ConfirmAdjustmentTest` (the Owner alone, 423 without recent authentication, every field mandatory with its key), `CapabilityMatrixTest`, `AdjustmentPagesTest` |
| AC-024-02 | PASS on the fast run | `ConfirmAdjustmentTest` (evidence of increases, allocations of decreases, INSUFFICIENT_STOCK for the decreases added together), `AdjustmentTablesTest` (both presence keys at commit), `AdjustmentRaceTest` (the last unit) |
| AC-024-03 | PASS on the fast run | `ConfirmAdjustmentTest::test_a_failure_after_every_write_step_…` (16 steps), `AdjustmentRaceTest` (RACE-01, RACE-02, RACE-24) |
| AC-024-04 | PASS subject to CI on the final head | `AdjustmentPagesTest` and `adjustmentDraft.test.ts` on the fast run; `adjustment.spec.ts`, its storage check and its widths, on the final head |

## 8. Review (CE-40)

**Self-review** of the whole change against the contract's scope items 1 to 3 and the rules it cites, before the isolated review: each item is built as section 2 says; nothing out of scope (no reversal or correction); every route with its gate, its parameters as allowlists and NOT_FOUND for a guessed identifier; the draft of RP-410 in page memory only; the 11 controls of contract section 4.2, UXI-056 and UXI-224 not extended (reading 8). It found nothing to change before the review.

| Round | Agent, effort | Package | Verdict |
| --- | --- | --- | --- |
| 1, the adversarial review (class A) | `bk-reviewer-high`, High | `review/TASK-024/r1`, head `b6cb7de`; `--check` `UNCHANGED` | PASS: no MUST-FIX, four SHOULD-FIX, five NOTEs. No MUST-FIX, so no targeted verification (CE-40) |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01: RACE-24, which RC-09 and WF-22 name, not proven for RC-09 | SHOULD-FIX | Fixed: `AdjustmentRaceTest::test_race_24_a_posting_block_against_an_adjustment_…`, both orders |
| R-02: the unavailable menu item looked active, and its reason entered its name | SHOULD-FIX | Fixed: `.bk-menu__item[aria-disabled='true']` in `components.css`, the item named by its label (`aria-labelledby`), the reason its description; tested by R-03's test |
| R-03: no test of the unavailable entries UXI-217 and UXI-221 | SHOULD-FIX | Fixed: `adjustment.spec.ts`, "while an adjustment is uncertain, the entries … are unavailable with their reason", on the final head (D-117) |
| R-04: the evidence lacked the findings, the backup impact, the criteria and the Definition of Done | SHOULD-FIX | Fixed: sections 4 to 8 |
| R-05: decreases checked against the stock before the adjustment | NOTE | Recorded as reading 1 |
| R-06: a decision entry for RP-402's list | NOTE | Recorded as reading 9 |
| R-07: the fact and its projection are one write step | NOTE | Recorded; as TASK-023's reading 9 |
| R-08: the storage check before the first send only | NOTE | No change: the references store no payload |
| R-09: the heavy checks `NOT_RUN` | NOTE | They run on the pull request's final head (CE-41, D-117) |

## 9. The guard's candidates (CE-31)

The guard of fix cycle 1 (section 10) is reported with its push; `guard.sh --preview` on its working tree lists no candidate.

## 10. Fix cycle 1 (CE-42, CE-43)

Its inputs: the red CI run 41652982248 on `9bf90f5`, and the automated reviewer's P2 finding, review comment 4455969386 on pull request #34, checked against the code and found to hold. The session that began it was cut off by a power loss before any change; this one resumed it with no work to recover.

| Input | Cause | Fix | Regression test |
| --- | --- | --- | --- |
| P2: lines of one product added before and after it changed elsewhere kept two snapshots, and `confirm` sent the last line's version for both, so a line reviewed in an old unit could post in the new one | `confirm` collected the versions in a map that overwrote | `withSnapshot` and `reviewedVersions` in `lib/inventory/stock.ts`: a product read again replaces the product of every line of it, which closes an open review; lines of one product with different versions are never sent | `adjustmentDraft.test.ts`, "one snapshot of a product in a draft" |
| CI: "Stok disesuaikan." absent on RP-402 after a success (4 tests) | `History.tsx` never took the note that UXP-410 leaves | RP-402 takes the note once and shows it, as the pages of a receiving and of a manual issue do | The 4 browser tests themselves |
| CI: the click on the unavailable menu item timed out | Playwright waits for an enabled element, and the item is `aria-disabled` | The spec forces that click, as `labels.spec.ts` does; on RP-402 it checks the button's description and the first link to UXP-006 within `main`, the banner of UXI-029 carrying one too | The test itself |
| CI: Operations' test saw a console error | The browser reports its own 403 page load, which the test asserts | The spec filters `status of 403`, as `import.spec.ts` and `correction.spec.ts` do | The test itself |

The fix changes production behaviour of a class-A Task, so `bk-reviewer-high` made one targeted verification (CE-42, CE-43) of package `review/TASK-024/r2` (the working tree on `9bf90f5`); `--check` `UNCHANGED`.

| Finding | Severity | Outcome |
| --- | --- | --- |
| A, B1 to B3: the four inputs above | — | FIXED, no new MUST-FIX |
| V-01: the unit tests prove the helpers, not that the page calls them | SHOULD-FIX | Recorded, not fixed: no test drives a React page outside the browser suite, and a browser case would change a product's unit in a second tab, a flow first run on the final head, whose failure would cost a fix cycle; the page's two calls were read by the verification |
| V-02: the first link to UXP-006 within `main` may be the banner's | NOTE | Kept: the button's description proves the reason, as `catalog.spec.ts` does |
| V-03: the URL is read at once after the forced click | NOTE | No change: the item has no address or handler that navigates |
| V-04: the re-read after a refusal does not close an open review | NOTE | Existing behaviour, one snapshot either way; no change |
| V-05: the note stays while RP-402 is filtered after the success | NOTE | As on the product list; no change |
| V-06: the 403 filter covers the whole test | NOTE | As the precedents; the 403 itself is asserted |

`guard.sh --preview` and `guard.sh` on every checkpoint, `b75ac85` to `fe3e595`, list no candidate: no address, host, e-mail or home path. The guard on the record's commit is reported with the pull request, because this file cannot name its own commit.

## 11. Fix cycle 2 (CE-42)

Its input: the red CI run 41689038370 on `1486ba3`, failure 1 of 3 of CE-42.

| Input | Cause | Fix | Regression test |
| --- | --- | --- | --- |
| CI: `ChangeBusinessProfileTest::test_a_logo_at_the_limits_of_its_purpose_is_accepted_within_the_memory_of_a_request` (TASK-010), `ErrorException: Failed to set memory limit to 134217728 bytes (Current memory usage is 135266304 bytes)` at its second `ini_set('memory_limit', '128M')` | The test set an absolute 128 MiB limit inside a PHPUnit process that holds the memory of every earlier test of the suite; PHP refuses a limit below the memory in use. The suite's peak was already 133 MB on `develop` (run 41618363878) and is 135 MB with this Task's code, so the margin of about 1 MB was crossed by the suite's growth, not by a defect of the Task's code: no production file of the image path changes, and TASK-024 adds no static state that tests accumulate | The test starts each request at 128 MiB, or at 16 MiB above the memory in use when the runner already holds more (`startRequestMemory`). Either stays far below what a 16-megapixel image needs (13 bytes a pixel and 64 MiB, `ImageNormalizer::reserveMemory`), so the reservation is still required and proven; no assertion changes | The test itself, on the next head's `test:postgres` |

No production behaviour changes, so no targeted verification (CE-42). The same pattern in `ProductPhotoTest::test_a_photo_at_its_limits_is_accepted_within_the_memory_of_a_request` passes, earlier in the suite with less memory held; it is left as it is (finding F-4).

- **F-4, for the Task that next touches the media tests:** `ProductPhotoTest` sets an absolute 128 MiB limit as `ChangeBusinessProfileTest` did, and will fail the same way when the suite's memory before it passes 128 MiB; `startRequestMemory` is the pattern to reuse.

Checks of fix cycle 2: the changed test runs in `fast-checks` on the push (a PostgreSQL file the branch changes) and in `test:postgres` on the pull request's next head; `composer lint` and `analyse` cover the file there. Nothing runs on this machine: no PHP (D-108). The guard of fix cycle 2 is reported with its push.
