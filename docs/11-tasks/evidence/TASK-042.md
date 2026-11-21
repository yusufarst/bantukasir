# TASK-042 evidence — Audit log and security log views

| | |
| --- | --- |
| Task | [TASK-042](../tasks/TASK-042-audit-views.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high`. Resumed once (CE-24) by a successor executor of the same agent, model and effort after the laptop shut down at the phase `BUILD` on checkpoint 3 (`1248ca6`, pushed, fast run 2 green), the lock taken over at 18:19 (`INACTIVE BOOT`); it kept the record's uncommitted updates and finished the review, the record and the pull request |
| Branch and base | `task/TASK-042-audit-views`, from `14b6383675f72351f91f25b66ea0f36ffa5dfb58` (origin/develop, the squash merge of TASK-041's pull request #56) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the stash of `plan/bantukasir-pos-v1` untouched; no branch or pull request of TASK-042 and no open pull request; the executor state at `BUILD` for TASK-042 with this session's lock, the merge of #56 verified; TASK-036 and TASK-035 `DONE`, no gate. PHP, the native `tsc` and ESLint are not on this machine (`npm run typecheck` answers that `tsc` is not recognized), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 15:10 | under 1 min | `NOT_RUN`: `tsc` is not installed natively (`'tsc' is not recognized`), nor ESLint or PHP; the fast run runs them |
| Guard | Local, on `b144268` (checkpoint 1) | 15:33 to 15:37 | about 4 min | One candidate, `192.0.2.7` in `AuditViewsTest`, the documentation range of RFC 5737 (TEST-NET-1), synthetic as in `SignInTest`: judged no finding (section 10). Its run with `--accept` stopped unfinished, superseded by checkpoint 2 |
| Guard | Local, on `6557b94` (checkpoints 1 and 2) | 15:41 to 15:51 | about 10 min, two passes | The same one candidate, its hash changed with the range (`REFUSED HASH`), judged again no finding; `GUARD ACCEPTED` on the second pass |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files | Fast run 1, 44886915703 on `6557b94` | 15:52 to 16:04 | 11 min 44 s for the job | `FAIL` (F-4): every step `PASS` — `composer lint`, `test:unit` (`AuditActionsTest`, `CopyDeckTest`), `npm run lint`, `typecheck` and `test:unit` (`activity.test.ts`), and the changed PostgreSQL files (`AuditViewsTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `SettingsPagesTest`) — except `composer analyse`, two PHPStan findings |
| The same checks, the targeted retest of F-4 (D-133) | Fast run 2, 44888901868 on `1248ca6` (checkpoint 3) | 16:17 to 16:26 | 8 min 36 s for the job | `PASS`: every step, `composer analyse` included |
| Guard | Local, on `3cdcdf3` (checkpoint 4, the fix of R-01) | 18:33 to 18:36 | about 3 min | `GUARD PASS`, no candidate |
| The same checks, the targeted retest of R-01 (D-133) | Fast run 3, 44899927184 on `3cdcdf3` (checkpoint 4) | 18:36 to 18:48 | 12 min 10 s for the job | `PASS`: every step, `AuditViewsTest` with R-01's regression test among the changed PostgreSQL files |
| Guard preview of the record | Local, the uncommitted record against `origin/develop` | 18:37 | under 1 min | Two candidates, judged no finding (section 10) |
| `composer test:postgres` (four parts), `test:race`, the build, the browser suite, the image checks, the restore rehearsal | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. RP-612, UXP-612 "Riwayat aktivitas": the filters by date, user, type (action code), product, cash session and reference (UXI-377), each an allowlist; keyset paging both ways (UXI-380); each row's time, actor as frozen, action label and reference (UXI-378, UXI-379), its reason on demand, the Owner-only reason only to the Owner (C-5, section 12); the label of every action code of INTERACTION_CONTRACTS section 8, the five of TASK-002 included; a read of a filter or a page that fails is UXS-22 with "Coba lagi", never the earlier rows (both views); "Kembali ke Pengaturan" (UXI-381) | `App\Domain\Audit\AuditActions`, `App\Queries\Audit\ActivityLog`, `App\Http\Requests\Audit\ActivityPageRequest`, `App\Http\Controllers\Audit\ActivityController`, `routes/web.php`; `resources/js/pages/audit/Activity.tsx`, `resources/js/lib/audit/activity.ts`, `resources/js/lib/catalog/ProductFilter.tsx` (the filter "Produk", now shared with UXP-402); `lang/id/audit.php`, `lang/id/ux.php` |
| 2. RP-613, UXP-613 "Riwayat keamanan": the filters by date, type and user (UXI-382), the outcome labels SUCCEEDED and REFUSED, each entry's details on demand (UXI-383), keyset paging (UXI-384), "Kembali ke Pengaturan" (UXI-385) | `App\Queries\Audit\SecurityLogEntries`, `App\Http\Requests\Audit\SecurityLogPageRequest`, `App\Http\Controllers\Audit\SecurityLogController`; `resources/js/pages/audit/SecurityLog.tsx` |
| 3. The staff's audit within their scope (BR-AUD-04) | Read as C-6: the read of the business audit keeps the scope itself — no Owner-only data and no event of the category SECURITY for a reader who is not the Owner — and the staff's approved pages show their scope as before, without the Owner-only data (`AuditViewsTest`) |
| 4. The settings index entries UXI-330 and UXI-331 | `SettingsController::ENTRIES`, `resources/js/pages/access/Settings.tsx`, `lang/id/config.php` |
| The controls extended with a destination: UXI-177, UXI-251, UXI-267, UXI-349, the way back to UXP-612 with its filters and page | `activityBack()` of `resources/js/lib/audit/activity.ts` in `catalog/Product.tsx`, `inventory/Receiving.tsx`, `inventory/Issue.tsx`, `access/User.tsx` |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| C-1 | **The module.** The audit log views are the Reporting module's (STACK_AND_MODULES section 2: "audit log views"), which reads every domain module and writes none; they sit in `app/Queries/Audit/` and `Http/Controllers/Audit/` as the contract (section 13) and ROUTE_CONTRACTS name them, so no new dependency between modules is recorded |
| C-2 | **The filters.** `user` is the acting account; `action` one code of section 8; `product` and `session` the event's columns of BR-AUD-04, the session named by its number; `reference` matched exactly, the event's own reference or the number of the Sale it names (a checkout records its Sale by its key, not by a reference). Days of Asia/Jakarta as the other lists. The accounts of "Pengguna" are every account by its name now, bounded at 500 |
| C-3 | **The frozen identity (AC-042-02).** An event shows `actor_display_name`, the name copied in its transaction (SCHEMA section 3.5, G-16), so a deactivated or anonymized user's events keep the name of that moment and a combined account's events name its one account; a process is "Prosedur operator" or "Sistem" (COPY_DECK section 3). The security log freezes no name (section 3.5 lists no copy there), so its entries name the account by its name now, after an anonymization its neutral label (D-043); its filter "Pengguna" finds the entries the account acted in or that concern it, so that a refused sign-in to the account is found by it |
| C-4 | **The reference (UXI-379).** A Sale, a correction (its Sale), a cash session and its close, note or report (the session), a receiving document, a manual issue, a reversal (the reversed movement), a user, and a product or its barcode lead to their pages; the link's text is the object's number or name, or the page's title for an object without either; any other object is its reference as text |
| C-5 | **The details.** Since the fix cycle (section 12), UXP-612's details hold the event's reason — its own, or else the reason typed with the change and kept under `reason` in its change data, or, for the Owner alone, in its Owner-only data (the reason of a cost correction, D-047); no other field of the change data leaves the read, because COPY_DECK labels none (OPEN_ITEMS T-56), and a row without a reason has no expander. UXP-613's details (UXI-383, "Row, expandable") hold the two columns of COPY_DECK section 11.13 that its row would crowd, "Akun terkait" and "Alamat jaringan"; the row shows time, type, actor and outcome. Both use the expander's key `audit.activity.expand` and the header `audit.activity.details`; a row with nothing to open has no expander. Never a request identifier, a route, an operator's note or a command |
| C-6 | **The staff's scope (scope item 3).** RP-612 and RP-613 are CAP-32's alone (WF-31); a cashier's own Sales and sessions (CAP-21) and Operations' stock history (CAP-14) stay on the approved pages that already show them (UXP-201, UXP-205, UXP-402), which read no audit. `ActivityLog::list(…, owner: false)` keeps BR-AUD-04 in the read itself, whoever calls it |
| C-7 | **The copy keys.** COPY_DECK names `audit.activity.expand` in a note with its copy in quotes and the event types after the first as `.sign_in_failed`; `CopyDeckTest` reads both forms (section 6). The page title of UXP-613 is "Riwayat keamanan" (COPY_DECK section 4.1, changed from "Log keamanan") |
| C-8 | **The way back.** As the other detail pages do, a page opened from UXP-612 reads its opener from the referrer and returns there with the filters and page of its address (NC-10); UXP-604's label becomes "Kembali ke Riwayat aktivitas" (UXI-349). RP-604's `from_page`, which TASK-011 accepted for this (its R-8), names a page but not its filters, so the link carries none and the parameter stays as it is |
| C-9 | **The product filter** finds the events whose `product_id` is the product, the column of BR-AUD-04: a document of several products names none (TASK-021 F-4, its reading 9), and is found by its reference, its user or its day |

