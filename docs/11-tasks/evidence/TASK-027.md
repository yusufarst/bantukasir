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
| F-3 | For TASK-030: the browser suite opens the Owner's session once, so `tests/Browser/cash/cash.spec.ts` needs a database without it, as CI's fresh one is; and the key of `close_presence` to `cash_session_closes` comes with that table | TASK-030 adds the key and lets the browser fixtures close the Owner's session first |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 families of `cash_registers`, `cash_sessions` and `cash_session_number_seq` (constraint, presence key, privilege, retention, numbering, indexes; the key of `audit_events`) | `tests/Feature/Cash/CashTablesTest.php`; `PrivilegeMatrixTest` extended |
| L3 RACE-07 (one register, one user), RACE-26 (both orders), RACE-01, RACE-02, the numbering family (no wait for a number, allocation order) | `tests/Concurrency/CashSessionRaceTest.php` |
| L4 RC-02: Rp0, the number and business date, its audit event and result, no movement, a repeat; a blank or malformed amount; an inactive register, one in use, an actor with a session; the roles without recent authentication; the allowlist; the failure after each write step and the gap it leaves; the numbers across Asia/Jakarta midnight; G-23 | `tests/Feature/Cash/OpenCashSessionTest.php` |
| L4 RC-33 and RC-34: create and change with audit; the code in any letter case; a change refused while a session is OPEN, at a stale version and when it changes nothing; the fields; the roles and recent authentication; the failure after each write step | `tests/Feature/Cash/CashRegisterCommandsTest.php` |
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
| Data integrity | `PASS` | Retained sessions with state columns only, the number held by a check, one OPEN session per register and per user by the database, atomic audit and result, RACE-07, RACE-26, RACE-01, RACE-02, the failure after every write step |
| Performance | `N/A` | Bounded lists (500 registers, the holders of OPEN sessions); no counter row or lock for numbering (SCH-25); the budgets are measured at TASK-048 |
| Operations | `PASS` | Both tables in the manifest's counts and the sequence in the backup role's reads (section 8); no runbook or status change |
| Verification | `BLOCKED` until the final head | Self-review and the class-A review (section 9); the fast runs of section 1; the whole command contract and the image checks are `NOT_RUN` until the pull request's final head |

## 8. Backup impact (EXC-47)

`cash_registers` (class M) and `cash_sessions` (class FS) hold retained data, so they are in the manifest's counts, which `backup:run` takes for every table of the matrix of a counted class (`BackupRunner::COUNTED_CLASSES`; TASK-013), with the backup role's `SELECT` on both tables, their identity sequences and `cash_session_number_seq`. After a restore, the runbook advances `cash_session_number_seq` with the migration role (SCH-28, BACKUP_RECOVERY RB-01 step 13), unchanged. No new kind of stored file. No status change.
