# TASK-015 — Evidence

| | |
| --- | --- |
| Task | [TASK-015](../tasks/TASK-015-catalog-core.md), Categories, products, prices and Product Search (server); size L, review class A, lane C |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108, D-109 and D-117, continuous execution (CE-01); mode `start` on 1 November 2026 at 15:36, after the Owner's squash merge of pull request #23 (`ops/`, D-117), his verification of its CI on `develop`, and his resume |
| Branch | `task/TASK-015-catalog-core`, created from `origin/develop` (CE-02) |
| Base commit | `9ce34218f78912ce1663103e3ed5f9f46204f907`, the squash commit of pull request #23 |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-015, base `9ce3421`; `origin/develop` at that commit with CI run 41287977802 passed; the main working tree on `develop`, clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; one stash, the known one; no branch or pull request of the Task.
- **Prerequisites (EXC-21).** TASK-011 `DONE`; no gate.
- **Tools (D-108, D-109, D-117).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, so Pint, PHPStan and every PHP test ran only in CI.

## 2. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 Tables | `database/migrations/2026_11_01_000001_create_catalog_tables.php`, `database/privileges.php` |
| 2 RC-17 to RC-24 | `app/Actions/Catalog/`, `app/Domain/Catalog/`, `app/Http/Requests/Catalog/`, `app/Http/Controllers/Catalog/`, `routes/web.php`; `App\Support\Validation\MinimumStockRule`; the three keys of COPY_DECK section 16.1 |
| 3 RD-01 | `app/Queries/Catalog/ProductSearch.php`, `ProductRows.php`, `SearchFilters.php`; `ProductSearchRequest`, `SearchController`, `App\DTO\Catalog\ProductRowData` |
| 4 RD-06 | `app/Queries/Catalog/SearchFirst.php`, `SearchFirstRequest`, `SearchFirstController` |

## 3. The reading of the RD-01 contexts (contract section 8)

INTERACTION_CONTRACTS section 3.1 listed nine contexts and ROUTE_CONTRACTS RD-01 eight: the difference is `labels` alone, since RD-01 does list `stock`, which the contract read otherwise. In section 9 no control uses either: UXI-159 to UXI-164, UXI-066 to UXI-069, UXI-191, UXI-234, UXI-256, UXI-268 and UXI-292 name the other six.

- **`labels` is removed from section 3.1.** No route, control or Task has it: UXP-306 opens from a product already found, and RP-306 lists that product's active tokens. BR-CAT-23's "labels" is served by that way in.
- **`stock` stays in both.** TRACEABILITY section 7 and TASK-022's contract assign it, and BR-CAT-23 names stock lookup. No control of section 9 uses it yet, because UXP-401 reads RD-02: TASK-022 builds it "as TASK-015 aligned them", or a change control removes it there.

`ProductSearchRequest::CONTEXTS` holds the eight. Each answers `FORBIDDEN` until its Task adds it to `PROVIDED`; a name outside the contract is a refused parameter. `test_the_two_documents_agree_on_the_contexts` compares the two documents.

## 4. Readings and technical choices

