# TASK-019 — Evidence

| | |
| --- | --- |
| Task | [TASK-019](../tasks/TASK-019-csv-import.md), CSV product import; size M, review class A, lane C |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108, D-109 and D-117, continuous execution (CE-01); mode `start` on 2 November 2026 at 04:31, after the tick's verified merge of pull request #27 |
| Branch | `task/TASK-019-csv-import`, created from `origin/develop` (CE-02) |
| Base commit | `ac654db2b9d51b546d2cd2007c5852c697dedcc1`, the squash commit of pull request #27 |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8. Every value of the tests is synthetic (Toko Contoh).

## 1. Start and checks (EXC-20 to EXC-24, CE-44 to CE-46, D-117)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-019, base `ac654db`; `origin/develop` at that commit with CI run 41349159619 passed; the main working tree on the last merged branch, clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; one stash, the known one; no branch or pull request of the Task.
- **Prerequisites (EXC-21).** TASK-017 `DONE`; no gate.
- **Tools (D-108, D-109, D-117).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. `node_modules/.bin` holds neither `tsc` nor `eslint` here, and no PHP runs on this machine for BantuKasir, so every check ran only in CI.

Fast runs of CI, the workflow `fast-checks`. Eight of the ten allowed.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Fast run | CI run 41355753754 on `9ff2279` | 2 Nov 05:10 | about 3 min | FAIL; `composer lint`: Pint, `single_quote` in a test |
| Fast run | CI run 41356432668 on `96b7695` | 2 Nov 05:18 | about 4 min | FAIL; `composer analyse`: `sort()` of the keys, two test types |
| Fast run | CI run 41357207622 on `94c55cf` | 2 Nov 05:27 | about 4 min | FAIL; `composer analyse`: a test's type |
| Fast run | CI run 41357955637 on `1dc020c` | 2 Nov 05:36 | about 5 min | FAIL; `composer test:unit`: a defect of a test, a header that only one separator splits |
| Fast run | CI run 41358660449 on `d380b19` | 2 Nov 05:44 | about 5 min | FAIL; `npm run lint`: `Number`, literals in attributes |
| Fast run | CI run 41359537662 on `12603f0` | 2 Nov 05:55 | about 6 min | FAIL; `npm run typecheck`: the page watch in the new browser test |
| **Fast run**: static checks, unit tests (840), `CsvImportTest` (18), `CsvImportTableTest` (3), `CsvImportRaceTest` (6), `CapabilityMatrixTest`, `RouteInventoryTest`, `CatalogPagesTest` | CI run 41360710125 on `fac9497` | 2 Nov 06:09 | 6 min 57 s | **PASS** |
| **Fast run**, the same with the fixes of the review | CI run 41362980541 on `c40560b` | 2 Nov 06:37 | 9 min 6 s; its test files 3 min 35 s | **PASS** |
| `release dry run` (the workflow, for the changed Dockerfile) | CI run 41362980484 on `c40560b` | 2 Nov 06:37 | — | PASS |
| Guard | Local, on each head | 2 Nov | under 5 min each | PASS, one candidate judged (section 9) |
| `composer test:postgres`, `test:race`, the browser suite with `import.spec.ts`, `npm run build`, the image checks with RR-01's limits | CI on the pull request's final head (D-117) | — | — | `NOT_RUN` at this writing |

