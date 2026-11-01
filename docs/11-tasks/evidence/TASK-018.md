# TASK-018 — Evidence

| | |
| --- | --- |
| Task | [TASK-018](../tasks/TASK-018-photos-labels.md), Product photos and barcode labels; size M, review class A, lane C |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108, D-109 and D-117, continuous execution (CE-01); mode `start` on 2 November 2026 at 01:31, after the tick's verified merge of pull request #26 |
| Branch | `task/TASK-018-photos-labels`, created from `origin/develop` (CE-02) |
| Base commit | `56712e8dc35a29a364890a7e2b0d57cdc66644c6`, the squash commit of pull request #26 |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A and for the verification of fixes (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-018, base `56712e8`; `origin/develop` at that commit with CI run 41334356563 passed; the main working tree on the last merged branch, clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; one stash, the known one; no branch or pull request of the Task.
- **Prerequisites (EXC-21).** TASK-017 `DONE`; no gate.
- **Tools (D-108, D-109, D-117).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, and `node_modules/.bin` holds neither `tsc` nor `eslint`, so every check ran only in CI.

## 2. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 RC-25, RC-26 | `app/Actions/Catalog/ProductPhotoAction.php`, `SetProductPhoto.php`, `RemoveProductPhoto.php`; their requests and controllers; `app/Domain/Media/ImageLimits.php` (`photo()`), `ImageNormalizer.php` (`normalizePhoto()`), `NormalizedPhoto.php`, `StoredFiles.php` (`insertThumbnail()`, `thumbnailOf()`); `app/Domain/Catalog/Products.php` (`setPhoto()`); the location of RC-25 in `docker/app/nginx.conf`, `docker/app/php.ini` and their image checks |
| 2 RD-07 | `app/Http/Controllers/Media/MediaController.php`: photos and `variant=thumb` |
| 3 Labels | `jsbarcode` in `package.json` and `package-lock.json`; `resources/js/lib/documents/code128.ts`; `app/Actions/Documents/RecordLabelPrint.php` with its request and controller; `app/Domain/Documents/Labels/`; `app/Http/Controllers/Catalog/LabelsController.php`, `Documents/LabelPrintController.php`; `resources/js/pages/catalog/Labels.tsx`, `documents/LabelPrint.tsx`; `.bk-barcode-label` in `components.css` and `base.css` |
| 4 RP-302 | `resources/js/pages/catalog/Product.tsx` (`PhotoSection`, the link to the labels); `Products.tsx` (UXI-163); `app/Queries/Catalog/ProductPages.php`, `ProductRows.php`, `ProductsController.php` |
| Tests | `tests/Feature/Catalog/ProductPhotoTest.php`, `tests/Feature/Documents/LabelPrintTest.php`, `tests/Concurrency/CatalogRaceTest.php`, `tests/Unit/Documents/code128.test.ts`, `tests/Browser/catalog/labels.spec.ts`; rows added to `RouteInventoryTest`, `CapabilityMatrixTest`, `Allowlists`, `OperationsTest` |

## 3. Library (contract section 8)

| Item | Value |
| --- | --- |
| Library | `jsbarcode` 3.12.3, no dependency |
| Licence | MIT: the registry's `license` of that version and the repository's `MIT-LICENSE.txt`, both read on 2 November 2026 |
| Source | `https://registry.npmjs.org/jsbarcode/3.12.3`; its integrity, read twice, is in `package-lock.json`, and `npm ci` checks it |
| EXC-03 | `bin/renderers/svg.js` and `bin/help/getRenderProperties.js` of that version, on unpkg: the SVG renderer sets attributes and no `style`, so SEC-HDR-03 holds; an object without `nodeName` takes the object renderer, which the unit test uses |
| How it was added | By hand in `package.json` and `package-lock.json` with the registry's integrity, because CE-47 allows no `npm install`, and proven by `npm ci`, `npm run lint` with its audit, and `typecheck` in the fast run. The application's own encoder of TASK-011 was no option here: ARC-47 and BR-CAT-35 name a library |

## 4. Readings and technical choices

