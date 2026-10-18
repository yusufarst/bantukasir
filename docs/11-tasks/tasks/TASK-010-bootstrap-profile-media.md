# TASK-010 — Installation bootstrap, BusinessProfile and media

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 2 Platform core · lane P, platform trunk |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-008](TASK-008-command-platform.md), [TASK-002](TASK-002-p7-change-control-1.md) |
| Gates | GATE-CC1 ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-010-bootstrap-profile-media` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. Context7 for the image library chosen and the framework's storage layer (EXC-03). |
| Modules | Configuration, Media, Access (operator procedures), Inventory (installation state) |
| Baseline | RF-7, D-090 |

## 1. Goal

The installation's first state through the bootstrap procedure OP-01; the shop identity with immutable BusinessProfile revisions and a logo (RC-32); the media pipeline with private storage and authorized delivery (RD-07); the settings index and the profile pages; and the shop's identity and accent in the shell.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SECURITY_MODEL SEC-OP-01, section 13, section 14 (common rules) and CAP-30
- OPERATIONS_RUNBOOK OPR-02, OPR-06 and section 8
- RELEASE_PROCESS RB-23 step 7
- SCHEMA sections 5.2, 5.3 (`recovery_epoch`), 5.5 and 5.7 (`onboarding_state`, `onboarding_releases`), G-20 and G-23
- TECHNICAL_RULES section 7.2 (BusinessProfile change; bootstrap), TR-TX-05, RACE-25 and RACE-30
- ARCHITECTURE ARC-40 to ARC-42 and ARC-80 to ARC-82
- INTERACTION_CONTRACTS RC-32 and section 3.7
- ROUTE_CONTRACTS RC-32, RD-07, RP-601, RP-607, RP-608 and OP-01
- DOMAIN_MODEL section 10 (BR-CFG-01 to BR-CFG-05)
- DESIGN_SYSTEM sections 3.3 and 7.2
- COPY_DECK sections 11.1, 11.7 and 11.8 and the TASK-002 accent names
- CRITICAL_FLOWS WF-30 and WF-32

## 4. Scope

### 4.1 In scope

1. Create `business_profile_revisions`, `business_profile_current`, `stored_files`, `onboarding_state` and `onboarding_releases` (with the deferred `release_presence` key), with their grants.
2. Build the media pipeline: content judged by decoding (PNG, JPEG, WebP; SVG, HTML, GIF and remote URLs refused), dimensions read from the header before decoding, animated images refused, size limits, orientation applied, re-encoding and metadata removal by an open-source image library chosen here with its licence recorded and external delegates disabled (SEC-UP-03); random storage keys never derived from input; private storage outside the web root; the file written before the transaction (TR-TX-05); `sha256` recorded; a stored file never overwritten or deleted; RD-07 for signed-in users, a logo for every role, `Cache-Control: private, max-age=86400, immutable`, never a path.
3. Implement OP-01 `owner:bootstrap` (SEC-OP-01) and the operator-procedure framework that OP-02 to OP-08 reuse: the operator types the environment's name, compared with the configuration and the database marker (OPR-02); typed, validated options only (THR-10); refusal when any account exists; in one transaction the SUPER_ADMIN account, the recovery epoch of at least 128 random bits, `onboarding_state` NOT_STARTED and the first BusinessProfile revision with its pointer (G-23); the FIRST_PASSWORD link through the TASK-006 broker printed only to the terminal (OPR-06); the audit event with the procedure as actor and the operator's note; the security log entry `OPERATOR_PROCEDURE_RUN`.
4. Implement RC-32 `business_profile.change` (the Owner; recent authentication; multipart with the logo bucket; a new immutable revision; the pointer moved under its update lock; audit).
5. Build UXP-601 (with the entries the role may open; later Tasks add theirs), UXP-607 (the revision in force and earlier revisions) and UXP-608 (the form with its expected version and the accent set named by the TASK-002 keys).
6. Deliver the shop's name, initials and accent to the shell and the document title (DESIGN_SYSTEM section 7.2) and resolve the reading against SEC-CH-02, whose shared-props allowlist does not name them: a per-page prop, or an amendment of SEC-CH-02 for this non-sensitive configuration that every role sees, recorded in the evidence and in SECURITY_MODEL; stop if the reviewer finds it changes a privacy rule.
7. Keep the code client-neutral: no client name or logo in code or bundled assets; "BantuKasir" never shown as the shop (REQ-SCOPE-03).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `business_profile_revisions`, `business_profile_current`, `stored_files`, `onboarding_state`, `onboarding_releases` |
| Keys completed (EXC-44) | — |
| Routes | OP-01, RC-32, RD-07, RP-601, RP-607, RP-608 |
| Route elements added | — |
| Pages | UXP-601 "Pengaturan", UXP-607 "Profil usaha", UXP-608 "Ubah profil usaha" |
| Controls | 11: UXI-008, UXI-327, UXI-358, UXI-359, UXI-360, UXI-361, UXI-362, UXI-363, UXI-364, UXI-365, UXI-366 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Product photos and thumbnails (TASK-018).
- The cleanup of unreferenced files (TASK-038).
- Snapshots that freeze the identity (TASK-028, TASK-030).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CFG-01, REQ-CFG-03, REQ-SCOPE-03 | REQ-CFG-02, REQ-ACC-05, REQ-ACC-12, REQ-UX-01 |
| Workflows | — | WF-30, WF-32 |
| User tasks | — | — |
| Acceptance suites | IDENTITY01 (final: TASK-044), OPPROC01 (final: TASK-045) | MEDIA01, SEC01, SESSION03 |
| Races | — | RACE-25, RACE-30 |
| Guards | G-20, G-23 | — |
| Threats | THR-09, THR-12 | THR-10 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-010-01** OP-01 refuses when any account exists and when the typed environment differs from the configuration or the marker; of two concurrent runs exactly one succeeds (RACE-30); afterwards one ACTIVE SUPER_ADMIN account, the epoch, `onboarding_state` NOT_STARTED and a revision with its pointer exist (G-23); the link appears only on the terminal; the audit event and the security log entry are written.
- **AC-010-02** Every refusal case for logos in SECURITY_MODEL section 13 is refused before any fact; an accepted logo is re-encoded without metadata, stored under a random key with its `sha256`, and never overwritten.
- **AC-010-03** RC-32 creates an immutable revision and moves the pointer, audited; it needs recent authentication; every other role is refused; a stale expected version is refused `STALE_VERSION`.
- **AC-010-04** RD-07 serves a logo only to a signed-in user, with private caching and never a path; a guessed identifier answers `NOT_FOUND`.
- **AC-010-05** The accent of the current revision applies to every page, named by the approved keys.
- **AC-010-06** UXP-601, UXP-607 and UXP-608 follow the approved artifacts; the settings index shows only the entries the role may open.
- **AC-010-07** A scan of the code and the built assets finds no client name or logo.
- **AC-010-08** The constraint, deferred and privilege families pass for the five tables; `onboarding_state` is RELEASED only with its release record.

## 7. Tests

- L2 families for the five tables.
- L3 RACE-30 bootstrap part.
- L4 uploads, RC-32 and RD-07.
- L5 pages and the accent.
- OP-01 procedure tests (L2).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-010.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The image library, version and licence.
- The reading of SEC-CH-02 and the choice made.

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

- The image library cannot disable external delegates.
- The SEC-CH-02 reading would change a privacy rule.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- SECURITY_MODEL SEC-CH-02 (only if that reading is chosen)
- TOOLCHAIN (the image library)
- STACK_AND_MODULES section 2.1 (if Configuration's dependency on Media is extended)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Fixtures now create the Owner through OP-01 (EXC-45).
- TASK-011 adds OP-02 and OP-03 to the procedure framework; TASK-018 extends the media pipeline to photos.
- Tasks that depend on this one directly: [TASK-011](TASK-011-user-management.md), [TASK-013](TASK-013-independent-backup.md).

## 13. Expected files

- `app/Domain/Configuration/`, `app/Domain/Media/`
- `app/Console/Commands/` (`owner:bootstrap`, the procedure framework)
- `resources/js/pages/configuration/`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