## 4. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-042-01 | `AuditViewsTest` (the filters by date, user, type, product, session and reference, both views' refusals for Operations, the cashier and the combined account; R-01's regression test), `CapabilityMatrixTest` RP-612 and RP-613, `audit.spec.ts` | `PASS` at L4 in fast run 3 (`3cdcdf3`), invalidated by the fix cycle's change of `AuditViewsTest` and `Allowlists` (section 12) and run again on its new head; L5 `audit.spec.ts` `NOT_RUN` until the final head |
| AC-042-02 | `AuditViewsTest::test_an_event_keeps_the_name_of_its_moment_and_a_combined_account_names_one_identity` | `PASS`, fast run 3 |
| AC-042-03 | `tests/Unit/Audit/AuditActionsTest.php` (the codes and keys of section 8 and COPY_DECK section 11.14, every code the application writes, every event type and outcome), `tests/Unit/Audit/activity.test.ts`, `CopyDeckTest` | `PASS`, fast run 3 |
| AC-042-04 | `AuditViewsTest::test_the_owner_only_data_reaches_the_owner_alone_and_never_a_staff_payload`, the allowlists of both pages (`Allowlists`) | `PASS`, fast run 3; changed by the fix cycle (section 12), invalidated and run again by its fast run |

## 5. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Carried for this Task: TASK-017 F-7 and TASK-011 F-4 and R-8 — the openers of UXI-177 and UXI-349 | Done: UXP-612 opens UXP-302 and UXP-604, whose ways back return to it (C-8) |
| F-2 | Carried for this Task: TASK-020 F-3 — `BARCODE_CORRECTION` with its reason and change data; TASK-035 — the labels of its five actions | Done: every code of section 8 labelled (AC-042-03); the reason in the details (C-5); since fix cycle 1 its change data is withheld until COPY_DECK labels it (OPEN_ITEMS T-56) |
| F-3 | Carried for this Task: TASK-021 F-4 — the product filter and a document of several products | Read as C-9 |
| F-4 | Fast run 1 (44886915703 on `6557b94`), `composer analyse`: `arrayValues.list` at `ActivityLog.php:98` (`array_values` of a list) and `argument.type` at `AuditViewsTest.php:197` (`array_unique` of `list<mixed>`) | Fixed in one push (checkpoint 3): the list returned as it is, the test's closure typed `string`; fast run 2, their one targeted retest (D-133), `PASS` |
| F-5 | The review's findings R-01 to R-07 (section 9) | R-01 fixed (checkpoint 4); R-02 and R-03 fixed in this record; R-04 recorded as OPEN_ITEMS T-55; R-03's measurement for TASK-048; R-05 to R-07 kept as readings |
| F-6 | The automated reviewer's P1 on pull request #57 (review comment 4652345963): the details showed the change data's stored field names as labels (section 12) | Fixed in fix cycle 1 with its regression tests; the missing copy recorded as OPEN_ITEMS T-56 |

