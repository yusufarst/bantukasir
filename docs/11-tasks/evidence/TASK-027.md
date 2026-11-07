# TASK-027 evidence — Cash registers and cash session open

| | |
| --- | --- |
| Task | [TASK-027](../tasks/TASK-027-registers-session-open.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-027-registers-session-open`, from `8118cbcb0a3ec06f3e75c13cfc74bfa9f8837e7b` (origin/develop, the squash merge of TASK-026) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-027; the executor state at `BUILD` for TASK-027 with this session's lock; TASK-011 and TASK-022 `DONE`, no gate; `tsc`, ESLint and PHP do not run on this machine (`node_modules/.bin` absent), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 7 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 13:45 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `b4db100` | 13:58 to 14:01 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 1, 42349574943 on `b4db100` | 14:02 to 14:05 | 19 s of Pint | `FAIL`: Pint's `method_argument_space` in `CashPagesTest` (finding F-4); the later steps did not run |
| Guard | Local, on `a26be3d` | 14:07 to 14:10 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint` (Pint, the translation keys and types, the migration review, the parts of `test:postgres`, the secret scan, the audits) | Fast run 2, 42350181420 on `a26be3d` | 14:11 to 14:15 | about 4 min | `PASS` |
| `composer analyse` | Same run | 14:15 | 19 s | `FAIL`: two findings of PHPStan in tests — a check already narrowed in `CashSessionRaceTest` and a nullsafe access in `TokoContoh::openSession` (finding F-5); none in `app/`; the later steps did not run |
| Guard | Local, on `430aed3` | 14:18 to 14:22 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 3, 42351035341 on `430aed3` | 14:26 | 28 s of Pint | `FAIL`: Pint's `method_argument_space` and `unary_operator_spaces` on the two lines of the fixes of F-5, whose spaces after a comma and after `??` the edit had dropped (finding F-6); the later steps did not run |
| Guard | Local, on `bd5addb` | 14:29 to 14:33 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse` | Fast run 4, 42351772127 on `bd5addb` | 14:36 to 14:37 | about 5 min | `PASS` |
| `composer test:unit` | Same run | 14:37 to 14:38 | 23 s | `FAIL`, 1 of 906: `PrivilegeMatrixTest` (finding F-7); the later steps did not run |
| Guard | Local, on `2146161` | 14:42 to 14:45 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 5, 42352646178 on `2146161` | 14:49 to 14:50 | about 6 min | `PASS` |
| `npm run lint` | Same run | 14:50 | 4 s | `FAIL`: ESLint's rule of ADR-005 refused the literal `'0'` inside the review's text on UXP-104 (finding F-8); the later steps did not run |
| Guard | Local, on `10dabc5` | 15:41 to 15:47 | about 6 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 6, 42356955703 on `10dabc5` | 15:47 to 15:53 | about 6 min | `PASS` |
| The changed PostgreSQL and concurrency files | Same run | 15:53 to 15:56 | about 3 min | `CashSessionRaceTest` (5 tests), `CapabilityMatrixTest` (7), `HomeRedirectTest` (4) `PASS`; `RouteInventoryTest` `FAIL`, 1 of 8 (finding F-9); the later files did not run |
| Guard | Local, on `80c9865` | 15:58 to 16:03 | about 5 min | `PASS`, no candidate |
| Static checks and unit tests, as in fast run 6 | Fast run 7, 42358123531 on `80c9865` | 16:04 to 16:09 | about 5 min | `PASS` |
| The changed PostgreSQL and concurrency files | Same run | 16:09 to 16:12 | about 3.5 min | `CashSessionRaceTest`, `CapabilityMatrixTest`, `HomeRedirectTest`, `RouteInventoryTest` (8), `CashPagesTest` (5), `CashRegisterCommandsTest` (6) `PASS`; `CashTablesTest` `FAIL`, 1 of 8 (finding F-10); `OpenCashSessionTest` and `SettingsPagesTest` did not run |
| Guard | Local, on `9ff35fd` | 16:16 to 16:21 | about 5 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 8, 42359433420 on `9ff35fd` | 16:25 to 16:26 | 81 s | `PASS` |
| `npm ci`, `npm run lint`, `typecheck`, `test:unit` | Same run | 16:26 to 16:28 | 112 s | `PASS` |
| The changed PostgreSQL and concurrency files: `CashSessionRaceTest`, `CapabilityMatrixTest`, `HomeRedirectTest`, `RouteInventoryTest`, `CashPagesTest`, `CashRegisterCommandsTest`, `CashTablesTest`, `OpenCashSessionTest`, `SettingsPagesTest` | Same run | 16:28 to 16:33 | 4 min 44 s | `PASS`, every file |
| Guard | Local, on `c8f4d38` (the fixes of review round 1) | 16:53 to 16:58 | about 5 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 9, 42361995551 on `c8f4d38` | 17:01 to 17:02 | 24 s of Pint | `PASS` |
| `composer analyse` | Same run | 17:02 | 11 s | `FAIL`: PHPStan refused `array_unique` over `array<mixed>` in the new test of R-01 (finding F-11); the later steps did not run |
| Guard | Local, on `5fe173f` | 17:06 to 17:12 | about 6 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 10, 42362984381 on `5fe173f` | 17:16 to 17:17 | 83 s | `PASS` |
| `npm ci`, `npm run lint`, `typecheck`, `test:unit` | Same run | 17:17 to 17:19 | 112 s | `PASS` |
| The changed PostgreSQL and concurrency files, the nine of fast run 8 with the tests of R-01 to R-03 | Same run | 17:19 to 17:24 | 5 min 1 s | `PASS`, every file |
| Image checks | Pull request CI 42364653754 on `eb13d6f` | 17:36 to 17:43 | about 7 min | `FAIL`, 1 of 372: the scan gate refused CVE-2026-76844 and CVE-2026-85091 in `zlib1g 1:1.3.dfsg+really1.3.1-1+deb13u1`, a Debian update of the package whose accepted entries name `+b1` (finding F-12); not a defect of this Task |
| Browser suite (`npm run test:e2e`) | Same run | 17:46 to 18:02 | about 16 min | `FAIL`, 3 of 308: `profile.spec.ts` counted two links on Pengaturan, which now has Laci kas as its third (finding F-13) |
| `composer test:postgres` whole, `test:concurrency` whole, the browser suite, the build, the image checks | The pull request's final head, after F-12 and F-13 | — | — | `NOT_RUN` (CE-44, D-117) |
| `npx eslint` on the fix of F-13 | Local | 18:06 | — | `NOT_RUN`: ESLint is not installed on this machine |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. `cash_registers` (class M), `cash_sessions` (class FS, its presence key `close_presence` computed now, its deferred key to `cash_session_closes` TASK-030's) and `cash_session_number_seq` (`CACHE 1`, `NO CYCLE`; runtime `USAGE` only); the key `audit_events.cash_session_id` → `cash_sessions` that TASK-006 left for this Task (EXC-44) | `database/migrations/2026_11_07_000002_create_cash_tables.php`, `database/privileges.php`; `App\Domain\Cash\Registers`, `Sessions`, `SessionNumber`; `AuditLog::record(…, cashSessionId:)` |
| 2. RC-33 and RC-34: the Owner, recent authentication, the code unique without regard to letter case by the index alone, the label limits, a change refused while a session is OPEN on the register under its lock, at the version reviewed; audit `REGISTER_CREATE` and `REGISTER_CHANGE` | `App\Actions\Cash\CashRegisterAction`, `CreateCashRegister`, `UpdateCashRegister`; `App\Http\Requests\Cash\*Register*`; controllers; `routes/web.php`; `CommandActions` |
| 3. RC-02: the register's update lock, the register active and free, the actor without an OPEN session, a BusinessProfile revision, an explicit opening amount (blank refused, Rp0 valid), the number and business date at step 8, atomic, no Sale and no movement; audit `CASH_SESSION_OPEN` with its session | `App\Actions\Cash\OpenCashSession`, `OpenCashSessionRequest`, `OpenCashSessionController` |
| 4. RP-609, RP-610, RP-104 (KS-06 with `default`, `review`, `no-register`), UXI-328 on RP-601, the mark of UXP-602, UXI-002 "Kasir"; RN-01 of a cashier without an OPEN session to RP-104; UXI-100 to the role's non-till home | `RegistersController`, `OpenSessionController`, `App\Queries\Cash\CashRegisterRows`, `OpenSessions`; `Homes`, `HomeRedirectController`, `SettingsController`, `UsersController`, `UserAccounts`, `UserRowData`; `resources/js/pages/cash/`, `resources/js/lib/cash/registers.ts`, `navigation.ts`, `Settings.tsx`, `Users.tsx`, `Stock.tsx`; `lang/id/cash.php`, `sales.php`, `access.php`, `config.php`, `ux.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| The contract's "Keys completed: —" omits `audit_events.cash_session_id` → `cash_sessions`, which TASK-006's migration and `AuditLog` leave to this Task; the migration adds it | EXC-44 requires the key of an earlier table that targets the new one; a technical correction with no business meaning (EXC-81) |
| RC-02's result leads to RN-01 (`/`), and RP-104 sends a user with an OPEN session there with 302, until TASK-029 delivers RP-101 | EXC-52: a destination not yet delivered leads to the role's home |
| UXI-002 "Kasir" leads to RP-104, whose server sends a user with an OPEN session home | The same reading; INTERACTION_INVENTORY UXI-002 gives UXP-101 with a session and UXP-104 without one |
| Until TASK-029, a cashier with an OPEN session goes home to Akun saya, the combined account to Stok either way | Homes of NC-12 not yet delivered (TASK-007's rule, EXC-52) |
| A register that does not exist is `NOT_FOUND` on RC-02 and RC-34; an inactive register or one in use is `VALIDATION_FAILED` at `payload.registerId` with `ux.validation.register_taken`; an actor who already holds a session is `VALIDATION_FAILED` without a field, and RP-104 then loads again, which leads home | RC-02 ("at `registerId`, or at the form"); COPY_DECK section 16.1 names no text for the form's case, and the reload shows the user's own session's home |
| A session opened on the register meanwhile refuses RC-34 at `payload.registerId` with `ux.validation.register_in_use`; a change that changes nothing is `VALIDATION_FAILED` without a field | RC-34, NAVIGATION_CONTRACTS UXP-610; TASK-011's rule for an empty change |
| A code another register holds is refused at `payload.code` with `ux.validation.register_code_taken`, mapped from the unique index's violation, never decided by the application | SCH-14 |
| The guard G-23 reads the current BusinessProfile without a lock; a missing revision is an internal error | TR §7.2 lists no step 2.4 for the open; no command removes a revision (BR-CFG-02), so its absence is a broken invariant, not a user's refusal |
| A register's status words are `catalog.status.active` and `catalog.status.inactive`, "Aktif" and "Nonaktif" | COPY_DECK section 11.9 gives the words without its own keys |
| A register is named "code · label" (the code alone when they are equal) in the choice, the review, the row's accessible name | SCHEMA section 5.10: the code identifies a drawer; the label is not unique |
| At most three registers are a segmented control, more a list | DESIGN_SYSTEM 8.4: the segmented control holds two or three options |
| Access's pages compose the Cash module's read of OPEN sessions (RN-01, UXP-602) in their controllers, as RD-03 composes Inventory's (TASK-022); `Homes` takes the answer as an argument, so the Access domain does not depend on Cash | STACK_AND_MODULES section 2.1: Cash depends on Access, so the reverse would be a cycle |
| The opening cash in the audit's change data is its canonical string | ADR-006, SCHEMA section 3.8 |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | `tsc`, ESLint and PHP do not run on this machine, so every static check is met first in CI (TASK-011 F-5) | As recorded since TASK-011; the fast runs cover them |
| F-2 | For TASK-029: RC-02's location and RP-104's redirect with a session are RN-01 until RP-101 exists; UXI-002 leads to RP-104; `Homes` sends a cashier with an OPEN session to Akun saya and the combined account to Stok | TASK-029 switches them to RP-101 (`OpenCashSession::LOCATION`, `OpenSessionController::WITH_SESSION`, `DESTINATIONS.till`, `Homes::of`) |
| F-4 | Fast run 1 failed Pint: a missing space after a comma in `CashPagesTest` | Fixed in checkpoint 2 |
| F-5 | Fast run 2 failed PHPStan on two lines of tests | Fixed in checkpoint 3, tests only |
| F-6 | Fast run 3 failed Pint on those two lines: a space dropped after a comma and after `??` | Fixed in checkpoint 4, tests only |
| F-7 | Fast run 4 failed one unit test: `PrivilegeMatrixTest` read a column-level `UPDATE` as a table privilege, which `tablePrivileges()` lists only for an update of every column | Fixed in checkpoint 5, test only |
| F-8 | Fast run 5 failed `npm run lint`: ESLint's rule of ADR-005 refused the literal `'0'` that the review's text on UXP-104 formats while no amount is entered. The same checkpoint gives RP-104 the till's icon rail (UXA-03, KS-06), which `shell.spec.ts` now reads: the banner names no shop there | Fixed in checkpoint 6: a named constant; the rail; the browser test |
| F-9 | Fast run 6 failed `RouteInventoryTest::test_no_route_of_a_disabled_feature_exists`: its fragment `register`, meant for Fortify's registration route, matched RP-609's contractual path `settings/cash-registers` anywhere in the path | Fixed in checkpoint 7, test only: a fragment is refused at the start of any segment of a path, and the test now proves that Fortify's paths are still caught and the registers' paths are not; every fragment that matched no path before still matches none |
| F-10 | Fast run 7 failed `CashTablesTest::test_each_check_and_key_of_a_session_refuses_its_violation`: the session with a seven-digit counter, committed in the middle of the test with the default opening command, made the later refusals answer `cash_sessions_open_command_unique` before the keys they test | Fixed in checkpoint 8, test only: that session is committed last, after every refusal; no assertion changed |
| F-11 | Fast run 9 failed PHPStan on one line of the new test of R-01: `array_unique` over the receipts' outcomes, typed `array<mixed>` | Fixed in checkpoint 10, test only: the harness's `outcomesOf()`, which asserts both receipts, the original's and the repeat's, `COMMITTED`, before the status check; the result's paths one by one |
| F-12 | The pull request's image checks on `eb13d6f` (failure 1 of 3, CE-42): Debian now ships `zlib1g 1:1.3.dfsg+really1.3.1-1+deb13u1`, and the scan gate refuses CVE-2026-76844 and CVE-2026-85091 there, because their accepted entries in `scripts/checks/image-scan-accepted.json` (D-112) name the version `+b1`; the gate reports them `ACCEPTED_NOT_REPORTED` for `+b1` and `REFUSED_NOT_ACCEPTED` for `+deb13u1`, both `not-fixed`, not exploited. Nothing of this Task touches the image | Not a defect of this Task, and not changed here: accepting an advisory for the new version is the Owner's decision (stop `IMAGE_SCAN_REFUSED`, CE-70); the fix of F-13 is committed and not pushed, so that the pull request's next head carries both |
| F-13 | The pull request's browser suite on `eb13d6f`: `profile.spec.ts` (three widths) still counted two links on Pengaturan, which the Task gives Laci kas as a third entry (UXI-328); `SettingsPagesTest` had been updated, the browser test had not | Fixed in the fix cycle, test only: three links, and Laci kas among them; runs with the browser suite on the next pull request head |
| F-3 | For TASK-030: the browser suite opens the Owner's session once, so `tests/Browser/cash/cash.spec.ts` needs a database without it, as CI's fresh one is; and the key of `close_presence` to `cash_session_closes` comes with that table | TASK-030 adds the key and lets the browser fixtures close the Owner's session first |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 families of `cash_registers`, `cash_sessions` and `cash_session_number_seq` (constraint, presence key, privilege, retention, numbering, indexes; the key of `audit_events`) | `tests/Feature/Cash/CashTablesTest.php`; `PrivilegeMatrixTest` extended |
| L3 RACE-07 (one register, one user), RACE-26 (both orders), RACE-04 (a role change against a running open, both orders), RACE-01 and RACE-02 for RC-02, RC-33 and RC-34 (the same key, another key of the same intent, another payload under one key), the numbering family (no wait for a number, allocation order) | `tests/Concurrency/CashSessionRaceTest.php` |
| L4 RC-02: Rp0, the number and business date, its audit event and result, no movement, a repeat; a blank or malformed amount; an inactive register, one in use, an actor with a session, none of which takes a number; the roles without recent authentication; the allowlist; the failure after each write step and the gap it leaves; the numbers across Asia/Jakarta midnight; G-23 | `tests/Feature/Cash/OpenCashSessionTest.php` |
| L4 RC-33 and RC-34: create and change with audit; a repeat and a status check answering the original result, with the receipt COMMITTED; the code in any letter case; a change refused while a session is OPEN, at a stale version and when it changes nothing; the fields; the roles and recent authentication; the failure after each write step | `tests/Feature/Cash/CashRegisterCommandsTest.php` |
| L4 the pages, their channels per role, RN-01, UXP-602's mark, no shared drawer, transfer, paid-in, paid-out or pickup | `tests/Feature/Cash/CashPagesTest.php`, `CapabilityMatrixTest`, `RouteInventoryTest`, `HomeRedirectTest`, `SettingsPagesTest`, `tests/Harness/Channels/Allowlists.php`, `OperationsTest` |
| L5 KS-06 (`default`, `review`, `no-register`), RP-609 at every width, RP-610, with axe | `tests/Browser/cash/cash.spec.ts`; the cashier's new home in `sign-in.spec.ts`, `session.spec.ts`, `shell.spec.ts`, `requests.spec.ts`, `profile.spec.ts` and `support/access.ts` |
| L1 the navigation's "Kasir" | `tests/Unit/Shell/navigation.test.ts` |

## 6. Acceptance criteria

| Criterion | Proof | Result |
| --- | --- | --- |
| AC-027-01 The two partial unique indexes hold; concurrent opens of one register or by one user give one success (RACE-07) | `CashTablesTest` (both indexes, CLOSED unlimited), `CashSessionRaceTest` (RACE-07 twice) | See section 1 |
| AC-027-02 No shared drawer, transfer of responsibility, paid-in, paid-out or cash pickup exists in the route and command inventory | `CashPagesTest::test_no_shared_drawer_transfer_paid_in_paid_out_or_pickup_exists`, `RouteInventoryTest` | See section 1 |
| AC-027-03 A blank opening amount is refused; Rp0 is accepted; the open is atomic and creates no Sale or movement; a revision exists from the bootstrap (G-23) | `OpenCashSessionTest` | See section 1 |
| AC-027-04 Registers are created and changed with recent authentication and audit; a change is refused while a session is OPEN, also against a concurrent open (RACE-26); codes are unique without regard to letter case | `CashRegisterCommandsTest`, `CashSessionRaceTest` (RACE-26), `CashTablesTest` (`cash_registers_code_ci_uniq`) | See section 1 |
| AC-027-05 Session numbers follow allocation order, do not wait on each other, leave a gap after a rollback and carry the right date part across Asia/Jakarta midnight | `OpenCashSessionTest` (the gap, midnight), `CashSessionRaceTest` (no wait, allocation order), `CashTablesTest` (the format and the sequence) | See section 1 |
| AC-027-06 KS-06 is reproduced with its states; the pages pass the accessibility checks | `cash.spec.ts` (axe at every width), `CashPagesTest` | The browser suite runs on the final head (D-117) |

## 7. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | `PASS` | AC-027-01 to AC-027-05 proven by the L2 to L4 tests of the fast runs (section 1); REQ-CASH-01 to REQ-CASH-03, REQ-CFG-04 |
| UI and UX | `BLOCKED` until the final head | RP-104, RP-609 and RP-610 follow COPY_DECK 7.4, 11.9 and 11.10, KS-06, INTERACTION_INVENTORY and NAVIGATION_CONTRACTS; the L4 page tests passed; the browser suite with `cash.spec.ts` (axe at every width) runs first on the pull request's final head (D-117) |
| Backend | `PASS` | FormRequest allowlists, CAP-21 and CAP-31 at the route and in the transaction, the responsible user always the actor, NOT_FOUND by object scope, the L4 tests |
| Database | `PASS` for the families run; `test:postgres` whole on the final head | The two tables and the sequence with the constraint, presence-key, privilege, retention, numbering and index families (`CashTablesTest`); forward and additive migration with class, duration, lock impact and verification (EXC-46; its rehearsal on the benchmark dataset waits for TASK-043) |
| Security | `PASS` | The capability matrix and route inventory, the refusals of the other roles and the combined account, recent authentication of RC-33 and RC-34, the channel allowlists per role, no secret (the guard) |
| Data integrity | `PASS` | Retained sessions with state columns only, the number held by a check, one OPEN session per register and per user by the database, atomic audit and result, RACE-07, RACE-26, RACE-04, RACE-01 and RACE-02 for each of the three commands, the failure after every write step |
| Performance | `PASS` | Bounded lists (500 registers, the holders of OPEN sessions); no counter row or lock for numbering (SCH-25); the budgets are measured at TASK-048 |
| Operations | `PASS` | Both tables in the manifest's counts and the sequence in the backup role's reads (section 8); no runbook or status change |
| Verification | `BLOCKED` until the final head | Self-review and the class-A review (section 9); the fast runs of section 1; the whole command contract and the image checks are `NOT_RUN` until the pull request's final head |

## 8. Backup impact (EXC-47)

`cash_registers` (class M) and `cash_sessions` (class FS) hold retained data, so they are in the manifest's counts, which `backup:run` takes for every table of the matrix of a counted class (`BackupRunner::COUNTED_CLASSES`; TASK-013), with the backup role's `SELECT` on both tables, their identity sequences and `cash_session_number_seq`. After a restore, the runbook advances `cash_session_number_seq` with the migration role (SCH-28, BACKUP_RECOVERY RB-01 step 13), unchanged. No new kind of stored file. No status change.

## 9. Review (EXECUTION_CONTEXT section 12, CE-40)

Self-review before the review, on `9ff35fd`: the Actions, the domain classes, the request, the controllers, the migration, the race tests and the browser test against the contract; no defect found.

Round 1, class A, adversarial, by `bk-reviewer-high`, on `9ff35fd` with the package `r1` (`review-pack.sh --check`: `UNCHANGED`). Verdict `FAIL`, one MUST-FIX. The production code held under attack: the register lock and RACE-07/RACE-26, the numbering at step 8, the grants, authorization and atomicity.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | RACE-01 and RACE-02, the COMMITTED receipt and the recovery of the result were proven for RC-02 only, not for RC-33 and RC-34 (EXECUTION_CONTEXT section 10, a command) | Fixed, tests only: `CashSessionRaceTest::test_race_01_and_race_02_a_register_command_runs_once_for_the_same_key_or_the_same_intent` (the same key, another key of the same intent, `IDEMPOTENCY_CONFLICT` for another payload, for both operations); `CashRegisterCommandsTest::test_a_repeat_and_a_status_check_answer_the_original_result_and_the_receipt_is_committed` |
| R-02 | SHOULD-FIX | RACE-04, to which the contract says the Task contributes, was not proven for RC-02 | Fixed, test only: `CashSessionRaceTest::test_race_04_a_role_change_against_a_running_open_in_both_orders`; mapped in sections 5 and 7 |
| R-03 | SHOULD-FIX | Nothing proved that a definite refusal takes no number (SCH-21, TR-TX-10) | Fixed, tests only: after the FormRequest refusals, an inactive register, one in use, NOT_FOUND and an actor with a session, the next open takes the next counter (`OpenCashSessionTest`) |
| R-04 | SHOULD-FIX | On UXP-610, outer spaces alone enabled "Simpan", whose trimmed request was refused without a reason shown | Fixed: `RegisterForm.tsx` compares the trimmed values, so the button says `ux.disabled.no_change`; regression in `cash.spec.ts` |
| R-05 | SHOULD-FIX | Performance recorded `N/A` with a reason of checked bounds | Fixed: `PASS` (section 7); the budgets stay TASK-048's |
| R-06 | NOTE | The contract's "Keys completed: —" omits the key of `audit_events` that the migration completes | Kept as the reading of section 3 (EXC-44, EXC-81); the contract is not changed by its own Task |
| R-07 | NOTE | `no-register` in the browser comes from rewriting the server's response; `CashPagesTest` proves the server sends `[]` | Accepted as recorded |
| R-08 | NOTE | An inactive register is refused with the text of a register in use | For the Owner: a COPY_DECK change-control item for a text of its own; COPY_DECK 16.1 names one text today |
| R-09 | NOTE | `cash.spec.ts` runs once per database until TASK-030; its codes are random | F-3, for TASK-030 |
| R-10 | NOTE | Two Access controllers read the Cash module's `OpenSessions` | As the reading of section 3 and the precedent of TASK-022; for the Owner whether STACK_AND_MODULES 2.1 records it |

The fixes passed fast run 10 on `5fe173f` (section 1). Because R-01 was a MUST-FIX, round 2 is the targeted verification of its fix and its tests (CE-40), below.

Round 2, verification, by `bk-reviewer-high`, on `5fe173f` with the package `r2` (previous `r1`; `review-pack.sh --check`: `UNCHANGED`). Verdict `PASS`: R-01 to R-05 `FIXED`, nothing regressed. One new NOTE:

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-11 | NOTE | The regression of R-04 is a browser assertion, which runs first on the pull request's final head | Recorded: the browser suite's result on the final head, `cash.spec.ts` included, moves Verification and UI and UX off `BLOCKED` (section 7) |

## 10. Guard candidates (CE-50)

`guard.sh --preview` before the final commit and `guard.sh` before every push: no candidate of the privacy and client-data check on any head of this branch; Gitleaks and the repository's secret scan found nothing.
