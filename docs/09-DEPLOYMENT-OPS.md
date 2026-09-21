# 09 — Deployment, protected data and recovery

Target design only. No deployment, migration, backup job or runtime is created by Plan 1.0.

## Protected production database — mandatory for every agent/operator

Production holds valuable live business records. Without explicit owner approval for the **EXACT destructive operation**, NEVER:
- DROP DATABASE, DROP SCHEMA or DROP populated production tables;
- TRUNCATE or mass DELETE business records;
- reset/recreate production, seed demo data, execute destructive reset scripts or migrations disguised as maintenance;
- delete PostgreSQL/Docker production volumes, persistent DB directories, or run `docker compose down -v` against production;
- delete StockMovement, completed Sales, Payments or AuditEvent history;
- silently rewrite historical inventory/financial facts.

Generic “fix”, “deploy” or “run migration” is not destructive approval. If needed, STOP and report: why; data at risk; safer alternatives; migration/backfill plan; backup/restore prerequisites; rollback/recovery plan. Non-production reset requires positively identified target and task authorization. Commands must verify environment/host/database before changing data, never infer safety from a directory name.

Default: additive/forward migrations; expand → migrate/backfill → verify → contract; populated isolated non-production rehearsal; backup before high-risk migration; restore readiness; transactional migrations where possible; post-migration row-count/constraint/business reconciliation. Runtime DB account cannot drop schema or update/delete immutable business facts. Migration credentials are separate and unavailable to web runtime. Reviewed destructive changes still require exact owner authorization.

## Runtime and cost

Existing VPS/domain; Compose with Caddy/HTTPS, non-root app, private PostgreSQL and scheduled backup/reconciliation commands. No public DB port. Pin compatible image/dependency versions, no production latest tags. Separate dev/test/staging/production DBs/volumes/secrets. Run migration once as controlled job, not racing web startups.

Approximately Rp0 additional recurring software: self-hosted auth/PostgreSQL, native search, browser print/PDF, first-party CSV, open-source barcodes and in-app restock. No paid auth/DB/search/queue/analytics/PDF/notification/UI credit service without explicit approval. Off-VPS backup capacity may cost money; do not pretend same-host backup is enough.

Runtime config: app origin, DB URL, migration DB URL, auth secret/origin, storage root, backup destination/credentials/encryption recipient; private restore key stored independently from VPS. Validate required settings; no silent production defaults or NEXT_PUBLIC secrets. Existing inherited .env.example is not a validated implementation contract; BK06/BK32 reconcile it when authorized. Never commit real credentials, addresses containing secrets or production data.

Retain immutable private logo assets referenced by receipts/reports; DB manifest/checksums. No public document storage. Simple image limits: <=5 MiB, <=16 megapixels, verified PNG/JPEG/WebP, re-encode/strip metadata, no executable/SVG/HTML/remote URL fetching. Storage paths configured, never client-specific source edits.

## Release procedure

Gate evidence + tests/build → immutable image → backup verified → populated migration rehearsal → authorized deployment window → controlled migration → reconciliation/readiness/role smoke tests → owner pilot. Use maintenance/write-stop if schema change requires it. Code rollback only with compatible schema; otherwise forward repair or rehearsed isolated recovery. No deploy/push/merge permission is granted by a planned task row.

## Backup and G4

Owner must choose independent destination and accept measured RPO/RTO before production. Proposed starting target **RPO <=6 hours / RTO <=4 hours is UNACCEPTED**, not a client fact. If unacceptable, evaluate more frequent dumps/WAL/PITR and adjust scope/capacity before pilot.

Scheduled consistent PostgreSQL dump, encrypted before off-VPS transfer, checksum/archive validation and metadata (snapshot/schema/app version). Keep at least seven days of six-hour copies, four weekly and three monthly copies, subject to capacity/owner policy. Never remove last verified good copy because a new upload exists. Independent host/device/account and separately accessible recovery keys required; same VPS volume does not qualify. Back up referenced media/manifest consistently; exports/PDF are not backups.

Real isolated restore before pilot, monthly and after major schema/storage changes. A green backup process alone does not pass. Owner system view exposes last successful backup/restore, age/failure/disk/reconciliation status, never secrets; unknown says “Belum Terverifikasi”. Independent existing host/device availability check is needed to detect dead VPS; if unavailable report gap rather than claim monitoring works.

## Restore runbook

1. Stop writes, declare incident/snapshot cutoff, preserve source read-only.
2. Verify/decrypt chosen backup, match app/DB versions, restore into a new isolated database and storage root; never destroy original.
3. Verify roles/extensions/constraints and referenced asset checksums.
4. Reconcile Sale/lines/Payment/SALE_ISSUE/receipt, all stock sums/balances, acquisition/valuation revisions, refunds/returns, daily source scopes/cutoffs/count/variance/snapshots, command receipts and audit. Missing finalized snapshot or mismatched stock blocks reopening.
5. Revoke restored sessions, establish new recovery epoch. Old browser intents cannot auto-execute as new work. Review post-snapshot cash/transfer/printed documents/physical stock; missing restored result does not prove original never committed.
6. Owner resolves missing interval from evidence using authorized append-only correction/recovery procedures, never SQL balance editing or blind payment replay.
7. Verify auth/search/scan/posting/report/print and independently retained recovery access. Record actual RPO/RTO/data gap; owner approves traffic reopening.

## Maintenance and retention

Daily read-only stock/cost/source reconciliation; failed check blocks affected posting. Backup age warning after configured schedule plus grace; disk warning at 20% free, critical 10%. Structured logs use IDs/safe errors, no full sensitive payload. No automatic deletion of ledger/Sales/Payments/audit/receipts/daily reports/cost revisions or referenced identity assets. Operational debug logs 30 days, security access logs 90 days subject to approved incident/privacy policy; expired auth tokens follow library retention without erasing historical actors. No legal compliance claim.

Keep actual host/key/contact runbook privately accessible to owner/operator. G4 includes independent recovery access, measured drill and sustainable storage cost.