## 6. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L4 filters, scope and payloads per role: only the Owner reaches both views and their entries, never stored, a read writing nothing; every parameter an allowlist; each filter and the keyset pages of both views; the frozen identity and the combined account; the Owner-only data for the Owner alone and the read's own scope; the staff's pages without it | `tests/Feature/Audit/AuditViewsTest.php`; `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`; `tests/Feature/Configuration/SettingsPagesTest.php`; `tests/Harness/Channels/Allowlists.php` |
| L1 the labels and the source scan; the interface's labels, actors, references, addresses and way back | `tests/Unit/Audit/AuditActionsTest.php`, `tests/Unit/Audit/activity.test.ts`; `tests/Unit/Localization/CopyDeckTest.php` |
| L5 RP-612 and RP-613: from "Pengaturan", a filter in the address, the details, the reference to UXP-604 and back with the filter, the security log's filter and details, 1440 px, 320 px, 200 % zoom with the keyboard, Operations refused | `tests/Browser/audit/audit.spec.ts` (project `people`); the settings index's six entries in `configuration/profile.spec.ts` |

## 7. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A. The filters by day, user, product, session and the event's own reference use the indexes of TASK-006 (`occurred_at, id`; `actor_user_id`, `product_id`, `cash_session_id` with `occurred_at, id`; `reference`); no index serves the action code of RP-612, the event type of RP-613 or the reference's branch of the Sale it names, which walk `(occurred_at, id)` within the page's limit (review R-03; measured at TASK-048, section 11) |
| EXC-45 | The fixtures through the Actions: products, receipts, registers, sessions, Sales, a cost correction, the deactivation and anonymization, a category of the combined account (`TokoContoh` and the Actions) |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `PASS` — AC-042-01 to AC-042-04 at L1 and L4 in fast run 3, the L4 results of AC-042-01 and AC-042-04 invalidated by fix cycle 1 and run again on its new head (section 12); REQ-AUD-02 and REQ-RPT-05 by RP-612 and RP-613 |
| UI and UX | `NOT_RUN` — the copy keys and labels `PASS` at L1 (fast run 3); the L5 tests of `audit.spec.ts` and `profile.spec.ts` on the pull request's final head. A deviation from UXI-378, reported to the Owner under section 14 item 7: "Perubahan" of its expanded row is not rendered, because COPY_DECK labels no field of the change data (OPEN_ITEMS T-56); and no browser fixture writes an event with a reason, so UXP-612's open expander has no L5 proof — its keyboard path, 200 % zoom and axe check while open stay on UXP-613's, for AUDIT01's final point at TASK-044 |
| Backend | N/A — no route or Action beyond RP-612 and RP-613 |
| Database | N/A — no migration |
| Security | `PASS` (the channel test of UXP-612 invalidated by fix cycle 1 and run again on its new head) — the gates, the refusals of Operations, the cashier and the combined account, the allowlists of parameters and payloads, no Owner-only data or SECURITY event outside CAP-32, the guard; the whole PostgreSQL suite on the final head |
| Data integrity | N/A — writes no business fact |
| Performance | `PASS` — every statement bounded by a page or a limit, keyset paging; the unindexed filters of R-03 and the budgets measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | `NOT_RUN` — self-review and the class-A review done (section 9), fast runs 2 and 3 `PASS`; the command contract and the image checks on the pull request's final head |

