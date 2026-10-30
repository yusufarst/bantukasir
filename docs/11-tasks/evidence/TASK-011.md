# TASK-011 evidence — User management and Owner procedures

| | |
| --- | --- |
| Task | [TASK-011](../tasks/TASK-011-user-management.md), size L, review class A, lane P |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (EXECUTION_CONTEXT section 19, CE-01): the selection of CE-10 at `origin/develop` `c6702d2`, the lowest-numbered `READY` Task with its dependencies `DONE` and no gate; mode `start`, delegated by the tick to `bk-executor-high` on 30 October 2026 at 20:29, after it verified the merge of TASK-010; mode `resume` from the phase `REVIEW` on 31 October 2026 at 00:15, after the Owner's answer D-111 to the stop `CHECKPOINT_LIMIT`; mode `fix` on 31 October 2026 at 01:15, for the automated reviewer's finding on pull request #18 (section 14) |
| Branch | `task/TASK-011-user-management`, created from `origin/develop` (CE-02) |
| Base commit | `c6702d2c75e106718cd902bad4501875b040fae2`, the squash commit of pull request #17 (TASK-010) on `develop` |
| Executor | The implementation executor agent `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewers | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40); section 8 |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, its commits and pushes through the scripts without a prompt (CE-30), one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Preflight.** The state showed the phase `BUILD` for TASK-011, base `c6702d2` equal to `origin/develop`, no open pull request, no branch of the Task, the lock held by this session. The working tree held only `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for, and `stash@{0}`, kept. Dependencies TASK-009 and TASK-010 `DONE`.
- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names; no stop.
- **Tools (D-109, CE-47).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. The native Node toolchain has no command links in `node_modules/.bin`, so `npm run typecheck` could not start (`tsc` not found); its run of record is CI, with lint, the unit tests, the build and the browser suite. PHP, PostgreSQL and the race suite run in checkpoint runs of CI only (D-108).
- **The previous merge (CE-50).** Pull request #17 (TASK-010) is recorded in the [TASK-010 evidence](TASK-010.md) section 14 and in CURRENT_HANDOFF.

## 2. Versions and sources checked (EXC-03)

| Fact relied on | Pinned version | Source and result |
| --- | --- | --- |
| The token repository keys its row by the account's email, so a link is revoked before an email changes | Laravel 13.37.0 | `DatabaseTokenRepository` (`delete`, `deleteExisting`, `recentlyCreatedToken`); proven by `UserManagementTest` (the old token matches nothing after an email change) |
| `FOR SHARE` conflicts with `FOR NO KEY UPDATE`, so the actor guard's share lock and the target's update lock order a deactivation and a running command | PostgreSQL 18.6 | The PostgreSQL manual, section 13.3.2; proven by `UserManagementRaceTest` (RACE-04, both orders) |
| The QR code: byte mode, level M, versions 1 to 10, the masks, the penalty rules, the format and version bits | ISO/IEC 18004 as the published descriptions give it | Implemented without a package; `tests/Unit/Access/qrCode.test.ts` checks the error correction against the published worked example of "HELLO WORLD" 1-M and reads the whole symbol back |

No Composer or npm package was added (finding F-1).

## 3. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1, RC-35, RC-36, RC-38 to RC-42 | `app/Actions/Access/` (`UserManagementAction` and one Action per operation), `app/Http/Requests/Access/`, `app/Http/Controllers/Access/`, `App\Domain\Access\StaffRoles`, `Accounts::change()`, `OneTimeLinks::activeUntil()` |
| 2, RC-37 | `UpdateOwnerDetails`, with the hook `CommandAction::ownAccountIsTarget()` for the single update lock of TR-LOCK-05 |
| 3, OP-02 and OP-03 | `RecoverOwner`, `ReplaceOwner`, `OwnerRecover`, `OwnerReplace`, on the framework of TASK-010, which now also writes `SESSION_REVOKED` for its run |
| 4, the pages | `resources/js/pages/access/` `OwnerDetails`, `Users`, `UserCreate`, `User`, `UserEdit`, `LinkHandover`; `lib/access/users.ts`, `lib/access/RoleChoices.tsx`, `lib/access/qrCode.ts`; UXI-046 on UXP-003 and UXI-326 on UXP-601; `App\Queries\Access\UserAccounts` with its DTOs |
| 5, the fixtures | `TokoContoh`: the Owner from OP-01's Action, staff from RC-35's Action, `staff()` and `tokenFor()` for a test that changes an account |

