# TASK-013 — Evidence

| | |
| --- | --- |
| Task | [TASK-013](../tasks/TASK-013-independent-backup.md), Independent backup; size L, review class A, lane O |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (CE-01); mode `start` on 31 October 2026 at 09:54, after the tick verified TASK-012's merge; mode `resume` at 13:14 in the phase `REVIEW`, after the stop `CHECKPOINT_LIMIT`, with the Owner's exception D-114 |
| Branch | `task/TASK-013-independent-backup`, created from `origin/develop` (CE-02) |
| Base commit | `9684bd8cd897d54139058724f0b3943be2338a82`, the squash commit of pull request #19 (TASK-012) |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40); section 9 |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No store account, staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-013, base `9684bd8`; `origin/develop` at that commit; the working tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; the stash `stash@{0}` as recorded.
- **Prerequisites (EXC-21).** TASK-012 and TASK-010 `DONE`; no gate.
- **Tools (D-109, CE-47).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, so Pint, PHPStan and every PHP test ran only in CI (section 8).
- **The previous merge (CE-50).** Pull request #19 is recorded in the [TASK-012 evidence](TASK-012.md) section 15 and in CURRENT_HANDOFF, with what D-113 asked of its run.

## 2. Versions and sources (EXC-03; contract section 8)

| Subject | Version | Licence | Source and date |
| --- | --- | --- | --- |
| `pg_dump` and `pg_restore` | 18, the image's | PostgreSQL License | `postgresql.org/docs/18/app-pgdump.html`, read on 31 October 2026: `--snapshot`, `--enable-row-security`, `--exclude-table-data`, `--no-password`, `--no-owner` (which the page says is ignored when emitting an archive, so a restore passes it to `pg_restore`, TASK-037), `--no-privileges`; statistics are not dumped by default |
| Guzzle | 8.3.0, already in `composer.lock` by the framework; no package added | MIT | Its source in `vendor/`: `Handler/StreamHandler.php`, which holds a body in memory, honours `sink`, `timeout` and `read_timeout`, and refuses `expect` only when it would add the header |
| The S3 API | The operations that ADR-010 item 4 needs, in path style with Signature Version 4 | — | The test server's behaviour in CI; the real store is verified by the operator at gate G4 (TASK-045) |
| **S3-compatible test server** (contract section 8) | MinIO `RELEASE.2025-04-22T22-12-26Z`, service `backup-store` of `compose.yaml`, built from its source by `docker/backup-store/Dockerfile` on the pinned `golang:1.27.3-trixie` with the toolchain go1.24.2 | **AGPL-3.0**, used unmodified as a separate test service; nothing of it is distributed, and it is never in the release image | Supports Object Lock in governance mode with a date per object, versioning, multipart uploads and `ListObjectVersions`, all of which the tests use; lifecycle rules are the store's and the operator's, and are not exercised (section 7, gaps) |

## 3. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1. `backup_runs` | Migration `2026_10_31_000001_create_backup_runs_table`, `database/privileges.php`, with `backup_runs_success_complete` written into SCHEMA section 5.12 |
| 2. `backup:run` | `app/Console/Commands/BackupRun.php`; `app/Domain/Operations/Backup/`: `BackupRunner` (steps 1 to 9), `BackupTools` (the programs), `ObjectStore` and `SigV4` (the store), `TierPlan`, `Deadline`, `BackupSettings`; `config/backup.php` |
| 3. The security log in the dump | `--enable-row-security` in `BackupTools::dump`; `BackupRunTest::test_the_dump_holds_every_security_log_row_and_none_of_the_excluded_data` |
| 4. Heartbeat | `app/Domain/Operations/Heartbeat.php`, `config/heartbeat.php`; SC-01 and SC-04 reuse it |
| 5. T-21 | Section 5 below; BACKUP_RECOVERY sections 6 and 9 |
| 6. T-22 | Section 5 below; BACKUP_RECOVERY section 6 |
| 7. The test server | `compose.yaml`, `tests/Harness/Backup/` |
| 8. `.env.example` | The role's settings as placeholders, with `BACKUP_STORE_REGION` and `BACKUP_RETENTION` |
| 9. The reassessment | Section 6 below; `app/Domain/Operations/OutboundHttp.php`; OPEN_ITEMS T-38 |

## 4. Readings and technical choices

