# 11 — Official build plan and progress

**Plan version 2.1 — 20 September 2026.** The only implementation progress tracker. All build/prototype tasks remain unstarted. Next sequence: owner architecture review → official brand assets → P00 prototypes when instructed. Production requires a separate implementation instruction; documents or approved HTML do not automatically authorize it.

## Status and calculation

| Status | Meaning | Completion credit |
| --- | --- | ---: |
| [ ] | NOT STARTED | 0 |
| [~] | IN PROGRESS | 0 |
| [T] | WAITING FOR TECHNICAL VERIFICATION | 0 |
| [V] | WAITING FOR OWNER VISUAL REVIEW | 0 |
| [x] | COMPLETE AND VERIFIED | 1 |
| [!] | BLOCKED | 0 |

[BE] = backend/database/integration without visual impact; [FE] = frontend/prototype; [FS] = backend plus visual impact. FE/FS needs [05](05-DESIGN-SYSTEM.md) evidence. At most one [~] task. Record the exact blocker and unblock condition; merely awaiting its turn is not blocked.

**65 tasks in 13 phases P00–P12; 34 Core tasks P00–P06.**
Overall progress = 100 × completed tasks /65, two decimals.
Core progress = 100 × completed P00–P06 tasks /34.
Phase progress uses actual row count. Prototype completion after Gate A counts as a design deliverable, **not production implementation**. Integration has separate Gate B tasks. [V] receives no partial credit.

Task count is not elapsed time, cost or complexity. Scope changes require a version and old/new denominator with rationale. Never split finished work to inflate progress or remove mandatory features to shrink the denominator.

| Snapshot | Verified state |
| --- | --- |
| Documentation, outside build | Technical refinement complete; owner review pending |
| Overall plan | **0/65 = 0.00%** |
| Core | **0/34 = 0.00%** |
| Active build task | None |
| First build task after review/assets/instruction | P00.1 |
| Production implementation | NOT STARTED |
| Before production | Owner document review, approved CP01–CP04 and explicit implementation instruction |

Evidence column later records commit, concise technical results and visual REVIEW link. Current dash means no evidence exists.

## Plan history

Version 1.0: 55 tasks, Core 30. Version 2.0 consolidated six unstarted prototype rows (P01.3/P02.3/P04.1/P04.4/P05.3/P06.3) into four P00 bundles, added six Core import/evidence tasks, one camera task and five finance tasks. Overall 55−6+4+6+1+5=65; Core 30−6+4+6=34. Old P11 became P12. No completed work gained duplicate credit. D31 records the replacement of D17's denominator.

Version 2.1 translates the tracker and makes structured public CMS, owner publication, wa.me configuration and portability acceptance explicit within existing P09/P06 tasks. Count remains 65/34; no implementation credit added.

## P00 — Core visual contracts before production

Prerequisites: owner architecture review and official brand assets. No Next.js/API/database in this phase. Four deterministic Core bundles with browser preview; CP05 waits until public work.

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P00.1 | [ ] | [FE] | Verify official assets; CP01 shell/auth, tokens/Lucide, brand toggle/rail/drawer, anti-slop/redundancy audit; Gate A | Owner review + official assets + prototype instruction | — |
| P00.2 | [ ] | [FE] | CP02 products/labels/product import/quantity and serial opening/errors/results/private owner evidence; Gate A | P00.1 | — |
| P00.3 | [ ] | [FE] | CP03 receipt/issue/transfer/stock/history/correction, keyboard/mobile, uncertainty/duplicates; Gate A | P00.2 | — |
| P00.4 | [ ] | [FE] | CP04 attention/inbox/cockpit/system exceptions; finance demo only, required viewports; Gate A | P00.3 | — |

## P01 — Foundation, identity and access (Core)

Production prerequisites remain separate from prototype completion.

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P01.1 | [ ] | [BE] | Authorized scaffold, compatible versions/lockfile, config, dev/test PostgreSQL, audit storage/DB roles and local test infrastructure | P00.4 + owner implementation instruction | — |
| P01.2 | [ ] | [BE] | Better Auth, permissions, bootstrap/invite/reset/2FA/revocation and negative security tests | P01.1 | — |
| P01.4 | [ ] | [FS] | Implement approved CP01 with real auth/sidebar/keyboard/mobile; Gate B | P01.2, P00.1 | — |
| P01.5 | [ ] | [BE] | Lint/typecheck/unit/integration/E2E/build/secret scripts and CI, environment guards, auth smoke | P01.2, P01.4 | — |

## P02 — Product, location and identity (Core)

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P02.1 | [ ] | [BE] | Product/SKU/unit/precision/tracking, category/brand, locations, optimistic versions and CRUD permissions | P01.5 | — |
| P02.2 | [ ] | [BE] | Barcode registry, no-stock serial registration, normalization/uniqueness/resolver and labels | P02.1 | — |
| P02.4 | [ ] | [FS] | CP02 master/location/identity/labels with real backend, server search/pagination and print smoke; Gate B | P02.2, P00.2 | — |
| P02.5 | [ ] | [BE] | Safe deterministic master demo and environment guard; no direct balance seed | P02.2 | — |
| P02.6 | [ ] | [BE] | XLSX/CSV staging/validation/atomic apply/permissions/retry/private errors; 5,000-row and duplicate tests | P02.5 | — |
| P02.7 | [ ] | [FS] | CP02 import preview/owner apply/history/errors, export/label selection; phone status and desktop review; Gate B | P02.6, P02.4 | — |