The platform additions, which later commands reuse: a link in the answer that commits it only (`CommandAction::handOver()`, `CommandOutcome::$link`, `CommittedData`), the location of that answer (`presentNow()`), the security log entry of ended sessions after the transaction (`sessionsEnded()`, `CommandPipeline`), and `Retry-After` on a THROTTLED refusal of a command's own check (`ErrorAnswer`).

## 4. Readings and technical choices

| ID | Reading or choice | Reason |
| --- | --- | --- |
| R-1 | `roles` of RC-35 and RC-36 is a list of `cashier` and `operationsAdmin`, each once | INTERACTION_CONTRACTS names the two values without a shape |
| R-2 | A target that is the actor's own account is refused FORBIDDEN before any lock, and any SUPER_ADMIN target but RC-40's is FORBIDDEN after the version check; both are recorded as AUTHORIZATION_REFUSED | CAP-33 is reachable through no route; TR-LOCK-05 forbids an update lock after the guard's share lock on one row |
| R-3 | A state the command does not allow (an anonymized target, RC-38 on an inactive account, RC-39 on one not deactivated, RC-40 on one not deactivated, RC-41 on one not active, a request that changes nothing) is VALIDATION_FAILED without a field | TECHNICAL_RULES section 4 has no other code for it; the version check comes first, so a page that is merely behind is STALE_VERSION |
| R-4 | RC-41's kind that does not fit the account (FIRST_PASSWORD with a password set, RESET without one) is STALE_VERSION | UXI-342 and UXI-343 tie the kind to the password; a password set meanwhile does not move the version |
| R-5 | The fresh answer of RC-35 and RC-41 has `location` RP-606 and `link`; a repeat and RC-50 have `location` RP-604 and no `link`, and the client says `commands.resolution.committed_link_hidden` by the operation (`committedText`) | RC-35 names RP-606 for the answer, UI_STATES section 4 and NAVIGATION_CONTRACTS UXP-604 name UXP-604 after a resolution; the key is the client's, since RT-07 and section 1.2 give a committed answer no message |
| R-6 | RP-602 takes no paging parameter (ROUTE_CONTRACTS section 2), so it is bounded at 500 rows ordered by name | "Bounded queries" of contract section 9 |
| R-7 | The Owner's account is one of RP-602's rows; `access.users.empty` shows when no account but the Owner exists | "Every account" of RP-602, and the copy's "Belum ada akun staf." |
| R-8 | RP-604 accepts `from_page` and ignores it until TASK-042 delivers UXP-612 | EXC-52; TRACEABILITY extends UXI-349 by TASK-042 |
| R-9 | The role's descriptions are literal dotted keys | OPEN_ITEMS T-29, now resolved; UXP-003 shows the Owner's |
| R-10 | The fixtures revoke the first link of OP-01 after it, so that an account's only link is the one its test issues | The fixtures hand over no link; every test of links counts rows of `password_reset_tokens` |
| R-11 | A reason of RC-40 is one line, without a control character | TR-VAL-05 allows a line break only in multi-line notes, and its field is one line |
| R-12 | An email under the reserved `users.invalid` domain is refused in RC-35, RC-36, RC-37, OP-01 and OP-03 | It would collide later with the placeholder of an anonymization (SEC-ACC-09) |
| R-13 | RC-41 requires both CAP-25 and CAP-26, not the capability of its `kind`: an Action's capabilities are read before the payload | The Owner holds both, the only holder of either (SECURITY_MODEL section 6) |
| R-14 | UXP-602 keeps its filters for its child pages' way back in session storage, a harmless interface preference that a sign-out clears (SEC-CH-05); the filters are also in its address, which is in the encrypted history (NC-10) | NC-01: the way back never depends on browser history alone, and the pages between are full page loads |
| R-15 | On a phone every action of UXP-604 is in one menu, and otherwise "Cabut tautan" is the overflow. An action that a request blocks stays a button, because a menu item cannot show why it waits (UXS-13). "Ubah pengguna" is a link to a page, not an action of this page, and stays in the header at every width (UXI-341) | NAVIGATION_CONTRACTS UXP-604; INTERACTION_INVENTORY UXI-344 |

