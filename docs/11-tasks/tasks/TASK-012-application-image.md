# TASK-012 — Application image and runtime configuration

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 3 Delivery infrastructure · lane O, operations and delivery |
| Size and review | M · review class B — self-review and the reviewer's check ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-004](TASK-004-database-foundation.md), [TASK-007](TASK-007-authorization-shell.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-012-application-image` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for the pinned Nginx, PHP-FPM and Laravel deployment settings (EXC-03). |
| Modules | Infrastructure (image, web server, process roles) |
| Baseline | RF-7, D-090 |

## 1. Goal

One reproducible image per revision that serves the application in process role `web` and runs the backup in role `backup`: pinned base images, Nginx and PHP-FPM with the time and size limits, the start-up settings check per role, the scheduled commands per role, the image checks, and the local environment moved onto the same image.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- ADR-002 items 1 to 6; ADR-003 items 3, 5, 7 and 8; ADR-010 item 6; ADR-011 items 5 and 6
- ENVIRONMENTS sections 6, 11, 13 and 14
- TECHNICAL_RULES section 9 (time limits; TR-TIME-03 worker pool) and section 11.4; ROUTE_CONTRACTS RT-12
- SECURITY_MODEL SEC-SECRET-03, SEC-HDR-04, SEC-UP-02 and THR-15
- RELEASE_PROCESS section 4 (image checks)
- PRODUCTION_DATA_SAFETY section 2

## 4. Scope

### 4.1 In scope

1. Write the image definition: production Composer dependencies, the Vite build, Nginx and PHP-FPM, the PHP extensions the application needs, the PostgreSQL client tools of the pinned server major version and `age` for role `backup`; base images pinned by digest; no development dependency or test fixture; a non-root runtime user where the platform allows it.
2. Select the process role from configuration: `web` serves HTTP and its scheduler registers SC-01, SC-02 and SC-04; `backup` serves nothing, publishes no port, and its scheduler registers only SC-03 (ADR-010 item 6). The commands are added by their Tasks; this Task provides the registration per role.
3. Check at start-up, per role, that every required setting exists and is not a default, without printing a value, and refuse to start otherwise (SEC-SECRET-03); cache configuration, routes and views at start; never run a migration at start (PRODUCTION_DATA_SAFETY section 2).
4. Configure Nginx to serve only `public/` and deny dotfiles, `vendor/`, `storage/` and every private path (THR-15); request body limits of RT-12 enforced at the proxy, in PHP and in validation, the outer limits just above the application's (SEC-UP-02); timeouts of at least 60 seconds; PHP-FPM `request_terminate_timeout` of 60 seconds; the worker pool sized for TR-TIME-03, stated as an assumption that TASK-048 measures.
5. Read the trusted proxies and hosts from configuration for the Coolify proxy and Cloudflare (ENVIRONMENTS section 10).
6. Write the image checks of RELEASE_PROCESS section 4 items 1 to 4: the application starts in role `web` against an ephemeral PostgreSQL with the migrations applied and `/up` answering 200; the start-up check refuses a missing or default setting in both roles; a secret scan of the image's file system and a vulnerability scan of its packages with open-source tools whose licences are recorded; no development dependency or fixture in the image.
7. Move the local environment onto this image, with development overrides only where needed, so that local, CI and release use one runtime.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | — |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The release workflow, registry publication and the migration job (TASK-014).
- The backup command itself (TASK-013).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-OPS-06, REQ-NFR-06, REQ-NFR-07 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | READY01, SEC01, DB01 |
| Races | — | — |
| Guards | — | — |
| Threats | THR-15 | THR-14 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-012-01** The image builds reproducibly from pinned base digests; it contains no development dependency or fixture; the evidence lists the image's packages and their scan results.
- **AC-012-02** Role `web` serves the application; role `backup` serves nothing, publishes no port and registers only SC-03.
- **AC-012-03** Each role refuses to start with a missing or default setting and prints no value; no container start runs a migration.
- **AC-012-04** Private paths, dotfiles, `vendor/` and `storage/` are unreachable through Nginx; the body limits of RT-12 hold at each layer; the request terminates after 60 seconds.
- **AC-012-05** The image checks of RELEASE_PROCESS section 4 pass in CI.
- **AC-012-06** The local environment runs on the same image.

## 7. Tests

- Image checks in CI (L6 rehearsal on synthetic data).
- L4 requests for private paths and oversized bodies against the image.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-012.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The base image digests.
- The scan tools, versions and licences, and their results.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | N/A — no request path |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class B (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A base image cannot be pinned by digest.
- A scan reports a high or critical finding without an accepted reason.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- TOOLCHAIN (the scan tools)
- ENVIRONMENTS section 11 (exact setting names, if they differ from the descriptive ones)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-013 runs in role `backup`; TASK-014 publishes this image.
- Tasks that depend on this one directly: [TASK-013](TASK-013-independent-backup.md).

## 13. Expected files

- The image definition and its Nginx and PHP-FPM configuration
- `app/Console/Kernel` or the scheduler registration per role
- The image-check scripts
- The local environment files

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
