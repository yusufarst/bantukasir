# TASK-013 — Independent backup

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 3 Delivery infrastructure · lane O, operations and delivery |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-012](TASK-012-application-image.md), [TASK-010](TASK-010-bootstrap-profile-media.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-013-independent-backup` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for the S3 client of the framework's storage layer and `pg_dump` of the pinned version (EXC-03). |
| Modules | Operations |
| Baseline | RF-7, D-090 |

## 1. Goal

The scheduled, encrypted, independent backup of ADR-010 — `backup:run` with its dump, files, manifest, upload with Object Lock retention, weekly re-verification, failure codes and status record — the heartbeat ping client, and the resolution of the two backup findings of the RF-6 review (OPEN_ITEMS T-21 and T-22).

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- ADR-010 (all)
- BACKUP_RECOVERY sections 1 to 9, 12 and 14
- ENVIRONMENTS sections 11, 13, 15 and 16
- SCHEMA SCH-42, section 5.12 (`backup_runs`) and section 10
- OPERATIONS_RUNBOOK section 4 and RB-09
- SECURITY_MODEL section 8, THR-10 and THR-17
- ADR-011 item 11
- OPEN_ITEMS T-21 and T-22
- The evidence of TASK-006 on T-23
- D-112, D-113, OPEN_ITEMS T-38, `scripts/checks/image-scan-accepted.json` and the evidence of TASK-012 section 5.3

## 4. Scope

### 4.1 In scope

1. Create `backup_runs` (class F) with the backup role's `INSERT` and the runtime role's `SELECT` (SCH-42).
2. Implement `backup:run` (SC-03) as BACKUP_RECOVERY section 4 steps 1 to 9 say: the no-overlap lock; the identifier; the marker check; free space of twice the last dump; `pg_dump` in custom format without owners and privileges, excluding the data of `sessions`, `password_reset_tokens`, `cache` and `cache_locks`; `pg_restore --list`; SHA-256 of plaintext and ciphertext; `age` encryption to the environment's recipient through the framework's process API with an argument list (THR-10); the plaintext removed at once; the files as a superset read from `stored_files` after the dump, each uploaded as `files/<storage_key>.age` with the recipient's identifier when missing or encrypted to another recipient; `FILE_CORRUPT` and `FILE_MISSING`; the tiers `db/6h/`, `db/weekly/` and `db/monthly/` with Object Lock retentions of 7, 35 and 93 days and the store's integrity check; sizes confirmed; `STORE_REFUSED` and `STORE_AUTH`; the manifest of section 5 written last; the Sunday re-verification with `REMOTE_MISMATCH`; one `backup_runs` row with outcome and failure code only; `TIMEOUT` after 30 minutes; `--reason=<code>` on demand.
3. Make the dump hold every row of `security_log_entries` under the mechanism of T-23 (TASK-006), proven by a test.
4. Build the heartbeat ping client: configured secret ping addresses, an outbound request after success, never logging the address; SC-03 pings the backup check; SC-01 (TASK-036) and SC-04 (TASK-039) reuse it.
5. Resolve T-21: make a key rotation (BKP-22) completable — for example a lifecycle rule that deletes superseded versions of `files/` objects after a period longer than the longest tier, so that after it no object encrypted to the old recipient remains and the old key can be retired — and amend BACKUP_RECOVERY section 6 and BKP-22. A current version is never hidden or deleted. Stop if any choice needs the Owner (a deletion beyond superseded copies of unchanged content, or a cost).
6. Resolve T-22: the capacity estimate counts staging and production in the one free allowance; staging's tiers are reduced to stated values; the threshold for the reduced retention takes the lock periods into account, so that usage stays under the allowance while older copies age out; amend BACKUP_RECOVERY section 6.
7. Test locally and in CI against an open-source S3-compatible test server whose licence is recorded, as far as it supports Object Lock; the real store is verified by the operator at gate G4 (TASK-045).
8. Add the backup role's settings of ENVIRONMENTS section 11 to `.env.example` as placeholders.
9. Reassess the exposure of curl and libcurl before its HTTP or S3 integration (D-112 item 5, D-113; gate RELEASE reassesses them again, and neither is a production risk acceptance): for every curl and libcurl finding of `scripts/checks/image-scan-accepted.json` and any new one, read Debian's and the curl project's status again and judge whether the chosen S3 client and the heartbeat ping client reach it — among them signed requests (CVE-2026-86409), HTTP/2 and the multi interface, connection reuse, cookies and credentials — then record the result per finding in the evidence and in T-38, and reconsider a safer curl, such as the one of `trixie-backports` that the evidence of TASK-012 records, before this client depends on libcurl.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `backup_runs` |
| Keys completed (EXC-44) | — |
| Routes | SC-03 |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Restore, drills and the after-restore procedures (TASK-037).
- The store account, buckets, keys and sealed copy (the operator, gate G4; TASK-045 and TASK-049).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-OPS-01 | REQ-NFR-05, REQ-NFR-06, REQ-OPS-04 |
| Workflows | — | WF-32 |
| User tasks | — | — |
| Acceptance suites | REC01 (final: TASK-045) | STATUS01, SEC01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-10, THR-17 |
| Open items | T-21 — resolved here: a key rotation that can finish, T-22 — resolved here: capacity counted for both environments and a threshold that allows for the lock periods | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-013-01** Each step and each failure code of BACKUP_RECOVERY section 4 is proven locally by a test that induces it.
- **AC-013-02** The server's configuration holds only the recipient; decryption in tests uses a test key kept by the harness outside the application's configuration.
- **AC-013-03** The manifest holds the fields of section 5, including the counts of every table of classes F, FS, M and S, and is written last; a run without a stored manifest is not a backup.
- **AC-013-04** The excluded tables' data are absent from the dump; every security log row is present.
- **AC-013-05** No external program is started with a shell string; arguments come from configuration only; stored file names with shell metacharacters never reach a process.
- **AC-013-06** Only the backup role inserts into `backup_runs` (privilege family); a failure writes a row with its code and never a path, a key or a store message.
- **AC-013-07** BACKUP_RECOVERY section 6 and BKP-22 state the resolutions of T-21 and T-22; OPEN_ITEMS marks both RESOLVED.
- **AC-013-08** The heartbeat is pinged only after a successful run, and its address is never printed.
- **AC-013-09** The reassessment of scope item 9 is recorded per finding before the HTTP or S3 integration runs in the image; no critical finding is materially reachable, known to be exploited or insufficiently assessed.

## 7. Tests

- L2 and local L6 rehearsal of every step and failure code.
- L2 privilege family for `backup_runs`.
- L1 static check of process calls.
- L6 local rehearsal against the S3-compatible test server.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-013.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The S3-compatible test server and its licence.
- The T-21 and T-22 resolutions and their reasoning.
- The decryption test outside the application's configuration.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | Applies — migrations reviewed and rehearsed (EXC-41 to EXC-46); the constraint, deferred, privilege and retention families for its tables |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- T-21 or T-22 needs a deletion beyond superseded copies of unchanged content, or a cost (COST_POLICY).
- The reassessment of scope item 9 finds a critical curl or libcurl finding materially reachable, known to be exploited or insufficiently assessed; or the accepted list would have to gain a critical finding, which only the Owner's own decision adds (D-112).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- BACKUP_RECOVERY sections 6 and 9 (T-21, T-22)
- OPEN_ITEMS T-21 and T-22 (RESOLVED); T-38, its part (a)
- TOOLCHAIN (backup tools: `age`, the client tools, the S3 client)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-014's preflight reads `backup_runs`; TASK-037 restores what this Task writes; TASK-039 shows its status.
- Tasks that depend on this one directly: [TASK-014](TASK-014-release-pipeline.md), [TASK-036](TASK-036-reconciliation-repair.md), [TASK-037](TASK-037-restore-tooling.md).

## 13. Expected files

- `app/Domain/Operations/Backup/`, `app/Console/Commands/` (`backup:run`)
- `database/migrations/`
- `tests/` backup tests and the local S3-compatible test service

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