1. **No S3 package.** The contract's MCP row names "the S3 client of the framework's storage layer", which would add `league/flysystem-aws-s3-v3` and the AWS SDK. Its adapter does not pass the Object Lock options, and the SDK reaches libcurl and libxml2 unless configured otherwise. `ObjectStore` is about 370 lines of signed requests over a client the framework already requires, so no package, licence or lock file changes (STK-01 to STK-03). The Task's executor cannot run Composer on this machine either (CE-47).
2. **No libcurl, no XML parser.** See section 6. An answer is read with fixed patterns, and one of another shape refuses the run.
3. **The dump's counts and the dump share one snapshot.** Section 4 of BACKUP_RECOVERY did not say how; separate reads would give a restore false differences under traffic. The exporting session sets its own `idle_in_transaction_session_timeout` to zero, since the role's 30 seconds would end it during a long dump.
4. **No read per object, and a rotation in its own step.** A `HEAD` per file per run is a capped transaction each, four times a day: about 600 files would pass the free allowance of 2,500 a day and make every backup fail, and a first run or a rotation of thousands of files could never finish (review finding R-02). So a run confirms sizes from listings, one transaction per thousand objects, and never reads one object. The object `state/files-recipient` keeps the place of a rotation by the key of `stored_files`, and step 7a moves it by up to 1,000 files per run after the backup is stored. That is possible because a file encrypted to the old recipient is still readable while BKP-22 keeps the old key, so a backup need not wait for the rotation. BACKUP_RECOVERY section 4 is amended as a technical correction of how step 4 works (EXC-81); steps 4 and 5 keep their order, and what a backup holds and which key reads it are unchanged, with each object's metadata naming its recipient.
5. **What no code names.** A failure of a tool or an unexpected exception is `DUMP_INVALID`, whose runbook action is to read the log and run again, or `TIMEOUT` after the limit; an unreachable store is `STORE_REFUSED`, whose action is to check the store's status. SCHEMA's list has no other code.
6. **No row for a refusal.** A marker that differs (ENV-04), a role that is not the backup role, or a setting of another form exits with `BACKUP_REFUSED` and no row, because the database may not be this environment's. `settings:check` refuses the same settings at the start, and the unpinged check alerts.
7. **Its own no-overlap lock.** An operator's run does not pass the scheduler's lock, so the command takes `backup:run` in the role's cache store for 35 minutes.
8. **No audit event or security log entry.** EXECUTION_CONTEXT section 10 asks a scheduled command for them, but the backup role can only read and insert `backup_runs` (SCH-42), and its container holds no other credential. The row and the command's lines are its record, as SCHEMA section 5.12 and ADR-010 item 9 say.
9. **The log file.** The scheduler sends a command's output to `/dev/null`, stderr included, so RB-09 would have nothing to read: SC-03 appends to `storage/logs/backup-run.log`. A later Task may send it to the container's output.
10. **`release` of the manifest.** `APP_RELEASE_VERSION`, `APP_RELEASE_COMMIT` and `APP_RELEASE_IMAGE_DIGEST` are read and are null until TASK-014's release workflow sets them in the image (finding F-1).
11. **Object keys.** `db/<tier>/<backup_id>.dump.age` and `.manifest.age`. The manifest's `dump.objectKey` names the first tier and `dump.objectKeys` every tier, because one encrypted manifest is uploaded to each.
12. **`--no-owner` and the custom format.** Passed as ADR-010 says; PostgreSQL's page says it is ignored for an archive, so `pg_restore --no-owner` of TASK-037 is what takes effect (finding F-2).
13. **A failed row with hashes.** Contract scope item 2 says "one `backup_runs` row with outcome and failure code only". A failure after the manifest was stored also keeps the stored copy's hashes, and a passed re-verification, because the next Sunday's re-verification needs them (R-01, R-27). They are no path, key or message of the store, so AC-013-06 holds; SCHEMA section 5.12 says so (EXC-81).

## 5. T-21 and T-22 (contract section 8)

**T-21.** A rotation could not finish because `files/` objects and their versions were never removed. The rule added to BACKUP_RECOVERY section 6, set for a rotation's window only, deletes a superseded version of `files/` 100 days after the newer version was uploaded. Reasoning: a stored file's content never changes (D-042), so a superseded version is the same content encrypted to an earlier recipient, and deleting it loses nothing that the current version does not hold; 100 days exceed the longest tier's 93 days and its day of hiding, so that no copy written under the old recipient outlives it; a current version has no newer version and is never touched. BKP-22 now finishes 101 days after `state/files-recipient` names the new recipient alone, when the operator confirms by listing the versions and retires the old key; the new sealed copy goes to the Owner at the start (R-03). No deletion beyond superseded copies of unchanged content is needed, since `state/` has no rule, so that part of the contract's stop condition does not hold. **The limits, stated in section 6 and to the Owner in the report:** within the window a compromised server could supersede a good file and the rule would delete the good version after 100 days (R-04); and a rotation costs nothing only while the files fit twice in the allowance: one that does not fit stops for his decision under COST_POLICY (R-05). The rule itself is set by the account owner and is not proven here (section 7, gaps).

