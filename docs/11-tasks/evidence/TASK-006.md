# TASK-006 evidence — Authentication, one-time links, audit and security log

| | |
| --- | --- |
| Task | [TASK-006](../tasks/TASK-006-authentication.md), size L, review class A, lane P |
| Status | `DONE` when the Owner squash-merges its pull request and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101, continuous execution (EXECUTION_CONTEXT section 19, CE-01): the selection of CE-10 at `origin/develop` `46c5ddc`, the lowest-numbered `READY` Task with its dependencies `DONE` and no gate; mode `start`, delegated by `scripts/executor/tick.sh` on 26 October 2026 at 04:53 to the agent `bk-executor-xhigh`. The delegation quoted no words of the Owner |
| Branch | `task/TASK-006-authentication`, created from `origin/develop` (CE-02) |
| Base commit | `46c5ddcb486a1a9d57963835d494100438538ad9`, the squash commit of pull request #8 (`ops/`, the D-104 hotfix) on `develop` |
| Executor | The implementation executor agent `bk-executor-xhigh` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, from the table of EXECUTION_CONTEXT section 19 (CE-11) |
| Reviewers | `bk-reviewer-xhigh` for the adversarial review of class A, and `bk-reviewer-high` for a verification of fixes (CE-11, CE-40); section 11 |
| Gates | None (TASK_PLAN section 3); EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01, for this Task only: its branch, its commits and pushes (CE-30), one pull request into `develop` and its CI, and the fixes of CE-40 to CE-43. Not authorized: any merge, auto-merge or approval; any push of `develop`, `main` or `plan/bantukasir-pos-v1`; a tag; a GitHub setting; staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24, CE-20)

