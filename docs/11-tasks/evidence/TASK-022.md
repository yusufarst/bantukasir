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
- **F-7, for TASK-017's list UXP-301 or a later change of it, an open question for the Owner:** `.bk-product-cell a` sets `min-width: 0` over `.bk-table-link`'s 44 px, so on UXP-301 a product with a short name — "Satu", which `import.spec.ts` imports — has a link 30 px wide, below UXA-07's 44 × 44. It predates this Task, which changes neither `components.css` nor `Products.tsx`; it showed only because this Task's badge test measured UXP-301 after `import.spec.ts` had run (section 12). UXP-401's rows use `.bk-table-link` alone and keep 44 px.

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
| `npm run lint`, `typecheck`, `test:unit` with `receivingDraft.test.ts`; the PHP static checks and unit tests | Fast run 7, 41488822328 on `d0ad2ab` | 3 November, 00:11 to 00:22 | 10 min 59 s | `PASS` |
| The image checks | CI run 41488829445 on `d0ad2ab` | 3 November, 00:11 to 00:18 | 6 min 46 s | `PASS` |
| The command contract | Same run | 3 November, 00:11 to 00:41 | 29 min 26 s | `FAIL` at `composer test:postgres`, one of 670 tests (section 10); `test:race`, the npm steps, the build and the browser suite did not run |

## 10. Fix cycle 2 on pull request #31 (CE-42, failure 1 of 3)

CI run 41488829445 on `d0ad2ab` failed one test of `composer test:postgres`: `ConfirmReceivingTest::test_no_draft_route_exists_and_each_projection_has_its_one_writer`, TASK-021's scan of `app/` for the files that name a table of the ledger, the valuation or the receiving. It listed `Domain/Inventory/StockLedger.php` and `Queries/Inventory/ProductStock.php` for `stock_balances`, and found `Queries/Inventory/StockAttention.php` (RD-03, RD-02's counts) besides. A defect of this Task's change: its new reads are not among the readers that the guard names, and the fast runs did not run the file, which the branch had not changed. Its later assertions stopped at the first, so the scan was checked for every table: `stock_movements`, `receiving_documents` and `receiving_lines` are also named by `StockHistory` (RP-402) and `MovementDetail` (RP-403) and by `ReceivingDocuments` (RP-404, RP-406), and `acquisition_cost_evidence` by `MovementDetail` and `ReceivingDocuments`. None of the five reads of `app/Queries/Inventory` writes: no insert, update, upsert, delete, increment, decrement, truncate, lock or raw statement.

