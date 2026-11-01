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
| F-5 | `SignInSessionTest::counts()` reads every table of the schema, in order of `id` unless it names another key, and the fast run does not hold it (section 12). A new table whose key is not `id` adds its line there | Each Task that adds such a table |

## 6. Checks (CE-44 to CE-46, D-108, D-117)

Fast runs of CI, the workflow `fast-checks`: `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; each PostgreSQL or concurrency test file that the branch changes. Five of the ten allowed.

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| Fast run | CI run 41306738717 on `6edd072` | 1 Nov 19:29 | 5 min 38 s | FAIL; `CopyDeckTest` looked for the reasons in section 12 (reading 8) |
| Fast run | CI run 41307464828 on `2aa4d73` | 1 Nov 19:38 | 4 min 8 s | FAIL; `assets.test.ts`: the catalogue did not name the reasons (reading 8) |
| Fast run | CI run 41308191462 on `90158d8` | 1 Nov 19:47 | 8 min 9 s | FAIL; `CapabilityMatrixTest`'s mark (reading 9) |
| **Fast run**: static checks, unit tests, `BarcodeRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `BarcodeCommandsTest`, `BarcodeTablesTest`, `CatalogCommandsTest`, `ProductSearchTest` | CI run 41309171552 on `76e196f` | 1 Nov 20:00 | 9 min 20 s | **PASS** |
| **Fast run**, the same, with the fixes of section 7 | CI run 41311601192 on `ea4fbe4` | 1 Nov 20:30 | 7 min 59 s | **PASS** |
| Guard's preview and guard | Local, on each head | 1 Nov | under 1 min each | PASS, no candidate to judge |
| Image checks with the vulnerability gate | CI run 41312936376 on `4dc8c05` | 1 Nov 20:47 | 6 min 14 s | PASS on that head; runs again on the fix's head |
| Static checks, `composer test:unit` | CI run 41312936376 on `4dc8c05` | 1 Nov 20:47 | within the job's 17 min 51 s | PASS |
| `composer test:postgres`, the whole suite | CI run 41312936376 on `4dc8c05` | 1 Nov 20:51 | 13 min 50 s | **FAIL**, 1 error of 545 tests (section 12) |
| `composer test:race`, the browser suite, `npm run lint`, `typecheck`, `test:unit` and `build` in that job | CI, skipped in run 41312936376 after the failure | — | — | `NOT_RUN` on `4dc8c05`; they run on the fix's head |
| The whole command contract and the image checks | CI on the fix's head (CE-42, CE-44, D-117) | — | — | `NOT_RUN` at this writing |

`npx tsc` and `npx eslint` have no local toolchain on this machine (TASK-014 evidence section 1); the TypeScript files changed are read by `npm run typecheck`, `npm run lint` and `npm run test:unit` in the fast run.

**The heavy suites that this change can affect**: `BackupRunTest` and the release tests read the privilege matrix, so they meet the new tables, and the browser suite meets the new routes through the shell's inventory and the new keys through the page's dictionary. All run on the final head. The commit after `ea4fbe4` changes documents only, so run 41311601192's results stand (CE-45).

## 7. Review (CE-40)

`bk-reviewer-high`, Claude Opus 5.5, read only: package `review/TASK-016/r1`, tree `5ce98bc`, the adversarial review of class A; `--check` `UNCHANGED`. Verdict PASS, with no MUST-FIX, so no verification agent ran (CE-40). It asked for no test run. The self-review before it found no defect in the code; the three red fast runs of section 6 were each a test that the change had to teach.

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | The stored result held the token, and a token is neither an internal key nor a document number (SCHEMA section 5.3); `number` was only for a numbered record | Fixed: the result holds the mapping's key alone, and `present()` reads the code from the mapping, which never changes; INTERACTION_CONTRACTS section 1.2 names the case (EXC-81); `BarcodeCommandsTest` reads `result_detail` |
| R-02 | SHOULD-FIX | Reading 3 said that a `q` with a tab or no-break space matches no token, false at the outer positions | Fixed: reading 3 says what happens; the behaviour stays, as section 3.1 allows and nothing is written from `q` |
| R-03 | SHOULD-FIX | No test sent `mode=exact` with what a scan delivers | Fixed: the terminators and outer spaces, the outer tab and no-break space, another case, and an inner line break refused, in `ProductSearchTest` |
| R-04 | SHOULD-FIX | No null in either column of the correction's composite key, and `MATCH FULL` unasserted | Fixed in `BarcodeTablesTest`: both nulls refused, `confmatchtype` read |
| R-05 | SHOULD-FIX | No test read `Cache-Control` on RC-27 to RC-29 | Fixed: `no-store, private` on a commit, a repeat and a refusal of each |
| R-06 | NOTE | The Action's CAP-06 refusal on RC-17 has no test | Recorded: reading 6; the first role that separates these capabilities adds it |
| R-07 | NOTE | `mode=exact`'s refusal of filters binds later clients | F-4. No line in DECISIONS: it changes no business meaning, and INTERACTION_CONTRACTS section 3.1 holds it |
| R-08 | NOTE | UXS-37's link cannot come from `DUPLICATE_BARCODE` | Added to F-1 |
| R-09 | NOTE | The record of CE-50 was not yet due | Done: sections 8 to 11, TASK_PLAN and CURRENT_HANDOFF |