- **Read path (EXC-01).** AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT (section 19 first), TASK_PLAN sections 1 to 4 and the contract, then its "Read first": ADR-004; SECURITY_MODEL sections 2, 3, 4 and 9, SEC-CH-03, SEC-CH-04, SEC-CH-08 and section 11; SCHEMA sections 3.4, 3.5, 4, 5.1, 5.4 and 10; INTERACTION_CONTRACTS IC-09 to IC-14; ROUTE_CONTRACTS RP-001 to RP-004 and RA-01 to RA-07; CRITICAL_FLOWS WF-01 to WF-03; UI_STATES section 5; COPY_DECK sections 3 and 6.2 to 6.5, with sections 12 and 1 for the states and the format of the keys; INFORMATION_ARCHITECTURE, NAVIGATION_CONTRACTS and INTERACTION_INVENTORY for UXP-001 to UXP-004; KEY_SCREENS KS-01 with its preview; DOMAIN_MODEL section 8; OPEN_ITEMS T-23, T-26 and T-27; D-049, D-056.
- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names; no stop.
- **Executor state and GitHub (EXC-20 under CE-20)**, on 26 October 2026 at 04:54: phase `BUILD`, Task TASK-006, class A, branch `task/TASK-006-authentication`, base `46c5ddc`, effort `xhigh`, agents `bk-executor-xhigh`, `bk-reviewer-xhigh` and `bk-reviewer-high`; the merge of pull request #8 verified by the tick (`MERGE_VERIFIED`, 04:51:09), the loop resumed by the Owner (04:53:28), the lock taken by this run (04:53:39). `origin/develop` at `46c5ddcb486a1a9d57963835d494100438538ad9` after `git fetch`; no branch `task/TASK-006-*` locally or on GitHub; no open pull request (`pr.sh status`: `REFUSED NO_PR`; `gh pr list`: none).
- **Working tree (EXC-20, CE-70).** The main working tree on `ops/d104-stop-keys` at `d40478c`, whose tree equals that of `46c5ddc` (section "Merge" of the record, 9.9); nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`, both listed in CURRENT_HANDOFF section 2; `stash@{0}` present; remote `origin` only; the worktree `bantukasir-task002` clean on `task/TASK-002-p7-change-control-1`. No `CLAUDE_CODE_EFFORT_LEVEL`.
- **Prerequisites (EXC-21).** TASK_PLAN section 4 at `46c5ddc`: TASK-004 and TASK-005 `DONE`, TASK-006 `READY`; no gate.
- **The previous merge (CE-50, CE-61).** Pull request #8 checked again on GitHub and recorded in [CONTINUOUS_EXECUTION_SETUP](../../00-governance/records/CONTINUOUS_EXECUTION_SETUP.md) section 9.9 and in CURRENT_HANDOFF.
- **Tools (EXC-23).** Docker and Docker Compose answer; the images of TASK-001 by digest. The local port 127.0.0.1:8080 is held by another program of the machine, so the local runs set `APP_PORT=18080`, as TASK-005's did; CI is unchanged.
- **Branch (EXC-22, CE-02).** `git switch -c task/TASK-006-authentication 46c5ddcb486a1a9d57963835d494100438538ad9`, on 26 October 2026 at 04:54:48.
- **Start recorded (EXC-24 under CE-21).** No start commit: the `IN_PROGRESS` of the Task in flight is the executor state's (CE-21).

## 2. Versions and sources checked (EXC-03)

Context7 is not verified in the executor environment (TOOLCHAIN section 1). The version-sensitive work was checked against the installed source of the pinned versions:

| Source | What it settled |
| --- | --- |
| `laravel/fortify` 1.41.0 (`composer show --all`, then `vendor/laravel/fortify`: `routes/routes.php`, `FortifyServiceProvider`, `Features`, the controllers of sign-in, sign-out, confirmation and password update, `LoginRequest`, `PrepareAuthenticatedSession`, the response contracts, `config/fortify.php`) | The features and their switches; that `Fortify::ignoreRoutes()` leaves every route to the application and that `views => false` registers no GET page; the pipeline of `authenticateThrough`; the contracts the application binds; that 1.41.0 requires `laravel/passkeys`, `pragmarx/google2fa` and `bacon/bacon-qr-code` whatever features are enabled (F-2) |
| `laravel/passkeys` 0.2.1 (`vendor/laravel/passkeys`) | Its routes are registered only through Fortify's provider when the feature is on, and `Fortify::ignoreRoutes()` covers them |
| `laravel/framework` 13.37.0 (`config/auth.php`, `config/hashing.php`, the database token repository and password broker, `RequirePassword`, `ShareErrorsFromSession`, `TrimStrings`, the Argon2id hasher) | The broker's hashed token, expiry and throttle; the 423 of the confirmation middleware; that the framework never trims the three password fields |
| `inertiajs/inertia-laravel` 3.5.1 and `@inertiajs/react` 3.9.1, `@inertiajs/core` (the distributed source and types) | `Inertia::location` answers 409 and causes a full page load; `clearHistory` and `encryptHistory`; the visit callbacks `onError`, `onHttpException` and `onNetworkError`; the initial visit reads `window.location` and its hash, which is why the fragment is read and removed before the application starts; history encryption needs a secure context (F-4) |
| PostgreSQL 18.6 (the pinned image) | Row security, permissive policies, `relforcerowsecurity`, `pg_policy` and `pg_get_expr`; `pg_dump` and `row_security` (section 4) |

The added Composer packages, their versions and licences are in TOOLCHAIN section 1.1: `laravel/fortify` 1.41.0 (MIT) and the 20 packages it requires, MIT except `bacon/bacon-qr-code` and `dasprid/enum` (BSD-2-Clause); 21 installs, no version change of an installed package; `composer audit`: no advisory. No npm package is added.

## 3. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The five tables, with their checks, indexes and grants | `database/migrations/2026_10_26_000001_create_access_tables.php` (`users`, `sessions`, `password_reset_tokens`) and `…_000002_create_audit_tables.php` (`audit_events`, `security_log_entries`); `database/privileges.php`; `audit_events.command_id`, `product_id` and `cash_session_id` without a key, which TASK-008, TASK-015 and TASK-027 add (EXC-44) |
| 2. T-23 | Section 4; SCHEMA SCH-06 and sections 5.4 and 10; SECURITY_MODEL SEC-LOG-05 and section 16; OPEN_ITEMS T-23 `RESOLVED` |
| 3. Fortify with only the features of ADR-004 item 1 | Section 5; `config/fortify.php`, `app/Providers/AccessServiceProvider.php`, `config/mail.php` |
| 4. Argon2id and AICWDF-COMPAT-8 | `config/hashing.php`; `App\Domain\Access\PasswordProfile`, the rule of every path that sets a password (RA-05, RA-06); `Accounts::verifyCredentials` with a decoy hash, `Accounts::rehashIfNeeded` as a compare-and-set update |
| 5. RA-01 to RA-05 and RA-07 | `routes/web.php`: RA-01 to RA-04 on Fortify's controllers, RA-03 behind the allowlist `ConfirmationInput`, RA-05 on the application's `OwnPasswordController` with Fortify's updater (R-01); `app/Http/Auth/` (the pipeline stages `AuthenticateUser` and `StartSignInSession`, `Responses`, `Limits`); `UpdateOwnPassword`; `SessionActivityController` |
| 6. The limits of the sign-in session | `App\Http\Middleware\EnsureSignInSession` with `App\Http\Auth\SignInSession`: 30 minutes without a user-initiated request, `X-Background-Request: 1` never counting, and 12 hours absolute; a deactivated or anonymized account loses its session at its next request and cannot sign in |
| 7. The one-time link broker and RA-06 with RP-002 | `App\Domain\Access\OneTimeLinks` on the framework's database token repository; `App\Actions\Access\ConsumeLink`; `PasswordLinkController`, `ConsumeLinkRequest`, `NoReferrer`; `resources/js/lib/linkFragment.ts`, called by `app.tsx` before Inertia starts |
| 8. The audit writer and the security log writer | `App\Domain\Audit\AuditLog` (in the transaction only; the actor columns and the frozen name; `owner_only_data` apart; secret-looking keys refused) and `SecurityLog` (refuses inside a transaction; one JSON line on the `security` log channel, the container's standard error stream) |
| 9. UXP-001 to UXP-004 | `resources/js/pages/access/SignIn.tsx`, `SetPassword.tsx`, `Account.tsx`, `ChangePassword.tsx`; `PasswordInput` in `Field.tsx`; `OwnAccount` and `OwnAccountData` |
| 10. Test fixtures through the Access service | `tests/Harness/Fixtures/TokoContoh.php`: each account is created by `Accounts::create` and given its password through a link and `ConsumeLink` (EXC-45) |

The pages, controls and routes of contract section 4.2 are all built: RP-001 to RP-004, RA-01 to RA-07, and the controls UXI-039 to UXI-045 and UXI-047 to UXI-050 (UXI-046 is the Owner's "Ubah nama dan email", UXP-005, which is TASK-011's).

## 4. T-23: the mechanism, its test and its effect on the dump (contract section 8)

- **Mechanism.** Row security is enabled on `security_log_entries`, not forced, with three permissive policies that name no role: `security_log_entries_read` (`SELECT`, `USING (true)`), `security_log_entries_add` (`INSERT`, `WITH CHECK (true)`) and `security_log_entries_retention` (`DELETE`, `USING (occurred_at < now() - interval '8760 hours')`). The runtime role keeps `SELECT`, `INSERT` and `DELETE` (SCHEMA section 10); its `DELETE` of an entry younger than 365 days matches no row and removes nothing, and an older entry is removable. 365 days are written as 8,760 hours, a fixed length, so that no session time zone and no change of daylight saving time shortens it. No trigger, rule, stored procedure or `SECURITY DEFINER` function is used: SCH-06 names a row security policy as a privilege, written in a migration like a check constraint. The migration role owns the table and is not subject to the policies.
- **The privilege check.** `database/privileges.php` gives the table a `row_security` map of its three policies; `PrivilegeMatrix` admits policies only on a table of class L, which must have a `DELETE` policy; `db:check-privileges` (`App\Database\PrivilegeCheck`) compares `relrowsecurity`, `relforcerowsecurity` and each policy of `pg_policy` — command, `USING`, `WITH CHECK`, permissive, `PUBLIC` — with the matrix, for every table, and fails on any difference.
- **Tests.** `AuditTablesTest::test_the_runtime_role_removes_a_security_log_entry_only_after_365_days`, as the runtime role on committed rows, the times taken from the database's clock as the housekeeping will: of entries 366 days, 8,760 hours and one minute, 8,760 hours less one minute, 30 days and no time old, a `DELETE` without a condition removes exactly the first two and refuses nothing; a `DELETE` aimed at a younger entry removes nothing; `UPDATE` and `TRUNCATE` are denied. `test_the_retention_holds_for_every_session_time_zone`: under `America/New_York`, whose days can be 23 hours, the entry of 8,760 hours less one minute stays. `test_the_security_log_grants_and_its_row_security`: the grants, the backup role reading every entry and deleting none, and the flags and policies in the catalogue. `PrivilegeCheckTest::test_the_check_fails_on_row_security_that_differs_from_the_matrix`: forced row security, a changed and a missing policy, an extra policy naming the runtime role, and row security with a policy on another table are each reported, and the check is clean again after the policies are restored. `PrivilegeMatrixTest` for the shape of the map.
- **Effect on the backup dump (for TASK-013).** Measured on 26 October 2026 at about 05:27 on the local database, as the backup role of the local environment: `pg_dump --data-only -t security_log_entries` exits 1 with `query would be affected by row-level security policy for table "security_log_entries"`, because `pg_dump` sets `row_security` off and the role is subject to the policies; with `--enable-row-security` the dump runs, with `SET row_security = on`, and the read policy admits every entry. TASK-013's dump therefore passes `--enable-row-security`. SCHEMA section 5.4 and OPEN_ITEMS T-23 state it. Measured again after the run of section 9.1, at 08:19:33: the backup role reads 89 entries; without the switch `pg_dump` exits 1 with the same error; with it, exit 0, `SET row_security = on`, and 89 rows in the table's `COPY` block.
- **For TASK-038.** The housekeeping deletes as the runtime role and finds only the entries the policy admits; its bounded batches need no further condition for safety.

## 5. The Fortify feature list (contract section 8)

| Feature of Fortify 1.41.0 | State | How it is proven |
| --- | --- | --- |
| Authentication (sign-in, sign-out) | Used: RA-01 `POST /login` and RA-02 `POST /logout` on `AuthenticatedSessionController`, with the application's pipeline | `SignInTest`, `SignInSessionTest` |
| Password confirmation | Used: RA-03 `POST /user/confirm-password` on `ConfirmablePasswordController`, behind the allowlist `ConfirmationInput`, RA-04 `GET /user/confirmed-password-status` on `ConfirmedPasswordStatusController`; 15 minutes (`auth.password_timeout` 900) | `RecentAuthenticationTest` |
| `updatePasswords` | Enabled: RA-05 `PUT /user/password` with Fortify's updater contract, `UpdateOwnPassword`, and its response, on the application's `OwnPasswordController`: Fortify's `PasswordController` would also delete the user's one-time link (review finding R-02) | `PasswordChangeTest`, `RouteInventoryTest`, `OneTimeLinkTest` |
| `registration` | Off | `RouteInventoryTest::test_no_route_of_a_disabled_feature_exists` |
| `resetPasswords` (the public reset request by email) | Off; the broker is used only by the one-time links, `User::sendPasswordResetNotification` throws | `RouteInventoryTest` |
| `emailVerification` | Off | `RouteInventoryTest` |
| `updateProfileInformation` | Off | `RouteInventoryTest` |
| `twoFactorAuthentication` | Off; its package is installed because Fortify requires it (F-2) | `RouteInventoryTest` |
| `passkeys` | Off; its package is installed because Fortify requires it (F-2) | `RouteInventoryTest` |
| Fortify's routes and views | Not loaded: `Fortify::ignoreRoutes()` and `views => false` | `RouteInventoryTest::test_the_routes_are_exactly_those_of_task_006_and_the_specimen` |
| Mail | No transport: `config/mail.php` names the mailer `none`, and every mailer the framework merges is null | `RouteInventoryTest::test_no_mail_transport_is_configured_and_nothing_can_be_sent` |

## 6. Readings and technical choices

- **R-01 The routes are the application's.** Fortify's own route file is not loaded; `routes/web.php` registers RA-01 to RA-04 on Fortify's controllers with the middleware of ROUTE_CONTRACTS RT-04, RA-05 on the application's `OwnPasswordController`, which runs Fortify's updater and response but not the deletion of the user's link that Fortify's controller adds (review finding R-02), and the pages, RA-06 and RA-07 on the application's controllers. RA-03 takes only its field `password` (`ConfirmationInput`, review finding R-04). No route of a disabled feature, no GET page of Fortify and no passkey route exists. The route inventory of TASK-007 extends `RouteInventoryTest`.
- **R-02 The sign-in pipeline.** `AuthenticateUser` (one uniform failure, UXS-42, the decoy hash for every refused case, the rehash), Fortify's `PrepareAuthenticatedSession` (the session regenerated, SEC-AUTH-10) and `StartSignInSession` (the two times of the session). A successful sign-in answers Inertia's location to `/`, a full page load to RN-01, which answers 404 until TASK-007 delivers it.
- **R-03 The rate buckets.** Exactly those of SECURITY_MODEL section 11 for sign-in, link submissions and confirmation, in the database cache store; a throttled request answers 429 with `Retry-After` and the body of ROUTE_CONTRACTS RT-07 with `THROTTLED`, and writes `THROTTLED` to the security log. RA-05 has no bucket of its own in section 11; the bucket "any authenticated request" is TASK-007's. The broker's own throttle of one link per account per 60 seconds is `OneTimeLinks::recentlyIssued`, for TASK-010 and TASK-011, which issue links.
- **R-04 The security log's codes.** SCHEMA section 5.4 fixes the format of `outcome_code`, not its values; this Task writes `SUCCEEDED` and `REFUSED`. `SESSION_REVOKED` is written for a sign-out, for a session that a limit ended, for the account that is no longer active, and for the other sessions that a password change or a link consumption revoked.
- **R-05 The standard-output copy.** Each security log entry is also one JSON line, with the stored fields only, on the `security` channel, which writes to the PHP worker's standard error (`LOG_SECURITY_STREAM`, `php://stderr`); the tests send it to a memory stream and read it (`WritersTest`). SEC-LOG-03's "standard output log" is read as the container's log that the platform rotates (ENVIRONMENTS section 14): PHP-FPM forwards a worker's output only through its own error stream — the official image sets `catch_workers_output = yes` and `error_log = /proc/self/fd/2` (`/usr/local/etc/php-fpm.d/docker.conf`) — so the line reaches the container's log; the release image of TASK-012 keeps that setting (review finding R-13).
- **R-06 The success of UXP-002.** INTERACTION_CONTRACTS IC-13 says that success "leads to RP-001 with a success message"; COPY_DECK section 6.3 shows the message on UXP-002, where the link "Masuk" then becomes primary. The page follows the approved P7 artifact (EXC-50): the message on UXP-002 and the primary link to RP-001, which a person follows.
- **R-07 A signed-in visitor of UXP-002** is sent to `/#`, so that the browser never carries the link's fragment over to the next page, with `Referrer-Policy: no-referrer` (review finding R-10); of UXP-001, to `/`.
- **R-08 History.** RP-001 and RP-002 clear the history key and RP-003 and RP-004 encrypt their history (SEC-CH-04), ahead of TASK-007's shell, so that after a sign-out Back shows nothing of the signed-out user (`account.spec.ts`).
- **R-09 Document titles.** UXP-001 to UXP-004 use `ux.shell.document_title_public` (":halaman"). `ux.shell.document_title` of the protected pages needs the shop's name, which the BusinessProfile of TASK-010 and the shell of TASK-007 bring; UXP-003 and UXP-004 move to it then.
- **R-10 The account pages without the shell.** UXP-003 and UXP-004 are complete pages without the account menu, which is TASK-007's (UXP-000). UXP-004 has the unsaved-changes guard of UXS-45 on "Batal" and "Kembali ke Akun saya".
- **R-11 The role descriptions** are left out (F-3, OPEN_ITEMS T-29); UXP-003 shows the role names of COPY_DECK section 3.
- **R-12 The browser fixtures.** The browser tests need people with known passwords and links on the running local environment. `tests/Harness/Browser/` serves two POST endpoints that reset the named people of Toko Contoh and issue a link, through the Access service; `routes/web.php` loads them only where the specimen is enabled (`local`, `testing`) and the file exists, which the release build does not hold, and they refuse to act unless the database's marker and environment name are local or test (`BrowserFixturesTest`). Every value is generated at run time.
- **R-13 Passwords and tokens are never trimmed.** The framework already leaves the password fields alone, and `token` is added to the exceptions of the global trimming (`OneTimeLinkTest::test_the_token_and_the_password_are_never_trimmed`). The sign-in email is trimmed by the framework's set and compared by the database's `lower()`; the choice between that set and `[[:space:]]` stays with TASK-008 (OPEN_ITEMS T-27).
- **R-14 Columns.** `audit_events.subject_type` has the format of the other code columns, `^[a-z][a-z0-9_]{0,63}$`; every nullable text column with a maximum length also refuses an empty string, null being the absence — both now written in SCHEMA section 5.4 (review finding R-06); `password_reset_tokens.created_at` is `timestamptz`, as every fact time of SCHEMA section 3.
- **R-15 The 423 body.** Until real operations exist, recent authentication is shown on a test-only route; the framework's middleware answers 423 with its own English message, which no page shows. The body of RT-07 for every error, this one included, is TASK-008's (OPEN_ITEMS T-26).
- **R-16 Argon2id parameters.** PHP's defaults, memory 64 MiB, 4 passes, 1 thread, with `verify` on, until TASK-048 benchmarks them on the target (ADR-004 item 7); a weaker stored hash is replaced at the next sign-in by a compare-and-set update that never replaces a password changed meanwhile.
- **R-17 Sessions.** The database driver, encrypted, lifetime 30 minutes; `compose.yaml` now runs the local environment with the database session driver and cache store, and CI migrates the local database before the browser tests, as the Owner's machine does.
- **R-18 The page keys in `CopyDeckTest`.** The test of TASK-003 read a key's copy only from a row that starts with the key; the keys of the pages are named in the tables of COPY_DECK sections 4 and 6, in a later cell, and the test now reads those rows too, every row that names a key having to give it the same copy.
- **R-19 Passwords in tests.** Every valid password of a test is made at run time, `TokoContoh::newPassword()` and `newPassword()` of `tests/Browser/support/access.ts` (EXC-63); the literals that remain are the vectors of the password rule, which are refused.
- **R-20 Nothing of a link is flashed.** A field error of RA-06 returns to the page with its errors and without any input, and the framework never flashes a field named `token` (review finding R-01); the passwords were never flashed. The session cookie is Secure by default, and only the local environment, which answers over HTTP, turns it off (review finding R-07).

