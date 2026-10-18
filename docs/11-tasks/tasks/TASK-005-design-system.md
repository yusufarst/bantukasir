# TASK-005 — Design system and frontend foundation

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 1 Foundation · lane P, platform trunk |
| Size and review | L · review class B — self-review and the reviewer's check ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-002](TASK-002-p7-change-control-1.md), [TASK-003](TASK-003-kernel-localization.md) |
| Gates | GATE-CC1 ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-005-design-system` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Playwright through the project's own package (TOOLCHAIN section 1). |
| Modules | Frontend (`resources/js/components`, `layouts`, styles) |
| Baseline | RF-7, D-090 |

## 1. Goal

Implement the approved design system in the stack — tokens, fonts, the accent set, the components and state presentations of DESIGN_SYSTEM, the print base and the keyboard primitives — with the browser test harness and the automated accessibility checks, so that every page Task composes approved components instead of inventing them.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- DESIGN_SYSTEM sections 1 to 14
- KEY_SCREENS sections 1 to 5 and the previews' `assets/` and `ds-specimen.html`
- ADAPTIVE_AND_ACCESSIBILITY sections 2 to 4
- UI_STATES sections 1 and 2
- COPY_DECK sections 1, 2, 5 and 12
- SECURITY_MODEL SEC-HDR-03
- TOOLCHAIN section 3
- TEST_STRATEGY TS-25
- ADR-005 item 5

## 4. Scope

### 4.1 In scope

1. Transcribe the tokens of DESIGN_SYSTEM sections 3 to 5 into CSS variables and the Tailwind theme — neutrals and text, status tones, the accent set (green, teal, blue, purple, graphite; green by default) selected per BusinessProfile through one attribute, typography, spacing, radius, elevation, motion and layers — and the icons of section 6 as the stack's own components.
2. Self-host the Inter font with its SIL Open Font License file; load no third-party origin.
3. Build the components of DESIGN_SYSTEM section 8 (8.1 to 8.16) with shadcn/ui primitives before new ones, with the sizes, states, keyboard and focus behaviour stated there; the till workspace (8.14) and receipt paper (8.16) as primitives that TASK-029 and TASK-031 assemble.
4. Build the layout primitives of section 7 — width classes at the provisional 600 and 1024 CSS px (UXA-02), containers, the page archetypes of section 7.3 — without the shell, which TASK-007 builds.
5. Build the presentation of every state UXS-01 to UXS-50 of UI_STATES section 2 that is a component, with its COPY_DECK key, never showing zero or blank for an unknown or failed value (section 9).
6. Build the shortcut registry with the provisional shortcuts of DESIGN_SYSTEM section 11 (D-075) for later pages to bind; Enter never activates a final, confirm or print action (UXA-10); the repeat guard of Ctrl+Enter.
7. Make every component work under the content security policy of SEC-HDR-03: no inline script or style without the nonce; styles that libraries inject at run time receive the nonce or are bundled.
8. Set up the browser harness: Playwright fixtures, automated accessibility checks per component (TS-25), the widths of KEY_SCREENS section 4 and 1440 px, the 200 % zoom check, contrast measurement, reduced motion; a component specimen available only in the testing environment.

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

- The application shell and navigation (TASK-007).
- Any page.
- Copy keys of pages (the page Tasks).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-UX-01, REQ-UX-02, REQ-UX-03, REQ-NFR-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | UX02 (final: TASK-044, TASK-046) | UX01, L10N01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-05 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-005-01** The token values equal DESIGN_SYSTEM sections 3 to 5 and the approved `bk-tokens.css`, proven by a snapshot comparison.
- **AC-005-02** Each component of DESIGN_SYSTEM section 8 has the stated sizes and behaviour; the keyboard and focus rules of UXA-08 and UXA-10 are proven in Playwright.
- **AC-005-03** Each state presentation uses its COPY_DECK key and never shows zero, blank or "Normal" for an unknown, failed or stale value.
- **AC-005-04** The automated accessibility checks pass for every component at every width; rendered contrast is at least 4.5:1, or 3:1 for large text, and the focus ring at least 5.91:1 against what surrounds it (DESIGN_SYSTEM section 3.5).
- **AC-005-05** Every component renders under the content security policy of SEC-HDR-03 with no violation reported in the browser console.
- **AC-005-06** Fonts are self-hosted with their licence file; no request leaves the application's origin.
- **AC-005-07** No user-facing literal exists in the components; lint and the typecheck pass.
- **AC-005-08** The evidence names what the Golden Frontend Reference v1.0.0 contributed, read at commit `14497d9`, and confirms that no MULTIPLECORP material was used (EXC-05).

## 7. Tests

- Component tests in Playwright with the accessibility checks at every width (L5).
- Token snapshot comparison (L1).
- CSP check of the specimen (L5).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-005.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The component list with the reference used for each.
- The contrast measurements.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | N/A — no request path |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class B (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- An approved token or component cannot be built as DESIGN_SYSTEM states in the stack.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- TOOLCHAIN section 1 (the accessibility checker and its licence)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every page Task composes these components; TASK-007 builds the shell on them.
- Tasks that depend on this one directly: [TASK-006](TASK-006-authentication.md).

## 13. Expected files

- `resources/js/components/`
- `resources/js/layouts/`
- `resources/css/`
- `public/fonts/` with the licence file
- `tests/Browser/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