## 5. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | The QR code is the application's own encoder, about 430 lines, because the executor cannot add a package under CE-47. It is proven by reading the symbol back in a unit test and by the standard's version bits, not by a physical scanner | OPEN_ITEMS T-35, for TASK-046 |
| F-2 | The bucket `link_issue` (TASK-007) counts every request of RC-41 for an account, also one refused STALE_VERSION or 423, so after such a refusal the next attempt may wait up to 60 seconds (UXS-12). The rule of RT-09 is met; the wait is a small inconvenience | Recorded; no change |
| F-3 | OPEN_ITEMS T-34 (d), a 401 of RD-04 during the shell's refresh, stays open: it needs the sign-out path in `lib/commands/unresolved.ts`, which `browserSession.ts` imports. Moved to TASK-029 with (b) | TASK-029 |
| F-4 | UXP-604 shows no way back to UXP-612 and no OPEN cash session yet; the contract leaves them to TASK-042 and TASK-030 | TASK-030, TASK-042 |
| F-5 | The first four checkpoint runs failed on what the Owner's machine cannot run locally: PHPStan, the unit list of operations, existing tests that assumed a database without the Owner's account, and ESLint (section 9). Without `tsc`, `eslint` and PHP natively, a Task that is large and new meets every static check first in CI, which consumes the checkpoint runs of CE-46 | Evidence of CE-46; the Owner may want the native toolchain, by an `ops/` change |
| F-6 | The targeted check found four SHOULD-FIX in what the fixes of the review brought (R-20, R-21, R-22, R-24, section 8.1), not fixed before the pull request: D-111 keeps the one remaining checkpoint run for a new test that goes red, and without a further run no changed code may reach the pull request (CE-44). R-20 was then fixed in fix cycle 1 (section 14) | OPEN_ITEMS T-36 |

## 6. Tests

| Contract section 7 | Where |
| --- | --- |
| L3 RACE-04, RACE-16 (account side), RACE-20, RACE-21, RACE-30; RACE-01 and RACE-02 of every operation | `tests/Concurrency/UserManagementRaceTest.php` |
| L4 capability matrix rows; link channel inspection | `CapabilityMatrixTest`, `RouteInventoryTest`, `UserManagementTest`, `UserPagesTest`, `Allowlists` |
| L2 procedure tests of OP-02 and OP-03 | `tests/Feature/Access/OwnerProceduresTest.php` |
| L5 the six pages | `tests/Browser/access/users.spec.ts`: three widths with axe, contrast, targets, the keyboard's Enter on a final action, UXS-05, UXS-13, UXS-49, a 401, and T-34 (f) |
| Failure injection after every write step | `UserManagementTest` for the eight operations, `OwnerProceduresTest` for the two procedures |
| QR code | `tests/Unit/Access/qrCode.test.ts` |

**Race outcomes** (all PASS in run 41077122452, and again in run 41098030065 on `313ecba`):

