# TASK-011 — User management and Owner procedures

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 2 Platform core · lane P, platform trunk |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-009](TASK-009-command-client.md), [TASK-010](TASK-010-bootstrap-profile-media.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-011-user-management` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Access |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's management of staff accounts — create with a first-password link, change roles and details, deactivate, reactivate, anonymize, issue and revoke links — the Owner's own details, and the operator procedures of Owner recovery and Owner replacement, with every revocation and race that the security model requires.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SECURITY_MODEL section 3, CAP-25 to CAP-29, CAP-33, SEC-OP-02, SEC-OP-03 and section 14
- DOMAIN_MODEL section 8 (BR-USR-01 to BR-USR-12)
- TECHNICAL_RULES section 7.2 (user management; Owner recovery; Owner replacement), TR-LOCK-05, RACE-04, RACE-16, RACE-20, RACE-21, RACE-28 and RACE-30
- SCHEMA section 5.1, G-08 and G-16
- INTERACTION_CONTRACTS RC-35 to RC-42
- ROUTE_CONTRACTS RP-005, RP-602 to RP-606, OP-02 and OP-03
- OPERATIONS_RUNBOOK RB-10 and RB-11
- CRITICAL_FLOWS WF-02, WF-03 and WF-29
- COPY_DECK sections 6.6 and 11.2 to 11.6
- D-043, D-048, D-049, D-056, D-069 (d), D-087

## 4. Scope

### 4.1 In scope

1. Implement RC-35 to RC-42 with the user-management family's locks (the target under 2.2), the Owner and recent authentication, `users.version` as expected version, and the rules of TECHNICAL_RULES section 7.2: never create, assign or remove SUPER_ADMIN, deactivate or anonymize the active Owner, or reactivate any SUPER_ADMIN account; deactivation, role change and anonymization revoke the target's sign-in sessions and links in the same transaction; an email change revokes the link; the `link_issue` bucket; a link returned once, in the issuing JSON body only (SEC-ACC-05); anonymization only of a deactivated staff account or a former Owner deactivated by replacement, with a reason, the neutral name from `access.user.anonymized_name` with `:nomor` the account's identifier and the email `anonymized-<id>@users.invalid`.
2. Implement RC-37 `owner.update_details` on the Owner's own account, locked once with the update lock (TR-LOCK-05).
3. Implement OP-02 `owner:recover` (a RESET link replacing any earlier one; the Owner's sessions revoked) and OP-03 `owner:replace` (the outgoing Owner named by internal identifier, deactivated with sessions and links revoked, never deleted; a new SUPER_ADMIN with its FIRST_PASSWORD link; a repeated run refused), on the procedure framework of TASK-010. An OPEN cash session of the outgoing Owner stays OPEN and unchanged; that part is proven when sessions exist (TASK-032).
4. Build UXP-005, UXP-602, UXP-603, UXP-604, UXP-605 and UXP-606 (the link held only in page memory from the RC-35 or RC-41 response; loaded any other way it shows UXS-49), with the handover warning of D-056 (THR-21) and the settings index entry. The OPEN-session mark of UXP-602 and the session link of UXP-604 are added by TASK-027 and TASK-030.
5. Switch the fixture builders to OP-01 and RC-35 for accounts (EXC-45).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RC-35, RC-36, RC-37, RC-38, RC-39, RC-40, RC-41, RC-42, RP-005, RP-602, RP-603, RP-604, RP-605, RP-606, OP-02, OP-03 |
| Route elements added | — |
| Pages | UXP-005 "Ubah nama dan email", UXP-602 "Pengguna", UXP-603 "Tambah pengguna", UXP-604 "Detail pengguna", UXP-605 "Ubah pengguna", UXP-606 "Tautan kata sandi" |
| Controls | 30: UXI-046, UXI-051, UXI-052, UXI-053, UXI-054, UXI-326, UXI-332, UXI-333, UXI-334, UXI-335, UXI-336, UXI-337, UXI-338, UXI-339, UXI-341, UXI-342, UXI-343, UXI-344, UXI-345, UXI-346, UXI-347, UXI-349, UXI-350, UXI-351, UXI-352, UXI-353, UXI-354, UXI-355, UXI-356, UXI-357 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The cash-session parts of OP-03 and of a deactivated user's session (TASK-032).
- The reprint proof of RACE-16 (TASK-031).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-ACC-05, REQ-ACC-06, REQ-ACC-09, REQ-ACC-10, REQ-ACC-11, REQ-ACC-12 | REQ-ACC-02, REQ-ACC-08 |
| Workflows | WF-02, WF-29 | WF-03, WF-32 |
| User tasks | UXT-12, UXT-35 | — |
| Acceptance suites | ACC01 (final: TASK-044) | LINK01, AUTH03, OPPROC01 |
| Races | RACE-04, RACE-16, RACE-20, RACE-21, RACE-30 | RACE-28 |
| Guards | G-08, G-16 | — |
| Threats | THR-21 | THR-03, THR-20 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-011-01** Every capability CAP-25 to CAP-29 behaves for each role and the combined account as the matrix says; CAP-33 is reachable through no route.
- **AC-011-02** Exactly one Owner account is active at every moment (G-08); RACE-21 and RACE-30 give their outcomes; reactivating a former Owner account is refused.
- **AC-011-03** Deactivation, role change and anonymization revoke sessions and links in the same transaction, and an email change revokes the link; RACE-04 (a deactivation or a role change against a running command) and RACE-20 (a consumption against revocation, re-issue or deactivation) give their outcomes in both orders.
- **AC-011-04** Anonymization needs a deactivated account and a reason, writes the neutral name with `:nomor` and the `.invalid` email, and leaves every frozen display name elsewhere unchanged (G-16); an anonymized account is never reactivated.
- **AC-011-05** A link appears only in the issuing JSON response — never in props, history state, a URL or a log; UXP-606 loaded any other way shows UXS-49; issuance and revocation are audited and the handover warning is shown.
- **AC-011-06** OP-02 and OP-03 follow the common rules of SECURITY_MODEL section 14 and produce their effects; a repeated OP-03 is refused.
- **AC-011-07** The pages follow the approved artifacts; every user-management action asks for recent authentication.

## 7. Tests

- L3 RACE-04, RACE-16 (account side), RACE-20, RACE-21, RACE-30.
- L4 capability matrix rows of these routes; link channel inspection.
- L2 procedure tests of OP-02 and OP-03.
- L5 the six pages.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-011.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The race outcomes.
- The channel inspection of the link.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A rule would let any route reach CAP-33.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-027 and TASK-030 add the session mark and link to UXP-602 and UXP-604; TASK-032 completes RACE-28 and the OP-03 session part.
- Tasks that depend on this one directly: [TASK-015](TASK-015-catalog-core.md), [TASK-027](TASK-027-registers-session-open.md), [TASK-032](TASK-032-assisted-close-notes.md), [TASK-038](TASK-038-housekeeping.md).

## 13. Expected files

- `app/Actions/Access/`, `app/Http/Controllers/Access/`, `app/Http/Requests/Access/`
- `app/Console/Commands/` (`owner:recover`, `owner:replace`)
- `resources/js/pages/access/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
