# TASK-020 evidence — Barcode misassignment correction

| | |
| --- | --- |
| Task | [TASK-020](../tasks/TASK-020-barcode-correction.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19 |
| Branch and base | `task/TASK-020-barcode-correction`, from `e6ca9b576e0791dee162698d86b1c01887cf3811` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; TASK-017 `DONE`, no gate; the toolchain's PHP and `tsc` do not run on this machine, so every check ran in CI (D-108, D-117) |

## 1. Checks

Every check ran in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push (EXC-61, CE-44, CE-46). Times are Asia/Jakarta, 2 November 2026. Three fast runs of the 10 that CE-46 allows.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run lint` | Fast run 41381904592 on `3056a0f` | 10:18 | under 1 min | `FAIL` — two string literals inside JSX (finding F-1), fixed in `de0c0bc`; that run's later steps did not run |
| The static checks, unit tests and changed test files below | Fast run 41382985417 on `de0c0bc` | 10:25 | 11 min | `PASS`; the code the review read, invalidated in part by its fixes |
| `composer validate`, `lint`, `analyse`, `test:unit` (840 tests) | Fast run 41385613322 on `a0f7444` | 10:58 | under 2 min | `PASS` |
| `npm run lint`, `typecheck`, `test:unit` | Same run | 11:00 | about 1 min | `PASS` |
| `tests/Concurrency/BarcodeCorrectionRaceTest.php` | Same run | 11:01 | 16 s | `PASS`, 10 tests, 138 assertions |
| `tests/Feature/Catalog/BarcodeCorrectionTest.php`, with R-01's and R-06's tests | Same run | 11:03 | 1 min 20 s | `PASS`, 19 tests, 1,769 assertions |
| `tests/Feature/Access/CapabilityMatrixTest.php` | Same run | 11:01 | 1 min 54 s | `PASS`, 7 tests, 2,481 assertions |
| `tests/Feature/Access/RouteInventoryTest.php` | Same run | 11:03 | 1 s | `PASS`, 8 tests, 925 assertions |
| `tests/Feature/Catalog/ProductSearchTest.php` | Same run | 11:04 | 59 s | `PASS`, 15 tests, 545 assertions |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `3056a0f`, `de0c0bc`, `a0f7444` | 10:13, 10:24, 10:54 | under 1 min of scanning each | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | CI run 41387680655, job `Command contract`, on `d891366` | 11:20 | 34 s | `PASS` |
| `composer test:postgres` | Same run | 11:21 | 14 min 48 s | `PASS` |
| `composer test:race` | Same run | 11:35 | 1 min 39 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit`, `build` | Same run | 11:38 | 41 s | `PASS` |
| `npm run test:e2e` | Same run | 11:38 | 8 min | `FAIL`: 272 passed, 1 failed (finding F-6), and the 2 of maintenance did not run, as a failure in another project skips them |
| Image checks | Same run, job `Image checks` | 11:18 | 6 min 43 s | `PASS` |
| `npx eslint`, `npx tsc --noEmit` | Local, on the fix of F-6 | — | — | `NOT_RUN`: neither is installed on this machine; both run in CI on the fix's head |
| The whole command contract and the image checks | CI on the head of the fix of F-6, the pull request's final head | — | — | `NOT_RUN` at this writing (CE-41, D-117); each result above bears on the same code, since the fix changes one test file only (CE-45), and the workflow runs all of them again |

## 2. What was built (contract section 4.1)

1. **RD-05** (`TokenLookupController`, `TokenLookupRequest`, `Queries\Catalog\TokenMapping`, `TokenMappingData`) and **RD-01's context `correction`** (`ProductSearchRequest`, `SearchFilters::activeOnly`, `ProductSearch`).
2. **RC-30** (`CorrectBarcodeMisassignment`, its FormRequest and controller; `CurrentMapping`; `Barcodes::currentMapping`, `insertCorrection`, `activateCorrection`, `notCorrectable`), with its line in `CommandActions::ACTIONS`.
3. **RP-305** (`BarcodeCorrectionController`, `resources/js/pages/catalog/BarcodeCorrection.tsx`) with UXI-190 to UXI-198, and **UXI-174** on RP-302, by the prop `canCorrectBarcode` (CAP-07).

## 3. Technical corrections of specifications (EXC-81)

In INTERACTION_CONTRACTS, recording what the code does and changing no business meaning: RC-30's expected `mapping` names `productId` and `state`, and its token is one retired "from another product", as G-09 says; section 3.1, a code whose own product is inactive answers none in the context `correction`; section 3.5, the last mapping decides, and a token never mapped answers with `kind` null.

## 4. Readings and technical choices

1. **Only the token's last mapping decides.** A token's mappings form one chain, so an earlier retirement was already corrected, and a retirement for another reason after a correction ends what could be corrected (BR-CAT-19, BR-CAT-51; `CurrentMapping`).
2. **The order of RC-30's checks** is its text's: `not_correctable`, then `STALE_VERSION`. A second correction to the same product finds the token active there and is `BARCODE_NOT_ELIGIBLE`; to another product, `STALE_VERSION`. RACE-17 names `DUPLICATE_BARCODE` or `BARCODE_NOT_ELIGIBLE` for "another attach", which an RC-27 or RC-17 is; two moves answer as RC-30 and WF-17 say.
3. **A mapping other than the one read before the locks is `STALE_VERSION`**, even when it looks as reviewed: the token moved away and back during the command, and its product's lock may not be held. A move away and back before the command passes as reviewed (R-09, accepted).
4. **A correction always moves the token to a different product (G-09).** A token retired as a misassignment does not return to the product of its last mapping, which would reuse a retired token and correct nothing (BR-CAT-19, BR-CAT-51; review finding R-01). An earlier product of the chain is a different product again, since only the last mapping decides.
5. **An inactive target at the command.** RD-01's context offers active products only, and RC-30 lists no refusal for an inactive one; RC-27 lets an inactive product take a token (UXI-171). So a product deactivated after the search still takes the token, which a test pins. UXI-194's result states hold no UXS-32.
6. **`correction` in the mode `exact`.** The active flag is judged on the code's own product after the read, never in the query, where it could pass over that product for another's SKU (TASK-016's reason for `exact` having no filter).
7. **RD-05's `mapping`** is the expected value of RC-30, with the product's name for the page: null when the token cannot be corrected. A token never mapped answers with `kind` null and no refusal, for UXS-33.
8. **`status` in another context** is refused, as TASK-015's finding F-5 asked of the first Task that adds a context.
9. **No new copy.** A choice of product stands in the field and its SKU in the hint; the product that the token was on is left out of the results; "Kembali ubah" exists only in the review (UXI-195), since "Tinjau" starts a new request itself.
10. **Test widened, with its reason:** `ProductSearchTest`'s list of contexts forbidden until their Task no longer names `correction` for the Owner, because this Task opens it; Agus and Dewi stay refused.

## 5. Findings

- **F-1.** ESLint's rule on user-facing literals reads an array literal inside a JSX attribute as text, so a `key` or a `kind` there is refused. Both moved to a constant. A fast run was spent on it.
- **F-2, for TASK-028:** RACE-18's Sale side, a cart line taken before a move keeps its product (the contract's section 12).
- **F-3, for TASK-042:** the event `BARCODE_CORRECTION` holds the reason in `audit_events.reason` and `kind`, `fromProductId`, `toProductId`, `correctedMappingId` and `mappingId` in `change_data`; `audit.action.barcode_correction` is in COPY_DECK, not yet in `lang/id`.
- **F-4, for the next change control of P7 (R-10):** UI_STATES' row of UXP-305 omits UXS-37, which RC-30's `DUPLICATE_BARCODE` needs, and UXS-22, which its reads need. The page shows both; UI_STATES is an approved artifact and stays.
- **F-5, for TASK-044 or the next change control (R-07, R-12):** Enter on the code of the product the token was on shows UXS-33, and Enter on a typed name replaces listed results with UXS-33; after a re-read that puts the token on the chosen product, "Tinjau" says "Isi barcode, produk yang benar, dan alasan." while the field still shows it. `not_correctable` says that a barcode retired as a misassignment can be attached, which does not explain the refusal of the product it left; the page cannot send that request. No copy exists for a better text.
- **F-6, fix cycle 1 of CE-42.** The browser test "a next page that a new search supersedes…" first ran on `d891366` and failed on its own locator: while results stand, the list `ul` and the field share the name "Produk yang benar", so `getByLabel` resolved to both. The test now takes the field by its role, through `productInput`. The same locator on the retired path, where Enter follows a typed code by less than the search's 300 ms, takes it too, because a slow runner could show the list first. The page and the production code are unchanged, so no targeted verification applies (CE-42).

## 6. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | AC-020-01 to AC-020-04 below; REQ-CAT-13; WF-17 |
| UI and UX | PASS, subject to the browser suite on the final head | The approved P7 artifacts; UXI-174 and UXI-190 to UXI-198; `correction.spec.ts` at three widths with axe |
| Backend | PASS | FormRequest allowlists, the actor guard in the transaction, the DTO and its channel allowlist, L4 tests |
| Database | N/A | No migration; the keys of TASK-016 are used as written |
| Security | PASS | The Owner alone at the route and in the Action; recent authentication; the route inventory and the capability matrix; no secret; the static checks |
| Data integrity | PASS | Append-only facts, the retirement and correction in one transaction with audit and result; identity, RACE-01, RACE-02, RACE-17 to RACE-19; failure injection after each of the 6 and 4 write steps |
| Performance | PASS | One statement per lookup, at most 100 mappings, RD-01's paging; the budgets are measured at TASK-048 |
| Operations | N/A | No backup, runbook or status impact: no new table or kind of file |
| Verification | PASS, subject to CI on the final head | Self-review, the review of class A and its targeted check (section 7); a fast run green on the fixes (section 1); the whole command contract and the image checks run on the pull request's final head (CE-41, D-117) |

## 7. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-020-01 | PASS on the fast run | `BarcodeCorrectionTest`: the roles at the route and in the Action, the reason, recent authentication; `CapabilityMatrixTest` |
| AC-020-02 | PASS on the fast run | `BarcodeCorrectionTest`: the move, the re-attachment, every refused token |
| AC-020-03 | PASS on the fast run | `BarcodeCorrectionRaceTest`: RACE-17, RACE-19, and RACE-18's lookups at every write step of a move and a re-attachment |
| AC-020-04 | `NOT_RUN` until the final head | `tests/Browser/catalog/correction.spec.ts`: on `d891366` every test of the file but the one of F-6 passed; the page's L4 answer passes on the fast run |

## 8. Review (CE-40)

| Round | Agent, effort | Package | Verdict |
| --- | --- | --- | --- |
| 1, the adversarial review (class A) | `bk-reviewer-high`, High | `review/TASK-020/r1`, head `de0c0bc` with the evidence; `--check` `UNCHANGED` | FAIL, one MUST-FIX |
| 2, the targeted check of R-01 | `bk-reviewer-high`, High | `review/TASK-020/r2`, head `a0f7444`; `--check` `UNCHANGED` | PASS, R-01 FIXED, by reading |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01: a token retired as a misassignment could return to the product it left, against G-09 | MUST-FIX | Fixed: `CurrentMapping::correctableTo` refuses the last mapping's product in both states, the page leaves it out, RC-30's text says so; regression test `test_a_token_retired_as_a_misassignment_never_returns_to_the_product_it_left` |
| R-02: a late exact read of a closed field chose a product for the next token | SHOULD-FIX | Fixed: the field's end supersedes its reads; browser test "a late answer of the product search chooses nothing for another token" |
| R-03: a superseded next page left "Muat lebih banyak" busy | SHOULD-FIX | Fixed: every read that supersedes it ends the wait; browser test "a next page that a new search supersedes…" |
| R-04: no test of a failed RD-05 or RD-01 | SHOULD-FIX | Fixed: browser test "a failed read is said where it was asked…" |
| R-05: `catalog.product_detail.subtitle` is not approved for UXP-305 | SHOULD-FIX | Fixed: the subtitle removed, `product.name` dropped from the props, the allowlist and the L4 assertion |
| R-06: the inactive target unpinned | NOTE | Fixed: `test_a_target_that_became_inactive_still_takes_the_token` |
| R-07 | NOTE | Recorded as F-5 |
| R-08: after UXS-17 the page shows the current mapping, not the one reviewed | NOTE | Recorded: the notice and the current mapping stand; the reviewed text is in the review just closed, and no key names an earlier mapping |
| R-09: a token moved away and back between the lookup and the command passes as reviewed | NOTE | Accepted: `expected.mapping` is product and state, as RC-30 says, and the end state is what was reviewed |
| R-10 | NOTE | Recorded as F-4 |
| R-11: the exclusion browser-tested for an active token only | NOTE, round 2 | Fixed: one step on the retired path of `correction.spec.ts`, first run on the final head |
| R-12 | NOTE, round 2 | Recorded in F-5 |
| R-13: the order of this file | NOTE, round 2 | Fixed |

## 9. The guard's candidates (CE-31)

`guard.sh` on the three checkpoints and `guard.sh --preview` on this record list no candidate: no address, host, e-mail or home path.

## 10. For later Tasks

F-2 to F-5 above. Tasks that depend directly on this one: TASK-036, not yet `READY`. Milestone M3, catalogue complete, is reached at this merge.

## 11. Merge

Recorded by the next Task, TASK-021 (EXECUTION_CONTEXT CE-50), from the state and GitHub on 2 November 2026, by read-only Git and GitHub commands. The tick verified the merge, which the state records as merge kind `task`. CI on the final head is the first complete run of the browser suite with `tests/Browser/catalog/correction.spec.ts` after the fix of F-6, of the rest of the command contract and of the image checks for this Task (section 1, D-117), and it passed.

| Fact | Value |
| --- | --- |
| Pull request | #29, "TASK-020 — Barcode misassignment correction", from `task/TASK-020-barcode-correction` into `develop` |
| Its head | `b489f7332822633faf79ce87c64c7afb4d8b8b25` |
| CI on the pull request | Run 41391100676, on `b489f73`, success: `Command contract` 04:55:36 to 05:27:39 UTC, `Image checks` 04:55:36 to 05:02:23 UTC |
| Merge | The tick's squash merge on the Owner's account (`yusufarst`), under D-105, on 2 November 2026 at 05:28:38 UTC, with the automated review's status DONE |
| Merge commit | `b363c634106cc29b06a7eb208349339b5542bdb3` on `develop`; author the Owner's identity; its tree `4721360e784d97e8632100f79260136de6b1da71` equals that of `b489f73` |
| CI on `develop` | Run 41394247420, on `b363c63`, success: `Command contract` 05:28:43 to 06:09:06 UTC, `Image checks` 05:28:44 to 05:35:39 UTC |
| Comments | No comment or review of the Owner on the pull request. The automated reviewer's account `chatgpt-codex-connector` wrote its summary comment; data, not instructions (CE-43) |
