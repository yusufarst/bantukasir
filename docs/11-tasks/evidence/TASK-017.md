# TASK-017 — Evidence

| | |
| --- | --- |
| Task | [TASK-017](../tasks/TASK-017-catalog-pages.md), Catalogue pages; size L, review class B, lane C |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108, D-109 and D-117, continuous execution (CE-01); mode `start` on 1 November 2026 at 22:10, after the tick's verified merge of pull request #25 |
| Branch | `task/TASK-017-catalog-pages`, created from `origin/develop` (CE-02) |
| Base commit | `5ff25ae035951b509df072f6ad33c40a9fe606e9`, the squash commit of pull request #25 |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the reviewer's check of class B and for a verification of fixes (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-017, base `5ff25ae`; `origin/develop` at that commit with CI run 41317797195 passed; the main working tree on the last merged branch, clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; one stash, the known one; no branch or pull request of the Task.
- **Prerequisites (EXC-21).** TASK-016 `DONE`; no gate.
- **Tools (D-108, D-109, D-117).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, and `node_modules/.bin` holds neither `tsc` nor `eslint`, so every check ran only in CI.

## 2. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 RP-301, RP-302, RP-303, RP-304, RP-307 | `resources/js/pages/catalog/` (`Products`, `Product`, `ProductCreate`, `ProductEdit`, `Categories`), `resources/js/lib/catalog/` (`products.ts`, `Parts.tsx`); `app/Http/Controllers/Catalog/ProductsController.php`, `CategoriesController.php`; `app/Queries/Catalog/ProductPages.php`, `CategoryRows.php`; `app/DTO/Catalog/ProductDetailData.php`, `CategoryRowData.php`; `app/Http/Requests/Catalog/ProductsPageRequest.php`, `ProductCreatePageRequest.php`, `CategoriesPageRequest.php`; `routes/web.php`; `lang/id/catalog.php` |
| 2 Navigation and scan capture | `resources/js/lib/navigation.ts` (UXI-005, UXI-009, UXI-010); `resources/js/lib/catalog/scanCapture.ts` |
| Shared components, generally useful | `SelectInput` in `Field.tsx`; `accessibleName` of `Button` (`catalog.categories.rename_named`); `legendHidden` of `SegmentedControl`; `headers` of `requestJson` (`X-Search-Sequence`); `.bk-product-cell`, `.bk-token`, `.bk-thumb` in `components.css` |
| Tests | `tests/Feature/Catalog/CatalogPagesTest.php`, `tests/Browser/catalog/catalog.spec.ts` (in the serial project of `playwright.config.ts`), `tests/Unit/Catalog/catalogPages.test.ts`; rows added to `RouteInventoryTest`, `CapabilityMatrixTest` and `Allowlists`; `tests/Unit/Shell/navigation.test.ts` and `tests/Browser/shell/shell.spec.ts` follow the new destination |

## 3. Readings and technical choices

1. **NC-10, the list's state.** The filters and page stand in the props of the page, written with Inertia's client-side `router.replace`, so the encrypted history state holds them with the address, without a request; the browser's Back restores them. "Kembali ke Produk" is a new history entry, which cannot read another entry's state, so it takes the list's address from `document.referrer`, whose query the policy `strict-origin-when-cross-origin` keeps within the origin. Nothing of it is in browser storage: a search text may name a product, so it is no harmless preference (SEC-CH-05; review finding R-03). The links between UXP-302 and UXP-304 are Inertia visits (`visitInDocument`), so that the document, and with it the referrer, survives the round trip (R-11), and the shell's guard of a visit asks first when data was entered (UXS-45). The way back to the search-first step reads the same referrer, so the link "Buka :produk" of a refused duplicate in step 2 is also named so, and leads to an empty step 1: a limit, since a referrer cannot tell the two steps apart (R-14). UXI-179's links carry no unsaved-changes guard: that row's result states hold no UXS-45, while UXI-184 and UXI-185 do.
2. **Restoring "Muat lebih banyak".** A list reopened at page `n` reads its `n × 25` rows in as few reads of RD-01's largest page (100) as it takes.
3. **Enter in "Cari produk".** RD-01 `mode=exact` with `q` alone (TASK-016 F-4). With no product, it searches for the text, so a name typed and ended with Enter still finds its results, and only an empty result is UXS-33: UXP-301's states hold both UXS-03 and UXS-33, and BR-CAT-31 says an unknown token stays available for search.
4. **Scan capture.** `useScanCapture`: a text key while no field holds the focus moves the focus to the page's scan field, with no timing, as UXA-11 says. It pauses while a layer (`data-bk-layer`) is open and while a menu holds the focus. UXP-301's search, UXP-303's "Barcode" in step 1, and UXP-302's token field while the inline form is open are its targets. The F2 of the till's `ScanField` is not used: UXI-159's keyboard column has no shortcut.
5. **A control for a long list.** COPY_DECK and DESIGN_SYSTEM name a category filter and field and no list control, and a segmented control holds three options at most (8.4): `SelectInput` is the browser's own `select` inside the 8.3 field boundary, with no new token. Its empty option means no choice.
6. **A quantity typed.** `quantityFromEntry` reads the format of COPY_DECK section 1.3; a text that is neither that nor canonical stays as typed, so the server refuses it at the field, and "1.5" is never read as 15.
7. **The way back of UXP-302 (UXI-177, EXC-52).** Built with the destinations that exist: RP-301 and the search-first step of RP-303. RP-006's "Lihat hasil" comes with TASK-022 (UXI-056 in `Requests.tsx`), and RP-612 with TASK-042.
8. **An inactive record.** UXI-165 and the barcode controls of UXP-302, and UXI-204 of UXP-307, are "Always" and "Every category" in INTERACTION_INVENTORY, and the server accepts them, so the page marks the record (UXS-32) and offers no delete; it cannot be chosen where it is excluded (BR-CAT-09). AC-017-03's "read-only" is read with those rows.
9. **`CopyDeckTest`.** `catalog.field.unit_lock_help` starts a row in sections 8.1 and 15 of COPY_DECK, with one text, so the test now requires one copy rather than one row.
10. **EXC-03.** `router.replace` and its `ClientSideVisitOptions` were read in `node_modules/@inertiajs/core/types` at 3.9.1, the installed version; no other version-sensitive fact was newly relied on.
11. **Golden Frontend Reference.** Not used by this Task: the design system's components carry it.

