# Production data safety

**There is no production system yet, and no production operation of any kind is authorized.** These rules are recorded now so that they are already in force on the day production data exists.

Sections 1 to 4 restate protections that already existed before the re-foundation, in the legacy operations document and in the Owner's Project instructions. They are intended to change nothing. Section 5 records one stricter rule from AICWDF-4.3 that the Owner has **not** yet decided; it is not in force.

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

## 5. Open Owner decision — who may operate on production (R-16, question 20)

`OWNER_REVALIDATION_REQUIRED`. **Not decided. Not in force.**

| | |
| --- | --- |
| Earlier BantuKasir rule | An agent may carry out a production release, migration or cutover when the Owner has authorized that exact target, revision and operation. The former plan assigned the production cutover to an executor work unit after such authorization. |
| AICWDF-4.3 rule (§29, §30) | Ordinary coding agents never connect to or mutate a production database, by any means. Agents design a migration, write it, rehearse it outside production and document the risk; a controlled executor applies it after approval. Changing production database credentials or ownership ad hoc is also forbidden. |
| Why it needs the Owner | The two rules differ on who operates a production release. That is release and cutover risk, which is the Owner's to decide. Keeping the earlier rule would be a departure from the framework that needs explicit approval (AICWDF-4.3 §41). |
| Planner's recommendation | Adopt the framework rule. |
| Effect until decided | None in practice: no release, migration, cutover or other production operation is authorized, so no agent may touch production under either rule. The decision is needed before release planning is rebuilt (step RF-6). |

The question is listed as number 20 in [OPEN_ITEMS](OPEN_ITEMS.md). When the Owner decides, record the decision in [DECISIONS](DECISIONS.md) and rewrite this section as a rule.

## 6. Where the detail is

Backup, restore, retention, reconciliation and recovery runbooks are in the legacy operations document, `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md`, until operations planning is rebuilt in RF-6.
