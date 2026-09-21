# 11 — BantuKasir Plan 1.1

**21 September 2026. Documentation/planning only. 36 total tasks, all 36 Core, 0/36 complete.** No active executor; all implementation/prototype tasks NOT STARTED. Hard ceiling 50 includes prototypes, integration, testing and deployment. Later topics in [01](01-PRD.md) have no task IDs/estimates and are excluded from this baseline.

Next: **BK01 — UI01 Foundation + Shell**, only in a subsequent authorized session. Do not start it during replanning.

## Capacity and phases

Target maximum approximately one month. Bottom-up focused effort below totals **166 hours (about 20.75 eight-hour working days)** for one experienced full-time executor; this is a constrained target, not guaranteed feasibility. The six-hour increase from Plan 1.0 covers cash-session visual states, opening/attribution, close races and daily aggregation. Most tasks take 3–7 focused hours, final regression/pilot up to one day. Estimates include task tests/docs and specified review preparation, not waiting for external answers or unexpected rework. No contingency is included. A major blocker or scope promotion invalidates the date; report it and replan, never skip safety.

| Phase | Tasks | Hours | Milestone |
| --- | --- | ---: | --- |
| P0 Visual contract | BK01–BK05 | 20 | Four compact bundles including cash sessions, complete owner Gate A |
| P1 Foundation | BK06–BK10 | 18 | Auth/config/integrated shell Gate B |
| P2 Product and stock | BK11–BK20 | 40 | Search/barcode/receive/opening/issue/restock Gate B |
| P3 Usable POS | BK21–BK25 | 25 | Session opening/attribution, atomic payment/issue/receipt/correction Gate B |
| P4 Reports and control | BK26–BK31 | 29 | Truthful HPP, session close/report/daily aggregation and audit Gate B |
| P5 Production readiness | BK32–BK36 | 34 | Protected migrations, restore, device regression, authorized pilot |

Approximate four-week map plus six focused spillover hours (one executor, no automatic parallel agents):
- Week 1, days 1–5: BK01–BK10 (38h), begin BK11 (2h). Owner available for complete Gate A by early day 3 and shell Gate B; integrations already available.
- Week 2, days 6–10: finish BK11 (2h), BK12–BK20 (36h), begin BK21 (2h). Obtain actual scanner/printer and G2 data before their gates.
- Week 3, days 11–15: finish BK21 (5h), BK22–BK28 (35h). G3 resolves before BK28 completion.
- Week 4, days 16–20: BK29–BK35 (38h), begin BK36 (2h). G1/G4 and deployment access/authorization ready before pilot.
- Spillover: finish BK36 (6h). Gate/rework wait is additional elapsed time; the target remains approximately one month, with no schedule padding.

Waiting for Gate A/B is real schedule risk. Hardware/backup access and owner answers can be collected while executor works, but gate failures do not disappear because of elapsed time. Cut already made: serials, CSV/XLSX import, Web Push, camera, advanced stock/corrections, deferred commerce/services, attendance/payroll shifts, simultaneous shared drawer and paid-in/out. Lightweight cash sessions are Core. Cosmetic charts/extra variants remain optional within existing tasks, not new features. If more cuts cannot preserve a usable safe POS, explicitly report that the one-month target is infeasible under actual constraints.

## Dependencies and execution

Dependencies refer to completed tasks, including their listed owner/technical gates. Default is one active executor. Critical chain: BK01 → BK02/BK03 → BK04 → BK05 → BK06–BK25 → BK26 → BK27 → BK30 → BK31–BK36; BK28 → BK29 also joins BK30. Runtime safety is introduced before any live use.

With explicit coordinated staffing, BK02/BK03 can run after common UI01; BK28 can run alongside BK26/BK27 after BK25 on agreed Sale/session contracts. Independent prerequisite collection (G1–G4, logo/tool setup, device availability) can overlap. Shared schema/token mutations require coordination; parallelism is not assumed in the 166h estimate.

