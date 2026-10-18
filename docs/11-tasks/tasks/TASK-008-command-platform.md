# TASK-008 — Command platform (server)

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 2 Platform core · lane P, platform trunk |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-007](TASK-007-authorization-shell.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-008-command-platform` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for Laravel transactions and the pinned PostgreSQL error codes (EXC-03). |
| Modules | Commands, Audit |
| Baseline | RF-7, D-090 |

## 1. Goal

The server side of every command: the envelope, identity, fingerprint and durable result of ADR-007, the recovery epoch check, the request receipts with their marking and settlement (ADR-009, D-089), the status check RC-50 with fences and stale-intent reports, the list of unresolved receipts RD-04, bounded retries, timeouts and the hard end, and the error body with every error code.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- ADR-007 (all)
- ADR-009 (all)
- INTERACTION_CONTRACTS sections 1.1, 1.2, 1.4, 1.5, 2.1 to 2.4, 3.6, 6 and 8
- ROUTE_CONTRACTS RT-06, RT-07, RT-09, RT-11, section 6, RC-50 and RD-04
- TECHNICAL_RULES sections 3, 4 and 6, section 7.1 step 1, section 7.2 (resolution; request receipt), section 9 and RACE-01, RACE-02, RACE-27, RACE-33, RACE-34
- SCHEMA section 5.3, G-12 and G-25
- SECURITY_MODEL CAP-34, SEC-AZ-06 and SEC-CH-11
- DOMAIN_MODEL section 7 (BR-CMD-01 to BR-CMD-06)
- COPY_DECK section 13.1

## 4. Scope

### 4.1 In scope

1. Create `recovery_epoch`, `command_identities`, `command_results`, `command_request_receipts`, `stale_intent_reports` and `intent_reconciliations` with their keys, presence key and grants; complete the key `audit_events.command_id` → `command_identities`.
2. Implement the pipeline of INTERACTION_CONTRACTS section 1.1 in its order: the request receipt in a short transaction of its own (a receipt that cannot be written refuses `SERVICE_BUSY` with nothing run, G-25); the FormRequest base (envelope fields, operation equal to the path, version 1, UUIDs, expected versions, unknown fields refused); the Action base (one transaction; the identity as first write; the epoch check G-12; the fingerprint, SHA-256 of the canonical payload or of the file for a multipart request; the durable result holding references only); the marking of the receipt, replacing only PENDING; the security log after the transaction; the answer built from an allowlisted DTO.
3. Answer `IDEMPOTENCY_CONFLICT` and `INTENT_ABANDONED` after the rollback by reading the original identity or fence (TR-TX-08); return a repeated identity's original result with `"repeated": true`, rebuilt under the actor's current authorization (ADR-007 section 3).
4. Implement the hard end of TR-TIME-03 (received time from the application's web server; a command starting more than 30 seconds later is refused `SERVICE_BUSY` before any write), the bounded retries of TR-RETRY-01 with the same identity, and `SERVICE_BUSY` on a lock wait beyond the lock timeout (TR-RETRY-02).
5. Implement RC-50 for the intent's actor and the Owner (CAP-34): COMMITTED with the result under the checker's authorization and never a link again; IN_PROGRESS; NOT_COMMITTED with a fence written in its own transaction with the checker as actor; NOT_COMMITTED from a reconciliation's fence (the reconciliations are written by OP-05, TASK-037); `EPOCH_STALE` with a stale-intent report inserted if absent and the intent's PENDING receipts marked HELD; `NOT_FOUND` for anyone else. A conclusive answer settles every PENDING or HELD receipt of the operation and intent (D-089): in the fence's transaction, or after reading an existing identity or fence in a short transaction of its own; receipts locked in ascending order; no session lock.
6. Implement RD-04 (`scope=own`; `all` for the Owner only): receipts whose outcome is PENDING or HELD, with family, intent, key, epoch marker, received time and, for `all`, the user; at most 100 per page with a cursor; never a payload.
7. Implement the error body of RT-07 with the 32 codes of TECHNICAL_RULES section 4, each with its HTTP status and COPY_DECK section 13.1 message key; `fields` and `lines` carry only safe values.
8. Keep the fixed server list of operations of ROUTE_CONTRACTS section 6 with their families (INTERACTION_CONTRACTS section 1.4); each operation's route, FormRequest and Action are added by its own Task.
9. Prove the platform end to end with a probe operation registered only in the testing environment; every real command proves the same again for itself (EXC section 10).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `recovery_epoch`, `command_identities`, `command_results`, `command_request_receipts`, `stale_intent_reports`, `intent_reconciliations` |
| Keys completed (EXC-44) | `audit_events.command_id` → `command_identities` |
| Routes | RC-50, RD-04 |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The client side of commands and UXP-006 (TASK-009).
- OP-04 and OP-05, which write epochs and reconciliations (TASK-037).
- Every real command (its Task).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CMD-01, REQ-CMD-04 | REQ-SALE-06, REQ-SALE-08, REQ-CMD-03 |
| Workflows | — | WF-07 |
| User tasks | — | — |
| Acceptance suites | CMD01 (final: TASK-044) | REC02, POS03 |
| Races | RACE-01, RACE-02, RACE-27, RACE-33 | RACE-34 |
| Guards | G-12, G-25 | — |
| Threats | — | THR-17 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-008-01** Every step runs in the order of INTERACTION_CONTRACTS section 1.1; a refusal before the receipt records no receipt; a failure injected into the receipt write answers `SERVICE_BUSY` with nothing run.
- **AC-008-02** RACE-01, RACE-02, RACE-27 and RACE-33 give the outcomes of TECHNICAL_RULES section 8 with two processes under barrier control.
- **AC-008-03** The settlement of D-089 holds on the probe operation: two receipts of one intent, one PENDING and one HELD with `IDEMPOTENCY_CONFLICT`, are settled to COMMITTED by the actor's check and, in a second case, by the Owner's, keeping `held_code` and recording the settling identity and checker; NOT_COMMITTED with its fence settles both in the fence's transaction; a third user's check answers `NOT_FOUND` and settles nothing; IN_PROGRESS settles nothing; a settled receipt never changes outcome.
- **AC-008-04** An earlier-epoch command is refused `EPOCH_STALE` before any fact; its stale-intent report is written once, also under a concurrent repeat; its PENDING receipts become HELD.
- **AC-008-05** A command starting more than 30 seconds after it was received is refused `SERVICE_BUSY` before any write; an injected deadlock is retried at most three times with the same identity; a lock wait beyond the timeout answers `SERVICE_BUSY`.
- **AC-008-06** A repeated identity returns the original result under the current authorization; a user who lost the capability meanwhile is refused.
- **AC-008-07** RD-04 lists exactly the unresolved receipts in scope; `scope=all` is refused to anyone but the Owner; no payload is ever listed.
- **AC-008-08** Every error code answers its status and message key; no error body holds a stack trace, SQL, a path or a value outside the role's scope.
- **AC-008-09** The constraint, deferred and privilege families pass for the six tables; an identity without its result cannot commit.

## 7. Tests

- L2 families for the six tables.
- L3 races with the probe operation.
- L2 failure injection after every write step of the pipeline.
- L4 RC-50, RD-04 and the error body.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-008.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The pipeline's step list with the test of each step.
- The race outcomes.

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

- A rule of ADR-007 or ADR-009 cannot be met on the pinned versions.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every command Task plugs into this pipeline; TASK-009 builds the client; TASK-037 writes epochs and reconciliations.
- Tasks that depend on this one directly: [TASK-009](TASK-009-command-client.md), [TASK-010](TASK-010-bootstrap-profile-media.md).

## 13. Expected files

- `app/Domain/Commands/`, `app/Actions/Commands/`, `app/Http/Controllers/Commands/`
- `app/Http/Requests/` (base)
- `database/migrations/`
- `lang/id/commands.php`, `lang/id/ux.php` (error keys)

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