| Race | First holds the lock | Second | Outcome |
| --- | --- | --- | --- |
| RACE-04 | A running command (share lock) | Deactivation or role change | Both commit, in that order |
| RACE-04 | Deactivation or role change | A running command | The change commits; the command FORBIDDEN, no fact |
| RACE-16 | Anonymization, or reactivation | The other | The first commits; the other STALE_VERSION |
| RACE-20 | Revocation, re-issue or deactivation | Consumption | The writer commits; `LINK_INVALID` |
| RACE-20 | Consumption | Revocation | CONSUMED; STALE_VERSION |
| RACE-20 | Consumption | Re-issue or deactivation | CONSUMED; the writer commits; the new password in force |
| RACE-21 | A command of the Owner | Replacement | Both commit; one Owner active |
| RACE-21 | Replacement | A command of the outgoing Owner | Replaced; the command FORBIDDEN, no fact; one Owner active |
| RACE-30 | Replacement | The same replacement | One runs; the other `NOT_THE_ACTIVE_OWNER`; one Owner active; a former Owner never reactivated (`UserManagementTest`) |

**Channel inspection of the link** (AC-011-05): `UserPagesTest::test_the_link_appears_only_in_the_issuing_json_body` — in the body of RC-35 with `no-store`; not in RP-602, RP-604, RP-605, RP-606, RP-003, the session, `audit_events.change_data`, `command_results.result_detail` or `sessions.payload`, and not stored in plain text. `users.spec.ts`: not in the address, `history.state`, local or session storage or the page's data; a reload, Back and a new visit show UXS-49. `OwnerProceduresTest`: on the terminal only, not in the audit or the security log. RC-41 has the same inspection in `UserPagesTest::test_the_link_of_rc_41_appears_only_in_its_issuing_body_never_in_a_repeat_a_status_check_or_a_log`: its fresh body, a repeat, a status check, the pages, the session, `command_results`, `audit_events` and the log. The log is inspected by a listener on every message of the test, with this limit: no test shows that the listener caught anything, and the assertion on the injected failure could not fail (R-22, OPEN_ITEMS T-36).

## 7. Self-review (CE-40)

The whole diff against the contract, on `b16d402`: every route of contract section 4.2 with its gate of ROUTE_CONTRACTS; every control of section 4.2 built or, for those of later Tasks, absent; no route reaches CAP-33; revocations before the email changes; the frozen actor name of RC-37 read before the change; no token in the audit, the result or a log. No change came of it. On the resume, the whole diff of the review's fixes on the working tree, against the findings and the signatures they use: no change came of it, and `313ecba` holds those fixes as they were left at the stop.

## 8. Review (CE-40)