**T-22.** The allowance belongs to the account, while a run reads one bucket. BACKUP_RECOVERY section 6 now gives staging a share of at most 1 GB and tiers of 3, 14 and 31 days, enforced at upload by `BackupSettings`, and sets the threshold at 6 GB in production's bucket, 70 % of the allowance with that share. Reasoning: after the reduced retention is applied, the usage is flat for 4 days, falls by about 16 dumps by day 8, by about 2 more by day 36 and by the last by day 94, so the threshold must leave room for three more copies and for growth over that period; about 2.5 GB are left for the growth. The Owner's status item still turns failed at 80 % (OPERATIONS_RUNBOOK section 5, unchanged, because it belongs to the approved package of D-099; ADR-010 item 11 is aligned). The run says `BACKUP_STORE_ABOVE_THRESHOLD` once a bucket passes its threshold, 1 GB in staging, and the operator reads `store_bytes` at each drill (OPERATIONS_RUNBOOK section 4).

## 6. The reassessment of curl and libcurl (scope item 9, AC-013-09; D-112, D-113)

**Result.** No outbound request of the application reaches libcurl. `OutboundHttp::client()` sets Guzzle's `StreamHandler`, PHP's own HTTP wrapper over OpenSSL, which is the only client that `ObjectStore` and `Heartbeat` use; `BackupRulesTest` refuses any cURL handler, `curl_*` call, the framework's `Http` facade or a client built elsewhere in `app/Domain/Operations`. The one curl in use stays the health check's fixed loopback request, as TASK-012 assessed. `age` is a static Go program without libcurl. `pg_dump` and `pg_restore` connect with libpq and password authentication; libpq's OAuth flow, the only part of it that could use libcurl, is not configured. So **no critical finding is materially reachable**, and a safer curl gives nothing that justifies leaving Debian's security process: curl of `trixie-backports` is **not** adopted. Gate RELEASE reassesses it again.

**The status read.** Debian's and the curl project's pages are outside the domains that CE-47 allows, so they were not fetched again by this run. The latest reading is TASK-012's of the same day, 31 October 2026 (its evidence section 5.3; `image-scan-accepted.json`), and each checkpoint run's job `Image checks` scans with that day's Grype database, whose gate refuses a fix that becomes available, a known exploitation or a new finding (section 8). Reachability above does not depend on the status of any finding: libcurl's code is not on the path. This limit is stated to the Owner in the report.

| Finding | Severity | Needs | Reached by the store's or the ping's client? |
| --- | --- | --- | --- |
| CVE-2026-10536 | Critical | HTTP/2 stream dependencies, libcurl | No: no libcurl. HTTP/1.1 only; no stream dependency |
| CVE-2026-11856 | Critical | A handle reused after Digest authentication | No: no libcurl. Signed headers per request, one connection per request, no Digest |
| CVE-2026-18924 | Critical | HTTP/2 Server Push with a callback | No: no libcurl. No HTTP/2 |
| CVE-2026-19931 | Critical | Negotiate authentication | No: no libcurl. No Negotiate, no Kerberos identity |
| CVE-2026-8926 | Critical | A `.netrc` lookup | No: no libcurl. Credentials from the role's settings, no `.netrc` |
| CVE-2026-9079 | Critical | Proxy credentials on a reused handle | No: no libcurl. No proxy configured |
| CVE-2026-12064 | High | A schemeless URL with `--proto`, the tool | No: the tool is not used; addresses are checked to have their scheme |
| CVE-2026-13608 | High | SASL for LDAP | No: no libcurl, no LDAP |
| CVE-2026-80229 | High | Pooled transfers of the multi interface | No: no libcurl, no multi interface, no pool; parts go one at a time |
| CVE-2026-80230 | High | `CURLOPT_PINNEDPUBLICKEY` | No: no libcurl, no pinning |
| CVE-2026-80255 | High | The cookie engine | No: no libcurl; Guzzle's own cookie middleware has no jar here |
| CVE-2026-82209 | High | libpsl for cookies | No: as above |
| CVE-2026-86409 | High | `CURLOPT_AWS_SIGV4` set to NULL | No: no libcurl. The signature is `SigV4`, the application's own code over `hash_hmac` |
| CVE-2026-86410 | High | SSH host keys, the tool | No: no SSH or SFTP |
| CVE-2026-8932 | High | Connection reuse across mutual TLS settings | No: no libcurl, no mutual TLS, one connection per request |
| CVE-2026-9080 | High | `curl_easy_pause` from a socket callback | No: no libcurl |
| CVE-2026-92386 | High | A Netscape cookie jar | No: no libcurl, no jar |
| CVE-2026-92387 | High | An FTP server | No: no FTP; addresses are `http` or `https`, HTTPS in production (`settings:check`) |
| CVE-2026-9545 | High | HTTP/3 | No: no libcurl, HTTP/1.1 only |

Not curl, and named by their review triggers:

- **libxml2** (CVE-2026-6653, -74860, -86138, -86139, -86140, -86142, -86143, -86144; T-38 (c)). The store's client parses XML with no parser: fixed patterns over the answer, and the completion's body is built by concatenation with `htmlspecialchars`, which does not call libxml2. `BackupRun` prints through `$this->line`, not a console component. Not reached.
- **zlib** (CVE-2026-85091; its trigger names TASK-013). It needs `gzprintf` after a non-blocking `gzwrite` stall. `pg_dump` compresses its custom archive with its own calls to zlib's deflate, to a regular file. Not reached.
- **Perl.** `backup:run` starts the client tools at `/usr/lib/postgresql/18/bin/`, so Debian's Perl wrapper does not run for it.

The accepted list `scripts/checks/image-scan-accepted.json` is unchanged: no finding entered it.

## 7. Tests

The tests of contract section 7 are the classes below; every failure code is induced by a real failure unless the table says otherwise.

| Class | Suite | Proves |
| --- | --- | --- |
| `BackupRunTest` | Postgres, L6 | A whole run with the real `pg_dump` and `age` against the test server: the row, the ping, the object hashes, the manifest's every field, decryption with the harness's key only; the excluded data absent and every security log row present; the dump and the counts from one snapshot, with a row committed in between; the manifest last in every tier; the lock of each tier; the reduced retention; the re-verification; a file uploaded once with no read per object; a rotation of one file per run with a backup in each, a file added meanwhile, and a bad marker; no stored name in a program's arguments and the plaintext gone before the next step; the threshold's line; the command's output |
| `BackupFailuresTest` | Postgres, L2 | `NO_SPACE` (a stub of `DiskSpace`, at one byte under twice the last dump, and exactly twice passes), `DUMP_INVALID` (a program that fails, then one that writes garbage), `FILE_MISSING`, `FILE_CORRUPT`, `STORE_REFUSED` (a bucket without Object Lock; an address out of reach; the refused manifest; a stored size that differs, of a dump, a manifest and a file), `STORE_AUTH` (a wrong key, a wrong key identifier), `REMOTE_MISMATCH` (a changed weekly object; a copy that is gone, in a new bucket; each recovering the next Sunday; a copy past its retention not judged), `TIMEOUT` (before a step; during a program); each with its row and nothing else but a stored copy's hashes, nothing left behind and no ping. The overlap, the other environment, the settings and reason of another form |
| `BackupRunsTableTest` | Postgres, L2 | The constraint, privilege and retention families of `backup_runs`, and the actual grants; `db:check-privileges` runs over it in the existing `PrivilegeCheckTest` |
| `ObjectStoreServerTest` | Postgres, L6 | The client against the test server: a large object in parts with its lock and metadata, lists, usage with superseded versions, absent objects, a wrong signature; a real ping |
| `ObjectStoreAnswersTest` | Unit, L1 | The code of each refusal, `cap_exceeded` included, which the test server cannot give; no store message in a failure; lists read to their end; answers of another shape or cut short; a completion that answers an error in a success; what is tried again and what is not; a default port; the time limit |
| `BackupRulesTest` | Unit, L1 | The tiers by the Jakarta clock; the signature against the independent chain; each environment's retention and threshold; the static checks of AC-013-05 and scope item 9, the libcurl one over all of `app/` |
| `ProcessScheduleTest`, `PrivilegeMatrixTest`, `RequiredSettingsTest` | Unit | SC-03 and its log; the matrix; `BACKUP_RETENTION` |
| `scripts/checks/image-checks.sh`, section 6 | Image checks | `backup:run` in the release image as `www-data`, with its real programs, temporary area and file lock, against an unreachable store: its code, no secret, nothing left behind, its row (R-12) |

**Gaps.** The store's own lifecycle rules, its cap and its response to `cap_exceeded` are the operator's at gate G4 (TASK-045); `cap_exceeded` is proven only from its text. A real restore is TASK-037's. The heartbeat's real monitor is the operator's.

**The decryption test outside the application's configuration (contract section 8).** `TestStore::newRecoveryKey` runs `age-keygen` into a temporary file of the harness at run time, and only `TestStore::decrypt` reads it; the application's configuration receives the recipient alone, and `BackupRulesTest::test_the_server_holds_no_recovery_key_setting` refuses any such setting in `config/backup.php`. No key is committed (EXC-63).

## 8. Checks (CE-44 to CE-46, D-108)

Every run is on the branch `task/TASK-013-independent-backup`, with synthetic data on CI's Linux runner: all 5 checkpoint runs of CE-46. Run 41172776809 on `e93e7d4` holds the fixes of round 1. The fixes of round 2 change `BackupRunner` and the backup's tests, so for them Pint, PHPStan, `test:postgres` and the image checks are `NOT_RUN` until CI on the pull request's final head, by the Owner's exception D-114 (section 15); nothing else they could affect has changed (CE-45).