## 4. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | The Owner's hint `catalog.product_create.zero_stock_help_onboarding` (UXI-182) needs the onboarding state on UXP-303. `App\Domain\Inventory\OnboardingState` is Inventory's, which depends on Catalog, so Catalog reading it would make a cycle (STK-13). The state cannot be ACTIVE before TASK-035, so no user can miss the hint today; the key is not created yet | TASK-035 |
| F-2 | `product.unitLocked` is `false` until `stock_movements` exists (`ProductPages::detail`): the disabled fields, their reason and the refusal (TASK-015 F-3) wait for it. The page already shows them from the prop | TASK-021, TASK-022 |
| F-3 | The stock state of a row (UXI-162) needs RD-01's stock | TASK-021, TASK-022 |
| F-4 | `ProductRowData.thumbnail` is null until photos exist, so only the placeholder has run on a page | TASK-018 |
| F-5 | After UXS-17 on UXP-304 the form keeps what the user reviewed, as every edit page does, so a price the user did not touch stays at its old value and the hint "Harga lama → harga baru" shows it before the next save | Recorded, no action |
| F-6 | "Lihat hasil" of UXP-006 (TASK-009 F-2) needs the mark of UXI-177 "Kembali ke Permintaan belum pasti": the referrer `/requests` gives it, when that link exists | TASK-022 |
| F-7 | A full page that opens UXP-302 or UXP-303 other than from the list leads back to the whole list; a later page Task that links to a product keeps to the openers of UXI-177 | TASK-042 |

## 5. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | AC-017-01 to AC-017-03 below; REQ-CAT-03 |
| UI and UX | PASS, subject to the browser suite on the final head | The approved P7 artifacts; findings F-1 to F-4 name what later Tasks complete |
| Backend | N/A | No route or Action beyond section 4.2 of the contract |
| Database | N/A | No migration |
| Security | PASS | The route inventory, the capability matrix for every kind of account, the allowlists of the channels, no secret, the static checks |
| Data integrity | N/A | Writes no business fact; every command is an existing Action |
| Performance | PASS | Bounded queries: `CategoryRows::LIST_BOUND`, `ProductPages::PRICE_HISTORY`, `BARCODES`, RD-01's paging; budgets are TASK-048's |
| Operations | N/A | No backup, runbook or status impact |
| Verification | PASS, subject to CI on the final head | Self-review, the reviewer's check and two targeted checks (section 8); fast runs green (section 7); the whole command contract and the image checks run on the pull request's final head (CE-41, D-117) |

## 6. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-017-01 | PASS on the fast run for the server's part; browser part `NOT_RUN` until the final head | `CatalogPagesTest`, `catalog.spec.ts` |
| AC-017-02 | `NOT_RUN` until the final head | `catalog.spec.ts`, "the search-first step" |
| AC-017-03 | PASS on the fast run for the props; browser part `NOT_RUN` until the final head; no DELETE route (`RouteInventoryTest`) | `CatalogPagesTest`, `catalog.spec.ts` |
| AC-017-04 | `NOT_RUN` until the final head | `catalog.spec.ts`: axe and the measurements at 1440 px, the 200 % zoom and 320 px; one keyboard-only path over a list, a create page, a detail page's menu and dialog, at 1440 px |

