# 00 — Current state and handoff

Updated **25 September 2026**, Asia/Jakarta. Written LAST after BK02 UI02 targeted remediation, final browser verification and documentation closeout. BK01 is approved and completed; BK02 is WAITING OWNER REVIEW. Gate A remains OPEN; this is not production implementation.

| Item | Verified state |
| --- | --- |
| Repository / branch | yusufarst/bantukasir / plan/bantukasir-pos-v1 |
| Plan 1.0 baseline | 6e0c13d72cd5166f050fab9698ab71c59ca49b15 |
| Previous Plan 1.1 checkpoint | bd8a80719f3bf46166446fb70bb36e21b732e793 |
| Product | Configurable production-grade GOODS-only POS and stock control |
| Plan | Optimized BantuKasir Plan 1.1 delivery; 36 total tasks, all 36 Core, 2/36 complete |
| Schedule | 154 focused engineering hours / 19.25 eight-hour days; five-day weeks leave six focused hours inside 20 days, with no rework or waiting contingency |
| Active executor | None; next is BK03 |
| Current review / next action | **BK03 — UI03 POS**: NOT STARTED; start UI03 next |
| Implementation | BK01 and BK02 are COMPLETE; approved UI01 R6 unchanged. BK02 deterministic Product Operations prototype and final evidence approved by owner. BK03–BK36 NOT STARTED; 2/36 complete. |
| Visual approval | UI01 R6 owner-approved on 25 Sep 2026 as the master visual language. No complete Gate A or integrated Gate B. |
| BantuKasir logo | Present in repository (`assets/brand/bantukasir-logo.svg`) |
| Authorization | BK02 closeout only; no BK03 or production execution authorized. Production frontend needs complete Gate A (BK05). |
| Local HEAD lineage | `1f62e980840c6c10e336450792e660630bd4c931` — approved BK01 UI01 R6. Preserve this local lineage; do not reset to an older remote. |
| Roles | SUPER_ADMIN / Pemilik; OPERATIONS_ADMIN / Admin Operasional; CASHIER / Kasir |

## Effective source of truth

Read [AGENTS](../AGENTS.md) → this file → [11 active/next task](11-BUILD-PLAN.md) → [01 PRD](01-PRD.md) → [02 Architecture](02-ARCHITECTURE.md) → [10 Decisions](10-DECISIONS.md) → [DESIGN](../DESIGN.md) for UI → task-specific docs → [16 handoff](16-EXECUTOR-HANDOFF.md) where relevant. Repository overrides chat memory; one active task, no unrelated refactor, update 11/real decisions then 00 last.

Core uses one stock pool, immutable StockMovement and synchronous balance. Full CASH/BANK_TRANSFER Sale atomically records lines/payment/SALE_ISSUE/cost facts/receipt/audit/durable result. Search is shared across workflows. Thermal and A4 reuse one immutable snapshot; printing follows commit. MWA HPP/gross profit is owner-only, unknown costs remain incomplete. Owner corrections append facts.

Cash accountability uses minimal CashRegister/CashSession: one OPEN session per logical drawer and responsible cashier, explicit opening cash (Rp0 allowed), then atomic CLOSED report. Sale and close share the session lock/activityVersion: a racing Sale is included once and forces recount or is rejected before facts; it never disappears. A cashier returning the same date opens a new session; handover closes A then opens B. No persistent CLOSING, shared active drawer, paid-in/out or attendance/payroll shift engine. Owner daily totals derive from source facts and list immutable session variance without double counting. G3 validates actual physical practice. [17](17-POS-SALES.md) controls behavior.

Services/deferred orders/DP/reservation/partial fulfillment/warehouses/complex shifts/shared drawer/paid-in-out/serials/Web Push/camera/enterprise features are Later. CSV/XLSX import is also Later; bounded manual opening is Core. Full list in 01. The upstream LATANSA repository is unchanged and remains the full-platform blueprint. Historical D01–D54 approvals have no effective authority over this replacement plan; preserved in Git history. Effective decision register: **BK-D01–BK-D26**. BK-D17 supersedes BK-D07; BK-D21 supersedes BK-D20's 166-hour estimate/sequencing while retaining its history.

## Optimized delivery sequencing

