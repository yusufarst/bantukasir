# 09 — Deployment and operations

Target design only. No deployment, Compose file, running backup or job exists. Use the client's existing VPS and domain; any extra recurring purchase requires explicit approval. Open-source software does not make storage or recovery free.

## Recurring cost audit

| Capability | Default Core choice | Software/SaaS recurring cost |
| --- | --- | ---: |
| App hosting | Existing client VPS + Docker Compose/Caddy | Rp0 additional software |
| Database | Self-hosted PostgreSQL | Rp0 |
| Authentication | Self-hosted Better Auth | Rp0 |
| Background jobs/outbox | PostgreSQL + same-codebase worker | Rp0 |
| Search | PostgreSQL indexes/search first | Rp0 |
| Barcode generation | Open-source library + browser print | Rp0 |
| Receipt/A4/shift reports | HTML/CSS browser print / print-to-PDF + first-party CSV | Rp0 |
| Notifications | In-app inbox + Web Push | Rp0 service fee |
| Analytics/reporting | First-party SQL/reporting | Rp0 |
| CMS/public content later | First-party structured DB content | Rp0 |
| Backup | Independent encrypted destination | Existing owned storage may be Rp0; otherwise explicit owner-approved infrastructure cost |

Do not introduce a paid dependency merely for convenience. Cost reduction never justifies weakening backup, security, audit or transaction integrity.

## Topology and portability

One Linux VPS runs Compose services: Caddy, Next.js web, worker from the same image, PostgreSQL and scheduled backup. Only Caddy exposes public 80/443 by default; host bindings remain deployment configuration. Database is private. Separate persistent database and file volumes, least-privilege permissions, non-root app/worker and pinned image versions/digests; no production latest tags.

Caddy provides HTTPS/reverse proxy. Configure hostnames, certificate contact, request limits, security headers and trusted proxy behavior at deployment. No auth/stock response caching. Central APP_ORIGIN identifies the internal application; PUBLIC_SITE_ORIGIN later identifies the public site. Actual deployment domains belong in private operator configuration, not universal product identity. Do not assume the final internal hostname/subdomain.

VPS IP, SSH host/user/keys, credentials, port mappings and absolute storage paths live in private operator configuration. Code uses logical volumes/object keys and validated environment settings. Migration to another VPS restores database/media/configuration, changes DNS/origins and verifies HTTPS/auth/push/backup; it must not require application source edits.

Separate development, test, staging/pilot and production databases, secrets, subscriptions and backup destinations. Staging cannot notify production recipients. Run migrations once as a controlled job; web startup must not race migration.

Release sequence: applicable review/quality gate → immutable image → verified backup → maintenance if incompatible → controlled migration → start web/worker → readiness/smoke → traffic → monitor. Prefer expand/contract changes. Code rollback requires compatible schema; destructive migrations need explicit recovery planning.

## Runtime configuration

[.env.example](../.env.example) holds empty values only. Store real values in protected runtime files/mounts, never committed Compose files or screenshots. Validate types, URLs/origins and secret strength. Errors name missing variables without exposing values.

| Variable | Component/requirement | Classification |
| --- | --- | --- |
| NODE_ENV | Web/worker; validated development/test/production | Nonsecret |
| APP_ORIGIN | Web; configured internal origin, HTTPS in production; CSRF/push links | Deployment setting |
| PUBLIC_SITE_ORIGIN | Public website once enabled; public canonical links/SEO | Deployment setting, no assumed subdomain |
| STORAGE_ROOT | Core import worker/web; configured private writable root/mount | Deployment path, never hardcoded |
| DATABASE_URL | Web/worker least-privilege runtime account | Secret |
| MIGRATION_DATABASE_URL | Migration job only, separate schema owner | Secret, absent from web environment |
| BETTER_AUTH_SECRET | Auth; required entropy under pinned library | Secret, no fallback |
| BETTER_AUTH_URL | Auth; consistent with APP_ORIGIN | Deployment setting |
| PUSH_ENABLED | Explicit boolean; false only development or recorded degradation | No silent default |
| VAPID_PUBLIC_KEY | Required when push enabled; limited client endpoint | Public key |
| VAPID_PRIVATE_KEY | Push worker | Secret |
| VAPID_SUBJECT | Push worker operator contact | Private deployment configuration |
| BACKUP_DATABASE_URL | Dedicated backup account | Secret |
| BACKUP_DESTINATION | Off-host destination, transport credentials separate | Private configuration |
| BACKUP_ENCRYPTION_RECIPIENT | Backup encryption public recipient key | Deployment value kept out of Git |
| BACKUP_DECRYPTION_KEY_FILE | Restore operator only, protected private key path | Secret file, absent from web/worker/routine backup |
| POSTGRES_DB, POSTGRES_USER | Container initialization | Private deployment configuration |
| POSTGRES_PASSWORD_FILE | Mounted database initialization secret | Secret file |

