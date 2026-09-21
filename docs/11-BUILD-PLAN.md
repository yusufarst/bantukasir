# 11 — BantuKasir Plan 1.1

**21 September 2026. Optimized Plan 1.1 delivery baseline; documentation/planning only. 36 total tasks, all 36 Core, 0/36 complete.** No active executor; all implementation/prototype tasks NOT STARTED. Hard ceiling 50 includes prototypes, integration, testing and deployment. Later topics in [01](01-PRD.md) have no task IDs/estimates and are excluded from this baseline.

Next: **BK01 — UI01 Foundation + Shell**, only in a subsequent authorized session. Do not start it during replanning.

## Capacity and phases

Target maximum approximately one month. Reassessed bottom-up focused effort totals **154 hours (about 19.25 eight-hour working days)** for one experienced full-time executor. This is the minimum credible estimate under current scope: 12 hours below the 166-hour Plan 1.1 checkpoint by reusing approved patterns/command infrastructure, removing repeated gate preparation and moving full release reruns to BK34. No acceptance suite, RBAC, transaction, stock/cost, CashSession, audit, migration, device or recovery control is removed. The six focused hours below a 160-hour month are schedule capacity, not contingency for rework or external waiting.

Estimates include implementation, targeted task tests/docs and preparation/recording of specified evidence. They exclude elapsed time waiting for owner review, policy answers, hardware, credentials or deployment authorization. A waiting gate can still make the calendar target infeasible. A blocker, failed gate or scope promotion triggers visible replan; never compress safety work or manufacture completion.

| Phase | Tasks | Hours | Milestone |
| --- | --- | ---: | --- |
| P0 Visual contract | BK01–BK05 | 18 | Four compact bundles including cash sessions, complete owner Gate A |
| P1 Foundation | BK06–BK10 | 17 | Auth/config/integrated shell Gate B |
| P2 Product and stock | BK11–BK20 | 37 | Search/barcode/receive/opening/issue/restock Gate B |
| P3 Usable POS | BK21–BK25 | 23 | Session opening/attribution, atomic payment/issue/receipt/correction Gate B |
| P4 Reports and control | BK26–BK31 | 27 | Truthful HPP, session close/report/daily aggregation and audit Gate B |
| P5 Production readiness | BK32–BK36 | 32 | Protected migrations, restore, device regression, authorized pilot |

Conceptual four-week allocation (one executor, no automatic parallel coding agents; not authorization to start):
- Week 1, up to 40 focused hours: BK01–BK05 (18h), BK06–BK10 (17h), BK11 (4h), begin BK12 (1h). Complete Gate A before BK09 or any other production frontend. If owner review is waiting, BK06–BK08 may proceed as backend/test foundation only.
- Week 2, up to 40 focused hours: finish BK12 (3h), BK13–BK20 (29h), BK21 (7h), begin BK22 (1h). Inventory core/costing can progress beside barcode UI work; Sale backend begins when catalog, ledger and costing contracts are technically ready, without waiting for BK20 owner/device acceptance.
- Week 3, up to 40 focused hours: finish BK22 (4h after a 1h start if available), BK23–BK30 (35h), begin BK31 (1h). BK26 and BK28 use completed Sale/correction contracts and do not wait for BK25 owner/device review; G3 must resolve before BK28 completes.
- Week 4, 34 focused hours: finish BK31 (2h), BK32–BK36 (32h). Packaging/restore can proceed while report Gate B waits, but BK34 cannot start until all listed Gate B and device prerequisites are complete.

This leaves six focused hours inside a 20-day month. Owner, hardware, access and policy waiting is separate elapsed time and can consume more than that capacity.

### Parallel prerequisite tracks

The owner/external tracks below can run while Antigravity executes one selected engineering task. Waiting carries no engineering-hour estimate and never receives completion credit.