| Check | Where it ran | Date and time | Duration | Result |
| --- | --- | --- | --- | --- |
| Command contract | CI 41164338460, `538abf4` | 31 Oct 10:25 | 6 s | FAIL at the start: the repository `minio/minio` no longer exists on Docker Hub, so the test server is now built from its source |
| Image checks | CI 41164338460, `538abf4` | 31 Oct 10:25 | 6 min 30 s | PASS |
| Command contract | CI 41165163575, `e4b69cc` | 31 Oct 10:37 | 3 min 38 s | FAIL at `composer analyse`: three findings in `BackupRunTest`, in the test only. Pint passed |
| Image checks | CI 41165163575, `e4b69cc` | 31 Oct 10:37 | 6 min 39 s | PASS |
| Command contract: `composer validate`, lint, analyse, `test:unit` 614, `test:postgres` 426 (10 min 21 s), `test:race` 29, `npm run lint`, typecheck, `test:unit` 524, build, browser 234 (7.1 min), on `compose.yaml` with `backup-store` | CI 41165797711, `c9337e1` | 31 Oct 10:46 | 25 min 42 s | PASS |
| Image checks: `Scan gate tests: 49 passed, 0 failed`, `Scan gate: 112 accepted, 0 refused, 192 of lower severity reported`, `Image checks: 268 passed, 0 failed` | CI 41165797711, `c9337e1` | 31 Oct 10:46 | 5 min 5 s | PASS, superseded by the fixes |
| Command contract | CI 41172122213, `721ba36` | 31 Oct 12:19 | 3 min 41 s | FAIL at `composer analyse`: one call of a renamed method left in a test. Pint passed |
| Image checks: `backup:run` in the release image ended `BACKUP_FAILED … STORE_REFUSED`, nothing left behind; `Image checks: 273 passed, 0 failed` | CI 41172122213, `721ba36` | 31 Oct 12:19 | 6 min 36 s | PASS |
| Command contract, with the review's fixes of round 1: `composer validate`, lint, analyse, `test:unit` 618, `test:postgres` 432, `test:race` 29, `npm run lint`, typecheck, `test:unit` 524, build, browser 234 (6.8 min) | CI 41172776809, `e93e7d4` | 31 Oct 12:29 | 24 min 55 s | PASS |
| Image checks, with the review's fixes | CI 41172776809, `e93e7d4` | 31 Oct 12:29 | 5 min 13 s | PASS |
| Command contract with the fixes of round 2: Pint, PHPStan, `test:postgres` with the new tests, and the rest on that head | CI on the pull request's final head, not yet (D-114, CE-41) | — | — | `NOT_RUN` |
| Image checks with the fixes of round 2 | CI on the pull request's final head, not yet (D-114) | — | — | `NOT_RUN` |
| Publication guard | Local, each push | 31 Oct | About a minute | PASS; candidates judged in section 12 |
| Native typecheck and ESLint | — | — | — | N/A: no TypeScript changed |

## 9. Self-review and review (CE-40)

The self-review of the whole diff against the contract preceded the review. Reviews by `bk-reviewer-high`, Claude Opus 5.5, effort High, with Read, Grep and Glob only.

| Round | Package | Head | Result |
| --- | --- | --- | --- |
| 1, the adversarial review | `review/TASK-013/r1` of the executor state; `--check` UNCHANGED | `c9337e1` with evidence edits | FAIL: 3 MUST-FIX, 9 SHOULD-FIX, 9 NOTE |
| 2, the targeted check of the MUST-FIX fixes | `review/TASK-013/r2`; `--check` was run only after the fixes of section 15 had begun, and printed `CHANGED` for them, so it proves nothing about the check itself (section 15) | `e93e7d4` with evidence edits | FAIL: R-01, R-02 and R-03 FIXED; one new MUST-FIX, R-22; 2 SHOULD-FIX, 5 NOTE |
| 3, the targeted check of R-22's fix alone | `review/TASK-013/r3`; `--check` UNCHANGED | `e93e7d4` with the uncommitted fixes of section 15, tree `7fec691` | PASS, by reading only: R-22 FIXED, no new finding |

