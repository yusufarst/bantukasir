# TASK-007 — Authorization framework, security baseline and application shell

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 2 Platform core · lane P, platform trunk |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-006](TASK-006-authentication.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-007-authorization-shell` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for Laravel authorization, CSRF, trusted proxies and Inertia history encryption (EXC-03). |
| Modules | Access, Operations (health route), Frontend shell |
| Baseline | RF-7, D-090 |

## 1. Goal

Deny-by-default authorization with the capability map and object scope, the route-inventory and capability-matrix tests, the remaining rate limits, CSRF, security headers and the content security policy, trusted hosts and proxies, error pages, the health route, the home redirect and the application shell with navigation per role, history encryption and client timers.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SECURITY_MODEL sections 5, 6, 7, 11 and 12
- ROUTE_CONTRACTS RT-01 to RT-12, RN-01, RH-01 and section 8
- TECHNICAL_RULES sections 2 and 4, TR-ERR-01 to TR-ERR-06
- INFORMATION_ARCHITECTURE sections 2 to 4 and 6
- NAVIGATION_CONTRACTS section 1 (NC-01 to NC-13) and the UXP-000 contract
- INTERACTION_INVENTORY UXP-000 rows
- UI_STATES UXS-07 to UXS-12, UXS-20 and UXS-23
- COPY_DECK sections 4 and 6.1
- DESIGN_SYSTEM section 7.2
- ENVIRONMENTS section 10; ADR-003 item 10; ADR-011 items 10 and 11
- ADR-004 item 8

## 4. Scope

### 4.1 In scope

1. Encode the capability map CAP-01 to CAP-34 once (SEC-AZ-02), with the combined account as the union of CASHIER and OPERATIONS_ADMIN that never reaches an Owner-only capability; a gate middleware on every route (RT-05); the object-scope pattern that answers `NOT_FOUND` for an object outside scope as for a missing one (TR-ERR-03); the in-transaction actor guard for Actions (SEC-AZ-04); the actor only from the sign-in session (SEC-AZ-05).
2. Write the route-inventory test (every route names its gate; no `DELETE` route; no `GET` that writes; no route outside V1) and the data-driven capability-matrix test (route × role × combined account → expected answer), both extended by every later Task (EXC section 10).
3. Write the channel-allowlist harness for SEC-CH-01: DTO bases and a payload-inspection helper per role and channel, extended by every later Task.
4. Configure the remaining buckets of SECURITY_MODEL section 11 — `search`, `command`, `photo`, `logo`, `csv`, `print`, `link_issue`, `authenticated` and the pre-sign-in bucket — keyed by user after sign-in, answering `THROTTLED` with `Retry-After` and writing the security log.
5. Configure CSRF in both layers with no exclusion (SEC-HDR-01); the headers of SEC-HDR-02 on every response; the content security policy of SEC-HDR-03 with a per-request nonce on the generated tags; HTTPS only (SEC-HDR-04); trusted hosts and only the proxy hops in front (SEC-HDR-05; ENVIRONMENTS section 10); generic translated error pages with debug output off outside local development (SEC-CH-10, TR-ERR-02).
6. Implement RH-01 `/up` with a `DiagnosingHealth` listener that checks the database; no session middleware; the pre-sign-in throttle; no detail in its body.
7. Implement RN-01 with the homes of NC-12. Until a role's home page exists, RN-01 sends that role to RP-003; TASK-022, TASK-027, TASK-029 and TASK-041 switch the homes as they deliver them.
8. Build the shell UXP-000 on DESIGN_SYSTEM section 7.2: side navigation with labels, the icon rail on till pages, the compact top bar with the drawer, the sub-navigation, the account menu with "Akun saya" and "Keluar", the banner region, landmarks and the document title; a navigation item appears only when its destination exists (EXC-52). Shared props exactly as SEC-CH-02 lists; history encryption on every authenticated page; client timers mirroring both limits with UXS-23 and "Tetap masuk" (RA-07); the history key cleared at sign-out, change of user and either limit; back-forward cache revalidation; full page loads at sign-in and sign-out; browser storage cleared at sign-out (TASK-009 adds the command references that survive it).
9. Wire the states UXS-07 to UXS-12 and UXS-20 to the answers 403, 404, 401, 419, 423, 429 and a lost connection.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RN-01, RH-01 |
| Route elements added | — |
| Pages | UXP-000 "Kerangka aplikasi" |
| Controls | 7: UXI-022, UXI-023, UXI-024, UXI-026, UXI-034, UXI-035, UXI-038 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The unresolved-requests item of the account menu and the banner (TASK-009).
- The shop's name, initials and accent in the shell (TASK-010).
- Destinations and their navigation items (their page Tasks).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-ACC-02, REQ-ACC-03, REQ-ACC-04, REQ-NFR-07, REQ-SCOPE-02 | REQ-UX-01, REQ-UX-02, REQ-ACC-07 |
| Workflows | — | WF-01 |
| User tasks | — | — |
| Acceptance suites | AUTH01 (final: TASK-044), AUTH02 (final: TASK-044), SEC01 (final: TASK-044), UX01 (final: TASK-044) | SIGNIN01, L10N01, STATUS01 |
| Races | — | — |
| Guards | — | — |
| Threats | THR-02, THR-03, THR-04, THR-05, THR-06, THR-07, THR-08, THR-11, THR-13, THR-18 | THR-15, THR-16 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-007-01** The route-inventory test fails on an ungated test route and passes on the tree; no `DELETE` route and no `GET` that writes exist.
- **AC-007-02** For every route present, each role and the combined account by direct request get the answer the capability matrix gives; an object outside scope answers the same `NOT_FOUND` as a missing one; a role or another authoritative value in a request body is refused.
- **AC-007-03** Every bucket of SECURITY_MODEL section 11 throttles with `THROTTLED`, `Retry-After` and a security log entry; a forged `X-Forwarded-For` or `CF-Connecting-IP` from an untrusted hop changes neither the rate limiter's nor the security log's address.
- **AC-007-04** Every response carries the headers of SEC-HDR-02; the CSP of SEC-HDR-03 with its nonce reports no violation on any existing page in a real browser; cross-origin and tokenless writes are refused in both CSRF layers; framing is refused.
- **AC-007-05** RH-01 answers 200 with the database up and an error status with it down, sets no cookie, reveals no detail and is throttled.
- **AC-007-06** The shell shows, per role, the navigation of INFORMATION_ARCHITECTURE section 4 for the destinations that exist, the account menu, landmarks and the title; the idle warning, the timers and the history-key clearing work; after sign-out, another user's sign-in, Back, Forward and reload show nothing of the first user (THR-18).
- **AC-007-07** Debug output is off in every non-local environment; environment, vendor and storage paths are unreachable through the local web server (completed for the image by TASK-012).

## 7. Tests

- L4 route inventory and capability matrix.
- L4 headers, CSRF, throttling and forged forwarding headers.
- L5 shell per role at every width; CSP; shared-device sequence.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-007.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The capability-matrix data and its coverage.
- The CSP and header test results.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A capability of SECURITY_MODEL section 6 cannot be expressed without changing its meaning.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every later Task extends the route inventory, the capability matrix and the channel harness for what it adds (EXC section 10).
- Tasks that depend on this one directly: [TASK-008](TASK-008-command-platform.md), [TASK-012](TASK-012-application-image.md).

## 13. Expected files

- `app/Policies/`, `app/Http/Middleware/`, `app/Domain/Access/Capabilities`
- `app/Http/Controllers/Access/HomeRedirectController`
- `resources/js/layouts/` (shell)
- `tests/Feature/` route inventory and matrix

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