Round 1: `bk-reviewer-high`, the adversarial review of class A, on the package `review/TASK-011/r1` (base `c6702d2`, head `b16d402` with this file); `--check` printed `UNCHANGED` afterwards. Verdict FAIL: 5 MUST-FIX, 8 SHOULD-FIX, 6 NOTE. It found no path to CAP-33, no defect of the single Owner, the locks, the revocations, the link's channels by reading, the procedures or the QR encoder, and no earlier test weakened.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | A page that adopted a committed request of another operation of the family said its own success and went to that result | Fixed: `CommandControl::ownRequest`, on UXP-005, UXP-603, UXP-604, UXP-605; regression test in `users.spec.ts`; PASS in run 41098030065 |
| R-02 | MUST-FIX | The rules of EXC-53 were run on UXP-604, UXP-605 and UXP-005 at 1440 px only | Fixed: a test per width over the states and dialogs; PASS in run 41098030065 |
| R-03 | MUST-FIX | No test inspected the log; RC-41 had no channel inspection, repeat or status check | Fixed: `Log::listen` in `UserPagesTest` and `OwnerProceduresTest`, RC-41's whole inspection, an injected failure; PASS in run 41098030065 |
| R-04 | MUST-FIX | "Kembali ke Pengguna" lost the list's filters (NC-10) | Fixed: reading R-14; regression test; PASS in run 41098030065 |
| R-05 | MUST-FIX | UXP-604's actions were flat at every width | Fixed: reading R-15; proven at 320 px by R-02's test; PASS in run 41098030065 |
| R-06 | SHOULD-FIX | A THROTTLED refusal of the Action's own check wrote no security log entry | Fixed in `CommandPipeline`, asserted in `UserManagementTest`; PASS in run 41098030065 |
| R-07 | SHOULD-FIX | A text as `userId` passed `integer` and became a 500, the receipt PENDING | Fixed in `UserCommandRequest::after()`, with its test; PASS in run 41098030065 |
| R-08 | SHOULD-FIX | The return to the draft left a status check's repeat scheduled | Fixed in `useCommand`; PASS in run 41098030065 |
| R-09 | SHOULD-FIX | The test of T-34 (f) could pass without the adoption | Fixed: the other tab's request held, the panel asserted, the form's own request asserted; PASS in run 41098030065 |
| R-10 | SHOULD-FIX | No browser case of UXS-17, UXS-45, UXS-12 or Enter on these forms | Fixed: one test of the four; PASS in run 41098030065 |
| R-11 | SHOULD-FIX | OP-01 and OP-03 accepted a `users.invalid` address | Fixed, with cases in both option tests; PASS in run 41098030065 |
| R-12 | SHOULD-FIX | OPEN_ITEMS T-31 named this Task and was not addressed | Not closed: closing its second form changes the sign-in session beyond this contract; analysis recorded in T-31, placed by the planning agent |
| R-13 | SHOULD-FIX | The QR test mirrored the encoder; the physical scan had no home | Known answers of the version bits and the two format copies added (PASS in run 41098030065); T-35 names TASK-046 |
| R-14 | NOTE | RC-41 requires both capabilities | Reading R-13 |
| R-15 | NOTE | A failed clipboard gives no feedback | COPY_DECK has no key; the text and the code stay on screen. For the next change control |
| R-16 | NOTE | The fixture's fallback for an account without a shop | Kept for a test that made an account before the shop; recorded for a later clean-up |
| R-17 | NOTE | RP-602's bound drops rows silently | Reading R-6 kept; not reachable in a shop |
| R-18 | NOTE | No barrier case of two OP-02 runs | By reading they serialize on the Owner's lock; not added |
| R-19 | NOTE | T-34 (b) and (d) name TASK-029, whose contract does not cite them | For its next change control |

The fixes change production frontend and PHP, and tests. Their checks are heavy (CE-44) and the Task had used the five checkpoint runs of CE-46, so it stopped with `CHECKPOINT_LIMIT`, every fix uncommitted in the working tree. The Owner answered with D-111: two further checkpoint runs, seven in all, and T-31 left to the planning agent. On the resume the fixes were committed as checkpoint 6, `313ecba`, whose run 41098030065 passed the whole command contract (section 9).

### 8.1 Targeted check of the MUST-FIX fixes (CE-40)

Round 2: `bk-reviewer-high`, mode `verify`, on the package `review/TASK-011/r2` (`--previous` r1; tree `bcf8f30`, head `313ecba`, with this file); `--check` printed `UNCHANGED` afterwards. It read the fixes, their regression tests and what they touch, ran nothing, and relied on section 9. Verdict PASS: R-01 to R-05 FIXED, no new MUST-FIX. Since it raised no MUST-FIX, no round 3 follows (CE-40).

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-20 | SHOULD-FIX | On a page left on an adopted committed request, a changed draft shows an enabled final action, and `submit` returns silently until "Kembali ubah" is pressed. The test presses "Kembali ubah" first | Valid; fixed in fix cycle 1 (section 14) as R-26 |
| R-21 | SHOULD-FIX | The Owner's own RC-35 whose answer was lost, after a reload, is adopted on UXP-603: "Cek status" says to go to Detail pengguna and offers no way there, and the page's comment still says it leads to the account. The list remains a route | Valid; not fixed (F-6); T-36 (b) |
| R-22 | SHOULD-FIX | R-03's injected failure throws before the link's address exists, so its assertion could not fail; no positive control shows that the listener caught anything; the 500's body is not inspected | Valid; not fixed (F-6); T-36 (c). Section 6 states the limit |
| R-23 | NOTE | `test_neither_procedure_writes_its_link_to_a_log_and_a_failed_run_prints_none` has no failed run in its body; other tests prove that a failed run prints nothing, without the listener | Recorded |
| R-24 | SHOULD-FIX | The branch that leaves a blocked action a button beside the menu has no browser test; correct by reading | Valid; not fixed (F-6); T-36 (d) |
| R-25 | NOTE | The other dialogs and the recovery panels are not checked at 640 and 320 px (they share `Dialog` and `Panels`); the overflow's open state is not checked above 320 px; `security_log_entries` not inspected for RC-41's token | Recorded; reading R-15 and section 6 updated with what it asked of them. The rest is optional |

