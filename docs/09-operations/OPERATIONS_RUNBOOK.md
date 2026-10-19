# Operations runbook

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta); corrected under D-089, 18 October 2026: OPR-04, because no restore drill runs on the VPS (finding B-1 of the RF-6 review); amended by TASK-002, 19 October 2026: section 5, the backup store's usage as a fifth item of the system status, by the Owner's answer (COPY_DECK section 22) |
| Phase | P9 — operator procedures, scheduled commands, the evidence of the system status, monitoring, incident response and maintenance |
| Replaces | The maintenance, reconciliation, monitoring and incident paragraphs of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` and `docs/09-operations/legacy/P9-OPERATIONS.md` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-034, D-048, D-049, D-063, D-068, D-072, D-073, D-087, D-088, D-089; [ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md), [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) |
| Related | [ENVIRONMENTS](ENVIRONMENTS.md), [BACKUP_RECOVERY](BACKUP_RECOVERY.md), [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 14, [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) section 7, [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md) |

How the operator runs and watches an installation, and what is done when something goes wrong. Rules are numbered `OPR-nn`; runbooks `RB-nn` share their numbering with BACKUP_RECOVERY and RELEASE_PROCESS; identifiers are permanent. Every operation here is the operator's, a person with technical access to the server; no AI agent performs any of them (D-088, question 20). The runbooks name no real address, account or credential (SECURITY_MODEL SEC-SECRET-05).

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| OPR-01 | **Authorized operations only.** A production operation needs the Owner's authorization of its exact target, revision and operation (gate RELEASE), except the routine operations of section 9 that this runbook, once approved by the Owner, covers as a standing instruction. A destructive operation needs the Owner's approval of that exact operation (PRODUCTION_DATA_SAFETY section 1); a general request to "fix" or "restore" is never that approval. | PDS §1, §5; D-088 |
| OPR-02 | **Positive identification.** Before any procedure, the operator confirms the environment from its configuration and its database marker (ENVIRONMENTS ENV-04); every procedure asks the operator to type the environment's name and refuses on a difference. | PDS §2, SECURITY_MODEL section 14 |
| OPR-03 | **Operations record.** The operator keeps a private operations record, outside the repository, of every operation on staging and production: date and time, environment, procedure or command, the Owner's authorization where needed, the result and any incident. Operator procedures also write their audit event and security log entry (SECURITY_MODEL section 14). | PROJECT_RULES section 5 |
| OPR-04 | **Heavy work outside opening hours.** A cost recovery, a projection rebuild, a migration and an update that restarts a container run outside the shop's opening hours unless an incident requires otherwise. | TECHNICAL_RULES section 9 |
| OPR-05 | **Never by hand.** No business fact, balance, projection, sequence or file is changed with SQL or a file tool. Repairs use only the procedures of this document and the product's commands; the one exception is the sequence advance of a restore (SCH-28), done with the migration role as BACKUP_RECOVERY RB-01 step 13 says. | BR-INV-46, PDS §1, SCH-46 |
| OPR-06 | **Links reach their owner only.** A one-time link that a procedure prints goes from the operator's terminal to the person it is for, directly; the operator never uses it, never stores it and never sends it through a channel the person has not confirmed. | SECURITY_MODEL SEC-ACC-05, section 14 |

## 2. Operator procedures

Console commands of the application, run by the operator inside the target environment's application container with the runtime role (SCHEMA SCH-46). Their effects are SECURITY_MODEL section 14; their commands are ROUTE_CONTRACTS section 7. Each is rehearsed on staging before the pilot and after every change to it (suite OPPROC01).

| ID | Command | Used | Runbook |
| --- | --- | --- | --- |
| OP-01 | `owner:bootstrap` | Once, at installation | RELEASE_PROCESS RB-23 |
| OP-02 | `owner:recover` | When the Owner has lost access | RB-10 |
| OP-03 | `owner:replace` | When another person becomes the Owner | RB-11 |
| OP-04 | `restore:after` | After every restore, and in every drill | BACKUP_RECOVERY RB-01, RB-02 |
| OP-05 | `restore:reconcile` | For each Owner decision after a restore | BACKUP_RECOVERY RB-01 |
| OP-06 | `costing:recover` | To resolve a cost-incomplete mark that the replay budget refused | RB-12 |
| OP-07 | `projection:rebuild` | To repair a projection after a reconciliation incident | RB-04 |
| OP-08 | `restore:record-drill` | After every production restore drill | BACKUP_RECOVERY RB-02 |

### RB-10 — Owner recovery (OP-02)

1. The Owner asks the operator for recovery in person, or by a call that the operator makes to the telephone number recorded for the Owner at installation, and states the recovery phrase that the Owner chose at installation and that only the Owner and the operator's private record hold.
2. The operator confirms that the account is the one active Owner account and runs OP-02. The procedure issues a RESET link, replacing any earlier link, and revokes every sign-in session of the Owner account.
3. The operator hands the link to the Owner directly (OPR-06). The Owner sets a new password within 60 minutes.
4. If identity cannot be verified this way, the operator does not run the procedure and the Owner proves identity in person.

### RB-11 — Owner replacement (OP-03)

1. Authorization evidence, kept in the operator's private record:
   - a written, dated and signed request of the outgoing Owner naming the new Owner and the new Owner's email; or
   - when the outgoing Owner cannot sign, written evidence that the new person now runs the business, together with the written confirmation of the client that funds the installation.
2. The operator verifies the new Owner's identity in person and runs OP-03 with the outgoing account's internal identifier. The outgoing account is deactivated, never deleted; an OPEN cash session of the outgoing Owner stays OPEN and unchanged (D-087).
3. The operator hands the new FIRST_PASSWORD link directly to the new Owner.
4. The new Owner closes any OPEN session of the outgoing Owner with "Tutup sesi oleh Pemilik" and a physical count (WF-09), and may later anonymize the former account (D-043).
5. The sealed copy of the recovery key and storage access is reviewed: if the outgoing Owner held it, the key is rotated and a new copy handed to the new Owner (BACKUP_RECOVERY BKP-22).

## 3. Scheduled commands

SC-01 to SC-04 are ENVIRONMENTS section 13. The operator does not run them by hand except: `reconciliation:run` after a restore, a rebuild or an incident (RB-04, RB-01); `backup:run --reason=<code>` before a release (RELEASE_PROCESS RB-20). A scheduled command that fails writes its failure (sections 4 and 5); none retries forever.

## 4. Monitoring and alerts

| Signal | Means | First action | Runbook |
| --- | --- | --- | --- |
| Web check missed (heartbeat) | The public `/up` failed through Cloudflare, or the server, the application container or its scheduler is down | Check the server over SSH, the containers in Coolify and Cloudflare's status | RB-06 |
| Backup check missed | No successful backup for more than 7 hours | Read the latest `backup_runs` rows and the backup container's log | RB-09 |
| Reconciliation check missed | The daily reconciliation did not complete | Run it again by hand and read its log | RB-04 |
| Store cap alert at 75 % or 100 % | The backup store is near or at its free allowance | Apply the reduced retention | BACKUP_RECOVERY section 6 |
| The Owner reports "Status sistem perlu dicek" | A status item failed or is "Belum Terverifikasi" | Open the item of section 5 | Section 5 |
| Certificate expiry within 60 days | From the operator's calendar | Renew | RB-07 |

The heartbeat monitor alerts the operator by email; the operator may add the Owner as a second recipient. The application itself sends no email (D-049).

## 5. Evidence behind the system status (UXP-611)

COPY_DECK section 11.11 names the five items and their values; this section fixes the evidence behind each (COPY_DECK section 21.2). The page reads all five in one request; a failed read is UXS-22, never a passing look. An item that is failed or "Belum Terverifikasi" also raises the attention item "Status sistem perlu dicek" on Beranda (UXI-064).

| Item | Evidence | "Berhasil :waktu" and ":durasi yang lalu" | "Gagal :waktu" | "Belum Terverifikasi" |
| --- | --- | --- | --- | --- |
| Cadangan data terakhir (`config.system_status.backup`) | The newest `backup_runs` rows of the environment | The newest run succeeded, at most 7 hours ago | The newest run failed | No run has ever succeeded; or the newest success is older than 7 hours (shown with its age); or the Sunday re-verification failed or has not run for 8 days |
| Uji pemulihan terakhir (`config.system_status.restore`) | The newest `restore_drills` row | The newest drill passed, at most 35 days ago | The newest drill failed | No drill has been recorded; or the newest pass is older than 35 days |
| Ruang penyimpanan (`config.system_status.disk`) | Free and total space of the file system that holds the file volume, measured when the page is read | "Tersisa :ukuran dari :total", with at least 20 % free | "Tersisa :ukuran dari :total" shown in the failed state when less than 20 % is free | The measurement could not be made |
| Rekonsiliasi harian (`config.system_status.reconciliation`) | The newest `reconciliation_runs` row | The newest run completed with no mismatch, at most 26 hours ago | The newest run failed or found a mismatch; "Insiden rekonsiliasi" links to UXP-414 while an incident is open (UXI-375) | No run has been recorded; or the newest is older than 26 hours |
| Penyimpanan cadangan (`config.system_status.backup_store`) | `store_bytes` of the newest successful `backup_runs` row, against the store's free allowance of 10 GB (ADR-010 items 4 and 11; BACKUP_RECOVERY section 6) | "Terpakai :ukuran dari :total", below 80 % of the allowance | "Terpakai :ukuran dari :total" shown in the failed state at 80 % of the allowance or more, when the operator applies the reduced retention (BACKUP_RECOVERY section 6) | No successful run has recorded the usage |

The backup store's usage against its allowance is the fifth item, as ADR-010 item 11 says, by the Owner's answer of 19 October 2026 to the question of TASK-002 ("A — Tampilkan"); the operator also acts on the store's cap alerts (section 4).

## 6. RB-03 — Incident response

| Class | Examples | Response |
| --- | --- | --- |
| S1 — integrity, security or data loss | A reconciliation mismatch; a stored file missing or corrupt; a suspected compromise or leaked secret; lost or corrupted data; a restore needed | At once; the Owner is told at once |
| S2 — the shop cannot sell | The server, the application or the database is unavailable during opening hours | At once during opening hours |
| S3 — protection degraded | Backups failing or overdue; a drill failing or overdue; a certificate expiring within 30 days; less than 20 % disk free | Within one day |
| S4 — minor | A single non-critical function or a cosmetic fault | The next release |

Steps for every incident:

1. **Classify** and open an entry in the operations record (OPR-03).
2. **Tell the Owner** at once for S1 and S2; for S3 the same day.
3. **Contain.** Stop the affected writes — a reconciliation block already does this for one product; maintenance mode for wider damage; revoke sessions and rotate secrets for a security incident (RB-05).
4. **Preserve evidence.** Keep logs, the database and the volumes as they are; delete nothing.
5. **Recover** with the runbook of the incident; a restore only by BACKUP_RECOVERY RB-01, with the Owner's authorization.
6. **Verify.** Reconciliation clean, system status current, heartbeat checks green.
7. **Record** what happened, the times, the actions and the evidence. A summary without real identities may be added to the repository by an agent from the operator's report.
8. **Review** the cause; a fix is an ordinary change through change control (PROJECT_RULES section 6), with a test that would have caught it.

## 7. Runbooks for specific incidents

### RB-04 — Reconciliation incident (BR-INV-45, BR-INV-46)

1. SC-01 found a mismatch and blocked the product's postings, or reported a difference in a running total or an active barcode mapping; the Owner sees it on "Insiden rekonsiliasi" (UXP-414, UXP-415). Postings of that product are refused `POSTING_BLOCKED`; other products continue.
2. Nothing is reopened and nothing is edited (OPR-05).
3. The operator and the Owner read the detail code and the product's stock and cost history, and find the cause: an application defect that wrote a wrong projection, or a fact that should not exist. A fact that should not exist is an S1 security incident (RB-05), because the runtime role cannot write one.
4. If the defect can happen again, it is fixed first by a release (RELEASE_PROCESS).
5. **Owner:** request the rebuild. The operator runs OP-07 `projection:rebuild` for the product (or for the Sale whose running totals differ, or for the token whose active mapping differs). The procedure recomputes the projection from immutable facts under the same guards as the commands that write it, writes the corrected projection and an audit event, and never changes a fact (SEC-OP-07). If the facts themselves fail validation, it refuses and changes nothing; the incident stays open for the Owner to decide, and a restore (RB-01) is considered only with the Owner's authorization.
6. Run `reconciliation:run` for the product again; it must be clean.
7. **Owner:** reopen the product's postings with "Buka kembali mutasi" (UXI-304, RC-16, recent authentication).

### RB-05 — Security incident or leaked secret (SEC-SECRET-04)

1. Class S1; tell the Owner.
2. Contain: put production into maintenance mode if a compromise of the server or the application is suspected; keep the logs.
3. Rotate every secret that may be exposed (ENVIRONMENTS section 16). Rotating the application key without keeping the old one as a previous key ends every sign-in session at once.
4. A staff account used by someone else: the Owner deactivates it and issues a new link (CAP-28, CAP-26). The Owner account: OP-02 (RB-10).
5. A leaked recovery key: BACKUP_RECOVERY BKP-22; every backup encrypted to it may be readable.
6. A compromised server: restore to a new server from a backup made before the compromise (RB-01), rotate every secret, and keep the old server off the network until the Owner decides on it.
7. Record and review.

### RB-06 — Server or application unavailable

1. Find where the failure is: Cloudflare's status, the domain, the origin over SSH, the containers in Coolify, the database container, the disk (RB-08).
2. Restart the failed container; check `/up`, the heartbeat and the system status.
3. A server that is gone or cannot be repaired is an S1 incident: BACKUP_RECOVERY RB-01 on a new server.
4. BantuKasir has no offline mode and queues nothing (BR-CAT-34). How the shop trades during an outage is its own procedure; anything entered afterwards is an ordinary command at the time it is entered (BR-NUM-05).

### RB-07 — Certificate

1. Create a new Origin CA certificate for the same hostnames in Cloudflare.
2. Place the certificate and key in the proxy's certificate directory (ENVIRONMENTS ENV-10), update the dynamic configuration, and check that the proxy uses the new certificate.
3. Check Full (strict) from a browser and the web heartbeat; revoke the old certificate; record the new expiry date.

### RB-08 — Disk space

1. Less than 20 % free is S3; less than 10 % free is S2.
2. Check the Docker log rotation (ENVIRONMENTS section 14) and the temporary areas; remove unused images of older releases, always keeping the current one and the one before it.
3. Never remove a volume, a database file, a stored file or a backup to free space (PRODUCTION_DATA_SAFETY section 1).
4. If the data have outgrown the disk, the client decides on a larger server; that is the client-funded server, not an additional recurring cost (COST_POLICY section 1).

### RB-09 — Backup failure

| Failure code | Action |
| --- | --- |
| `NO_SPACE` | RB-08, then run the backup again |
| `DUMP_INVALID`, `TIMEOUT` | Read the log, check the database, run again; repeated failure is S2 |
| `STORE_REFUSED` | Usually the cap: apply the reduced retention (BACKUP_RECOVERY section 6); otherwise check the store's status |
| `STORE_AUTH` | The server's key was revoked or rotated: set the right key (ENVIRONMENTS section 16) |
| `FILE_MISSING`, `FILE_CORRUPT` | S1. Find the newest backup that holds the file with its recorded SHA-256. **Owner:** authorize putting that file back under its own storage key; the operator restores the exact bytes, which must match `stored_files.sha256`. A file that no backup holds is recorded as lost |
| `REMOTE_MISMATCH` | S1. Take a new backup at once; check the other copies; contact the store's support |

A backup that keeps failing for more than a day is reported to the Owner.

### RB-12 — Cost recovery (OP-06)

1. The Owner sees a product marked "Data biaya belum lengkap" on "Biaya perlu dilengkapi" (UXP-503) and wants it resolved.
2. If the mark's reason is an unsafe history (`UNSAFE_HISTORY`), the history is corrected first with the ordinary commands that fit — a reversal or an adjustment — and the Owner then repeats the correction (RC-15); its success clears the mark (SCHEMA section 5.8). OP-06 is never used to force an unsafe history.
3. If the reason is the movement cap or the time budget (`MOVEMENT_CAP`, `TIME_BUDGET`): **Owner:** authorize the recovery in writing, naming the mark, the cost source, the corrected amount and the reason. The operator runs OP-06 `costing:recover` outside opening hours (OPR-04) with those values.
4. OP-06 runs the same validation and replay as the correction command, with a larger bound set for the run — at most ten times the movement cap and 10 minutes — under the product guard; it publishes the correction atomically, as a correction fact whose actor is the Owner, clears the product's marks of that cost source with the clearing kind `RECOVERY_PROCEDURE`, and writes its audit event; or it refuses and changes nothing (SEC-OP-06).
5. Check that the reports show the product's figures as complete and that the reconciliation is clean.

### RB-13 — Maintenance and updates

| Item | Rule |
| --- | --- |
| Operating system | Security updates install automatically; a reboot they need happens outside opening hours, followed by a check of the heartbeat and the system status |
| Coolify | The operator reads the release notes, takes a backup and updates outside opening hours |
| PostgreSQL minor version | Through a release that changes the pinned image, or the platform's update of the database resource, after a backup |
| PostgreSQL major version | Treated like a migration: rehearsed on a drill target with the latest production backup, applied in an authorized window with a write stop, by dump and restore into the new version (RELEASE_PROCESS) |
| Collation library | After an operating-system or library update that changes how PostgreSQL compares text, the text indexes that depend on it — the case-insensitive unique indexes and the trigram index — are rebuilt with the migration role outside opening hours (SCHEMA SCH-14); a rebuild that finds a duplicate is an S1 incident |
| Dependencies | CI checks advisories on every build; the operator and the planning agent review them monthly and before every release (THR-14); a fix is an ordinary release |
| Cloudflare ranges | Re-read at every release and quarterly (ENVIRONMENTS ENV-21) |

## 8. Owner recovery facts set at installation

At OP-01 the operator records privately, for RB-10: the Owner's telephone number for a call-back and the recovery phrase the Owner chooses. Neither is written in the repository or in the application.

## 9. Routine calendar

| When | Automatic | Operator |
| --- | --- | --- |
| Every 5 minutes | Web heartbeat (SC-04) | Respond to alerts |
| Hourly | Housekeeping (SC-02) | — |
| Four times a day | Backup (SC-03) | — |
| Daily | Reconciliation (SC-01) | — |
| Weekly | Remote re-verification of the latest weekly copy | — |
| Monthly | — | Restore drill RB-02 and its record; review of advisories; look at the system status and the store's usage |
| Quarterly | — | Cloudflare ranges (ENV-21) |
| Yearly | — | Database passwords rotated (ENVIRONMENTS section 16); free-tier record checked (ENVIRONMENTS section 15); sealed-copy check (BKP-21); full recovery estimate (BACKUP_RECOVERY section 12); certificate expiry checked |

These routine operations are the standing instruction of OPR-01 once the Owner has approved this runbook at gate RELEASE.

## 10. Open items

| Item | Subject | Step |
| --- | --- | --- |
| Gate RELEASE | The Owner names the operator and approves this runbook and BACKUP_RECOVERY | Before the pilot |
| Implementation | OP-06, OP-07, OP-08, SC-02 to SC-04 and the status evidence of section 5, with their tests | RF-7 Tasks |