| Track | Prepare in parallel | Must be ready by |
| --- | --- | --- |
| Visual/tool | Original BantuKasir logo; verified free 21st.dev and supported Impeccable setup; timely Gate A/B review slots | UI tasks; logo-specific BK05 approval; relevant Gate B |
| Policy/data | G1 nota/tax/buyer answer; G2 units, product, barcode, cost and opening-stock samples | Affected document behavior/BK35; BK18 and capacity decisions |
| Cash operations | G3 physical drawer labels, opening float, responsible-cashier/handover and owner-refund funding confirmation | BK28 completion and pilot |
| Devices | Actual USB/Bluetooth scanner, label printer and receipt printer, including drivers/media | BK20, BK25 and final BK34 proof as specified |
| Recovery/release | G4 independent destination/access/RPO/RTO; VPS, domain, secrets and deployment access | BK33, BK35 and authorized BK36 |

Waiting for Gate A/B is real schedule risk. Gate A stays strict before production frontend; all relevant Gate B/device evidence must converge before BK34 and release. Cut already made: serials, CSV/XLSX import, Web Push, camera, advanced stock/corrections, deferred commerce/services, attendance/payroll shifts, simultaneous shared drawer and paid-in/out. Lightweight cash sessions are Core. Cosmetic charts/extra variants remain optional within existing tasks, not new features. Below 154 hours is not credible without new evidence or scope change; if the calendar target fails, report it rather than weaken safety.

## Dependencies and execution

Tracker dependencies are **engineering prerequisites**. A predecessor in WAITING OWNER REVIEW or WAITING TECHNICAL CHECK may satisfy an edge only when its required technical artifact/tests are complete and recorded; its separate completion hold remains open. At most one task may be IN PROGRESS. Waiting tasks do not occupy that executor slot, but their frozen evidence must not be changed silently. A task is COMPLETE only after both technical acceptance and its own completion hold pass.

Gate A is the exception that remains absolute for production frontend: BK06–BK08 may build backend/test foundations while BK05 waits, but BK09 and every production UI need BK05 COMPLETE. Gate B tasks BK10/BK20/BK25/BK31 are not universal backend blockers. Their tested technical output may feed listed downstream work while owner/device acceptance waits; BK34 is the release fan-in and requires all four Gate B tasks plus BK33 complete.

Technical paths: BK01 → BK02/BK03 → BK04 → BK05 for visual approval; BK06 → BK07 → BK08 for backend foundation; BK11 → BK12 → BK13 and BK11 + BK07 → BK14 → BK15 for product/inventory; BK11 + BK15 → BK21 for Sale; BK21 → BK28 for session close; BK24 → BK26 → BK27 and BK27 + BK28 → BK29 → BK30 for reports; BK30 → BK32 → BK33 for operations; BK10/BK20/BK25/BK31/BK33 → BK34 → BK35 → BK36 for release. The graph is acyclic and assumes no parallel coding hours.

Common READ: AGENTS, 00, this task, 01, 02, 10, plus listed docs. Common DoD: applicable schema/migration + validation + backend RBAC + audit + targeted tests/races/recovery + lint/typecheck/build + browser/mobile/device evidence + docs under [08](08-TESTING-ACCEPTANCE.md). Every meaningful UI task follows actual tool workflow in 05. Task paths are proposed domains until scaffold exists.

## Tracker