Common READ: AGENTS, 00, this task, 01, 02, 10, plus listed docs. Common DoD: applicable schema/migration + validation + backend RBAC + audit + targeted tests/races/recovery + lint/typecheck/build + browser/mobile/device evidence + docs under [08](08-TESTING-ACCEPTANCE.md). Every meaningful UI task follows actual tool workflow in 05. Task paths are proposed domains until scaffold exists.

## Tracker

| ID | Status | Goal / deliverable | Depends on | Hours |
| --- | --- | --- | --- | ---: |
| BK01 | NOT STARTED | UI01 Foundation + Shell | None; future execution instruction required | 4 |
| BK02 | NOT STARTED | UI02 Product Operations | BK01 | 4 |
| BK03 | NOT STARTED | UI03 POS | BK01 | 5 |
| BK04 | NOT STARTED | UI04 Reports | BK02, BK03 | 5 |
| BK05 | NOT STARTED | Complete visual Gate A | BK01, BK02, BK03, BK04 | 2 |
| BK06 | NOT STARTED | Foundation and isolated environments | BK05 | 4 |
| BK07 | NOT STARTED | Transactional command and audit foundation | BK06 | 4 |
| BK08 | NOT STARTED | Authentication and fixed-role enforcement | BK07 | 4 |
| BK09 | NOT STARTED | Runtime business identity slice | BK08 | 3 |
| BK10 | NOT STARTED | Integrated role shell Gate B | BK09 | 3 |
| BK11 | NOT STARTED | Goods catalog vertical slice | BK10 | 4 |
| BK12 | NOT STARTED | Canonical Product Search and creation checks | BK11 | 4 |
| BK13 | NOT STARTED | Barcode registry and labels slice | BK12 | 3 |
| BK14 | NOT STARTED | Ledger, balance and inventory command core | BK07, BK13 | 5 |
| BK15 | NOT STARTED | Acquisition evidence and MWA posting | BK14 | 5 |
| BK16 | NOT STARTED | Search-first receiving slice | BK15 | 5 |
| BK17 | NOT STARTED | Manual goods issue slice | BK16 | 4 |
| BK18 | NOT STARTED | Owner manual opening and cutover controls | BK17 | 3 |
| BK19 | NOT STARTED | Current stock, history and restock slice | BK18 | 3 |
| BK20 | NOT STARTED | Product operations/device Gate B | BK19 | 4 |
| BK21 | NOT STARTED | Cash-session opening and atomic Sale backend | BK20 | 7 |
| BK22 | NOT STARTED | Integrated cashier checkout slice | BK21 | 5 |
| BK23 | NOT STARTED | Thermal and A4 document slice | BK22 | 4 |
| BK24 | NOT STARTED | Minimal owner correction/refund/return slice | BK23 | 5 |
| BK25 | NOT STARTED | POS and documents/device Gate B | BK24 | 4 |
| BK26 | NOT STARTED | Cost revision and replay completeness | BK25 | 6 |
| BK27 | NOT STARTED | Owner period reporting slice | BK26 | 4 |
| BK28 | NOT STARTED | Lightweight cash-session close backend | BK21, BK25 | 7 |
| BK29 | NOT STARTED | Session reports and daily aggregation slice | BK28 | 5 |
| BK30 | NOT STARTED | Owner log and operational history review | BK27, BK29 | 3 |
| BK31 | NOT STARTED | Reports/audit Gate B | BK30 | 4 |
| BK32 | NOT STARTED | Production packaging and safe maintenance | BK31 | 6 |
| BK33 | NOT STARTED | Independent backup and isolated restore drill | BK32 | 6 |
| BK34 | NOT STARTED | Release regression and device/performance verification | BK33 | 8 |
| BK35 | NOT STARTED | Client policy and pilot readiness | BK34 | 6 |
| BK36 | NOT STARTED | Authorized deployment, cutover and pilot acceptance | BK35 | 8 |

## Execution cards

Each card supplements the tracker and common DoD; no giant executor prompts needed. Use [16](16-EXECUTOR-HANDOFF.md). Evidence is empty until execution.

### BK01 — UI01 Foundation + Shell

