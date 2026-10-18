# TASK-003 — Shared kernel, formatting and localization

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 1 Foundation · lane P, platform trunk |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-001](TASK-001-toolchain-ci.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-003-kernel-localization` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for Brick Math, Laravel localization and Inertia shared props (EXC-03). |
| Modules | Shared kernel (`app/Support`), Localization (`lang/id`, `resources/js/lib`) |
| Baseline | RF-7, D-090 |

## 1. Goal

Build the shared kernel — exact Money, Quantity and Cost values, canonical strings, the business date and clock boundary, the formatters — and the localization mechanism of ADR-005, so that every later Task computes, transports and shows numbers, dates and words through one proven implementation.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- ADR-006 (all)
- ADR-005 (all)
- ADR-008 section 5 (rounding)
- COPY_DECK sections 1.1 to 1.4 and 2
- TECHNICAL_RULES section 11.1 and TR-VAL-04, TR-VAL-05
- ARCHITECTURE ARC-50 and ARC-51
- STACK_AND_MODULES section 2 (the shared kernel) and STK-31
- DOMAIN_MODEL section 7 (BR-NUM-01 to BR-NUM-07)
- SCHEMA section 3.2
- COSTING section 9 and BR-COST-37 (periods)
- TEST_STRATEGY section 3 (clock), section 5 (Localization family) and TS-08

## 4. Scope

### 4.1 In scope

1. In `app/Support`, value objects for Money (whole rupiah on `BigInteger`), Quantity (`BigDecimal` with the product's precision of 0 to 3) and Cost (scale 6, KNOWN or UNKNOWN), with canonical-string parsing and formatting that refuse every non-canonical form of ADR-006 item 2, range checks of TECHNICAL_RULES section 11.1, line arithmetic rounded half-up once (ADR-006 item 4) and the cost rounding of ADR-008 section 5.
2. Eloquent casts to these types with no float conversion (ADR-006 item 5); their database round-trip is proven by TASK-004.
3. Validation rules for canonical number strings, for use by every FormRequest.
4. The clock boundary of STK-31: the application clock in UTC and a controllable test clock; the Asia/Jakarta business date of a time; the half-open periods `today`, `7d`, `this_month`, `last_month` and `custom` of BR-COST-37 as pure functions.
5. Server and client formatters for every format of COPY_DECK sections 1.3 and 1.4 — money, negative money, quantity, cost on screen, date, date and time, time of day, the WIB marker where a rule asks for it, durations, counts and the variance words — reading month and day names from `ux.format.*`, never from browser or server locale data.
6. TypeScript exact-number helpers for previews with scaled `BigInt` (ADR-006 item 6), and lint rules that forbid `Number()`, `parseFloat`, unary `+` and `Intl.NumberFormat` on these values.
7. Localization per ADR-005: `lang/id/` with the files of COPY_DECK section 1.1, holding the `ux.format.*` keys and the keys this Task's own code uses; locale and fallback `id`; no switcher; the dictionary shared once through `Inertia::shareOnce`; the typed `t(key, replacements)` helper with `:name` placeholders; a TypeScript key type generated from `lang/id/`; a build check that fails on a key used in PHP or TypeScript and missing from `lang/id/`; `Lang::handleMissingKeysUsing` throwing in tests.

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

- Keys of pages and messages, added verbatim from COPY_DECK by the Tasks that use them.
- Database tables and the harness (TASK-004).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-COST-02, REQ-NFR-01, REQ-NFR-09, REQ-DOC-05 | REQ-SALE-03, REQ-NFR-08, REQ-RPT-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | L10N01 (final: TASK-044) | FIN01, POS02, SESSION09, FIN03 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-003-01** The pure arithmetic cases of ACCEPTANCE_SUITES FIN01 pass at L1: 1.5 × Rp3.333 gives a line total of Rp5.000; half-up once per line; six-decimal cost; quantities with 0 to 3 decimals; an overflow is refused; no floating point.
- **AC-003-02** Every non-canonical input of ADR-006 item 2 is refused, never repaired; no money, quantity or cost value is ever a JSON number in a request or a response the kernel builds.
- **AC-003-03** The float static check passes on `app/Support` and `app/Domain` and fails on its negative sample.
- **AC-003-04** Server and client formatters give exactly the formats of COPY_DECK sections 1.3 and 1.4 from the same inputs, also with the operating system and browser set to another locale.
- **AC-003-05** Business dates across Asia/Jakarta midnight and the five periods as half-open ranges are correct with the test clock (BR-NUM-05, BR-NUM-06, BR-COST-37).
- **AC-003-06** A key missing from `lang/id/` fails the build and the typecheck; a literal in JSX fails lint; a missing server key throws in tests; the dictionary reaches React once per full page load.

## 7. Tests

- L1 arithmetic, canonical-string and rounding tests (PHP and Vitest).
- L1 formatter tests on server and client against the same table of cases.
- L1 period and business-date tests with the test clock.
- The localization build checks with negative samples.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-003.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The table of arithmetic and formatting cases with their results.
- The adversarial review of the arithmetic (class A).

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
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A rule of ADR-006 or ADR-008 section 5 cannot be met with Brick Math on the pinned version.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- STACK_AND_MODULES section 3 (only an adaptation under STK-22)
- TOOLCHAIN (the browser decimal helper, if a library is used, with its licence)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every Task uses these types, formatters and `t()`; no Task formats money, quantities or dates on its own.
- Tasks that depend on this one directly: [TASK-004](TASK-004-database-foundation.md), [TASK-005](TASK-005-design-system.md).

## 13. Expected files

- `app/Support/`
- `lang/id/*.php`
- `resources/js/lib/` (exact numbers, formatters, `t()`)
- `resources/js/types/` (generated key type)
- `tests/Unit/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