| Finding | Severity | Outcome |
| --- | --- | --- |
| R-01 The Sunday re-verification could stay failed for good, and raised a false S1 for a copy past its retention or in another bucket | MUST-FIX | Fixed: the row of a failure after the manifest keeps the stored copy's hashes, and the next Sunday judges it; a copy past its tier's retention is not judged; RB-09 and ENVIRONMENTS section 15 name the move to a new bucket. Three regression tests of `BackupFailuresTest` |
| R-02 A rotation could not finish above about 2,500 files and blocked dumps | MUST-FIX | Fixed: step 7a after the manifest, at most 1,000 files per run, its place in the marker, no read per object anywhere in a run. `test_a_rotation_goes_by_a_bounded_number_of_files_per_run_with_a_backup_in_each`, `test_a_later_run_uploads_only_what_is_missing_and_reads_no_object` |
| R-03 BKP-22 handed over the new sealed copy only after 101 days | MUST-FIX | Fixed in BKP-22: made, tested and handed over before the new recipient is set |
| R-04 The T-21 rule lets a compromised server replace a good file; `state/` is not unchanged content | SHOULD-FIX | Fixed: the rule only for a rotation's window, `state/` without a rule, the trade-off in section 6 and ADR-010, and in the report |
| R-05 "No cost" holds only if the rotation fits | SHOULD-FIX | Fixed: section 6 states the stop for the Owner's decision; T-21 qualified |
| R-06 ADR-010, RUNBOOK and BACKUP_RECOVERY not aligned | SHOULD-FIX | Fixed in ADR-010 items 5 and 11 and its consequences, RUNBOOK section 4 and RB-09. RUNBOOK section 5 is left as approved under D-099: its 80 % stays the status's failed state |
| R-07 The threshold reached no one | SHOULD-FIX | Fixed: a row of RUNBOOK section 4, the read at each drill, staging's own 1 GB; test of the line |
| R-08 A bodyless 403 of `HEAD` told the operator to replace a valid key | SHOULD-FIX | Fixed by R-02's change: a run no longer uses `HEAD`, and a listing's refusal has a body |
| R-09 No request retried | SHOULD-FIX | Fixed: three attempts with a growing pause, for a failed connection and for 429, 500, 502, 503 and 504; unit test |
| R-10 No test of the shared snapshot | SHOULD-FIX | Fixed: `test_the_dump_and_the_counts_share_one_snapshot` |
| R-11 The static check could be passed by a client elsewhere | SHOULD-FIX | Fixed: exempts only `OutboundHttp.php`, over all of `app/` |
| R-12 No run of `backup:run` in the release image | SHOULD-FIX | Fixed: five checks in `image-checks.sh`, section 6 |
| R-13 A list cut short passed | NOTE | Fixed: the closing element is required; test |
| R-14 `tiers` and `snapshot_at` of a failed row | NOTE | Fixed in SCHEMA's wording |
| R-15 Three gaps of AC-013-01 | NOTE | Two fixed (a copy that is gone, the boundary of `NO_SPACE`). A store's refusal turned into `TIMEOUT` stays without its own test: the mapping is three lines, and both codes' runbook actions apply (finding F-6) |
| R-16 The plaintext's removal unchecked | NOTE | Fixed: the run fails if the delete fails. After a kill the plaintext stays in the 0700 folder until the next run reaches `prepare` (finding F-6) |
| R-17 A default port was signed but not sent | NOTE | Fixed: `ObjectStore::hostOf`; test |
| R-18 SECURITY_MODEL section 14 not excepted for SC-03; DoD wording | NOTE | Recorded: the reading of section 4 item 8, for the next change control of SECURITY_MODEL section 14 (finding F-6). The DoD's wording corrected |
| R-19 "Read again" was not literally done | NOTE | Accepted by the reviewer for development; the limit is in section 6, T-38 and the report |
| R-20 The numbers of T-22 | NOTE | Fixed: ranges on one basis, "under that growth" |
| R-21 `BACKUP_RETENTION=staging` outside staging | NOTE | Fixed: refused; test. The null `release` is finding F-1 |
| R-22 A first run cut short left the marker absent, so the next run encrypted its files again, leaving versions no rule removes | MUST-FIX | Fixed: the marker is written before step 4 in an empty bucket. `test_a_first_run_cut_short_leaves_nothing_to_encrypt_again` |
| R-23 A move to a new bucket shows the Owner a status the runbook did not mention | SHOULD-FIX | Fixed in RB-09 and ENVIRONMENTS section 15 |
| R-24 No test failed step 7a | SHOULD-FIX | Fixed: `test_a_failure_of_a_rotation_keeps_the_stored_copy_and_the_next_run_goes_on` |
| R-25 The marker moves before sizes are confirmed, and a later run accepts any present object | NOTE | Recorded: each upload carries `content-md5` and a signed SHA-256, which the store checks |
| R-26 Step 7a could run into the time limit and cost a run its success | NOTE | Fixed: it stops when 5 minutes remain; `test_a_rotation_stops_short_of_the_time_limit_and_the_run_succeeds` |
| R-27 A failed rotation lost the row's `reverified_backup_id` | NOTE | Fixed: the row keeps it; tested with R-24's |
| R-28 The age test of step 7 uses `finished_at`, not the upload | NOTE | Recorded: it needs four Sundays without a stored weekly copy, gives one false S1 and clears the next Sunday |
| R-29 The hashes of a failed row were recorded only here | NOTE | Fixed: section 4 item 13 |