## 9. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review: both reads against BR-AUD-02 to BR-AUD-05, SEC-LOG-03, SCHEMA section 5.4 and RT-08 — every parameter an allowlist in the route and the FormRequest, keyset paging both ways with the cursor read again under the filters, every statement bounded by a page or a limit, the Owner-only data selected only for CAP-32 and the category SECURITY excluded for anyone else, no request identifier, route, operator's note or command on any channel; the gates of both routes and the entries of the index; the labels of section 8 held to the documents and to the Actions' codes; the copy keys verbatim; the controls of section 4.2 and the four extended ways back; the obligations of EXECUTION_CONTEXT section 10 for two routes, two reads and two pages. Repeated by the successor executor on resume (the laptop shut down after checkpoint 3) over `ActivityLog` and its controller: the Owner-only column selected and shown only for CAP-32, the category SECURITY kept from any other reader, every statement bounded.

Round 1, the independent adversarial review of class A (EXC-71), once:

| | |
| --- | --- |
| Agent | `bk-reviewer-high`, model `claude-opus-5-5`, effort High, tools Read, Grep and Glob |
| Package | `.git/bantukasir-executor/review/TASK-042/r1`, base `14b6383`, tree `cf5e490` (HEAD `1248ca6` with the uncommitted record), written 18:21; `--check` after the review: `UNCHANGED` |
| Verdict | `PASS`, no MUST-FIX: 2 SHOULD-FIX, 5 NOTE; no test run asked for |
| Verification | None: no MUST-FIX, so no round 2 (CE-40) |

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | AC-042-01: "Sesi kas" sent any text, RP-612 refused one not of the form `^[0-9]{8}-[0-9]{6,19}$` with 422, and the page showed UXS-22 "Coba lagi", which retried the same refusal; the field's numeric keypad had no hyphen | Fixed in checkpoint 4 (`3cdcdf3`): `session` is a text of up to 40 characters without a control character, matched exactly, so a number no session has finds nothing (TR-PAGE-03), as an unknown key does; the field without `inputMode` numeric. Regression test: `AuditViewsTest` — `2026`, `20261115-1`, `20261115999999` and `LACI 1` answer 200 with no rows; 41 characters and a control character stay refused. Fast run 3, its targeted retest, `PASS` |
| R-02 | SHOULD-FIX | EXC-61, EXC-62, section 13: the Definition of Done and the criteria's statuses stale, without the vocabulary; the review record missing | Fixed in this record: sections 4, 8 and 9 |
| R-03 | NOTE | No index serves the filters by action code, event type or the Sale's branch of the reference; bounded by the page's limit | Section 7 corrected; measured against the budgets at TASK-048 (section 11) |
| R-04 | NOTE | UXI-379's "returns here" for seven pages, while only the four controls of the contract's section 4.2 name UXP-612 as an opener; UXP-202, UXP-206 and UXP-403 return to their usual openers | Recorded as OPEN_ITEMS T-55; nothing to change in this Task |
| R-05 | NOTE | `ADJUSTMENT_CONFIRM`, `OPENING_STOCK_CONFIRM` and `COST_CORRECTION` with one product show its name as text, not a link | Kept as reading C-4: the reference is the event's subject; the text is accurate and nothing leaks |
| R-06 | NOTE | "Akun terkait" and "Alamat jaringan" of UXP-613 in the details, not as columns; the keys of UXP-612 borrowed | Kept as reading C-5 ("Row, expandable", UXI-383) |
| R-07 | NOTE | RP-613: after housekeeping removes every entry beyond an `after` cursor, an empty page offers no "Sebelumnya" | Recorded, no change: an edge of SEC-LOG-05's removal only; RP-612's audit is append-only |