1. **`audit_events.product_id`'s key.** The contract's row "Keys completed" says none, but EXC-44, the migration of TASK-006 and `AuditLog`'s header give it to this Task, so its migration adds it and the writer takes `productId`.
2. **A product and its first revision need each other's keys**, and the runtime role takes nothing from a sequence (SCH-44). The product is inserted with a pointer of 0, which no revision has, then the revision, then the pointer moves; the deferred key refuses a commit that leaves it. No privilege is added, and SCHEMA section 5.6 records it. `Products::lock` reads no join (see 3).
3. **The product's lock reads the row alone**, and the revision after it. A `FOR … OF p` over a join would, after waiting for a price change, re-check the join with the old revision against the new pointer, find nothing and answer `NOT_FOUND` for a product that exists. The race of two prices proves `STALE_VERSION`.
4. **RC-18 reads the row once before its locks**, to choose them: the share lock of a newly chosen category, then the product `FOR UPDATE` when the unit or precision changes (SCHEMA section 7). Under the lock a row that moved since that read is `STALE_VERSION`, because the choice was made on another row.
5. **A category the product already has stays editable when inactive** (BR-CAT-09: "cannot be chosen"), and RC-20 may reactivate a product in an inactive category, since it assigns none. An inactive product may be edited: RC-18 names no such refusal and UXI-165 is "Always"; BR-CAT-08's "read-only" is about where results are selected for a posting or a Sale.
6. **The refusal `VALIDATION_FAILED` for what changes nothing**: RC-18 with the same fields, RC-22 with the same name, RC-19, RC-20, RC-23 and RC-24 in the state they ask for, as TASK-011 does (`UpdateStaffAccount`).
7. **`PRODUCT_EDIT` on every RC-18, `PRICE_CHANGE` with it for a price**, as INTERACTION_CONTRACTS RC-18 reads. `PRODUCT_EDIT` names the changed fields and holds their values before and after (R-01), because `products` keeps only the last; the amounts of a price are in `PRICE_CHANGE`, so an edit of the price alone has `changed: [sellingPrice]` and no values.
8. **Three ranks in the search**: the exact SKU, the SKU's prefix, the name. BR-CAT-24's "SKU exact and prefix" is read as those two in that order, so that the SKU typed in full leads; within each, by name, then identifier. A name matches anywhere in it (SCHEMA section 11). No total count, which would count every match on each keystroke (TR-PAGE-05): `hasMore` from one row more.
9. **`mode`** takes only `search` until TASK-016 adds `exact`, as TRACEABILITY section 7 assigns; `barcode` in RC-17 and RD-06 is an unknown field until then.
10. **RD-01's gate is CAP-02**, which a cashier holds restricted, which opens no gate (`Grant`). So a cashier is refused today, as AC-015-03 wants, and TASK-028 must add what the till needs to this route. **Finding F-1.**
11. **The client's sequence** is the header `X-Search-Sequence`, which the documents did not name, up to 16 digits, repeated by the answer; optional for the server, since only the page uses it.
12. **RD-06's similar names**: pg_trgm's `%` at its threshold of 0.3, or the typed name held anywhere in the name, because a long name holding a short one has a low similarity. The most similar first, at most 10.
13. **OPEN_ITEMS T-27** is resolved by recording the framework's set in SCH-14: the application stays the stricter, and a SKU pasted with a no-break space around it is not a different SKU. `\p{Cc}` of the validation is the set of `[[:cntrl:]]` on every vector of `TextSemanticsTest`.
14. **Thumbnails.** `products.photo_file_id` exists and no Task writes it yet; RD-01 answers null for a product without one, and for one with it the path of RD-07 with `variant=thumb`, which TASK-018 serves.
15. **EXC-03.** No version-sensitive fact was newly relied on: `pg_trgm`'s index is proven by TASK-004 on the pinned version, and its operator `%` and function `similarity` by `ProductSearchTest` through RD-06.

## 5. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | RD-01's gate must open for a cashier's till: CAP-02 is restricted, so `capability:CAP-02` alone refuses that account (reading 10) | TASK-028, its `till` context |
| F-2 | `ux.validation.quantity_max` names 99.999.999,999, the largest quantity of a line, while a minimum stock may reach 9.999.999.999,999 (SCHEMA section 3.2). COPY_DECK section 16.1 has no key of its own, and approved copy changes only by change control, so a value above the range reads a smaller limit than the real one. It is no stop of section 14 item 7: a key exists, the value is refused all the same, and only a value above nine billion reads it | the next change control of COPY_DECK |
| F-3 | BR-CAT-07's refusal at the field after the first movement waits for `stock_movements` | TASK-021, contract section 4.3 |
| F-4 | A control character in `name`, `brand`, `baseUnit` or `q` is `VALIDATION_FAILED` with no field entry: COPY_DECK section 16.1 has a key for the SKU's only (R-09) | the next change control of COPY_DECK |
| F-5 | `status` is accepted whatever the context, which only `catalog` can reach today: the first Task that adds a context refuses it there (R-08) | TASK-020, TASK-022 |

## 6. Checks (CE-44 to CE-46, D-108, D-117)