## P03 — Atomic ledger and stock attention (Core)

State/episode/outbox are part of the initial posting transaction. Device delivery/UI follow in P05.

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P03.1 | [ ] | [BE] | Ledger/balance/receipt/product sequence, constraints/locks, immutable DB privileges; reviewed migrations | P02.7 | — |
| P03.2 | [ ] | [BE] | Receipt/issue/transfer, serial transitions, idempotency/status recovery and atomic audit | P03.1 | — |
| P03.3 | [ ] | [BE] | Threshold/health/episodes/inbox/outbox in posting and policy changes; NOT01–05 | P03.2 | — |
| P03.4 | [ ] | [BE] | Opening, owner adjustment/reversal with safe rejection, lawful demo movements | P03.3 | — |
| P03.5 | [ ] | [BE] | Independent-connection races, rollback/property/idempotency/serial suite and full-ledger oracle | P03.4 | — |
| P03.6 | [ ] | [BE] | Separate quantity/serial opening import, cutover freeze, bounded 5,000-row atomic posting, receipts/recovery | P03.5, P02.6 | — |
| P03.7 | [ ] | [BE] | Owner-only receipt/opening evidence, revision/completeness/private audit; unknown cost does not block stock | P03.6 | — |

## P04 — Warehouse and scanner (Core)

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P04.2 | [ ] | [FE] | CP03 HID reducer/queue/focus, rapid quantity, serial dedupe/session recovery; browser checks and Gate B | P00.3, P03.7 | — |
| P04.3 | [ ] | [FS] | Resolver/command/status integration, actual USB/Bluetooth and network errors; Gate B | P04.2, P03.5 | — |
| P04.5 | [ ] | [FS] | CP03 real stock/history/serial/correction/export, permissions/re-auth; Gate B | P04.3, P00.3 | — |
| P04.6 | [ ] | [FS] | CP02 opening review/result/recovery, count reconciliation/freeze release/monitor activation; Gate B | P04.5, P03.6 | — |
| P04.7 | [ ] | [FS] | CP02 private owner evidence/source/completeness/revision, no staff cost fields; Gate B | P04.6, P03.7 | — |

## P05 — Notifications and owner cockpit (Core)

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P05.1 | [ ] | [BE] | Outbox lease/retry/dedup, Web Push subscription and delivery authorization | P04.7, P03.3 | — |
| P05.2 | [ ] | [BE] | Consistent dashboard/inbox/read-state queries, factual metrics and WIB boundaries | P05.1 | — |
| P05.4 | [ ] | [FS] | CP04 real queries/policy, attention without duplicate metrics, current state separate from period, no fake finance; Gate B | P05.2, P00.4 | — |
| P05.5 | [ ] | [FS] | Inbox/push permission/escalation/recovery E2E and provider tests; Gate B; actual owner-device acceptance at P06.5 | P05.4 | — |

## P06 — Operations, recovery and Core pilot

No operational production stock before this phase is accepted. HTTPS staging is required for real device tests; go-live waits for restore evidence and Core acceptance.

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P06.1 | [ ] | [BE] | Portable Compose/Caddy, pinned images/private network/secrets, configurable origins/storage, encrypted off-host DB/media backup, health/worker supervision | P05.5 | — |
| P06.2 | [ ] | [BE] | Isolated restore including alternate host/storage configuration, measured RPO/RTO, reconciliation and restored jobs/outbox controls | P06.1 | — |
| P06.4 | [ ] | [FS] | CP04 real exceptions/health/backup, unknown not success, no normal-health filler card; Gate B | P06.2, P00.4 | — |
| P06.5 | [ ] | [FS] | Full Core gate 08, devices/labels/mobile, actual owner push on HTTPS/closed tab, visual acceptance, assumptions/SOP/cutover and pilot acceptance | P06.4, all P01–P05 | — |

Core requires all 34 tasks [x], accepted device/channel behavior and no safety blocker. Product/opening import precedes pilot. Tests never bypass owner review or restore.

## P07 — Reservation, transit, stock opname and approval

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P07.1 | [ ] | [BE] | Reservation/events/expiry/partial fulfillment, availability/health and race tests | P06.5 | — |
| P07.2 | [ ] | [BE] | Transit dispatch/partial receive/return/loss and identity/ledger invariants | P07.1 | — |
| P07.3 | [ ] | [BE] | Versioned one-use approval, opname freeze/count/adjustment and negative tests | P07.2 | — |
| P07.4 | [ ] | [FE] | CP03 warehouse/approval variants and conflict states; Gate A | P07.3 | — |
| P07.5 | [ ] | [FS] | Approved advanced flows, cross-module E2E and Gate B | P07.4 | — |
| P07.6 | [ ] | [FS] | CP03 camera variant, Gate A for new interaction, permissions/frame latch/manual-HID fallback and actual devices; Gate B | P06.5, P00.3 | — |