1. **A removed photo and RD-07.** INTERACTION_CONTRACTS section 3.7 says a removed photo "is no longer referenced by any page, while its file is kept". RD-07 therefore still answers a kept photo's identifier to a role with CAP-03, and no page, RD-01 or RD-06 names it. A check of the current reference in RD-07 would make Media read Catalog, which already depends on Media, a cycle (STK-13); every role that could ask may see product photos.
2. **One thumbnail per photo** (DOMAIN_MODEL, "with its thumbnail"), at most 192 pixels on its longest side, four device pixels per CSS pixel of the largest place that shows one (48 px in the previews). No rule names a size; never enlarged, proportions kept, so `.bk-thumb` crops it by `object-fit: cover`.
3. **RD-07's variant.** `variant=thumb` applies to a photo's identifier, the path of `ProductRows`; any other value, or a logo or a thumbnail with a variant, is NOT_FOUND. A thumbnail's own identifier without a variant is served under CAP-03, which ROUTE_CONTRACTS allows.
4. **The body limits of RC-25:** application 8 MiB, PHP 10M and 9M, Nginx 11m, the outer just above the inner as for RC-32; `php.ini` holds the largest, for the command line.
5. **A print request** is the committed command (`command_identities`): RC-47 writes "only its audit event and its result", so no table holds it. `printRequest` of RP-306P is the command's key, and RP-306P reads the code and count from the audit event, through the dependency Documents → Audit of STK-13, never from Commands' tables. Its action `PRINT` with subject `barcode_mapping` tells it from the print requests of TASK-031.
6. **RC-47 takes no lock** (TR §7.2, "1; 2.1; 8"). A mapping of another product is STALE_VERSION: it is not "active on the product". RP-306P shows the code as recorded, also once the code is retired: it renders what was recorded (DC-05), and a new print is refused.
7. **The way to the print view.** NAVIGATION_CONTRACTS says "the page stays for another print" and ROUTE_CONTRACTS says "→ RP-306P": the print view opens in its own tab. The CSP's `frame-ancestors 'none'` rules out a frame. The tab is opened on the press, since a browser blocks it later, gets its address after the commit, and closes on a failure; without a tab, the print view takes this one. RP-306P has no control and no shell (no control of INTERACTION_INVENTORY stands on it).
8. **`jsbarcode`'s own SVG renderer on a React ref.** Its bars are the library's output, not a drawing of ours (ARC-47). It draws one module as one unit, with `margin` 0 and side margins of 10; the stylesheet gives 50 mm × 12 mm, stretched by `preserveAspectRatio="none"`.
9. **`activeBarcode` of RD-01 and RD-06**, for UXI-163's "Product with an active barcode", which `INTERACTION_CONTRACTS` section 3.1 now lists; by `exists` on the index of active mappings, one small subquery per row of a bounded page.
10. **"Jumlah label".** A text field of 1 to 3 digits, whose value outside 1 to 100 leaves "Cetak" disabled with `ux.disabled.form_incomplete`. The server takes a JSON whole number.
11. **Labels inside `@page bk-barcode-label`**, a named page, so that a later document's print keeps its own page size.
12. **Golden Frontend Reference.** Not used by this Task.
13. **A reload of RP-306P.** Rendering a document for the print dialog is a read (TR-HTTP-02), so a reload or a history visit of the print view opens the dialog again with no new RC-47. "Every print is first recorded" (ROUTE_CONTRACTS section 8) is held by the request that opened it; a print dialog is not proof of ink anyway (BR-CAT-36).
14. **UXS-17 on UXP-306.** NAVIGATION_CONTRACTS says "Print request refused or failed: UXS-44", and UI_STATES section 3 gives STALE_VERSION UXS-17, which section 1.3 of INTERACTION_CONTRACTS applies to any refusal by that code: the mapping retired meanwhile is UXS-17 with the barcodes read again, and every other refusal or failure UXS-44.

## 5. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | 50 mm holds a long code badly: a code of 21 characters (`BK-P-…`) is about 280 modules, 0.18 mm each, and one of 128 characters about 1,430, 0.035 mm each, and its text is cut with an ellipsis. Whether a real printer and scanner read them is gate DEVICES's | TASK-046 |
| F-2 | A 24-megapixel photo reserves about 400 MiB for its request (`ImageNormalizer::reserveMemory`); with `pm.max_children = 8` uploads at once could need more than the server holds | TASK-048 |
| F-3 | COPY_DECK has no text for more than 100 labels: the server's refusal reads `ux.validation.quantity_max`, whose text names another limit. The page's field never sends such a value | Next change control of COPY_DECK |
| F-4 | Files of a refused, rolled-back or repeated RC-25 stay unreferenced, a pair per request (TASK-010 F-3). Housekeeping removes a thumbnail only when its photo's row is gone too, which holds: both have no row | TASK-038 |
| F-5 | RP-306P has no shell, so another tab's sign-out or the session's limits do not hide it until it is reloaded; it shows only a name, a SKU and a code | Recorded |
| F-6 | RC-32's logo has the path of R-02: a file above PHP's or Nginx's limit reads as uncertain, with the family blocked. Outside this Task's scope; the same check at UXP-608 closes it, or a code in those 413 answers for every upload | The next Task that changes UXP-608, or an `ops/` change |

