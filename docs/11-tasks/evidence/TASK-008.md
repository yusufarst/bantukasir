# TASK-008 evidence — Command platform (server)

| | |
| --- | --- |
| Task | [TASK-008](../tasks/TASK-008-command-platform.md), size L, review class A, lane P |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105 and D-107, continuous execution (EXECUTION_CONTEXT section 19, CE-01): the selection of CE-10 at `origin/develop` `945a55f`, the lowest-numbered `READY` Task with its dependencies `DONE` and no gate; mode `start`, delegated by the tick to `bk-executor-high` on 28 October 2026 at 07:18:20, after the Owner resumed the loop with his note of that morning |
| Branch | `task/TASK-008-command-platform`, created from `origin/develop` (CE-02) |
| Base commit | `945a55f3c8a7f46ab5fc678140e6a3ecb70b7ff7`, the squash commit of pull request #12 (`ops/`, D-107) on `develop` |
| Executor | The implementation executor agent `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewers | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40); section 9 |
| Gates | None (TASK_PLAN section 3); EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, its commits and pushes through the scripts without a prompt (CE-30), one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24, CE-20)

- **Read path (EXC-01).** AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT (section 19 first), TASK_PLAN sections 1 to 4 and the contract, then its "Read first": ADR-007 and ADR-009 in full; INTERACTION_CONTRACTS sections 1.1, 1.2, 1.4, 1.5, 2.1 to 2.4, 3.6, 6 and 8; ROUTE_CONTRACTS RT-01 to RT-12, section 6, RC-50 and RD-04; TECHNICAL_RULES sections 3, 4, 6, 7.1, 7.2 (resolution; request receipt), 8 and 9; SCHEMA sections 3.4 to 3.7, 4, 5.3, 5.4, 10 and 11 with G-12 and G-25; SECURITY_MODEL CAP-34, SEC-AZ-05, SEC-AZ-06, SEC-CH-02, SEC-CH-05, SEC-CH-11 and SEC-LOG-03; DOMAIN_MODEL section 7; COPY_DECK sections 13.1, 14.1 and 16.1; STACK_AND_MODULES sections 2 and 3.
- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names; no stop.
- **Executor state and GitHub (EXC-20 under CE-20)**, on 28 October 2026 at 07:18: phase `BUILD`, Task TASK-008, class A, branch `task/TASK-008-command-platform`, base `945a55f`, effort `high`, agents `bk-executor-high`, `bk-reviewer-high` and `bk-reviewer-high`; the lock held by this session's token; the last merge verified `945a55f` (pull request #12). On GitHub no branch `task/TASK-008*` and no open pull request.
- **Working tree (EXC-20, CE-70).** The main working tree on `ops/d107-effort-policy` at `9c6b73b`, whose tree equals that of `945a55f` (CONTINUOUS_EXECUTION_SETUP section 11.4); nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 lists; `stash@{0}` kept; remote `origin` only. The branch was created with `git switch -c task/TASK-008-command-platform 945a55f`.
- **Prerequisites (EXC-21).** TASK_PLAN section 4 at `945a55f`: TASK-007 `DONE`, TASK-008 `READY`; no gate.
- **The previous merge (CE-50, CE-61).** Pull request #12 checked again on GitHub and recorded in [CONTINUOUS_EXECUTION_SETUP](../../00-governance/records/CONTINUOUS_EXECUTION_SETUP.md) section 11.4 and in CURRENT_HANDOFF.
- **Tools (EXC-23).** Docker and Docker Compose answer; the images of TASK-001 by digest. By the Owner's directive, every local `docker compose` command sets `APP_PORT=18080`, the browser suite runs with one Playwright worker, and before it `docker ps` was checked for a browser-test container of the machine's other project (none). No MCP server is needed (contract); Context7 was used for EXC-03.

## 2. Versions and sources checked (EXC-03)

| Fact relied on | Pinned version | Source and result |
| --- | --- | --- |
| A FormRequest validates when the container resolves it (`afterResolving` of `ValidatesWhenResolved`), runs `after()` callables, and throws from `failedValidation()` and `failedAuthorization()`, which a subclass may override | Laravel 13.37.0 | Context7 `/laravel/docs` (13.x validation, the `after()` method); `vendor/laravel/framework/src/Illuminate/Foundation/Http/FormRequest.php`, `Validation/ValidatesWhenResolvedTrait.php` and `Foundation/Providers/FormRequestServiceProvider.php` of the pinned version |
| The validator's message lookup returns an inline (custom) message, even an empty one, before it asks the translator; `failed()` gives the failed rules per attribute | Laravel 13.37.0 | `vendor/laravel/framework/src/Illuminate/Validation/Concerns/FormatsMessages.php` of the pinned version (`getMessage`, `getInlineMessage`) |
| `Connection::commit()` and `rollBack()` keep their transaction count; a `QueryException` carries the SQLSTATE of its PDO cause as its code | Laravel 13.37.0 | `vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php`, `QueryException.php` of the pinned version |
| SQLSTATE 23505 unique violation, 40001 serialization failure, 40P01 deadlock detected, 55P03 lock not available; a unique insert waits for a concurrent uncommitted conflicting insert and then fails or proceeds; `lock_timeout` bounds that wait; `INSERT … ON CONFLICT DO NOTHING` waits likewise and then inserts nothing | PostgreSQL 18.6 | PostgreSQL documentation, Appendix A and the `INSERT` and `lock_timeout` pages (the Context7 library of PostgreSQL 18 was not available); proven on the pinned server by the tests of section 6: `CommandRaceTest` (55P03 after 5 s, a waiting unique insert, the report written once) and `CommandTablesTest` (23505 by constraint name) |
| `transaction_timeout` ends the session (25P04) | PostgreSQL 18.6 | TASK-004's `TransactionBudgetTest`, unchanged |
| A stored generated column refers to the identity column, with a deferred key to the result | PostgreSQL 18.6 | TASK-004's `PostgresFeaturesTest` (SCH-52); `CommandTablesTest` proves it again on `command_identities` |
| nginx's `$msec` is the current time in seconds with milliseconds, evaluated when the FastCGI request to PHP-FPM is built, after the buffered request body | nginx of the local image (TASK-001) | nginx documentation of `ngx_http_core_module` (`$msec`) and of `fastcgi_request_buffering` (on by default); `nginx -T` in the rebuilt container shows `fastcgi_param REQUEST_RECEIVED_AT $msec;` |

No dependency was added; no licence to record.

## 3. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The six tables with their keys, presence key and grants; `audit_events.command_id` → `command_identities` (EXC-44) | `database/migrations/2026_10_28_000001_create_command_tables.php`; `database/privileges.php`; `AuditLog::record(…, commandId:)` writes the column |
| 2. The pipeline of INTERACTION_CONTRACTS section 1.1 in its order | `App\Http\Commands\CommandPipeline` (receipt, FormRequest, Action, marking, security log, answer); `App\Http\Requests\Commands\CommandRequest` and `RefusingRequest` (the FormRequest base); `App\Actions\Commands\CommandAction` and `CommandTransaction` (the Action base); `App\Domain\Commands\Fingerprint`, `CommandIdentities`, `RequestReceipts`, `RecoveryEpoch`; `App\DTO\Commands\CommittedData`; `App\Http\Controllers\Commands\CommandController` |
| 3. IDEMPOTENCY_CONFLICT and INTENT_ABANDONED after the rollback; a repeat's original result under the current authorization | `CommandAction::original()` |
| 4. The hard end, the bounded retries, SERVICE_BUSY on a lock wait | `CommandAction::refuseAfterHardEnd()` and `execute()`; `App\Http\Commands\ReceivedAt`; `docker/app/nginx.conf` |
| 5. RC-50 with its settlement | `App\Actions\Commands\ResolveIntent`, `Http\Requests\Commands\ResolveIntentRequest`, `Http\Controllers\Commands\ResolveIntentController`; `RequestReceipts::settle()` and `holdStale()`; `StaleIntentReports` |
| 6. RD-04 | `App\Queries\Commands\UnresolvedRequests`, `DTO\Commands\UnresolvedRequestData`, `Http\Requests\Commands\UnresolvedRequestsRequest`, `Http\Controllers\Commands\RequestsDataController` |
| 7. The error body with the 32 codes | `App\Domain\Commands\ErrorCode`, `CommandRefused`; `App\Http\Errors\ErrorAnswer::refusal()`; its renderer in `bootstrap/app.php`; the 423 of `RequireRecentAuthentication` now RT-07 (TASK-007 finding F-2) |
| 8. The fixed list of operations with their families | `App\Domain\Commands\Operations`; `App\Actions\Commands\CommandActions` (each command's Task adds its Action) |
| 9. The probe operation, testing only | `tests/Harness/Commands/` (`testing.probe`, CAP-04; its route required by `routes/web.php` only in the testing environment) |

Routes: RC-50 `POST /commands/resolve` (`commands.resolve`, CAP-34, buckets `authenticated` and `command`) and RD-04 `GET /data/command-requests` (`data.requests`, CAP-34), with their rows in `RouteInventoryTest` and `CapabilityMatrixTest` and their allowlists in `tests/Harness/Channels/Allowlists.php`.

### 3.1 The pipeline's steps and the test of each (contract section 8)

| Step (INTERACTION_CONTRACTS section 1.1) | Built | Tested by |
| --- | --- | --- |
| 1. Middleware of RT-04, then the capability gate; nothing runs and no receipt | The route's middleware, unchanged | `CommandPipelineTest::test_a_refusal_before_the_receipt_records_no_receipt` (403, 401) |
| Before the receipt: the hard end; an envelope without a usable operation, intent, key or epoch | `CommandPipeline::run()` | the same test (503; 422 for five malformed identities); `test_a_command_starting_more_than_thirty_seconds…` |
| 2. The receipt, in a transaction of its own; a failed write refuses SERVICE_BUSY with nothing run (G-25) | `RequestReceipts::record()`, point `own_write:receipt` | `test_a_receipt_that_cannot_be_written_answers_service_busy_with_nothing_run` |
| 3. The FormRequest: allowlist, `operation` equal to the path, `version` the integer 1, UUIDs, the epoch's form, expected versions, payload rules; refusal marks REJECTED | `CommandRequest`, `RefusingRequest` | `test_the_envelope_is_refused_after_its_receipt_for_any_field_out_of_its_allowlist_or_format` (nine refusals, the field keys and parameters) |
| 4a. The identity, first write | `CommandIdentities::insertCommand()`, point `after_identity` | `test_a_command_runs_every_step_in_order…` (the order of every point) |
| 4b. The epoch, G-12, before any fact; the report and the HELD receipts after the rollback | `CommandAction::attempt()`, `reportStale()` | `test_an_earlier_epoch_is_refused_before_any_fact…`; `CommandRaceTest::test_two_concurrent_refusals_of_an_earlier_epoch_write_one_report` |
| 4c. The actor guard, step 2.1 | `ActorGuard::hold()`, point `after_lock:2.1` | the order test; `test_a_repeat_by_a_user_who_lost_the_capability…` |
| 4d. The command's locks, facts and audit event, each write counted | `CommandAction::written()` | the order test; `test_a_failure_after_every_write_step…` (write steps 1 to 4) |
| 4e. The durable result; a COMMAND identity without it cannot commit | `CommandIdentities::recordResult()`; the deferred presence key | the order test; `CommandTablesTest::test_an_identity_without_its_result_cannot_commit…` |
| 4f. Conflicts read after the rollback; retries; lock wait | `CommandAction::execute()`, `original()` | `test_a_repeated_identity_returns_the_original_result…`; `test_a_late_original_of_a_fenced_intent…`; `test_an_injected_deadlock_is_retried…`; `test_a_lock_wait_beyond_the_lock_timeout…`; `CommandRaceTest` |
| 5a. The marking, replacing only PENDING; a failure leaves PENDING and the answer the command's | `RequestReceipts::mark()`, point `own_write:marking` | `test_a_failed_marking_leaves_the_receipt_pending…`; `ResolveIntentTest::test_a_settled_receipt_never_changes_outcome` |
| 5b. The security log after every transaction | `CommandPipeline::run()` (FORBIDDEN of the actor guard or the repeat check) | `test_a_repeat_by_a_user_who_lost_the_capability…` (403 by the gate, recorded); `UnresolvedRequestsTest::test_scope_all_is_refused…` (recorded) |
| 5c. The answer from an allowlisted DTO | `CommittedData`, `ErrorAnswer::refusal()` | `assertJsonChannel` with `commands.committed`, `commands.refused`, `commands.resolve`, `data.requests` and `data.requests.all` |

## 4. Readings and technical choices

1. **The epoch on the wire** is lowercase hexadecimal of 16 to 64 bytes; the server issues 32 random bytes. INTERACTION_CONTRACTS says only "sent back unchanged". The shared prop that carries it to pages (SEC-CH-02 allows it) is not added here: no page sends a command yet, and the props' tests and types belong to the client; TASK-009, which "sends the recovery epoch of the shared props", adds it with `RecoveryEpoch::current()` (finding F-1).
2. **Refusals before the receipt.** The order puts the receipt before the FormRequest. A request whose operation differs from the path, or whose intent, key or epoch is missing or malformed, cannot be checked or repeated, so it is refused VALIDATION_FAILED before a receipt; so is a request past the hard end, refused SERVICE_BUSY, since the hard end forbids any write. Every other refusal of the FormRequest comes after the receipt and marks it REJECTED.
3. **The receipt is written by the pipeline, not by a middleware**, and the pipeline resolves the FormRequest itself (STACK_AND_MODULES section 3, the TASK-008 bullets), so that marking, then the security log, then the answer follow the order of section 1.1 step 5 in one place.
4. **Marking.** COMMITTED for 200 and 201; HELD with its code for IDEMPOTENCY_CONFLICT and EPOCH_STALE; NOT_COMMITTED for INTENT_ABANDONED; REJECTED for every other refusal; SERVICE_BUSY and a server error leave the receipt PENDING, since their outcome is uncertain to the client (TR-ERR-01), until a status check settles it.
5. **The body's `outcome`** is HELD for IDEMPOTENCY_CONFLICT and EPOCH_STALE and REJECTED for every other code, SERVICE_BUSY and THROTTLED included, as RT-07 allows only these two values and TASK-007's bodies already answer THROTTLED and CSRF_FAILED with REJECTED; the client's state follows the code (INTERACTION_CONTRACTS section 1.3), never `outcome` alone. Recorded as OPEN_ITEMS T-32 (a) (review finding R-06).
6. **EPOCH_STALE holds only the PENDING receipts of the intent that carry an epoch other than the current one.** SCHEMA section 5.3 says "the intent's PENDING rows"; a receipt of the same intent with the current epoch would belong to a request that is not stale and may still commit, so it is left to its own marking. The command's own receipt is among the held ones. Recorded as OPEN_ITEMS T-32 (b).
7. **Who may check an intent (CAP-34, "own").** The Owner (CAP-34 in full) may check any. Anyone else: a COMMAND identity must be the checker's; a FENCE must be the checker's or belong to an intent whose receipts are the checker's; without an identity, no receipt or report of the intent may be another user's. A check that the checker may not make answers NOT_FOUND and writes nothing, also after a conflict reread, and the receipts and report are read again under the fence before it settles anything (review finding R-09). Recorded as OPEN_ITEMS T-32 (c).
8. **IN_PROGRESS** is the answer when the fence's insert waits for a running original until the lock timeout of 5 seconds (55P03). A lock timeout after the fence was inserted — on a receipt — answers SERVICE_BUSY.
9. **The result of a status check under the checker's authorization**: when the checker may no longer see it, the answer is COMMITTED with `result: null`, so that the client still releases its reference; a repeat of the envelope by an actor who lost the command's capability is FORBIDDEN (AC-008-06). A command that committed is likewise answered COMMITTED with `result: null` when its viewer may not see the result, now or on a repeat, and never refused, so that no committed command's receipt is REJECTED (review finding R-05).
9a. **The epoch of an intent without an identity is the server's knowledge**: a stale-intent report, or a receipt that carried an epoch other than the current one, makes it an earlier-epoch intent — EPOCH_STALE, no fence — whatever epoch the check sends; the epoch sent decides only for an intent of which the server holds no receipt and no report. The knowledge is read before the fence and again under it, in its transaction (review finding R-02; D-073, BR-CMD-06). A user's status check names only an operation that users submit: the scheduled `posting_block.block`, whose intent is derived, is refused VALIDATION_FAILED (review finding R-03).
10. **The answer of RC-50**: `{"outcome", "operation"}`, and for COMMITTED also `commandId` and `result` with the fields of section 1.2; no `repeated`.
11. **RD-04's parameters**, from the query string alone — a body is refused VALIDATION_FAILED, so that the authorization, the validation and the query read the same values (review finding R-01): `scope` (`own` by default, `all` for the Owner only, FORBIDDEN otherwise, recorded), `after` or `before` (an opaque base64url keyset cursor of the received time and key, refused VALIDATION_FAILED when it does not decode; not both) and `perPage` (1 to 100, 25 by default, RT-08). The answer is `{"rows", "nextCursor", "previousCursor"}`, each cursor null when no row lies beyond it (IC-03; review finding R-07). Each row: `operation`, `family` (the last part of its label key `commands.family.<family>`), `sourceIntentId`, `idempotencyKey`, `epoch` (`current` or `earlier`), `receivedAt` (UTC, ISO 8601 with microseconds) and `outcome`; with `scope=all` also `user` with `id` and `displayName`. The held code is not listed (INTERACTION_CONTRACTS section 3.6 does not name it).
12. **The family of SC-01**, `posting_block.block`, is `reopen`, the family of the reconciliation's block or reopening (ROUTE_CONTRACTS RC-16); print, reprint and export have the family `print`, which has no label and is exempt from NC-13.
13. **Field messages without translation.** The FormRequest base gives every built-in rule an empty message, so the validator looks up no translation and prints nothing; `fields` maps the request's path to the keys that each request names for its rules (`fieldKeys()`), with `*.required` and `*.present` as `ux.validation.required` by default. A literal key in `messages()` would be refused by the translation check, and Laravel's ValidationException would look up its English summary (OPEN_ITEMS T-26).
14. **The received time** is the server parameter `REQUEST_RECEIVED_AT`, nginx's `$msec` when it hands the buffered request to PHP-FPM, so that a wait in PHP-FPM's queue counts; PHP's `REQUEST_TIME_FLOAT` stands in where no web server sets it (PHP's built-in server, the tests). The release image of TASK-012 must keep the parameter (finding F-2).
15. **Retries** count attempts from the first one's start; a further attempt starts only within the retry window of 15 seconds (`config/database.php`, `retry_window`), with a backoff of 50 to 250 ms by `random_int`. A bounded heavy command (`heavy()`) is never retried and keeps the runtime role's budget of 30 seconds; an ordinary one sets 10 seconds before BEGIN and resets it after.
16. **A lost connection** — the budget ended the session, or a commit could not be confirmed — answers SERVICE_BUSY and drops the connection, so that the next statement opens a new session; the receipt stays PENDING (TR-RETRY-03).
17. **The settlement after reading** an existing identity or fence runs in a short transaction of its own, and a conclusive answer is given only after it committed; if it fails, the answer is SERVICE_BUSY and the client's state is unchanged.
18. **The probe** (`testing.probe`, family `testing`) needs CAP-04, so that a cashier is refused by the gate and a capability can be lost; it locks its counter (step 6), writes a fact, the counter and the audit event `HARNESS_PROBE`, then the result: four write steps. Its `refuseWith` lets the tests raise every code through the real pipeline.
19. **OPEN_ITEMS T-27** (the trimming of outer whitespace): the FormRequest base handles no text field and keeps the global trimming; the item moves to TASK-015, whose SKU is the first text a command writes under SCH-14.
20. **The Query of RD-04 reads the Access module's display names** for `scope=all`, as an Action of the application layer orchestrates modules; the Commands module itself depends on the shared kernel and the database infrastructure only (STACK_AND_MODULES section 2.1).

## 5. Findings

| ID | Finding | Disposition |
| --- | --- | --- |
| F-1 | The recovery epoch is not yet in the Inertia shared props (SEC-CH-02 allows it) | For TASK-009, which sends it: add `recoveryEpoch` from `RecoveryEpoch::current()` to `HandleInertiaRequests::share()` with its allowlist and type |
| F-2 | The received time of TR-TIME-03 needs the web server's `fastcgi_param REQUEST_RECEIVED_AT $msec;` | For TASK-012: the release image keeps it; without it the hard end measures from PHP-FPM's start of the request and misses its queue |
| F-3 | The framework's 422 answers of RA-03 and RA-05 (Fortify) still carry Laravel's summary in `message` when several fields fail (OPEN_ITEMS T-26) | A technical choice for the Task that next changes those routes or the shell's dialog, which reads their field messages |
| F-4 | Each command Task adds its line to `CommandActions::ACTIONS`, so that RC-50 rebuilds its results; an operation without an Action answers COMMITTED with `result: null` | For every command Task (EXECUTION_CONTEXT section 10) |
| F-5 | During the build, Pint was run once with an explicit path that included `tests/Fixtures`, which `pint.json` excludes, and reformatted three negative samples of the static checks; the executor put their committed content back by hand before any commit; `git diff` of `tests/Fixtures` is empty | Closed; Pint is run without paths or on named paths only |

## 6. Tests

| Test | Suite | Proves |
| --- | --- | --- |
| `tests/Feature/Commands/CommandTablesTest.php` | Postgres (L2) | The constraint family of the six tables and of `audit_events_command_fk`; the deferred family (an identity without its result; a fence never has one); the privilege family of the runtime and backup roles; the retention family (the pipeline only adds rows of class F and changes a receipt only in its outcome columns) — AC-008-09 |
| `tests/Feature/Commands/CommandPipelineTest.php` | Postgres (L4, L2 failure injection) | AC-008-01, AC-008-04 to AC-008-06 and AC-008-08 on the probe |
| `tests/Feature/Commands/ResolveIntentTest.php` | Postgres (L4) | AC-008-03, AC-008-04 for RC-50 |
| `tests/Feature/Commands/UnresolvedRequestsTest.php` | Postgres (L4) | AC-008-07 |
| `tests/Concurrency/CommandRaceTest.php` | Concurrency (L3) | AC-008-02 (RACE-01, RACE-02, RACE-27, RACE-33), IN_PROGRESS, the lock wait, the report once (AC-008-04, AC-008-05) |
| `tests/Unit/Commands/ErrorCodeTest.php`, `OperationsTest.php`, `FingerprintTest.php` | Unit | The 32 codes against COPY_DECK 13.1 and TECHNICAL_RULES section 4, each key in `lang/id`; the list of operations against ROUTE_CONTRACTS section 6 and the families of INTERACTION_CONTRACTS section 1.4; the fingerprint |
| Changed: `RouteInventoryTest`, `CapabilityMatrixTest` (RC-50 and RD-04 for every kind of account, RC-50 refusing a role in its body), `RecentAuthenticationTest` (the 423 body), `PrivilegeMatrixTest`, `SignInSessionTest` (the key of the two tables without `id`) | Postgres, Unit | The standing obligations of a route and a table (EXECUTION_CONTEXT section 10) |

The standing obligations of section 10: the two routes are in the route inventory with their gate, each role and the combined account by direct request, no GET that writes, `no-store`, the DTO allowlists per role; object scope with a guessed intent answers NOT_FOUND (`ResolveIntentTest::test_a_third_users_check…`); the read RD-04 is paged and bounded with allowlisted parameters; the six tables have the constraint, deferred, privilege and retention families. The race scenarios and the failure injection are those of the probe; every command Task proves them again for its own operation.

### 6.1 The race outcomes (contract section 8)

| Race | Interleaving | Outcome on PostgreSQL 18.6 | Test |
| --- | --- | --- | --- |
| RACE-01, same payload | A holds its identity at `after_identity`; B with the same key waits on the unique key; A commits | A COMMITTED; B the original result (`repeated`), one fact | `test_race_01_…` |
| RACE-01, other payload | As above | B IDEMPOTENCY_CONFLICT, nothing written | `test_race_01_…` |
| RACE-02 | Another key of the same intent, same or other payload | Same outcomes as RACE-01; one identity per intent | `test_race_02_…` |
| RACE-27, fence first | The check holds its fence at `after_identity`; the original waits | NOT_COMMITTED; the original INTENT_ABANDONED before any fact | `test_race_27_…` |
| RACE-27, original first | The original holds its identity; the check's fence waits | Both COMMITTED, the same command | `test_race_27_…` |
| RACE-33 | The Owner's check against the user's repeat, in both orders, with the user's PENDING receipt | Fence first: NOT_COMMITTED, the repeat INTENT_ABANDONED, the receipt NOT_COMMITTED by the Owner; repeat first: COMMITTED, the receipt COMMITTED by the Owner | `test_race_33_…` |
| IN_PROGRESS | The original holds its identity beyond the lock timeout | IN_PROGRESS after 5 s; no fence; the receipt still PENDING; the original then commits | `test_a_check_of_a_running_original_answers_in_progress…` |
| Lock wait (AC-008-05) | A repeat waits beyond the lock timeout | SERVICE_BUSY; the original commits once | `test_a_repeat_waiting_beyond_the_lock_timeout…` |
| The check's second read (review R-02, R-09, R-16) | The check holds its fence; an earlier-epoch receipt, or another user's, commits before the fence's second read | EPOCH_STALE with its report, the receipt HELD; or NOT_FOUND, the receipt PENDING; the fence rolled back in both | `test_the_check_reads_again_under_its_fence…` |
| Report once (AC-008-04) | Two refusals of an earlier epoch; the first holds its report uncommitted | Both EPOCH_STALE; one report; no identity | `test_two_concurrent_refusals_of_an_earlier_epoch…` |

## 7. Close (EXECUTION_CONTEXT section 15, CE-50)

### 7.1 Local checks (CE-44)

In the containers of the local environment, `APP_PORT=18080`, on 28 October 2026, on the working tree before the review. The change touches `bootstrap/app.php`, `routes/web.php`, the harness every suite shares and `docker/app/nginx.conf`, so the full command contract ran, as `.github/workflows/ci.yml` runs it:

| Command | Result |
| --- | --- |
| `docker compose up -d --build --wait` | PASS: the image rebuilt with the new `fastcgi_param`, `db-setup` completed, `app` healthy |
| `composer validate --strict --no-check-all` | PASS |
| `composer lint` | PASS: Pint clean, 295 files; translation keys 199 used, all present, no literal user-facing text; translation types current, 233 keys; migration review: 4 migrations, none destructive; the secret scan of the tree and of the commits found nothing; `composer audit` without advisory; the Network suite 2 tests |
| `composer analyse` | PASS: no error |
| `composer test:unit` | PASS: 532 tests, 382,985 assertions (after `PrivilegeMatrixTest` was extended for the new tables) |
| `composer test:postgres` | PASS: 328 tests; the one error of the first run, `SignInSessionTest`'s snapshot of every table by `id`, which `command_results` and `intent_reconciliations` do not have, was fixed in the test and the class passed again (10 tests) |
| `composer test:race` | PASS: 18 tests, 122 assertions |
| `npm ci`, then `npm run lint` | PASS: ESLint clean, the npm audit without a high or critical advisory, its network proof 1 test |
| `npm run typecheck` | PASS |
| `npm run test:unit` | PASS: 16 files, 496 tests |
| `npm run build` | PASS |
| `php artisan migrate --database=pgsql_migration --force` | PASS: `2026_10_28_000001_create_command_tables` on the local database |
| `npm run test:e2e -- --workers=1`, as `docker compose run --rm playwright` | PASS: 189 passed in 19.9 minutes, none failed, flaky, skipped or not run (section 7.5) |

### 7.2 Acceptance criteria

| Criterion | Status | Evidence |
| --- | --- | --- |
| AC-008-01 | PASS | Section 3.1; the order test; no receipt before step 2; the receipt's injected failure answers SERVICE_BUSY with nothing run |
| AC-008-02 | PASS | Section 6.1, two processes under barrier control |
| AC-008-03 | PASS | `ResolveIntentTest`: the actor's and the Owner's COMMITTED settlement keeping `held_code` and recording the identity and checker; NOT_COMMITTED with its fence settling both in its transaction (an injected failure leaves neither); a third user NOT_FOUND settling nothing; IN_PROGRESS settling nothing (`CommandRaceTest`); a settled receipt never changes outcome |
| AC-008-04 | PASS | `CommandPipelineTest` and `ResolveIntentTest` (EPOCH_STALE before any fact, one report, PENDING → HELD); `CommandRaceTest` (one report under a concurrent repeat) |
| AC-008-05 | PASS | The hard end at 31 s (before the receipt and in the Action) and 29 s passing; two injected deadlocks retried to a third attempt with the same identity, three answering SERVICE_BUSY with no fourth; the lock wait answering SERVICE_BUSY |
| AC-008-06 | PASS | The original result with `repeated: true` and the same result; a user who lost CAP-04 refused by the gate and by the Action |
| AC-008-07 | PASS | `UnresolvedRequestsTest` |
| AC-008-08 | PASS | Every one of the 32 codes through the pipeline with its status, key and body outcome, the body only its six fields, no SQL, path or trace; the 423 body; a server error's body empty |
| AC-008-09 | PASS | `CommandTablesTest` |

### 7.3 Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | AC-008-01 to AC-008-09 (section 7.2); REQ-CMD-01 and REQ-CMD-04 through the platform and the probe; BR-CMD-01 to BR-CMD-06 as ADR-007 and ADR-009 implement them |
| UI and UX | N/A | No page or control; the client is TASK-009 |
| Backend | PASS | The FormRequest base with its allowlist; the gate CAP-34 and the check of own intents; the actor guard in the transaction; the DTO allowlists of every answer; L4 tests |
| Database | PASS | One additive migration with its class, duration, lock impact and verification queries in its header (REL-20); the constraint, deferred, privilege and retention families; `db:check-privileges` through the privilege family; rehearsal on the benchmark dataset waits for TASK-043 (EXC-46) |
| Security | PASS | No secret; no payload in a receipt, a report or RD-04; NOT_FOUND for another user's intent; `scope=all` for the Owner only, refused and recorded; the error bodies hold no trace, SQL or path; the static checks pass |
| Data integrity | PASS | Identity first, the result's deferred key, append-only identities, results and reports, receipts changed only in their outcome; the races and the failure injection of section 6 |
| Performance | PASS | Every lookup by a unique key or an index of SCHEMA section 11; RD-04 keyset-paged to at most 100; the budgets are measured at TASK-048 |
| Operations | PASS | The six tables hold retained data and belong in the backup manifest's counts of BACKUP_RECOVERY section 5, which TASK-013 builds (EXC-47); `command_request_receipts` is class E, removed by housekeeping 30 days after its outcome (TASK-038); no runbook or status impact |
| Verification | PASS | The command contract locally (section 7.1); the self-review (section 7.4); the review of class A (section 9); CI on the pull request follows the push |

### 7.4 Self-review (EXC-70)

The whole diff from `945a55f` was read against the contract and the rules it cites before the review. Checked in particular: the order of the pipeline's steps and of the Action's writes; that no refusal before the receipt writes one and that nothing before the hard end writes; that every refusal of the Action rolls back, with the conflict, fence and epoch answers made after the rollback; that the marking replaces only PENDING and the settlement only PENDING or HELD, in ascending order, without a session lock; that no answer, receipt, report or log carries a payload; that the migration matches SCHEMA section 5.3, its grants section 10 and its indexes section 11; that no file of `tests/Fixtures`, `scripts/executor/` or `.claude/` changed.

### 7.5 The browser suite

Run of record on 28 October 2026 from about 09:03 to 09:23, after the rebuilt image and the migration of the local database, with one Playwright worker by the Owner's directive (a command-line override; `playwright.config.ts` is unchanged and CI runs its own number). Before it, `docker ps` showed the other project's application, web server, database and cache containers and no browser-test container of it. Result: 189 passed, none failed, flaky, skipped or not run. No browser test changed: the platform adds no page, and the critical path of EXC-34 at this point is the sign-in of TASK-006, which passed.

## 8. The publication guard's preview (CE-31)

`bash scripts/executor/guard.sh --preview` on the working tree, 28 October 2026, before the review and again after its fixes: `GUARD PREVIEW PASS` both times, no candidate of the privacy and client-data check to judge. The test values of this Task are generated at run time (UUIDs, the epoch) or are fixed synthetic values (`11111111-…`, repeated hexadecimal digits) that name nothing.

## 9. Review (CE-40)

### 9.1 Round 1: the adversarial review of class A

By `bk-reviewer-high` (Claude Opus 5.5, effort High; CE-11, D-107), in its own context with Read, Grep and Glob only, on 28 October 2026 from 09:25 to 09:38, on the package `.git/bantukasir-executor/review/TASK-008/r1` (tree `d2f77d3`, base `945a55f`). `review-pack.sh --check` printed `UNCHANGED` before and after it. Verdict FAIL: four MUST-FIX, four SHOULD-FIX, seven NOTE.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | RD-04's authorization and validation read the merged input, in which a JSON body overrides the query string, while the query read the query string: a cashier could get every user's receipts and an unbounded page | Fixed: `UnresolvedRequestsRequest` reads the query string alone — `authorize()` its `scope`, `page()` the validated values, checked equal to the query string — and refuses any body and two cursors at once. Regression test `UnresolvedRequestsTest::test_a_body_that_disagrees_with_the_query_string_never_widens_the_scope_or_the_page` (a cashier's `scope=all` with a body saying `own`: 403 and recorded; bodies that disagree: 422) |
| R-02 | MUST-FIX | RC-50 decided "earlier epoch" from the epoch the check sends only, so a check sent with the current epoch fenced an intent the server knew to be of an earlier epoch and settled its HELD receipts NOT_COMMITTED without a reconciliation; a wrong epoch for a current intent wrote a bogus report | Fixed: `ResolveIntent::known()` — a report, or a receipt that carried another epoch, makes the intent earlier-epoch; the epoch sent decides only for an intent without receipts or report; read again under the fence (`StaleIntentFound` rolls the fence back). Regression test `ResolveIntentTest::test_the_servers_knowledge_of_an_earlier_epoch_decides_whatever_epoch_the_check_sends` (the Owner's check with the current epoch of an earlier-epoch HELD receipt: 409, no fence, still HELD; a report alone; the reverse case fenced with no report) |
| R-03 | MUST-FIX | RC-50 accepted the scheduled `posting_block.block`, whose derived intent anyone with CAP-34 could fence ahead of the run | Fixed: `Operations::SCHEDULED` and `submittedByUsers()`; `ResolveIntentRequest` refuses it VALIDATION_FAILED. Regression: `ResolveIntentTest::test_the_check_is_refused_for_anything_outside_its_three_fields_and_formats` and `OperationsTest` |
| R-04 | MUST-FIX | No failure injection at `own_write:report` or `own_write:settlement` | Fixed with tests: `CommandPipelineTest::test_a_failed_stale_intent_report_answers_service_busy_with_no_report_and_the_receipt_pending`; `ResolveIntentTest::test_a_failed_report_or_settlement_answers_service_busy_and_changes_nothing` (the report of a check; the settlement after an existing identity and after an existing fence; a repeated check then settles) |
| R-05 | SHOULD-FIX | A fresh commit whose result the viewer may not see was answered NOT_FOUND and its receipt marked REJECTED | Fixed: COMMITTED with `result: null`, now and on a repeat. Regression test `CommandPipelineTest::test_a_committed_command_whose_result_the_viewer_may_not_see_is_still_answered_committed` (the probe's `hideResult`) |
| R-06 | SHOULD-FIX | Readings 5 to 7 settled normative text without an open item | Fixed: OPEN_ITEMS T-32 (a) to (c); CURRENT_HANDOFF tells TASK-009 that the client's state comes from `code` |
| R-07 | SHOULD-FIX | RD-04 answered `next` without a previous cursor (IC-03, RT-08) | Fixed: `nextCursor` and `previousCursor`, with `before`; `UnresolvedRequestsTest::test_pages_are_bounded…` pages back to the first page |
| R-08 | SHOULD-FIX | A float such as the JSON number 3.0 passed the integer rule and failed the fingerprint with a 500 after the receipt | Fixed: the FormRequest base refuses any float in `payload` or `expected`. Regression test `CommandPipelineTest::test_a_floating_point_number_anywhere_in_the_envelope_is_refused_before_the_action` |
| R-09 | NOTE | The check of whose receipts exist ran before the fence's transaction | Fixed with R-02: read again under the fence before settling |
| R-10 | NOTE | Any CAP-34 holder can write fences and reports for intents of which the server has no trace | Conforms to ADR-007 section 4 and ADR-009 section 3; recorded for the next change control with OPEN_ITEMS T-32 |
| R-11 | NOTE | Identity conflicts and lost connections are recognised from the server's English message | Recorded: the constraint name in the message is an identifier, not text, and the lost-connection check also uses the SQLSTATE; pinning `lc_messages` is an `ops/` or TASK-012 matter (ENV-33 holds the database's settings) |
| R-12 | NOTE | The retry window, the backoff bounds and the no-retry of a heavy command are untested with a controlled clock | Recorded for the first heavy command, TASK-019's CSV import or TASK-025's replay, whose Action overrides `heavy()` |
| R-13 | NOTE | A route calling `execute()` directly would skip the receipt | Recorded for every command Task (finding F-4): its controller extends `CommandController`; the route inventory already lists each RC route |
| R-14 | NOTE | T-27 reassigned from TASK-008 to TASK-015 | Kept: T-27 named TASK-008 as its Task, and EXC-35 lets a Task record its own entries' facts; the reason is reading 19 |
| R-15 | NOTE | `assertGreaterThanOrEqual(0, …)` always passed | Fixed: the backup role reads the runtime role's count of every table |

The checks after the fixes, 28 October 2026 from about 09:50 to 10:16, in the same environment: `composer analyse` PASS; `composer lint` PASS (296 files, the same keys, types, migrations, scans and audit); `composer test:unit` PASS, 532 tests; `composer test:postgres` PASS, 334 tests, 14,793 assertions; `composer test:race` PASS, 18 tests. The fixes change backend code and its tests only — no page, script or stylesheet, and no route or answer that a browser test reads — so the browser suite of section 7.5 stands (CE-44).

### 9.2 Round 2: the targeted verification of the MUST-FIX fixes

By `bk-reviewer-high` (Claude Opus 5.5, effort High; CE-11: a verification one level below the review, never below High), on 28 October 2026 from about 10:20 to 10:25, on the package `.git/bantukasir-executor/review/TASK-008/r2` (tree `31f4bc6`, previous `d2f77d3`, with `fix.patch`). `review-pack.sh --check` printed `UNCHANGED` after it. Verdict PASS: R-01 to R-04 FIXED, each regression test traced to fail without its fix; no new MUST-FIX; R-05, R-07, R-08 and R-15 hold.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-16 | SHOULD-FIX | No test reached the second read under the fence — its NOT_FOUND branch and `StaleIntentFound` | Fixed with a test: `CommandRaceTest::test_the_check_reads_again_under_its_fence_and_rolls_it_back_for_an_earlier_epoch_or_another_users_intent` — the check holds its fence at `after_identity` while an earlier-epoch receipt, then another user's receipt, commits; EPOCH_STALE with its report and the receipt HELD, and NOT_FOUND with the receipt PENDING; no identity in either |
| R-17 | NOTE | Once a report exists, a PENDING receipt of the same intent with the current epoch, which only a client defect produces, can never be settled | Recorded in OPEN_ITEMS T-32 (b) |
| R-18 | NOTE | The comment claimed that every later request waits for the fence; a receipt written after the second read is settled with the fence | Comment of `ResolveIntent` reworded; the behaviour is that of an original arriving just after the fence, which the design accepts |

No MUST-FIX remains, so there is no round 3 (CE-40). After R-16 and R-18: `composer analyse` PASS, Pint clean, `composer test:race` PASS, 19 tests, 132 assertions. The fix of R-16 adds a test only and R-18 changes a comment, so no other suite is affected (CE-44).
