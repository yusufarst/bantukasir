# TASK-009 evidence — Command client, recovery and unresolved requests

| | |
| --- | --- |
| Task | [TASK-009](../tasks/TASK-009-command-client.md), size M, review class A, lane P |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105 and D-107, continuous execution (EXECUTION_CONTEXT section 19, CE-01): the selection of CE-10 at `origin/develop` `e54cbab`, the lowest-numbered `READY` Task with its dependencies `DONE` and its gate GATE-CC1 satisfied; mode `start`, delegated by the tick to `bk-executor-high` on 28 October 2026 at 16:32:44, after it verified the merge of TASK-008; resumed in `REVIEW` on 29 October 2026 at 22:23 under D-108, on the Owner's note naming the merge of pull request #14 and the move of the branch to `5f20137` |
| Branch | `task/TASK-009-command-client`, created from `origin/develop` (CE-02) |
| Base commit | `e54cbab04dc5106982cd207bad2795c09e6318e3`, the squash commit of pull request #13 (TASK-008) on `develop`; since 29 October 2026 `5f20137f1146be4a6f615d2ac5acb624999a596f`, the squash commit of the `ops/` pull request #14 (D-108), to which the Owner moved the branch by fast-forward before any commit of the Task |
| Executor | The implementation executor agent `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewers | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40); section 9 |
| Gates | GATE-CC1, SATISFIED (D-099); EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, its commits and pushes through the scripts without a prompt (CE-30), one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24, CE-20)

- **Read path (EXC-01).** AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT (section 19 first), TASK_PLAN sections 1 to 4 and the contract, then its "Read first": INTERACTION_CONTRACTS sections 1.1 to 1.7, 2.1 to 2.4, 3.3, 3.6 and the rows of section 9 for the Task's controls; ADR-007 sections 1, 5 and 6; ADR-009 section 2; NAVIGATION_CONTRACTS NC-13 and the UXP-006 contract; UI_STATES UXS-04, UXS-06, UXS-09 to UXS-16, UXS-21, UXS-22 and section 4; INTERACTION_INVENTORY the rows of UXP-000 (UXI-025 to UXI-038) and UXP-006; COPY_DECK sections 1.1 to 1.3, 4.1, 6.1, 6.7, 12, 14 and 15; SECURITY_MODEL SEC-CH-02, SEC-CH-05, SEC-SESS-02, SEC-SESS-05; ROUTE_CONTRACTS RT-07, RT-08, RT-10, RP-006, RD-03, RD-04, RA-02 to RA-04 and RC-50; TEST_STRATEGY TS-21 to TS-25; TECHNICAL_RULES TR-TIME-03 (the record-check wait); the TASK-008 evidence (its readings and findings F-1 and F-4); OPEN_ITEMS T-29 and T-32; STACK_AND_MODULES sections 2.1 and 3.
- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names; no stop.
- **Executor state and GitHub (EXC-20 under CE-20)**, on 28 October 2026 at 16:33: phase `BUILD`, Task TASK-009, class A, branch `task/TASK-009-command-client`, base `e54cbab`, effort `high`, agents `bk-executor-high`, `bk-reviewer-high` and `bk-reviewer-high`; the lock held by this session's token; the last merge verified `e54cbab` (pull request #13). On GitHub no branch `task/TASK-009*` and no open pull request.
- **Working tree (EXC-20, CE-70).** The main working tree on `task/TASK-008-command-platform` at `16250c0`, whose tree equals that of `e54cbab`; nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 lists; `stash@{0}` kept; remote `origin` only. The branch was created with `git switch -c task/TASK-009-command-client e54cbab`.
- **Prerequisites (EXC-21).** TASK_PLAN section 4 at `e54cbab`: TASK-008 and TASK-002 `DONE`, TASK-009 `READY`; GATE-CC1 SATISFIED (D-099).
- **The previous merge (CE-50, CE-61).** Pull request #13 checked again on GitHub and recorded in the [TASK-008 evidence](TASK-008.md) section 10 and in CURRENT_HANDOFF.
- **Tools (EXC-23).** Docker and Docker Compose answer; the images of TASK-001 by digest. By the Owner's directive, every local `docker compose` command sets `APP_PORT=18080`, the browser suite runs with one Playwright worker, and before it `docker ps` was checked for a browser-test container of the machine's other project (none). No MCP server is needed (contract).

## 2. Versions and sources checked (EXC-03)

| Fact relied on | Pinned version | Source and result |
| --- | --- | --- |
| `Arr::get()`, which the translator's `getLine()` uses, returns `$array[$key]` for the whole key before it splits the key at its dots | Laravel 13.37.0 | `vendor/laravel/framework/src/Illuminate/Collections/Arr.php` (`get`) and `Translation/Translator.php` (`getLine`) of the pinned version; proven by `LocalizationTest`, which asserts `__($key)` for every key of the dictionary, the dotted ones included |
| Inertia merges every shared prop into its response factory for the life of the process, so a key shared for one request stays for the next unless it is set again | inertiajs/inertia-laravel 3.5.1 | `HandleInertiaRequests::dropEarlierDictionary()` (TASK-003) and the failure of `SharedPropsTest` before the fix of reading 3 |
| `page.clock.install()` and `fastForward()` drive `setTimeout`, `setInterval` and `Date.now()` of the page; `route.fetch()` sends the request to the server and returns its answer without fulfilling the route; `route.continue({ postData })` replaces the body; `browser.newContext()` in a test takes the options of the configuration's `use` | Playwright 1.64.1 | Playwright documentation of `Clock`, `Route.fetch`, `Route.continue` and of the test options; used already by TASK-006 and TASK-007 (`session.spec.ts`, `contextAt`); proven by the browser suite of section 6 |
| `crypto.randomUUID()` exists in a secure context, which every signed-in page is (SEC-CH-04; `SECURE_LOCAL_ORIGIN`) | Chromium of the Playwright image; the browsers of DEVICE_VERIFICATION | WHATWG Web Crypto (`randomUUID` is `[SecureContext]`) |

No dependency was added; no licence to record.

## 3. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The command client: freeze, the envelope, the recovery epoch of the shared props, the client states, the 65-second wait with "Masih diproses" after 4 seconds, "Kirim ulang" with the same intent and key, "Cek status" with its repeats after 3, 6 and 12 seconds and then every 15 seconds as background requests; time never yields REJECTED; a check without an answer leaves the state | `resources/js/lib/commands/client.ts`, `answers.ts`, `useCommand.ts`; the shared prop `recoveryEpoch` in `App\Http\Middleware\HandleInertiaRequests` (TASK-008 finding F-1) |
| 2. The references of SEC-CH-05 in local storage per user, kept across a reload, a closed tab and a sign-out, released when resolved; every other entry of the application's storage cleared at sign-out, at the limits and on a change of user | `resources/js/lib/commands/references.ts`; `clearBrowserData()` of `resources/js/lib/browserSession.ts` |
| 3. NC-13: the block of a family by the union of the references and the user's receipts (RD-04), read when a page of the family opens, before its final action sends and with the shell refresh; the reason with its link (UXS-13); the exceptions of NC-13 (d) and print; the Owner's own requests only; a page of a blocked family opens on the request's panel | `resources/js/lib/commands/families.ts` (`blockingRequest`), `unresolved.ts`, `useCommand.ts` |
| 4. The uncertain and held panels with the family texts, the recent-authentication flow for 423 and the handling of 401 and 419 | `resources/js/lib/commands/Panels.tsx` (`CommandPanels`, `RecentAuthDialog`); `RecoveryPanel` of `States.tsx`, whose buttons Enter never activates (UXA-10) |
| 5. The sign-out warning of IC-10 | `SignOutWarning` in `resources/js/layouts/AppShell.tsx` (UXI-027, UXI-028) |
| 6. UXP-006 (RP-006) | `App\Http\Controllers\Commands\RequestsController`; `resources/js/pages/commands/Requests.tsx` (UXI-055, UXI-057, UXI-058) |
| 7. RD-03 with the number of the actor's unresolved receipts and the as-of time; the shell's refresh; the banner and the account-menu number | `App\Http\Controllers\Access\ShellDataController`, `App\Queries\Access\ShellIndicators`, `App\DTO\Access\ShellIndicatorsData`, `App\Queries\Commands\UnresolvedRequestCount`; `UnresolvedBanner`, `IndicatorsNotice` and the account menu in `AppShell.tsx` (UXI-025, UXI-029) |
| 8. The browser harness for network faults (TS-22) and a second device (TS-23), with the probe of TASK-008 | `resources/js/pages/Testing/CommandSpecimen.tsx` and its route `GET /testing/command-specimen`; the probe registered in the local environment too (`Operations::PROBE_ENVIRONMENTS`); the fixtures `POST /testing/browser/commands` and `/testing/browser/receipt` (`tests/Harness/Browser/`); `tests/Browser/support/commands.ts` and `tests/Browser/commands/` |

The copy: the keys of COPY_DECK sections 4.1, 6.1, 6.7 and 14 in `lang/id/commands.php` and `lang/id/access.php`, with exactly their names (EXC-51); the TASK-002 additions `commands.requests.*` among them. UXI-030, the onboarding banner, is TASK-035's (TRACEABILITY section 9.2), and UXI-056 "Lihat hasil" TASK-022's: neither is built (EXC-52).

## 4. Readings and technical choices

1. **A key that is both a text and the parent of other keys (OPEN_ITEMS T-29).** COPY_DECK section 14.1 names `commands.family.<family>`, a label, and `commands.family.<family>.must_not` below it. Instead of waiting for a change control that renames them, the keys are created with exactly the names of COPY_DECK (EXC-51): a child of a text is written at the top of its file as one literal dotted key (`'family.sale.must_not'`), which Laravel's translator looks up before it splits a key (section 2). `App\Support\Localization\Dictionary` accepts such a key only at the top of a file, snake_case in every segment, as a text whose parent is a text of the same file, and never twice; `scripts/checks/translation-keys.php` looks the whole path up first, as the translator does; `CopyDeckTest` reads the "`key`: text" cells of section 14. T-29 records the fact and stays open for the role descriptions of TASK-011.
2. **The reference keeps the family and the recovery epoch** besides the operation, intent, key and time of SEC-CH-05: the family, read from the operation, so that the block holds for the harness's probe too; the epoch, because RC-50 sends back the epoch the request was created under, which decides for an intent of which the server holds no receipt (TASK-008 reading 9a; D-073). Neither is a payload, a secret or a cost. A reference is one line of six space-separated fields under `bk.commands.references.<user>`, each field checked against its form when read; no JSON is parsed (ESLint's exact-number rules). OPEN_ITEMS T-33 (a).
3. **The epoch in the shared props is always set**: lowercase hexadecimal for a signed-in page, null before the bootstrap and for a visitor, because Inertia keeps a shared prop for the life of the process and a value of an earlier request would otherwise reach the next visitor; the channel allowlists name it for every kind of account.
4. **The client state follows the code** (TASK-008 reading 5, T-32 (a)): HELD for IDEMPOTENCY_CONFLICT and EPOCH_STALE; UNCERTAIN for SERVICE_BUSY, no answer, any 5xx and anything unreadable, a 409 without a readable code included; not run for 401, 419, 423 and 429, whatever the body's outcome; REJECTED for every other code, INTENT_ABANDONED with UXS-16. The status is used only when no code can be read.
5. **The reference is kept before the request leaves** and released on COMMITTED, REJECTED, NOT_COMMITTED and when nothing ran (401, 419, 423, 429), so that a closed tab mid-request leaves it; after 423 and 429 the frozen request stays in the page and is sent again with the same intent and key, which writes the reference again.
6. **The block is read three times**: when a page of the family opens; at the final action, before the request leaves — the action is then busy and the request is not sent if a request of the family is now listed; and with the shell's refresh. A failed read leaves the block to what is known, never blocks by itself. A page of a blocked family opens on that request's panel (NC-13 (b)) with "Cek status" only when the payload is not in this page — after a reload or on another device — because "Kirim ulang" needs the frozen payload, which is never stored.
7. **Print, reprint and export** are exempt from NC-13 and repeated, never resolved: RD-03 does not count their receipts and the client does not list them, so the banner and UXP-006 agree (OPEN_ITEMS T-33 (b)).
8. **The shell's number** is RD-03's count and each reference of this browser that no receipt of RD-04's first page stands for; RD-04 is read with the shell's refresh only when RD-03 counts a receipt. It is null, never zero, while the server's count is unknown; a failed read keeps the last count with its time (UXS-21) or, without an earlier read, says the area could not be loaded (UXS-22). OPEN_ITEMS T-33 (c).
9. **UXP-006's way back** leads to the role's home, "Kembali ke halaman awal" (NC-12): the page takes no parameter (RT-08), so the opener is not known to it (T-33 (d)). The banner is not shown on UXP-006 itself, which it would only link to.
10. **The sign-out warning** opens from the account menu's "Keluar" (UXI-026) while the user has an unresolved request; the sign-in-ending dialog's "Keluar" (UXI-035) signs out at once, as it did, the references kept either way (T-33 (e)).
11. **The probe's family is the Owner's review note**, a family with a label and a panel text whose only command no Task has built yet, so the client's panels, its block and UXP-006 show the probe with real words; the probe and its tables exist in the local environment too, created once on the local database by the fixture, for the browser suite. `RecoveryPanel`'s buttons take `enterActivates={false}` (UXA-10, UXI-036, UXI-037).
12. **The status check of an Owner's row** (UXI-058) sends the page's epoch: the server decides from its own receipts (TASK-008 reading 9a). A row that concludes disappears and the page says the result; IN_PROGRESS repeats the check as the panel does.
13. **The browser fixtures resolve a person's earlier requests through RC-50's Action as the Owner**, which writes a fence or settles the receipt, so that every browser test starts without an unresolved request; the receipt fixture writes through `RequestReceipts::record()` (EXC-45).

## 5. Findings

| ID | Finding | Disposition |
| --- | --- | --- |
| F-1 | The command specimen was first registered without a capability gate and was refused at run time by `RequireDeclaredCapability` (SEC-AZ-01) | Fixed before the review: the route has the middleware of every signed-in page with `capability:CAP-01`; `BrowserFixturesTest::test_the_command_specimen_opens_for_a_signed_in_user…` |
| F-2 | UXI-056 "Lihat hasil" belongs to TASK-022 (TRACEABILITY section 9.2); until then a request resolved as COMMITTED on UXP-006 says "Sudah tersimpan." and leaves the list | For TASK-022 and the Tasks of section 9.2's "Extended by" |
| F-3 | INTERACTION_CONTRACTS section 1.5 asks a page to read RA-04 when its review opens; `useCommand` reads it at the final action of a command declared `recentAuth`, before the request leaves; no command of this Task needs it, so the path is proven only by the 423 flow | For the first command that needs recent authentication (TASK-011's user management) |
| F-4 | Each command page uses `useCommand` with its operation and family and `CommandPanels`, or its own panel with the same parts for a critical family (ADR-007 section 1) | For every command Task (contract section 12) |
| F-5 | The full browser run of 28 October failed `shell/session.spec.ts` "an answer for another user … (review finding R-28)": its page showed the shell's UXS-21 notice "Data belum diperbarui" with Agus's menu. A read of RD-03 after Agus's sign-in session ended on the server had answered 401, and the shell took it for a failed read (UXS-21) instead of the ended sign-in session (UXS-09, INTERACTION_CONTRACTS section 1.3); the form's request then did not answer within the test's 15 seconds on the loaded machine. The test passed twice when run again alone and with its file, at 1.5 minutes, so the timing did not reproduce; the wrong state did not need it | Fixed on resume: `indicatorsOf()` in `resources/js/lib/commands/unresolved.ts` reads 401 as `ended`, and the shell then leaves as for any ended sign-in session (`leaveOnce`, the sign-in page); regression tests `commandClient.test.ts` "reads RD-03's answer: 401 ends the sign-in session…" and `session.spec.ts` "a read of the shell's indicators that finds the sign-in session ended loads the sign-in page…" |
| F-6 | Rerunning the affected specs after F-5's fix failed `session.spec.ts` "after a sign-out, the next user sees nothing of the first…" and `shell.spec.ts` "a signed-in ops at 320x640…": a read of RD-03 still in flight when the sign-out ended the session was answered 401, which the browser logs as a console error that those tests refuse. The same race existed before F-5's fix (the earlier full run did not meet it) | Fixed: `stopIndicators()` in `unresolved.ts` cancels the read in flight and starts none once the page leaves the user's sign-in session — a sign-out, its end, another tab's sign-out or user, a change of user — called from each such path of `AppShell.tsx`; `requestJson()` takes the caller's `signal`. The two failing tests are its regression tests |
| F-7 | The same rerun failed `session.spec.ts` "a sign-out hides every tab at once…" by its time limit: Agus had an unresolved request, so "Keluar" opened the sign-out warning (IC-10) instead of signing out. The three width checks of `requests.spec.ts`, added after the full run, left Agus's PENDING receipt of `receiving.confirm`, and the fixtures of the other folders leave receipts alone | Fixed in the test: the width checks resolve Agus's receipts through the commands fixture in a `finally` block, so a later file starts without them even when a check fails |

## 6. Tests

| Test | Suite | Proves |
| --- | --- | --- |
| `tests/Browser/commands/client.spec.ts` | L5 (TS-22) | AC-009-01, AC-009-02, AC-009-07: a response dropped after the commit resolved by "Cek status" — the recovery case of the L5 critical paths (EXC-34); the 65-second wait with "Masih diproses" on a fake clock and no refusal by time; 503 and "Kirim ulang" with the same intent, key, epoch and payload; a second press, Space or Enter while SUBMITTING and Enter on the final action send nothing; 409 IDEMPOTENCY_CONFLICT and EPOCH_STALE held with "Cek status" only, a check without an answer leaving them held; IN_PROGRESS repeated as background requests until NOT_COMMITTED; 423, the recent-authentication dialog with "Batal", a refused password and the same frozen request after RA-03; 419 and 429; the accessibility checks of the panels and the dialog |
| `tests/Browser/commands/devices.spec.ts` | L5 (TS-23) | AC-009-04: a request that reached the server blocks its family in a second context of the same user and in the first after its site data are cleared; the page opens on the request's panel with "Cek status" only and the reason with its link; the shell's banner and number; the Owner never blocked by another user's request; the resolution on UXP-006 ends the block in both contexts |
| `tests/Browser/commands/requests.spec.ts` | L5 | AC-009-03, AC-009-05, AC-009-06, AC-009-07: UXP-006 for a cashier (a receipt of another device, the banner, the menu number, the sign-out warning and "Tetap keluar", the check that fences it, the empty state, the way back) and for the Owner (another user's request with the name, his check settling it for that user); a reference that survives a reload and a sign-out, is never shown to another user in the same browser, keeps no payload and is released when resolved; the accessibility checks |
| `tests/Feature/Access/ShellDataTest.php` | Postgres (L4) | RD-03: exactly the user's own PENDING and HELD receipts, without print requests and another user's, the as-of time, for every role and the combined account on the allowlisted channel; no parameter; a background read leaves the idle time and writes nothing |
| `tests/Feature/Commands/RequestsPageTest.php` | Postgres (L4) | RP-006 for every role and the combined account: no data of its own, encrypted history, `no-store`, no parameter |
| `tests/Feature/Access/BrowserFixturesTest.php` (extended) | Postgres | The command fixtures (the probe's tables, the epoch, earlier requests fenced through RC-50's Action), the receipt fixture, their refusals outside the local and test databases, and the command specimen behind the gate of every signed-in page |
| `tests/Feature/Access/SharedPropsTest.php` (extended), `AccountPagesTest`, `SignInTest`, `OneTimeLinkTest` | Postgres | The recovery epoch in the shared props: null before the bootstrap, the current one after it, null for a visitor also after a signed-in request of the same process |
| `RouteInventoryTest`, `CapabilityMatrixTest` (extended) | Postgres | RD-03 and RP-006 with their gates for every kind of account, no GET that writes; the test-only routes of the harness |
| `tests/Unit/Commands/commandClient.test.ts` | Vitest (L1) | Every answer class to its client state from the code; the status check's answers; the wait and the repeats; the envelope; the references per user, their form and their release; NC-13 with print and NC-13 (d); the union and the number |
| `tests/Unit/Commands/ClientFamiliesTest.php`, `OperationsTest` (extended) | Unit | The client's families equal the server's; every family's label and panel text in `lang/id`; the probe only in the local and testing environments; the exempt family |
| `tests/Unit/Localization/CopyDeckTest.php`, `LocalizationTest.php` (extended) | Unit | Every new key's text equal to COPY_DECK, the "`key`: text" cells of section 14 included; the dotted children of texts resolved by the translator and refused when malformed (T-29) |

The standing obligations of section 10: the two routes are in the route inventory with their gate, each role and the combined account by direct request, no GET that writes, `no-store`, the DTO allowlists per role (`data.shell`, `commands/Requests`, the shared `recoveryEpoch`); RD-03 is a read with no parameter, never zero after a failed read (UXS-21, UXS-22); the page UXP-006 has its controls (EXC-52), states, copy keys, the keyboard path of its buttons (Enter never activates "Cek status" or "Kirim ulang") and the automated accessibility checks. No table, command, procedure or document is added: object scope of a guessed intent answering NOT_FOUND is RC-50's (TASK-008). The widths and 200 % zoom of UXP-006 are covered by the design system's components it uses; its key screen does not exist (KEY_SCREENS).

## 7. Where BUILD stands at the Owner's shutdown (28 October 2026, about 19:40)

The Task was stopped by the Owner's directive of a safe shutdown (`OWNER_SHUTDOWN`), in the phase `BUILD`, before the review; nothing is committed or pushed. Kept: every change in the main working tree on `task/TASK-009-command-client` (base `e54cbab`), uncommitted; the logs of the local runs in the session's scratchpad.

The command contract ran locally once, as `.github/workflows/ci.yml` runs it, from 18:05 to 19:36, `APP_PORT=18080`, one Playwright worker:

| Command | Result |
| --- | --- |
| `docker compose up -d --build --wait` | PASS |
| `composer validate --strict --no-check-all` | PASS |
| `composer lint` | FAIL in that run: the secret scan flagged the fixed UUID named `KEY` of `tests/Unit/Commands/commandClient.test.ts` (generic-api-key); fixed by making the intent and key at run time (EXC-63); `composer lint` then PASS at 18:35 (Pint 304 files, 235 keys used, types current with 289 keys, no leak, no advisory, Network 2 tests) |
| `composer analyse` | PASS |
| `composer test:unit` | PASS, 536 tests |
| `composer test:postgres` | PASS, 341 tests |
| `composer test:race` | PASS, 19 tests |
| `npm ci`, `npm run lint`, `npm run typecheck`, `npm run test:unit` (513 tests), `npm run build` | PASS |
| `php artisan migrate --database=pgsql_migration --force` | PASS, nothing to migrate |
| `npm run test:e2e -- --workers=1` | FAIL: 200 passed, 1 failed — `tests/Browser/shell/session.spec.ts:321` "an answer for another user clears the tab's history key and storage and loads the page again (review finding R-28)": the page stayed on Agus's account with the shell's UXS-21 notice instead of loading Rina's; a defect of this Task's shell refresh to examine and fix first on resume. The twelve tests of `tests/Browser/commands/` passed |

Left on resume: the fix of that failure and the affected suites again; the three width checks added to `tests/Browser/commands/requests.spec.ts` after that run (`NOT_RUN`); the self-review, the class-A review by `bk-reviewer-high` (CE-40); the acceptance criteria and the Definition of Done of this file; the guard's preview again (it passed once, no candidate); the record of CE-50 — TASK_PLAN, the TASK-008 merge and CURRENT_HANDOFF are already written on the tree — the commit, the push and the pull request.

## 8. Local checks after the resume (CE-44), 28 October 2026, 21:50 to 23:05

The resume (`OWNER_RESUME`, the Owner's note "Owner resume after OWNER_SHUTDOWN of TASK-009") fixed findings F-5 to F-7, all in the frontend and the browser tests; no PHP file changed after the full run of section 7 (checked by modification time), so its PHP results stand: `composer validate`, `composer analyse`, `composer test:unit` (536), `composer test:postgres` (341) and `composer test:race` (19). Run again, because the change touches them (CE-44 (b) to (d)):

| Check | Why | Result |
| --- | --- | --- |
| `npm run typecheck`, `npm run lint` (ESLint, npm audit, Network 1 test) | Static checks of the changed TypeScript | PASS |
| `npm run test:unit` | `commandClient.test.ts` gained the RD-03 answer test | PASS, 514 tests |
| `npm run build` | Frontend changed | PASS |
| `composer lint` | The secret scan and the translation checks read the changed files | PASS: Pint 304 files, 235 keys used, types current with 289 keys, no leak, no advisory, Network 2 tests |
| `npm run test:e2e -- tests/Browser/shell tests/Browser/commands --workers=1` | The shell's refresh and every command spec; R-28 and the three width checks | 63 passed, 2 failed (F-6), 6 not run |
| `… tests/Browser/shell tests/Browser/commands/requests.spec.ts` after F-6's fix | The specs of the fix | 22 passed, 1 failed (F-7), 4 not run; `shell.spec.ts` 10 of 10 |
| `… tests/Browser/commands/requests.spec.ts tests/Browser/shell/session.spec.ts` after F-7's fix | The two files with a failure or a test not run | PASS, 17 tests: R-28, the new 401 test and the three width checks among them |
| `… tests/Browser/access tests/Browser/security --workers=1` | They sign in and out through the shell's changed leaving paths | PASS, 42 tests |

`tests/Browser/design-system` and `smoke.spec.ts` sign no one in, so the signed-in shell that changed does not run there; they passed in section 7's run and were not run again. The full command contract runs in CI on the pull request.
## 9. Review (CE-40)

One class-A review, never repeated (D-108), and one targeted verification of its two MUST-FIX.

| Round | Agent and effort | Package | Base, tree | Time, duration | Verdict |
| --- | --- | --- | --- | --- | --- |
| 1, review | `bk-reviewer-high`, High | `.git/bantukasir-executor/review/TASK-009/r1` | `e54cbab`, `f6ecff3` | 28 October 2026, from 23:04 | FAIL, two MUST-FIX; `--check` UNCHANGED |
| 2, verification | `bk-reviewer-high`, High (CE-11) | `.git/bantukasir-executor/review/TASK-009/r2`, `--previous` r1 | `5f20137`, `96a9a25` (HEAD `eac393f`) | 29 October 2026, 23:00:44, 2 min 38 s | PASS: R-01 and R-02 FIXED, no new MUST-FIX; `--check` UNCHANGED |

The base of round 2 is `5f20137`, the squash commit of the `ops/` pull request #14 (D-108), to which the Owner moved the branch by fast-forward from `e54cbab` with nothing else changed (his note of the resume). Its `fix.patch` holds the 15 files of D-108 that came with `develop`; they were outside the verification's scope and not judged.

Between the rounds the Task stopped three times with `LOCAL_DOCKER_DOWN` in `REVIEW` (28 October 2026 about 23:40, resume 1 at 23:48, resume 2 at 00:03 on 29 October): the local Docker engine answered HTTP 500, no check ran and nothing changed but this file. Resume 3, at 22:23 on 29 October under D-108, ran the checks of section 10 without a local container.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | A repeat of an UNCERTAIN request answered 401, 419, 423 or 429 released the reference of an intent that may have committed; 423 then "Batal" dropped to an idle draft; 429 showed no "Cek status" | FIXED, verified: `useCommand.ts` releases on a not-run answer only for a first attempt (`reached`); "Batal" returns to UXS-14; 429 keeps UXS-14 with the UXS-12 notice (`Panels.tsx`). Regression tests in `client.spec.ts`, "a repeat of an uncertain request that runs nothing keeps its reference…" and "419 on a repeat of an uncertain request keeps its reference…", which fail without the fix (verification) and pass in run 40905063814 |
| R-02 | MUST-FIX | No browser test of the shell's refresh or of UXS-21 and UXS-22 | FIXED, verified: `requests.spec.ts` "the shell reads RD-03 as background requests on open, every 30 seconds and on focus…". Its first CI run (40902074910) failed at the focus step, a defect of the test (F-8) |
| R-03 | SHOULD-FIX | REJECTED answers and 401 on a first command not exercised in the browser | Open: OPEN_ITEMS T-34 (a) |
| R-04 | SHOULD-FIX | A pending status-check repeat is not cleared by "Kirim ulang" | Open: OPEN_ITEMS T-34 (b) |
| R-05 | SHOULD-FIX | UXP-006 at 3 of the 8 widths, no 200 % zoom, no keyboard pass of "Cek status" | Open: OPEN_ITEMS T-34 (c) |
| R-06 | SHOULD-FIX | `tests/Browser/commands` not run whole on the final tree | Fixed: the whole browser suite, 208 tests, in run 40905063814 |
| R-07 | SHOULD-FIX | No acceptance-criteria, Definition of Done or review section | Fixed: sections 9, 11 and 12 |
| R-08 | SHOULD-FIX | CURRENT_HANDOFF's `EXECUTION:` and `TESTS:` lines out of date | Fixed in CURRENT_HANDOFF section 8 |
| R-11 | NOTE | Leaving paths of `useCommand` and UXP-006 did not stop the shell's reads | Fixed: `clearBrowserData()`, which every leaving path calls, calls `stopIndicators()`; the verification found no regression |
| R-13 | NOTE | RD-04's 401 in the refresh read as a failed read | Recorded: OPEN_ITEMS T-34 (d) |
| R-09, R-10, R-12, R-14 to R-17 | NOTE | An unmounted page drops a late answer; the client's idle deadline not fed by fetch commands; `location` with a backslash; the probe in `local`; the simulation of cleared site data; RD-04's first page only; no epoch before the bootstrap | Recorded, no change: the probe in `local` is reading 11 and the epoch before the bootstrap reading 3 of section 4; the others are limits of the harness or wait for the first command pages (TASK-010, TASK-011) |
| R-18 | NOTE (round 2) | The R-02 test does not cover the read after a command | Recorded: OPEN_ITEMS T-34 (e) |
| R-19 | NOTE (round 2) | The 30-second step counts any new read after the open | Recorded, no change: nothing else reads in that window |
| R-20 | NOTE (round 2) | Section 9 did not yet cite the checkpoint runs | Fixed: this section and section 10 |
| R-21 | NOTE (round 2) | After a 429 on a repeat, "Kirim ulang" is available before the wait has passed, as in the existing throttled phase | Recorded: OPEN_ITEMS T-34 (b) |

The self-review of the fixes found no defect (every leaving path of `browserSession.ts` calls `clearBrowserData()`, no import cycle). One finding of this resume:

| ID | Finding | Disposition |
| --- | --- | --- |
| F-8 | Checkpoint run 40902074910 failed the R-02 test at its focus step: `refreshIndicators()` returns the read in flight (RD-03, then RD-04 while a receipt is counted), so a focus during the interval's read sends nothing | Fixed in the test (`readOnFocus`, for the focus and the failed-read steps); no production code changed; run 40905063814 green |

## 10. Checks (CE-44 to CE-46, D-108)

Under D-108 no container of BantuKasir runs on the Owner's machine: the heavy checks ran in checkpoint runs of CI on the Task branch, the rest natively. Times are Asia/Jakarta, 29 October 2026; the checks of sections 7 and 8 ran on earlier trees and are superseded by these.

| Check | Where | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `tsc --noEmit` | Local, tree of `8b706fa` | 22:24:37 | 11 s | PASS |
| ESLint | Local, tree of `8b706fa` | 22:24:51 | 52 s | PASS |
| Vitest | Local | 22:25:48 | 3 s | NOT_RUN: `node_modules` comes from the Linux container and has no Windows binding of rolldown; run in CI |
| Guard preview (CE-31) | Local, before the checkpoint | 22:26:10 | 5 s | PASS, no candidate |
| Guard | Local, `8b706fa` | 22:26:42 | 2 min 22 s | PASS: Gitleaks 8.30.1 native, no leak, no candidate |
| Checkpoint 1, the command contract | CI run 40902074910, head `8b706fa` | 22:29:26 | 13 min 15 s | FAIL: every step PASS except `npm run test:e2e`, 207 passed, 1 failed (F-8) |
| `tsc --noEmit`, ESLint of the changed test | Local, after F-8's fix | 22:44:39 | 12 s | PASS |
| Guard | Local, `eac393f` | 22:45:08 | 1 min 46 s | PASS: no leak, no candidate |
| Checkpoint 2, the command contract | CI run 40905063814, head `eac393f` | 22:47:14 | 12 min 46 s | PASS, every step below |
| — start of the local environment | run 40905063814 | 22:47:17 | 43 s | PASS |
| — `composer validate`, `composer lint`, `composer analyse` | run 40905063814 | 22:48:00 | 27 s | PASS |
| — `composer test:unit` | run 40905063814 | 22:48:27 | 13 s | PASS, 536 tests |
| — `composer test:postgres` | run 40905063814 | 22:48:40 | 4 min 48 s | PASS, 341 tests |
| — `composer test:race` | run 40905063814 | 22:53:28 | 35 s | PASS, 19 tests |
| — `npm ci`, `npm run lint`, `npm run typecheck` | run 40905063814 | 22:54:03 | 19 s | PASS |
| — `npm run test:unit` | run 40905063814 | 22:54:22 | 6 s | PASS, 514 tests |
| — `npm run build` | run 40905063814 | 22:54:28 | 6 s | PASS |
| — `npm run test:e2e` | run 40905063814 | 22:54:34 | 5 min 24 s | PASS, 208 tests: `tests/Browser/commands` whole, the R-01 and R-02 regression tests, the recovery case of EXC-34 |

Run 40905063814 stays valid for the pull request's code (CE-45): the record commit after it changes documents only. The full command contract runs again in CI on the pull request's final head (CE-41).

## 11. Acceptance criteria

| Criterion | Result | Proven by (run 40905063814) |
| --- | --- | --- |
| AC-009-01 | PASS | `client.spec.ts` with the faults of TS-22 and the R-01 tests; `commandClient.test.ts` |
| AC-009-02 | PASS | `client.spec.ts`: a second press, Space or Enter while SUBMITTING; Enter on the final action |
| AC-009-03 | PASS | `requests.spec.ts`: reload, sign-out, another user, release when resolved; `commandClient.test.ts` |
| AC-009-04 | PASS | `devices.spec.ts` (TS-23): a second context, cleared site data, NC-13 (d), print, the Owner never blocked by another user |
| AC-009-05 | PASS | `requests.spec.ts` for a cashier and for the Owner; `RequestsPageTest`, `ShellDataTest` |
| AC-009-06 | PASS | `requests.spec.ts`: the sign-out warning and the R-02 test (RD-03 on open, every 30 seconds, on focus, the background marker, UXS-21, UXS-22); `ShellDataTest` |
| AC-009-07 | PASS | `CopyDeckTest`, `LocalizationTest`, the translation-key check of `composer lint`; axe on the panels, the dialog and UXP-006 |

## 12. Definition of Done (EXECUTION_CONTEXT section 13)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | AC-009-01 to AC-009-07; WF-07 and UXT-08 |
| UI and UX | PASS | The controls of contract section 4.2 with their routes, the states, the keys of COPY_DECK, axe; UXP-006 tested at three widths, the other widths and 200 % zoom left by R-05 to OPEN_ITEMS T-34 (c) |
| Backend | PASS | RD-03 and RP-006 with their gates, no parameter, `no-store`, the DTO allowlists per role (`ShellDataTest`, `RequestsPageTest`, the route inventory and the capability matrix) |
| Database | N/A | No migration |
| Security | PASS | Deny by default on both routes for every role and the combined account; references without a payload; storage cleared at sign-out and on a change of user; the guard and the static checks |
| Data integrity | N/A | Writes no business fact |
| Performance | PASS | RD-03 one bounded count; RD-04 its first page only; the budgets are measured at TASK-048 |
| Operations | N/A | No backup, runbook or status impact |
| Verification | PASS | The command contract green in checkpoint run 40905063814; the self-review; the class-A review and its targeted verification (section 9); the pull request's own CI follows (CE-41) |

## 13. Guard preview of the record (CE-31, CE-50)

`bash scripts/executor/guard.sh --preview` on the working tree with this record, 29 October 2026 at 23:09:07, 2 s: `GUARD PREVIEW PASS`, no candidate of the privacy and client-data check, so no judgment to record. The checkpoint commits `8b706fa` and `eac393f` each passed `guard.sh` before their push (section 10), with no candidate.