After the review the working tree, the index, HEAD and the executor state were as the package's manifest records them (`--check` `UNCHANGED`).

## 10. Guard's candidates (CE-31; D-076, D-097, D-098)

| Candidate | Where | Judgment |
| --- | --- | --- |
| `192.0.2.7` (ipv4) | `tests/Feature/Audit/AuditViewsTest.php`, added in `b144268` | The documentation range of RFC 5737 (TEST-NET-1), the client address of a refused sign-in in the test, synthetic as `SignInTest`'s `192.0.2.x`: no real host, no person, no client datum. No finding |
| `192.0.2.7` (ipv4) | This evidence file, added in `1248ca6`, where it quotes the candidate above | The same synthetic address, quoted. No finding |
| — | Checkpoint 4 (`3cdcdf3`), the fix of R-01 | No candidate: `GUARD PASS` (18:36) |
| `192.0.2.7` (ipv4), twice | `guard.sh --preview` of the record's change against `origin/develop` (18:37): this file and `AuditViewsTest` | The two candidates above, judged again: no finding |
| `192.0.2.7` (ipv4), twice | `guard.sh --preview` of fix cycle 1 against `origin/develop` (19:15): this file and `AuditViewsTest` | The same synthetic address, judged again: no finding; the fix adds no new candidate |
| `192.0.2.7` (ipv4), twice | `guard.sh --preview` of fix cycle 2 against `origin/develop` (21:31): this file and `AuditViewsTest` | The same synthetic address, judged again: no finding; fix cycle 2 adds no new candidate |