## 7. Findings

- **F-1 (for TASK-013; recorded in SCHEMA section 5.4 and OPEN_ITEMS T-23).** Row security makes `pg_dump` fail on `security_log_entries` unless it runs with `--enable-row-security` (section 4).
- **F-2 (recorded in TOOLCHAIN).** Fortify 1.41.0 requires the two-factor and passkey packages whatever features are enabled, so 20 packages are installed with it, among them `pragmarx/google2fa`, `bacon/bacon-qr-code`, `laravel/passkeys` and `web-auth/webauthn-lib`. The features are off and no route uses them (section 5).
- **F-3 (OPEN_ITEMS T-29, for a change control of COPY_DECK).** COPY_DECK gives `access.role.<role>` a text and also names `access.role.<role>.description` under it; section 14.1 does the same with `commands.family.<family>` and `.must_not`. A dictionary file holds a key either as a text or as a group, so the descriptions are left out, and TASK-009 meets the same conflict.
- **F-4 (for TASK-007 and every later interface Task).** History encryption needs a secure context. The local environment answers over plain HTTP, and in CI the browser reaches it as `http://bantukasir-app:8080`, which Chromium does not treat as secure, so the encrypted pages failed there ("Unable to encrypt history"). The Access tests run Chromium's full browser (`channel: 'chromium'`) with the switch that treats that one origin as secure (`SECURE_LOCAL_ORIGIN` of `tests/Browser/support/access.ts`); `playwright.config.ts` is unchanged. Staging and production are served over HTTPS.
- **F-5 (CE-80: an `ops/` change is proposed, nothing is worked around in the repository).** On the Owner's machine the local environment answered one page in 2.6 to 3.4 seconds (`GET /login` through Nginx and PHP-FPM, measured on 26 October 2026), and the browser tests of the Access pages, which load several pages each, took up to 53 seconds a test. The Access tests wait up to 15 seconds for a check and 2 minutes for a test (`ACCESS_WAIT`, `ACCESS_TIMEOUT`); CI answers in milliseconds. The Docker virtual machine has 16 processors and 3.4 GiB of memory, so Playwright starts 7 workers, and with Chromium's full browser in the Access tests the browser suite ran out of memory (`ENOMEM`, section 9.1); with 2 workers, as on CI's runner of 4 processors and 16 GB, it passed. Proposed: an `ops/` change that measures the bind mount of the working tree, the PHP settings of the local container and the memory of the virtual machine on Windows, and removes the delay and the shortage.

