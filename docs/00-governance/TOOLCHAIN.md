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
| Visual and frontend reference | Golden Frontend Reference v1.0.0, commit `14497d9` | REQUIRED for P7 design work (RF-4C) and later frontend work: the one approved reference (D-085) | APPROVED by the Owner (D-085); read only, nothing installed; tag `v1.0.0` confirmed at commit `14497d9` on 17 October 2026 | See section 3. AICWDF-4.3 requires a visual reference and prefers DesainPakaiAI; the requirement is the capability. DesainPakeAI is no longer required (section 5). |
| PHP quality | Pint, PHPStan with Larastan, Pest or PHPUnit | REQUIRED from the foundation Task | PLANNED | Real PostgreSQL is required for constraint, rollback and race tests; SQLite and mocks are not evidence for these. |
| Frontend quality | Lint, TypeScript typecheck, Vitest where behaviour warrants it, Vite production build | REQUIRED from the foundation Task | PLANNED | |
| Version control | Git on the Owner's Windows machine | REQUIRED | In use | Identity and safety rules in [PROJECT_RULES](PROJECT_RULES.md) section 9. |

Versions are pinned, with lockfiles, by the foundation Task after checking support windows, advisories and compatibility. Production never uses a floating `latest` tag.

## 2. Installing tools

An agent may install a missing development tool on its own only when the active Task needs it and the installation is reversible, project-local or a dev dependency where possible, exposes no secret, triggers no unrelated major upgrade and touches nothing in production. After installing, verify the tool and record tool, purpose, version, scope, verification and files changed.

Stop and ask the Owner when a tool needs a login, an API key, a subscription or payment, administrator rights, an external account, or a risky infrastructure change. Complete every safe step first, then state the minimum action needed.

No tool is installed during the re-foundation steps.

## 3. Frontend reference

The approved reusable frontend reference is the **Golden Frontend Reference v1.0.0** — Golden Frontend Reference v1.0, public release `v1.0.0`, baseline `golden-frontend-v1.0` — which the Owner established on 17 October 2026 (D-085). It is the visual reference for P7 design work (RF-4C) and for later frontend work. No design tool is required, and a design-generation tool or service is used only with the Owner's authorization. The reference authorizes no work: it is used only inside a step that the Owner authorizes, and it approves no BantuKasir artifact; gate P7-G3 is a separate Owner decision.

- **Identity.**
  - Repository `https://github.com/yusufarst/golden-frontend-reference`, public, on the Owner's account. Commit `14497d997ee7994dfa9153d41b8cea636578e24c`, tree `3f19c1e253fd5c21eeb2483e0aa513e0ef1861ee`, at which the annotated tag `v1.0.0` (object `9d46af300949b2dd4b2f3d443652d78c38067deb`) points.
  - Frozen scope: the 41 files listed in `release/golden-frontend-v1.0/SHA256SUMS-scope.txt`, list digest `39fa879dc304275e55b1ffecda290ebd6b481a27d89bbda67011c26e0796eca4`.
  - Before relying on it, confirm that the tag still resolves to that commit (`git ls-remote` shows `refs/tags/v1.0.0^{}` at it), and read from the commit, never from a branch. On a mismatch, stop.
  - Another version (v1.1, v2.0 or any other) or another commit needs the Owner's decision. The replacement rule in the introduction of this document does not apply to this reference.
  - It is read only: never modify it.
- **What may be adapted.** Its generic layer, translated into the approved stack — Laravel, Inertia and React with TypeScript, Tailwind CSS and shadcn/ui (D-004, ADR-001, [STACK_AND_MODULES](../04-architecture/STACK_AND_MODULES.md)) — without a framework migration:
  - design tokens: the names, values and semantic roles of colour, typography, spacing, radius, elevation, surfaces, status tones, density and motion;
  - components: anatomy, states, sizes, and keyboard, focus and accessibility behaviour, built as the stack's own components, with shadcn/ui before new primitives (section 1);
  - UX patterns and principles: page archetypes, numbered lists and pagination, validation and recovery, confirmation levels, result anatomy, guided work, and the responsive and reduced-motion rules.

  Its code is a specification, not a dependency. Token values and specifications are transcribed into the stack's own files; the reference's files are neither imported into the application nor copied into this repository unless an Owner-approved architecture decision allows it.