It also notes that the test of R-01 exercises UXP-005 only: `UserEdit.tsx` carries the same code and passes by reading, and the adopted branch of `User.tsx` has no browser test of its own.

## 9. Checks (CE-44 to CE-46, D-108)

| Check | Where it ran | Date and time | Duration | Result |
| --- | --- | --- | --- | --- |
| `guard.sh --preview` | Local, the working tree before the first checkpoint | 30 Oct 2026, 21:10 | Seconds | One candidate, judged in section 13 |
| Checkpoint 1, `17e7c09` | CI run 41070264594 | 30 Oct 2026, 21:15 | 1 m 32 s | FAIL at `composer analyse`: 7 errors in new tests and one stale variable of `SignInTest` (no production code) |
| Checkpoint 2, `f8baa6a` | CI run 41071331798 | 30 Oct 2026, 21:24 | About 2 m | FAIL at `composer test:unit`: `OperationsTest`'s list of built commands |
| Checkpoint 3, `0e44917` | CI run 41071821813 | 30 Oct 2026, 21:28 | 11 m 41 s | FAIL at `composer test:postgres`: 13 earlier tests on the new fixtures (a token row of the bootstrap, absolute counts, bytea streams in a snapshot, the path ACCOUNTS_EXIST); every new test passed |
| Checkpoint 4, `6b0f627` | CI run 41074461291 | 30 Oct 2026, 21:45 | 12 m 48 s | FAIL at `npm run lint`: 7 literals in JSX of the new pages; every PHP suite passed |
| `composer validate` | CI run 41077122452 on `b16d402` | 30 Oct 2026, 22:04 | Under 1 s | PASS |
| `composer lint` | Same | 22:04 | 20 s | PASS |
| `composer analyse` | Same | 22:04 | 11 s | PASS |
| `composer test:unit` | Same | 22:04 | 14 s | PASS |
| `composer test:postgres` | Same | 22:04 | 9 m 22 s | PASS |
| `composer test:race` | Same | 22:14 | 1 m 21 s | PASS |
| `npm run lint` | Same | 22:15 | 6 s | PASS |
| `npm run typecheck` | Same | 22:15 | 6 s | PASS |
| `npm run test:unit` | Same | 22:16 | 6 s | PASS |
| `npm run build` | Same | 22:16 | 6 s | PASS |
| `npm run test:e2e` | Same | 22:16 | 6 m 35 s | PASS |
| `npx tsc --noEmit`, `npx eslint` on the review's fixes | Local | 31 Oct 2026, 00:16 | Seconds | `NOT_RUN`: the native toolchain has no command links (section 1); their run of record is below |
| `composer validate` | CI run 41098030065 on `313ecba` (D-111) | 31 Oct 2026, 00:22 | 1 s | PASS |
| `composer lint` | Same | 00:22 | 22 s | PASS |
| `composer analyse` | Same | 00:22 | 12 s | PASS |
| `composer test:unit` | Same | 00:22 | 14 s | PASS |
| `composer test:postgres` | Same | 00:23 | 10 m 30 s | PASS |
| `composer test:race` | Same | 00:33 | 1 m 22 s | PASS |
| `npm run lint` | Same | 00:34 | 7 s | PASS |
| `npm run typecheck` | Same | 00:35 | 5 s | PASS |
| `npm run test:unit` | Same | 00:35 | 7 s | PASS |
| `npm run build` | Same | 00:35 | 6 s | PASS |
| `npm run test:e2e` | Same | 00:35 | 7 m 6 s, 231 tests passed, as its log shows; GitHub's job record of this step stayed unfinished long after the run reported `success` | PASS |