## P08 — QC, warranty and service

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P08.1 | [ ] | [BE] | Immutable QC, quarantine and eligibility integrated with ledger/health | P07.5 | — |
| P08.2 | [ ] | [BE] | Warranty/service and customer custody separate from company stock | P08.1 | — |
| P08.3 | [ ] | [FE] | CP02/CP03 QC/warranty/service/item-detail variants and exception states; Gate A | P08.2 | — |
| P08.4 | [ ] | [FS] | Approved real QC/service flows; Gate B | P08.3 | — |
| P08.5 | [ ] | [BE] | Custody/availability/audit/privacy regression and backup of new metadata | P08.4 | — |

## P09 — Structured public CMS, website and catalog

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P09.1 | [ ] | [BE] | Structured DB content/settings, owner-only draft/preview/publish/revisions/audit, safe product projections, media validation/storage, configured wa.me templates, dynamic published reads | P06.5 | — |
| P09.2 | [ ] | [BE] | Published search/filter/detail/comparison, featured references, pagination and allowlist queries | P09.1 | — |
| P09.3 | [ ] | [FE] | CP05 factual B2B/B2G home/catalog/detail/comparison plus CP02 CMS editor/preview variant; official assets and Gate A | P09.2 | — |
| P09.4 | [ ] | [FS] | Real internal CMS/public rendering/SEO/wa.me, content changes without redeploy, safe optional click tracking; Gate B | P09.3 | — |
| P09.5 | [ ] | [BE] | PUB01–05, leak/cache/draft/publication authorization/media/redirect/performance tests; sentinel private-field checks | P09.4 | — |

## P10 — RFQ, leads, quotations and sales

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P10.1 | [ ] | [BE] | RFQ validation/anti-spam/PII policy, inquiry attribution without false conversion claims | P09.5 | — |
| P10.2 | [ ] | [BE] | Lead/quotation/deal, prices/discounts/acceptance/RevenueEvent/credit notes, sales permissions and reservation service | P10.1, P07.1 | — |
| P10.3 | [ ] | [FE] | CP02/CP05 RFQ/follow-up/quotation/deal/privacy variants; Gate A | P10.2 | — |
| P10.4 | [ ] | [FS] | Approved sales/public RFQ with real data/audit; Gate B | P10.3 | — |
| P10.5 | [ ] | [FS] | Deal → reservation → issue/partial/cancel E2E and cross-role owner review | P10.4, P07.5 | — |

## P11 — Costing and profitability after Sales

First report is complete-data **Laba Kotor Penjualan Barang**. No hidden expense/net-profit module. [15](15-FINANCE-PROFITABILITY.md) and [08](08-TESTING-ACCEPTANCE.md) own policy/acceptance.

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P11.1 | [ ] | [BE] | Eligibility/verified value cutoff, global SKU MWA, sequence/allocations/clearing and versioned replay | P10.5, P03.7 | — |
| P11.2 | [ ] | [BE] | Revenue/COGS/return/credit matching, completeness/watermark, atomic report publication and owner-only queries | P11.1 | — |
| P11.3 | [ ] | [FE] | CP04 finance/drill-down revision, scope/period/incomplete/revised states, no net-profit label; Gate A | P11.2 | — |
| P11.4 | [ ] | [FS] | Real approved gross profit/margin, valid comparisons and Gate B | P11.3 | — |
| P11.5 | [ ] | [BE] | Golden costing/return/reversal/delay/rounding, no-leak matrix and ledger/clearing/report/restore reconciliation | P11.4 | — |

## P12 — Extended reporting and production maturity

Basic hardening is already mandatory in P06. This phase must not defer Core safety.

| ID | Status | Class | Deliverable / acceptance | Dependencies | Evidence |
| --- | --- | --- | --- | --- | --- |
| P12.1 | [ ] | [BE] | Cross-module real-data reports/exports, retention/archive and source permissions | P11.5, P08.5 | — |
| P12.2 | [ ] | [FE] | CP02/CP04 report/filter/drill-down/export/system variants and redundancy audit; Gate A | P12.1 | — |
| P12.3 | [ ] | [FS] | Approved real report queries/access audit; Gate B | P12.2 | — |
| P12.4 | [ ] | [BE] | Actual-load profiling, patch/hardening, expanded restore, capacity/monitoring | P12.3 | — |
| P12.5 | [ ] | [FS] | Full platform regression, final owner acceptance, operator handover/runbook and verified release | P12.4, P07.6 | — |

## Tracker updates

Update only actual task changes, then recalculate summaries. Include date/evidence and specific blockers. Preserve [V] while waiting for owner review; independent BE work may become the sole active task. Do not remove gates because review takes time.

Later phases may be reordered by a recorded business decision without weakening Core/invariants. P09 depends on reliable Core, not an artificial QC dependency. Camera P07.6 is independent of reservation/transit and may be prioritized after Core. New significant visual variants still require their canonical CP and both applicable gates; do not create a new family just to rename a page.
