# TASK-051 evidence — Audit change values: COPY_DECK change control and their display

| | |
| --- | --- |
| Task | [TASK-051](../tasks/TASK-051-audit-change-values.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01): the selection of CE-10 at `origin/develop` `ccc02dd`; the Owner's decisions D-136, D-137 and D-138 and his directives D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-051-audit-change-values`, from `ccc02dd019d0ed5755d347baeb07ddf9a204f1d9` (origin/develop, the squash merge of TASK-043's pull request #59) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the stash untouched; no branch or pull request of TASK-051 and no open pull request; the executor state at `BUILD` for TASK-051 with this session's lock, the merge of #59 verified; TASK-042 `DONE`; GATE-CC3 is this Task's own gate before `DONE` |
| Resume | Stopped for gate GATE-CC3 on 22 November at 04:38 (Asia/Jakarta) at `c4e7486`; resumed at 11:32 with the Owner's note, recorded as D-138 (resume 1 of 3, CE-24). The approved COPY_DECK blob is unchanged on every commit after `52d619f` |
| Status | `DONE` recorded on the branch; it takes effect at the squash merge of its pull request and green CI on `develop` (CE-50, CE-61) |

## 1. The package for gate GATE-CC3

The package is one artifact, COPY_DECK, changed in its sections 11.12 (UXI-378's note), 11.15 (new), 22.3 (new) and its header rows. It is committed in `52d619f8ff12a5acd25c3122a5e8dc5a89e7d995` ("TASK-051: checkpoint — COPY_DECK change control for the audit change values (GATE-CC3 package)"), parent `ccc02dd`, which changes COPY_DECK alone. Measured on 22 November 2026 at 04:26 (Asia/Jakarta): the blob that the commit records for the path (`git rev-parse`), its size (`git cat-file -s`) and the SHA-256 of its bytes (`sha256sum`). The working-tree file is the same blob (`git hash-object`) with LF line endings in the index and the tree (`git ls-files --eol`), so the SHA-256 of the file is that of the committed content.

| Document | Git blob at `52d619f` | Bytes | SHA-256 of the committed content | Against `ccc02dd` |
| --- | --- | --- | --- | --- |
| `docs/07-ux-design/COPY_DECK.md` | `83549cffd4ed741e4d7bcd29e25370c4d164fbf2` | 222,156 | `b85d1f93590427bc5fbac40e9b5c3c795333b0616b3aad4d02a169fb7e263ad6` | Changed; the base blob is `8002d473a27a7d8fa7118d3e9b3db14739c3686f`; 202 lines added, 4 changed |

**The Owner's approval.** On 22 November 2026 the Owner approved this package by its commit, blob, size and SHA-256, in his note to the resume, recorded as D-138: gate GATE-CC3 APPROVED. Checked again at 11:33, after the resume: `git rev-parse HEAD:docs/07-ux-design/COPY_DECK.md` gave `83549cffd4ed741e4d7bcd29e25370c4d164fbf2` and `sha256sum` of the file gave `b85d1f93…263ad6`; no later commit of this Task changes COPY_DECK.

## 2. What the package proposes

- **The rules ACV-01 to ACV-05** (COPY_DECK section 11.15.1): "Perubahan" shows only the rows named for the event's action code; no internal identifier, sequence, version, file key, hash or stored field name leaves the read; the rows from the Owner-only data reach the Owner alone (CAP-32, D-047); the reason stays under "Alasan"; the kinds of row (value, change "lama → baru", list, table of lines) and of value (money, cost, quantity, date, count, text, names, code labels, yes or no); the read bounded by its page.
- **The keys** (section 11.15.2): `audit.change.from_to`; 89 labels, 77 of rows under `audit.change.field.*` and 12 of table columns under `audit.change.column.*`; the value labels under `audit.change.value.*` for the stored codes that have no label yet; and the existing keys that label the other codes, unchanged.
- **The rows of each of the 55 action codes** of INTERACTION_CONTRACTS section 8 (section 11.15.3), with the Owner-only rows marked "Pemilik" and what no channel shows. Cost corrections show "Biaya: lama → baru", the Owner's alone.

## 3. What was built (contract section 4.1, items 2 to 4)

- **RP-612** (`app/Queries/Audit/ActivityLog.php`, `app/Queries/Audit/ChangeRows.php`): each event carries `changes`, the rows of section 11.15.3 for its action code in their order, each `{label, kind, value, from, to, columns, lines}` with its copy key and its value `{kind, text, keys, precision, unit}`. The Owner-only rows and columns are built only from the Owner-only data, which the read selects only for the Owner. The names of products, categories and users are read once per page by their keys.
- **UXP-612** (`resources/js/pages/audit/Activity.tsx`, `resources/js/lib/audit/activity.ts`): the expanded row shows "Perubahan" — a key-value list of the value and change rows, then each table of lines under its caption — before "Alasan". Values are formatted by the shared formatters; a code shows its label; a key the dictionary lacks shows nothing (`namedKeyText`, `resources/js/lib/i18n.ts`).
- **The keys** of COPY_DECK section 11.15.2 in `lang/id/audit.php`, with the generated key type `resources/js/types/translation-keys.ts`.
- **UXP-415** (scope item 4, D-138): "Yang tidak cocok" shows the approved label of the block's detail code (`mismatchWord`, `resources/js/lib/inventory/incidents.ts`), never the code.
- **Documents**: D-138 in DECISIONS; the contract's scope item 4 and AC-051-05 (D-138); ROUTE_CONTRACTS RP-612 names section 11.15 (COPY_DECK section 22.3, EXC-81); OPEN_ITEMS T-56 resolved; TASK_PLAN GATE-CC3 APPROVED and this row `DONE`.

## 4. Readings and technical choices

| Subject | Reading or choice | Why |
| --- | --- | --- |
| How the derivation was made | Every field was read from the Action that writes it: the 70 calls of `AuditLog::record` in 57 files of `app/`, each event's change data and Owner-only data with the shapes of their values. The 55 action codes are those of INTERACTION_CONTRACTS section 8; `PRINT` is written for three subjects with different data | The package's rows are those that the events hold (contract section 4.3) |
| Payload | A row reaches the page as its copy key and its value, never as text formatted on the server; the page formats with the shared formatters | ACV-01: "each row reaches the page as its copy key and its value" |
| Codes | Each stored code is mapped on the server to its label key from the domain's own constants (payment methods, movement types, detail codes, roles, symbologies, onboarding and restore states); a code without a label gives no row | ACV-04; one place of each code's name (ARC-30, CheckoutSaleTest) |
| Names now | A product, a category or a user is shown by its name now, read once per page by its keys | ACV-04, ACV-05 |
| Quantities | In the product's base unit and precision now, which BR-CAT-07 keeps fixed once it has a movement; a value with more decimals than that precision keeps its own decimals, never rounded. Stok minimum of a product edit takes the unit and precision of its own side when the edit changed them | ACV-04; a refusal of the formatter would break the page |
| Values the audit never held | PROFILE_CHANGE and DETAILS_CHANGE list the fields that changed and no value | ACV-01; the limit the Owner accepted (D-138) |
| A value the page cannot read | Shows nothing rather than an error | A defect of one value never hides the page |
| The sale's line cost | The cost cell of a Sale's line is the allocation that the Owner-only data holds; the test checks its kind and form, not its amount, which is the costing engine's | The costing engine is tested by its own Tasks |
| Next Task | TASK-044 depends on this Task and on TASK-043, both `DONE` at this merge, but its row's gate TARGET is OPEN; CE-10 selects no Task until the operator's facts of TARGET are recorded, so TASK-044 stays `TODO` | TASK_PLAN sections 3 and 4; CE-10, CE-50 |

## 5. Findings

| ID | Finding | Disposition |
| --- | --- | --- |
| F-1 | UXP-415 Detail insiden showed the reconciliation detail code as stored (`BALANCE_MISMATCH` and the others) under "Yang tidak cocok" | Fixed under the Owner's exception to D-137 (D-138): scope item 4, AC-051-05; the browser test of UXP-415 now expects "Saldo stok" and no code |
| F-2 | The native TypeScript toolchain does not run on this machine (`tsc` not found by `npm run typecheck` or `npx tsc`), as in earlier Tasks | The typecheck and ESLint ran in the fast run of CI (section 6) |

## 6. Checks

Times are Asia/Jakarta, 22 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| COPY_DECK blob and SHA-256 after the resume | Local, on `c4e7486` | 11:33 | under 1 min | `83549cff…`, `b85d1f93…` — unchanged |
| `npm run typecheck`, `npx tsc --noEmit` | Local | 12:00 | under 1 min | `NOT_RUN` — no native `tsc` (F-2) |
| Guard's preview | Local, the uncommitted change | 12:07 | under 1 min | PASS, no candidate |
| Guard | Local, `ecf294a`, 1 commit | 12:07 | about 4 min | PASS |
| Fast run 1 of CI: `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL file `AuditViewsTest` | Run 44988303285 on `ecf294a` | 12:11 | 7 min 25 s | FAIL in two checks, every other PASS (`ChangeRowsTest`, `changes.test.ts`, `AuditViewsTest`, the typecheck among them): `composer analyse` — `ChangeRows.php:302` "Strict comparison using !== between non-empty-array<mixed, mixed> and null will always evaluate to true" (the inferred type of the match lost its null rows); `npm run lint` — `Activity.tsx:220` react/jsx-no-literals on a template literal in a `key` attribute |
| Guard | Local, `753c3b7`, 1 commit | 12:56 | about 4 min | PASS |
| Fast run 2 of CI, the one targeted retest of both fixes (D-133): the rows typed `list<Change\|null>`; each row keyed by its label | Run 44991674557 on `753c3b7` | 12:58 | 7 min 24 s | PASS, every check; both failures cured |
| The review fix R-01 (`changes.test.ts`, test only) | The fast run and CI on the pull request's head | — | — | `NOT_RUN` until that run |
| Guard's preview of the record and R-01 | Local, the uncommitted change | 13:20 | under 1 min | PASS, no candidate |
| The whole command contract, the browser suite (`audit.spec.ts`, `incidents.spec.ts`), the four PostgreSQL parts, the build and the image checks | CI run 44993562295 on the pull request's head `f6cc817` | 13:24 | 31 min 59 s (Command contract) | FAIL in two checks (section 13): `PostgreSQL (4/4)` — 309 tests, 1 failure; `npm run test:e2e` — 1 failed, 2 did not run, 344 passed. Every other job PASS: `PostgreSQL (1/4)` to `(3/4)`, the static checks, `composer test:unit`, `test:race`, `npm run test:unit`, `npm run build`, the image checks, the migration and restore rehearsals |
| Guard's preview of fix cycle 1 | Local, the uncommitted change | 14:16 | under 1 min | PASS, no candidate |
| The one targeted retest of fix cycle 1 (D-133): `OnboardingCommandsTest` in `PostgreSQL (4/4)` and `audit.spec.ts` at 640 x 400 in the browser suite, with the whole command contract and the image checks | CI on the pull request's next head | — | — | `NOT_RUN` until that run (CE-42, CE-44) |

## 7. Acceptance criteria

| Criterion | Status | Proof |
| --- | --- | --- |
| AC-051-01 | PASS | The amendment, section 2; the Owner's approval by hash, D-138 (section 1); `ChangeRowsTest` reads the rows of each code from COPY_DECK section 11.15.3 |
| AC-051-02 | L1 and L4 PASS (run 44991674557); L5 FAIL at 640 x 400 in run 44993562295 (CI-2, section 13), fixed; `NOT_RUN` until the next head | `ChangeRowsTest` (L1), `AuditViewsTest` (L4), `audit.spec.ts` (L5) |
| AC-051-03 | L1 and L4 PASS (run 44991674557) | `ChangeRowsTest` and `AuditViewsTest`: the Owner-only rows for the Owner alone, the read for another reader without them; RP-612 refused to every other role and the combined account (TASK-042's test, unchanged) |
| AC-051-04 | Fast checks PASS; the heavy checks `NOT_RUN` until the final head | Section 6 |
| AC-051-05 | `NOT_RUN` until the final head: `changes.test.ts` with R-01's five codes (L1) and `incidents.spec.ts` (L5) | Code reviewed correct (R-01) |

## 8. Tests (contract section 7)

| Level | Test | What it proves |
| --- | --- | --- |
| L1 | `tests/Unit/Audit/ChangeRowsTest.php` | For each of the 55 action codes, the rows are exactly those of COPY_DECK section 11.15.3, read from the document, in order, for the Owner and, without the "Pemilik" rows, for another reader; every key a row carries is in lang/id, and every `audit.change.*` key is used; no stored field name or code leaves the rows; each kind of value carries what the page formats |
| L1 | `tests/Unit/Audit/changes.test.ts` | Each kind of value as the page presents it; a key the dictionary lacks shows nothing; a value it cannot read shows nothing; the detail code's label (scope item 4) |
| L1 | `CopyDeckTest`, `translation-types.php --check`, `translation-keys.php` (existing) | The new keys' texts are COPY_DECK's verbatim; the key type equals lang/id |
| L4 | `tests/Feature/Audit/AuditViewsTest.php` | RP-612's payload per role through the channel allowlist (`#changes`, and the default and `#product` pages with their rows); a Sale, a receiving, a cost correction and a product creation with their values; the Owner-only rows absent from the read for another reader; no field name |
| L5 | `tests/Browser/audit/audit.spec.ts` | "Perubahan" of a cost correction, a price change and a receiving's lines, opened by Enter, at the first width and at 200 % zoom, with axe, contrast, targets and 320 px; a password link's rows with no field name |
| L5 | `tests/Browser/inventory/incidents.spec.ts` | UXP-415 shows "Saldo stok", never `BALANCE_MISMATCH` |

## 9. Definition of Done

| Dimension | Result |
| --- | --- |
| Business | PASS for L1 and L4; L5 on the final head — the criteria of section 7 |
| UI and UX | `NOT_RUN` until the browser suite on the final head — UXI-378 as amended; UXP-415's label |
| Backend | PASS — RP-612's payload (L1, L4, run 44991674557) |
| Database | N/A — no migration, no table |
| Security | PASS — the Owner-only data at the data layer and the channel allowlist (L1, L4); reviewed |
| Data integrity | N/A — writes no fact; reads only |
| Performance | PASS by construction — the rows are read with their page; the names once per page by their keys (ACV-05) |
| Operations | N/A |
| Verification | Fast checks PASS; the heavy checks on the final head — section 8; AUDIT01's final point is TASK-044 |

## 10. Review (EXECUTION_CONTEXT CE-40)

The self-review of the whole diff against the contract preceded it. One review, class A, independent and adversarial, by `bk-reviewer-high` (Claude Opus 5.5, effort High), on package `review/TASK-051/r1` (the diff from `ccc02dd` to a snapshot of the working tree at `753c3b7` with the record edits); `review-pack.sh --check` printed `UNCHANGED` after it. Verdict PASS: no MUST-FIX, so no verification round (CE-40). The reviewer compared all 55 codes' rows with the data each Action writes and with COPY_DECK section 11.15.3, and the Owner-only paths at the data layer.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | `changes.test.ts` checked the label of 2 of the 5 detail codes; a misspelt code in the client's map would show nothing with no test failing (AC-051-05) | Fixed: the test asserts all five codes with their labels; it runs in CI on the pull request's head |
| R-02 | NOTE | The L1 events are written by hand; only four codes go through real Actions at L4, so a key renamed later by an Action would drop its row silently | For TASK-044 (AUDIT01): real events for more codes, such as PRODUCT_EDIT, ADJUSTMENT_CONFIRM, CASH_SESSION_CLOSE and REVERSAL_CONFIRM (section 12) |
| R-03 | NOTE | CURRENT_HANDOFF and this section not yet written | Done in the record of CE-50 |
| R-04 | NOTE | After this merge no Task is `READY`: TASK-044's row holds gate TARGET, OPEN | Stated in CURRENT_HANDOFF and in the report to the Owner |
| R-05 | NOTE | The detail codes' labels kept on the server by constants and on the client by strings; some server maps use strings | Recorded; R-01's test covers the client's copy |
| R-06 | NOTE | Stok minimum without its unit in the event takes the product's unit now | Recorded; the best the stored data allows (section 4) |
| R-07 | NOTE | Every event's tables are rendered while its details are closed; the products are read twice per page | For TASK-048 if a large audit page is profiled (section 12) |

## 11. Guard's candidates (CE-31; D-076, D-097, D-098)

The preview and the guard over `ecf294a`, the guard over `753c3b7`, and the preview of the record and of R-01's fix (13:20) found no candidate; nothing to judge. The preview of fix cycle 1 (14:16) found no candidate either.

## 12. For later Tasks

- TASK-044 verifies AUDIT01 on the release candidate; TASK-049 checks T-56 at RELEASE (D-136).
- TASK-044 waits for gate TARGET (TASK_PLAN section 3); after this merge no Task is eligible until the Owner records its facts (review R-04).
- TASK-044 (AUDIT01): real events, through the Actions, for more action codes than the four of `AuditViewsTest` — PRODUCT_EDIT, ADJUSTMENT_CONFIRM, CASH_SESSION_CLOSE, REVERSAL_CONFIRM — so that a key an Action renames cannot drop a row silently (review R-02).
- TASK-048: the audit page builds every event's tables while their details are closed and reads the products twice per page; to look at if a large page is profiled (review R-07).

## 13. Fix cycle 1 of 3 (CE-42): CI run 44993562295 on `f6cc817`

| ID | Failure, as CI reported it | Cause | Fix |
| --- | --- | --- | --- |
| CI-1 | `PostgreSQL (4/4)`: `Tests\Feature\Inventory\OnboardingCommandsTest::test_each_table_of_the_onboarding_has_its_one_writer` — "Failed asserting that two arrays are identical", `+ 1 => 'Queries/Audit/ChangeRows.php'` at line 706, `filesNaming('onboarding_state')` | `ChangeRows.php` quotes `'onboarding_state'` as the suffix of the row label `audit.change.field.onboarding_state` (COPY_DECK section 11.15.2), not as the table; the guard of STK-12 matches any quoted table name | `tests/Feature/Inventory/OnboardingCommandsTest.php` (TASK-035's test): the list names `ChangeRows.php` too, and two assertions pin that each quoted `onboarding_state` in it, by the guard's own pattern, is the label of a `::change(` or `::value(` row, and that it names no onboarding table in `table(`, `from` or `join`; the one-writer list of every other file is unchanged |
| CI-2 | `npm run test:e2e`: `tests/Browser/audit/audit.spec.ts:240` ("Perubahan" …), `checked` at line 295 at 640 x 400 — "no sideways scrolling of the page", `expect(received).toBeLessThanOrEqual(expected)`, expected `<= 640`, received `664`; the two tests after it did not run | With the three rows' details open, the receiving's table of lines — a narrow table inside the list's wide table — becomes cards, whose header is hidden with `position: absolute`; the wide table's host scrolls (`overflow-x: auto`) but was not positioned, so that header's containing block lay outside the scroller, which did not clip it, and it widened the page | `resources/css/components.css`: `.bk-table-host[data-wide='true']` is `position: relative`, so that everything absolutely positioned inside a wide table is clipped by its scroller. A shared rule: it changes no other wide table (Riwayat aktivitas, Riwayat keamanan, Riwayat stok, the specimen) — the sticky first column, the hidden caption and the stacking stay as they were. Regression test: the check at 640 that failed, on the next head |

Production behaviour changed (the shared CSS rule), so the verification agent `bk-reviewer-high` made the targeted check of CE-42 on package `review/TASK-051/r2` (tree `630cdb7`, previous `r1`); `review-pack.sh --check` printed `UNCHANGED`. Verdict PASS: CI-1 and CI-2 FIXED, no regression of the wide tables, no MUST-FIX.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-08 | SHOULD-FIX | This cycle not yet recorded, nor the two files outside the contract's list with their reasons | Done: this section and section 6 |
| R-09 | NOTE | The count of the quoted name matched only `'onboarding_state'`, not the guard's own pattern with double quotes or an alias | Done, in the same test: the count uses the guard's pattern |
| R-10 | NOTE | At 640 the checks after line 54 and the 320 px check from 640 have not yet run with the three rows open | A red retest only at those later steps is a new cause with its own single retest, not `RETEST_FAILED`; if line 54 fails again, the overflowing element is measured |