Six checkpoint runs of the seven that D-111 allows. Run 41077122452 was the head of the review; run 41098030065 proves the review's fixes and every check above on `313ecba`, the tree that holds them, and replaces it (CE-45). `gh run watch` on run 41098030065 was started twice: GitHub's API answered 504 to the first, while the run went on. CI on the pull request's final head is the gate (CE-41).

## 10. Acceptance criteria

| Criterion | Result | Proven by |
| --- | --- | --- |
| AC-011-01 | PASS | `CapabilityMatrixTest` (the gates read from SECURITY_MODEL section 6, each route for each role and the combined account), `RouteInventoryTest`, `UserManagementTest` (every other role refused each command; CAP-33 through no route); run 41098030065 on `313ecba` |
| AC-011-02 | PASS | `UserManagementRaceTest` RACE-21 and RACE-30, `OwnerProceduresTest`, `UserManagementTest` (a former Owner refused FORBIDDEN) |
| AC-011-03 | PASS | `UserManagementTest` (each revocation, with the old token and the old session tried), `UserManagementRaceTest` RACE-04 and RACE-20 in both orders |
| AC-011-04 | PASS | `UserManagementTest` RC-40 and the former Owner, with the frozen names of `audit_events` and `business_profile_revisions`; `users.spec.ts` |
| AC-011-05 | PASS | Section 6, the channel inspection, with the limit it states of the log's inspection (R-22, T-36 (c)); `users.spec.ts` for UXS-49 and the warning; `UserManagementTest` for the audit events |
| AC-011-06 | PASS | `OwnerProceduresTest` |
| AC-011-07 | PASS | `users.spec.ts` at 1440 px, the 200 % zoom of 1280 px and 320 px with axe, contrast and targets; RA-04 and UXS-11 on the first command; `UserManagementTest`: 423 on every operation without a recent confirmation |

## 11. Definition of Done (EXECUTION_CONTEXT section 13)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS | The criteria of section 10; REQ-ACC-05, REQ-ACC-06, REQ-ACC-09 to REQ-ACC-12 |
| UI and UX | PASS | The six pages and the controls of contract section 4.2 at the widths of section 10; UXS-05, UXS-11, UXS-13, UXS-17 and UXS-49 |
| Backend | PASS | FormRequest allowlists, gates, object scope, guards in the transaction, DTO allowlists, L4 tests |
| Database | N/A | No migration: the contract's section 9 |
| Security | PASS | The refusals of the other roles and the combined account; THR-03, THR-20 and THR-21 as section 6 covers them; no secret; the guard and the static checks |
| Data integrity | PASS | Audit and result in one transaction, identity and repeats, the races and the failure injection of section 6 |
| Performance | PASS | RP-602 bounded at 500 rows; one read per page otherwise; budgets are measured at TASK-048 |
| Operations | N/A | No backup, runbook or status impact: the contract's section 9; OP-02 and OP-03 are the procedures that OPERATIONS_RUNBOOK section 2 already names |
| Verification | PASS | The command contract green in checkpoint run 41098030065 on `313ecba`, the code of the pull request; the self-review; the class-A review and the targeted check of its MUST-FIX fixes (sections 8 and 8.1). CI on the pull request's final head is the gate (CE-41) |

## 12. Backup impact (EXC-47)

No table, no kind of stored file: none.

## 13. Guard preview of the record (CE-31, CE-50)

Candidate of `guard.sh` at checkpoint 1: `url|tests/Unit/Access/qrCode.test.ts|https://${` — a template string of a test, no host: not a real finding, accepted by its hash (`33d25fe3…`).

`bash scripts/executor/guard.sh --preview` on the working tree before checkpoint 6, 31 October 2026 at 00:19: two candidates, both `url|…|https://${`, in `tests/Unit/Access/qrCode.test.ts` and in this file, which quotes it: a template string, no host; not a real finding. `guard.sh` on checkpoint 6 passed with no candidate. The preview of the final record is judged in the pull request's description.