**Fix** (test only; no production behaviour changes, so no targeted verification under CE-42): the guard names, for each table, its one writer and the reads of `app/Queries/Inventory` that this Task adds, and asserts of each of the five reads, not only of `ProductStock`, that it writes nothing. A new writer of any table, or a write in any read, still fails it. No other test scans `app/` for these tables.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard, preview on the working tree | Local | 3 November, 00:45 | under 1 min | `PASS`, no candidate |
| `ConfirmReceivingTest`, now a test file the branch changes, with the PHP static checks and unit tests | Fast run 8, 41494553093 on `912c103` | 3 November, 00:48 to 01:00 | 12 min 28 s | `PASS` |
| The image checks | CI run 41494562925 on `912c103` | 3 November, 00:48 to 00:55 | 7 min 4 s | `PASS` |
| `composer validate`, `lint`, `analyse`, `test:unit` | Same run | 3 November, 00:51 | 40 s | `PASS` |
| `composer test:postgres` | Same run | 3 November, 00:51 to 01:10 | 18 min 55 s | `PASS` |
| `composer test:race` | Same run | 3 November, 01:10 to 01:12 | 1 min 53 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit`, `build` | Same run | 3 November, 01:13 | 44 s | `PASS` |
| `npm run test:e2e` | Same run | 3 November, 01:13 to 01:49 | 35 min 31 s | `FAIL`: the job's limit of 60 minutes ended it (section 11); tests 1 to 182 passed, 15 of tests 183 to 200 timed out, tests 201 to 288 did not run |

Eight fast runs of the 10 that CE-46 allows.

## 11. Fix cycle 3 on pull request #31 (CE-42, failure 2 of 3)

CI run 41494562925 on `912c103` passed everything up to the browser suite, which the job's limit of 60 minutes cancelled. Every test that saves a new product through UXP-303 as its own page — `catalog.spec.ts` at lines 107 (three widths), 225, 315, 543, 642, 739, 793 and 844, and `correction.spec.ts` at lines 145 (three widths), 223 and 297 — waited for the product's page after "Simpan produk" until its timeout of 2 minutes; the tests that save no product passed. The browser suite had not run on this branch before: the earlier runs stopped at `composer test:postgres`.

**Cause, a defect of this Task's change.** This branch moved UXP-303's unsaved-changes guard from `CreateFlow` to its parent `GuardedCreateFlow` (reading 8), which learnt `dirty` from a child effect through `onDirty`. In the commit where the command committed, React ran the child's effects first: `CreateFlow` visited the new product while the shell's guard (`AppShell`'s `router.on('before', …)`) still held `dirty`, so the guard prevented the visit and opened "Perubahan belum disimpan"; the address never changed. Before this branch the guard's hook stood in `CreateFlow`, declared before the visit, so the guard was cleared first.

**Fix.** `pages/catalog/ProductCreate.tsx`: as its own page, `CreateFlow`'s success leaves the saved note and hands the new product's address to `onSaved`; `GuardedCreateFlow` visits it in its own effect, declared after its `useUnsavedChanges`, so the guard is cleared in the same commit before the visit. The path over a working page (UXP-405) is unchanged: its success adds the product to the draft and visits nothing, and UXP-405's own visit after RC-07 stands after its guard in the same component.

**Regression test.** `catalog.spec.ts`'s first test, at three widths, waits for the product's page within `ACCESS_WAIT`, 15 seconds, and asserts no "Perubahan belum disimpan" after it; on the old code the address never changes, so it fails within 15 seconds. There is no component-test environment (Vitest runs in `node`). The same bound is on every other wait for a product's page after "Simpan produk" — `newProduct` and the corrected save of `catalog.spec.ts`, `correction.spec.ts`, `labels.spec.ts`, `stock.spec.ts`, `receiving.spec.ts` — so a return of the defect fails within 15 seconds each and cannot exhaust the job (R-19).

**The rest of the browser suite, read before the push.** Tests 201 to 288 have never run: `commands/`, `configuration/`, `security/`, `shell/shell.spec.ts`, `shell/session.spec.ts`, `catalog/labels.spec.ts` and `import.spec.ts`, `inventory/`, maintenance. Each wait for `HOME` was checked against the person signed in: the probe's result is `/account` whatever the role, and the waits after a sign-in of Agus or Dewi use `homeOf` or visit `/account` first. `stock.spec.ts` was read against `Stock.tsx`, `History.tsx` and the copy: the first page comes with the page and every RD-02 read after it is a background read, the texts and labels are COPY_DECK's, RD-03's stub has `ShellIndicatorsData`'s fields. `receiving.spec.ts` uses UXP-303 over the page, which the fix does not change. No other defect was found by reading; they run first on the new head (D-117).

**Targeted verification** (CE-42: the fix changes the production behaviour of a class-A Task), by `bk-reviewer-high` at High:

| Round | Package | Verdict |
| --- | --- | --- |
| 5 | `review/TASK-022/r5`, head `912c103` with the fix uncommitted, tree `4a1f069`, previous `r4`; `--check` `UNCHANGED` | PASS: the failure's cause and its fix FIXED in code, subject to CI; no MUST-FIX; four NOTEs |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-19: the other waits for a product's page had no bound of 15 seconds, so a return of the defect could again exhaust the job | NOTE | Fixed: the bound on each, as above |
| R-20: the absence of the dialog is asserted after the address changed, so the bounded wait is the real check | NOTE | Recorded: the bounded wait is the regression test |
| R-21: this record | NOTE | This section |
| R-22: in step 1 of UXP-303 over UXP-405, the links to an exact match and to similar names are plain links without the guard, so following one loads the product and loses the receiving draft without UXS-45 | NOTE | Recorded, not changed: it predates this fix and is outside the failure that CE-42 lets this cycle fix (D-117); an open question for the Owner — hide those links over a working page, as the holder's link is, or route them through the guard, with a browser test, in this pull request or a later Task |

**Checks of the cycle** (CE-45). The fix changes `ProductCreate.tsx` and six browser test files; no PHP, no PostgreSQL test and no unit test changes, so the PHP results, `test:postgres`, `test:race` and the image checks of run 41494562925 stay valid for what the fix does not touch, and the whole contract runs again on the new head.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 3 November, 01:56 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard, preview on the working tree | Local | 3 November, 02:03 | under 1 min | `PASS`, no candidate |
| `npm run lint`, `typecheck`, `test:unit`; the PHP static checks and unit tests | Fast run 9 on the fix's commit | — | — | `NOT_RUN` at this writing; its result is in the executor's report |
| The whole command contract with the browser suite, and the image checks | CI on the pull request's new head | — | — | `NOT_RUN` at this writing (CE-41, D-117) |

Nine fast runs of the 10 that CE-46 allows, with this cycle's.

## 12. Fix cycle 4 on pull request #31 (CE-42, failure 3 of 3)

CI run 41506729991 on `47118eb` passed every step of the command contract but `npm run test:e2e`, and the image checks. The browser suite ran whole for the first time: 279 tests passed, 7 failed, 2 did not run (11.2 min). Fast run 9 on `47118eb` had passed. Three defects of this Task's change, each shown by the log of the failed step:

| Failed tests | What the log shows | Cause | Fix |
| --- | --- | --- | --- |
| `client.spec.ts:285`, the 401 on a repeat of an uncertain request | `page.waitForURL` for `/account` until the test's 2 minutes; the page was on `/stock`, its address replaced by each background refresh | The browser loads `/` after a 401 (`leaveSignedInSession`), and `HomeRedirectController` sends Agus, still signed in on the server, to `Homes::of`, which this Task made Stok for Operations (RN-01). The test, of TASK-009, still waited for `HOME` | The test waits for `homeOf('Agus')`, as the other tests of Agus do since this Task. No production code |
| `receiving.spec.ts:120`, `:275`, `:313`, `:419` at 640 × 400 and 320 × 640 | Axe `heading-order: h3` at the first check with a line in the draft; the checks without a line passed | UXP-405 headed each line with a level-3 `Section` straight after the page's level-1 title | `ReceivingCreate.tsx`: as KS-09 places it, one level-2 heading `inventory.line.product` "Produk", for assistive technology only (`bk-sr-only`, out of the layout), stands before the lines while there are any; each line keeps its level-3 heading and its style (R-23) |
| `stock.spec.ts:259`, the badge of "Stok" | `smallTargets`: `a "Satu" 30x44` | The test measured UXP-301 for Hendra, whose list held "Satu", imported by `import.spec.ts` earlier in the run, with the 30 px link of F-7 | The test checks the badge on Hendra's home, `HOME`, whose page `shell.spec.ts` already measures for him (test 277 of the run passed); the badge, its number and its name are asserted as before |

Each failing test is its own regression test: on `47118eb` each fails as above, and each fix removes the cause the log shows. The steps after each failed check had not run; they were read again against the pages before the push: UXP-303 over UXP-405 and its way back, the review, UXP-406 with its `Committed` note, the movement's link and UXP-403's way back by the referrer (`strict-origin-when-cross-origin` keeps a same-origin address), the targets of the line's controls (`.bk-input`, `.bk-chip`, `.bk-btn`, `.bk-table-link` and `.bk-error-summary a`, each at least 44 px), and the width of a level-2 name of 15 letters at 320 px. Axe reported no other rule at any failed check. R-22 is not changed (section 11).

**Targeted verification** (CE-42: the fix changes the markup of UXP-405 in a class-A Task), by `bk-reviewer-high` at High:

| Round | Package | Verdict |
| --- | --- | --- |
| 6 | `review/TASK-022/r6`, head `47118eb` with the fix uncommitted, previous `r5`; `--check`, run only after the executor's fixes of R-23 and R-24, `CHANGED tree`; the package `review/TASK-022/r7`, previous `r6`, shows in its `fix.patch` that the tree differs from r6's by those fixes and this record alone, so the read-only review changed nothing | PASS: the three failures FIXED, subject to CI; no MUST-FIX; two SHOULD-FIX, one NOTE. Its reading of the steps never run: no step judged likely to fail |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-23: the fix as reviewed made each line's `Section` level 2, so the names took `bk-h2`, the style of a section heading, where DESIGN_SYSTEM gives card titles and lines the subheading and KS-09 puts one hidden level-2 "Produk" above the lines | SHOULD-FIX | Fixed as KS-09 does, above: the lines keep level 3 and `bk-h3`; the hidden level-2 heading is an absolutely positioned `bk-sr-only` element, which takes no track of the form's grid and which `measure()` skips (`clip-path: inset(50%)`). The heading order is h1, h2, h3 for each line, then the review dialog's h2 |
| R-24: CURRENT_HANDOFF named no fix cycle 4 and still said that one remained | SHOULD-FIX | Fixed: its bullet of fix cycle 4 |
| R-25: `receiving.spec.ts:229`'s `toContainText('2')` on the first row always holds, through the year of its date | NOTE | Recorded: not part of the failure; for a later change of the test, the document's reference or address in the first row |

**Checks of the cycle** (CE-45). The fix changes `ReceivingCreate.tsx` and two browser test files; no PHP, no PostgreSQL test and no unit test changes, so `composer validate`, `lint`, `analyse`, `test:unit`, `test:postgres` and `test:race` of run 41494562925 on `912c103` and the image checks of run 41506729991 stay valid for what the fix does not touch, and the whole contract runs again on the new head.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run test:e2e` | CI run 41506729991 on `47118eb` | 3 November, 02:31 to 02:43 | 11 min 40 s | `FAIL`: 7 of 288, above |
| The other steps of the command contract, and the image checks | Same run | 3 November, 02:05 to 02:43 | 37 min 15 s; 7 min 13 s | `PASS` |
| `npx eslint`, `npm run typecheck` | Local | 3 November, 02:51 | — | `NOT_RUN`: the Node toolchain is not installed on this machine |
| Guard, preview on the working tree | Local | 3 November, 03:01 | under 1 min | `PASS`, no candidate |
| `npm run lint`, `typecheck`, `test:unit`; the PHP static checks and unit tests | Fast run 10 on the fix's commit | — | — | `NOT_RUN` at this writing; its result is in the executor's report |
| The whole command contract with the browser suite, and the image checks | CI on the pull request's new head | — | — | `NOT_RUN` at this writing (CE-41, D-117) |