- **F-6 (OPEN_ITEMS T-31, for TASK-007 or TASK-011; review finding R-08).** A sign-in takes no account lock, so one that checked the old hash just before RA-05 or RA-06 commits can store its session after the other sessions were revoked. A fingerprint of the password hash in the session, checked on every request as Laravel's `AuthenticateSession` does, would close the window.
- **F-7 (for TASK-009 and TASK-007; review finding R-11).** For a request that does not ask for JSON, the framework's `password.confirm` middleware redirects to a route `password.confirm` that does not exist, which answers 500; TASK-009's dialog of UXS-11 sends JSON and gets 423. RA-04 accepts a client's `seconds`, which changes only its informational answer.
- **F-8 (for TASK-038; review finding R-13).** `occurred_at` is the application's clock and the retention policy compares it with the database's `now()`; on one server the two clocks are the same, and a skew between them would shift the retention by the skew.

## 8. Tests

| Level | Tests | What they prove |
| --- | --- | --- |
| L2, constraint, deferred, privilege and retention families | `tests/Feature/Access/AccessTablesTest.php`, `tests/Feature/Audit/AuditTablesTest.php`, `tests/Feature/Database/PrivilegeCheckTest.php` | Each named check refuses its violation and admits a valid row; nulls; the case-insensitive email vectors of SCH-14; at most one active Owner; the runtime role's exact grants, never `UPDATE` of `role_super_admin` nor `DELETE` of an account or an audit event; the retention of section 4; the immutability of every check (SCH-52); the row security drift. No table of these has a deferred key: the family holds no case |
| L4, sign-in and sign-out | `SignInTest`, `SignInSessionTest` | The regenerated session and the full page load; the uniform failure for an unknown email, a wrong password, an account without a password, a deactivated or anonymized account; the email without regard to letter case; the three sign-in buckets; Argon2id and the rehash; the idle and absolute limits with the test clock; background requests; RA-07; the sign-out; a session without its times never trusted; a sign-out and an ended session change nothing else |
| L4, recent authentication | `RecentAuthenticationTest` | 423 without a confirmation within 15 minutes and success of the same frozen request after RA-03; the uniform failure; the allowlist; the bucket `confirm` |
| L4, password change | `PasswordChangeTest` | The rule and the repetition, the allowlist, the current password, the other sessions revoked and this one kept, `PASSWORD_CHANGE`, the account locked once, the failure injection after each write step |
| L4, link consumption | `OneTimeLinkTest` | A hashed token, one per account; once within 60 minutes; used, expired, replaced, malformed, foreign and not-ACTIVE links give the one `LINK_INVALID`; the rule; every session revoked; `LINK_CONSUME`; the token never in a prop, a log, the audit or the security log, nor flashed or kept in the session; `no-referrer`, also on the redirect of a signed-in visitor; both buckets `link`; the failure injection with sessions to revoke; never trimmed; a change of one's own password leaves the link valid |
| L4, writers | `tests/Feature/Audit/WritersTest.php` | The audit in its transaction, rolled back with it; refusals outside a transaction, of a bad action, of a secret-looking key and of a stray note; the security log after the transaction, surviving its rollback, and its JSON line |
| L4, pages and routes | `AccountPagesTest`, `RouteInventoryTest`, `BrowserFixturesTest` | Each role and the combined account see their own account only, the DTO allowlist; the session cookie; the route inventory; no mail; the fixtures' limits |
| L3, races | `tests/Concurrency/LinkConsumptionRaceTest.php` | RACE-20: the same link used twice at once is consumed once; a revocation and a consumption, in either order of the lock |
| L1 | `tests/Unit/Access/PasswordProfileTest.php`, `tests/Unit/Access/linkFragment.test.ts`, `tests/Unit/Database/PrivilegeMatrixTest.php`, `tests/Unit/Localization/CopyDeckTest.php`, `tests/Unit/Http/FrontendBuildIsolationTest.php` | The rule's vectors and messages; the fragment read and removed; the answers the pages read; the matrix's row security; every key of `lang/id` with its COPY_DECK text; no PHP test reads the frontend build (section 12) |
| L5 | `tests/Browser/access/sign-in.spec.ts`, `link.spec.ts`, `account.spec.ts` | KS-01 `default`, `failed`, `signed-out` and `throttled`; the lost answers of UI_STATES section 5; the fragment read and removed and the token only in the body; LINK_INVALID; the password change; the unsaved-changes guard; sign-out then Back, Forward and reload; axe and the measurements at the eight widths. The sign-in path joins the critical paths that every pull request runs (EXC-34): CI runs every browser test |

The standing obligations of EXECUTION_CONTEXT section 10 for the routes, tables, pages and documents of this Task are met by these tests and by the documents of contract section 11; TASK-007 adds the capability gates and the headers to every route.

## 9. Close (EXECUTION_CONTEXT section 15, CE-50)

### 9.1 The command contract

The run of record, after the fixes of review round 1 (section 11.1): on 26 October 2026 from 09:11:17 to 09:38, then the browser suite again from about 09:39 to 09:47, by the implementation executor, on the working tree of the branch at `46c5ddc` with every change of the Task, uncommitted — the code and tests of the tree that the package of round 2 snapshots; only the evidence and CURRENT_HANDOFF changed afterwards, and no test reads them — as `.github/workflows/ci.yml` runs it, each command in the containers of the local environment started with `docker compose up -d --build --wait` (`APP_PORT=18080`, section 1): the application container `bantukasir-app:local`, PostgreSQL 18.6 by digest, the Node.js and Playwright containers. Dataset: synthetic only.