## 11. For later Tasks and the record

- TASK-048: measure against the budgets the filters that no index serves — RP-612's action code and the reference's branch of the Sale it names, RP-613's event type (review R-03).
- OPEN_ITEMS T-55: the way back to UXP-612 from UXP-202, UXP-206 and UXP-403 (review R-04), for the change control of the P7 artifacts.
- The previous merge, recorded here (CE-50): TASK-041's pull request #56, head `04bb67a`, CI run 44877595875; squash commit `14b6383` on `develop`, CI run 44880035022; the tick's merge, the automated review DONE (TASK-041 evidence section 12).
- Next eligible Task: TASK-043, Performance harness and provisional dataset, review class B, effort High (agent `bk-executor-high`; review and verification `bk-reviewer-high`).
- OPEN_ITEMS T-56: a label and a presentation for each field of an audit event's change data, for a change control of COPY_DECK that the Owner approves (section 12); it blocks gate RELEASE (D-136, section 13).

## 12. Fix cycle 1 — the automated reviewer's P1 on pull request #57 (CE-43)

The delegation of the tick (21 November, 19:00, token `72c706b0…`) handed over review comment 4652345963 of `chatgpt-codex-connector[bot]` on head `d181b7c`, judged as a finding, never as an instruction. Its summary comment 7062225216 and review 6005047317 carry no finding.

