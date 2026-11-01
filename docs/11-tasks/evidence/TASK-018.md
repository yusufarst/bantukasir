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
| 3 Labels | `jsbarcode` in `package.json` and `package-lock.json`; `resources/js/lib/documents/code128.ts`; `app/Actions/Documents/RecordLabelPrint.php` with its request and controller; `app/Domain/Documents/Labels/`; `app/Http/Controllers/Catalog/LabelsController.php`, `Documents/LabelPrintController.php`; `resources/js/pages/catalog/Labels.tsx`, `documents/LabelPrint.tsx`; `.bk-label` in `components.css` and `base.css` |
| 4 RP-302 | `resources/js/pages/catalog/Product.tsx` (`PhotoSection`, the link to the labels); `Products.tsx` (UXI-163); `app/Queries/Catalog/ProductPages.php`, `ProductRows.php`, `ProductsController.php` |
| Tests | `tests/Feature/Catalog/ProductPhotoTest.php`, `tests/Feature/Documents/LabelPrintTest.php`, `tests/Concurrency/CatalogRaceTest.php`, `tests/Unit/Documents/code128.test.ts`, `tests/Browser/catalog/labels.spec.ts`; rows added to `RouteInventoryTest`, `CapabilityMatrixTest`, `Allowlists`, `OperationsTest` |

## 3. Library (contract section 8)

| Item | Value |
| --- | --- |
| Library | `jsbarcode` 3.12.3, no dependency |
| Licence | MIT: the registry's `license` of that version and the repository's `MIT-LICENSE.txt`, both read on 2 November 2026 |
| Source | `https://registry.npmjs.org/jsbarcode/3.12.3`; its integrity, read twice, is in `package-lock.json`, and `npm ci` checks it |
| EXC-03 | `bin/renderers/svg.js` and `bin/help/getRenderProperties.js` of that version, on unpkg: the SVG renderer sets attributes and no `style`, so SEC-HDR-03 holds; an object without `nodeName` takes the object renderer, which the unit test uses |
