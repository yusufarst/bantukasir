# TASK-022 evidence — Receiving, stock and history pages

| | |
| --- | --- |
| Task | [TASK-022](../tasks/TASK-022-receiving-stock-pages.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-022-receiving-stock-pages`, from `f90a274c0f323981684e7e865d00042de9d32347` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-022; TASK-021 and TASK-017 `DONE`, no gate; PHP, `tsc` and ESLint do not run on this machine, so every check runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 2 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard, preview on the working tree | Local | 22:10 | under 1 min of scanning | `PASS`, no candidate |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `7526324`, `be47008`, `79a28e9`, `65bb79d` | 22:11, 22:18, 22:26, 22:35 | under 1 min of scanning each | `PASS`, no candidate |
| `composer lint` | Fast run 41469208849 on `7526324` | 22:15 | 22 s | `FAIL`: Pint's `single_quote` in `StockAttention.php` (finding F-5); the later steps did not run |
| `composer analyse` | Fast run 41470367163 on `be47008` | 22:22 | 16 s | `FAIL`: 11 PHPStan errors, ten in the new tests and one needless `array_values` (finding F-5) |
| `npm run lint` | Fast run 41471727887 on `79a28e9` | 22:32 | 5 s | `FAIL`: three literals in JSX attributes (finding F-5); the PHP static checks and `composer test:unit` passed before it |
| `composer validate`, `lint`, `analyse`, `test:unit` (866 tests) | Fast run 41473137685 on `65bb79d` | 22:38 to 22:39 | 52 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit` (21 files) | Same run | 22:40 to 22:41 | 51 s | `PASS` |
| `tests/Feature/Access/CapabilityMatrixTest.php` | Same run | 22:41 | 1 min 53 s | `PASS`, 7 tests, 2,748 assertions |
| `tests/Feature/Access/HomeRedirectTest.php` | Same run | 22:43 | 7 s | `PASS`, 4 tests, 71 assertions |
| `tests/Feature/Access/RouteInventoryTest.php` | Same run | 22:43 | under 1 s | `PASS`, 8 tests, 1,021 assertions |
| `tests/Feature/Access/ShellDataTest.php` | Same run | 22:43 | 11 s | `PASS`, 3 tests, 93 assertions |
| `tests/Feature/Catalog/ProductSearchTest.php` | Same run | 22:43 | 55 s | `PASS`, 16 tests, 598 assertions |
| `tests/Feature/Inventory/ReceivingPagesTest.php` | Same run | 22:44 | 18 s | `PASS`, 5 tests, 12,233 assertions |
| `tests/Feature/Inventory/StockAttentionTest.php` | Same run | 22:44 | 15 s | `PASS`, 4 tests, 178 assertions |
| `tests/Feature/Inventory/StockPagesTest.php` | Same run | 22:44 | 25 s | `PASS`, 7 tests, 12,184 assertions |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `8bda0e0`, the review's fixes | 23:12 | under 1 min of scanning | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41479313079 on `8bda0e0` | 23:16 to 23:17 | 1 min 7 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit` | Same run | 23:18 to 23:19 | 1 min 0 s | `PASS` |
| The PostgreSQL test files that the branch changes, with the new cases of R-01 and R-02 in `StockAttentionTest`, `StockPagesTest` and `ReceivingPagesTest`, and the changed `ReceivingsController` | Same run | 23:19 to 23:24 | 4 min 33 s | `PASS` |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `e269506`, the fix of R-11 (round 2) | 23:32 | under 1 min of scanning | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41482579180 on `e269506` | 23:35 to 23:36 | 39 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit` | Same run | 23:36 to 23:37 | 39 s | `PASS` |
| The PostgreSQL test files that the branch changes, `StockAttentionTest` with the name of line separators among them | Same run | 23:37 to 23:41 | 3 min 50 s | `PASS` |
| `npx tsc --noEmit`, `npx eslint` | Local | 22:05, 23:10 | — | `NOT_RUN`: neither is installed on this machine |
| The whole command contract — `test:postgres`, `test:race`, `build`, the browser suite with `tests/Browser/inventory/` and the changed browser files — and the image checks | CI on the pull request's final head | — | — | `NOT_RUN` at this writing (CE-41, D-117) |

Fast run 4, 41473137685 on `65bb79d`, is the run of the code to review; fast run 5, 41479313079 on `8bda0e0`, that of the review's fixes (section 7), and fast run 6, 41482579180 on `e269506`, that of the fix of round 2's R-11: six fast runs of the 10 that CE-46 allows. The record's commit changes documents only. The new browser tests of R-03 and the changed `ReceivingCreate.tsx` run first in the browser suite on the pull request's final head.

## 2. What was built (contract section 4.1)

1. **RP-404 to RP-406** (`Inventory\ReceivingsController`, `ReceivingsPageRequest`, `Queries\Inventory\ReceivingDocuments`; `resources/js/pages/inventory/Receivings.tsx`, `ReceivingCreate.tsx`, `Receiving.tsx`). RP-405 is KS-09: the draft in page memory only, scan capture, RD-01 in the context `receiving`, "Tambah produk" over the page (NC-11) through `CreateFlow` of `pages/catalog/ProductCreate.tsx` with `over`, the review and the confirmation of RC-07 with each product's version as reviewed. RP-406 shows each line's purchase cost to every holder of CAP-11 (CAP-12), a zero's evidence or reason to the Owner alone.
2. **RP-401 to RP-403** (`StockController`, `HistoryController`, `MovementsController`, `StockListRequest`, `HistoryPageRequest`; `Queries\Inventory\StockList`, `StockHistory`, `MovementDetail`; `pages/inventory/Stock.tsx`, `History.tsx`, `Movement.tsx`). RP-401 is KS-08, refreshed from RD-02 at least every 30 seconds while visible, on focus and after a command, as background reads; a failed refresh keeps the last rows with their time (UXS-21). RP-402 is keyset-paged both ways, in posting order within a product, otherwise by time. RP-403 shows the movement, its source link and, for the Owner, its cost evidence (CAP-20).
3. **RD-02** (`StockDataController`, `StockList`): the rows, the numbers of Habis and Menipis and the as-of time from one statement over one snapshot (a lateral join of the page beside the counts). **RD-01's contexts** `receiving` (CAP-11, with each row's `version`) and `stock` (CAP-14). **RD-03's** `stockAttention` (`StockAttention`, read by `ShellDataController` for a full holder of CAP-14) and the badge UXI-006 (`AppShell`, `lib/commands/unresolved.ts`).
4. **RP-302's section "Stok"** (UXI-167; `ProductStock::of`, `pages/catalog/Product.tsx`); the navigation UXI-006 and UXI-012 to UXI-014 (`lib/navigation.ts`); **RN-01** to RP-401 for Operations and the combined account (`App\Domain\Access\Homes`); "Lihat hasil" (UXI-056) on UXP-006 for a receiving (`pages/commands/Requests.tsx`).
5. **Copy** in `lang/id/inventory.php`, `costing.php`, `ux.php` and `catalog.php`, verbatim from COPY_DECK sections 4.1, 4.3, 5, 9.1 to 9.7, 15 and 16.1, and `resources/js/types/translation-keys.ts`.
6. **Tests:** L2 and L4 `tests/Feature/Inventory/StockAttentionTest.php`, `StockPagesTest.php`, `ReceivingPagesTest.php`; L5 `tests/Browser/inventory/stock.spec.ts` and `receiving.spec.ts`, in the project `people` of `playwright.config.ts`.
7. **Guard tests widened**, each for what this Task adds, as reported to the reviewer: `RouteInventoryTest` and `CapabilityMatrixTest` (the seven routes; RN-01's answer for Operations and the combined account), `HomeRedirectTest` (the homes of NC-12 as this Task switches them; its test of the ended sign-in session, OPEN_ITEMS T-37, is unchanged), `ShellDataTest` (RD-03's new field), `ProductSearchTest` (the contexts `receiving` and `stock` now open), `Tests\Harness\Channels\Allowlists` (the six pages, RD-02, RD-01 `receiving`, RD-03 and RP-302's `stock`), `tests/Unit/Shell/navigation.test.ts` (the destinations registered), `tests/Unit/Commands/commandClient.test.ts` (RD-03's `stockAttention`), and the browser tests whose people now land on Stok: `support/access.ts` (`homeOf`, the sign-in's wait for either home), `shell/shell.spec.ts`, `shell/session.spec.ts` and `catalog/catalog.spec.ts`.

## 3. Readings and technical choices

1. **UXP-401 lists the active products.** Stock attention is a state of an active product (INVENTORY section 10); an inactive product's movements stay in UXP-402. The counts of Habis and Menipis are those of every active product, whatever the filters, as the badge counts them.
2. **One definition, one statement.** `StockAttention::STATE` derives the state in SQL; RD-03 counts with it, and RD-02 reads its counts and its page in one statement, a lateral join, so that the list, its counts and the badge come from one snapshot (BR-INV-41, ARC-22). After each read of RD-02 the page gives its numbers to the badge. `StockAttentionTest` proves the SQL equal to the Domain's `StockState`.
3. **RD-02's order and search.** Name, then internal identifier, the keyset of "Muat lebih banyak"; `q` finds a product by its active barcode, its exact SKU, a SKU that starts with it or a name that holds it, without regard to letter case (SCH-14). The Enter that ends a scan searches for the code at once; an empty answer is UXS-33.
4. **The history's order.** Newest first: by posting sequence within a product, otherwise by the server's time, then identifier. "Oleh" is the account's display name now, which an anonymization replaces. No index is added (contract section 9, Database N/A): ordering by `recorded_at` and by `confirmed_at` sorts without one, which TASK-048 measures (finding F-3).
5. **"Tanggal" is one day.** COPY_DECK names one date filter; the field chooses a day of Asia/Jakarta, `from` and `to` alike. A range comes only from an address, and `from` after `to` is refused at `to` with `ux.validation.date_order`.
6. **Ways back.** RP-402 names its opener in `from_page` (`stock`, `product` or `requests`), for UXI-223; RP-403, RP-405 and RP-406 read the document's referrer, as the catalogue's pages do, so that a list's filters come back with it and nothing is in the browser's storage.
7. **The context `receiving`** answers inactive products too, marked, and the page refuses to add one (UXS-32); only this context adds each row's `version`, which RC-07 expects. A scan adds one base unit to the latest line of its product, or a new line; a choice from the results always adds a new line of one base unit (UXI-234, D-067).
8. **"Tambah produk" over the page** is `CreateFlow` with `over`: its back and cancel return to the draft, its success reads the new product by its SKU in the context `receiving` and adds it as a line. The working page holds the document's one unsaved-changes guard, so the guard of UXP-303 as its own page moved to `GuardedCreateFlow`.
9. **Cost at the field.** A known cost needs its amount; Rp0 is the Owner's alone, with its evidence or reason, and for staff it shows `costing.field.zero_disabled` at the field (UXI-236), so staff never send one (TASK-021 review R-07). The server's refusals are read at their lines by the index of the request sent.
10. **RP-406's cost** is the evidence as confirmed. The current cost, "Dikoreksi Pemilik" and the cost at confirmation after a correction, and the Owner's correction history (UXI-246, UXI-247), need the corrections of TASK-025; no correction can exist yet, so nothing of them is shown.
11. **"Lihat hasil" (UXI-056)** is offered for `receiving.confirm`, whose result page RP-406 now exists; the other result pages join with their Tasks (`RESULT_PAGES`).
12. **RN-01:** no cash session exists before TASK-027, so the combined account has no OPEN session of its own and lands on Stok.
13. **Not built here, by their Tasks:** UXI-217 and UXI-221 (TASK-024), UXI-226 to UXI-228, UXI-249 and UXI-250 (TASK-025, TASK-026), UXI-244 (not provided, D-069).

## 4. Findings

- **F-1, for the next change control of COPY_DECK:** two keys of this Task's pages cannot be read from their rows by `CopyDeckTest`, whose cells name keys alone or in lists separated by "; ": `inventory.receipt_new.reference` (section 9.6, UXI-238, in a cell "…, each + `ux.action.optional`") and `catalog.product_detail.stock_now` (section 8.3, UXI-167, in a cell with `inventory.state.*`). The page uses `inventory.receipts.col_reference` "Referensi", the same text, for the reference field, and shows UXI-167's stock and state with `inventory.stock.col_stock` and `inventory.stock.col_state`; no text changes. The approved artifact is not edited.
- **F-2, for TASK-017's control UXI-162 or the next change control:** the state of a row of UXP-301 needs RD-01's stock, which TASK-021 added, and a column whose header COPY_DECK section 8.2 does not name. UXI-162 is not among this Task's controls (TRACEABILITY section 9.2), so UXP-301 is unchanged.
- **F-3, for TASK-048:** RP-402 without a product filter orders by `stock_movements.recorded_at`, and RP-404 by `receiving_documents.confirmed_at`, neither of which has an index; each page is bounded, and its plan sorts the rows of the filter. An index, if the budgets need one, is a new migration (EXC-41).
- **F-4, from TASK-021 F-3:** a body of RC-07 above RT-12's 1 MiB, possible only with 500 lines of nearly 1,000 characters of the Owner's zero evidence outside ASCII, is refused whole; the page does not warn before it. Recorded, not built: no text of COPY_DECK names it.
- **F-5.** Three fast runs were spent on static checks that do not run on this machine: a double-quoted string that Pint wants single (run 1), PHPStan's level 9 on `mixed` in the new tests, a test helper `sorted()` that shadowed `ChannelInspector::sorted()`, and a needless `array_values` (run 2), and literals in JSX attributes, a state identifier and a template (run 3). Each was a defect of the change, fixed in the next checkpoint.
- **F-6, for TASK-048 or a later change of RP-401:** the review's notes R-08 and R-09, recorded and not changed. A refresh of UXP-401 after "Muat lebih banyak" past 100 rows reads its pages one after another, so the counts shown are those of its first page's snapshot, never of a mixture (R-08). A failed "Muat lebih banyak" keeps the rows with UXS-21's notice and its "Coba lagi", where UI_STATES names UXS-22 for a part that cannot load; the text differs, the recovery is the same (R-09).

## 5. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS on the fast runs, the L5 criteria subject to CI on the final head | AC-022-01 to AC-022-06 below; the requirements of contract section 5 |
| UI and UX | PASS subject to CI on the final head | The pages of KS-08 and KS-09 and their controls against the approved P7 artifacts (EXC-50 to EXC-53), COPY_DECK verbatim (`CopyDeckTest`); their browser tests, with axe, the widths and the contrast, run first on the pull request's final head (D-117); F-1 and F-2 |
| Backend | PASS on the fast runs | The FormRequests' allowlists, the cursor's shape and its texts refused as VALIDATION_FAILED (R-02), object scope with NOT_FOUND, the DTOs and the channel allowlists of every role, L4 tests |
| Database | N/A | No migration (contract section 9); no index added, F-3 |
| Security | PASS on the fast runs | CAP-11 and CAP-14 at each route, the cashier and a visitor refused, the route inventory and the capability matrix; no cost to a staff account but RP-406's purchase cost (CAP-12); a zero's evidence and RP-403's cost evidence under CAP-20; the draft never in the browser's storage; no secret; the static checks |
| Data integrity | N/A | The pages write no business fact; RC-07 is TASK-021's |
| Performance | PASS | Each read bounded: keyset pages of at most 100 rows, RD-02 one statement; the budgets are measured at TASK-048 (F-3) |
| Operations | N/A | No backup, runbook or status impact |
| Verification | PASS subject to CI on the final head | Self-review; the review of class A and the targeted verification of its MUST-FIX (section 7); fast runs green on the reviewed code and on the fixes (section 1); the whole command contract and the image checks run on the pull request's final head (CE-41, D-117) |

## 6. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-022-01 | PASS on the fast run | `StockAttentionTest`: the thresholds of minimum 5 and 0, a new product Habis, the badge and the list from one definition and equal to the Domain's `StockState` |
| AC-022-02 | PASS subject to CI on the final head | `stock.spec.ts`: the refresh every 30 seconds while visible and on focus, as background reads, and a failed refresh keeping the rows with their time (UXS-21) |
| AC-022-03 | PASS subject to CI on the final head | `receiving.spec.ts`: the review and its confirmation, never Enter; the draft absent from the browser's storage; the uncertain answer and each refusal (R-03) |
| AC-022-04 | PASS on the fast run | `ReceivingPagesTest`, `StockPagesTest`, `StockAttentionTest` and the channel allowlists: the purchase cost of RP-406 for Operations, never an average cost, cost of goods sold, margin, profit or value; the cashier refused on every route |
| AC-022-05 | PASS on the fast run | `StockAttentionTest`, `StockPagesTest`, `ReceivingPagesTest`: keyset pages both ways, a cursor past a name of 200 characters (R-01), the payload of each role under CAP-14 |
| AC-022-06 | PASS subject to CI on the final head | `stock.spec.ts`, `receiving.spec.ts`: KS-08 and KS-09 with their states, axe and the measurements at the widths of KEY_SCREENS section 4 named in the tests; R-06 |

## 7. Review (CE-40)

**Self-review** of the whole change against the contract's scope items 1 to 4 and the rules it cites, before the isolated review: each item is built as section 2 says; nothing out of scope (no adjustment, reversal, cost correction, incident or migration); every route with its gate, the parameters of its row as allowlists and NOT_FOUND for a guessed identifier; no cost on a staff account's page but the purchase cost of RP-406 (CAP-12), and the cost evidence of RP-403 for the Owner alone; the draft of RP-405 in page memory only; the 37 controls of section 4.2 against TRACEABILITY section 9.2, with those of later Tasks absent (reading 13). It found the empty typed search of RP-405 shown as an unknown code, fixed before fast run 2 to UXS-03.

| Round | Agent, effort | Package | Verdict |
| --- | --- | --- | --- |
| 1, the adversarial review (class A) | `bk-reviewer-high`, High | `review/TASK-022/r1`, head `65bb79d`, tree `d05e12e`, with this file; `--check` `UNCHANGED` | FAIL: three MUST-FIX, four SHOULD-FIX, five NOTEs |
| 2, the targeted verification of the MUST-FIX | `bk-reviewer-high`, High | `review/TASK-022/r2`, head `8bda0e0`, tree `06eace9`, previous `r1`; `--check` `UNCHANGED` | PASS: R-01 to R-03 FIXED, no new MUST-FIX; one SHOULD-FIX, two NOTEs |

The reviewer found the widened guard tests of section 2 item 7 justified by what the Task adds. Every fix of code has its regression test; the fixes of round 1 are in `8bda0e0`, run in fast run 5, and that of R-11 in `e269506`, run in fast run 6.

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01: a cursor escaped every character outside ASCII and every slash, so a page past a long name in another script exceeded the cursor's limit of 400 and was refused | MUST-FIX | Fixed: `Keyset::encode` leaves them unescaped, and `Keyset::CURSOR`, of up to 1,400 characters, is the rule of `after` and `before` in the three requests; `StockAttentionTest` pages past two names of 198 four-byte characters. Verified FIXED |
| R-02: a cursor with a day that does not exist, the year 0000, or a text with a control character reached PostgreSQL and gave a server error | MUST-FIX | Fixed: `Keyset::isInstant` reads the time back exactly, from the year 1, and a text refuses `\p{Cc}`; VALIDATION_FAILED cases on RD-02, RP-402 (both ways and in a product's order) and RP-404. Verified FIXED |
| R-03: no browser test of UXP-405's uncertain answer or of its refusals | MUST-FIX | Fixed: two tests in `receiving.spec.ts`, the lost answer with the draft frozen and "Kirim ulang" sending the same request, which commits, and VALIDATION_FAILED, STALE_VERSION with the re-read, ONBOARDING_FREEZE, PRODUCT_INACTIVE and POSTING_BLOCKED with their line and message. Verified FIXED by reading; the tests run first on the pull request's final head (D-117) |
| R-04: a refusal of the reference or the document date showed nowhere | SHOULD-FIX | Fixed: each at its field and in the error summary |
| R-05: the re-read after STALE_VERSION searched only by the SKU as reviewed, which may itself have changed | SHOULD-FIX | Fixed: by the SKU, then by the name, matched by the product's key |
| R-06: the widths of the browser tests, the Owner's view of UXI-225, RP-302's section "Stok" and UXI-056 have no browser test of their own | SHOULD-FIX | Recorded, not built: each is covered at L4 (`StockPagesTest`, `ProductSearchTest`, the channel allowlists) and the pages run at the widths named in `stock.spec.ts` and `receiving.spec.ts`; more browser tests would run first on the final head with no fast run to catch their defects, at the cost of a fix cycle (D-117) |
| R-07: the evidence lacked the tables of the Definition of Done and the criteria, and its findings were out of order | SHOULD-FIX | Fixed: sections 4 to 6 |
| R-08, R-09: the counts of a refresh past 100 rows; UXS-21 where UXS-22 is named | NOTE | Recorded as F-6 |
| R-10: the zero's note of RP-406 shown by role, where RP-403 uses CAP-20 | NOTE | Fixed: `ReceivingsController::show` asks CAP-20; the Owner alone holds it, so nothing shown changes |
| R-11 (round 1): no index for RP-402's and RP-404's orders | NOTE | Recorded as F-3, for TASK-048 |
| R-12 (round 1): the badge's tests of RD-03 to be confirmed in the browser run | NOTE | Recorded: `stock.spec.ts` runs on the final head |
| R-11 (round 2): U+2028 and U+2029, allowed in a name, were still escaped in six bytes each, so a name of many of them exceeded the limit | SHOULD-FIX | Fixed: `JSON_UNESCAPED_LINE_TERMINATORS`, and a third long name of them in the R-01 test |
| R-12 (round 2): the refusal by index is tested with one line, and "Cek status" is not clicked | NOTE | Recorded: the index is the TASK-021 contract's, and "Cek status" is the command client's, tested in `tests/Browser/commands/` |
| R-13 (round 2): a refusal of the document date has no message key on the server | NOTE | Recorded: the date field always sends `YYYY-MM-DD`, so the server refuses no well-formed date; for the next change of RC-07's request |

## 8. The guard's candidates (CE-31)

`guard.sh --preview` and `guard.sh` on every checkpoint, `7526324` to `e269506`, list no candidate: no address, host, e-mail or home path. The guard on the record's commit is reported with the pull request, because this file cannot name its own commit.

## 9. Fix cycle 1 on pull request #31 (CE-42, CE-43)

Pull request #31 opened on `9b2af60`. The repository's automated reviewer left two findings on it, change requests under CE-43; each was checked against the code and holds. Its summary comment and its review receipt are information only. Times are Asia/Jakarta, 2 and 3 November 2026.

| Finding | Severity | What held | Fix | Regression tests |
| --- | --- | --- | --- | --- |
| P1, review comment 4445124822: the formatter fed a draft quantity that is no quantity yet | P1 | UXP-405's review is a child of `ReviewDialog`, made with every render while the dialog is closed too, and it called `formatQuantity(quantityFromEntry(…))` on the quantity as typed. An emptied field, a decimal half typed (`0,`, `1,`) and the entries that `quantityProblem` accepts but are not canonical (`01`, `2,0` of a whole unit) threw `NonCanonicalNumberError` during render, which ended the page and the draft in its memory (BR-INV-17) | `draftQuantity` in `lib/inventory/stock.ts`: the canonical shortest form of an entry that is a quantity of the precision within eight whole digits, otherwise null, with the acceptance of `quantityProblem`; the review formats only it. `QUANTITY_DIGITS_MAX` moved there. What RC-07 is sent is unchanged | `tests/Unit/Inventory/receivingDraft.test.ts` (the old call throws on those entries; `draftQuantity` never lets the formatter throw at precisions 0 to 3); `receiving.spec.ts` "a quantity emptied or half typed keeps the page and its draft…", which ends the page at the first emptied field on `9b2af60` |
| P2, review comment 4445124828: a refresh in the background superseded the read of a new filter | P2 | UXP-401's refresh — every 30 seconds while visible, on focus, after a settled command — read the filters shown and advanced the read counter, so the answer of a filter the user had just chosen was dropped and the list and its address went back to the old filters while the controls showed the new; nothing read it again | `Stock.tsx`: a read the user asked for (a filter, the search, "Muat lebih banyak", "Muat ulang") holds `userRead` until it answers, and a refresh while it is out does nothing; the refresh reads the filters last asked for (`requested`), with the rows shown when the list shows them (`wanted()`) | `stock.spec.ts` "a refresh while a filter is being read never brings back the filters shown before it": the read of "Habis" held through a focus and the 30 seconds' refresh, then the list, its address and the chip those of "Habis", and the next refresh of "Habis" |

**Targeted verification** (CE-43: the fixes change the production behaviour of a class-A Task), by `bk-reviewer-high` at High:

| Round | Package | Verdict |
| --- | --- | --- |
| 3 | `review/TASK-022/r3`, head `9b2af60` with the fixes uncommitted, tree `ddaa88f`, previous `r2`; `--check` `UNCHANGED` | FAIL: P1 FIXED; P2's code FIXED; one MUST-FIX in its test (R-14), one SHOULD-FIX, three NOTEs |
| 4 | `review/TASK-022/r4`, tree `e89f080`, previous `r3`; `--check` `UNCHANGED` | PASS: R-14 and R-15 FIXED, no new MUST-FIX; three NOTEs |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-14: the P2 test's `fastForward(30_000)` while the read of "Habis" was held also fired that read's own wait of 30 seconds, so the read failed and the test failed before and after the fix | MUST-FIX | Fixed: the clock goes 20 seconds before the click and 15 after it, so that the refresh falls due while the read is out and its wait does not; "Data belum diperbarui" asserted absent. Verified FIXED |
| R-15: "Muat ulang" and "Coba lagi" read the filters shown, unlike the refresh | SHOULD-FIX | Fixed: both read `wanted()`, as the refresh does; the test's last part, a failed read of "Menipis" then "Muat ulang", reads "Menipis". Verified FIXED |
| R-16 (round 3): a refresh dropped while a user read is out is not run after it; the next comes within 30 seconds | NOTE | Recorded: the delay is bounded by the client's wait |
| R-17 (round 3), R-18 (round 4): this record | NOTE | This section |
| R-18 (round 3): the quantity is parsed in three places, `plusOne`, `quantityProblem` and `draftQuantity` | NOTE | Recorded: their acceptance is equal today; for the next change of UXP-405 |
| R-16 (round 4): the 30 seconds' refresh falls due during the held read only when the page's start takes under 10 seconds; otherwise the focus alone proves P2 | NOTE | Recorded |
| R-17 (round 4): after a filter refused as UXS-05, "Muat ulang" asks for it again and is refused again, as the refresh does since P2 | NOTE | Recorded, intended: the field holds the refusal until the user changes it |

**Checks of the cycle** (CE-45). The fixes change `ReceivingCreate.tsx`, `Stock.tsx` and `lib/inventory/stock.ts` and add tests; no PHP and no PostgreSQL test file changes, so the fast runs' PHP results and the targeted PostgreSQL files of section 1 stay valid.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 2 November, 23:56 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard, preview on the working tree | Local | 3 November, 00:07 | under 1 min | `PASS`, no candidate |
| `npm run lint`, `typecheck`, `test:unit` with `receivingDraft.test.ts`; the PHP static checks and unit tests | Fast run 7 on the fix's commit | — | — | `NOT_RUN` at this writing; its result is in the executor's report |
| The browser suite with the new tests of P1, P2 and R-15, and the rest of the command contract and the image checks | CI on the pull request's new head | — | — | `NOT_RUN` at this writing (CE-41, D-117) |