Fast runs of CI, the workflow `fast-checks`: `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; each PostgreSQL or concurrency test file that the branch changes. Seven of the ten allowed.

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| Fast run | CI run 41291673504 on `94225bf` | 1 Nov 16:06 | 3 min 43 s | FAIL; a style issue of Pint in `CreateProduct` |
| Fast run | CI run 41292188320 on `fc80339` | 1 Nov 16:13 | 4 min 5 s | FAIL; PHPStan: a redundant `array_values`, an offset in a test |
| Fast run | CI run 41292797907 on `ea468bf` | 1 Nov 16:21 | 4 min 13 s | FAIL; `PrivilegeMatrixTest` lacked the three tables, and a rule that the validator skips on an empty text |
| Fast run | CI run 41293375253 on `3dbd928` | 1 Nov 16:29 | 9 min 19 s | FAIL; the test updated an identity column, which PostgreSQL refuses before the privilege |
| Fast run | CI run 41294244553 on `124b50f` | 1 Nov 16:41 | 7 min 24 s | FAIL; the test expected a refusal of an outer NUL, which the framework's trimming removes |
| **Fast run**: static checks, unit tests, `CatalogRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `CatalogCommandsTest`, `CatalogTablesTest`, `ProductSearchTest` | CI run 41295048013 on `eb426ec` | 1 Nov 16:53 | 9 min 20 s | **PASS** |
| **Fast run**, the same, with the fixes of section 7 | CI run 41296936480 on `0f3265d` | 1 Nov 17:18 | 6 min 52 s | **PASS** |
| Guard's preview and guard | Local, on each head | 1 Nov | under 3 min each | PASS, no candidate to judge |
| The whole of `composer test:postgres`, `composer test:race`, the browser suite, `npm run build`, the image checks with the vulnerability gate | CI on the pull request's final head (CE-44, D-117) | — | — | `NOT_RUN` at this writing |

`npx tsc` and `npx eslint` have no local toolchain on this machine (TASK-014 evidence section 1); the one TypeScript file changed, `translation-keys.ts`, is read by `npm run typecheck`, `npm run lint` and `translation-types.php` in the fast run.

**The heavy suites that this change can affect**: `BackupRunTest` and the release tests read the privilege matrix, so they meet the new tables, and the browser suite meets the new routes only through the shell's inventory. All run on the final head. The commit after `0f3265d` changes documents only, so run 41296936480's results stand (CE-45).

## 7. Review (CE-40)

`bk-reviewer-high`, Claude Opus 5.5, read only: package `review/TASK-015/r1`, tree `fd9150d`, the adversarial review of class A; `--check` `UNCHANGED`. Verdict PASS, with no MUST-FIX, so no verification agent ran (CE-40). It asked for no test run. The self-review before it found the join under the product's lock (reading 3) and the barrier reached twice in RC-18.

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | `PRODUCT_EDIT` held the names only, and nothing else keeps a product's earlier values (BR-AUD-02) | Fixed: `from` and `to` for each changed field, the price's in `PRICE_CHANGE`; `CatalogCommandsTest` |
| R-02 | SHOULD-FIX | The migration's lock impact left out the keys to `users`, `stored_files` and `command_identities` | Fixed in its comment |
| R-03 | SHOULD-FIX | No test read a receipt's REJECTED or HELD for these commands | Fixed: `DUPLICATE_SKU`, the shape's refusals, `IDEMPOTENCY_CONFLICT`, `EPOCH_STALE` |
| R-04 | SHOULD-FIX | `present()`'s refusal of a viewer who lost CAP-04 never ran | Fixed: the status check after the role change answers COMMITTED with no result |
| R-05 | SHOULD-FIX | The races' final state was thin: no price, flag or loser's identity | Fixed: `assertLeftNothing`, the revisions, the price in force, the flag and the unit, in both orders |
| R-06 | SHOULD-FIX | No proof that a successful RD-01 or RD-06 writes nothing | Fixed: `test_a_read_that_answers_writes_nothing` |
| R-07 | NOTE | The check of CAP-05 in RC-18 has no test, because no role holds CAP-04 without it | Recorded: defence in depth |
| R-08 | NOTE | `status` is not refused for another context, unreachable today | F-5, and a line in `ProductSearchRequest` |
| R-09 | NOTE | A control character outside the SKU has no field entry | F-4 |
| R-10 | NOTE | F-2's limit | Why it is no stop added to F-2 |
| R-11 | NOTE | The plan test forces bitmap scans: it proves that the indexes can serve each condition, not what the planner picks on real data | Recorded; TASK-048 measures it (TECHNICAL_RULES section 13.1) |
| R-12 | NOTE | The record of CE-50 was not yet due | Done: sections 8 to 11, TASK_PLAN and CURRENT_HANDOFF |