- Goal: Use verified tools to establish proposed BantuKasir brand/semantic foundation, login, role shells, profile pattern and active cash-session entry/identity.
- Depends on: None; subsequent task authorization.
- Read: [05](05-DESIGN-SYSTEM.md), [04](04-AUTH-RBAC-SECURITY.md).
- Main files/domain: prototypes/ui01; PRODUCT.md; DESIGN.md.
- Acceptance/verification: UI01 desktop/mobile/keyboard states, real-logo availability/status and tool critique recorded; Inter/palette/session-shell patterns consolidated as proposed.
- Stop if: Missing 21st/Impeccable or paid-only access; real BantuKasir logo remains absent when logo-specific brand approval is required; stop at review, no production UI.

### BK02 — UI02 Product Operations

- Goal: Prototype search-first catalog, receiving/opening, issue/history/restock and labels.
- Depends on: BK01.
- Read: [05](05-DESIGN-SYSTEM.md), [06](06-INVENTORY-SPEC.md), [12](12-BARCODE-SCANNER.md), [14](14-BULK-IMPORT.md).
- Main files/domain: prototypes/ui02; shared prototype patterns.
- Acceptance/verification: All UI02 representative flows, duplicate warning and errors reuse UI01; browser evidence.
- Stop if: Missing required UI tools or inconsistent tokens; no production UI.

### BK03 — UI03 POS

- Goal: Prototype session open/opening cash, persistent cashier/drawer identity, cart/full cash/transfer, handover via close/new session, uncertain result, receipt/A4/refund.
- Depends on: BK01.
- Read: [05](05-DESIGN-SYSTEM.md), [12](12-BARCODE-SCANNER.md), [17](17-POS-SALES.md).
- Main files/domain: prototypes/ui03; print mock documents.
- Acceptance/verification: UI03 covers same-day new session, A→B handover, tender/change, recovery/reprint and touch/keyboard flows with tool review; no shared active drawer implied.
- Stop if: Missing required UI tools; no real payment or production UI.

### BK04 — UI04 Reports

- Goal: Prototype versioned session count/close/report/export, owner daily aggregation across sessions, periods/incomplete profit, audit/system states.
- Depends on: BK02, BK03.
- Read: [05](05-DESIGN-SYSTEM.md), [15](15-FINANCE-PROFITABILITY.md), [17](17-POS-SALES.md), [09](09-DEPLOYMENT-OPS.md).
- Main files/domain: prototypes/ui04; shared report patterns.
- Acceptance/verification: UI04 includes opening/cash/transfer/expected/physical/variance, stale-count recount, immutable session history, daily session list and device layouts.
- Stop if: Missing required UI tools; unresolved G3 assumptions must remain visibly proposed.

### BK05 — Complete visual Gate A

- Goal: Record owner review of all four bundles and freeze exact shared baseline.
- Depends on: BK01, BK02, BK03, BK04.
- Read: [05](05-DESIGN-SYSTEM.md).
- Main files/domain: DESIGN.md; prototype review register.
- Acceptance/verification: Explicit owner approval of complete revision; source/screenshots/pattern inventory linked.
- Stop if: Wait for owner approval; do not self-approve or start production frontend.

### BK06 — Foundation and isolated environments

- Goal: Scaffold pinned compatible stack, isolated dev/test DB and repeatable checks.
- Depends on: BK05.
- Read: [02](02-ARCHITECTURE.md), [09](09-DEPLOYMENT-OPS.md).
- Main files/domain: package/lockfile; db; tests; config; CI.
- Acceptance/verification: Environment validation, populated migration test harness and lint/typecheck/test/build scripts run; versions/advisories verified.
- Stop if: No authorized implementation session, missing DB isolation or unsafe inherited config.

### BK07 — Transactional command and audit foundation