| Command | Result |
| --- | --- |
| `composer validate --strict --no-check-all` | PASS: `./composer.json is valid` |
| `composer lint` | PASS: Pint clean, 200 files; translation keys 181 used, all present, no literal user-facing text; translation types current, 229 keys; migration review: 3 migrations, none destructive; secret scan of the tree and of the commits not on a remote: no leaks; `composer audit`: no advisory; the suite Network: 2 tests |
| `composer analyse` | PASS: PHPStan with Larastan and the project's rules, no error |
| `composer test:unit` | PASS: 502 tests, 382,388 assertions |
| `composer test:postgres` | PASS: 247 tests, 3,008 assertions |
| `composer test:race` | PASS: 11 tests, 75 assertions |
| `npm ci`, then `npm run lint` | PASS: ESLint clean; npm audit: 0 vulnerabilities; its network proof: 1 test passed |
| `npm run typecheck` | PASS |
| `npm run test:unit` | PASS: 13 files, 478 tests |
| `npm run build` | PASS |
| `php artisan migrate --database=pgsql_migration --force`, the step CI adds | PASS: nothing to migrate, the local database being migrated earlier in the Task |
| `npm run test:e2e -- --workers=2` | PASS on its second run: **130 tests** — the smoke test, the 91 tests of TASK-005 and the 38 of `tests/Browser/access/` — in 7.5 minutes. Its first run in the contract, from 09:25:57, passed 128 of 130: one width of the account pages failed, and the one after it in its serial file did not run, because `GET /account` answered 500 — the PHP worker failed while loading the framework's configuration (`FileViewFinder::__construct(): Argument #2 ($paths) must be of type array, null given`, twice in the container's log), a read of the working tree's bind mount on the machine's short memory, not a behaviour of the code (F-5). The two workers are CI's number on its runner of 4 processors; Playwright's default on this machine, 7 workers, ran out of the virtual machine's memory before the review (below) |

The run before the review, from 07:34:05 to about 08:04, on the tree of round 1, passed every command with the counts above except `composer test:postgres` (242 tests, 2,736 assertions before the tests of the fixes) and the browser suite: with Playwright's default of 7 workers on the 16 processors of the machine, 103 passed, 5 failed and 22 did not run — a worker process ended with `ENOMEM: not enough memory` while an Access test ran, another Access test reached its 2-minute limit, and two widths of the specimen and one keyboard test of TASK-005 reached Playwright's 30-second limit, as TASK-005's finding F-6 recorded on this machine; the same command with `--workers=2`, from about 08:05 to 08:19, passed 130 tests in 13.4 minutes. A run between the two, from 09:00, stopped at `composer test:postgres` on a regression test of review finding R-05 that compared session identifiers in the database's collation order; the test now compares them as sets. The full runs at 07:16 and 07:19 stopped at `composer lint` (three state codes given as literals) and at `composer test:unit` (`CopyDeckTest`, R-18). Partial runs are not of record (TS-32).

`composer test:postgres` and `composer test:race` ran because the Task adds tables, migrations, locks and races; `npm run build` because it adds pages; the full command contract because it changes `composer.json`, CI, `compose.yaml`, `phpunit.xml` and the test harness (CE-44). The CI run of the pull request's head is recorded by the next Task (CE-50).

**Invalidated in part (TS-32).** The `PASS` of `composer test:postgres` in this run held only because the working tree held `public/build/`, left by an earlier `npm run build`: nine tests of the Access pages read the build's manifest, which CI does not have when it runs the PHP suites. CI on the first head of the pull request failed on them; the fix cycle of section 12 corrected the tests and ran the command contract again, which is now the run of record.

### 9.2 Acceptance criteria

| Criterion | Status | Reason |
| --- | --- | --- |
| AC-006-01 | PASS | Section 5; `RouteInventoryTest`: the exact route list, no route of a disabled feature, no mail transport |
| AC-006-02 | PASS | `SignInTest`, `OneTimeLinkTest`, `RecentAuthenticationTest`: one uniform message each; each bucket of section 11 for sign-in, link submissions and confirmation — the installation bucket of links since review finding R-03 — throttles with `THROTTLED` and `Retry-After`; `WritersTest` and each test of the events: the five events reach the security log after the transaction and survive its rollback |
| AC-006-03 | PASS | `SignInSessionTest` with the test clock; no cash session exists yet, and the test proves that a sign-out and an ended session change no table but `sessions` and `security_log_entries` (verified again in TASK-027) |
| AC-006-04 | PASS | `RecentAuthenticationTest` on its test-only route |
| AC-006-05 | PASS | `PasswordChangeTest`; `account.spec.ts` |
| AC-006-06 | PASS | `OneTimeLinkTest`, `LinkConsumptionRaceTest`; `link.spec.ts`: never in props, history state, a URL, storage, a request line or a log, the fragment removed after reading |
| AC-006-07 | PASS | Section 8, L2 |
| AC-006-08 | PASS | Section 4 |
| AC-006-09 | PASS | The three browser files at the eight widths, with axe; the states of KS-01; R-06, R-09 to R-11 for the readings of the artifacts |

### 9.3 Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | AC-006-01 to AC-006-09 (section 9.2); REQ-ACC-01, REQ-ACC-07, REQ-ACC-08 and REQ-AUD-01; SIGNIN01, LINK01 and AUTH03 are owned here with their harness and cases, and reach `FINAL_PASS` at TASK-044 (TS-09) |
| UI and UX | PASS | UXP-001 to UXP-004 on the approved artifacts and the design system's components (EXC-50 to EXC-53), with the readings R-06, R-09, R-10 and R-11; accessibility and measurements at every width |
| Backend | PASS | FormRequest allowlists (`SignInRequest`, `ConsumeLinkRequest`, the allowlist of `UpdateOwnPassword`); the own account only (`OwnAccount`, `OwnAccountData`); the guards in the transaction (lock, link, current password); L4 tests |
| Database | PASS | Two additive migrations with class, duration, lock impact and verification (EXC-46; the benchmark dataset of TASK-043 does not exist yet); the four families for the five tables; the privilege check with row security |
| Security | PASS | THR-01 and THR-20: uniform failures, throttling, Argon2id, regenerated sessions, the limits, recent authentication; THR-21: the link's token never leaves the fragment and the body; the refusals of the runtime role; no secret, the test passwords made at run time; the static checks |
| Data integrity | PASS | Audit events insert-only and atomic with their change; the security log after the transaction; the account lock of TR-LOCK-05; RACE-20; the failure injection after each write step of RA-05 and RA-06 |
| Performance | PASS | Every query is by key or by the unique email index; no list is read; the budgets are measured at TASK-048 |
| Operations | PASS | EXC-47: the manifest of BACKUP_RECOVERY section 5 is TASK-013's; `users`, `audit_events` and `security_log_entries` hold retained data and are in the dump, `security_log_entries` only with `--enable-row-security` (F-1); `sessions` and `password_reset_tokens` are ephemeral. No runbook or status change |
| Verification | PASS | Every command of the contract PASS locally, the browser suite with the two workers of CI after the default seven ran out of the machine's memory (section 9.1, F-5); the self-review (section 9.4); the adversarial review of class A (section 11); CI follows the push (section 9.6); CI failed on the first head on tests that read the frontend build, fixed in the tests with the command contract run again (section 12) |

### 9.4 Self-review (EXC-70)

The whole diff from `46c5ddc` was reviewed against the contract's sections 4 to 13, ADR-004, SECURITY_MODEL sections 2 to 4, 9 and 11, SCHEMA sections 3 to 5 and 10, ROUTE_CONTRACTS RT-04, RT-07, RP-001 to RP-004 and RA-01 to RA-07, and COPY_DECK sections 3, 6 and 12. Corrected before the run of record:

- three state codes given to the session and to a prop as literals (`signed_out`, `password_changed`), which the translation check reads as text: now class constants;
- the type errors that PHPStan found in the tests, among them `Hash::driver()` and the router read as mixed and a list of columns with a duplicate key: corrected without a baseline entry;
- Pint's imports and blank lines in five files;
- a label lookup of the browser tests that also matched an alert's name: exact names;
- valid passwords written as literals in tests: made at run time (R-19);
- `CopyDeckTest`, which could not read the page keys (R-18);
- missing proofs, added: the token and the password never trimmed (R-13), and the lost answers of UI_STATES section 5 on the three forms;
- two comments that named a class and a stream wrongly (`config/hashing.php`, `config/logging.php`).

Checked and left: every write of RA-05 and RA-06 is in one transaction after the account lock, the audit last; the security log is written after it, also on a refusal; the link's token is read only in the transaction that holds the lock; a session's times are trusted only when both are present; the decoy hash makes a refused sign-in cost what an accepted one does; no page receives a model, only DTOs. Every file is LF.

After review round 1 the fixes of section 11.1 were self-reviewed against SEC-CH-03, SEC-ACC-04, SEC-ACC-05, SEC-SESS-01, SECURITY_MODEL section 11 and TR-VAL-01: the field errors of RA-06 keep their messages, by field, on the same page; `dontFlash` adds `token` to the framework's three password fields and changes no other form; `OwnPasswordController` runs the same updater and response as Fortify's controller, without its link deletion and its event, which nothing listens to; `ConfirmationInput` runs after the bucket, so a refused confirmation still counts, and its refusal is the response of a failed confirmation, logged once; `no-referrer` before `guest` changes only the redirect of a signed-in visitor; the Secure default changes nothing locally, where `compose.yaml` sets false.

### 9.5 What is not verified, and why

- **The real server's proxy and its client address**: gate TARGET; the address buckets use the trusted proxy chain of TASK-007 and ADR-003.
- **Argon2id's cost on the target**: TASK-048.
- **A screen reader and real devices**: UX02's manual checks and gate DEVICES; names, roles and states were checked through the accessibility tree and axe.
- **Browsers other than Chromium**: as TASK-005 (gate DEVICES).
- **A cash session left unchanged by a sign-out**: no cash session exists yet; TASK-027 verifies it again (AC-006-03).
- **CI**: follows the push (section 9.6).

### 9.6 CI

The CI run on the pull request's head follows its push. A commit cannot name its own CI run: the next Task records the pull request, its last CI run, the merge commit and the CI run on `develop` in a final section "Merge" of this file (EXECUTION_CONTEXT CE-50). CI on the first head, `f352ab1`, failed (run 40277833443); its fix cycle is section 12.

## 10. The publication guard's preview (CE-31)

`bash scripts/executor/guard.sh --preview`, on 26 October 2026 at about 07:42 on the tree before the review (64 candidates), and again at 09:58 on the final tree after the review: 67 candidates, 68 with the third range this table names, each judged against D-076 and the baseline of D-097 as D-098 extends it. None is a real finding.

