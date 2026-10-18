# TASK-001 evidence — Toolchain, repository conventions and CI

| | |
| --- | --- |
| Task | [TASK-001](../tasks/TASK-001-toolchain-ci.md), size L, review class B, lane P |
| Status | `IN_PROGRESS`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | The Owner's instruction of 18 October 2026, "D-094 / D-095 bookkeeping + TASK-001 — executor instruction, rev2", recorded as D-094 and D-095 in [DECISIONS](../../00-governance/DECISIONS.md) |
| Branch | `task/TASK-001-toolchain-ci`, created from `develop` (EXC-22) |
| Base commit | `a2cb9713f56d59472677f40b09bf4c3016f73c51`, the D-094 bookkeeping commit on `develop` (parent `6e18185aa177834b55270cfac09a6d54670645f8`) |
| Executor | Implementation executor in Claude Code Desktop on the Owner's Windows machine; Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, as the Owner's instruction names it (D-095), and the desktop app's record of the session showed effort `xhigh`; a new session dedicated to the instruction |
| Gates | GATE-P11 APPROVED (D-092); GATE-BRANCH SATISFIED (D-094); EXECUTOR checked at the start; GATE-PUBLISH OPEN, a gate before `DONE`, so this Task reaches `VERIFYING` at most (EXC-21, AC-001-09) |
| Authorized beyond local commits | Nothing: no push, pull request, merge, tag or release (D-094) |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24)