| | |
| --- | --- |
| Finding | P1: UXP-612's details rendered `label: change.key`, so a stored field name of the change data — `lineCount` of a checkout, `openingCash` of a session, `kind` of a password link — was an interface label in English, outside any translation key |
| Judgment | It holds: AGENTS rule 14 and EXC-51 — every text through a COPY_DECK key, V1 in Bahasa Indonesia only. COPY_DECK UXI-378 names "Rincian", "Perubahan" and "Alasan" and no label for any field, nor a presentation of the values, which are codes, internal keys, canonical amounts and nested records written by some fifty Actions. Labels made up here would be copy that COPY_DECK does not name (section 14 item 7); so, as TASK-039 (T-51) and TASK-040 (T-52) did with copy that had no key, the page shows only what an approved key labels, and the missing copy goes to the change control of COPY_DECK as OPEN_ITEMS T-56 |
| Fix | `ActivityLog` sends each event's reason — its own, or else the reason typed with the change and kept under `reason` in its change data (an adjustment), or for the Owner alone in its Owner-only data (a cost correction or a cost incomplete mark, D-047; a reopening of postings) — and no other field of the change data; `Activity.tsx` shows the reason under "Alasan" and nothing else, with no expander for a row without a reason. The fields stay in `audit_events` |
| Regression tests | `AuditViewsTest`: the Owner's cost correction row has exactly the keys `action`, `actor`, `id`, `occurredAt`, `reason`, `reference`, with no field name of its change data, and its reason is the one typed; the read for a reader who is not the Owner has no reason for it and no trace of it. `Allowlists` no longer admits `events.rows.*.changes`, so a page that carries the change data again fails UXP-612's channel test. `audit.spec.ts`: the LINK_CONSUME row shows no expander, no `kind` and no "Perubahan"; the keyboard path of an entry's details stays on UXP-613 |
| Gap | No browser fixture writes an event with a reason, so UXP-612's expander with a reason is proven on its payload at L4, not at L5 |
| Verification (CE-43, class A) | `bk-reviewer-high`, model `claude-opus-5-5`, effort High, package `.git/bantukasir-executor/review/TASK-042/r2` (tree `7161cc9`, previous r1), 19:09 to 19:14, about 5 min; `--check` `UNCHANGED`. Verdict `PASS`: the P1 FIXED, BR-AUD-02, BR-AUD-04 and AC-042-04 held, no regression in the code; findings below |

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| V-01 | SHOULD-FIX | F-2's outcome and this section's reopening and channel test inconsistent with the fix | Fixed in this record |
| V-02 | SHOULD-FIX | AC-042-01, Business and Security still `PASS` on results the fix invalidated | Marked invalidated (sections 4 and 8) |
| V-03 | SHOULD-FIX | UXP-612's open expander has no L5 proof | Recorded in the Definition of Done for AUDIT01's final point at TASK-044; a browser fixture with a reason would widen this fix cycle's scope |
| V-04 | SHOULD-FIX | The omission of UXI-378's "Perubahan" is a deviation from an approved artifact to report to the Owner | Recorded in the Definition of Done; T-56 names the Owner as decider; in the report |
| V-05 | NOTE | `audit.activity.changes` unused by the code | Kept for T-56 |
| V-06 | NOTE | No L4 assertion of the change data's `reason` (an adjustment) | Recorded; the branch shares `reasonIn()` with the Owner-only case that the regression test covers |
| V-07 | NOTE | The event's own `reason` column is not checked for blank; no Action writes a blank one | Recorded, no change |

The verification's findings are numbered V- here to keep them apart from round 1's R-01 to R-07.

### 12.1 Checks of the fix cycle

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, ESLint, PHP | Local | — | — | `NOT_RUN`: not installed natively (section 1); CI on the new head runs them |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files, `AuditViewsTest` among them | Fast run and CI on the pull request's new head | — | — | `NOT_RUN` until that run, the fix's one targeted retest (D-133) |
| The whole command contract, the browser suite with `audit.spec.ts`, the image checks | CI run 44903440436 on the pull request's head `360a230` | 19:20 to 19:51 | about 31 min | `FAIL`, failure 1 of 3 (CE-42): the static checks, unit tests, `test:race`, the build, the restore rehearsal and PostgreSQL parts 1 and 2 `PASS`; the browser suite 342 passed, 2 failed; the four failed jobs below (section 13) |

## 13. Fix cycle 2 — CI run 44903440436 on `360a230` (CE-42)

