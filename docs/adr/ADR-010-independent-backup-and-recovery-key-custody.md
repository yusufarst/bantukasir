# ADR-010 — Independent backup and recovery-key custody

```text
ID:               ADR-010
TITLE:            Independent backup and recovery-key custody
STATUS:           ACCEPTED
DATE:             18 October 2026 (Asia/Jakarta), written in RF-6 (D-088)
DECISION SOURCE:  D-088 (Owner, question 18: an S3-compatible object store on a free tier with a hard
                  spending cap at zero; the operator holds the recovery key and the storage access, and a
                  sealed copy of both is handed to the shop's Owner); D-088 (Owner, question 20: no AI agent
                  operates production); D-005 and ADR-002 item 7 (an independent, encrypted backup is
                  mandatory); the method, the backup container and the layout are planner choices under
                  PROJECT_RULES section 3, recorded in D-088 for the Owner's review
EXTENDS:          ADR-002 item 7; amends ADR-002 item 1 (one backup container per environment)
```

## Context

ADR-002 item 7 requires a backup that is encrypted before it leaves the server, stored outside the server and its volumes, written with its own credential, described by a manifest with checksums that covers the database and every referenced file, and proven by a restore to an isolated target. It left the destination, its cost and the holder of the recovery key to question 18, and the backup job to RF-6. The Owner answered question 18 in D-088:

- **Destination and cost (Q18a):** an S3-compatible object store with a free allowance of 10 GB and a spending cap of Rp0, so that it never bills; an upload over the allowance is refused and the backup status shows the failure; if the data outgrow 10 GB, retention is reduced first, and a paid plan needs the Owner's separate approval.
- **Recovery key and access (Q18b):** the operator keeps the recovery key and the storage account access in a password manager outside the server, for the monthly restore drill and a real restore; a sealed copy of both, printed and on an offline USB drive, is handed to the shop's Owner, so that recovery never depends on one person.

Other inputs: the temporary CSV area is never backed up (D-054); the backup credential is held by the backup process only (SECURITY_MODEL section 8; PRODUCTION_DATA_SAFETY section 3); the backup role has the minimum the method needs (SCHEMA SCH-42); the restore runbook revokes sessions, sets a new recovery epoch and reconciles requests and onboarding (SEC-OP-04, SEC-OP-05) and advances the number sequences (SCH-28); the Owner's system status shows the last backup and restore (REQ-OPS-04); RPO ≤ 6 hours and RTO ≤ 4 hours are proposals until the Owner accepts measured values at gate G4 (REQ-NFR-05). Under question 20 (D-088) a human operator, never an AI agent, operates production and restores.

## Decision

1. **Content.** A backup is one consistent set of:
   - a logical dump of the environment's database: `pg_dump` in the custom format, from one consistent snapshot, without ownership and privilege statements (they are re-applied at restore), with the data of the four ephemeral framework tables `sessions`, `password_reset_tokens`, `cache` and `cache_locks` excluded, so that no sign-in session or one-time link survives a restore;
   - every stored file that a row of `stored_files` names: logos, product photos and thumbnails, each checked against its recorded SHA-256 before it is copied;
   - a manifest (section 5 of [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md)) that names the dump and every file with their sizes and checksums, the release and migration that wrote the data, and the counts the restore verifies.

   Not in a backup: the temporary CSV area (D-054), unreferenced files left by a rolled-back command (TR-TX-05), secrets, the platform configuration (rebuilt from [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md)), the application image (kept in the registry, [ADR-011](ADR-011-release-pipeline-environments-and-production-operation.md)) and logs. Document templates are code (ARCHITECTURE ARC-45), so the release recorded in the manifest reproduces every snapshot.