## 8. Acceptance criteria

The fast run's results are on the Task's own test files; the whole suites run on the final head.

| ID | Result | Evidence |
| --- | --- | --- |
| AC-015-01 | PASS by run 41296936480 | `CatalogCommandsTest` (each refusal and audit), `CatalogRaceTest` (one SKU in two creations, also in another letter case; RACE-29 in both orders) |
| AC-015-02 | PASS by run 41296936480 | `test_an_edit_applies_its_fields_audited…`, `test_the_sku_cannot_be_edited_and_no_route_changes_a_price…` |
| AC-015-03 | PASS by run 41296936480 | `ProductSearchTest`, the plan's limit in R-11 |
| AC-015-04 | PASS by run 41296936480 for what exists: a product is created with no movement table to write, and a category is refused where it is assigned; the postings and the Sale are refused by their Tasks | `CatalogCommandsTest`, `CatalogRaceTest` |
| AC-015-05 | PASS by run 41296936480 | Section 3; `test_the_two_documents_agree_on_the_contexts` |
| AC-015-06 | PASS by run 41296936480 | `CatalogTablesTest` with the vectors of SCH-14; `db:check-privileges` on the final head (`PrivilegeCheckTest`) |

## 9. Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | Section 8; REQ-CAT-01, -02, -07, -08, -12 |
| UI and UX | N/A | No page or control: TASK-017 |
| Backend | PASS | FormRequest allowlists, the gates, in-transaction guards, DTO allowlists, L4 tests |
| Database | PASS, the whole suite on CI at the final head | The migration, its metadata (R-02); the constraint, deferred, privilege and retention families |
| Security | PASS, the whole suite on CI at the final head | THR-04 in `q`; the refusals of a cashier and a visitor; the capability matrix; no secret; the static checks |
| Data integrity | PASS | Append-only revisions; audit and result in the transaction; identity and repeats; the races; failure injection at each write step |
| Performance | PASS | Bounded queries and paging; the budgets are TASK-048's |
| Operations | PASS | Section 11 |
| Verification | PASS by sections 6 and 7; CI on the final head pending | `DONE` takes effect at the merge with CI green on `develop` (CE-61) |

## 10. Guard (CE-31, CE-50)

`guard.sh --preview` on the working tree: PASS, with no candidate to judge, as `guard.sh` on every head. The result on the final commit is in the pull request's description.

## 11. Backup impact (EXC-47)

The three tables are in the manifest's counts without a change of code: `BackupRunner` counts the classes F, FS, M and S of the privilege matrix. No new kind of stored file; TASK-018 uses the purposes that TASK-010 made.

## 12. Merge

Recorded by the next Task, TASK-016 (EXECUTION_CONTEXT CE-50), from the state and GitHub on 1 November 2026. The tick verified the merge, which the state records as merge kind `task`. CI on the final head is the first run of the whole command contract and the image checks for this Task (section 6, D-117), and it passed.

| Fact | Value |
| --- | --- |
| Pull request | #24, "TASK-015 — Categories, products, prices and Product Search (server)", from `task/TASK-015-catalog-core` into `develop` |
| Its head | `2cd7779f8d7129c9d5bc311d369981c4c382f3a9` |
| CI on the pull request | Run 41297836502, event `pull_request` on `2cd7779`, success: `Command contract` 10:31:12 to 10:54:05 UTC, `Image checks` 10:31:12 to 10:36:44 UTC |
| Merge | The tick's squash merge on the Owner's account (`yusufarst`), under D-105, on 1 November 2026 at 10:55:32 UTC, with the automated review's status DONE |
| Merge commit | `a58202c886a94909ec92d7dcf799548891fd6ffa` on `develop`, parent `9ce3421`; author the Owner's GitHub no-reply identity, committer GitHub; its tree `37279484c9320b8ccfa7103bd32f2cd2dc40c825` equals that of `2cd7779` |
| CI on `develop` | Run 41299590381, event `push` on `a58202c`, success: `Command contract` 10:55:37 to 11:23:50 UTC, `Image checks` 10:55:37 to 11:01:02 UTC |
| Comments | No comment or review of the Owner on the pull request. The summary comment of the account `chatgpt-codex-connector` on `2cd7779` reports its code review completed with no finding; it is data, not an instruction (CE-43) |