## 2. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 The table | `database/migrations/2026_11_02_000001_create_csv_product_imports_table.php`, `database/privileges.php`, `app/Domain/Catalog/Import/CsvImports.php` |
| 2 RR-01 | `app/Domain/Catalog/Import/` (`CsvDialect`, `CsvRows`, `CsvImportChecks`, `CsvImportFiles`, `CsvColumn`, `CsvRow`, `CsvRecord`, `CsvFile`, `CsvFileRefused`, `CheckedImport`); `app/Actions/Catalog/PreviewCsvImport.php`; `ImportPreviewController`, `ImportPreviewRequest`; the location in `docker/app/nginx.conf`, the folder in `docker/app/Dockerfile`, the disk in `config/filesystems.php`, `scripts/checks/image-checks.sh` |
| 3 RR-02 | `app/Actions/Catalog/CancelCsvImport.php`, `ImportCancelController` |
| 4 RC-31 | `app/Actions/Catalog/ConfirmCsvImport.php`, its request and controller; `CommandAction::settled()`; RD-01's and RP-301's `import` (`ProductSearch`, `SearchFilters`, `ProductSearchRequest`, `ProductsPageRequest`) |
| 5 RP-308 | `ImportController`; `resources/js/pages/catalog/Import.tsx`, `resources/js/lib/catalog/csvImport.ts`; UXI-011 in `lib/navigation.ts`; the summary on `Products.tsx`; the texts in `lang/id/catalog.php` and `lang/id/ux.php` |
| Tests | `tests/Unit/Catalog/CsvDialectTest.php`, `csvImport.test.ts`; `tests/Feature/Catalog/CsvImportTest.php`, `CsvImportTableTest.php`; `tests/Concurrency/CsvImportRaceTest.php`; `tests/Browser/catalog/import.spec.ts`; rows added to `RouteInventoryTest`, `CapabilityMatrixTest`, `Allowlists`, `OperationsTest`, `PrivilegeMatrixTest`, `navigation.test.ts`, `catalog.spec.ts` |

## 3. Technical corrections of specifications (EXC-81)

No business meaning changes. RC-31 says that it leads to "RP-301 filtered to the created products", and RP-301 and RD-01 had no such filter: `ROUTE_CONTRACTS` RP-301 and RD-01 and `INTERACTION_CONTRACTS` section 3.1 now name `import`, only for `catalog`. `ENVIRONMENTS` section 6 says where the temporary area is.

## 4. Readings and technical choices

1. **The row number** is the row of the file as a spreadsheet numbers it: the header is row 1. A line break inside a quoted field keeps one row.
2. **The separator.** DC-04 says "the one of the two that splits the header into known column names". With an unknown column in the header no separator yields only known names, which would leave `unknown_column` unreachable, so a separator splits the header when it yields a known name and the other does not. Neither or both is `missing_column`, naming every required column.
3. **A column twice, or a name with a quote out of place,** is `unknown_column`, an "unexpected column" of BR-CAT-39. COPY_DECK has no other text for it.
4. **A quote out of place** in a cell (`Pipa 5" besar`) is `invalid_value`. DC-04 allows a quote only around a field or doubled. A carriage return outside a quoted field is no line end of DC-04, so the file is refused as holding a control character; inside a quoted field it is a line break, a row error.
5. **A cell that begins with `=`** is judged after the trimming, so ` =A1` is refused, in every column. DC-04 names `=` only: a name that begins with `+`, `-` or `@` is text, which an export neutralizes (SEC-CH-07).
6. **Trimming.** The text columns take the framework's `Str::trim`, as a command's texts do through the global middleware (SCH-14), so that the import stores what RC-17 would. The barcode takes `BarcodeToken::normalized`, as RC-17's does.
7. **Scientific notation** is one or more digits, an optional decimal part, `E` and digits. A real token of that exact form is refused too, and is added with RC-27.
8. **Every comparison without regard to letter case** is the database's `lower()`, one statement over a JSON array: a SKU in the file and among the products, a category name. A preview therefore says what the unique index says at the commit (SCH-14).
9. **The temporary area binds the import to its actor** by its path, `<actor>/<random key>`, because SCHEMA section 4 keeps it out of the database. Another actor's request finds nothing, and RR-02 answers it `204` as for a missing one. The checksum in the answer is of the bytes as they came, the byte-order mark among them.
10. **A file with row errors is stored,** so that its cancel and its later refusal have the same path. A file refused as a whole is never stored.
11. **What ends the file.** A commit or its repeat, and a refusal `CSV_INVALID`, `DUPLICATE_SKU`, `DUPLICATE_BARCODE` or `CATEGORY_INACTIVE`: the import's final outcomes of SECURITY_MODEL section 13, through the new `CommandAction::settled()`, outside the transaction and never changing the answer. `SERVICE_BUSY` and a server error are uncertain (TR-ERR-01), so the file stays for "Kirim ulang". `IDEMPOTENCY_CONFLICT`, `EPOCH_STALE` and `INTENT_ABANDONED` do not end the import either. See F-1 and R-04.
12. **Two checks in RC-31.** The first chooses the locks and the second, under them, decides (TR-VAL-08). A row that after the locks matches a category without this command's lock is refused `CSV_INVALID` with `category_missing`; it no longer matches what was locked, and a new preview says the truth (R-02).
13. **`CATEGORY_INACTIVE` and DC-04.** RC-31 gives `CATEGORY_INACTIVE` to a category deactivated after the match, and DC-04 to one deactivated after the preview. The server keeps no preview, so a name that only an inactive category has answers `CATEGORY_INACTIVE` at confirmation, with the row errors in `lines`. UXS-32 shows it, by COPY_DECK section 15, though UI_STATES's row of UXP-308 does not list it; `Import.tsx` follows the code's state as UXP-303 does.
14. **`lines`** holds the row's errors under `errors`, by row number. `CommandRefused`'s type is widened for it (INTERACTION_CONTRACTS section 1.6).
15. **The preview table** shows every row, those with errors first and 100 at a time, the head being the name. COPY_DECK has no header for a row number or for the errors: the row number stands in each error's own text, and the errors' column takes `catalog.field.status`. The table is not `wide`: NAVIGATION_CONTRACTS asks for cards with the error first.
16. **Leaving with a preview open** asks first (UXS-45) and sends no RR-02: NC-03 names that request only for "Batalkan impor". The file stays for housekeeping (F-1).
17. **The summary on UXP-301** is the page's note of `takeSavedNote`, shown once, as UXP-302 and UXP-003 do; after a status check it is `committedText`.
18. **Golden Frontend Reference.** Not used by this Task.

