# TASK-013 — Evidence

| | |
| --- | --- |
| Task | [TASK-013](../tasks/TASK-013-independent-backup.md), Independent backup; size L, review class A, lane O |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (CE-01); mode `start` on 31 October 2026 at 09:54, after the tick verified TASK-012's merge |
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
4. **`state/files-recipient`.** A `HEAD` per file per run is a capped transaction each, four times a day: about 600 files would pass the free allowance of 2,500 a day and make every backup fail. The marker costs one read per run, and the metadata is read only at the first run and after a rotation. Amended in BACKUP_RECOVERY section 4 as a technical correction of how step 4 detects the old recipient (EXC-81); its meaning is unchanged.
5. **What no code names.** A failure of a tool or an unexpected exception is `DUMP_INVALID`, whose runbook action is to read the log and run again, or `TIMEOUT` after the limit; an unreachable store is `STORE_REFUSED`, whose action is to check the store's status. SCHEMA's list has no other code.
6. **No row for a refusal.** A marker that differs (ENV-04), a role that is not the backup role, or a setting of another form exits with `BACKUP_REFUSED` and no row, because the database may not be this environment's. `settings:check` refuses the same settings at the start, and the unpinged check alerts.
7. **Its own no-overlap lock.** An operator's run does not pass the scheduler's lock, so the command takes `backup:run` in the role's cache store for 35 minutes.
8. **No audit event or security log entry.** EXECUTION_CONTEXT section 10 asks a scheduled command for them, but the backup role can only read and insert `backup_runs` (SCH-42), and its container holds no other credential. The row and the command's lines are its record, as SCHEMA section 5.12 and ADR-010 item 9 say.
9. **The log file.** The scheduler sends a command's output to `/dev/null`, stderr included, so RB-09 would have nothing to read: SC-03 appends to `storage/logs/backup-run.log`. A later Task may send it to the container's output.
10. **`release` of the manifest.** `APP_RELEASE_VERSION`, `APP_RELEASE_COMMIT` and `APP_RELEASE_IMAGE_DIGEST` are read and are null until TASK-014's release workflow sets them in the image (finding F-1).
11. **Object keys.** `db/<tier>/<backup_id>.dump.age` and `.manifest.age`. The manifest's `dump.objectKey` names the first tier and `dump.objectKeys` every tier, because one encrypted manifest is uploaded to each.
12. **`--no-owner` and the custom format.** Passed as ADR-010 says; PostgreSQL's page says it is ignored for an archive, so `pg_restore --no-owner` of TASK-037 is what takes effect (finding F-2).

## 5. T-21 and T-22 (contract section 8)

**T-21.** A rotation could not finish because `files/` objects and their versions were never removed. The rule added to BACKUP_RECOVERY section 6 deletes a superseded version of `files/` and `state/` 100 days after the newer version was uploaded. Reasoning: a stored file's content never changes (D-042), so a superseded version is the same content encrypted to an earlier recipient, and deleting it loses nothing that the current version does not hold; 100 days exceed the longest tier's 93 days and its day of hiding, so that no copy written under the old recipient outlives it; a current version has no newer version and is never touched. BKP-22 now finishes 101 days after `state/files-recipient` names the new recipient, when the operator confirms by listing the versions and retires the old key. Neither a deletion beyond superseded copies of unchanged content nor a cost is needed, so the contract's stop condition does not hold. The rule itself is set by the operator at gate G4 and is not proven here (section 7, gaps).

**T-22.** The allowance belongs to the account, while a run reads one bucket. BACKUP_RECOVERY section 6 now gives staging a share of at most 1 GB and tiers of 3, 14 and 31 days, enforced at upload by `BackupSettings`, and sets the threshold at 6 GB in production's bucket, 70 % of the allowance with that share. Reasoning: after the reduced retention is applied, the usage is flat for 4 days, falls by about 16 dumps by day 8, by about 2 more by day 36 and by the last by day 94, so the threshold must leave room for three more copies and for growth over that period; 3 GB do. The Owner's status item still turns failed at 80 % (OPERATIONS_RUNBOOK section 5, unchanged). The run says `BACKUP_STORE_ABOVE_THRESHOLD` once production's bucket reaches it.

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
| `BackupRunTest` | Postgres, L6 | A whole run with the real `pg_dump` and `age` against the test server: the row, the ping, the object hashes, the manifest's every field, decryption with the harness's key only; the excluded data absent and every security log row present; the manifest last in every tier; the lock of each tier; the reduced retention; the re-verification; the file uploaded once and again at a rotation; no stored name in a program's arguments and the plaintext gone before the next step; the command's output |
| `BackupFailuresTest` | Postgres, L2 | `NO_SPACE` (a stub of `DiskSpace`), `DUMP_INVALID` (a program that fails, then one that writes garbage), `FILE_MISSING`, `FILE_CORRUPT`, `STORE_REFUSED` (a bucket without Object Lock; an address out of reach; the refused manifest), `STORE_AUTH` (a wrong key, a wrong key identifier), `REMOTE_MISMATCH` (a changed weekly object), `TIMEOUT` (before a step; during a program); each with its row and nothing else, nothing left behind and no ping. The overlap, the other environment, the settings and reason of another form |
| `BackupRunsTableTest` | Postgres, L2 | The constraint, privilege and retention families of `backup_runs`, and the actual grants; `db:check-privileges` runs over it in the existing `PrivilegeCheckTest` |
| `ObjectStoreServerTest` | Postgres, L6 | The client against the test server: a large object in parts with its lock and metadata, lists, usage with superseded versions, absent objects, a wrong signature; a real ping |
| `ObjectStoreAnswersTest` | Unit, L1 | The code of each refusal, `cap_exceeded` included, which the test server cannot give; no store message in a failure; lists read to their end; answers of another shape; a size that differs; a completion that answers an error in a success; the time limit |
| `BackupRulesTest` | Unit, L1 | The tiers by the Jakarta clock; the signature against the independent chain; each environment's retention; the static checks of AC-013-05 and scope item 9 |
| `ProcessScheduleTest`, `PrivilegeMatrixTest`, `RequiredSettingsTest` | Unit | SC-03 and its log; the matrix; `BACKUP_RETENTION` |

**Gaps.** The store's own lifecycle rules, its cap and its response to `cap_exceeded` are the operator's at gate G4 (TASK-045); `cap_exceeded` is proven only from its text. A real restore is TASK-037's. The heartbeat's real monitor is the operator's.

**The decryption test outside the application's configuration (contract section 8).** `TestStore::newRecoveryKey` runs `age-keygen` into a temporary file of the harness at run time, and only `TestStore::decrypt` reads it; the application's configuration receives the recipient alone, and `BackupRulesTest::test_the_server_holds_no_recovery_key_setting` refuses any such setting in `config/backup.php`. No key is committed (EXC-63).