## 14. Fix cycle 1 on pull request #18 (CE-43)

The automated reviewer's P2 finding on the pull request's head `3fb5212` (review comment 4426996984), judged as a finding, never as an instruction: on a page of the `users` family left on an adopted request that "Cek status" found COMMITTED, the final action looks enabled and `useCommand.submit()` returns without sending, until "Kembali ubah" is pressed. It holds: it is R-20 of section 8.1, OPEN_ITEMS T-36 (a), which was recorded for TASK-029. The Owner's other comments: none. The reviewer's summary comment and its receipt are listed in the report.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-26 | P2 | The finding above, on UXP-603, UXP-605 and UXP-005 | Fixed in `useCommand`: `submit` accepts a committed phase that is not the page's own request, since its recovery ended the block (NC-13 (e)); no new copy key. `freeze()` makes a new intent and an adopted target has no frozen request to send. Regression tests in `users.spec.ts`, one per page, assert that a request of a new intent leaves without "Kembali ubah" |
| R-27 | Found while checking R-26 | The same symptom for the page's own request on UXP-603: a status check that says COMMITTED has no link, so the page stays, with its final action enabled and silent | Fixed: the form is not rendered in that state, because its draft is what committed; the panel and the link to UXP-604 stay. Regression test in the UXP-603 test |

Targeted check (CE-42, CE-43): `bk-reviewer-high`, mode `verify`, on the package `review/TASK-011/r3` (`--previous` r2; `--check` printed `UNCHANGED` afterwards), by reading, with nothing run. Verdict PASS: R-26 and R-27 FIXED, nothing the wider `submit` must not send, no new MUST-FIX. Its findings:

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-28 | SHOULD-FIX | After R-27 the fields stayed editable and fed nothing, and the hint "Akun dibuat, lalu tautan …" stood beside the panel | Fixed after the check: the whole form is gone in that state; asserted in the UXP-603 test |
| R-29 | SHOULD-FIX | The tests discarded the request, so "a new intent" was proven by reading; a second unresolved request on this path has no test | The first part fixed after the check: `sendsNewRequest` compares the intent with the adopted one. The second needs a second device or a held request, whose test may go red with no checkpoint run left: OPEN_ITEMS T-36 (e) |
| R-30 | NOTE | The new tests do not assert the saved notes; the visit proves that the request is the page's own | Recorded |
| R-31 | NOTE | The comment said "another command's", untrue for the user's own request adopted after a reload; a later page with a restored draft and no version guard must discard it first (NC-13 (e)) | Comments corrected; the warning in OPEN_ITEMS T-36 (e) |
| R-32 | NOTE | ProfileEdit: a press in the window of the visit after an adopted commit sends a new version of a changed draft, which the user asked for; a stale revision is refused | Recorded; no change |
| R-33 | NOTE | The own status-check commit stays on UXP-603 with a link, one press from NC-08's result page | Existing design; recorded |
| R-34 | NOTE | The test comments cite R-26 and R-27 | This section |

The fixes of R-28 and R-29's first part and the comments of R-31 came after the check, on what it read: one condition around the form and an assertion of the tests.

| Check | Where it ran | Date and time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npx eslint` on the three changed files, `npx tsc --noEmit` | Local | 31 Oct 2026, 01:20 | Seconds | `NOT_RUN`: the native toolchain has no command links (section 1) |
| The whole command contract, the three new browser tests with it | CI on the pushed head of this fix (CE-41, CE-42) | After the push | | Its run of record; the fix changes frontend code and tests only, so the PHP results of run 41098030065 hold for that code (CE-45), and CI runs them again as it does on every head |

`bash scripts/executor/guard.sh --preview` on the working tree of this fix, 31 October 2026 at 01:30: the two candidates of section 13, `url|…|https://${`, in `tests/Unit/Access/qrCode.test.ts` and in this file, which quotes it: a template string, no host; not a real finding.