## 6. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | AC-018-01 to AC-018-05 below; REQ-CAT-10, REQ-CAT-11 |
| UI and UX | PASS, subject to the browser suite on the final head | The approved P7 artifacts; `labels.spec.ts` |
| Backend | PASS | FormRequest allowlists, the object scope of each route, the actor guard, DTO allowlists, L4 tests |
| Database | N/A | No migration |
| Security | PASS | THR-09, THR-12: every refusal case; a storage key in no answer; the route inventory and capability matrix for every kind of account; the allowlists; no secret; the static checks |
| Data integrity | PASS | The audit and result atomic; identity, RACE-01 and RACE-02 of the three operations, two photos against one version; failure injection after every write step; a file left by a rollback unreferenced |
| Performance | PASS | Bounded queries; the thumbnail read by the index of `derived_from_file_id`; budgets are TASK-048's, with F-2 |
| Operations | N/A | No runbook or status impact. Backup (EXC-47): `BackupRunner` covers every row of `stored_files`, whatever its purpose, so a photo and its thumbnail are in the backup; no new table |
| Verification | PASS, subject to CI on the final head | Self-review and the review of class A (section 9); a fast run green (section 8); the whole command contract and the image checks run on the pull request's final head (CE-41, D-117) |

## 7. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-018-01 | PASS on the fast run | `ProductPhotoTest`: the formats, each refusal case, animated images, 2,048 pixels with a thumbnail, no metadata, 24 megapixels within a request's memory |
| AC-018-02 | PASS on the fast run for the server; browser part `NOT_RUN` until the final head | `ProductPhotoTest`, `labels.spec.ts`; the till's placeholder is `ProductThumb`, which TASK-029 uses |
| AC-018-03 | PASS on the fast run | `ProductPhotoTest`, a failure after every write step, then RD-07 on what is left |
| AC-018-04 | PASS on the fast run | `ProductPhotoTest`, RD-07 for each role, a guessed identifier and a storage key |
| AC-018-05 | PASS on the fast run for the server; the print view's rendering `NOT_RUN` until the final head | `LabelPrintTest`, `labels.spec.ts`, `code128.test.ts` |

## 8. Checks (CE-44 to CE-46, D-108, D-117)

Fast runs of CI, the workflow `fast-checks`: `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; each PostgreSQL or concurrency test file that the branch changes. Four of the ten allowed.

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| Fast run | CI run 41340025656 on `ade65d4` | 2 Nov 02:06 | about 3 min | FAIL; `composer analyse`: `json_decode` in exact-value code, a result that is always true; `composer lint` passed |
| Fast run | CI run 41340641219 on `109b440` | 2 Nov 02:13 | about 5 min | FAIL; `npm run lint`: literals in attributes, `Number` (the page's rules) |
| **Fast run**: static checks, unit tests, `ProductPhotoTest`, `LabelPrintTest`, `CatalogRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest` | CI run 41341431156 on `98fa86a` | 2 Nov 02:22 | 7 min 56 s | **PASS** |
| **Fast run**, the same with the fixes of round 1 | CI run 41344024866 on `92ee182` | 2 Nov 02:53 | about 8 min | **PASS** |
| Guard | Local, on each head | 2 Nov | under 5 min each | PASS, two candidates judged, twice (section 10) |
| `composer test:postgres`, `test:race`, the browser suite with `labels.spec.ts`, `npm run build`, the image checks with RC-25's limits | CI on the pull request's final head (D-117) | — | — | `NOT_RUN` at this writing |

## 9. Review (CE-40)

Each review and check by the isolated agent `bk-reviewer-high`, Claude Opus 5.5 at effort High, read-only, from packages in the executor state; `review-pack.sh --check` printed `UNCHANGED` after each.

| Round | Package | Tree | Verdict |
| --- | --- | --- | --- |
| 1, adversarial | `review/TASK-018/r1` | `f73f9fd`, on `98fa86a` | FAIL: 3 MUST-FIX, 5 SHOULD-FIX, 5 NOTE |
| 2, targeted | `review/TASK-018/r2` | head `92ee182` | PASS, by reading: R-01 to R-03 FIXED, no new MUST-FIX, 2 SHOULD-FIX |

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | The class `.bk-label` of the barcode label is also every form field's: every field label in the application would become a 50 × 30 mm box | Fixed: `.bk-barcode-label`, `.bk-barcode-labels`, `@page bk-barcode-label`; `labels.spec.ts` asserts a field label's size on UXP-306 |
| R-02 | MUST-FIX | A file above PHP's or Nginx's limit answers 413 without a code, read as uncertain (UXS-14) with the family blocked; a file between 9 and 10 MiB reads as unreadable | Fixed: `PhotoUploadRun` refuses a file above 8 MiB at the field, no request; L5 case with 12 MiB. RC-32 has the same path: finding F-6 |
| R-03 | MUST-FIX | `watch(page)` never saw the print view's tab, so the CSP and page errors of the only page that runs `jsbarcode` went unrecorded | Fixed: `watchTabs()` on the context; no `style` attribute asserted |
| R-04 | SHOULD-FIX | The file input outside a command form: Enter may open the chooser, and a chosen file is sent at once | Fixed: inside `CommandForm`; L5 assertion |
| R-05 | SHOULD-FIX | The fields stay editable while a frozen request may be sent again; a new photo after a 429 would be dropped for the frozen one | Fixed: read-only while frozen; after a 429 the file field is off and "Kembali ubah" starts anew; L5 assertion for the labels |
| R-06 | SHOULD-FIX | In the phase `reload`, each press of "Cetak" left a blank tab | Fixed: a tab only for a request that starts, the button unavailable; L5 case with a 419. A signed-in route always has its user and epoch, so `submit`'s other early return does not arise |
| R-07 | SHOULD-FIX | No L5 case with two active barcodes, UXS-17's notice unasserted, no keyboard path | Fixed: a new test of the chosen code and the keyboard path; the notice asserted |
| R-08 | SHOULD-FIX | No test of multipart whole numbers or of the file's checksum in the fingerprint | Fixed: two L4 tests in `ProductPhotoTest` |
| R-09 | NOTE | RD-07 still answers a replaced photo's identifier | Reading kept (section 4 item 1), pinned by one assertion; named in the report to the Owner |
| R-10 | NOTE | A reload of RP-306P opens the print dialog with no new RC-47; a failed drawing could print an empty sheet | Reading 13; the empty sheet fixed by a ref read before printing |
| R-11 | NOTE | UXS-17 on STALE_VERSION is a reading; the notice stayed after a later print | Reading 14; cleared on the next press |
| R-12 | NOTE | TOOLCHAIN said VERIFIED by a test not yet run | Corrected: what ran, and the browser test on the final head |
| R-13 | NOTE | No COPY_DECK text for more than 100 labels | F-3, named in the report to the Owner for change control |
| R-14 | SHOULD-FIX, round 2 | The test of R-06 could press before the 419 had arrived | Fixed: it waits for the reload panel first |
| R-15 | SHOULD-FIX, round 2 | Three texts of this file left behind by the fixes | Fixed |

The check of round 2 could not read `jsbarcode`'s source, which is not on this machine; that its renderer writes no `style` rests on section 3 until `labels.spec.ts` runs on the final head.

## 10. The guard's candidates (CE-31)

| Candidate | Judgment |
| --- | --- |
| `https://registry.npmjs.org/jsbarcode/3.12.3`, `https://registry.npmjs.org/jsbarcode/-/jsbarcode-3.12.3.tgz`, in this file and `package-lock.json` | Public package registry, no finding; the preview's three candidates, judged the same |