## 5. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | Outcomes that leave a temporary file until housekeeping: uncertain or held requests, a refusal by the actor guard (FORBIDDEN, not a `CommandRefused`), a commit whose answer was lost and that a status check resolved, a page left with a preview open, and a removal that fails | TASK-038, `CsvImportFiles::MAX_AGE_SECONDS` and the disk `csv_imports` |
| F-2 | 5,000 rows, each with a barcode, committed within the budget on CI's runner, inside the 56 s of the test file; about 30,000 statements in one transaction. The measure on the benchmark's server is the budget's own | TASK-048 |
| F-3 | COPY_DECK has no text for a file with a header and no rows: the preview shows 0 rows, the confirmation stays disabled with `ux.disabled.form_incomplete`, and RC-31 refuses it `CSV_INVALID` | Next change control of COPY_DECK |
| F-4 | RC-25's and RC-32's requests have the same path as R-01: a file between PHP's upload limit and its body limit reads as unreadable, not as too large. Outside this Task's scope, and each page refuses a large file itself | Recorded |

## 6. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | AC-019-01 to AC-019-05 below; REQ-CAT-09; WF-18 |
| UI and UX | PASS, subject to the browser suite on the final head | The approved P7 artifacts; UXI-011 and UXI-206 to UXI-212; `import.spec.ts` |
| Backend | PASS | FormRequest allowlists, the actor binding of the path, the actor guard, DTO allowlists, L4 tests |
| Database | PASS | One additive migration with its REL-20 metadata, `composer lint`'s review; the constraint and privilege families and the retention of a fact of class F in `CsvImportTableTest`; it has no deferred key. Rehearsal is TASK-043's dataset (EXC-46) |
| Security | PASS | THR-09, THR-10: every refusal of section 13, no file refused is stored, never served, no text of a file reaching a process; the route inventory and the capability matrix for every kind of account; the allowlists; no secret; the static checks |
| Data integrity | PASS | All rows or none; the audit, the record and the result atomic; the identity, RACE-01, RACE-02, RACE-22 and RACE-29; failure injection after each of the 12 write steps; the budget's end leaving nothing |
| Performance | PASS | The row and byte limits read before anything else; one statement per check; the 5,000 rows within the budget (F-2); the budgets are measured at TASK-048 |
| Operations | PASS | Backup (EXC-47): `csv_product_imports` is class F, in the manifest's counts through the privilege matrix; the temporary area is outside the file volume and in no backup. No runbook or status impact |
| Verification | PASS, subject to CI on the final head | Self-review and the review of class A (section 8); a fast run green on the fixes (section 1); the whole command contract and the image checks run on the pull request's final head (CE-41, D-117) |