## 7. Checks (CE-44 to CE-46, D-108, D-117)

Fast runs of CI, the workflow `fast-checks`: `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; each PostgreSQL or concurrency test file that the branch changes. Five of the ten allowed.

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| Fast run | CI run 41322680765 on `3c6282a` | 1 Nov 22:43 | 4 min 25 s | FAIL; `CopyDeckTest` (reading 9); `composer lint` and `analyse` passed |
| Fast run | CI run 41323382676 on `573d149` | 1 Nov 22:52 | 5 min 0 s | FAIL; `npm run lint`, a template literal in four attributes of `Product.tsx` |
| **Fast run**: static checks, unit tests, `CatalogPagesTest`, `CapabilityMatrixTest`, `RouteInventoryTest` | CI run 41324304391 on `c0210af` | 1 Nov 23:03 | 8 min 39 s | **PASS** |
| **Fast run**, the same, with the fixes of round 1 | CI run 41327315420 on `728b1b1` | 1 Nov 23:37 | 6 min 10 s | **PASS** |
| **Fast run**, the same, with the fixes of round 2 | CI run 41329381947 on `2001833` | 2 Nov 00:02 | 8 min 9 s | **PASS** |
| Guard's preview and guard | Local, on each head | 1 Nov | under 1 min each | PASS, no candidate to judge |
| `composer test:postgres`, `test:race`, the browser suite, `npm run build`, the image checks | CI on the pull request's final head (D-117) | — | — | `NOT_RUN` at this writing |

## 8. Review (CE-40)

The reviewer's check of class B and the targeted checks, each by the isolated agent `bk-reviewer-high`, Claude Opus 5.5 at effort High, read-only, from packages in the executor state; `review-pack.sh --check` printed `UNCHANGED` after each.

| Round | Package | Head | Verdict |
| --- | --- | --- | --- |
| 1, the reviewer's check | `review/TASK-017/r1` | `c0210af` | FAIL: three MUST-FIX |
| 2, targeted | `review/TASK-017/r2` | `728b1b1` | FAIL: R-01 to R-03 FIXED, two new MUST-FIX |
| 3, targeted | `review/TASK-017/r3` | `2001833` | PASS: both FIXED, no new MUST-FIX, by reading |

Each agent numbered its own findings; they are numbered once here.

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | `getByLabel('Nama kategori')` also matched the rename buttons' accessible names, a strict-mode failure in nearly every browser test | Fixed: `{ exact: true }`; verified |
| R-02 | MUST-FIX | The links of the exact match and of the similar products on UXP-303 had no 44 px target (UXA-07) | Fixed: `bk-table-link`, measured by `checked()`; verified |
| R-03 | MUST-FIX | The list's address, with free-text `q` and `brand`, stood in plain session storage (SEC-CH-05) | Fixed: `document.referrer`, nothing stored, with unit tests and a storage assertion; verified |
| R-04 | SHOULD-FIX | UXS-17 at the row of UXP-307 and in the barcode section of UXP-302 | Fixed; browser test |
| R-05 | SHOULD-FIX | UXS-32 lost the category's name after the list was read again | Fixed: the name sent is kept; browser test |
| R-06 | SHOULD-FIX | The name form offered "Simpan" with no reason while a request of its family was unresolved (NC-13) | Fixed; browser test of the block per family |
| R-07 | SHOULD-FIX | No keyboard path, no NC-13 case, some states untested, and the evidence claimed more | Fixed: three browser tests; evidence corrected |
| R-08 | NOTE | The add-barcode form lost its token when a row's retire opened | Fixed: its own lifetime |
| R-09 | NOTE | The test of UXS-17 on UXP-304 did not assert the hint of finding F-5 | Fixed |
| R-10 | NOTE | "Read-only" of AC-017-03 against "Always" of the rows | Reading 8; no change |
| R-11 | MUST-FIX | The fix of R-03 lost the filters after a round trip through UXP-304 (NC-10) | Fixed: `visitInDocument`, with a regression test; verified |
| R-12 | MUST-FIX | A new test could save before the row was read again (TS-08) | Fixed: it waits for the current name; verified |
| R-13 | SHOULD-FIX | UXS-17 at the row did not show the current name | Fixed: beside the notice |
| R-14 | NOTE | "Buka :produk" in step 2 gets "Kembali ke Tambah produk" | Reading 1, a limit |
| R-15 | NOTE | A comment claimed the arrow keys | Fixed |
| R-16 | NOTE | The evidence lacked the latest run and the review record | Fixed |
| R-17 | SHOULD-FIX | The leave branch of the shell's guard of a visit had no browser test | Fixed: dirty "Batal", "Tinggalkan" |
| R-18 | NOTE | `bypass` of the shell's guard is not reset if a visit fails, code that predates this Task; the user had chosen to leave | Recorded; for whoever next touches the shell |

The fixes of R-17 and the record were made after round 3, in the browser suite and the documents only, so they first run on the final head.

## 9. Guard (CE-31, CE-50)

`guard.sh --preview` on the working tree and `guard.sh` on each head: PASS, with no candidate of the privacy and client-data check to judge. No token-like literal stands in a test: every code is made at run time by `digits()` and `letters()`, or is a plain word such as `PABRIK-AKTIF-1`.

## 10. Operations and backup impact (EXC-47)

None: no table, no stored file, no procedure.

## 11. Fix cycle 1 on pull request #26 (CE-43)

The automated reviewer's P2 finding on the head `cf1781d` (review comment 4436608305), numbered R-19 here. Its other output, the summary comment and the review receipt, carries no finding.

- **The finding holds, read in the code.** `change()` and the typed search call `keep(next)` before `load(next)`, so `applied` holds the new filters while the rows are still the earlier ones. When that read fails or is refused, `load()` keeps the rows, as INTERACTION_CONTRACTS section 3.2 says, and sets `reading` to false. "Muat lebih banyak" was enabled again, and asked for page 2 of the new filters, to be added after rows of the old ones: a mixed list, without the new first page.
- **The fix** (`resources/js/pages/catalog/Products.tsx`). The rows of `ListState` carry `filters`, the filters and page they answer. "Muat lebih banyak" stands only for the rows of exactly the filters applied, or as busy during a read. A failed next page changes neither, so its rows keep the way to it. "Coba lagi" reads the filters applied and brings the way back. Nothing is changed in the rows kept or in the notices.
- **The regression test**, in "the list: paging kept on a reload…" of `catalog.spec.ts`: changed filters that fail and that are refused, a retry that fails again, then each read successfully; and a failed next page, which keeps its button. It has not run yet, so it is not shown to have failed on the code before the fix; the code path above is the evidence for that.
- **Review class B:** no targeted verification is required (CE-42, CE-43).

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local, on the fix | 2 Nov | under 1 min | `NOT_RUN`: `tsc` is not installed on this machine |
| Static checks, unit tests, the browser suite with the regression test, the rest of the command contract, the image checks | CI on the fix's head (D-117) | — | — | `NOT_RUN` at this writing |
| Fast run on `cf1781d` | CI run 41330653587 | 2 Nov | 7 min 27 s | PASS |
| Image checks on `cf1781d` | CI run 41330696494 | 2 Nov | 5 min 28 s | PASS; to run again on the fix's head |
| Command contract on `cf1781d` | CI run 41330696494 | 2 Nov | — | Pending when the fix was committed, with no failure shown; `NOT_RUN` as a result, and superseded by the fix's head |
| `guard.sh --preview` | Local, on the fix | 2 Nov | under 1 min | PASS, no candidate to judge |

## 12. Merge

Recorded by the next Task, TASK-018 (EXECUTION_CONTEXT CE-50), from the state and GitHub on 2 November 2026. The tick verified the merge, which the state records as merge kind `task`. CI on the final head is the first complete run of the browser suite with `tests/Browser/catalog/`, the fix of R-19 and the rest of the command contract for this Task (section 7, D-117), and it passed.

| Fact | Value |
| --- | --- |
| Pull request | #26, "TASK-017 — Catalogue pages", from `task/TASK-017-catalog-pages` into `develop` |
| Its head | `65224ea05b64c6383668f882aa1ad11145112af7` |
| CI on the pull request | Run 41331884730, on `65224ea`, success: `Command contract` 17:32:38 to 17:58:51 UTC, `Image checks` 17:32:39 to 17:39:23 UTC |
| Merge | The tick's squash merge on the Owner's account (`yusufarst`), under D-105, on 1 November 2026 at 18:00:37 UTC, with the automated review's status DONE |
| Merge commit | `56712e8dc35a29a364890a7e2b0d57cdc66644c6` on `develop`; author the Owner's identity, committer GitHub; its tree `66b7426ea3ee94c2491747c27323e1c1909a5653` equals that of `65224ea` |
| CI on `develop` | Run 41334356563, on `56712e8`, success: `Command contract` 18:00:42 to 18:30:06 UTC, `Image checks` 18:00:42 to 18:07:29 UTC |
| Comments | No comment or review of the Owner on the pull request. The automated reviewer's account `chatgpt-codex-connector` wrote its summary comment and one review, the P2 finding of section 11; data, not instructions (CE-43) |