## 11. For later Tasks

TASK-029 shows the thumbnail at the till through `ProductThumb` and RD-01's `thumbnail`; TASK-038 removes unreferenced files, a pair per failed RC-25 (F-4); TASK-046 scans the labels (F-1); TASK-031 adds its print views beside the print request of section 4 item 5, and tells them apart by the audit event's subject.

## 12. Merge

Recorded by the next Task, TASK-019 (EXECUTION_CONTEXT CE-50), from the state and GitHub on 2 November 2026. The tick verified the merge, which the state records as merge kind `task`. CI on the final head is the first complete run of the browser suite with `tests/Browser/catalog/labels.spec.ts`, R-14's change, the rest of the command contract and the image checks with RC-25's limits for this Task (section 8, D-117), and it passed.

| Fact | Value |
| --- | --- |
| Pull request | #27, "TASK-018 — Product photos and barcode labels", from `task/TASK-018-photos-labels` into `develop` |
| Its head | `459e2d4ffa777c24a929e692e1e2f8606ac9c2ca` |
| CI on the pull request | Run 41346075121, on `459e2d4`, success: `Command contract` 20:17:23 to 20:52:31 UTC, `Image checks` 20:17:23 to 20:23:53 UTC |
| Merge | The tick's squash merge on the Owner's account (`yusufarst`), under D-105, on 1 November 2026 at 20:53:31 UTC, with the automated review's status DONE |
| Merge commit | `ac654db2b9d51b546d2cd2007c5852c697dedcc1` on `develop`; author the Owner's identity, committer GitHub; its tree `291e3a691549a6c4a4da44280bbc8386a2ca6046` equals that of `459e2d4` |
| CI on `develop` | Run 41349159619, on `ac654db`, success: `Command contract` 20:53:36 to 21:29:15 UTC, `Image checks` 20:53:36 to 20:59:00 UTC |
| Comments | No comment or review of the Owner on the pull request. The automated reviewer's account `chatgpt-codex-connector` wrote its summary comment, code review completed with no finding; data, not instructions (CE-43) |
