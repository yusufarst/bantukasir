# TASK-004 — Database foundation, roles and test harness

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 1 Foundation · lane P, platform trunk |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-001](TASK-001-toolchain-ci.md), [TASK-003](TASK-003-kernel-localization.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-004-database-foundation` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for the pinned PostgreSQL and Laravel versions (EXC-03). |
| Modules | Infrastructure (database), Test harness |
| Baseline | RF-7, D-090 |

## 1. Goal

Establish PostgreSQL of the pinned version with the three roles and their grants, the reviewed setup script, the migration discipline, the privilege check, the verified PostgreSQL features that SCHEMA relies on, and the harness for constraint, privilege, race and failure-injection tests that every later Task uses.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SCHEMA sections 1, 2, 3 (all), 4, 7, 9.4, 10, 11, 13 and 15
- ENVIRONMENTS ENV-04 and ENV-30 to ENV-33
- TECHNICAL_RULES section 9 (time limits) and TR-TX-06
- TEST_STRATEGY sections 1 to 5
- PRODUCTION_DATA_SAFETY sections 2 and 3
- ARCHITECTURE ARC-70 to ARC-72
- RELEASE_PROCESS REL-20 and section 7 (RB-21 step 2)
- ADR-002 item 3

## 4. Scope

### 4.1 In scope

1. Pin the PostgreSQL version used by local, CI and the release image; record the database's encoding, collation provider and locale (SCH-14).
2. Write the reviewed setup script of ENV-30: it creates the database and the migration (owner), runtime and backup roles from names and passwords supplied at run time and writes neither; revokes `CONNECT` and `TEMPORARY` from `PUBLIC` (SCH-43); sets the role settings of ENV-31; sets the environment marker with `COMMENT ON DATABASE` (ENV-04); creates `pg_trgm` as the migration role (ENV-33).
3. Configure a runtime connection for the application and a migration connection used only by migrations; the `migrations` table is owned by the migration role and the runtime role has no privilege on it.
4. Create the framework tables `cache` and `cache_locks` (class E) and `migrations`, with their grants.
5. Encode SCHEMA section 10 as a machine-readable privilege matrix that each table's Task extends (EXC-43); write the read-only console command `db:check-privileges` that compares the catalogue with it (RELEASE_PROCESS RB-21 step 2), and the grants procedure that applies the matrix with the migration role, used after a restore (BACKUP_RECOVERY RB-01 step 9).
6. Prove on the pinned version every feature of SCH-52 — partial and expression unique indexes, deferrable foreign keys, stored generated presence keys that refer to the identity column with a deferred foreign key, `MATCH FULL`, immutable functions in checks, column privileges, `FOR NO KEY UPDATE` and `FOR SHARE` with column `UPDATE` privilege, sequences with `CACHE 1` and `NO CYCLE`, `pg_trgm`, the `public` schema's default privileges and the sequence privileges of identity columns — and SCH-30 (the query builder's lock clauses) and SCH-14 (case folding with non-ASCII letters, character classes with a no-break space); verify and record how the transaction budgets of TECHNICAL_RULES section 9 are enforced.
7. Build the harness: a test database identified by the marker `test` and reset only by the migration role after the marker check (TS-07); committed fixtures; independent connections in separate processes; the race harness with named barriers after the identity insert, after each lock step and before commit (TS-20); the failure-injection hook after write step k (TS-21); both registered only in the testing environment; helpers for the constraint, deferred, privilege, retention and numbering families; the fixture builder of the synthetic shop "Toko Contoh", runnable only where the marker is `test` or `local`.
8. Prove the database round-trip of the kernel's Money, Quantity and Cost casts (TASK-003) without float conversion, on a test-only table.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `cache`, `cache_locks`, `migrations` |
| Keys completed (EXC-44) | — |
| Routes | — |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Any business table (each table's Task).
- The release preflight and the migration job (TASK-014).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-OPS-02 | REQ-NFR-08, REQ-OPS-05 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | DB01 (final: TASK-045) | KEEP01, READY01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-17 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-004-01** The setup script creates the three roles and the marker from run-time input, writes no credential, and leaves `PUBLIC` without `CONNECT` and `TEMPORARY`; it is reviewed as SQL and used identically by local, CI and the tests.
- **AC-004-02** Migrations run only as the migration role; the runtime role is refused DDL, `TRUNCATE` and every privilege on `migrations` (privilege family).
- **AC-004-03** Every SCH-52 feature is proven on the pinned version by a test; a difference stops the Task, except the presence-key fallback that SCHEMA section 3.7 fixes, which is then applied with SCHEMA updated.
- **AC-004-04** `db:check-privileges` passes on a fresh database and fails both on an induced extra grant and on a missing one; the grants procedure is idempotent.
- **AC-004-05** Two commands run in separate processes under barrier control; a failure injected after step k leaves no row behind; neither hook exists in a build outside the testing environment.
- **AC-004-06** A reset is refused when the marker is not `test`.
- **AC-004-07** Money, Quantity and Cost round-trip exactly through `bigint` and `numeric` columns.
- **AC-004-08** The collation and character-class vectors pass and are recorded; the lock-clause SQL and the transaction-budget mechanism are verified and recorded.

## 7. Tests

- Privilege family (L2).
- SCH-52 feature tests (L2).
- Harness self-tests with a test-only table: barriers, failure injection, reset refusal (L2, L3).
- Cast round-trip (L2).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-004.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The pinned PostgreSQL version and image digest; the collation record.
- The result of each SCH-52 feature and its source.
- The transaction-budget mechanism chosen.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | Applies — migrations reviewed and rehearsed (EXC-41 to EXC-46); the constraint, deferred, privilege and retention families for its tables |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A SCH-52 feature fails outside the fallback that SCHEMA section 3.7 fixes.
- `pg_trgm` is not available in the pinned image (SCHEMA section 11).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- SCHEMA (only a verified difference: section 3.7 fallback, section 15)
- TOOLCHAIN (PostgreSQL VERIFIED)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every Task with a table extends the privilege matrix and uses the harness.
- TASK-012 uses the setup script in the release image checks; TASK-037 uses the grants procedure.
- Tasks that depend on this one directly: [TASK-006](TASK-006-authentication.md), [TASK-012](TASK-012-application-image.md).

## 13. Expected files

- `database/` (setup script, migrations)
- `app/Console/Commands/` (`db:check-privileges`, grants procedure)
- `tests/Concurrency/`, `tests/Feature/` harness and families
- `config/database.php`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