Backup adapter credentials are mounted separately, not embedded in destination/logs. Choose and verify the actual destination/recovery access before pilot; do not add speculative SaaS keys.

Missing required settings fail readiness for the relevant component. Disabled features display **Belum Aktif**, never success. PUSH_ENABLED=false can support development but does not satisfy the real owner-channel gate. No secret uses NEXT_PUBLIC. WhatsApp number/templates belong to published database content, not environment variables.

## Local file storage

Use a storage adapter with logical keys under configurable STORAGE_ROOT. Separate private imports/error exports, private evidence attachments when introduced, and intentionally published media. Private directories MUST NOT be mounted as public web roots. Serve private objects through authenticated handlers; static public assets contain no serial labels/documents.

- Import limits under [14](14-BULK-IMPORT.md): 10 MiB input, 50 MiB expanded XLSX, 100 archive entries and 5,000 data rows.
- Later product/hero images: at most 10 MiB input and 20 megapixels; allow JPEG/PNG/WebP, verify signatures, decode/re-encode, strip metadata, bound CPU/memory, and create sized variants. Reject SVG/HTML/user scripts and arbitrary remote URL fetches. Trusted owner-provided logo source files are reviewed separately before packaging.
- Random/immutable object keys, no user-supplied filesystem paths; reject traversal and executable uploads. Files have no execute permission; owner-only filesystem access plus restricted runtime group.
- Store object metadata/checksum/reference in PostgreSQL. Publish only validated derived assets. Do not physically remove objects still referenced by published revisions or retained backup manifests.
- Monitor disk and cleanup terminal staging under retention rules. Back up committed media with DB-referenced manifests.
- Future S3-compatible storage changes the adapter/configuration, not business identities. Copy objects, verify counts/checksums/references, switch adapter, verify access/restore, then retire the old copy under retention. Do not automatically purchase object storage.

## Recoverable backup

Pilot targets: **RPO ≤6 hours, RTO ≤4 hours**, subject to owner acceptance and measured restore. If loss of six hours is unacceptable, design WAL/PITR before go-live.

