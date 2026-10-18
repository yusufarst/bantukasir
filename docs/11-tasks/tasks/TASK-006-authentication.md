# TASK-006 — Authentication, one-time links, audit and security log

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 2 Platform core · lane P, platform trunk |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-004](TASK-004-database-foundation.md), [TASK-005](TASK-005-design-system.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-006-authentication` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for Laravel Fortify and the session and password broker of the pinned version (EXC-03). |
| Modules | Access, Audit |
| Baseline | RF-7, D-090 |

## 1. Goal

Sign-in, sign-out, the limits of the sign-in session, recent authentication, the own password change and the one-time link broker with its consumption page, on the accounts table, with the business audit store and the separate security log — including the database-level enforcement of the security log's 365-day retention (OPEN_ITEMS T-23).

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- ADR-004 (all)
- SECURITY_MODEL sections 2, 3, 4 and 9, SEC-CH-03, SEC-CH-04, SEC-CH-08 and section 11 (the sign-in, link and confirmation buckets)
- SCHEMA sections 3.4, 3.5, 4 (class L), 5.1, 5.4 and the rows of section 10 for these tables
- INTERACTION_CONTRACTS section 4 (IC-09 to IC-14)
- ROUTE_CONTRACTS RP-001 to RP-004, RA-01 to RA-07
- CRITICAL_FLOWS WF-01, WF-02 (consumption), WF-03
- UI_STATES section 5
- COPY_DECK sections 6.2 to 6.5
- INFORMATION_ARCHITECTURE, NAVIGATION_CONTRACTS and INTERACTION_INVENTORY for UXP-001 to UXP-004
- KEY_SCREENS KS-01
- DOMAIN_MODEL section 8 (BR-USR-01 to BR-USR-12)
- OPEN_ITEMS T-23; D-049, D-056

## 4. Scope

### 4.1 In scope

1. Create `users` (class M, with the role and status checks and `users_one_active_owner`), `sessions`, `password_reset_tokens`, `audit_events` (with `command_id` nullable; its key is completed by TASK-008) and `security_log_entries` (class L), with their grants and indexes.
2. Resolve OPEN_ITEMS T-23: make the database refuse the runtime role's deletion of a security log entry younger than 365 days, with a privilege-class mechanism consistent with SCH-06 — for example a row security policy for `DELETE`, with permissive `SELECT` and `INSERT` policies — and update SCHEMA sections 5.4 and 10 (and the wording of SCH-06 if the mechanism needs naming there). State the effect on the backup dump for TASK-013 (`pg_dump` and row security). Stop if no mechanism fits without a business choice.
3. Configure Fortify with only the features of ADR-004 item 1; registration, email verification, two-factor authentication, passkeys and the public reset request are off; no mail transport is configured outside local development.
4. Hash passwords with Argon2id (ADR-004 item 7; parameters benchmarked on the target in TASK-048) and enforce AICWDF-COMPAT-8 on every path that sets a password.
5. Implement RA-01 (uniform failure UXS-42; session regenerated; full page load to RN-01, which TASK-007 delivers), RA-02 (session invalidated, CSRF token rotated, history key cleared, full page load to RP-001), RA-03 and RA-04 (15 minutes; bucket `confirm`), RA-05 (current password; account lock of TR-LOCK-05; the user's other sessions revoked; audit `PASSWORD_CHANGE`) and RA-07 (counts as activity).
6. Enforce the sign-in session limits in middleware: 30 minutes without user-initiated requests — `X-Background-Request: 1` never counts (RT-10) — and 12 hours absolute (ADR-004 item 8; SEC-SESS-02); a deactivated or anonymized account cannot sign in.
7. Implement the one-time link broker of ADR-004 item 4 and SECURITY_MODEL section 3 as an Access service — hashed token, one per account, 60-minute expiry, single use, checked only against the account named by its internal identity — for OP-01 (TASK-010) and RC-35 and RC-41 (TASK-011) to issue; implement its consumption RA-06 and the page RP-002 (account and token read from the fragment and removed; `Referrer-Policy: no-referrer`; no third-party resource; uniform `LINK_INVALID`; every session of the account revoked; token deleted; audit `LINK_CONSUME`).
8. Write the audit writer (in the business transaction; actor columns of SCHEMA section 3.4; frozen display name; `owner_only_data` kept apart) and the security log writer (after the transaction ends, committed or rolled back; also to standard output; no secret, token, user agent or payload).
9. Build the pages UXP-001 (KS-01), UXP-002, UXP-003 and UXP-004 on the approved artifacts.
10. Until OP-01 (TASK-010) and RC-35 (TASK-011) exist, test fixtures create accounts through the Access module's account service (EXC-45).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `users`, `sessions`, `password_reset_tokens`, `audit_events`, `security_log_entries` |
| Keys completed (EXC-44) | — |
| Routes | RP-001, RP-002, RP-003, RP-004, RA-01, RA-02, RA-03, RA-04, RA-05, RA-06, RA-07 |
| Route elements added | — |
| Pages | UXP-001 "Masuk", UXP-002 "Atur kata sandi", UXP-003 "Akun saya", UXP-004 "Ubah kata sandi" |
| Controls | 11: UXI-039, UXI-040, UXI-041, UXI-042, UXI-043, UXI-044, UXI-045, UXI-047, UXI-048, UXI-049, UXI-050 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Capability gates, the route inventory, headers, CSP, the other rate buckets, RN-01 and the shell (TASK-007).
- Link issuance by the Owner (TASK-011) and by OP-01 (TASK-010).
- The audit log and security log pages (TASK-042).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-ACC-01, REQ-ACC-07, REQ-ACC-08, REQ-AUD-01 | REQ-ACC-04, REQ-ACC-05, REQ-AUD-02, REQ-NFR-07 |
| Workflows | WF-01, WF-03 | WF-02 |
| User tasks | UXT-01, UXT-11, UXT-39 | — |
| Acceptance suites | SIGNIN01 (final: TASK-044), LINK01 (final: TASK-044), AUTH03 (final: TASK-044) | AUTH02, AUDIT01, SEC01, KEEP01 |
| Races | — | RACE-20 |
| Guards | — | G-16 |
| Threats | THR-01, THR-20 | THR-18, THR-21 |
| Open items | T-23 — resolved here: database-level enforcement of the 365-day retention of the security log | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-006-01** Fortify exposes exactly the features of ADR-004 item 1; the route inventory shows no registration, email verification, two-factor or public reset route, and no mail transport is configured outside local development.
- **AC-006-02** A failed sign-in, link or password confirmation answers one uniform message that reveals nothing; each bucket of SECURITY_MODEL section 11 for sign-in, link submissions and confirmation throttles with `THROTTLED` and `Retry-After`; `SIGN_IN_SUCCEEDED`, `SIGN_IN_FAILED`, `THROTTLED`, `LINK_FAILED` and `PASSWORD_CONFIRMATION_FAILED` reach the security log after the transaction and survive its rollback.
- **AC-006-03** With the test clock, the idle limit counts only user-initiated requests and the absolute limit ends a session after 12 hours; sign-out or the end of a sign-in session changes no cash session (verified again in TASK-027).
- **AC-006-04** An operation needing recent authentication answers 423 without a confirmation within 15 minutes and succeeds when the same frozen request follows RA-03, shown on a test-only route until real operations exist.
- **AC-006-05** The own password change enforces AICWDF-COMPAT-8 and the current password, revokes the user's other sign-in sessions and writes `PASSWORD_CHANGE`.
- **AC-006-06** A link works once within 60 minutes and is refused, with the uniform `LINK_INVALID`, after use, after expiry and when malformed; it never appears in props, history state, a URL or a log, and the fragment is removed after reading (Playwright).
- **AC-006-07** The constraint, deferred, privilege and retention families pass for the five tables, including one active Owner at most and the case-insensitive email vectors of SCH-14.
- **AC-006-08** OPEN_ITEMS T-23 is resolved: a runtime-role `DELETE` of an entry younger than 365 days removes nothing, an older one is removable, SCHEMA states the mechanism, and the evidence states its effect on the backup dump.
- **AC-006-09** UXP-001 to UXP-004 follow the approved artifacts, with the states of KS-01; their accessibility checks pass.

## 7. Tests

- L2 families for the five tables.
- L4 sign-in, sign-out, confirmation, password change and link consumption.
- L4 throttling and uniform failures.
- L5 KS-01 states, the link page's fragment handling, sign-out then Back.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-006.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The T-23 mechanism, its test and its effect on the dump.
- The Fortify feature list.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | Applies — migrations reviewed and rehearsed (EXC-41 to EXC-46); the constraint, deferred, privilege and retention families for its tables |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- T-23 cannot be enforced in the database without a business choice.
- A Fortify feature cannot be switched off on the pinned version.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- SCHEMA sections 5.4 and 10 (T-23; SCH-06 wording if needed)
- SECURITY_MODEL section 9 (a pointer to the mechanism)
- OPEN_ITEMS T-23 (RESOLVED)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-007 adds gates, headers and the shell; TASK-010 and TASK-011 issue links through the broker; TASK-013 dumps the security log under the T-23 mechanism; TASK-038 runs its 365-day cleanup.
- Tasks that depend on this one directly: [TASK-007](TASK-007-authorization-shell.md).

## 13. Expected files

- `app/Domain/Access/`, `app/Domain/Audit/`
- `app/Actions/Access/`, `app/Http/Controllers/Access/`, `app/Http/Middleware/`
- `database/migrations/`
- `resources/js/pages/access/`
- `lang/id/access.php`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