## 10. Acceptance criteria

| Criterion | Status | Evidence |
| --- | --- | --- |
| AC-013-01 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | `BackupRunTest`, `BackupFailuresTest`, `ObjectStoreAnswersTest`; run 41172776809; the regression tests of R-22, R-24 and R-26 in section 15 |
| AC-013-02 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | `TestStore`; `BackupRunTest::test_a_run_stores_the_dump_the_files_and_the_manifest_records_its_row_and_pings`; `test_the_server_holds_no_recovery_key_setting` |
| AC-013-03 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | The same; `test_the_manifest_is_written_last_in_every_tier`; `test_a_failure_at_the_manifest_leaves_no_backup` |
| AC-013-04 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | `test_the_dump_holds_every_security_log_row_and_none_of_the_excluded_data` |
| AC-013-05 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | `test_a_program_gets_no_stored_name_and_the_plaintext_is_gone_before_the_next_step`; `test_a_setting_or_a_reason_of_another_form_refuses_the_run`; `test_every_program_starts_from_an_argument_list_in_one_class`; PHPStan's `ForbiddenProcessFunctionRule`. The check `stored_files_storage_key_format` refuses a key with a metacharacter (`ConfigurationTablesTest`), and no key reaches a process in any case |
| AC-013-06 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | `BackupRunsTableTest`; `assertFailedWith` of `BackupFailuresTest` |
| AC-013-07 | PASS | BACKUP_RECOVERY sections 6 and 9, BKP-22; OPEN_ITEMS T-21 and T-22 `RESOLVED` |
| AC-013-08 | PASS on `e93e7d4`; `NOT_RUN` on the final head until CI there (D-114) | `assertFailedWith`; `test_a_ping_that_fails_prints_no_address_and_does_not_fail_the_backup`; `test_the_command_prints_no_address_key_or_path` |
| AC-013-09 | PASS, with the limit of section 6 | Section 6, recorded before the first checkpoint's integration ran in the image; `test_the_outbound_client_is_php_s_wrapper_and_no_code_reaches_libcurl_or_an_xml_parser`; the scan gate of run 41172776809 |

## 11. Definition of Done (EXECUTION_CONTEXT section 13)

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | Section 10; REQ-OPS-01 up to its final point at TASK-045 |
| UI and UX | N/A | No page or control; TASK-039 shows the status |
| Backend | PASS | No route or Action of the application, so the contract's FormRequest and policy families have nothing to hold; the command's one option is a typed code, refused otherwise; L4's share is `BackupFailuresTest` |
| Database | PASS | The migration reviewed (`migration-review.php`), with class, duration, lock impact and verification in its header; the constraint, privilege and retention families; no deferred key |
| Security | PASS | THR-10 and THR-17's part; the server holds only the recipient; no secret printed or stored; section 6 |
| Data integrity | PASS on `e93e7d4`; R-22's fix `NOT_RUN` until CI on the final head (D-114) | One row per run, written once; the manifest last; the dump and its counts from one snapshot; `backup_runs` retained and never changed; a first run cut short leaves nothing to encrypt again (R-22) |
| Performance | PASS | Paged reads of `stored_files` and of the store's lists, one part in memory at a time, a rotation bounded per run, no read per object; TASK-048 measures budgets, and a real dump's duration is the operator's at gate G4 |
| Operations | PASS | EXC-47: `backup_runs` is class F, so the manifest counts it; `BACKUP_RECOVERY`, `ENVIRONMENTS`, `TOOLCHAIN`, `.env.example` |
| Verification | `NOT_RUN` on the final tree | The command contract and the image checks green in run 41172776809 on `e93e7d4`; the self-review, the adversarial review and the targeted checks, section 9, the last by reading only. CI on the pull request's final head is the first run of the fixes of round 2 (CE-41, D-114) |

## 12. Guard (CE-31, CE-50)

| Where | Candidates | Judgment |
| --- | --- | --- |
| The guard on `538abf4`, 4 candidates | `http://backup-store:1` and `:9000` with paths, in the tests and the harness | The compose service of the test server, a name on CI's internal network, and a closed port; none a real address. Accepted by its hash |
| The guard on `e4b69cc`, 1 candidate | `http://127.0.0.1:9000/minio/health/live` in `compose.yaml` | The test server's health check on its own loopback address. Accepted by its hash |
| The guard on `c9337e1` | None | PASS |
| The preview on the working tree for the pull request, 7 candidates | `http://127.0.0.1:9000/minio/health/live` in `compose.yaml` and this file; `http://backup-store:1`, with `/the-secret-ping-address` and `/secret`, in the tests and this file; `http://backup-store:9000` in the harness | The test server's own loopback address, and the compose service's name on CI's internal network with a closed port or its own; the two paths are fixed words of the tests. No real address, none a finding. Judged again at the guard on the commit |

