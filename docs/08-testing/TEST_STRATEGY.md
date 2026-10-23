# Test strategy

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta); amended under D-091 the same day: the samples of the secret scan (section 6) and the L5 critical paths of a pull request (section 7); amended under D-101, 23 October 2026: the reviews under continuous execution (section 11) |
| Phase | P8 — test levels, harness, test families, CI gates, evidence, acceptance gates and the Definition of Done |
| Replaces | `docs/08-testing/legacy/P8-QUALITY.md`, the method, evidence and Definition of Done sections of `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md`, and `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Testing implications (all LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-006, D-025, D-075, D-087, D-088, D-091, D-101; FD-07; [ADR-006](../adr/ADR-006-exact-numbers.md), [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md), [ADR-008](../adr/ADR-008-cost-allocation-representation.md), [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md), [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) |
| Related | [ACCEPTANCE_SUITES](ACCEPTANCE_SUITES.md), [DEVICE_VERIFICATION](DEVICE_VERIFICATION.md), [REQUIREMENTS](../01-product/REQUIREMENTS.md), [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 8, [SCHEMA](../04-architecture/SCHEMA.md) section 9.4, [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 15 |

How BantuKasir is proven. Rules are numbered `TS-nn`; identifiers are permanent. This strategy turns the race matrix, the test families of SCHEMA, the security tests of SECURITY_MODEL and the acceptance criteria of REQUIREMENTS into a test plan that the Tasks of RF-7 implement; the suites themselves are ACCEPTANCE_SUITES. Nothing has been run: no code exists.

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| TS-01 | **Evidence, not assertion.** A check passes only when it ran on a named revision and target and its result is recorded (section 8). A check not run is `NOT_RUN`, never `PASS`; a fixture, a prototype, a screenshot or an executor's report proves only what it measured. | AGENTS rule 16, PROJECT_RULES section 5 |
| TS-02 | **Real PostgreSQL for database behaviour.** Constraints, privileges, locks, rollback, races and failure injection are proven on real PostgreSQL of the version that production runs, never on SQLite, an in-memory store or a mock. | STK-20, SCH-10, SCH-53 |
| TS-03 | **Committed fixtures, independent connections.** A test of a constraint, a rollback or a race commits its fixtures and uses at least two independent connections in separate processes; it never runs inside one wrapping transaction. | STK-20, TECHNICAL_RULES section 8 |
| TS-04 | **Every rule has a test.** Every requirement's acceptance criteria, every race of TECHNICAL_RULES section 8, every guard of SCHEMA section 8, every constraint and privilege of SCHEMA, every threat's negative proof of SECURITY_MODEL section 10 and every operator procedure is covered by a case of a suite (ACCEPTANCE_SUITES sections 5 to 9). | SCH-10, SEC-PR-06 |
| TS-05 | **Negative tests per role.** An Owner's positive test never replaces the refusals of the other roles; every route is tried by every role and by the combined account, directly, with guessed identifiers. | REQ-ACC-03, SECURITY_MODEL section 15 |
| TS-06 | **No production data in tests.** Tests use synthetic data only, in local environments, CI and staging; production data appear only in a restore drill on an isolated target (ENVIRONMENTS ENV-01). Test output, logs and CI artifacts are public and hold nothing sensitive. | PDS §4, D-076 |
| TS-07 | **Tests never touch production.** No test, harness or tool connects to production; the post-deployment checks of RELEASE_PROCESS section 7 are reads by the operator, not tests. A test database is reset only after its marker identifies it as a test database (ENVIRONMENTS ENV-04). | PDS §2, D-088 |
| TS-08 | **Deterministic.** Tests control the clock, randomness and interleaving; a race test that fails sometimes is a defect, never a retry. A test is never retried until it passes. | TECHNICAL_RULES section 8 |
| TS-09 | **The suite's final point decides.** Early evidence in a Task is real but not final: a suite reaches `FINAL_PASS` only at its final point on the release candidate (section 9). | L-08 §Bukti terarah versus final |

## 2. Test levels

| Level | Proves | Tools | Where | Data |
| --- | --- | --- | --- | --- |
| L1 Unit | Pure logic: ADR-006 arithmetic and rounding, the ADR-008 calculations, periods and business dates, DTO allowlists, CSV parsing and neutralization, client state decisions | Pest or PHPUnit; Vitest | Local, CI | Synthetic |
| L2 Database | Constraint, deferred, privilege, retention and numbering families; Actions with committed fixtures; failure injection | Pest or PHPUnit on real PostgreSQL as the runtime role | Local, CI | Synthetic |
| L3 Concurrency | Every race of TECHNICAL_RULES section 8 with controlled interleaving | The race harness of section 4 on real PostgreSQL | Local, CI | Synthetic |
| L4 HTTP | Every route: gate, object scope, validation, error mapping, DTO per role, CSRF, rate limits, headers, the route inventory | Laravel HTTP tests on real PostgreSQL | Local, CI | Synthetic |
| L5 Browser | Workflows per role, UI states, recovery panels, channels (history, back-forward cache), CSP, accessibility checks, responsive widths, keyboard paths, print views | Playwright against a real server and database | Local, CI, staging | Synthetic |
| L6 Operations | Migration rehearsal, privileges on the target, backup, restore drill, after-restore procedures, operator procedures, infrastructure exposure, status evidence | The runbooks of P9 and P10, run by the operator | Staging, drill target, the server | Synthetic; a production backup only on a drill target |
| L7 Performance | The budgets of TECHNICAL_RULES section 13 | An open-source load tool chosen by the Task | The intended server before go-live, then staging | The provisional benchmark dataset |
| L8 Devices | Scanners, printers, tablets, browsers and the on-screen keyboard | DEVICE_VERIFICATION | Staging, on the shop's devices | Synthetic |
| L9 UAT and pilot | The shop's people doing their tasks; real use | UAT_PILOT | Staging, then production | Synthetic, then real |

## 3. Environments and data for tests

- **Databases.** Each test run starts PostgreSQL of the pinned version with the three roles and the grants of SCHEMA section 10, created by the same migrations as production (SCH-53). Application tests connect as the runtime role, migrations as the migration role, and the privilege family as each role in turn.
- **Reset between tests.** Tests that commit reset the database between cases with the migration role, only after the database's marker says `test` (TS-07); the runtime role keeps its production privileges and never truncates.
- **Fixtures.** Facts are created through the Actions, never by inserting rows directly, so that every fixture obeys the rules. The constraint family is the exception: it writes violating rows directly as the runtime role to prove the refusal.
- **Datasets.** The fixtures of KEY_SCREENS section 5 ("Toko Contoh"), extended; the provisional benchmark dataset of TECHNICAL_RULES section 13.2 for L7 and for migration rehearsal (REL-21). Nothing real is ever used.
- **Clock.** The clock boundary of STK-31 fixes time for business dates, periods, link expiry, sign-in session limits, retention and the record-check wait, including Asia/Jakarta midnight.

## 4. Harness

| ID | Rule | Source |
| --- | --- | --- |
| TS-20 | **Race harness.** A race test runs each competing command in its own process with its own connection. Barriers at named points of a command — after the identity insert, after each lock step of TECHNICAL_RULES section 7.1, before commit — let the test choose the interleaving; the points are test-only hooks that the release build does not register. The test asserts the required outcome of the race for each order that matters, and the final facts, projections, audit events, results and receipts. | TECHNICAL_RULES section 8 |
| TS-21 | **Failure injection.** Each command lists its write steps; a test-only hook fails the command after step k, for every k, and the test asserts that no fact, projection, audit event or command result remains, and that the receipt is not marked committed. | TECHNICAL_RULES section 8, G-25 |
| TS-22 | **Network faults in the browser.** Playwright intercepts requests to drop a response after the server committed, to answer 409 `IDEMPOTENCY_CONFLICT` or `EPOCH_STALE`, 503 or a timeout, and moves time past the client wait of 65 seconds with a fake clock, so that UXS-14, UXS-15, "Cek status" and "Kirim ulang" are proven without waiting. | INTERACTION_CONTRACTS section 1.3 |
| TS-23 | **Second device.** Cross-device behaviour is proven with two independent browser contexts of the same user, and with one context whose site data are cleared (ADR-009 section 2). | D-074, ADR-009 |
| TS-24 | **Restore in tests.** The restore cases of OPEN01, REC02 and SESSION05 restore a test database from a dump taken earlier in the same test, then run OP-04, so that the behaviour after a restore is proven at L2 and L3 before the operational drills of L6. | D-072, D-073 |
| TS-25 | **Accessibility.** Automated checks run in Playwright on every page at the widths of UX01; keyboard paths are scripted with real key events; a screen-reader pass and the on-screen keyboard are manual checks recorded in UX02 and DEVICE_VERIFICATION. | REQ-UX-03 |

## 5. Test families

| Family | Proves | Level | Source |
| --- | --- | --- | --- |
| Constraint | Every check, unique, foreign-key and partial unique constraint refuses a violating row written directly by the runtime role, including a null in each nullable column that a check or a composite key involves | L2 | SCHEMA section 9.4, SCH-13 |
| Deferred | A transaction that omits a row required by a deferred or presence key fails at commit and leaves nothing | L2 | SCHEMA section 9.4 |
| Privilege | The actual grants equal SCHEMA section 10 for the runtime, migration and backup roles; refusals of DDL, of `UPDATE` and `DELETE` on retained classes, of `TRUNCATE` and of `setval`; `PUBLIC` rights revoked | L2; L6 on the target | SCHEMA section 9.4, SCH-45 |
| Race | Every race RACE-01 to RACE-35 with its required outcome; the mark transaction of SCHEMA section 5.8 | L3 | TECHNICAL_RULES section 8 |
| Failure injection | No partial state after a failure at any write step | L2 | TECHNICAL_RULES section 8 |
| Reconciliation | Induced mismatches in balances, valuations, allocation projections, running totals and active mappings are detected and block | L2 | G-03, BR-INV-45 |
| Numbering | Concurrent checkouts do not wait for a number; numbers are unique and in allocation order; gaps after a rollback; the date part across midnight; the advance after a restore | L2, L3 | SCHEMA section 6 |
| Costing | The ADR-008 test list | L1, L2 | ADR-008 |
| Retention | No product path deletes or rewrites a retained row; the cleanups of classes E and L remove only what their decisions allow | L2 | SCHEMA section 9.4 |
| Route inventory | Every route has a gate; no `DELETE` route; no `GET` that writes; no route for anything outside V1 | L4 | ROUTE_CONTRACTS RT-05, section 8 |
| Capability matrix | Every capability for each role and the combined account, by direct request | L4 | SECURITY_MODEL section 6 |
| Channel allowlist | Every output channel per role: props, JSON, errors, documents, exports, media, history state, logs | L4, L5 | SECURITY_MODEL section 7 |
| Recovery | Client states, resolution, receipts across devices, no replacement while uncertain | L4, L5 | ADR-007, ADR-009, INTERACTION_CONTRACTS section 2 |
| Localization | Translation keys only; build fails on a literal or a missing key | L1, CI | ADR-005, REQ-NFR-01 |
| Document | Snapshots render the same content in every format; copy marker; CSV dialect | L2, L5 | DOCUMENT_CONTRACTS |
| Operations | Backup, drill, after-restore procedures, operator procedures, status evidence | L6 | BACKUP_RECOVERY, OPERATIONS_RUNBOOK |

## 6. Static checks

| Check | Fails when | Source |
| --- | --- | --- |
| Composer validation, Pint, PHPStan with Larastan | Any error at the level the foundation Task sets | TOOLCHAIN |
| Process functions | `exec`, `shell_exec`, `system`, `passthru`, `popen`, a string-command `proc_open` or backticks appear in application code | THR-10 |
| Raw SQL | SQL built from input, or an unbound parameter | TR-VAL-07 |
| Floats in exact values | A floating-point type or operation in the money, quantity and cost code of the shared kernel and the domain modules | ADR-006 |
| ESLint, TypeScript typecheck | Any error; `react/jsx-no-literals` on interface code | ADR-005 |
| Translation keys | A key used in code is missing from `lang/id`, or a literal user-facing string exists | REQ-NFR-01 |
| Migration review | A migration contains `DROP`, `TRUNCATE`, `DELETE` or `UPDATE` of retained data without the approval reference of REL-06 | PDS §1, REL-06 |
| Secret scan | A secret-looking value is in the tree or the history being pushed | SEC-SECRET-01, D-076 |
| Dependency audit | `composer audit` or `npm audit` reports a high or critical advisory that is not recorded as accepted for a stated reason | THR-14 |

Each check is proven on a deliberately violating sample. The samples of the secret scan are generated by its test at run time outside the tracked tree and removed afterwards: a secret-looking value is never committed, not even a fake one, so that neither the scan nor the publication guard of D-076 needs an exception for it (D-091; [EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md) EXC-63).

## 7. CI gates

The `ci` workflow (ADR-011 item 4) runs the command contract that the foundation Task creates: `composer validate`, `composer lint`, `composer analyse`, `composer test:unit`, `composer test:postgres`, `composer test:race`, `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build` and `npm run test:e2e`.

| Event | Required | Blocks |
| --- | --- | --- |
| Pull request into `develop` | Section 6; L1; L2; L3 for the modules the change touches and every race that names them; L4; the L5 critical paths already implemented on `develop` or by the change — sign-in, checkout cash and transfer, close, drawer-funded refund, one recovery case — each from the Task that delivers it ([EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md) EXC-34; D-091) | The merge |
| Push to `develop` | Everything above; all of L3; all of L5 with the accessibility checks | The next release candidate |
| Release tag | The release workflow (RELEASE_PROCESS section 4) | Publication of the image |

No scheduled workflow is relied on: GitHub may disable scheduled workflows of a quiet public repository.

## 8. Evidence

| Status | Meaning |
| --- | --- |
| `NOT_RUN` | Not executed on this revision |
| `PASS` | Executed on a named revision and target; every case passed |
| `FAIL` | Executed; at least one case failed |
| `BLOCKED` | Cannot run yet; names the gate or fact it waits for (for example gate DEVICES or TARGET) |
| `FINAL_PASS` | `PASS` at the suite's final point on the release candidate (section 9) |

| ID | Rule | Source |
| --- | --- | --- |
| TS-30 | An evidence record holds: the suite and cases; the revision and, where there is one, the image digest; the environment and target, described without real identities; the dataset; the commands; the date and time (Asia/Jakarta); the result per case; who ran it (by role); the reviewer; the CI run; gaps; and what later invalidated it. | L-08 §Bukti |
| TS-31 | Automated evidence is the CI run plus a summary in the Task's record (the folder is set in RF-7); operational, device and pilot evidence is the operator's private record plus a summary without identities in the repository. | ENVIRONMENTS section 17 |
| TS-32 | A change to the source, schema, assets, configuration, image, server or device that a suite covers invalidates that suite's `FINAL_PASS`; it is run again on the new revision. Old evidence is never edited to look current. | PROJECT_RULES section 6 |

## 9. Acceptance gates

| Gate | Passes when | Decided by |
| --- | --- | --- |
| Task done | The Task's cases `PASS` on its revision and its Definition of Done holds (section 10) | The reviewer; the Owner per the Task plan of RF-7 |
| Integration | `develop` is green with all of L3 and L5 | CI and the reviewer |
| Release candidate | Every automated suite `PASS` on the candidate; the operational suites run on staging where they can | The final verification Tasks (section 12), which record `FINAL_PASS` |
| TARGET | ENVIRONMENTS section 17, SEC02 and PERF01 on the intended server | The operator's evidence; the Owner |
| DEVICES | DEVICE01 `FINAL_PASS` on the shop's devices | The Owner |
| G4 | REC01 and REC02 `FINAL_PASS`, a production drill, and the measured recovery point and time accepted (BACKUP_RECOVERY section 12) | The Owner |
| G1 to G3 | The business facts recorded (OPEN_ITEMS section 3) | The Owner |
| RELEASE | Every gate above and the Owner's exact authorization (RELEASE_PROCESS REL-01) | The Owner |
| Pilot | RELEASE01 `FINAL_PASS`: PIL-01 to PIL-11 (UAT_PILOT section 3) | The Owner |

## 10. Definition of Done

Every Task records, for each dimension, `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason, and the evidence of section 8. `N/A` never hides a real risk.