- Goal: Provide durable idempotency/fingerprint/result and ordered transaction helpers.
- Depends on: BK06.
- Read: [02](02-ARCHITECTURE.md), [03](03-DOMAIN-MODEL.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: src/modules/commands; audit; db; tests.
- Acceptance/verification: Duplicate/conflicting key, source intent, rollback and audit failure integration tests pass.
- Stop if: Cannot enforce transaction/immutable audit/result boundary.

### BK08 — Authentication and fixed-role enforcement

- Goal: Implement private bootstrap/invite/reset, sessions, rate limit and three roles.
- Depends on: BK07.
- Read: [04](04-AUTH-RBAC-SECURITY.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: src/modules/auth; db; route guards; tests.
- Acceptance/verification: AUTH01 plus backend permission/object tests; no signup/TOTP; revoked users cannot write.
- Stop if: Unverified auth version/security, secret exposure or role bypass.

### BK09 — Runtime business identity slice

- Goal: Implement owner-only BusinessProfile revisions and approved configuration form.
- Depends on: BK08.
- Read: [01](01-PRD.md), [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md), [09](09-DEPLOYMENT-OPS.md).
- Main files/domain: src/modules/business; profile UI; private assets.
- Acceptance/verification: Runtime name/logo/contact/header/footer/accent changes without source edit; upload/RBAC/audit tests.
- Stop if: Missing UI tools/Gate A or unsafe asset storage/contrast.

### BK10 — Integrated role shell Gate B

- Goal: Integrate approved login/navigation/workspaces with real session permissions.
- Depends on: BK09.
- Read: [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: src/app; shared tokens/components; login/workspaces.
- Acceptance/verification: Role routes and responsive/keyboard tests pass; owner Gate B revision recorded.
- Stop if: Missing UI tools; wait for technical/owner evidence, no invented role metrics.

### BK11 — Goods catalog vertical slice

- Goal: Implement goods/category/unit/brand/price/minimum/active master and revisions.
- Depends on: BK10.
- Read: [03](03-DOMAIN-MODEL.md), [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: src/modules/catalog; db; product forms; tests.
- Acceptance/verification: Role CRUD/price/precision/audit tests; approved list/detail/form; creation uses BK12 search before enabled onboarding.
- Stop if: Missing UI tools or unsupported G2 units; no real opening yet.

### BK12 — Canonical Product Search and creation checks

- Goal: Implement one paged search reused by all contexts and duplicate-first creation.
- Depends on: BK11.
- Read: [12](12-BARCODE-SCANNER.md), [04](04-AUTH-RBAC-SECURITY.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: catalog/search; shared SearchPicker; query indexes.
- Acceptance/verification: SEARCH02 plus name/SKU/category/brand and similar warning/pagination tests; exact-barcode contract defined here and tested with registry in BK13.
- Stop if: Full-master browser fetch, duplicate bypass or cost leakage.

### BK13 — Barcode registry and labels slice

- Goal: Add manufacturer aliases/internal Code 128 and shared exact lookup.
- Depends on: BK12.
- Read: [12](12-BARCODE-SCANNER.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: catalog/barcodes; label UI/print; db; tests.
- Acceptance/verification: SEARCH01 exact-barcode reuse/uniqueness/leading-zero tests complete; labels/reprint same identity, no stock writes.
- Stop if: Missing UI tools/license or identity collision handling.

### BK14 — Ledger, balance and inventory command core

- Goal: Implement movement/balance/source constraints and ordered product locks.
- Depends on: BK07, BK13.
- Read: [06](06-INVENTORY-SPEC.md), [02](02-ARCHITECTURE.md), [03](03-DOMAIN-MODEL.md).
- Main files/domain: inventory; stock schema; integration tests.
- Acceptance/verification: INV01/02 movement/balance/audit/result rollback, last-unit/retry and reconciliation pass; cost legs added/tested in BK15.
- Stop if: Any direct balance endpoint, negative stock or unsafe command result.

### BK15 — Acquisition evidence and MWA posting

- Goal: Attach known/unknown evidence and synchronous MWA allocations to movement core.
- Depends on: BK14.
- Read: [15](15-FINANCE-PROFITABILITY.md), [04](04-AUTH-RBAC-SECURITY.md).
- Main files/domain: inventory/costing; db; finance tests.
- Acceptance/verification: FIN01/02 baseline math, final-stock rounding, UNKNOWN propagation and staff write-only boundary.
- Stop if: Guessed cost, unsafe arithmetic or historical cost disclosure.

### BK16 — Search-first receiving slice

- Goal: Integrate scan/search/create-resume, qty/cost/reference/review/confirm.
- Depends on: BK15.
- Read: [06](06-INVENTORY-SPEC.md), [12](12-BARCODE-SCANNER.md), [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: inventory/receiving; receiving UI; tests.
- Acceptance/verification: SEARCH02 and INV01/02; one receipt/balance/cost/audit/result, repeat submit safe; browser flow.
- Stop if: Missing UI tools or cost payload leak; real data requires G2.

### BK17 — Manual goods issue slice

- Goal: Implement scan/search/qty/mandatory non-sale reason and history.
- Depends on: BK16.
- Read: [06](06-INVENTORY-SPEC.md), [12](12-BARCODE-SCANNER.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: inventory/manual-issue; issue UI; tests.
- Acceptance/verification: INV02; reason enforced, insufficient stock rejected, no customer-sale bypass labels.
- Stop if: Missing UI tools, missing reason/authorization or negative stock.

### BK18 — Owner manual opening and cutover controls

- Goal: Implement bounded initial counts with guarded once-per-product opening.
- Depends on: BK17.
- Read: [14](14-BULK-IMPORT.md), [06](06-INVENTORY-SPEC.md), [10](10-DECISIONS.md).
- Main files/domain: inventory/opening; onboarding flag/UI; tests.
- Acceptance/verification: Atomic invalid-row rollback/retry, prior-ledger rejection, cost completeness review and global posting freeze.
- Stop if: G2 unresolved for acceptance/real data; onboarding volume unsuitable; no implicit CSV scope.

### BK19 — Current stock, history and restock slice

- Goal: Deliver paged stock/history/LOW/OUT list and badge from same current query.
- Depends on: BK18.
- Read: [13](13-NOTIFICATIONS.md), [06](06-INVENTORY-SPEC.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: inventory/queries; stock/restock/history UI.
- Acceptance/verification: STOCK01, stale/error refresh, RBAC and price/threshold audit; no private cost in operations.
- Stop if: Missing UI tools or balance mismatch.

### BK20 — Product operations/device Gate B

- Goal: Validate complete search/receive/issue/opening/restock/label journey.
- Depends on: BK19.
- Read: [08](08-TESTING-ACCEPTANCE.md), [12](12-BARCODE-SCANNER.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: product/inventory browser tests; device evidence.
- Acceptance/verification: UI02 integrated owner Gate B, actual USB/Bluetooth HID and label printer evidence.
- Stop if: Required hardware or owner approval absent; keep pending, never simulated pass.

### BK21 — Cash-session opening and atomic Sale backend

- Goal: Implement minimal logical registers, idempotent own-session opening and completed Sale with full payment, issue, costing, immutable receipt and exact session/cashier attribution.
- Depends on: BK20.
- Read: [17](17-POS-SALES.md), [02](02-ARCHITECTURE.md), [15](15-FINANCE-PROFITABILITY.md).
- Main files/domain: sales; payments; cash-register/session open; session guard; receipt schema; tests.
- Acceptance/verification: POS01/02 and SESSION01–04 prerequisites: unique OPEN register/cashier, explicit opening cash, both methods attributed once, transfer excluded from expected cash; stock races/rollback every boundary; one source intent.
- Stop if: Payment/issue split, unresolved source result or cost/stock mismatch.

### BK22 — Integrated cashier checkout slice

- Goal: Connect approved session opening/identity and cart/payment/error/unknown-result interaction to atomic Sale.
- Depends on: BK21.
- Read: [17](17-POS-SALES.md), [12](12-BARCODE-SCANNER.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: POS UI; shared search/scan/cart; browser tests.
- Acceptance/verification: POS02/03 and session-required checkout; keyboard/mobile, price review, recovery after reload, no cashier override or Sale outside an own OPEN session.
- Stop if: Missing UI tools/Gate A or unresolved command recovery.

### BK23 — Thermal and A4 document slice

- Goal: Render same frozen Sale as thermal/A4 commercial nota and browser PDF.
- Depends on: BK22.
- Read: [17](17-POS-SALES.md), [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: document renderers; receipt/history/reprint UI.
- Acceptance/verification: POS03, immutable identity/number after profile edits, scoped reprint, printer-failure recovery.
- Stop if: Missing UI tools or formal tax claim without G1.

### BK24 — Minimal owner correction/refund/return slice

- Goal: Implement bounded owner refund/return and eligible stock reversal/adjustment.
- Depends on: BK23.
- Read: [06](06-INVENTORY-SPEC.md), [15](15-FINANCE-PROFITABILITY.md), [17](17-POS-SALES.md), [04](04-AUTH-RBAC-SECURITY.md).
- Main files/domain: corrections; owner UI; eligibility/tests.
- Acceptance/verification: RET01/INV03, append-only history, saleable historical-cost recovery and payout evidence.
- Stop if: Missing UI tools; unsafe dependent cost replay stays rejected until BK26; drawer payouts require G3 replan.

### BK25 — POS and documents/device Gate B

- Goal: Verify real counter flow from session open through checkout, receipt/A4/reprint/corrections and recovery.
- Depends on: BK24.
- Read: [08](08-TESTING-ACCEPTANCE.md), [05](05-DESIGN-SYSTEM.md), [12](12-BARCODE-SCANNER.md).
- Main files/domain: POS E2E; print/scanner evidence.
- Acceptance/verification: UI03 owner Gate B; actual scanner/receipt printer, POS01–03, RET01 and session opening/attribution integration pass. Detailed close/report is BK28–BK31.
- Stop if: Missing hardware, owner approval or unresolved atomicity/privacy failure.

### BK26 — Cost revision and replay completeness

- Goal: Implement bounded owner evidence correction with atomic valuation revision publication.
- Depends on: BK25.
- Read: [15](15-FINANCE-PROFITABILITY.md), [06](06-INVENTORY-SPEC.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: finance/cost replay; owner evidence UI; tests.
- Acceptance/verification: FIN01–03, missing→known replay/return/reversal and concurrent posting; measure 10k product movements.
- Stop if: Missing UI tools, capacity timeout or partial revision publication; incomplete remains visible.

### BK27 — Owner period reporting slice

- Goal: Deliver five period presets/custom range and truthful sales/HPP/gross metrics.
- Depends on: BK26.
- Read: [15](15-FINANCE-PROFITABILITY.md), [13](13-NOTIFICATIONS.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: reports/query; owner dashboard; tests.
- Acceptance/verification: FIN03, timezone bounds/consistent asOf, owner-only endpoints, current LOW/OUT separate.
- Stop if: Missing UI tools or incomplete cost represented as zero profit.

### BK28 — Lightweight cash-session close backend

- Goal: Implement OPEN→CLOSED versioned physical count, race-safe cutoff and atomic immutable CashSession report without persistent CLOSING.
- Depends on: BK21, BK25.
- Read: [17](17-POS-SALES.md), [04](04-AUTH-RBAC-SECURITY.md), [10](10-DECISIONS.md).
- Main files/domain: cash-session close; snapshot; register/session constraints; integration tests.
- Acceptance/verification: SESSION01–07 including same-day return, A→B handover, two-key duplicate, racing Sale, assisted close and atomic snapshot rollback.
- Stop if: G3 changes opening cash/register/refund assumptions or requires shared active drawer/paid-in/out; never silently add a complex shift engine.

### BK29 — Session reports and daily aggregation slice

- Goal: Integrate count/recount/close flow, own session history and safe snapshot exports plus owner daily aggregation over source facts and session rows.
- Depends on: BK28.
- Read: [17](17-POS-SALES.md), [05](05-DESIGN-SYSTEM.md), [04](04-AUTH-RBAC-SECURITY.md).
- Main files/domain: session report UI/history; owner daily recap; print/PDF/CSV; tests.
- Acceptance/verification: SESSION08–10, no private fields, formula-safe CSV, no aggregation double count and same snapshot after export failure/corrections.
- Stop if: Missing UI tools or authorization/export leakage.

### BK30 — Owner log and operational history review

- Goal: Add owner date/user/type/product/reference filters across critical sources.
- Depends on: BK27, BK29.
- Read: [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: audit queries; log UI; history tests.
- Acceptance/verification: AUDIT01 and pagination including register/session, linked original/correction facts, denied staff audit access.
- Stop if: Missing UI tools, missing critical event or sensitive log payload.

### BK31 — Reports/audit Gate B

- Goal: Verify owner/operations/cashier reporting against approved UI04.
- Depends on: BK30.
- Read: [05](05-DESIGN-SYSTEM.md), [08](08-TESTING-ACCEPTANCE.md), [15](15-FINANCE-PROFITABILITY.md), [17](17-POS-SALES.md).
- Main files/domain: reports E2E; role no-leak matrix; review evidence.
- Acceptance/verification: UI04 owner Gate B plus FIN/SESSION/AUDIT and all export no-leak tests; incomplete/stale states verified.
- Stop if: Missing owner review or unresolved reconciliation/privacy failure.

### BK32 — Production packaging and safe maintenance

- Goal: Prepare private-DB HTTPS runtime and forward-migration/reconciliation/health runbook.
- Depends on: BK31.
- Read: [09](09-DEPLOYMENT-OPS.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: ops/compose/caddy; release/migration scripts; system UI.
- Acceptance/verification: DB01 populated rehearsal; image/config/role smoke in isolated staging; approved system pattern.
- Stop if: Missing UI tools for system UI; no live deployment authorization; unsafe destructive script.

### BK33 — Independent backup and isolated restore drill

- Goal: Configure authorized destination, encrypted backup and measured restoration.
- Depends on: BK32.
- Read: [09](09-DEPLOYMENT-OPS.md), [10](10-DECISIONS.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: ops/backup/restore; private runbook; system status.
- Acceptance/verification: REC01/02 + G4 accepted measured RPO/RTO; snapshots/assets and real recovery access reconcile.
- Stop if: G4 destination/access/cost approval absent or restore fails; no production pilot.

### BK34 — Release regression and device/performance verification

- Goal: Run complete risk suites on representative populated environment and real devices.
- Depends on: BK33.
- Read: [08](08-TESTING-ACCEPTANCE.md), [09](09-DEPLOYMENT-OPS.md).
- Main files/domain: integration/E2E/load tests; device/release evidence.
- Acceptance/verification: All acceptance suites, lint/typecheck/build, migration/restore and measured performance/device checks pass.
- Stop if: Any integrity/privacy/recovery failure or missing hardware; do not waive to meet date.

### BK35 — Client policy and pilot readiness

- Goal: Confirm G1–G4 dispositions, real opening plan, permissions/training and release authorization.
- Depends on: BK34.
- Read: [01](01-PRD.md), [09](09-DEPLOYMENT-OPS.md), [10](10-DECISIONS.md), [14](14-BULK-IMPORT.md).
- Main files/domain: operator/cashier SOP; cutover checklist; gate evidence.
- Acceptance/verification: Owner reviews nota policy/data/cash model/recovery, signs readiness; restore/SOP accessible.
- Stop if: Any gate open or unsafe client assumption; no real cutover yet.

### BK36 — Authorized deployment, cutover and pilot acceptance

- Goal: Deploy only after explicit authorization, open real stock safely and validate real-role pilot.
- Depends on: BK35.
- Read: [09](09-DEPLOYMENT-OPS.md), [08](08-TESTING-ACCEPTANCE.md), [14](14-BULK-IMPORT.md).
- Main files/domain: authorized runtime; cutover/pilot evidence; handoff docs.
- Acceptance/verification: RELEASE01; reconciled opening/sales/cash/reports/backup, system Gate B, owner production acceptance and handoff.
- Stop if: No explicit deploy authorization or failing gate; stop writes and follow recovery, never destructive reset.

## Tracker maintenance

Statuses: NOT STARTED, IN PROGRESS, WAITING TECHNICAL CHECK, WAITING OWNER REVIEW, BLOCKED, COMPLETE. One active task maximum by default. Never award completion for rendering alone, documentation, historical prototype or missing device evidence. Record actual commands/results, file/revision links, owner approval and remaining blockers beside the card; preserve IDs. Scope changes update plan version/count/dependency validation. Update 00 last. No push/merge/deploy permission is implied by task completion.
