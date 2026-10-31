# Backup and recovery

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta); corrected under D-089, 18 October 2026: every decryption off the VPS, also in drills (BKP-07, BKP-08, sections 8 and 9, RB-01 steps 8 to 11, RB-02), the close of a restored session after its receipts are settled (section 11), and the open items T-21 and T-22 (finding B-1 and two nonblocking findings of the RF-6 review); amended by TASK-013, 31 October 2026: sections 4, 6 and 14 and BKP-22, resolving T-21 and T-22 |
| Phase | P9 — backup, restore, restore drills, the reconciliation of a restore, recovery-key custody and the recovery objectives |
| Replaces | The backup, retention, restore and missing-interval sections of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-005, D-044, D-054, D-062, D-072, D-073, D-088, D-089; [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md), [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md), [ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md), [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) |
| Related | [ENVIRONMENTS](ENVIRONMENTS.md), [OPERATIONS_RUNBOOK](OPERATIONS_RUNBOOK.md), [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 14, [SCHEMA](../04-architecture/SCHEMA.md) sections 5.3, 5.12, 6 and 10, [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md) |

How BantuKasir's data are copied out of the server, how a copy is proven, and how a shop is recovered from one. Rules are numbered `BKP-nn` and runbooks `RB-nn` (the runbook numbers are shared with OPERATIONS_RUNBOOK and RELEASE_PROCESS); identifiers are permanent. The operator — never an AI agent — performs every step on production and on a drill target (D-088, question 20). This document names no real account, bucket, key or location (SECURITY_MODEL SEC-SECRET-05).

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| BKP-01 | **Independent and encrypted.** Every production backup is encrypted on the server to the environment's recipient and stored outside the server and its provider (ADR-010). A copy on the server, or a dump made by the hosting platform alone, is not a backup. | ADR-002 item 7, ADR-010 |
| BKP-02 | **Proven, not assumed.** A backup counts only when its manifest is stored (section 4). A green job is not a restore: the restore is proven by a drill (RB-02) at least once a month, after any change of the schema, the backup tooling or the store, and before the pilot. Anything not proven is "Belum Terverifikasi" on the Owner's system status. | REQ-OPS-01, REQ-OPS-04, L-09 |
| BKP-03 | **Never over the live data.** A backup is restored only into a new, isolated target. Restoring over the live database is a destructive operation that needs the Owner's approval of that exact operation, and the runbook never asks for it. | PRODUCTION_DATA_SAFETY section 1 |
| BKP-04 | **Missing is not proof.** A result missing from restored data is not proof that a command never committed. Nothing after the backup point is replayed, re-created or edited into the database; the gap is reviewed from evidence (section 11). | BR-CMD-06, REQ-CMD-03, D-073 |
| BKP-05 | **The old world is fenced before anyone works.** After a restore, OP-04 sets a new recovery epoch and revokes every sign-in session, and both number sequences are advanced, before any user other than the Owner reaches the restored data (RB-01 phase D). | SEC-OP-04, SCH-28, D-073 |
| BKP-06 | **The Owner decides, the operator records.** Every decision about a request from before a restore and about an onboarding restore hold is the Owner's; the operator records it with OP-05. The Owner's own user-management requests are reconciled before the security changes of SEC-OP-04 are re-applied. | D-072, D-073, SEC-OP-05 |
| BKP-07 | **A decrypted copy lives only as long as its drill or restore.** A decrypted copy of a backup exists only on the operator's own machine and on the drill target of section 8, only during the drill or the restore, and is destroyed at its end. On a server it exists only as the restored data of a real restore's target (RB-01). | ENVIRONMENTS ENV-01 |
| BKP-08 | **The recovery key never enters the VPS.** Every decryption — in a restore, a drill or a check of the sealed copy — runs only on the operator's own machine or on an isolated drill target off the production and staging server (section 8). The recovery key is never copied to the VPS, to any of its containers, volumes or settings, to the hosting platform, to CI or to the repository. Decrypted data reach another machine only through an SSH tunnel from the machine that decrypted them (RB-01 steps 8 to 10). | ADR-010 item 2, D-089 |

## 2. What a backup holds

A backup is the set of ADR-010 item 1: the database dump, every stored file that `stored_files` names, and the manifest. The dump excludes the data — not the definitions — of `sessions`, `password_reset_tokens`, `cache` and `cache_locks`, so no sign-in session or one-time link comes back from a restore. It keeps `command_request_receipts`, `stale_intent_reports`, `intent_reconciliations`, the onboarding tables, `security_log_entries` and every retained table, because the restore reconciliation needs them. Outside every backup: the temporary CSV area (D-054), unreferenced files (TR-TX-05), secrets, the platform configuration, the image and logs.

## 3. Schedule

| Run | When | Started by |
| --- | --- | --- |
| Scheduled | 00.00, 06.00, 12.00 and 18.00 Asia/Jakarta (SC-03, ENVIRONMENTS section 13) | The backup container's scheduler |
| Weekly copy and re-verification | The Sunday 00.00 run | The same run |
| Monthly copy | The 00.00 run on the 1st of the month | The same run |
| On demand | Before every production migration or release (RELEASE_PROCESS RB-20), before an operator procedure that changes data in bulk, and whenever the operator needs a fresh copy | The operator: `backup:run --reason=<code>` in the backup container |

The four daily runs serve the proposed recovery point of 6 hours (REQ-NFR-05); a shorter interval is a configuration change if the Owner wants a smaller recovery point at gate G4, within the store's free allowance.

## 4. One backup run

`backup:run` (SC-03), in the backup container. Every external program is started through the framework's process API with an argument list, never a shell string; arguments come from configuration only (THR-10). The run holds no lock that the application needs.

1. **Start.** Take the scheduler's no-overlap lock; create the backup identifier (`YYYYMMDDTHHMMSSZ-` and 8 random characters); check that the environment name equals the database marker (ENV-04) and that the private temporary area has at least twice the last dump's size free, else fail with `NO_SPACE`.
2. **Dump.** Run `pg_dump` with the backup role into the temporary area, custom format, without ownership and privilege statements, excluding the data of the four tables of section 2. Check the archive with `pg_restore --list`; an unreadable archive fails the run with `DUMP_INVALID`.
3. **Encrypt.** Hash the plaintext dump (SHA-256); encrypt it with age to the recipient; hash the encrypted file; remove the plaintext at once.
4. **Files.** After the dump has finished, read every row of `stored_files`; because the table only grows, this is a superset of the rows the dump holds. List the store's `files/` prefix. For each row whose object is missing, or whose object metadata names another recipient (section 9): read the file from the read-only volume, hash it and compare with `stored_files.sha256` — a difference fails the run with `FILE_CORRUPT`, an absent file with `FILE_MISSING`, both incidents of class S1 (OPERATIONS_RUNBOOK RB-09) — then encrypt it and upload it as `files/<storage_key>.age` with the recipient's identifier in its metadata. Reading an object's metadata is one capped transaction, too many to spend on every file of every run, so the object `state/files-recipient` names the recipient that every current file is encrypted to, and the metadata is read only when it names another: at the first run and after a rotation. It reads `ROTATING` while such a pass is uploading, so that a pass cut short is walked again.
5. **Upload.** Upload the encrypted dump to `db/6h/`, with the store's integrity check and an Object Lock retention of 7 days; on the weekly and monthly runs upload it again to `db/weekly/` (35 days) and `db/monthly/` (93 days). Confirm each object's size. A refusal by the store fails the run with `STORE_REFUSED` (for example `cap_exceeded`), an authentication failure with `STORE_AUTH`.
6. **Manifest.** Build the manifest of section 5, encrypt it, and upload it beside the dump in every tier written, with the same retention. The backup exists from this moment.
7. **Re-verify (Sunday run).** Download the encrypted dump and manifest of the previous weekly copy and compare their SHA-256 with the values recorded when they were uploaded; a difference fails the run with `REMOTE_MISMATCH`, an S1 incident.
8. **Record.** Write one `backup_runs` row (SCHEMA section 5.12): outcome `SUCCEEDED`, the identifier, the sizes, the number of files uploaded, the SHA-256 of the encrypted dump and manifest objects, the store usage read after the upload; then ping the backup check of the heartbeat monitor.
9. **On any failure.** Write a `backup_runs` row with outcome `FAILED` and its failure code — never a path, a key or a message from the store that could hold one — remove the temporary files, ping nothing and exit with an error. The earlier copies are untouched.

Every step after step 1 is bounded: the whole run stops with `TIMEOUT` after 30 minutes (ENVIRONMENTS ENV-31).

The counts of the manifest and the dump come from one exported snapshot (`pg_dump --snapshot`), so that a restore compares like with like while the shop works. The dump runs with `--enable-row-security`, under which the backup role reads every entry of `security_log_entries` (OPEN_ITEMS T-23). A store's answer is read with fixed patterns, never with an XML parser, and every request goes through PHP's HTTP wrapper, never libcurl (OPEN_ITEMS T-38). A failed run's lines, a word and its code, are in `storage/logs/backup-run.log` of the backup container when the scheduler started it (RB-09). A run whose settings are not those of a backup, or whose database's marker differs (ENV-04), writes no row: it says `BACKUP_REFUSED` and exits with an error, and the backup check goes unpinged.

## 5. The manifest

A JSON document, encrypted like the dump. It holds no secret, no hostname and no personal data beyond what the counts reveal.

| Field | Content |
| --- | --- |
| `format` | `1` |
| `backupId`, `environment` | The identifier of section 4 and the environment's name |
| `startedAt`, `snapshotAt` | UTC times: the start of the run and the moment the dump's snapshot was taken |
| `release` | The application version, the commit and the image digest that wrote the data |
| `migration` | The name of the latest applied migration |
| `postgres` | The server's version |
| `dump` | Object key; encrypted size and SHA-256; plaintext SHA-256; the tables whose data were excluded |
| `files` | Count and total size, and for each file: `storageKey`, `purpose`, size, plaintext SHA-256 (as in `stored_files`) and object key |
| `counts` | The row count of every table of classes F, FS, M and S, which the restore compares |
| `recipient` | The identifier of the age recipient the objects are encrypted to |

## 6. The store

Configured by the operator when the store account is opened (gate G4); one bucket per environment.

| Item | Setting |
| --- | --- |
| Bucket | Private; Object Lock enabled; default retention in governance mode of 7 days |
| Lifecycle rules | `db/6h/`: hidden 7 days after upload, deleted one day after hiding. `db/weekly/`: 35 and 1. `db/monthly/`: 93 and 1. `files/` and `state/`: never hidden, and a **superseded version** deleted 100 days after the newer version was uploaded (`daysFromHidingToDeleting` of 100, without `daysFromUploadingToHiding`), which a current version never meets. Unfinished large uploads cancelled after one day |
| Per-object retention | Set by the run at upload: 7, 35 or 93 days for the dump and manifest of each tier; files take the default |
| Caps | Rp0 for storage, downloads and every capped transaction class, so the store refuses instead of billing (D-088) |
| Alerts | The store's own cap alerts at 75 % and 100 %, to the operator |
| Keys | The server's key of ADR-010 item 7 only; the account owner's key stays with the operator and in the sealed copy |

**The superseded versions of `files/` (OPEN_ITEMS T-21).** A stored file's content never changes, so a second version of a `files/` object is the same file encrypted to a later recipient (BKP-22), and a superseded version is a copy of unchanged content, which the rule above removes. The 100 days are longer than the longest tier's 93 days and the one day of hiding, so that every dump and manifest that was written while the old version was current has gone first. The current version of a file is never hidden or deleted, and no other object is touched by this rule.

**Tiers per environment and profile.** The operator sets the same values in the lifecycle rules and, through `BACKUP_RETENTION`, in the run; staging always uses its own.

| Profile | Environment | `db/6h/` | `db/weekly/` | `db/monthly/` | Dumps at any time, about |
| --- | --- | --- | --- | --- | --- |
| Full | Production | 7 days | 35 days | 93 days | 36 to 42 |
| Reduced | Production, once applied | 3 days | 21 days | 62 days | 17 |
| Staging | Staging, always | 3 days | 14 days | 31 days | 20 |

**Capacity (OPEN_ITEMS T-22).** The free 10 GB belong to the store account, which holds one bucket per environment, and a run can read only its own bucket's usage. So the allowance is divided in advance:

- **Staging's share is at most 1 GB.** Staging holds synthetic data with few or no files, about 20 dumps. Its `store_bytes` above 1 GB means that its data have grown beyond its purpose: the operator reduces them or its tiers first, because staging's copies have no value that production's lack.
- **Production's bucket** holds about 36 to 42 dumps with their manifests, plus the files; with a 50 MB dump that is about 2 GB plus the files.
- **The threshold for the reduced retention is 6 GB in production's bucket**, its `store_bytes`, which with staging's share is 70 % of the allowance. The run says `BACKUP_STORE_ABOVE_THRESHOLD` in its log from then on, and the operator applies the reduced retention by changing the lifecycle rules and `BACKUP_RETENTION` together. The Owner's status item still turns failed at 80 % of the allowance (OPERATIONS_RUNBOOK section 5): by then the reduced retention must already be in force.

**Why 6 GB: the lock periods.** The reduced retention removes nothing at once, because a copy stays until its own lock ends. For the first 4 days after it is applied, each new `db/6h/` copy replaces one that ages out, and the usage does not fall; it falls by about 16 dumps by day 8, by about 2 more by day 36, and by the last by day 94. Meanwhile a run may add a weekly and a monthly copy at once, and the data grow. The 4 GB left above the threshold, of which staging's share is 1 GB, leave 3 GB for this: at the largest dump production can reach at the threshold, about 140 MB (6 GB over 42), three copies and 94 days' growth of the files and of each kept dump stay under 10 GB, so the store never refuses while older copies age out. A key rotation adds the size of the files again for 100 days (BKP-22), so the operator checks that room before starting one that is not urgent. If the reduced retention does not fit either, the Owner decides on a paid exception (ADR-010 item 11; COST_POLICY).

## 7. Verification

| Check | Proves | When |
| --- | --- | --- |
| Archive check, hashes and the store's integrity check (section 4 steps 2 to 6) | The dump is readable and every object arrived intact | Every run |
| File check against `stored_files.sha256` | No stored file is missing or corrupt on the server | Every run, for every file not yet in the store |
| Remote re-verification (step 7) | The store still returns the bytes it received | Every Sunday run |
| Restore drill (RB-02) | The backup decrypts with the recovery key, restores, reconciles and starts the application | At least monthly; after any change of the schema, the backup tooling or the store; before the pilot |
| Full recovery estimate (section 12) | The recovery time of a lost server | At the first installation and yearly |

## 8. The restore-drill target and copies of production data

- The drill target is the operator's own machine, or another isolated machine under the operator's control that is physically or logically separate from the VPS, for example a virtual machine on the operator's computer. It is never the VPS: never the production or staging server, a Coolify environment on it, or one of their resources (ENVIRONMENTS section 2; BKP-08). While the recovery key is in use there or decrypted data are present, no AI agent runs on it (ENVIRONMENTS ENV-01, ENV-03). A machine that would cost money needs the Owner's approval first (COST_POLICY).
- The backup is decrypted on the operator's own machine or on the drill target itself. The decrypted dump and files exist only there; when the decrypting machine and the target differ, the decrypted data pass between them only through an SSH tunnel. At the end of the drill the operator destroys the target's database, volume and every decrypted file on every machine that held one, and the drill record says so. Destroying a drill copy is not a destructive production operation; keeping one "for later" is not allowed (BKP-07).
- The same target, with the latest production backup, is the representative data of a migration rehearsal (RELEASE_PROCESS section 5).

## 9. Recovery key and storage access

| ID | Rule | Source |
| --- | --- | --- |
| BKP-20 | **Creation.** The operator generates each environment's key pair with `age-keygen` on the operator's own machine, never on the server. The recipient goes into the backup container's settings; the private key into the operator's password manager. For production, the operator prints the private key and the storage account access, writes both to an offline USB drive, decrypts a test object with that copy on the operator's own machine (BKP-08) together with the Owner, then seals it and hands it to the Owner (ADR-010 item 3). | D-088 (Q18b) |
| BKP-21 | **Yearly check.** Once a year, at the full recovery estimate, the Owner confirms that the sealed copy is still sealed and stored apart from the shop's devices, and the operator confirms that the password manager still holds the key. | D-088 (Q18b) |
| BKP-22 | **Rotation.** When a holder changes or a leak is suspected: generate a new key pair; set the new recipient; the next run uploads every file again under the new recipient (step 4 detects the old recipient) and every new dump uses it. The rotation **finishes** when the object `state/files-recipient` names the new recipient and 101 days have passed since then: by that day the lifecycle rules of section 6 have removed every superseded version of `files/` (100 days) and every dump and manifest encrypted to the old recipient (at most 93 days and one day of hiding), so that no object encrypted to it is still in the store. The operator confirms this by listing the bucket's versions, then retires the old private key: until then it stays in the same custody, because a restore may need it; afterwards the operator removes it from the password manager, hands over a new sealed copy and lets the Owner destroy the old one. During those 101 days the files count twice in the store's usage (section 6). A suspected leak of a private key is also an incident (RB-05): every copy encrypted to it may be readable by someone else. | SEC-SECRET-04 |
| BKP-23 | **Loss.** If the operator's copy is lost, the sealed copy restores it, and the key is rotated. If both copies are lost, every backup is unreadable: generate a new key pair at once, take a new backup, record the incident and inform the Owner; the live data are unaffected. | ADR-010 |
| BKP-24 | **Storage access.** The account owner's password, recovery codes and key follow the same custody; rotating them updates the sealed copy. The server's key is rotated as ENVIRONMENTS section 16 says. | ADR-010 item 7 |

## 10. RB-01 — Restore after loss or corruption

Used only when the live data are lost, corrupted or untrustworthy and no forward repair is possible (OPERATIONS_RUNBOOK RB-04 prefers repair). Every step is the operator's; the Owner's decisions are marked **Owner**.

**A. Decide**

1. Declare an incident of class S1 (RB-03) and inform the Owner at once.
2. **Owner:** authorize the restore as a production operation: the target, the backup to restore (the latest backup with a stored manifest from before the incident) and the conditions for reopening.
3. Stop writes: put production into maintenance mode, or stop its application container; pause the backup container's schedule.
4. Preserve the original: the old database volume and file volume stay untouched and are neither reused nor deleted; their disposal after the incident is a destructive operation that needs the Owner's approval of that exact operation (BKP-03).

**B. Prepare the target**

5. The target is a new database — after the loss of the server, a new server prepared as RELEASE_PROCESS RB-23 steps 1 to 5 describe. Never the old database.
6. Deploy the release image that the manifest names; a later release is used only if it is compatible with the manifest's migration, and its migrations are then applied afterwards as an ordinary release (RELEASE_PROCESS).
7. Create the roles and the environment marker (ENVIRONMENTS ENV-30), with new passwords.

**C. Restore**

8. Download the manifest, the dump and the files with the operator's storage access to the operator's own machine, and decrypt them there with the recovery key — never on the target or anywhere else on the VPS (BKP-08); compare every SHA-256 with the manifest. On a difference, take the next older backup.
9. Restore the dump into the empty database with `pg_restore` run on the operator's machine through an SSH tunnel to the target database, whose port stays unpublished (ENVIRONMENTS section 9), as the migration role, without owners or privileges; then apply the grants of SCHEMA section 10 with the release image's grants procedure, and run the privilege test against the target (DB01). A difference stops the restore.
10. Copy the decrypted files from the operator's machine over SSH into the new file volume under their storage keys; check each against `stored_files.sha256`.
11. Compare the row counts with the manifest's `counts`; run `reconciliation:run` against the target. A mismatch blocks the products concerned, which RB-04 handles before they reopen. Then delete every decrypted file from the operator's machine (BKP-07); a later need downloads and decrypts again.

**D. Fence the old world, before any user**

12. Run OP-04 `restore:after` (SEC-OP-04): a recovery epoch never issued before, every sign-in session revoked, and an onboarding restore hold when the restored onboarding state is not `RELEASED` (D-072).
13. Advance `sale_number_seq` and `cash_session_number_seq` with the migration role to at least 100,000 above the restored value and above every higher number found in the gap review (section 11), so that no number already in a customer's hands is issued again (SCH-28).
14. Check that `synchronous_commit` is on (ENVIRONMENTS ENV-32).

**E. Reconcile with the Owner, with traffic closed to everyone else**

15. Open the application to the Owner only (maintenance mode with a bypass for the Owner's devices). **Owner:** sign in again on each device used before the incident. Each held request of the Owner is checked automatically: one whose identity is in the restored data answers COMMITTED; any other answers `EPOCH_STALE` and leaves a stale-intent report (ADR-009 section 3).
16. **Owner:** decide each of the Owner's own **user-management** requests first, from the surviving evidence — the standard-output copies of the security log and the platform's logs that survived, and the Owner's own knowledge; the operator records each decision with OP-05 (not committed in the restored data, with a fence).
17. Re-apply the security-relevant account changes made after the backup point that step 16 and the evidence show — deactivations, role removals, link revocations, password resets, an Owner replacement — through the product's own commands and procedures; where there is doubt, require new passwords through links (OP-02 for the Owner, the Owner's links for staff) (SEC-OP-04).
18. **Owner:** decide the onboarding restore hold, if one was set: released before the restore, or never released (D-072); the operator records it with OP-05.
19. **Owner:** decide the Owner's remaining reported requests; the operator records each with OP-05.

**F. Review the gap and reopen**

20. Review the gap as section 11 says, and record the incident's findings.
21. **Owner:** approve reopening. Remove maintenance mode. Staff sign in again; a held request of a staff member answers COMMITTED or `EPOCH_STALE` with a report, which the Owner decides and the operator records with OP-05 when it appears; until then that family stays blocked for that user (D-073).
22. Resume the backup schedule; run a backup at once; check the system status.
23. Record the measured recovery point (from the backup's snapshot to the incident) and recovery time (from step 2 to step 21) in the incident record; they are evidence for gate G4.
24. Close the incident. **Owner:** decide the disposal of the preserved volumes (step 4).

## 11. The gap after a restore

The restored data end at the backup's snapshot. What happened afterwards is lost from the database even though it happened. The review uses only existing, append-only means (BKP-04):

| Subject | Evidence | What is done |
| --- | --- | --- |
| Sales after the backup point | Receipts in customers' hands or on paper, bank transfer statements, the cashiers' account | Not re-created. The incident record lists what the evidence shows — numbers, times, totals, methods — so that the shop's own books can include them; BantuKasir's reports for the gap do not, and the record says so. The highest number seen sets the sequence advance of step 13 |
| Stock | A physical count of the affected products | The Owner adjusts each product to its count with a reason that names the incident (BR-INV-31) |
| Cash sessions | The drawers' cash; the restored sessions | A session restored as OPEN is counted and closed by its user or the Owner once the responsible user's checkout receipts are settled (SCHEMA G-27; ADR-009 section 1); its variance, which includes the lost interval, is explained by an Owner review note that names the incident (BR-CASH-19). A session opened after the backup point does not exist in the data; its cash is part of the count above |
| Receiving, issues, corrections | Paper documents, the Owner's knowledge | Entered again only as new, ordinary commands at the time they are entered, if the Owner wants them recorded; never with an earlier date (BR-INV-05) |
| Anything that cannot be settled | — | Recorded as unresolved in the incident record; the affected postings may stay blocked until the Owner decides (BR-INV-46) |

## 12. Recovery objectives (gate G4)

| Objective | Proposed | Measured as |
| --- | --- | --- |
| Recovery point | ≤ 6 hours | The longest interval between two successful snapshots over the last 30 days, from `backup_runs`, plus the run's duration |
| Recovery time | ≤ 4 hours | The restore time of the latest drill (download, decryption, restore, verification, fence and reconciliation run), plus the installation time of a new server measured at the first installation (RELEASE_PROCESS RB-23), plus the Owner's reconciliation time measured in a staging rehearsal of RB-01 phase E |

The proposals become accepted only when the Owner accepts the measured values, recorded in DECISIONS (gate G4, REQ-NFR-05). The full recovery estimate is repeated yearly with the latest measurements.

## 13. RB-02 — Restore drill

1. Choose the latest production backup with a stored manifest.
2. Prepare the drill target off the VPS (section 8): deploy the manifest's release image; create roles and the marker `drill`.
3. Download the backup to the operator's own machine, or to the drill target itself, and decrypt it there with the recovery key — never on the VPS (BKP-08); compare every SHA-256 with the manifest. When the target is another machine than the one that decrypted, the decrypted dump and files reach it only through an SSH tunnel.
4. Restore the database, apply the grants and run the privilege test; restore the files and check them against `stored_files.sha256`.
5. Compare the row counts with the manifest; run `reconciliation:run` on the target: it must be clean, because the backup came from reconciled data.
6. Rehearse the fence: run OP-04 and the sequence advance on the target.
7. Start the application on the target without traffic and check that `/up` answers 200; reach it locally or through an SSH tunnel and render one recent receipt and one session report through an Owner recovery link issued in the drill copy (OP-02 on the target).
8. Measure the time of each step.
9. Destroy the target and every decrypted file on every machine that held one (section 8).
10. Record the drill in production with OP-08 `restore:record-drill`: kind `ROUTINE`, the backup identifier, the manifest's SHA-256, the result, the measured durations and a note; the system status then shows "Uji pemulihan terakhir" (OPERATIONS_RUNBOOK section 5).
11. A failed drill is an incident of class S3 at least (RB-03), fixed and repeated.

The same runbook on staging's backups rehearses the procedure with synthetic data, with its decryption off the VPS as well (BKP-08); a staging drill is recorded in staging only and never counts for production.

## 14. Open items

| Item | Subject | Step |
| --- | --- | --- |
| Gate G4 | The store account, buckets and keys; the sealed copy; the first production backup and drill; the measured objectives accepted by the Owner | Before the pilot |
| Implementation | `backup:run` is TASK-013's, with REC01 up to its final point at TASK-045; the grants procedure is TASK-004's; OP-04 to OP-08 and the drill tooling, with REC02 and OPPROC01, are TASK-037's | RF-7 Tasks |
| T-21 | RESOLVED by TASK-013: the lifecycle rule of section 6 for superseded versions, and BKP-22 says when a rotation finishes | — |
| T-22 | RESOLVED by TASK-013: section 6 counts both environments, states staging's tiers and sets the threshold with the lock periods | — |