1. PostgreSQL custom-format logical dump every six hours. Encrypt before off-host copying; verify checksum and archive readability. Record snapshot time, schema/app version, size, safe destination and job outcome.
2. Use a consistent database dump, not a copy of a live database volume. Restore cluster roles/extensions/privileges separately. Reference: [PostgreSQL SQL dump](https://www.postgresql.org/docs/current/backup-dump.html).
3. Retain all six-hour backups for seven days, one weekly for four weeks and one monthly for three months. Never delete the last verified backup because the newest upload is unverified.
4. Maintain at least one encrypted copy on a separate host/device/account from the VPS. Another volume on the same VPS does not protect total host loss. Restrict backup credentials from deleting all copies; use versioning/immutability where available.
5. Keep recovery decryption keys outside the VPS, accessible to authorized owner/operator. Test the real recovery key path privately.
6. Backup immutable media and a manifest consistent with database references. Retain referenced objects; if nonimmutable files exist, briefly freeze uploads to coordinate the snapshot. DB-only backup does not restore media.

Cheapest responsible option: an already-owned independent device/host or existing storage account with reliable automated transfer and checks. If none meets reliability/capacity requirements, recommend a small offsite storage cost for owner approval. The vendor choice is optional; a reliable off-VPS copy is a mandatory go-live control. Spreadsheet exports are not backups.

## Restore runbook

1. Declare incident/cutoff to owner; stop posting, external delivery and automatic import/valuation resume. Preserve the old source read-only.
2. Select a verified backup, match checksum/version and decrypt on an isolated recovery host. Record snapshot time and potential missing transaction window.
3. Prepare compatible PostgreSQL, roles/extensions/permissions and referenced media. Restore into a **new database**, failing on errors rather than masking partial recovery.
4. Reconcile Orders/revisions, append-only payments/refunds, goods fulfillments and their ledger legs, service jobs/progress/completions, cashier shifts/cash events/counts, unique close receipts and ShiftCloseReportSnapshots with source cutoffs/revisions, document snapshots, ledger/balances, serial positions, reservations, health/episodes/events/outbox, unique constraints and ImportCommit/CommandReceipt. Verify media checksums; run ANALYZE and controlled smoke checks.
5. Revoke restored sessions; assess credential rotation. Establish a new recovery epoch before reopening commands so pre-restore browser envelopes cannot execute as new work. Suppress stale pre-cutoff push replay by default while preserving inbox/history. Resume delivery only after reconciliation.
6. Reconcile physical goods, printed/payment evidence, actual money and performed service work after the snapshot. A missing restored receipt does not prove the original never committed. Decide replay/correction from evidence before resubmitting Orders/payments/fulfillments or requeuing imports; never re-charge, refund or issue by assumption and never use SQL balance edits.
7. Finance checks sequences, evidence versions, clearing, watermarks and published pointers. Reports stay incomplete until reconciled. CMS publication pointers/assets must resolve to the restored published revisions.
8. Owner confirms remaining business differences; operator switches traffic and validates auth/scan/commit/attention before reopening posting. Record actual RPO/RTO, backup identity, checks and follow-up.

Full restore drill before pilot, monthly, and after major schema/storage/backup changes. A successful backup job alone does not pass acceptance.

Shift-report restore acceptance: verify every CLOSED shift has its unique close/snapshot/command receipt, reconcile source facts and variance, and reproduce original allowlisted report content after later corrections. Retain referenced identity/logo/template revisions with snapshots. Re-export creates no money/stock facts. Print/PDF/CSV files are conveniences, not substitutes for database/media backup; later linked corrections do not overwrite restored historical snapshots.

## Health and schedules

Minimal liveness exposes no sensitive configuration. Readiness checks database/schema/configuration; restrict detailed health endpoints. Owner system view shows backup/drill times, heartbeat, outbox age/failures, reconciliation and disk. Unknown is **Belum Terverifikasi**, never green.

| Check | Initial schedule/threshold | Response |
| --- | --- | --- |
| Worker heartbeat | Each minute; missing >3 minutes | Inspect worker/logs, no stock rewrite |
| Outbox | Continuous polling; oldest pending >5 minutes | Inspect provider/lease/config; inbox remains authoritative |
| Reconciliation | Daily and after restore | Mismatch blocks affected posting and raises incident |
| Backup | Every six hours; failed or last success >7 hours | Alert and repair without deleting last valid copy |
| Disk | Warning <20% free, critical <10% | Capacity/authorized retention action before disk full |
| Restore drill | Monthly/major changes | Measure recovery; failed drill blocks release |
| Dependency/security updates | Periodic and before release | Reviewed branch/tests, no blind auto-update |
| Imports/valuation | Age/lease/error/backlog monitoring | Reconcile receipts and fencing before retry; no false progress |

Structured logs include request/command/job IDs, duration, safe error code and necessary actor ID. No full payloads, passwords, cookies, raw serials or credential URLs. Log count is not transaction count.

A dead VPS cannot notify from itself. Before production use an independent existing device/host check or an approved service. If unavailable, record the detection gap; do not claim full monitoring or silently buy SaaS.

## Retention

| Data | Initial policy |
| --- | --- |
| Ledger, historical actors, business audit, receipts, shift-close snapshots, episodes/events | No automatic deletion; retain for platform lifetime with traceable archive |
| UserNotification | UI archive after 90 days; open episodes stay accessible |
| Delivery attempts/debug logs | 30 days; final event/result links remain |
| Security access/login logs | 90 days, restricted; incident records held until resolved |
| Expired sessions/tokens | Library-appropriate cleanup, no actor-audit deletion |
| Browser drafts | Under 12, never treated as ledger |
| Customer/Order PII | Core collects only required customer context; define purpose/access/retention before collection. Immutable business history is not permission to keep all PII forever |
| Raw import/error files | Private; purge seven days after definitive terminal outcome, never while uncertain; READY revalidation after 24 hours |
| Successful import manifest/ImportCommit | Durable with related business/audit history, separate from raw-file TTL |
| Cost evidence/revenue/cost events/report versions | Private, final immutable, versioned correction; archival policy reviewed for actual finance needs |
| Published content/settings revisions | Preserve publication/audit lineage; no deletion of still-referenced media |
| Optional outbound click events | 90 days, minimal non-PII fields; aggregate only if useful |

These are operational policies, not a legal compliance claim. Review contractual/business needs before changing retention. Sensitive purge is scheduled, authorized and audited; never expose delete-history controls.

## Incident ownership

Owner decides business action; authorized operator performs recovery. Keep real contacts/host/key access in private runbooks outside Git. Operator handoff includes recovery access and a drill, never public production passwords.

Private import parsing uses bounded resources and a separate execution slot from notifications. Do not report parsed rows as committed rows. Cleanup must respect active/uncertain jobs. Successful manifests remain restorable after raw files expire. Exports omit schema constraints, ledger links, sessions/queues and runtime configuration; they cannot replace database/media backup.
