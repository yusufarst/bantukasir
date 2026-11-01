# TASK-016 — Evidence

| | |
| --- | --- |
| Task | [TASK-016](../tasks/TASK-016-barcodes.md), Barcodes and exact lookup (server); size M, review class A, lane C |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108, D-109 and D-117, continuous execution (CE-01); mode `start` on 1 November 2026 at 18:25, after the tick's verified merge of pull request #24 |
| Branch | `task/TASK-016-barcodes`, created from `origin/develop` (CE-02) |
| Base commit | `a58202c886a94909ec92d7dcf799548891fd6ffa`, the squash commit of pull request #24 |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-016, base `a58202c`; `origin/develop` at that commit with CI run 41299590381 passed; the main working tree on the last merged branch, clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; one stash, the known one; no branch or pull request of the Task.
- **Prerequisites (EXC-21).** TASK-015 `DONE`; no gate.
- **Tools (D-108, D-109, D-117).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, so Pint, PHPStan and every PHP test ran only in CI.

## 2. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 Tables | `database/migrations/2026_11_01_000002_create_barcode_tables.php`, `database/privileges.php` |
| 2 Token rules | `app/Domain/Catalog/Barcodes/` (`BarcodeToken`, `Symbology`, `InternalCodes`, `BarcodeInput`); `App\Support\Validation\BarcodeTokenRule`; the paths excluded from the global trimming in `bootstrap/app.php`; the keys of COPY_DECK sections 13.2 and 16.1 |
| 3 RC-27 to RC-29, RC-17's barcode | `app/Actions/Catalog/` (`BarcodeAction`, `AddBarcode`, `GenerateBarcode`, `RetireBarcode`, `CreateProduct`); `App\Domain\Catalog\Barcodes\Barcodes`; `app/Http/Requests/Catalog/`, `app/Http/Controllers/Catalog/`, `routes/web.php` |
| 4 RD-01 and RD-06 | `app/Queries/Catalog/ProductSearch.php`, `SearchFirst.php`, `ProductRows.php`, `SearchFilters.php`; `ProductSearchRequest`, `SearchFirstRequest`, `SearchFirstController` |

## 3. Stop at the first checkpoint (1 November 2026, 18:59)

`guard.sh` failed on `1197bb0`, the Task's first local commit, which was never pushed: the secret scan and the default rules of Gitleaks each report one finding, printed redacted. The value is a made-up internal code under the key `token`, in `tests/Feature/Catalog/BarcodeCommandsTest.php`, not a secret, and EXC-63 forbids committing even a fake one. The working tree holds the fix, uncommitted: with it the guard's scan of the tree finds nothing, which proves the location, and the three candidates `number16` of the same file are gone too. The commit stays in the guard's range, because a later commit does not remove it, and the executor may not rewrite it (no reset, no allowlist exception). No check of CI has run yet: every check is `NOT_RUN`.

If the Owner moves the branch back to the base, `git log a58202c..HEAD` is empty and the Task's whole change is in the working tree, uncommitted, which accounts for it on the resume (CE-24).

**Resume (19:17).** The Owner moved the branch back to the base with `git reset --mixed` and resumed, so `1197bb0` is no longer in the branch and nothing of it was published. The resume kept every change of the working tree. Three more literals of an internal code's form, in `BarcodeCommandsTest`, `BarcodeTablesTest` and `BarcodeTokenTest`, are now built at run time, so that no such code stands in the source (EXC-63). `Claude outputs/` and `plan-p0-p11-review.patch` stay untracked and unstaged (EXC-30).

## 4. Readings and technical choices