| Dimension | Evidence when the Task touches it |
| --- | --- |
| Business | The acceptance criteria of its requirements and the rules they cite, including refusals and recovery |
| UI and UX | The approved P7 artifacts (D-082, D-086, D-087): structure, copy keys, states, design; responsive widths; keyboard; no dead control |
| Backend | FormRequest allowlists; policies and object scope; in-transaction guards; DTO allowlists; L4 tests |
| Database | The migration reviewed and rehearsed (REL-20, REL-21); the constraint, deferred and privilege families for its tables; real PostgreSQL |
| Security | The threat paths of SECURITY_MODEL for the surfaces touched; the refusals of the other roles; no secret; the static checks |
| Data integrity | Append-only facts; atomic audit and result; identity and idempotency; the races and failure injection that name it |
| Performance | Bounded queries and paging; the budgets of TECHNICAL_RULES section 13 measured at the final point |
| Operations | Backup and restore impact (new tables in the manifest's counts, new files in the backup); runbooks and status evidence updated |
| Verification | The command contract green; self-review; the independent review of section 11 where it applies; evidence recorded |

## 11. Reviews

- Every Task: the executor's self-review and the reviewer's check of its evidence against the repository. Under continuous execution the isolated reviewer agent makes the reviewer's check ([EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md) CE-40, D-101).
- A Task that touches money, stock, cash sessions, cost, authorization, recovery, a migration or an operator procedure also gets an independent adversarial review in a separate session before it is done; its findings are fixed and verified (D-075 review method). Under continuous execution, the isolated, read-only reviewer agent is that separate session, at the effort that EXECUTION_CONTEXT CE-11 sets by the Task's class and risk, with one review and one verification of the fixes (CE-40, D-101).

## 12. Final verification

Under FD-07 these final points are dedicated verification Tasks of RF-7; ACCEPTANCE_SUITES section 3 assigns each suite to one.

| Final point | Suites | Where |
| --- | --- | --- |
| Integrated verification | Every suite at L1 to L5 | CI and staging, on the release candidate |
| Operations verification | DB01, KEEP01, REC01, REC02, OPPROC01, STATUS01, SEC02, READY01 | Staging, a drill target and the intended server |
| Device verification | DEVICE01, and the manual part of UX02 | The shop's devices, on staging |
| Performance verification | PERF01 | The intended server |
| Pilot | RELEASE01 | Production, after the authorized release |

## 13. Open items

| Item | Subject | Step |
| --- | --- | --- |
| RF-7 | The Tasks that implement these suites, the final verification Tasks, and the folder of evidence summaries | RF-7 |
| Gates TARGET, DEVICES, G1 to G4 | Facts and devices that the blocked suites wait for | Before the pilot |