| ID | Status | Goal / deliverable | Engineering prerequisites | Completion hold / restriction | Hours |
| --- | --- | --- | --- | --- | ---: |
| BK01 | NOT STARTED | UI01 Foundation + Shell | None; future execution instruction required | UI tools; real-logo status explicit | 4 |
| BK02 | NOT STARTED | UI02 Product Operations | BK01 | UI tools | 4 |
| BK03 | NOT STARTED | UI03 POS | BK01 | UI tools | 4 |
| BK04 | NOT STARTED | UI04 Reports | BK02, BK03 | UI tools; G3 assumptions visibly provisional | 4 |
| BK05 | NOT STARTED | Complete visual Gate A | BK01, BK02, BK03, BK04 | Owner Gate A; real logo or explicit provisional-identity approval | 2 |
| BK06 | NOT STARTED | Foundation and isolated environments | None; future execution instruction required | Backend/test scaffold only before BK05 | 4 |
| BK07 | NOT STARTED | Transactional command and audit foundation | BK06 | — | 4 |
| BK08 | NOT STARTED | Authentication and fixed-role enforcement | BK07 | — | 4 |
| BK09 | NOT STARTED | Runtime business identity slice | BK05, BK08 | UI tools | 3 |
| BK10 | NOT STARTED | Integrated role shell Gate B | BK09 | Owner shell Gate B | 2 |
| BK11 | NOT STARTED | Goods catalog vertical slice | BK10 technical evidence | UI tools | 4 |
| BK12 | NOT STARTED | Canonical Product Search and creation checks | BK11 | UI tools | 4 |
| BK13 | NOT STARTED | Barcode registry and labels slice | BK12 | UI tools; physical proof deferred to BK20 | 3 |
| BK14 | NOT STARTED | Ledger, balance and inventory command core | BK07, BK11 | — | 5 |
| BK15 | NOT STARTED | Acquisition evidence and MWA posting | BK14 | — | 5 |
| BK16 | NOT STARTED | Search-first receiving slice | BK13, BK15 | UI tools | 4 |
| BK17 | NOT STARTED | Manual goods issue slice | BK13, BK15 | UI tools | 3 |
| BK18 | NOT STARTED | Owner manual opening and cutover controls | BK13, BK15 | G2 for real-data acceptance | 3 |
| BK19 | NOT STARTED | Current stock, history and restock slice | BK12, BK14 | UI tools | 3 |
| BK20 | NOT STARTED | Product operations/device Gate B | BK13, BK16, BK17, BK18, BK19 | Owner product Gate B; actual HID and label printer | 3 |
| BK21 | NOT STARTED | Cash-session opening and atomic Sale backend | BK11, BK15 | — | 7 |
| BK22 | NOT STARTED | Integrated cashier checkout slice | BK10 technical evidence, BK13, BK21 | UI tools | 5 |
| BK23 | NOT STARTED | Thermal and A4 document slice | BK22 | Commercial nota only while G1 remains open | 3 |
| BK24 | NOT STARTED | Minimal owner correction/refund/return slice | BK23 | G3 replan if drawer-funded refund is required | 5 |
| BK25 | NOT STARTED | POS and documents/device Gate B | BK24 | Owner POS Gate B; actual scanner and receipt printer | 3 |
| BK26 | NOT STARTED | Cost revision and replay completeness | BK24 | UI tools | 6 |
| BK27 | NOT STARTED | Owner period reporting slice | BK26 | UI tools | 4 |
| BK28 | NOT STARTED | Lightweight cash-session close backend | BK21 | G3 confirmation | 7 |
| BK29 | NOT STARTED | Session reports and daily aggregation slice | BK27, BK28 | UI tools | 4 |
| BK30 | NOT STARTED | Owner log and operational history review | BK27, BK29 | UI tools | 3 |
| BK31 | NOT STARTED | Reports/audit Gate B | BK30 | Owner reports Gate B | 3 |
| BK32 | NOT STARTED | Production packaging and safe maintenance | BK30 technical evidence | No live deployment authorization implied | 6 |
| BK33 | NOT STARTED | Independent backup and isolated restore drill | BK32 | G4 destination/access and measured restore | 6 |
| BK34 | NOT STARTED | Release regression and device/performance verification | BK10, BK20, BK25, BK31, BK33 complete | All Gate B/device evidence complete | 8 |
| BK35 | NOT STARTED | Client policy and pilot readiness | BK34 | G1–G4 disposition and owner readiness | 5 |
| BK36 | NOT STARTED | Authorized deployment, cutover and pilot acceptance | BK35 | Explicit deployment authorization and owner pilot acceptance | 7 |

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
- Depends on: None beyond explicit task authorization.
- Scheduling: May proceed as backend/test foundation while BK05 waits. BK05 must be COMPLETE before any production frontend or shared visual implementation.
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
- Depends on: BK05, BK08.
- Read: [01](01-PRD.md), [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md), [09](09-DEPLOYMENT-OPS.md).
- Main files/domain: src/modules/business; profile UI; private assets.
- Acceptance/verification: Runtime name/logo/contact/header/footer/accent changes without source edit; upload/RBAC/audit tests.
- Stop if: Missing UI tools/Gate A or unsafe asset storage/contrast.