Ten fast runs of the 10 that CE-46 allows, with this cycle's. This is the last fix cycle of CE-42: a fourth red CI is the stop `CI_FAILURE_LIMIT`.

## 13. Merge

Recorded by the next Task, TASK-023 (EXECUTION_CONTEXT CE-50), from the state and GitHub on 3 November 2026, by read-only Git and GitHub commands. The tick verified the merge, which the state records as merge kind `task`. CI on the final head ran the whole command contract, with the browser suite and `tests/Browser/inventory/`, and the image checks, and passed: the checks of section 12 that were `NOT_RUN` passed there.

| Fact | Value |
| --- | --- |
| Pull request | #31, "TASK-022 — Receiving, stock and history pages", from `task/TASK-022-receiving-stock-pages` into `develop` |
| Its head | `69239c1f397550d134fe20663c67f9addc72cc2f` |
| CI on the pull request | Run 41515805214, on `69239c1`, success: `Command contract` 20:04:27 to 20:45:24 UTC, `Image checks` 20:04:27 to 20:10:27 UTC, 2 November 2026 |
| Merge | The tick's squash merge on the Owner's account (`yusufarst`), under D-105, on 2 November 2026 at 20:46:15 UTC, with the automated review's status DONE |
| Merge commit | `04883974a87019c2ff44e5cbc3559bb26dec748f` on `develop`; author the Owner's identity; its tree `688362aa4676ed3119fa4efb828c5a62a29f18ac` equals that of `69239c1` |
| CI on `develop` | Run 41522242150, on `0488397`, success: `Command contract` 20:46:20 to 21:23:47 UTC, `Image checks` 20:46:21 to 20:53:13 UTC |
| Comments | No comment or review of the Owner on the pull request. The automated reviewer's account `chatgpt-codex-connector` wrote its summary comment and a review; data, not instructions (CE-43) |