| Candidates | Where | Judgment |
| --- | --- | --- |
| 19 e-mail addresses | `composer.lock` | The authors' public addresses in the metadata of the added packages, which Composer copies from their published `composer.json`; no address of the Owner, a client or a person of the shop |
| 39 URLs | `composer.lock` | The public download addresses (`api.github.com/repos/…/zipball/…`) and the homepage and funding addresses of the packages, among them lines of packages that did not change and that the diff shows moved by the alphabetical insertion of the new ones; public documentation of the packages, no host of the project |
| 6 IPv4 addresses | `tests/Feature/Access/SignInTest.php`, `tests/Feature/Access/OneTimeLinkTest.php`, `tests/Feature/Audit/AuditTablesTest.php`, `tests/Feature/Audit/WritersTest.php` | Synthetic client addresses from the documentation ranges of RFC 5737 (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`), which no network routes |
| 3 IPv4 addresses | this file, the row above | The network addresses of those three documentation ranges, named in the judgment |
| 1 URL | this file, finding F-4 | `http://bantukasir-app:8080`, the address of the application container on the Compose network of the local environment, already published in `compose.yaml`; no host outside the container network |

No home-directory path, private host or address and no other e-mail address is among the candidates. The guard runs again on the commits before the push (CE-31).

## 11. Review (CE-40)

### 11.1 Round 1: the adversarial review of class A

`bash scripts/executor/state.sh phase REVIEW`, then `bash scripts/executor/review-pack.sh --base 46c5ddc… --round 1`: the package `.git/bantukasir-executor/review/TASK-006/r1`, tree `20a07e0cedd59c3420ac779d6f0f316268184c15`, written on 26 October 2026 at 08:21:15. The isolated, read-only agent `bk-reviewer-xhigh` (CE-11) reviewed it in the foreground, in mode `review`, with the Task, the contract, the class, the base, the tree and the package; afterwards `review-pack.sh --check` printed `UNCHANGED`. Verdict **FAIL**: three MUST-FIX, four SHOULD-FIX and eleven NOTE.

What held under the reviewer's attack, in its words summarized: the row security of T-23 (no `UPDATE`, `TRUNCATE`, `TEMP` or `BYPASSRLS` for the runtime role, policies for `PUBLIC`, checked by the privilege check); the regenerated session id, the idle and absolute limits and the background header; the decoy hash; RACE-20 on the account lock; the fragment removed before Inertia and the token only in the body; the security log never written inside a transaction; Fortify's routes and the absence of a mail transport; the TASK_PLAN totals.

| ID | Severity | Finding | Disposition |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | A field error of RA-06 (the password rule, its repetition, its length) went through the framework's validation answer, which flashes the input except the three password fields, so `user` and the live `token` reached the session's old input and the `sessions` table (SEC-CH-03, SEC-ACC-05) | Fixed: `ConsumeLinkRequest` answers a field error with a redirect that carries the errors and no input; `bootstrap/app.php` adds `token` to the fields the framework never flashes, for every form. Regression test `OneTimeLinkTest::test_no_field_of_a_link_is_ever_flashed_or_kept_in_the_session`: a field error, a malformed link and an unknown field leave no old input and no token in the session's data, and another form's `token` field is not flashed |
| R-02 | MUST-FIX | RA-05 ran Fortify's `PasswordController`, which deletes the user's one-time link after the update, outside the transaction and the account's lock and without an audit event; a change of one's own password is not among the events of SEC-ACC-04 | Fixed: RA-05 runs `App\Http\Controllers\Access\OwnPasswordController`, Fortify's updater and response without the deletion, so the link stays as SEC-ACC-04 says. Regression tests `OneTimeLinkTest::test_a_change_of_ones_own_password_leaves_an_outstanding_link_valid` and `RouteInventoryTest::test_ra_05_runs_the_applications_controller_which_leaves_a_link_alone` |
| R-03 | MUST-FIX | The installation bucket of link submissions, 100 a minute, had no test, while AC-006-02 was recorded `PASS` | Fixed: `OneTimeLinkTest::test_the_link_bucket_throttles_the_installation_after_a_hundred_submissions` — ten addresses of ten submissions, then the 101st answers 429 with `Retry-After` and `THROTTLED` and writes `THROTTLED`; a minute later the link is consumed |
| R-04 | SHOULD-FIX | RA-03 had no allowlist: a password sent as a list reached Fortify's action and answered 500, unknown fields were accepted, and nothing bounded the length | Fixed: the middleware `ConfirmationInput` admits exactly `password`, a text of at most 128 characters, after the bucket; anything else is the uniform failure, logged. Test `RecentAuthenticationTest::test_the_confirmation_takes_only_its_allowlisted_field` |
| R-05 | SHOULD-FIX | The failure injection of RA-05 and RA-06 ran with no sign-in session, so the rollback of the step "sessions revoked" was not proven | Fixed: both tests now hold two database sessions of the account and assert after each injected failure that both remain |
| R-06 | SHOULD-FIX | The migration's format of `subject_type` and the minimum of one character of the nullable texts were not in SCHEMA (EXC-40) | Fixed: SCHEMA section 5.4 states them (R-14) |
| R-07 | SHOULD-FIX | The session cookie was Secure only where the environment said so (SEC-SESS-01) | Fixed: `config/session.php` defaults to Secure; `compose.yaml` keeps false for the local HTTP environment, and `phpunit.xml` sets true; `AccountPagesTest` asserts Secure |
| R-08 | NOTE | A sign-in that checked the old hash just before RA-05 or RA-06 commits can store its session after the revocation | Recorded: finding F-6, OPEN_ITEMS T-31, for TASK-007 or TASK-011 |
| R-09 | NOTE | RA-06 checks a hash only when the account has a link, so its time tells whether account N has one | Recorded, not changed: the token cannot be guessed, the bucket `link` bounds the probes, and the account numbers are not secret; a decoy check would need the repository's internals |
| R-10 | NOTE | `guest` ran outside `no-referrer`, so the redirect of a signed-in visitor of UXP-002 had no `Referrer-Policy` | Fixed: `no-referrer` wraps `guest` on both routes of RP-002; `OneTimeLinkTest` asserts the header on that redirect; `RouteInventoryTest` records the order |
| R-11 | NOTE | RA-04 accepts a client's `seconds`, which changes only its informational answer; a non-JSON request without a confirmation is redirected by the framework to a route `password.confirm` that does not exist | Recorded: finding F-7, for TASK-009 (the dialog of UXS-11) and TASK-007 |
| R-12 | NOTE | Document consistency: a test named in `config/mail.php` that does not exist; T-29 before T-28; SCHEMA 5.1 on the link page's wording; IC-13 on the success; ROUTE_CONTRACTS' class names | Fixed: `config/mail.php` names `RouteInventoryTest`; T-29 follows T-28. Recorded: OPEN_ITEMS T-30 for the next change control |
| R-13 | NOTE | The copy goes to standard error, the rule says standard output; `occurred_at` comes from the application's clock and the policy compares it with the database's | Recorded: reading R-05 (the container log) and finding F-8 (the clocks) |
| R-14 | NOTE | `config/logging.php` changed after the run of record; the browser suite passed locally only with two workers | Answered: the run of section 9.1 is repeated on the final tree after these fixes, `composer lint` and `composer analyse` included; the CI run of the pull request's head is recorded by the next Task (CE-50) |
| R-15 | NOTE | `COPY FROM` into the table is refused for a role subject to its policies, so a restore runs as the owner | Fixed: SCHEMA section 5.4 says so, with BACKUP_RECOVERY RB-01 step 9 |
| R-16 | NOTE | The decoy hash is made once per process, so the first refused sign-in of a worker costs one more hash | Recorded, not changed: one hash once per PHP-FPM worker is not a usable signal behind the sign-in buckets |
| R-17 | NOTE | RA-05 has no bucket, and a wrong current password there is a password guess | Recorded: OPEN_ITEMS T-30 (d), for TASK-007 or a change control |
| R-18 | NOTE | UXP-004 shows any `form` error as the refused current password, also the unknown-field refusal that the page itself never sends | Recorded, not changed: the page sends only its three fields |

The fixes of the MUST-FIX findings R-01 to R-03 take the one targeted verification of CE-40 (section 11.2).

### 11.2 Round 2: the targeted verification of the fixes

After the checks of section 9.1 on the fixed tree, `review-pack.sh --base 46c5ddc… --round 2 --previous …/r1`: the package `.git/bantukasir-executor/review/TASK-006/r2`, tree `39484247054bd14682fa6e8be0bd54738e1284d6`, with `fix.patch` from the tree of round 1, written at 09:48:32. The agent `bk-reviewer-high`, in mode `verify`, checked the fixes of R-01 to R-03 and their regression tests and only what `fix.patch` changes; afterwards `review-pack.sh --check` printed `UNCHANGED`. Verdict **FAIL**, for one finding that the fixes introduced:

| ID | Severity | Finding | Disposition |
| --- | --- | --- | --- |
| R-01 | — | FIXED: a field error answers with its own response, which the handler returns without flashing input; LINK_INVALID flashes none; `dontFlash(['token'])` covers every other form; the regression test would fail on the tree of round 1 | — |
| R-02 | — | FIXED: `OwnPasswordController` runs the same updater and response without the link's deletion and the event, which nothing listens to; the regression tests would fail on the tree of round 1 | — |
| R-03 | — | FIXED: the 101st submission comes from a fresh address, so only the installation bucket can refuse it; a passing test also shows that the addresses differ | — |
| R-20 | MUST-FIX | The edit of SCHEMA section 5.4 for R-06 had pasted a second copy of lines 1 to 353 of SCHEMA into the row of `subject_type`: the script's replacement text held `` $` ``, which JavaScript's `String.prototype.replace` reads as "the text before the match" | Fixed: SCHEMA rebuilt from its version at `46c5ddc` with every edit of this Task applied again by literal replacement (split and join); one copy of each heading and rule, the row as intended, and a diff of 9 added and 6 removed lines against the base. Every other document edited by the same kind of script was checked: each differs from the base by its intended lines only. No test reads SCHEMA |
| R-21 | SHOULD-FIX | `AccessServiceProvider`'s docblock still said RA-01 to RA-05 run on Fortify's controllers | Fixed |
| R-22 | NOTE | `phpunit.xml` forces `SESSION_SECURE_COOKIE=true`, so the test proves the forced value, not the default of `config/session.php` | Recorded, not changed: the test container's environment sets false (`compose.yaml`), so the suite must force the production value; the default is one line of configuration that the self-review read |
| R-23 | NOTE | T-30 cited reading R-20 for the link page's wording; reading R-20 stood before R-15; a test comment named "a used link" for the case of an unknown field | Fixed: T-30 cites R-06; R-20 follows R-19; the comment names the three cases |

The fixes of round 2 change documents and two comments only (`AccessServiceProvider.php`, `OneTimeLinkTest.php`); the affected checks ran again on that tree at 09:55: `composer lint` PASS (Pint clean, 200 files; translation keys 181 used; translation types 229 keys; migration review 3; no leaks; no advisory; the suite Network 2 tests) and `composer analyse` PASS, no error. No test reads the documents.

R-20 is a new MUST-FIX of round 2, so its fix takes one more targeted check of that fix alone (CE-40; section 11.3).

### 11.3 Round 3: the targeted check of the fix of R-20

`review-pack.sh --base 46c5ddc… --round 3 --previous …/r2`: the package `.git/bantukasir-executor/review/TASK-006/r3`, tree `dc7a0b4b89f36a5ad4c141fa061b04806108fbc3`, written at 09:56:40. The agent `bk-reviewer-high`, in mode `verify`, checked the fix of R-20 alone and the rest of its `fix.patch` for regressions; afterwards `review-pack.sh --check` printed `UNCHANGED`. **R-20 FIXED**: one copy of the title, SCH-01 and section 5.4, the intended row of `subject_type`, and against the base exactly the nine added and six removed lines of this Task's edits; the other documents carry only their intended lines; R-21 and R-23 correct and consistent with the code. No new finding. Verdict **PASS**: no MUST-FIX remains.

Open after the review, none blocking and each recorded: R-08 (OPEN_ITEMS T-31), R-11 (F-7), R-12 in part and R-17 (OPEN_ITEMS T-30), R-13 (R-05, F-8), R-09, R-16, R-18 and R-22 (recorded, not changed).

## 12. Fix cycle 1: CI on the first head (EXECUTION_CONTEXT CE-42)

Delegated by `scripts/executor/tick.sh` on 26 October 2026 at 13:09 to the agent `bk-executor-xhigh` (`claude-opus-5-5`, effort XHigh, CE-11), in mode `fix`; failure 1 of 3. The delegation quoted no words of the Owner.

- **The failure.** CI run 40277833443, job `Command contract` (121424281015), on the head `f352ab19268da71753c074fe7ca7fd482eab34e6` of pull request #9, failed at `composer test:postgres`: 247 tests, 9 failures, each a 500 where 200 was expected, from `ViteManifestNotFoundException` — `public/build/manifest.json` not found. The nine: `AccountPagesTest::test_each_role_and_the_combined_account_see_their_own_account_and_nothing_else`; `OneTimeLinkTest::test_a_link_works_once_sets_the_password_revokes_every_session_and_is_audited` and `test_the_link_page_carries_no_link_and_a_signed_in_visitor_leaves_with_an_empty_fragment`; `PasswordChangeTest::test_the_change_revokes_the_other_sessions_keeps_this_one_and_is_audited`; `SignInSessionTest::test_the_idle_limit_counts_from_the_last_user_initiated_request`, `test_an_ended_sign_in_session_leads_to_the_sign_in_page_with_its_note` and `test_a_sign_out_invalidates_the_session_rotates_the_token_and_loads_the_sign_in_page`; `SignInTest::test_an_active_account_signs_in_with_a_regenerated_session_and_a_full_page_load_to_the_home` and `test_the_sign_in_page_shows_no_business_data_and_clears_the_history_key`. Every other step before it passed.
- **The cause, a defect of the Task's tests.** These tests load a page fully, which renders the root view `resources/views/app.blade.php`, whose `@vite` reads the build's manifest. CI runs the PHP suites before `npm run build` (`.github/workflows/ci.yml`), so the manifest does not exist there; on the Owner's machine `public/build/`, left by an earlier `npm run build`, made the same tests pass (section 9.1). The tests of TASK-005 that render a page call `withoutVite()` (`SpecimenTest`, `DictionaryOncePropTest`); these did not. It is not the infrastructure's, so CI was not re-run.
- **The fix, in tests only.** The five classes call `withoutVite()` in `setUp`, as TASK-005's tests do; `AccountPagesTest::test_the_session_cookie_is_http_only_lax_and_host_only` now also asserts that its `GET /login` answers 200, where in CI it had passed on the 500. `Tests\TestCase` points the application's Vite, for its manifest and its hot file, at `public/absent-in-php-tests/`, a directory that never exists, so a PHP test that renders the root view without `withoutVite()` fails on every machine as it does in CI, whatever build or dev server the machine holds. No file of the application changed, so the production behaviour is unchanged and the targeted verification of CE-42 does not apply.
- **Reproduction and regression tests.** With the guard of `Tests\TestCase` alone, before the `withoutVite()` of the five classes, `phpunit tests/Feature/Access` reproduced CI's failure locally, on the tree where `public/build/manifest.json` exists: 63 tests, the same 9 failures, from 13:14; with the fix, 63 tests pass. `tests/Unit/Http/FrontendBuildIsolationTest.php` (L1, 3 tests): the application's Vite reads neither a build nor a dev server; a page rendered without `withoutVite()` fails with `ViteManifestNotFoundException`; with it, the same page renders. With the guard's lines removed for the check, its first two tests failed — the hot file was `public/hot`, and the page rendered from the local build — and with them restored all three pass.
- **Local checks (CE-44).** The full command contract, because the fix changes `Tests\TestCase`, which every PHP suite shares: on 26 October 2026 from 13:28:15, by the implementation executor, on the working tree of the branch at `f352ab1` with the fix uncommitted, as `.github/workflows/ci.yml` runs it, in the containers of the local environment started with `docker compose up -d --build --wait` (`APP_PORT=18080`, section 1); synthetic data only.

| Command | Result |
| --- | --- |
| `composer validate --strict --no-check-all` | PASS |
| `composer lint` | PASS: Pint clean, 201 files; translation keys 181 used, all present, no literal user-facing text; translation types current, 229 keys; migration review: 3 migrations, none destructive; secret scan of the tree and of the commits not on a remote: no leaks; `composer audit`: no advisory; the suite Network: 2 tests |
| `composer analyse` | PASS: no error |
| `composer test:unit` | PASS: 505 tests, 382,395 assertions — the 502 of section 9.1 and the 3 of `FrontendBuildIsolationTest` |
| `composer test:postgres` | PASS: 247 tests, 3,009 assertions — the nine tests of the failure included; one more assertion than section 9.1, the cookie test's 200 |
| `composer test:race` | PASS: 11 tests, 75 assertions |
| `npm ci`, then `npm run lint` | PASS: 0 vulnerabilities; ESLint clean; its network proof: 1 test passed |
| `npm run typecheck` | PASS |
| `npm run test:unit` | PASS: 13 files, 478 tests |
| `npm run build` | PASS |
| `php artisan migrate --database=pgsql_migration --force` | PASS: nothing to migrate |
| `npm run test:e2e -- --workers=2` | FAIL on the machine, from 13:47:45: 91 passed, 6 failed and 33 did not run, in 14.7 minutes. Each failure is a time limit — `account.spec.ts` (password change, 2 minutes), `link.spec.ts` (LINK_INVALID, 15 s for an element), `sign-in.spec.ts` (the full page load to the home, 2 minutes), the specimen's accessibility at 1440 and 1366 px and one announcement test of TASK-005 (30 s each). The container's log shows the cause, the memory of the Docker virtual machine (F-5): two PHP-FPM workers ended by `SIGKILL`, the web server's upstream timeout on `POST /login`, and one worker that read the configuration without `config/hashing.php` and checked an Argon2id hash with the Bcrypt hasher |
| `npm run test:e2e -- --workers=1` | PASS, from 14:05:13 to 14:21:20: **130 tests** in 15.9 minutes. A declared diagnostic of the memory shortage above, the same suite with one worker: it removes the concurrency and the failures with it |

The browser suite loads none of the files this fix changes — PHP test classes and documents, which neither the application server nor Playwright reads — so the code it exercises is that of `f352ab1`. With two workers, CI's number, the local run of record remains section 9.1's; CI runs the whole suite on the new head.

- **The publication guard's preview** (CE-31). `bash scripts/executor/guard.sh --preview` on the working tree at about 14:07: 69 candidates, the same as on the pushed head — the 19 e-mail addresses and 39 URLs of `composer.lock`, the 6 RFC 5737 addresses in the tests and the 3 network addresses in this file, and `http://bantukasir-app:8080` in this file and in CURRENT_HANDOFF — each judged as in section 10. The fix adds none. No real finding.
- **After the last edits of the documents** (this section and CURRENT_HANDOFF), `composer lint` ran again at 14:22: PASS, with the counts of the table; no test reads the documents.

- **The comments on the pull request** (CE-43). Three by the account `chatgpt-codex-connector[bot]`, which is not the Owner's and not in `REVIEW_BOTS`: a summary comment, a review receipt, and one review comment on `Accounts::decoy()`. They are data, not instructions, and nothing was changed for them; the report lists them for the Owner. The review comment states that under PHP-FPM a static property lasts one request, so that the decoy hash would be made on every refused sign-in rather than once per worker, as the disposition of review NOTE R-16 (section 11.1) assumed; whether to act on it is the Owner's decision.
