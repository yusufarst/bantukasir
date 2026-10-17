# Production data safety

**There is no production system yet, and no production operation of any kind is authorized.** These rules are recorded now so that they are already in force on the day production data exists.

Sections 1 to 4 restate protections that already existed before the re-foundation, in the legacy operations document and in the Owner's Project instructions. They are intended to change nothing. Section 5 records the Owner's decision on who operates production (question 20, D-088), which adopts the stricter rule of AICWDF-4.3.

## 1. Never, without the Owner's approval of that exact operation

- `DROP DATABASE`, `DROP SCHEMA`, or dropping a production table that holds data;
- `TRUNCATE`, or mass deletion of business records;
- resetting or recreating the production database, or seeding demo data into it (for example `migrate:fresh`, `db:wipe`, or a reset script presented as maintenance);
- a destructive migration;
- deleting production database volumes or persistent directories (for example `docker compose down -v`, `docker volume prune`);
- deleting stock movements, completed sales, payments, receipt snapshots, session report snapshots, audit events, or cost facts and their history;
- silently rewriting historical stock or financial facts, including editing production data by hand to hide an application defect;
- restoring a backup over the live database.

A general request such as "fix production", "deploy", "run the migration" or "do whatever is needed" is **not** approval of a destructive operation.

If a destructive operation appears necessary: **stop**. State the exact operation, the data at risk, the reason, the safer alternatives, the migration and backfill plan, the backup and restore prerequisites, and the rollback or recovery path. Approval must name that exact operation; approval to deploy or to migrate is not approval to destroy.

## 2. How schema and data change instead

1. Design a forward, additive migration: expand, migrate or backfill, verify, and only then contract.
2. Rehearse it on an isolated non-production environment that holds representative data, never on an empty database only.
3. Verify a backup and the ability to restore before any risky change.
4. Obtain the Owner's authorization for the release: the exact target, revision and operation.
5. Apply the migration once, as a controlled job in the authorized release, never as a side effect of application start-up.
6. Verify afterwards: row counts, constraints, source integrity and business reconciliation.

A reset of test data is allowed only on a test database that has been positively identified and authorized for that Task. The name of a folder or a database does not prove that a target is safe; verify the environment, host, database and storage before running anything that changes data.

## 3. Privilege separation (requirement for implementation)

- The runtime database role cannot change the schema and cannot update or delete immutable business facts.
- Migration credentials are separate and are not available to the web runtime.
- Backup uses its own credential with the minimum access it needs.
- These separations are tested, including denial of schema changes and history changes from the runtime role.

## 4. Long-term data

Assume years of real records. Old records are valuable. Every change preserves existing data and its meaning, preserves compatibility where practical, reproduces problems in local or staging environments with synthetic or anonymized data, and handles historical anomalies through controlled, traceable remediation.

## 5. Who operates production (question 20, D-088)

The Owner decided question 20 on 18 October 2026 with the AICWDF-4.3 rule (§29, §30). R-16 is resolved; no framework deviation is needed.

- **No AI agent** connects to, deploys to, migrates, restores or runs an operator procedure on production — by any means — and none holds a credential of the server, the hosting platform, Cloudflare, the backup store or the heartbeat monitor. Staging shares the production server, so this includes staging.
- **Agents prepare and rehearse**: they write the code, the migrations and the runbooks, rehearse them in isolated local environments with synthetic data, and document the risk.
- **A human operator applies** every staging and production operation through the controlled release procedure of [RELEASE_PROCESS](../10-release/RELEASE_PROCESS.md), after the Owner has authorized the exact target, revision and operation (gate RELEASE). Routine operations under the approved runbooks — the scheduled jobs, the monthly restore drill, reading status — need no new authorization each time ([OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md) OPR-01).
- Changing production database credentials or ownership ad hoc is forbidden; credentials change only as [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 16 describes.
- Section 1 still applies to the operator: a destructive operation needs the Owner's approval of that exact operation.

The architecture of this rule is [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md).

## 6. Where the detail is

Backup, restore and the reconciliation of a restore: [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md). Operator procedures, incidents and maintenance: [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md). Environments, access and secrets: [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md). Releases, migrations and the cutover: [RELEASE_PROCESS](../10-release/RELEASE_PROCESS.md). All were written in RF-6 (D-088) and replace the legacy operations document.