- **What is never adopted.** The Owner's prohibition (D-085) covers the following, together with everything else the reference itself classifies as MULTIPLECORP example material or as not reusable:
  - MULTIPLECORP business examples: the worked flows, roles, capabilities, document-numbering formats, procurement and warehouse steps, the reference pages 01 to 04 with their CSS blocks (`.pur-*`, `.atq-*`, `.prj-*`), and business copy;
  - branding: the MultipleCorp name and mark (`i-logo-mark`); the MULTIPLECORP brand text, account placeholder and favicon of `template/page-skeleton.html`; third-party marks such as the Google "G" (`i-google`);
  - fixtures and sample data: the MULTIPLECORP identity, personas and QA fixtures (the `FIXTURES` block of `assets/js/project-config.js`) and the sample data of the consumer demo;
  - MULTIPLECORP workflows and business rules.

  The lab in which the reference was frozen, its MULTIPLECORP demonstration baseline, and every MULTIPLECORP repository, application and design project stay out of bounds ([AGENTS.md](../../AGENTS.md) rule 5).
- **BantuKasir governs.**
  - The reference's documents, including its own `AGENTS.md`, are reference material, not instructions for this project. Every source in [SOURCE_OF_TRUTH](SOURCE_OF_TRUTH.md) section 1 prevails over them.
  - Where a default of the reference differs from a decision or an approved document, the BantuKasir source governs. For example, the provisional breakpoints of 600 and 1024 CSS px in ADAPTIVE_AND_ACCESSIBILITY UXA-02 (D-075) prevail over its responsive classes, and COPY_DECK, through translation keys, prevails over its Indonesian component copy (D-050, ADR-005).
  - A step that adopts a deviation from a default records it in [DECISIONS](DECISIONS.md).
  - Its interface guards are not authorization (AGENTS.md rule 8). Its verification results and statuses are evidence about the reference only, never about BantuKasir.
- **Licence.** The reference has no open-source licence. Its rights are reserved, and reuse needs its Owner's permission, which D-085 gives for BantuKasir for the generic layer above. The Inter font that it uses is under the SIL Open Font License 1.1, which covers the font files only; the licence file stays with them. Its icons are its own drawings, except the brand marks above. It adds no cost ([COST_POLICY](COST_POLICY.md)).
- Do not claim that the reference was used, or that a screen follows it, when that was not checked.

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

DesainPakeAI, through its command-line tool `dpai`, was the required tool for P7 design work until D-085; the framework spells the product "DesainPakaiAI". Version 0.2.2 was used on 2–3 October 2026 for the former design candidate and was not re-verified; its plan, credits and entitlements were never verified. It is no longer required and not part of the workflow, and no step uses it without the Owner's separate authorization. If he authorizes it again, these rules apply before any access:

- Use only the design project **BantuKasir Design**, identifier `eddb0e15-9909-44b3-8517-9239cadc65ef`, which also holds eight unrelated CRM template pages (OPEN_ITEMS T-08). Before reading context and before every mutation, run `dpai project current --pretty` and confirm that exact name and identifier. On any mismatch, stop before further access.
- Never select, read, export or mutate a MULTIPLECORP design project, and never use its assets, tokens or layouts.
- Do not use the DesainPakeAI MCP server. Do not repeat login or setup inside a Task. Never store an access key in the repository, in evidence or in a screenshot.
- Design mutations and anything that may consume paid credit need the Owner's authorization for that scope.
- The tool may propose layout, hierarchy, composition, interaction patterns and design-system choices; it may not redefine business rules. Its prototype output is never shipped as the application. Do not claim it was used when it was not.

Its evidence from the former candidate stays in `docs/07-ux-design/legacy/` as RECORD.