The delegation of the tick (21 November, 19:51, token `c1b58396…`) handed over the red run; that cycle diagnosed it and stopped `OWNER_DECISION` for the image checks. The Owner decided D-135 and D-136 (`ops/` pull request #58, merged into `develop` as `d581e95`) and resumed the loop; the resumed executor (21:18, token `3ec7e11b…`, same agent, model and effort) fixed the three defects in one push.

| Job | Failure | Cause | Fix |
| --- | --- | --- | --- |
| Image checks | The gate refused ten High findings already accepted (D-112 and D-115 glibc, D-134 OpenSSL) at the Debian point release's versions openssl 3.5.9-1~deb13u1 and glibc 2.41-12+deb13u5 | Not this Task's code: the accepted list named the earlier versions | The Owner's D-135 accepts them again at those versions; `develop` merged into the branch by the merge commit `8145f73` (EXC-36), so the image checks read D-135's list. No other change |
| Command contract | `audit.spec.ts:74` and `:164`, axe `landmark-unique` on `form:nth-child(5)` | UXP-612's two `<form role="search">` ("Sesi kas" and "Referensi") had no accessible name, so the two search landmarks could not be told apart. `ProductFilter` renders no form or landmark | `Activity.tsx`: each form is named by its field's existing COPY_DECK label (`audit.activity.filter_session`, `audit.activity.filter_reference`); no new copy. `audit.spec.ts`: the six filters are found as their controls (the form now shares its field's name), and the two search landmarks by their names |
| PostgreSQL (3/4) | `CheckoutSaleTest:798` (`sales`) and `RecordCorrectionTest:450` (`sale_corrections`) | The source scans of ARC-20 and ARC-21 found `Queries/Audit/ActivityLog.php`, a reader that neither test named | `ActivityLog` confirmed to only read (no insert, update, delete, increment, truncate, lock or `DB::statement` in `app/Queries/Audit/`); named as a reader in both tests, each of which now also holds it to no write |
| PostgreSQL (4/4) | `ConfirmReceivingTest:660` (`receiving_documents`) | The same scan, the same reader | Named as a reader; held to no write |

The P1 fix of fix cycle 1 stays: UXP-612's details show only "Alasan". OPEN_ITEMS T-56 now cites D-136: it blocks gate RELEASE.

Verification (CE-42, class A, the form's accessible name being a change of the page): `bk-reviewer-high`, model `claude-opus-5-5`, effort High, package `.git/bantukasir-executor/review/TASK-042/r3` (tree `eb3f290`, HEAD `8145f73`, previous r2), 21:27 to 21:30, about 3 min; `--check` `UNCHANGED`. Verdict `PASS`: the landmark names and the three source scans FIXED, ActivityLog read-only, the P1 fix intact, no MUST-FIX.

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| V-08 | SHOULD-FIX | D-136 asks the record in CURRENT_HANDOFF too; it named T-56 only for COPY_DECK's change control | Fixed: TASK-042's entry of CURRENT_HANDOFF cites D-136 and the block of RELEASE |
| V-09 | SHOULD-FIX | T-56's route left decisions to the planning agent and named TASK-047 as if by default | Fixed: every decision the Owner's or a planning change he approves; TASK-047 only a possible vehicle |
| V-10 | NOTE | The glibc finding CVE-2026-84243 was accepted by D-115 | Fixed in the row above |
| V-11 | NOTE | Section 11 described T-56 without D-136 | Fixed |

### 13.1 Checks of fix cycle 2

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, ESLint, PHP | Local | — | — | `NOT_RUN`: not installed natively (section 1) |
| The whole command contract with `audit.spec.ts`, the four PostgreSQL parts with the three changed tests, the image checks with D-135's list | CI on the pull request's new head, the fix's one targeted retest (D-133) | — | — | `NOT_RUN` until that run |

## 14. Merge

Recorded by TASK-043 (EXECUTION_CONTEXT CE-50): pull request #57, head `ecf9865f6085ad1d3e675138681c1ece0c725418`, CI run 44915121486 passed at attempt 2 (the Owner's note: every required job PASS), after the stop `CI_TIMEOUT` of attempt 1 and his resume; squash-merged by the tick under D-105 as `6203e9048024f47cf73b241dfc9ca16b6db97066` on `develop`, the automated review's status DONE; CI on `develop` run 44922101510 passed; verified by the tick on 21 November 2026 (`MERGE_VERIFIED pr=57`). TASK-042 is `DONE`. OPEN_ITEMS T-56 is named for TASK-051 by D-137.