- **Git state**, checked in Part 0 of the instruction, before the D-094 bookkeeping commit (the session began on 18 October 2026 at 20:57), and again when the Task branch was created at 21:19: `develop` at `6e18185` and then at the D-094 bookkeeping commit `a2cb971`; `plan/bantukasir-pos-v1` at `6e18185`; `main` and `origin/main` at `f7cecfe`; `origin/plan/bantukasir-pos-v1` at `86822d5`; no remote-tracking `develop`; remote `origin` only; nothing staged; no tracked file modified, apart from the line-ending-only differences of section 2; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`; `stash@{0}` at `f4c98df`; the ref under `refs/codex/turn-diffs/` at `0410607`; no `.git/*.lock`. Git ran with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true` (EXECUTION_CONTEXT section 18).
- **Prerequisites (EXC-21).** TASK-001 depends on no Task; GATE-P11 and GATE-BRANCH hold; its row was `READY` on `develop` (D-094).
- **Docker gate of the instruction.** PASS: Docker client and engine 29.8.0 (Docker Desktop 4.91.0), `docker info` OSType `linux` with kernel `6.18.33.2-microsoft-standard-WSL2`, Docker Compose v5.5.1. Docker Compose 5 is the continuation of the Compose v2 plugin line, invoked as `docker compose`; the executor reads the instruction's "`docker compose` v2" as that plugin, as opposed to the retired standalone `docker-compose` v1.
- **Tools on the host, before any installation.** Git 2.55.0.windows.3; Node.js 24.18.0 and npm 11.16.0 are on the host but are not used for the command contract, which runs in the project's containers; PHP and Composer are not on the host.
- **Start recorded (EXC-24).** The Task's first commit sets TASK-001 `IN_PROGRESS` in TASK_PLAN section 4 (totals `TODO` 48, `READY` 1, `IN_PROGRESS` 1) and creates this file.

## 2. Line endings before the line-ending commit

`git ls-files --eol` on the base commit `a2cb971`, before `.gitattributes` exists (OPEN_ITEMS T-01), run with `-c core.autocrlf=true` on 18 October 2026 at 21:19. Every tracked text file is stored as LF in the index; the working-tree differences are line endings only.

| Index and working tree | Files |
| --- | --- |
| `i/lf w/lf` | 195 |
| `i/-text w/-text` | 9 |
| `i/lf w/mixed` | 3 |
| `i/lf w/crlf` | 2 |
| `i/none w/none` | 1 |

The five files with CRLF or mixed line endings in the working tree — `.env.example`, `.gitignore`, `docs/07-ux-design/legacy/DESIGN.md`, `docs/07-ux-design/legacy/P7-UX-DESIGN.md` and `docs/07-ux-design/legacy/p7-correction/REVIEW.md` — were proved equal to their index blobs once CR is removed (`tr -d '\r' | git hash-object --stdin` equals `git rev-parse :<path>`); they were not rewritten. The one file without a line ending, `assets/brand/bantukasir-logo.svg`, is a single line with no final newline.

```text
i/lf    w/crlf  attr/                 	.env.example
i/lf    w/crlf  attr/                 	.gitignore
i/lf    w/lf    attr/                 	AGENTS.md
i/lf    w/lf    attr/                 	CLAUDE.md
i/lf    w/lf    attr/                 	README.md
i/lf    w/lf    attr/                 	assets/brand/README.md
i/none  w/none  attr/                 	assets/brand/bantukasir-logo.svg
i/lf    w/lf    attr/                 	docs/00-governance/COST_POLICY.md
i/lf    w/lf    attr/                 	docs/00-governance/DECISIONS.md
i/lf    w/lf    attr/                 	docs/00-governance/FRAMEWORK_DEVIATIONS.md
i/lf    w/lf    attr/                 	docs/00-governance/LEGACY_INDEX.md
i/lf    w/lf    attr/                 	docs/00-governance/OPEN_ITEMS.md
i/lf    w/lf    attr/                 	docs/00-governance/PRODUCTION_DATA_SAFETY.md
i/lf    w/lf    attr/                 	docs/00-governance/PROJECT_RULES.md
i/lf    w/lf    attr/                 	docs/00-governance/REFOUNDATION_PLAN.md
i/lf    w/lf    attr/                 	docs/00-governance/SOURCE_OF_TRUTH.md
i/lf    w/lf    attr/                 	docs/00-governance/TOOLCHAIN.md
i/lf    w/lf    attr/                 	docs/00-governance/legacy/00-CURRENT-STATE.md
i/lf    w/lf    attr/                 	docs/00-governance/legacy/10-DECISIONS.md
i/lf    w/lf    attr/                 	docs/00-governance/legacy/GAPS-CHANGES.md
i/lf    w/lf    attr/                 	docs/00-governance/legacy/P0-GOVERNANCE.md
i/lf    w/lf    attr/                 	docs/00-governance/records/REFOUNDATION_ANALYSIS_2026-10-03.md
i/lf    w/lf    attr/                 	docs/01-product/PRODUCT_BLUEPRINT.md
i/lf    w/lf    attr/                 	docs/01-product/REQUIREMENTS.md
i/lf    w/lf    attr/                 	docs/01-product/legacy/01-PRD.md
i/lf    w/lf    attr/                 	docs/01-product/legacy/P1-PRODUCT.md
i/lf    w/lf    attr/                 	docs/01-product/legacy/requirements/CORE-MAP.md
i/lf    w/lf    attr/                 	docs/01-product/legacy/requirements/core.json
i/lf    w/lf    attr/                 	docs/02-domain/CATALOG_BARCODE.md
i/lf    w/lf    attr/                 	docs/02-domain/COSTING.md
i/lf    w/lf    attr/                 	docs/02-domain/DOMAIN_MODEL.md
i/lf    w/lf    attr/                 	docs/02-domain/GLOSSARY.md
i/lf    w/lf    attr/                 	docs/02-domain/INVENTORY.md
i/lf    w/lf    attr/                 	docs/02-domain/SALES_CASH.md
i/lf    w/lf    attr/                 	docs/02-domain/legacy/06-INVENTORY-SPEC.md
i/lf    w/lf    attr/                 	docs/02-domain/legacy/12-BARCODE-SCANNER.md
i/lf    w/lf    attr/                 	docs/02-domain/legacy/15-FINANCE-PROFITABILITY.md
i/lf    w/lf    attr/                 	docs/02-domain/legacy/17-POS-SALES.md
i/lf    w/lf    attr/                 	docs/02-domain/legacy/P2-DOMAIN.md
i/lf    w/lf    attr/                 	docs/03-workflows/CRITICAL_FLOWS.md
i/lf    w/lf    attr/                 	docs/03-workflows/DOCUMENT_CONTRACTS.md
i/lf    w/lf    attr/                 	docs/03-workflows/INTERACTION_CONTRACTS.md
i/lf    w/lf    attr/                 	docs/03-workflows/ROUTE_CONTRACTS.md
i/lf    w/lf    attr/                 	docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md
i/lf    w/lf    attr/                 	docs/04-architecture/ARCHITECTURE.md
i/lf    w/lf    attr/                 	docs/04-architecture/SCHEMA.md
i/lf    w/lf    attr/                 	docs/04-architecture/STACK_AND_MODULES.md
i/lf    w/lf    attr/                 	docs/04-architecture/legacy/02-ARCHITECTURE.md
i/lf    w/lf    attr/                 	docs/04-architecture/legacy/P4-ARCHITECTURE.md
i/lf    w/lf    attr/                 	docs/05-security/SECURITY_MODEL.md
i/lf    w/lf    attr/                 	docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md
i/lf    w/lf    attr/                 	docs/05-security/legacy/P5-SECURITY.md
i/lf    w/lf    attr/                 	docs/06-api-performance/TECHNICAL_RULES.md
i/lf    w/lf    attr/                 	docs/06-api-performance/legacy/P6-CONCURRENCY-API.md
i/lf    w/lf    attr/                 	docs/07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md
i/lf    w/lf    attr/                 	docs/07-ux-design/COPY_DECK.md
i/lf    w/lf    attr/                 	docs/07-ux-design/DESIGN_SYSTEM.md
i/lf    w/lf    attr/                 	docs/07-ux-design/INFORMATION_ARCHITECTURE.md
i/lf    w/lf    attr/                 	docs/07-ux-design/INTERACTION_INVENTORY.md
i/lf    w/lf    attr/                 	docs/07-ux-design/KEY_SCREENS.md
i/lf    w/lf    attr/                 	docs/07-ux-design/NAVIGATION_CONTRACTS.md
i/lf    w/lf    attr/                 	docs/07-ux-design/UI_STATES.md
i/lf    w/lf    attr/                 	docs/07-ux-design/USER_TASKS.md
i/lf    w/mixed attr/                 	docs/07-ux-design/legacy/DESIGN.md
i/lf    w/lf    attr/                 	docs/07-ux-design/legacy/P7-COVERAGE.md
i/lf    w/mixed attr/                 	docs/07-ux-design/legacy/P7-UX-DESIGN.md
i/lf    w/lf    attr/                 	docs/07-ux-design/legacy/p7-correction/BROWSER.json
i/lf    w/mixed attr/                 	docs/07-ux-design/legacy/p7-correction/REVIEW.md
i/lf    w/lf    attr/                 	docs/07-ux-design/legacy/p7-correction/VALIDATION.json
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-correction/auth-reset-375.jpg
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-correction/desainpakeai-review.jpg
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-correction/mobile-cart-375.jpg
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-correction/owner-dashboard-1440.jpg
i/lf    w/lf    attr/                 	docs/07-ux-design/legacy/p7-owner-r1/REVIEW.md
i/lf    w/lf    attr/                 	docs/07-ux-design/legacy/p7-owner-r1/VALIDATION.json
i/lf    w/lf    attr/                 	docs/07-ux-design/legacy/p7-owner-r1/VERIFY.json
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-owner-r1/catalog-desktop.png
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-owner-r1/owner-chart-desktop.png
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-owner-r1/owner-issue-mobile.png
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-owner-r1/pos-mobile.png
i/-text w/-text attr/                 	docs/07-ux-design/legacy/p7-owner-r1/product-detail-desktop.png
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/assets/bk-app.js
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/assets/bk-base.css
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/assets/bk-components.css
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/assets/bk-copy.js
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/assets/bk-icons.js
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/assets/bk-tokens.css
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ds-specimen.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/frame.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/index.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-01-masuk.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-02-beranda.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-03-kasir.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-04-pembayaran.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-05-struk.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-06-buka-sesi.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-07-tutup-sesi.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-08-stok.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-09-catat-barang-masuk.html
i/lf    w/lf    attr/                 	docs/07-ux-design/previews/ks-10-laporan-keuangan.html
i/lf    w/lf    attr/                 	docs/08-testing/ACCEPTANCE_SUITES.md
i/lf    w/lf    attr/                 	docs/08-testing/DEVICE_VERIFICATION.md
i/lf    w/lf    attr/                 	docs/08-testing/TEST_STRATEGY.md
i/lf    w/lf    attr/                 	docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md
i/lf    w/lf    attr/                 	docs/08-testing/legacy/P8-QUALITY.md
i/lf    w/lf    attr/                 	docs/09-operations/BACKUP_RECOVERY.md
i/lf    w/lf    attr/                 	docs/09-operations/ENVIRONMENTS.md
i/lf    w/lf    attr/                 	docs/09-operations/OPERATIONS_RUNBOOK.md
i/lf    w/lf    attr/                 	docs/09-operations/legacy/09-DEPLOYMENT-OPS.md
i/lf    w/lf    attr/                 	docs/09-operations/legacy/P9-OPERATIONS.md
i/lf    w/lf    attr/                 	docs/10-release/RELEASE_PROCESS.md
i/lf    w/lf    attr/                 	docs/10-release/UAT_PILOT.md
i/lf    w/lf    attr/                 	docs/10-release/legacy/P10-RELEASE-UAT.md
i/lf    w/lf    attr/                 	docs/11-tasks/DEPENDENCY_GRAPH.md
i/lf    w/lf    attr/                 	docs/11-tasks/EXECUTION_CONTEXT.md
i/lf    w/lf    attr/                 	docs/11-tasks/TASK_PLAN.md
i/lf    w/lf    attr/                 	docs/11-tasks/TRACEABILITY.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/11-BUILD-PLAN.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/P11-BUILD-UNITS.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-CASH-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-CASH-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-CAT-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-CFG-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-CMD-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-DOC-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-FOUND-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-IAM-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-INV-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-INV-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-INV-003.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-OPS-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-OPS-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-READ-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-READ-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-RELEASE-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-RELEASE-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-SALE-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-SALE-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-UI-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-UI-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-UI-003.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-UI-004.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-VERIFY-001.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-VERIFY-002.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-VERIFY-003.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/BU-VERIFY-004.md
i/lf    w/lf    attr/                 	docs/11-tasks/legacy/build-units/registry.json
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-001-toolchain-ci.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-002-p7-change-control-1.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-003-kernel-localization.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-004-database-foundation.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-005-design-system.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-006-authentication.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-007-authorization-shell.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-008-command-platform.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-009-command-client.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-010-bootstrap-profile-media.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-011-user-management.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-012-application-image.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-013-independent-backup.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-014-release-pipeline.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-015-catalog-core.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-016-barcodes.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-017-catalog-pages.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-018-photos-labels.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-019-csv-import.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-020-barcode-correction.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-021-ledger-receiving.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-022-receiving-stock-pages.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-023-manual-issue.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-024-stock-adjustment.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-025-cost-correction.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-026-reversal.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-027-registers-session-open.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-028-checkout.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-029-till-pages.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-030-session-close.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-031-documents-sales-lookup.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-032-assisted-close-notes.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-033-refunds-core.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-034-drawer-refunds.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-035-onboarding.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-036-reconciliation-repair.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-037-restore-tooling.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-038-housekeeping.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-039-system-status.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-040-finance-report.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-041-owner-home-daily.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-042-audit-views.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-043-performance-harness.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-044-integrated-verification.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-045-operations-verification.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-046-device-verification.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-047-p7-change-control-2.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-048-performance-verification.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-049-release-readiness.md
i/lf    w/lf    attr/                 	docs/11-tasks/tasks/TASK-050-production-pilot.md
i/lf    w/lf    attr/                 	docs/adr/ADR-001-stack-and-modular-monolith.md
i/lf    w/lf    attr/                 	docs/adr/ADR-002-minimum-runtime-infrastructure.md
i/lf    w/lf    attr/                 	docs/adr/ADR-003-hosting-topology.md
i/lf    w/lf    attr/                 	docs/adr/ADR-004-authentication.md
i/lf    w/lf    attr/                 	docs/adr/ADR-005-localization-architecture.md
i/lf    w/lf    attr/                 	docs/adr/ADR-006-exact-numbers.md
i/lf    w/lf    attr/                 	docs/adr/ADR-007-command-identity-and-idempotency.md
i/lf    w/lf    attr/                 	docs/adr/ADR-008-cost-allocation-representation.md
i/lf    w/lf    attr/                 	docs/adr/ADR-009-command-receipts-and-reconciled-outcomes.md
i/lf    w/lf    attr/                 	docs/adr/ADR-010-independent-backup-and-recovery-key-custody.md
i/lf    w/lf    attr/                 	docs/adr/ADR-011-release-pipeline-environments-and-production-operation.md
i/lf    w/lf    attr/                 	docs/adr/README.md
i/lf    w/lf    attr/                 	docs/handoff/CURRENT_HANDOFF.md
```