## 13. Findings

- **F-1 (for TASK-014).** The manifest's `release` reads `APP_RELEASE_VERSION`, `APP_RELEASE_COMMIT` and `APP_RELEASE_IMAGE_DIGEST`, null until the release workflow sets them in the image. BACKUP_RECOVERY section 5 asks for them, so a production backup needs them. TASK-014's preflight also reads `backup_runs`.
- **F-2 (for TASK-037).** A custom-format archive ignores `--no-owner` and `--no-privileges` at the dump: `pg_restore --no-owner --no-privileges` is what takes effect. The restore also finds `state/files-recipient` beside the objects, and the manifest's `dump.objectKeys`.
- **F-3 (for TASK-039).** The status reads `backup_runs` as OPERATIONS_RUNBOOK section 5 says; a `SUCCEEDED` row's `reverified_backup_id` shows the Sunday re-verification.
- **F-4 (for the operator, gate G4).** The lifecycle rules of BACKUP_RECOVERY section 6, the new rule for superseded versions included, and the behaviour of the real store under Object Lock, its multipart uploads and `ListObjectVersions` are unproven until TASK-045.
- **F-6.** What the review leaves: no test of a store's refusal turned into `TIMEOUT` by the time limit; a killed run's plaintext stays in the private 0700 folder until the next run starts; SECURITY_MODEL section 14 is not yet excepted for SC-03, whose role can write nothing else (section 4 item 8). For TASK-045's operations verification and the next change control of SECURITY_MODEL.
- **F-5.** The test server's build from source is part of the first step of the job `Command contract`, which took about 3.5 minutes in run 41165797711; its share of them was not measured.

## 14. Backup impact (EXC-47)

`backup_runs` holds retained data: it is in the dump and in the manifest's counts as class F. No new kind of stored file.

## 15. The fixes of round 2 and the limit of CE-46

The five checkpoint runs are used, and PHP does not run on this machine (D-108, CE-47). The fixes of R-22, R-24, R-26 and R-27 therefore stay uncommitted on `e93e7d4` until a run can prove them: they change `BackupRunner.php`, `BackupFailuresTest.php` and `BackupRunTest.php`, with documents. Their three new tests and Pint and PHPStan on them are `NOT_RUN`.

**The stop.** R-22's fix needs its regression test run before the pull request (CE-40, CE-44), and a sixth checkpoint run is the stop `CHECKPOINT_LIMIT` (CE-46). The Task stopped on 31 October 2026 in the phase `REVIEW`, with these fixes uncommitted and kept. The targeted check of round 3 read them, as the compiler and PHPUnit would, and found no defect; a reading is no run, so they stay `NOT_RUN`.

**A lapse of CE-40.** The executor began these fixes before running `review-pack.sh --check` on the package of round 2, so that check printed `CHANGED`, from the fixes themselves. What is known instead: the verification agent has only Read, Grep and Glob; HEAD stayed `e93e7d4`; and `git status` lists only the files the executor edited.

**The Owner's answer, D-114.** No further checkpoint run: the fixes reach the pull request as they are, and CI on its final head proves them, R-22 above all. The working tree was accounted for at the resume: the eight files of `git diff --stat` are these fixes, their documents and this file. The review is not restarted (CE-40), and no unaffected test runs again (CE-45).

What that run must show, for the job `Command contract`: Pint and PHPStan green on the changed files; `test:unit` 618 and `test:race` 29 and the browser suite 234, as on `e93e7d4`, since those suites gain nothing; `test:postgres` 435, which is the 432 of run 41172776809 and the three new tests `test_a_first_run_cut_short_leaves_nothing_to_encrypt_again` (R-22), `test_a_failure_of_a_rotation_keeps_the_stored_copy_and_the_next_run_goes_on` (R-24, R-27) and `test_a_rotation_stops_short_of_the_time_limit_and_the_run_succeeds` (R-26). Together with the classes of section 7 they exercise backup integrity, encryption, retention, recovery and failure handling, as D-114 names them. For the job `Image checks`: `Scan gate tests: 49 passed, 0 failed` and `Image checks: 273 passed, 0 failed`, with `backup:run` in the release image still ending in `STORE_REFUSED` and leaving nothing behind.

A red run enters the fix cycles of CE-42, at most three, with only its demonstrated issue fixed and every result kept here. A failing or unverified critical change is not merged: `ci_result` of `scripts/executor/lib.sh` counts every check run of the head. A fix of a MUST-FIX red there, or one that changes the production behaviour of this class-A Task, has its targeted check (CE-40, CE-42). The record of TASK-013's merge in the next Task states what the run showed (CE-50).