## 8. Acceptance criteria

The fast run's results are on the Task's own test files; the whole suites run on the final head.

| ID | Result | Evidence |
| --- | --- | --- |
| AC-016-01 | PASS by run 41311601192 | `BarcodeTokenTest`; `test_a_token_round_trips_exactly…`, `test_a_token_that_holds_what_no_token_may…`, `test_a_declared_symbology_validates_its_check_digit` |
| AC-016-02 | PASS by run 41311601192 | `test_the_bk_namespace_is_reserved…`, `test_a_clash_of_a_generated_code…`, `test_a_product_whose_barcode_is_refused_is_not_created`, the unit test of 2,000 draws |
| AC-016-03 | PASS by run 41311601192 | `BarcodeRaceTest` (RACE-17's attach part in five cases, RACE-19's ordinary part); `test_a_retirement_records_its_reason_class…`, `BarcodeTablesTest` |
| AC-016-04 | PASS by run 41311601192 | `test_the_exact_mode_answers_at_most_one_product…`, `test_rd_06_reports_an_exact_barcode…`, `test_an_exact_barcode_leads_the_search…` |
| AC-016-05 | PASS by run 41311601192 for the constraint, deferred, privilege and retention families; `db:check-privileges` on the final head (`PrivilegeCheckTest`) | `BarcodeTablesTest`, `PrivilegeMatrixTest` |

## 9. Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | Section 8; REQ-CAT-04, REQ-CAT-05 |
| UI and UX | N/A | No page or control: TASK-017 |
| Backend | PASS | FormRequest allowlists, the gates, in-transaction guards, the allowlisted answers, L4 tests |
| Database | PASS, the whole suite on CI at the final head | The migration and its metadata; the four families for its tables |
| Security | PASS, the whole suite on CI at the final head | A cashier refused before a command runs; the capability matrix; injection only text; `no-store`; no secret; the static checks |
| Data integrity | PASS | Facts only added; audit and result in the transaction; identity and repeats; the races; failure injection after each write step |
| Performance | PASS | One bounded statement per lookup on the indexes of SCHEMA section 11; the budgets are TASK-048's |
| Operations | PASS | Section 11 |
| Verification | PASS by sections 6 and 7; CI on the final head pending | `DONE` takes effect at the merge with CI green on `develop` (CE-61) |

## 10. Guard (CE-31, CE-50)

`guard.sh --preview` on the working tree: PASS, with no candidate to judge, as `guard.sh` on every head since the resume. The result on the final commit is in the pull request's description.

## 11. Backup impact (EXC-47)

The three class F tables are in the manifest's counts without a change of code: `BackupRunner` counts the classes F, FS, M and S of the privilege matrix. `barcode_active_mappings` is class P, a projection that is not counted, and the dump holds it. No new kind of stored file.

## 12. Fix cycle 1 on pull request #25 (CE-42, failure 1 of 3)

- **The failure.** CI run 41312936376 on `4dc8c05`, job `Command contract`, step `composer test:postgres`: one error of 545 tests, `SignInSessionTest::test_signing_out_and_a_session_ending_change_nothing_but_the_sign_in_session_and_the_security_log`, with SQLSTATE 42703: `column "id" does not exist`, in `select * from "barcode_active_mappings" order by "id" asc`. The steps after it did not run. `Image checks` passed.
- **The cause: a defect of the test, not of the code.** The test snapshots every table of the schema, ordered by `id` unless `counts()` names another key. `barcode_active_mappings` has the key `token_id` and `barcode_mapping_retirements` the key `mapping_id`; the second would have failed next, in the same way. The file is outside the fast run, which runs only the test files that the branch changes (CE-46), so this was the first run to reach it (CE-42, D-117). It is not intermittent: it fails on every run.
- **The fix.** The two keys added to `counts()`, in `tests/Feature/Access/SignInSessionTest.php`. No production code changed, so no targeted verification is due (CE-42), and the failing test is its own regression test. F-5 tells later Tasks.
- **Checks.** PHP does not run on this machine (D-108), so the fixed test was not run locally: `NOT_RUN` until CI on the fix's head, which runs the whole contract. The guard on the fix's commit is in the report of this cycle.
