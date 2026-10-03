# Toolchain

The capabilities this project needs, the tool chosen for each, and its real status. The requirement is the capability; a named tool may be replaced by an equivalent that is already present and adequate.

**Nothing is installed.** There is no application, so every implementation tool below is `PLANNED`. A status is changed to `VERIFIED` only by the Task that installs the tool and proves it works, recording version, scope and the verification performed.

## 1. Capabilities

| Capability | Tool | Requirement | Status | Notes |
| --- | --- | --- | --- | --- |
| Current documentation for version-sensitive work | Context7, or the official documentation of the exact version | REQUIRED before relying on any framework or library API | NOT VERIFIED in the executor environment | Do not rely on remembered APIs for Laravel, Inertia, React, TypeScript, Tailwind, shadcn/ui or PostgreSQL. |
| Codebase impact analysis | Graphify, or equivalent targeted search | DEFERRED until application code exists, then required for significant changes | NOT INSTALLED | A map, not an authority. |
| Browser end-to-end testing | Playwright, as a project dev dependency | REQUIRED for every interface Task | PLANNED | Use the project package and CLI, not an MCP server, unless interactive use materially helps. |
| Interface components | shadcn/ui with Tailwind CSS | REQUIRED; use before creating new primitives | PLANNED | |
| Visual reference and design authoring | DesainPakeAI, through its command-line tool `dpai` | REQUIRED for P7 design work | Version 0.2.2 was used on 2–3 October 2026 (legacy evidence); not re-verified | See section 3. The framework spells the product "DesainPakaiAI"; the tool's own name is DesainPakeAI. |
| PHP quality | Pint, PHPStan with Larastan, Pest or PHPUnit | REQUIRED from the foundation Task | PLANNED | Real PostgreSQL is required for constraint, rollback and race tests; SQLite and mocks are not evidence for these. |
| Frontend quality | Lint, TypeScript typecheck, Vitest where behaviour warrants it, Vite production build | REQUIRED from the foundation Task | PLANNED | |
| Version control | Git on the Owner's Windows machine | REQUIRED | In use | Identity and safety rules in [PROJECT_RULES](PROJECT_RULES.md) section 9. |

Versions are pinned, with lockfiles, by the foundation Task after checking support windows, advisories and compatibility. Production never uses a floating `latest` tag.

## 2. Installing tools

An agent may install a missing development tool on its own only when the active Task needs it and the installation is reversible, project-local or a dev dependency where possible, exposes no secret, triggers no unrelated major upgrade and touches nothing in production. After installing, verify the tool and record tool, purpose, version, scope, verification and files changed.

Stop and ask the Owner when a tool needs a login, an API key, a subscription or payment, administrator rights, an external account, or a risky infrastructure change. Complete every safe step first, then state the minimum action needed.

No tool is installed during the re-foundation steps.

## 3. Design tool safety

- Use only the design project **BantuKasir Design**, identifier `eddb0e15-9909-44b3-8517-9239cadc65ef`.
- Before reading context and before every mutation, run `dpai project current --pretty` and confirm that exact name and identifier. On any mismatch, stop before further access.
- Never select, read, export or mutate a MULTIPLECORP design project, and never use its assets, tokens or layouts.
- Do not use the DesainPakeAI MCP server. Do not repeat login or setup inside a Task. Never store an access key in the repository, in evidence or in a screenshot.
- Design mutations and anything that may consume paid credit need the Owner's authorization for that scope.
- A design tool may propose layout, hierarchy, composition, interaction patterns and design-system choices. It may not redefine business rules.
- Do not claim the tool was used when it was not.
- The prototype output of the tool is never shipped as the application.

## 4. MCP policy

Installed does not mean exposed. Each session exposes only the MCP servers the active Task needs.

| Rule | |
| --- | --- |
| Default for heavy or specialized MCP servers | OFF |
| Prefer | The project's own CLI or package when it does the job |
| Activate | Only for the Task that needs the capability, and deactivate afterwards where practical |
| Never | Keep browser, cloud or infrastructure MCP servers active during unrelated work; duplicate one capability across several servers |

Each Task contract states its required MCP servers and the ones that stay off. No MCP server is currently required by this project.

## 5. Retired tools

The earlier planning rounds used a review tool named Impeccable and referred to 21st.dev. Neither is part of the workflow. Their tracked outputs are retired in RF-1 (see [LEGACY_INDEX](LEGACY_INDEX.md)).