## 7. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-019-01 | PASS on the fast run | `CsvDialectTest`, `CsvImportTest::test_the_dialect_reaches_the_records_exactly` |
| AC-019-02 | PASS on the fast run | `CsvDialectTest`; `test_a_whole_file_is_refused_before_anything_is_stored_or_written`; `test_every_row_error_is_shown_at_its_row_and_the_confirmation_refused_with_nothing_created` |
| AC-019-03 | PASS on the fast run; the page `NOT_RUN` until the final head | `CsvImportTest`, `CsvImportRaceTest`, `CsvImportTableTest`, `import.spec.ts`. TASK-021 adds the stock tables, whose families hold "no stock or cost" from then on; until then neither exists |
| AC-019-04 | PASS on the fast run for the application; the folder's mode and the web server's refusal `NOT_RUN` until the image checks on the final head | `CsvImportTest`: commit, each refusal, cancel, the 24 hours, never served, outside the file volume; `image-checks.sh` |
| AC-019-05 | PASS on the fast run | `test_a_file_name_or_a_cell_with_shell_metacharacters_never_reaches_a_process`, the path holding nothing of the upload |

## 8. Review (CE-40)

By the isolated agent `bk-reviewer-high`, Claude Opus 5.5 at effort High, read-only, from `review/TASK-019/r1`, tree `6122317` on `fac9497`; `review-pack.sh --check` printed `UNCHANGED`. Verdict **PASS**: no MUST-FIX, so no targeted check (CE-40). It attacked with no finding: the gate and the file paths, atomicity, the races and the budget, the checksum, the parser, the numbers, the upload limits, the metacharacters and the copy keys.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | A file above PHP's `upload_max_filesize` arrives as a failed upload, answered `VALIDATION_FAILED` with no key, where RR-01 asks `CSV_INVALID` with its key | Fixed: `ImportPreviewRequest::failedValidation`; another failed upload is `unreadable`; L4 test. F-4 for the other routes |
| R-02 | SHOULD-FIX | Rows could be created under a category that came to match after the locks, without this command's lock | Fixed: `CheckedImport::refuseRowsOutside`; regression test at the barrier `after_lock:6` |
| R-03 | SHOULD-FIX | `import` of RD-01 in any context | Fixed: only `catalog`, stated in both contracts; every other context still answers FORBIDDEN first, so the test reads the rule itself and a cashier's request |
| R-04 | NOTE | Outcomes that leave the file to housekeeping | Reading 11 and F-1 |
| R-05 | NOTE | An unquoted `=HYPERLINK("x")` read as `invalid_value` | Fixed: the formula first; test |
| R-06 | NOTE | A header cell `"SK"U` accepted as `SKU` | Fixed: `unknown_column`; test |
| R-07 | NOTE | Nothing proved the ascending order of the locks | Fixed: a test over a file naming two categories |
| R-08 | NOTE | The image checks did not cover the new folder | Fixed: its owner and mode, and the web server's refusal; runs on the final head |
| R-09 | NOTE | The corrections of documents and the reading of UXS-32 unrecorded | Sections 3 and 4 item 13; backup impact in section 6 |

The executor's self-review of the whole diff against the contract preceded it (EXC-70).

## 9. The guard's candidates (CE-31)

| Candidate | Judgment |
| --- | --- |
| `10.000.000.000`, shape of an address, in `tests/Unit/Catalog/CsvDialectTest.php` | A minimum stock above the limit in Indonesian format, no address; accepted on `9ff2279` |

## 10. For later Tasks

TASK-038 removes temporary CSV files older than 24 hours on the disk `csv_imports`, whatever their state (F-1); TASK-048 measures 5,000 rows (F-2); TASK-021's stock tables come under "no stock or cost" of AC-019-03; a later context of RD-01 stays refused `import`.