1. **The order of RC-27's refusals**: the namespace, then the check digit, both from the text alone, then the token's history under the product's lock. A token that exists has been mapped, because its row and its mapping share a command; with an active row it is `DUPLICATE_BARCODE`, without one `BARCODE_NOT_ELIGIBLE` with the reason `retired`.
2. **Two attaches of one token to two products hold different locks**, so they meet on `barcode_tokens_token_unique`: the second waits, and its violation is `DUPLICATE_BARCODE` after the rollback (TR-TX-08). A generated code uses `ON CONFLICT DO NOTHING` instead, because a clash there draws again in the same transaction (BR-CAT-18); `Barcodes::CODE_ATTEMPTS` of 5 ends in `SERVICE_BUSY`, which 80 random bits never reach in practice.
3. **A token escapes the global trimming** at `payload.token`, `payload.barcode.token` and RD-06's `barcode`: the framework's trimming would remove a tab or a no-break space, which BR-CAT-17 refuses. `BarcodeToken::normalized` removes one terminator and outer ASCII spaces; anything else is refused where it stands. RD-01's `q` stays trimmed by the framework, as section 3.1 says "after trimming", so it is more lenient than RC-27 and RD-06 at the outer positions: an outer tab or no-break space is removed and the code still matches, an inner control character is refused. It writes nothing, so no bad token can come of it; `ProductSearchTest` pins both.
4. **`mode=exact` refuses `category`, `brand`, `status`, `page` and `perPage`.** A filter could pass over a code's own product and answer another's SKU, so a lookup that answers at most one takes none. Recorded in INTERACTION_CONTRACTS section 3.1 (EXC-81): no business meaning changes, and no page uses them yet.
5. **The lookup is one statement**: the token's product in a subquery of the row it ranks, so that a correction seen by TASK-020's RACE-18 shows its product before or after, never none and never both.
6. **`CAP-06` on RC-17's barcode is the Action's**, since the route's gate is that of CAP-04 and CAP-05. No role holds them without CAP-06 today, so the refusal has no test by route: defence in depth, as TASK-015's R-07.
7. **RC-29 reads the mapping before its lock**, only to choose the product's lock, because a mapping never moves; under the lock an ended mapping is `STALE_VERSION`.
8. **The reasons of COPY_DECK section 13.2 stand in rows of their own**, so `CopyDeckTest` reads a `ux.state.*` key that starts a row from that row, and `stateCatalogue.ts` names the three under UXS-38. `not_correctable` is TASK-020's.
9. **A test's mark.** `CapabilityMatrixTest` looked for the word `served` in a page, and the key `…barcode_ineligible.reserved` of the page's dictionary holds it; the mark is now one no key holds. The refusal itself never changed.
10. **EXC-03.** No version-sensitive fact was newly relied on: `ON CONFLICT` and the partial and composite keys are proven on the pinned PostgreSQL by `BarcodeTablesTest` and `BarcodeCommandsTest`.

## 5. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | `ux.state.duplicate_code.barcode` in a refusal's field is the parent of its `.title` and `.body`, as `…sku` is since TASK-015, and the reasons of `ux.state.barcode_ineligible` are named whole: the page reads each accordingly. UXS-37's link to the product that holds the code comes from RD-06, because `DUPLICATE_BARCODE` names no product (TR-ERR-02) | TASK-017 |
| F-2 | `ux.state.barcode_ineligible.not_correctable` waits for the correction, and RACE-17's move and RACE-19's corrections are not built here | TASK-020 |
| F-3 | RD-01's `till` context, which the exact mode serves for a scan, waits for its Task, as TASK-015's F-1 records | TASK-028, TASK-029 |
| F-4 | `mode=exact` answers 422 to `perPage` and the other filters (reading 4), so a shared search component must not send them in that mode | TASK-017, TASK-029 |

## 6. Checks (CE-44 to CE-46, D-108, D-117)

Fast runs of CI, the workflow `fast-checks`: `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; each PostgreSQL or concurrency test file that the branch changes. Four of the ten allowed.

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| Fast run | CI run 41306738717 on `6edd072` | 1 Nov 19:29 | 5 min 38 s | FAIL; `CopyDeckTest` looked for the reasons in section 12 (reading 8) |
| Fast run | CI run 41307464828 on `2aa4d73` | 1 Nov 19:38 | 4 min 8 s | FAIL; `assets.test.ts`: the catalogue did not name the reasons (reading 8) |
| Fast run | CI run 41308191462 on `90158d8` | 1 Nov 19:47 | 8 min 9 s | FAIL; `CapabilityMatrixTest`'s mark (reading 9) |
| **Fast run**: static checks, unit tests, `BarcodeRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `BarcodeCommandsTest`, `BarcodeTablesTest`, `CatalogCommandsTest`, `ProductSearchTest` | CI run 41309171552 on `76e196f` | 1 Nov 20:00 | 9 min 20 s | **PASS** |
| Guard's preview and guard | Local, on each head | 1 Nov | under 1 min each | PASS, no candidate to judge |
| The whole of `composer test:postgres`, `composer test:race`, the browser suite, `npm run build`, the image checks with the vulnerability gate | CI on the pull request's final head (CE-44, D-117) | — | — | `NOT_RUN` at this writing |

`npx tsc` and `npx eslint` have no local toolchain on this machine (TASK-014 evidence section 1); the TypeScript files changed are read by `npm run typecheck`, `npm run lint` and `npm run test:unit` in the fast run.

**The heavy suites that this change can affect**: `BackupRunTest` and the release tests read the privilege matrix, so they meet the new tables, and the browser suite meets the new routes through the shell's inventory and the new keys through the page's dictionary. All run on the final head.