Plan 1.1 keeps all 36 IDs and safety requirements. Its honest floor is 154 focused hours: reuse UI/command/report primitives, prepare each gate once, and reserve the complete release-wide rerun for BK34. No RBAC, immutable stock, atomic Sale/idempotency, no-negative-stock, MWA/HPP, ReceiptSnapshot, CashSession race safety, audit, database protection, migration rehearsal, backup/restore, actual-device proof or owner review was removed.

Dependencies in [11](11-BUILD-PLAN.md) now distinguish engineering prerequisites from external completion holds. At most one task is IN PROGRESS; a task waiting for owner/device evidence can coexist without consuming the executor slot. BK06–BK08 may build backend/test foundations while BK05 waits, but Gate A remains mandatory before BK09 or any production frontend. Recorded technical evidence from BK10/BK20/BK25/BK31 may unblock only the explicitly listed independent work; all four Gate B tasks, BK33 and their actual evidence must be COMPLETE before BK34.

Owner/external preparation runs beside engineering: real logo and verified UI tools/review slots; G1/G2 policy/data; G3 drawer/float/handover/refund facts; scanners and label/receipt printers; G4 backup destination/RPO/RTO; VPS/domain/deployment access. Their waiting time is excluded from 154 hours and remains the largest threat to the one-month calendar.

## Design and tool readiness

Four compact bundles remain UI01 Foundation/Shell, UI02 Product Operations, UI03 POS/session workflow and UI04 reports. The owner explicitly makes this UI01 revision the **master visual language** for later bundles after approval. [DESIGN](../DESIGN.md) records candidate BK-DS-UI01-r6; [pattern handoff](../prototypes/ui01/PATTERNS.md) maps every primitive to existing code so Antigravity can reuse it without another foundational Astra design pass. BK05 still records complete Gate A before production frontend.

The primary LATANSA CP01 reference remains read only. UI01 retains sidebar/topbar/activity/attention/table structure with BantuKasir identity, self-hosted Source Sans 3 adopted from the supplied reference, restrained navy/teal and independent semantics. Login, operations/owner dashboards, cashier NO_SESSION/OPEN_SESSION entry, profile/account and mobile shell are implemented as synthetic examples. Empty/error/loading states and session identity are reusable patterns; no full product-operations, checkout/receipt or report screens were added.

Actual free 21st.dev catalog research covered date range, chart, cart, bottom navigation and popover patterns. No callable 21st MCP or paid installation was used. Impeccable skill 4.3.1 / bundled CLI 4.0.0 context and one detector scan ran; audit/polish playbooks guided the pass. Four advisories (three intentional overlay-shadow notices and one new boundary token) are disclosed in [R6 tool evidence](../.impeccable/review/r6/TOOLS.md). That tool evidence does not itself confer owner approval; the subsequent explicit UI01 R6 approval is recorded above.

## Approved UI01 R6 evidence (preserved historical verification)

- Current [R6 final continuation packet](../.impeccable/review/r6/final/REVIEW.md) contains 24 fresh screenshots and responsive, integrity and touch/landscape results. Initial R6, R5 and older captures are historical. Existing staged deliverables were preserved; R3 and earlier untracked evidence were not staged.
- Browser checks passed: 40 route/viewport cases at 375/440/768/1024/1440; five period/chart/account groups; seven POS viewports including 844×390 and 1180×820; 23 permitted role destinations; eight integrity groups and three final emulated-touch/landscape groups. Zero captured page errors. Required JavaScript syntax and working/staged whitespace checks accompany closeout.
- Period panel is capped at 360px; presets hide endpoint fields. Custom Range shows Dari tanggal and Sampai tanggal, each opening the same anchored 336px single-month calendar. Month/year and keyboard navigation support cross-month/year selection. End-before-start is disabled; moving start beyond end resolves end with explicit feedback. Apply alone changes the active period; Cancel/outside dismissal does not. Escape dismisses child before parent. Calendar day targets are 44px high.
- BK-D26 supersedes numeric date displays: use 23 September 2026 and combined 23 September 2026, 14:25:30 across roles, sessions, dashboards and filter/report summaries. Internal ISO dates remain internal. Synthetic maximum remains 23 September 2026; month summaries use Indonesian month/year.
- Cashier catalog/cart panels share bounded height from 768px. Both lists scroll internally; total/payment stays in-panel, without body growth when adding seven distinct product lines. Short landscape compresses redundant headings/session details and summary while preserving a complete readable cart row and quantity controls. Phones retain catalog/cart switching and fixed total above bottom navigation. Eight compact authored ATK SVG thumbnails aid discovery. Active POS for Cashier and Owner has one local search, without duplicate global search.
- Query/cart/session survive permitted navigation and sidebar changes. Unknown/OUT/stock-cap feedback preserves the draft. Account menus remain simplified and role-safe; opening does not change rail width. Desktop outside clicks preserve navigation, mobile drawer dismisses outside; per-role navigation scroll stays stable.
- Owner trend remains period-aligned, interactive and honest about missing costs. Hari Ini shows a daily point because hourly fixture facts are absent. Null-cost probes suppress HPP/gross figures; synthetic days without sales are explicitly zero sales, not zero unknown cost. Operations/Cashier do not receive owner financial navigation. These are prototype presentation checks, not backend RBAC.
- Notifications retain compact priority-first stock attention and three recent activities; Cashier receives own-session information only. Existing Source Sans 3, navy/teal family, original logo, semantic colors and restrained borders remain the shared candidate.
- UI01 synthetic scope only; no real payment/receipt, backend, migration, database operation, upstream LATANSA edit, commit, push, merge, deployment or next-BK execution. `.agents/` and old helper evidence remain untracked; `.local/` stays ignored. That pre-commit packet preceded the approved R6 commit; current local HEAD is `1f62e980840c6c10e336450792e660630bd4c931`. Actual devices, assistive technology, scanners/printers and complete Gate A/B remain outstanding.