2. **Encryption.** Every object is encrypted on the server, before it leaves the server, with **age** (open source, BSD-3-Clause) to one X25519 recipient per environment. The server holds only the recipient, which is a public key. The private key — the **recovery key** — is never on the server, in the repository, in the hosting platform or in CI. A compromise of the server therefore cannot decrypt any backup that left it.
3. **Custody (Q18b).** For production, the operator keeps the recovery key and the storage account access in a password manager outside the server. A sealed copy of both — printed, and on an offline USB drive — is handed to the shop's Owner, who keeps it apart from the shop's devices. At handover the operator and the Owner decrypt a test object with the copy before it is sealed. The staging key and access are held by the operator only; staging holds synthetic data. Who holds what, and when the copy was last checked, is recorded in the operator's private record, never in the repository (SECURITY_MODEL SEC-SECRET-05).
4. **Destination (Q18a).** An S3-compatible object store of a provider other than the server's, used within its free allowance, with a spending cap at zero. It must offer: at least 10 GB free; a cap that refuses an operation instead of billing it; Object Lock in governance mode with a retention date set per object; lifecycle rules by name prefix; application keys restricted to one bucket and without the delete and governance-bypass capabilities; the S3 API; and no payment card or contract. **Backblaze B2** meets every requirement as verified on 18 October 2026 (Verified sources) and is the candidate; the operator confirms the service when opening the account at gate G4, with one bucket per environment. The account, bucket names and keys are never written in the repository.
5. **Layout, schedule and retention.** Each backup run writes its encrypted dump and manifest under a tier prefix; files are written once under `files/`. Retention is enforced by the store, not by the server: each tier has a lifecycle rule that hides an object after its period and deletes it a day later, and each object is uploaded with a governance-mode Object Lock retention equal to its tier's period, so that a key from the server can hide an object but not delete it before that date.

   | Tier | Written | Kept for | Copies at any time (about) |
   | --- | --- | --- | --- |
   | `db/6h/` | Every run: 00.00, 06.00, 12.00 and 18.00 Asia/Jakarta, and every on-demand run | 7 days | 28 to 32 |
   | `db/weekly/` | The run of Sunday 00.00, a second upload of the same objects | 35 days | 5 to 6 |
   | `db/monthly/` | The run of the 1st of the month at 00.00, a second upload | 93 days | 3 to 4 |
   | `files/` | Once per stored file, at the first run after it exists, and again at a key rotation | Never removed: no lifecycle rule; a hidden file is never deleted. During a key rotation only, a superseded version of an unchanged file is removed after 100 days (TASK-013, OPEN_ITEMS T-21; [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) section 6 and BKP-22) | One per stored file |

   The object `state/files-recipient`, a small text without a lifecycle rule, says which recipient the current files are encrypted to (BACKUP_RECOVERY section 4; TASK-013).

6. **Where it runs.** Each environment runs one **backup container** in addition to the application container and PostgreSQL. It is started from the same release image as the application, with the process role `backup`; it serves no HTTP, publishes no port, mounts the environment's file volume read only, and its scheduler runs only the backup command (ROUTE_CONTRACTS SC-03). Its environment alone holds the backup database credential, the storage key and the recipient; the application container never holds them, and the backup container never holds the runtime or migration credential. This amends ADR-002 item 1, which named one application container and one PostgreSQL per environment.
7. **Credentials.**
   - The backup database role (SCHEMA SCH-42): `CONNECT`, `USAGE` on the schema, `SELECT` on every table and sequence, and `INSERT` on `backup_runs`, which records each run for the Owner's system status (SCHEMA section 5.12). Nothing else.
   - The storage key: restricted to the environment's bucket, with `listFiles`, `readFiles`, `writeFiles` and `writeFileRetentions` only — never `deleteFiles`, `bypassGovernance` or a bucket-settings capability. The account owner's key, which can change the bucket and remove locks, is part of the storage account access of item 3 and is never on the server.
8. **Integrity.** A run checks the dump archive before encrypting it, hashes every object it uploads, uploads with the store's integrity check, confirms each stored object's size, checks every file against `stored_files.sha256`, and writes the manifest last. A backup exists only when its manifest is stored. Once a week the run downloads the latest weekly objects again and compares their SHA-256 with the manifest's values, without decrypting them. Decryption and a real restore are proven by the monthly drill (item 10).
9. **Status and alerting.** Every run writes one row of `backup_runs` (SCHEMA section 5.12), from which the Owner's system status shows "Cadangan data terakhir" (OPERATIONS_RUNBOOK section 5). A successful run also pings the external heartbeat of [ADR-011](ADR-011-release-pipeline-environments-and-production-operation.md) item 11, so that a backup that stops altogether is noticed outside the server.
10. **Restore and drills.** A backup is never restored over the live database (PRODUCTION_DATA_SAFETY section 1). It is restored only into a new, isolated target, by the operator, under the restore runbook RB-01 of BACKUP_RECOVERY, whose order of steps puts SEC-OP-04, SEC-OP-05 and the sequence advance of SCH-28 before the shop reopens. A restore drill (RB-02) restores the latest production backup into an isolated drill target at least once a month, after any change of the schema, the backup tooling or the storage, and before the pilot; its result is recorded in production by the operator procedure SEC-OP-08 and shown as "Uji pemulihan terakhir".
11. **Capacity.** The status shows the store's usage against the free allowance. The operator applies the reduced retention of BACKUP_RECOVERY section 6 at its threshold, which allows for staging's share and for the lock periods (TASK-013, OPEN_ITEMS T-22), and at the latest at the 80 % of the status; if even the reduced retention does not fit, the Owner decides on a paid exception (COST_POLICY). A real restore downloads less than the stored total, inside the free download allowance; should the store refuse a download during a real restore, raising the cap is a cost the Owner approves at that moment.
12. **Recovery objectives.** The six-hourly schedule serves the proposed RPO of 6 hours; the drills measure the restore time. Both stay proposals until the Owner accepts the measured values at gate G4 (REQ-NFR-05).

## Alternatives considered

