# Release process

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta) |
| Phase | P10 — release, migration, cutover and rollback |
| Replaces | `docs/10-release/legacy/P10-RELEASE-UAT.md` (readiness, cutover and fallback) and the release and migration section of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-022, D-025, D-071, D-072, D-076, D-087, D-088; [ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md), [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) |
| Related | [UAT_PILOT](UAT_PILOT.md), [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md), [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md), [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md), [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md), [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md), [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md), [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 12 |

How a revision of BantuKasir reaches staging and production, how its migrations are made safe, how a shop is installed and its data onboarded, and how a release is undone. Rules are numbered `REL-nn`; runbooks `RB-nn` share their numbering with the P9 documents; identifiers are permanent. Nothing here authorizes a release: the Owner authorizes each one (gate RELEASE), and the operator, never an AI agent, carries it out (D-088).

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| REL-01 | **One exact authorization per release.** The Owner authorizes a production release by naming its target environment, its revision (tag, commit and image digest), its operation (deploy; the reviewed list of migrations; any operator procedure) and its window. A passing check, a merge, a tag or an earlier approval is not that authorization. | PROJECT_RULES section 5, PDS §2 |
| REL-02 | **The operator releases.** Agents prepare the release — code, migrations, the release record draft, the rehearsals in local environments — and the operator deploys, migrates and verifies (ADR-011 item 1). | D-088 |
| REL-03 | **Staging first, same image.** Every release is deployed to staging with the same image digest and verified there before production (ENVIRONMENTS ENV-05). | ADR-011 |
| REL-04 | **Backed up and drilled.** A production release needs a successful backup less than one hour old, taken by the release runbook; a release with a migration also needs a production restore drill that passed within the last 35 days (BACKUP_RECOVERY RB-02). | PDS §2, ADR-010 |
| REL-05 | **Safe migrations only.** Migrations are forward and additive, classified as section 5 says, rehearsed on a populated isolated target and applied once by the migration job of ADR-011 item 7, never by a container start. | PDS §2, SCH-50, ARC-64 |
| REL-06 | **Destructive needs its own approval.** A migration or step that deletes or rewrites retained data is destructive; it needs the Owner's approval of that exact operation, named in the release record, and the preflight refuses it otherwise. | PDS §1, SCH-54 |
| REL-07 | **Verification writes nothing.** Checking a production release creates no Sale, movement, session or other business fact; it uses reads, the health route and the reconciliation (section 7). | PDS §4 |
| REL-08 | **Rollback is a deploy, never a restore.** A release is undone only by deploying the previous image where the release record states that it is compatible with the current schema, or by a forward fix. Restoring a backup over live data is never a rollback (PDS §1). | PDS §1 |
| REL-09 | **Every release is recorded.** Each release has a release record (section 9) and a release integrity report (section 4). | T-10 |
| REL-10 | **The publication guard applies.** Nothing reaches the public repository or the public registry without the publication guard of D-076: the tag that triggers the release workflow is pushed only with its own authorization, after the guard. | D-076 |

## 2. Who does what

| Step | Agent (executor) | Reviewer (planning agent or a separate session) | Operator | Owner |
| --- | --- | --- | --- | --- |
| Code and migrations of the release | Writes, tests, rehearses locally | Reviews | — | — |
| Release candidate and integrity report | Prepares | Checks | — | — |
| Authorization (gate RELEASE) | — | — | — | Decides (REL-01) |
| Tag and push | Only with its own authorization (REL-10) | — | — | Authorizes |
| Staging deployment and rehearsal | — | — | Performs | — |
| Production deployment, migration, verification | — | — | Performs | Is told the result |
| Release record | Writes it into the repository from the operator's report, without real identities | Checks | Reports | Accepts the release |

## 3. Versions and candidates

- Versions are `vMAJOR.MINOR.PATCH`; `v1.0.0` is the first production release of V1. PATCH: fixes without a schema change; MINOR: features or additive and expand migrations; MAJOR: a release that removes a capability or contracts the schema in a way an older image cannot use.
- A release candidate is one commit on `develop` (ADR-011 item 3) whose CI checks pass and whose release notes list every change, every migration with its class and every Task it closes.

## 4. Release candidate checks and the integrity report

The release workflow (ADR-011 item 5) builds the image and runs, on it:

1. the application starting in role `web` against an ephemeral PostgreSQL with the release's migrations applied, `/up` answering 200;
2. the start-up check refusing a missing or default setting, in both roles (SEC-SECRET-03);
3. a scan of the image's file system for secrets and of its packages for known vulnerabilities, with open-source tools chosen by the operations Task;
4. a check that no development dependency and no test fixture is in the image.

The scan of item 3 reports every finding and fails on a high or critical finding that has a fix, has an unknown fix state, is known to be exploited, or is outside the accepted list, and on a report it cannot trust (`scripts/checks/image-scan-gate.sh`, `scripts/checks/image-scan-accepted.json`; D-112). That list is a temporary acceptance for development: passing it authorizes no deployment to staging or production, and it does not replace the review of section 12.

The **release integrity report**, attached to the release record, states: the version, commit, tag and image digest; the base image digests; the lockfile hashes; the CI run and its results; the suites and their status on this revision (TEST_STRATEGY section 8); the migrations, each with its class and rehearsal evidence; the scan results; the publication guard's result; the compatibility of the previous image with the new schema; known issues; and the cost inventory unchanged or its approved exceptions (COST_POLICY).

## 5. Migration safety

| Class | Examples | Rule |
| --- | --- | --- |
| Additive | A new table; a new nullable column; a new index built concurrently; a new check added `NOT VALID` and validated afterwards | Applied in one release |
| Expand | A new column filled by a backfill | Expand, then backfill in bounded batches outside the request path, then verify, then let the code depend on it — the backfill runs as a console command of the migration job, never in a request |
| Contract | Removing a column or table no longer used; tightening a constraint | Only in a later release, after the expand is in production and verified; destructive when it removes data (REL-06) |
| Destructive | Deleting or rewriting retained data (classes F, FS, M, S, L of SCHEMA section 4) | Never without the Owner's approval of that exact operation (PDS §1); none is foreseen in V1 |
| Privilege | Grants and revokes | By migration only (SCH-45), followed by the privilege test |

| ID | Rule | Source |
| --- | --- | --- |
| REL-20 | Every migration is reviewed as SQL (SCH-51) and carries its class, its expected duration and lock impact, and its verification queries: row counts, constraints valid, privileges equal to SCHEMA section 10, and a clean `reconciliation:run`. A production rollback never relies on a down migration. | SCH-51, PDS §2 |
| REL-21 | Rehearsal on a populated isolated target: with the provisional benchmark dataset in CI or locally for every migration; and, for every migration that changes or moves existing data, on a restore-drill target holding the latest production backup (BACKUP_RECOVERY section 8). An empty database is never enough. | PDS §2, TECHNICAL_RULES section 13.2 |
| REL-22 | DDL waits at most the migration role's `lock_timeout` of 5 seconds (ENVIRONMENTS ENV-31); an index on a large table is built concurrently, in its own migration step outside a transaction. | ENV-31 |
| REL-23 | **Preflight** (`release:preflight`, run by the migration job before it migrates): the operator's typed environment name equals the configuration and the database marker (ENV-04); in production a successful backup less than one hour old exists; the pending migrations are exactly the release record's list; no migration is marked destructive without the approval reference; `synchronous_commit` is on; the backup container's schedule is paused. Any failure stops the job before the first statement. | ADR-011 item 7 |
| REL-24 | A migration that needs no write stop still runs in the authorized window; one that does runs in maintenance mode. | PDS §2 |

## 6. RB-20 — Deploying a release

**Before the window**

1. The Owner's authorization (REL-01) is recorded.
2. The release integrity report is complete; the reviewer has checked it.
3. Staging: deploy the digest, run the migration job with the preflight, run section 7's checks and the staging regression of the release (UAT_PILOT section 2 where the change touches a workflow); record.
4. For a release with a migration: a production drill passed within 35 days (REL-04).

**In the window (production)**

5. Tell the shop when the window starts; the window lies outside opening hours unless the Owner authorized otherwise.
6. Pause the backup container's schedule; run `backup:run --reason=pre-release`; the run must succeed.
7. If the release has migrations: switch on maintenance mode, with a bypass only for the operator.
8. Run the migration job: preflight (REL-23), then the migrations, then the verification queries of REL-20.
9. Deploy the new digest to the application and backup resources.
10. Run the checks of section 7; switch off maintenance mode.
11. Resume the backup schedule and run a backup at once.
12. Report the result to the Owner and for the release record (section 9).

Commands sent while maintenance mode is on are answered before any route runs, so they leave no receipt and no fact; the client treats them as uncertain and resolves them afterwards (INTERACTION_CONTRACTS section 2).

## 7. RB-21 — Checking a release

Read-only checks (REL-07), by the operator, and by the Owner or a staff member where a person must sign in:

1. `/up` answers 200 through Cloudflare; the web heartbeat is green.
2. The privilege check `db:check-privileges` — a read-only comparison of the catalogue with SCHEMA section 10 — passes.
3. `reconciliation:run` is clean.
4. The system status shows current evidence for all four items.
5. A person signs in and opens Beranda, the till page with an OPEN session, a recent receipt and a session report — reads only; no Sale, no movement, no session is created for the check.
6. The logs show no new error.

## 8. RB-22 — Rolling back

1. A failure found before the shop resumes: if the release record states that the previous image works with the new schema (additive and expand migrations), deploy the previous digest; otherwise prepare a forward fix as a new release. A migration that failed part-way on PostgreSQL rolled back in its own transaction; the database stays at the previous migration and the previous digest is deployed.
2. A failure that damaged data is an S1 incident (OPERATIONS_RUNBOOK RB-03): repair by RB-04, or restore by BACKUP_RECOVERY RB-01 with the Owner's authorization — never by restoring over live data.
3. Record the rollback in the release record.

## 9. The release record

Written into `docs/10-release/records/` from the operator's report, one file per release (`RELEASE-<version>.md`), without real identities: the version, commit, tag and digest; the Owner's authorization (date and reference); the window; the pre-release backup's identifier; the migrations applied and their verification results; the checks of section 7; the operator's role (never a name in the public repository); incidents and rollbacks; the integrity report. The folder is created with the first record.

## 10. RB-23 — First installation

For each new shop, before its pilot. Steps 1 to 5 are also the server rebuild of BACKUP_RECOVERY RB-01.

1. Gate TARGET: the server, Cloudflare, the firewall and the certificate (ENVIRONMENTS sections 5 to 9 and 17).
2. Coolify: the project, the environments `staging` and `production`, and their resources (ENVIRONMENTS section 6). Record the time each step takes: it is part of the recovery-time estimate (BACKUP_RECOVERY section 12).
3. The databases, roles and markers of both environments (ENVIRONMENTS ENV-30).
4. The backup store, its buckets and keys, and both key pairs generated on the operator's machine; the production sealed copy tested and handed to the Owner (BACKUP_RECOVERY BKP-20).
5. The heartbeat checks, each alert tested once.
6. Staging: deploy the release (RB-20); rehearse every operator procedure (suite OPPROC01); run UAT (UAT_PILOT section 2); rehearse the cutover of RB-24 with synthetic data.
7. Production, with the Owner's authorization: deploy the release; run OP-01 `owner:bootstrap` with the shop's name and the Owner's email; hand the link to the Owner directly (OPERATIONS_RUNBOOK OPR-06); record the Owner recovery facts (OPERATIONS_RUNBOOK section 8).
8. **Owner:** set the password; complete the BusinessProfile; create the cash registers; create the staff accounts and hand over their links, telling each person to report a password they did not set (D-056).
9. The first production backup and the first production drill; the measured objectives go to the Owner for gate G4 (BACKUP_RECOVERY section 12).
10. Gate DEVICES on staging with the shop's real devices (DEVICE_VERIFICATION).
11. The cutover RB-24, then the pilot (UAT_PILOT section 3).

## 11. RB-24 — Cutover: master data and opening stock

Onboarding is started and released **once** for a shop's business data; after its release, stock enters only through receiving, and corrections use adjustment, reversal and cost correction (D-071, D-072, BR-INV-24). The cutover is therefore rehearsed on staging first (RB-23 step 6).

1. Prepare the product master (gate G2): the CSV import of DC-04 or entry by hand; verify the list with the Owner.
2. **Owner:** start onboarding ("Mulai masa input stok awal", RC-12). Ordinary postings and Sales are refused while it is active (G-10).
3. Count the stock. Enter it as opening stock, at most 100 rows per command, with zero counts on the checklist (RC-11); correct mistakes before the release only by withdrawing a zero count or reversing an OPENING and entering its one replacement (D-087).
4. **Owner:** review the checklist until every product is counted or zero, and the stock list matches the count. No database lock is held while people count (TR-TX-03).
5. Run `backup:run --reason=pre-release-onboarding`.
6. **Owner:** release onboarding ("Akhiri masa input stok awal", RC-13). It is final.
7. Cashiers open their sessions with an explicit opening amount (Rp0 is valid); selling starts and the pilot begins.

## 12. Gate RELEASE for V1

V1 is released for a shop only when PRODUCT_BLUEPRINT section 12 holds: every requirement accepted on the release revision (every suite of ACCEPTANCE_SUITES at FINAL_PASS on it, TEST_STRATEGY section 9); gates P7-G1 to P7-G3 approved (D-082, D-086, D-087); gates G1 to G4, DEVICES and TARGET satisfied with real evidence; the independent review of every vulnerability that remains in the image, which the temporary acceptance of D-112 does not settle, with curl and libcurl reassessed again, the update of `trixie-backports` included (D-113, OPEN_ITEMS T-38); the Owner's authorization of the exact target, revision and operation; and the pilot accepted by the Owner (UAT_PILOT section 3, suite RELEASE01). The Owner's acceptance is recorded in DECISIONS.

## 13. Open items

| Item | Subject | Step |
| --- | --- | --- |
| N-12 | The transition from the planning branch to `develop` and `main` | Before the first implementation Task |
| Gate RELEASE | The Owner names the operator and authorizes the first release | Before the pilot |
| Implementation | The release workflow, `release:preflight`, `db:check-privileges` and the grants procedure, with their tests (DB01, READY01) | RF-7 Tasks |