## BK02 final candidate and verification

BK02 is **COMPLETE**, with [UI02 routes and limits](../prototypes/ui02/README.md), [reuse map](../prototypes/ui02/PATTERNS.md), [final review packet](../.impeccable/review/bk02/REVIEW.md) and [truthful tool record](../.impeccable/review/bk02/TOOLS.md). UI02 loads the approved UI01 stylesheet and a shell reference copy with route adaptation; no UI01 source or brand asset changed.

Representative states include catalog/search/list/detail/form, exact duplicate block and similar-product warning, barcode alias/internal label intent, receiving, write-only Operations incoming cost, owner opening, reasoned manual issue, current/LOW/OUT/restock, role-safe history and owner append-only correction/reversal. New products start at stock 0; explicit final confirmation alone changes deterministic fixture quantities. Operations review/results/history do not echo incoming cost values; Cashier cannot manage products. The static fixture harness is not production RBAC.

Independent design/evidence review identified hidden reversal fields, lost checkbox focus, mobile draft reachability, catalog/fixture density and missing correction impact. Targeted fixes are complete. Final Chromium results: **55 responsive cases at 375/440/768/1024/1440; 8 core interaction groups; 9 additional audit groups; 21 resolved sidebar destinations; zero captured page errors; 14 fresh screenshots**. Node syntax and working/staged whitespace checks, UTF-8 scan and exact file inventory are recorded in the closeout evidence. Physical devices, assistive technology and label/HID hardware are not certified by these emulated checks.

Actual free 21st catalog research informed filters/tables/dialogs/forms; no community source was imported. Impeccable skill 4.3.1 / CLI 4.0.0 context and one detector pass ran, with independent browser reviews. Detector: 0 primary findings and 3 disclosed inherited overlay-shadow advisories. The only new vendor code is local MIT JsBarcode 3.11.0 for Code128 preview. No paid service was used.

Only intentional BK02 source, canonical documentation and the latest review packet are staged. `.agents/`, historical untracked evidence and ignored `.local/` are excluded. No product-image capability, CSV/XLSX import, warehouse/location, backend/database/migration, production frontend or BK03 was added. No commit/push/merge/deploy was performed. Gate A remains OPEN; BK03 NOT STARTED.

## Remaining gates / exact next action

| Gate | Still needed |
| --- | --- |
| G1 OPEN | Actual tax/legal invoice/buyer policy; commercial nota makes no formal-tax claim |
| G2 OPEN | Units/opening volume/barcodes/cost availability/serialized need before affected real onboarding |
| G3 OPEN | Actual drawer identities/labels, opening-float practice, one responsible cashier/no simultaneous sharing, owner cash-refund funding; replan if extra cash movements are required |
| G4 OPEN | Independent backup destination/access/cost and accepted measured RPO/RTO before production |

These facts still block the corresponding execution/pilot gates in 11. Current handoff: **BK03 UI03 POS**. BK01 and BK02 remain COMPLETE and UI01 R6 is the approved baseline. BK03 is NOT STARTED and authorized to start next. Complete Gate A (BK05) is still required before production frontend.
