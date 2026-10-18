# TASK-001 — Toolchain, repository conventions and CI

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 1 Foundation · lane P, platform trunk |
| Size and review | L · review class B — self-review and the reviewer's check ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | — |
| Gates | GATE-P11, GATE-BRANCH, GATE-PUBLISH (before its first push) ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-001-toolchain-ci` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7, or the official documentation of each pinned version, for version checks (EXC-03). |
| Modules | Repository and shared configuration; no business module |
| Baseline | RF-7, D-090 |

## 1. Goal

Create the empty application with pinned, verified versions, the directory layout, the command contract of TEST_STRATEGY section 7, the static checks of section 6, the CI workflow, the local container environment, the line-ending policy (OPEN_ITEMS T-01) and the rebuilt `.env.example` and `.gitignore` (T-02), so that every later Task starts from one reproducible, checked toolchain.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- TOOLCHAIN sections 1, 2 and 4
- STACK_AND_MODULES section 1 (STK-01 to STK-04, "Not part of the stack") and section 3 (STK-20 to STK-22)
- ADR-001; ADR-002 items 1 to 6; ADR-004 item 1; ADR-005 item 5; ADR-006 item 5
- TEST_STRATEGY sections 6 and 7; ADR-011 item 4
- ENVIRONMENTS section 2 (Local, CI) and section 11 (settings)
- SECURITY_MODEL SEC-SECRET-01, THR-10, THR-14
- PROJECT_RULES sections 9 and 10; OPEN_ITEMS T-01, T-02, T-14, T-15
- COST_POLICY section 1

## 4. Scope

### 4.1 In scope

1. Select and pin exact versions (STK-01) of PHP, Composer, Laravel, Laravel Fortify, Inertia (Laravel and React adapters), React, TypeScript, Vite, Tailwind CSS, the shadcn/ui tooling, Brick Math, Pest or PHPUnit (one of them), PHPStan with Larastan, Pint, ESLint with `react/jsx-no-literals`, Vitest, Playwright, Node.js (LTS) and the PostgreSQL major version with its container image pinned by digest; for each, check the support window, security advisories, licence and compatibility, and record it in TOOLCHAIN section 1 as VERIFIED with version, source and date. Commit `composer.lock` and the npm lockfile.
2. Create the Laravel application with Inertia and React with TypeScript, without the WorkOS starter-kit variant, Sanctum, Passport, Socialite, Redis or Valkey clients, queue workers, Horizon, Octane, server-side rendering or any component that STACK_AND_MODULES section 1 excludes.
3. Lay out the directories of STACK_AND_MODULES section 3; record any adaptation the pinned framework needs in that section (STK-22). Create no speculative class or interface (STK-30).
4. Create the command contract of TEST_STRATEGY section 7 as Composer and npm scripts: `composer validate`, `composer lint`, `composer analyse`, `composer test:unit`, `composer test:postgres`, `composer test:race`, `npm run lint`, `npm run typecheck`, `npm run test:unit`, `npm run build`, `npm run test:e2e`. Each runs and passes; suites may start with one smoke test, and `test:e2e` opens the running application in Playwright.
5. Implement the static checks of TEST_STRATEGY section 6: Pint; PHPStan with Larastan at a level stated in the evidence; the forbidden process functions of THR-10 (`exec`, `shell_exec`, `system`, `passthru`, `popen`, string-command `proc_open`, backticks); SQL built from input or unbound parameters; floating-point types and operations in `app/Support` and `app/Domain` (ADR-006 item 5); ESLint and the TypeScript typecheck with `react/jsx-no-literals` set as ADR-005 item 5 says; the translation-key check, wired now and completed by TASK-003; the migration review check (a migration with `DROP TABLE`, `DROP COLUMN`, `DROP SCHEMA`, `DROP DATABASE`, `TRUNCATE`, or `DELETE` or `UPDATE` of retained data, without the approval reference of REL-06, fails); a secret scan of the tree and of the commits a push would add; and the dependency audit (`composer audit`, `npm audit`), failing on a high or critical advisory not recorded as accepted with its reason.
6. Write the `ci` workflow of ADR-011 item 4 on GitHub Actions: on every pull request and every push to `develop` and `main`; PostgreSQL of the pinned version as a service container; the command contract; no secret of any environment; no scheduled trigger relied upon; workflows from forks never publish; nothing sensitive printed (TS-06).
7. Provide the local environment in containers: an application container with Nginx and PHP-FPM and a PostgreSQL container of the pinned version, started and stopped with one documented command each, on the Owner's Windows machine and on CI's Linux runner. TASK-012 later moves it onto the release image.
8. Apply the line-ending policy of OPEN_ITEMS T-01: a `.gitattributes` file (`* text=auto eol=lf` and the binary patterns the repository needs), committed alone, changing no content; then update the Windows note of EXECUTION_CONTEXT section 18 and CURRENT_HANDOFF section 2.
9. Rebuild `.env.example` with every setting of ENVIRONMENTS section 11 for the process roles `web` and `backup`, placeholders only, and `.gitignore` for Laravel and Node (environment files, dependencies, build output, coverage, test artifacts), keeping `.local/` and `.agents/` ignored. Neither file settles OPEN_ITEMS T-14 or T-15: `Claude outputs/` is left as it is.

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

- Database roles, the setup script and the test harness (TASK-004).
- The shared kernel and the localization mechanism (TASK-003).
- Design tokens and components (TASK-005).
- The release image and the release workflow (TASK-012, TASK-014).
- Any business code, migration or page.

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-NFR-01, REQ-NFR-07, REQ-NFR-08, REQ-OPS-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | SEC01, L10N01, DB01 |
| Races | — | — |
| Guards | — | — |
| Threats | THR-10, THR-14 | — |
| Open items | T-01 — resolved here: the line-ending policy, T-02 — resolved here: `.env.example` and `.gitignore` rebuilt, T-14, T-15 — not settled here (CURRENT_HANDOFF section 4) | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-001-01** Every component that STACK_AND_MODULES section 1 lists and the skeleton needs is pinned to an exact version, with lockfiles committed; TOOLCHAIN section 1 shows each as VERIFIED with its version, support window and advisory check; no floating tag or `latest` exists anywhere (STK-01).
- **AC-001-02** None of the components that STACK_AND_MODULES section 1 excludes is in the Composer or npm dependency tree, as the evidence shows from the resolved trees.
- **AC-001-03** The eleven commands of TEST_STRATEGY section 7 run and pass locally and, once GATE-PUBLISH allows the push, in the `ci` workflow, on the Task's final revision.
- **AC-001-04** Each static check of TEST_STRATEGY section 6 fails on a deliberately violating sample kept in a test fixture directory that the build excludes, and passes on the real tree.
- **AC-001-05** The `ci` workflow triggers only on pull requests and pushes to `develop` and `main`, starts PostgreSQL of the pinned version, references no secret other than the token GitHub issues to a workflow, and prints no environment value.
- **AC-001-06** `.gitattributes` is in a commit of its own that changes no other file's content; afterwards `git ls-files --eol` shows every tracked text file stored as LF.
- **AC-001-07** `.env.example` lists every setting of ENVIRONMENTS section 11 for both process roles with placeholder values only, and the secret scan passes on the tree and the history the first push would add.
- **AC-001-08** The local environment starts with one command on Windows and on Linux, and the Playwright smoke test opens the running application.

## 7. Tests

- The negative samples of every static check (L1, CI).
- The command contract in CI on the Task's final revision.
- The Playwright smoke test against the local environment.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-001.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The version table: component, version, licence, support window, advisory check, source and date.
- The CI run of the final revision, or `BLOCKED` on GATE-PUBLISH with the local results.
- `git ls-files --eol` before and after the line-ending commit.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | N/A — no request path |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class B (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A component's support window, advisory status or licence is unacceptable and no alternative fits the stack of ADR-001.
- Docker or another tool needs a login, a paid plan or administrator rights (EXC-14).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- TOOLCHAIN section 1 (statuses)
- STACK_AND_MODULES section 3 (only an adaptation under STK-22)
- README (the stack line)
- OPEN_ITEMS T-01 and T-02 (RESOLVED)
- EXECUTION_CONTEXT section 18 and CURRENT_HANDOFF section 2 (the Windows note)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every later Task runs the command contract created here.
- TASK-003 completes the translation-key check; TASK-012 moves the local environment onto the release image.
- Tasks that depend on this one directly: [TASK-003](TASK-003-kernel-localization.md), [TASK-004](TASK-004-database-foundation.md).

## 13. Expected files

- `composer.json`, `composer.lock`, `package.json`, the npm lockfile
- `app/`, `bootstrap/`, `config/`, `routes/`, `resources/js/`, `tests/`
- `phpstan.neon`, `pint.json`, the ESLint, TypeScript, Vite, Vitest and Playwright configurations
- `.github/workflows/ci.yml`
- The local container files
- `.gitattributes`, `.gitignore`, `.env.example`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