### BK10 — Integrated role shell Gate B

- Goal: Integrate approved login/navigation/workspaces with real session permissions.
- Depends on: BK09.
- Read: [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md), [08](08-TESTING-ACCEPTANCE.md).
- Main files/domain: src/app; shared tokens/components; login/workspaces.
- Acceptance/verification: Role routes and responsive/keyboard tests pass and freeze the technical evidence; owner Gate B revision is recorded separately.
- Stop if: Missing UI tools. If technical checks pass but review is unavailable, mark WAITING OWNER REVIEW; only listed downstream consumers may use the frozen evidence.

### BK11 — Goods catalog vertical slice

- Goal: Implement goods/category/unit/brand/price/minimum/active master and revisions.
- Depends on: Recorded BK10 technical evidence; BK10 may still be WAITING OWNER REVIEW, but Gate A must already be complete.
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
- Depends on: BK07, BK11. Barcode/label UI and device acceptance are not ledger prerequisites.
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
- Depends on: BK13, BK15.
- Read: [06](06-INVENTORY-SPEC.md), [12](12-BARCODE-SCANNER.md), [04](04-AUTH-RBAC-SECURITY.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: inventory/receiving; receiving UI; tests.
- Acceptance/verification: SEARCH02 and INV01/02; one receipt/balance/cost/audit/result, repeat submit safe; browser flow.
- Stop if: Missing UI tools or cost payload leak; real data requires G2.

### BK17 — Manual goods issue slice

- Goal: Implement scan/search/qty/mandatory non-sale reason and history.
- Depends on: BK13, BK15. Reuse search and inventory command primitives; receiving completion is not required.
- Read: [06](06-INVENTORY-SPEC.md), [12](12-BARCODE-SCANNER.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: inventory/manual-issue; issue UI; tests.
- Acceptance/verification: INV02; reason enforced, insufficient stock rejected, no customer-sale bypass labels.
- Stop if: Missing UI tools, missing reason/authorization or negative stock.

### BK18 — Owner manual opening and cutover controls

- Goal: Implement bounded initial counts with guarded once-per-product opening.
- Depends on: BK13, BK15. G2 remains a completion hold for real-data acceptance.
- Read: [14](14-BULK-IMPORT.md), [06](06-INVENTORY-SPEC.md), [10](10-DECISIONS.md).
- Main files/domain: inventory/opening; onboarding flag/UI; tests.
- Acceptance/verification: Atomic invalid-row rollback/retry, prior-ledger rejection, cost completeness review and global posting freeze.
- Stop if: G2 unresolved for acceptance/real data; onboarding volume unsuitable; no implicit CSV scope.

### BK19 — Current stock, history and restock slice

- Goal: Deliver paged stock/history/LOW/OUT list and badge from same current query.
- Depends on: BK12, BK14.
- Scheduling: BK20 later verifies the complete receive/issue/opening journey.
- Read: [13](13-NOTIFICATIONS.md), [06](06-INVENTORY-SPEC.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: inventory/queries; stock/restock/history UI.
- Acceptance/verification: STOCK01, stale/error refresh, RBAC and price/threshold audit; no private cost in operations.
- Stop if: Missing UI tools or balance mismatch.

### BK20 — Product operations/device Gate B

- Goal: Validate complete search/receive/issue/opening/restock/label journey.
- Depends on: BK13, BK16, BK17, BK18, BK19.
- Read: [08](08-TESTING-ACCEPTANCE.md), [12](12-BARCODE-SCANNER.md), [05](05-DESIGN-SYSTEM.md).
- Main files/domain: product/inventory browser tests; device evidence.
- Acceptance/verification: Freeze passing UI02 integration checks, then record owner Gate B plus actual USB/Bluetooth HID and label-printer evidence without rerunning unrelated suites.
- Stop if: Required hardware or owner approval is absent; mark the applicable waiting status, never simulate a pass. Listed downstream backend work may use only the frozen technical evidence.

### BK21 — Cash-session opening and atomic Sale backend

- Goal: Implement minimal logical registers, idempotent own-session opening and completed Sale with full payment, issue, costing, immutable receipt and exact session/cashier attribution.
- Depends on: BK11, BK15.
- Scheduling: BK20 owner/device Gate B is not a backend prerequisite.
- Read: [17](17-POS-SALES.md), [02](02-ARCHITECTURE.md), [15](15-FINANCE-PROFITABILITY.md).
- Main files/domain: sales; payments; cash-register/session open; session guard; receipt schema; tests.
- Acceptance/verification: POS01/02 and SESSION01–04 prerequisites: unique OPEN register/cashier, explicit opening cash, both methods attributed once, transfer excluded from expected cash; stock races/rollback every boundary; one source intent.
- Stop if: Payment/issue split, unresolved source result or cost/stock mismatch.

### BK22 — Integrated cashier checkout slice

- Goal: Connect approved session opening/identity and cart/payment/error/unknown-result interaction to atomic Sale.
- Depends on: Recorded BK10 technical evidence, BK13, BK21.
- Scheduling: BK10/BK20 owner reviews may remain pending.
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
- Acceptance/verification: Freeze passing POS01–03, RET01 and session opening/attribution integration, then record UI03 owner Gate B and actual scanner/receipt-printer evidence. Detailed close/report is BK28–BK31.
- Stop if: Missing hardware or owner approval means the applicable waiting status; unresolved atomicity/privacy failure blocks technical evidence and all dependent work.

### BK26 — Cost revision and replay completeness

- Goal: Implement bounded owner evidence correction with atomic valuation revision publication.
- Depends on: BK24.
- Scheduling: BK25 owner/device Gate B is not a costing prerequisite.
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
- Depends on: BK21.
- Scheduling: G3 must resolve before completion; BK25 owner/device Gate B is not a close-transaction prerequisite.
- Read: [17](17-POS-SALES.md), [04](04-AUTH-RBAC-SECURITY.md), [10](10-DECISIONS.md).
- Main files/domain: cash-session close; snapshot; register/session constraints; integration tests.
- Acceptance/verification: SESSION01–07 including same-day return, A→B handover, two-key duplicate, racing Sale, assisted close and atomic snapshot rollback.
- Stop if: G3 changes opening cash/register/refund assumptions or requires shared active drawer/paid-in/out; never silently add a complex shift engine.

### BK29 — Session reports and daily aggregation slice

- Goal: Integrate count/recount/close flow, own session history and safe snapshot exports plus owner daily aggregation over source facts and session rows.
- Depends on: BK27, BK28 so daily aggregation reuses the authoritative period-report query and completed close contract.
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
- Acceptance/verification: Freeze passing FIN/SESSION/AUDIT, export no-leak and incomplete/stale-state evidence, then record UI04 owner Gate B without duplicating BK34's release-wide rerun.
- Stop if: Missing owner review means WAITING OWNER REVIEW; unresolved reconciliation/privacy failure blocks technical evidence and BK32/BK34.

### BK32 — Production packaging and safe maintenance

- Goal: Prepare private-DB HTTPS runtime and forward-migration/reconciliation/health runbook.
- Depends on: Recorded BK30 technical evidence.
- Scheduling: BK31 may remain WAITING OWNER REVIEW; release still requires it complete at BK34.
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
- Depends on: BK10, BK20, BK25, BK31 and BK33 all COMPLETE. This is the Gate B/device/recovery release fan-in.
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

Statuses: NOT STARTED, IN PROGRESS, WAITING TECHNICAL CHECK, WAITING OWNER REVIEW, BLOCKED, COMPLETE. One IN PROGRESS engineering task maximum; externally WAITING tasks may coexist under the frozen-evidence rule above. Never award completion for rendering alone, documentation, historical prototype or missing device evidence. Record actual commands/results, file/revision links, owner approval and remaining blockers beside the card; preserve IDs. Scope changes update plan version/count/dependency validation. Update 00 last. No push/merge/deploy permission is implied by task completion.
