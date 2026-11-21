# TASK-042 evidence — Audit log and security log views

| | |
| --- | --- |
| Task | [TASK-042](../tasks/TASK-042-audit-views.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-042-audit-views`, from `14b6383675f72351f91f25b66ea0f36ffa5dfb58` (origin/develop, the squash merge of TASK-041's pull request #56) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the stash of `plan/bantukasir-pos-v1` untouched; no branch or pull request of TASK-042 and no open pull request; the executor state at `BUILD` for TASK-042 with this session's lock, the merge of #56 verified; TASK-036 and TASK-035 `DONE`, no gate. PHP, the native `tsc` and ESLint are not on this machine (`npm run typecheck` answers that `tsc` is not recognized), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 15:10 | under 1 min | `NOT_RUN`: `tsc` is not installed natively (`'tsc' is not recognized`), nor ESLint or PHP; the fast run runs them |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. RP-612, UXP-612 "Riwayat aktivitas": the filters by date, user, type (action code), product, cash session and reference (UXI-377), each an allowlist; keyset paging both ways (UXI-380); each row's time, actor as frozen, action label and reference (UXI-378, UXI-379), its changes and reason on demand, the Owner-only data only to the Owner; the label of every action code of INTERACTION_CONTRACTS section 8, the five of TASK-002 included; a read of a filter or a page that fails is UXS-22 with "Coba lagi", never the earlier rows (both views); "Kembali ke Pengaturan" (UXI-381) | `App\Domain\Audit\AuditActions`, `App\Queries\Audit\ActivityLog`, `App\Http\Requests\Audit\ActivityPageRequest`, `App\Http\Controllers\Audit\ActivityController`, `routes/web.php`; `resources/js/pages/audit/Activity.tsx`, `resources/js/lib/audit/activity.ts`, `resources/js/lib/catalog/ProductFilter.tsx` (the filter "Produk", now shared with UXP-402); `lang/id/audit.php`, `lang/id/ux.php` |
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
| C-5 | **The details.** The changes are the event's safe change data, and for the Owner its Owner-only data after them, as pairs of a field and its value as recorded; the reason when there is one. UXP-613's details (UXI-383, "Row, expandable") hold the two columns of COPY_DECK section 11.13 that its row would crowd, "Akun terkait" and "Alamat jaringan"; the row shows time, type, actor and outcome. Both use the expander's key `audit.activity.expand` and the header `audit.activity.details`; a row with nothing to open has no expander. Never a request identifier, a route, an operator's note or a command |
| C-6 | **The staff's scope (scope item 3).** RP-612 and RP-613 are CAP-32's alone (WF-31); a cashier's own Sales and sessions (CAP-21) and Operations' stock history (CAP-14) stay on the approved pages that already show them (UXP-201, UXP-205, UXP-402), which read no audit. `ActivityLog::list(…, owner: false)` keeps BR-AUD-04 in the read itself, whoever calls it |
| C-7 | **The copy keys.** COPY_DECK names `audit.activity.expand` in a note with its copy in quotes and the event types after the first as `.sign_in_failed`; `CopyDeckTest` reads both forms (section 6). The page title of UXP-613 is "Riwayat keamanan" (COPY_DECK section 4.1, changed from "Log keamanan") |
| C-8 | **The way back.** As the other detail pages do, a page opened from UXP-612 reads its opener from the referrer and returns there with the filters and page of its address (NC-10); UXP-604's label becomes "Kembali ke Riwayat aktivitas" (UXI-349). RP-604's `from_page`, which TASK-011 accepted for this (its R-8), names a page but not its filters, so the link carries none and the parameter stays as it is |
| C-9 | **The product filter** finds the events whose `product_id` is the product, the column of BR-AUD-04: a document of several products names none (TASK-021 F-4, its reading 9), and is found by its reference, its user or its day |

## 4. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-042-01 | `AuditViewsTest` (the filters by date, user, type, product, session and reference, both views' refusals for Operations, the cashier and the combined account), `CapabilityMatrixTest` RP-612 and RP-613, `audit.spec.ts` | See section 1 |
| AC-042-02 | `AuditViewsTest::test_an_event_keeps_the_name_of_its_moment_and_a_combined_account_names_one_identity` | See section 1 |
| AC-042-03 | `tests/Unit/Audit/AuditActionsTest.php` (the codes and keys of section 8 and COPY_DECK section 11.14, every code the application writes, every event type and outcome), `tests/Unit/Audit/activity.test.ts`, `CopyDeckTest` | See section 1 |
| AC-042-04 | `AuditViewsTest::test_the_owner_only_data_reaches_the_owner_alone_and_never_a_staff_payload`, the allowlists of both pages (`Allowlists`) | See section 1 |

## 5. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Carried for this Task: TASK-017 F-7 and TASK-011 F-4 and R-8 — the openers of UXI-177 and UXI-349 | Done: UXP-612 opens UXP-302 and UXP-604, whose ways back return to it (C-8) |
| F-2 | Carried for this Task: TASK-020 F-3 — `BARCODE_CORRECTION` with its reason and change data; TASK-035 — the labels of its five actions | Done: every code of section 8 labelled (AC-042-03); the reason and the change data in the details (C-5) |
| F-3 | Carried for this Task: TASK-021 F-4 — the product filter and a document of several products | Read as C-9 |

## 6. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L4 filters, scope and payloads per role: only the Owner reaches both views and their entries, never stored, a read writing nothing; every parameter an allowlist; each filter and the keyset pages of both views; the frozen identity and the combined account; the Owner-only data for the Owner alone and the read's own scope; the staff's pages without it | `tests/Feature/Audit/AuditViewsTest.php`; `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`; `tests/Feature/Configuration/SettingsPagesTest.php`; `tests/Harness/Channels/Allowlists.php` |
| L1 the labels and the source scan; the interface's labels, actors, references, addresses and way back | `tests/Unit/Audit/AuditActionsTest.php`, `tests/Unit/Audit/activity.test.ts`; `tests/Unit/Localization/CopyDeckTest.php` |
| L5 RP-612 and RP-613: from "Pengaturan", a filter in the address, the details, the reference to UXP-604 and back with the filter, the security log's filter and details, 1440 px, 320 px, 200 % zoom with the keyboard, Operations refused | `tests/Browser/audit/audit.spec.ts` (project `people`); the settings index's six entries in `configuration/profile.spec.ts` |

## 7. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A. The filters use the indexes of TASK-006 (`occurred_at, id`; `actor_user_id`, `product_id`, `cash_session_id` with `occurred_at, id`; `reference`) |
| EXC-45 | The fixtures through the Actions: products, receipts, registers, sessions, Sales, a cost correction, the deactivation and anonymization, a category of the combined account (`TokoContoh` and the Actions) |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged |

## 8. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `NOT_RUN` until the fast run |
| UI and UX | `NOT_RUN` until the fast run and the final head |
| Backend | N/A — no route or Action beyond RP-612 and RP-613 |
| Database | N/A — no migration |
| Security | `NOT_RUN` until the fast run |
| Data integrity | N/A — writes no business fact |
| Performance | Bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | `NOT_RUN` |