| Alternative | Why not |
| --- | --- |
| The hosting platform's own scheduled PostgreSQL backup to S3 | It copies the database only, not the files, and does not encrypt to an offline key; ADR-002 item 7 already rules it out as the only backup |
| restic, Borg or rclone's encryption | Symmetric encryption: the key that writes the backup is on the server and also reads it; pruning needs delete rights on the store, which a compromised server could then use |
| Pulling copies to the Owner's own computer or drive | Declined by the Owner (D-088): it depends on that device being on and connected, which weakens the recovery point |
| A paid storage plan from the start | Declined by the Owner (D-088): free first; a paid plan needs a separate approval |
| A snapshot or second disk at the server's provider | Not independent of the provider, and usually a recurring cost |
| Running the backup in the application container | The web process would hold the backup credential and the storage key (SECURITY_MODEL section 8) |

## Consequences

- One more small container per environment, from the same image; no new service and no recurring cost.
- The backup role gains one `INSERT`; the privilege test follows it (SCHEMA SCH-42).
- The operator opens and administers the storage account and the heartbeat monitor; the Owner keeps the sealed copy.
- The free allowance bounds what can be kept; retention shrinks before any cost is incurred.
- A compromise of the server can hide recent copies but cannot delete them before their lock ends, and cannot read any of them. During a key rotation's window it could also supersede a file, whose good version the window's rule removes after 100 days (BACKUP_RECOVERY section 6).
- Losing both the operator's copy and the sealed copy of the recovery key makes every backup unreadable; key rotation and loss are BACKUP_RECOVERY section 9.

## Framework conformance

```text
ADR REQUIRED:      YES (completes ADR-002 item 7; amends ADR-002 item 1)
DEVIATION:         NONE — AICWDF §4C requires an independent backup whatever the cost target
OWNER APPROVAL:    GIVEN — D-088 (question 18: destination, cost, custody); the method, container and layout
                   are planner choices recorded in D-088 for the Owner's review
REASON:            an independent, encrypted, verifiable backup at zero recurring cost, unreadable on the server
RISK:              the free terms may change (record and exit path: ENVIRONMENTS section 15); a compromise of the
                   server can hide recent copies; the recovery key must not be lost
MIGRATION IMPACT:  none — nothing is implemented
ROLLBACK IMPACT:   none
```

## Verified sources

Checked on 18 October 2026. Version numbers are observed facts, not pins (STK-01).

| Source | Fact used |
| --- | --- |
| https://www.backblaze.com/cloud-storage/pricing | "First 10GB storage is always free"; storage from $6.95 per TB per month; free egress up to three times the average stored data; no minimum file size or storage duration fees |
| https://www.backblaze.com/sign-up/cloud-storage | "No credit card required" |
| https://www.backblaze.com/docs/cloud-storage-data-caps-and-alerts | Caps limit daily spending for storage and for Class A, B and C transactions; without caps usage is unlimited; counters reset daily at 00.00 GMT; alerts at 75 % and 100 % of a cap |
| https://www.backblaze.com/docs/cloud-storage-upload-files-with-the-native-api | An upload over the storage cap is refused with `cap_exceeded` |
| https://www.backblaze.com/docs/cloud-storage-lifecycle-rules | Rules by `fileNamePrefix` with `daysFromUploadingToHiding` and `daysFromHidingToDeleting`; up to 100 rules per bucket; applied once a day; lifecycle deletion does not apply to a version under Object Lock |
| https://www.backblaze.com/docs/cloud-storage-application-keys and https://www.backblaze.com/docs/cloud-storage-application-key-capabilities | Keys restricted to one bucket and a name prefix; `writeFiles` covers uploads and hiding; `deleteFiles` covers deleting a file version; `listFiles` and `readFiles` as named |
| https://www.backblaze.com/docs/cloud-storage-object-lock | Object Lock can be enabled at or after bucket creation and not disabled; governance mode settings can be overridden only with the right key capabilities, compliance mode not at all; no extra cost |
| https://github.com/FiloSottile/age | age encrypts to one or more recipients (`-r`), decrypts with an identity file (`-d -i`), supports passphrase-protected identity files; BSD-3-Clause |
| https://www.postgresql.org/docs/current/app-pgdump.html (PostgreSQL 18) | pg_dump makes consistent exports while the database is in use; it dumps one database, not roles; `--no-owner` and `--no-privileges`; the custom format is compressed and restored with pg_restore; it runs `SELECT` statements |

## Related

[ADR-002](ADR-002-minimum-runtime-infrastructure.md), [ADR-003](ADR-003-hosting-topology.md), [ADR-011](ADR-011-release-pipeline-environments-and-production-operation.md); [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md), [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md), [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md); [SCHEMA](../04-architecture/SCHEMA.md) SCH-42 and section 5.12; [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 8, SEC-OP-04, SEC-OP-05, SEC-OP-08; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md); [COST_POLICY](../00-governance/COST_POLICY.md); OPEN_ITEMS question 18 and gate G4. Superseded legacy content: the backup and retention paragraphs of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` (LEGACY — SUPERSEDED in RF-6).
