# TASK-020 evidence — Barcode misassignment correction

| | |
| --- | --- |
| Task | [TASK-020](../tasks/TASK-020-barcode-correction.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19 |
| Branch and base | `task/TASK-020-barcode-correction`, from `e6ca9b576e0791dee162698d86b1c01887cf3811` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; TASK-017 `DONE`, no gate; the toolchain's PHP and `tsc` do not run on this machine, so every check ran in CI (D-108, D-117) |

## 1. Checks

Every check ran in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push (EXC-61, CE-44, CE-46). Times are Asia/Jakarta, 2 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run lint` | Fast run 41381904592 on `3056a0f` | 10:18 | under 1 min | `FAIL` — two string literals inside JSX (finding F-1), fixed in `de0c0bc`; that run's later steps did not run |
| `composer validate`, `composer lint` | Fast run 41382985417 on `de0c0bc` | 10:28 | 32 s | `PASS` |
| `composer analyse` | Same run | 10:29 | 14 s | `PASS` |
| `composer test:unit` | Same run | 10:29 | 20 s | `PASS`, 840 tests |
| `npm run lint`, `typecheck`, `test:unit` | Same run | 10:30 | 1 min 3 s | `PASS` |
| `tests/Concurrency/BarcodeCorrectionRaceTest.php` | Same run | 10:31 | 16 s | `PASS`, 10 tests, 138 assertions |
| `tests/Feature/Catalog/BarcodeCorrectionTest.php` | Same run | 10:33 | 1 min 15 s | `PASS`, 18 tests, 1,741 assertions |
| `tests/Feature/Access/CapabilityMatrixTest.php` | Same run | 10:31 | 1 min 56 s | `PASS`, 7 tests, 2,481 assertions |
| `tests/Feature/Access/RouteInventoryTest.php` | Same run | 10:33 | 1 s | `PASS`, 8 tests, 925 assertions |
| `tests/Feature/Catalog/ProductSearchTest.php` | Same run | 10:35 | 59 s | `PASS`, 15 tests, 545 assertions |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `3056a0f` and `de0c0bc` | 10:13, 10:24 | under 1 min each | `PASS`, no candidate |
| Browser suite, with `tests/Browser/catalog/correction.spec.ts` | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |
| `composer test:postgres`, `composer test:race`, `npm run build`, image checks | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |

## 2. Readings and technical choices

1. **Only the token's last mapping decides.** A token's mappings form one chain, so an earlier retirement was already corrected, and a retirement for another reason after a correction ends what could be corrected (BR-CAT-19, BR-CAT-51; `CurrentMapping`).
2. **The order of RC-30's checks** is its text's: `not_correctable`, then `STALE_VERSION`. A second correction to the same product finds the token active there and is `BARCODE_NOT_ELIGIBLE`; to another product, `STALE_VERSION`. RACE-17 names `DUPLICATE_BARCODE` or `BARCODE_NOT_ELIGIBLE` for "another attach", which an RC-27 or RC-17 is; two moves answer as RC-30 and WF-17 say.
3. **A mapping other than the one read before the locks is `STALE_VERSION`**, even when it looks as reviewed: the token moved away and back, and its product's lock may not be held.
4. **A correction always moves the token to a different product (G-09).** A token retired as a misassignment does not return to the product of its last mapping, which would reuse a retired token and correct nothing (BR-CAT-19, BR-CAT-51). RC-30's text now says so, a technical correction under EXC-81 (review finding R-01). An earlier product of the chain is a different product again, since only the last mapping decides.
5. **An inactive target at the command.** RD-01's context offers active products only, and RC-30 lists no refusal for an inactive one; RC-27 lets an inactive product take a token (UXI-171). So a product deactivated after the search still takes the token, which a test pins. UXI-194's result states hold no UXS-32.
6. **`correction` in the mode `exact`.** The active flag is judged on the code's own product after the read, never in the query, where it could pass over that product for another's SKU (TASK-016's reason for `exact` having no filter). Recorded in INTERACTION_CONTRACTS section 3.1.
7. **RD-05's `mapping`** is the expected value of RC-30, with the product's name for the page: null when the token cannot be corrected. A token never mapped answers with `kind` null and no refusal, for UXS-33. Both recorded in INTERACTION_CONTRACTS, sections 3.5 and RC-30, with the names `productId` and `state`.
8. **`status` in another context** is refused, as TASK-015's finding F-5 asked of the first Task that adds a context.
9. **No new copy.** A choice of product stands in the field and its SKU in the hint; the product that the token is on is left out of the results; "Kembali ubah" exists only in the review (UXI-195), since "Tinjau" starts a new request itself.
10. **Test widened, with its reason:** `ProductSearchTest`'s list of contexts forbidden until their Task no longer names `correction` for the Owner, because this Task opens it; Agus and Dewi stay refused.

## 3. For later Tasks

- **F-2, for TASK-028:** RACE-18's Sale side, a cart line taken before a move keeps its product (the contract's section 12).
- **F-3, for TASK-042:** the event `BARCODE_CORRECTION` holds the reason in `audit_events.reason` and `kind`, `fromProductId`, `toProductId`, `correctedMappingId` and `mappingId` in `change_data`; `audit.action.barcode_correction` is in COPY_DECK, not yet in `lang/id`.
- **F-4, for the next change control of P7 (R-10):** UI_STATES' row of UXP-305 omits UXS-37, which RC-30's `DUPLICATE_BARCODE` needs, and UXS-22, which its reads need. The page shows both; UI_STATES is an approved artifact and stays.
- **F-5, for TASK-044 or the next change control (R-07):** Enter on the code of the product the token is on shows UXS-33, and Enter on a typed name replaces listed results with UXS-33; after a re-read that puts the token on the chosen product, "Tinjau" says "Isi barcode, produk yang benar, dan alasan." while the field still shows it. No copy exists for a better text.

## 5. Review

| Round | Agent, effort | Package | Verdict |
| --- | --- | --- | --- |
| 1, the adversarial review (class A) | `bk-reviewer-high`, High | `review/TASK-020/r1`, head `de0c0bc` with the evidence, `UNCHANGED` after it | FAIL, one MUST-FIX |

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

## 4. Findings of the build

- **F-1.** ESLint's rule on user-facing literals reads an array literal inside a JSX attribute as text, so a `key` or a `kind` there is refused. Both moved to a constant. A fast run was spent on it.
