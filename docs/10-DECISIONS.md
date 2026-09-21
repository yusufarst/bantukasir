# 10 — BantuKasir decisions and gates

**Effective baseline: optimized BantuKasir Plan 1.1 delivery, 21 September 2026.** Owner explicitly refined cash operations, visual direction, agent continuity and the one-month execution path after Plan 1.0. Decisions below are planning choices under those requests; no prototype approval or implementation/deployment authorization is inferred.

Provenance: copied from yusufarst/latansa-inventory at f7cecfed1fcd0b183625e49f39dfeb736ab4e3a5. D01–D54 and prior plan approvals remain in Git history at that baseline. They are **superseded as effective instructions in this repository**; the source repository remains unchanged. This concise register replaces contradictory historical sections.

| ID | Decision / reason / tradeoff |
| --- | --- |
| BK-D01 | GOODS-only full-payment Sale replaces unified Order/services/DP/reservation/fulfillment matrix. Reduces delivery scope; deferred commerce requires later replan. 01/03/17 |
| BK-D02 | One stock pool, immutable movements and synchronous balances; no warehouse/location/serial/transfer engine. Serial need remains G2, not guessed. 06 |
| BK-D03 | Atomic Sale/Payment/issue/cost facts/receipt/audit/result with ordered locks and durable source intent. Printing after commit; bank transfer is human-confirmed recording. 02/17 |
| BK-D04 | Exactly three roles, backend object RBAC, owner-only private costs/profit; staff receiving may submit new cost evidence write-only. No TOTP/2FA V1; vetted self-hosted auth permits later extension. 04 |
| BK-D05 | One PostgreSQL Product Search and shared barcode registry; search-first creation, exact uniqueness + similar-product warning. Internal Code 128 BK-P namespace, USB/Bluetooth HID/manual; camera Later. 12 |
| BK-D06 | MWA with explicit UNKNOWN, immutable evidence/allocation revisions and bounded synchronous replay. No fake complete profit, latest-price HPP or net-profit label. 15 |
| BK-D07 / SUPERSEDED BY BK-D17 | Plan 1.0 used one immutable report/cashier/date and blocked later same-day Sales. Real return-to-work and drawer handover cases make that identity too restrictive. Retained only as history. |
| BK-D08 | Minimal owner executed refund/return; independent qty/money bounds, saleable return restores historical cost. Owner funds separate from cashier sales collection; no hidden payout adjustment to expected cash. G3 must confirm. 17 |
| BK-D09 | One runtime BusinessProfile, thermal/A4 from frozen Sale snapshot; commercial nota, no tax equivalence assumption (G1). 01/17 |
| BK-D10 | Complete compact UI01–UI04 stage and owner Gate A before production frontend; DESIGN sole authority/frozen revision, integration Gate B. Actual free 21st + supported Impeccable workflow required; missing setup stops UI. 05 |
| BK-D11 | Manual catalog/opening Core; CSV/XLSX/enterprise import Later. Reassess actual onboarding volume at G2; no silent addition of import engine. 14 |
| BK-D12 | Query-based LOW/OUT dashboard/badge/list; no Web Push/episodes/outbox worker Core. 13 |
| BK-D13 | Protected production DB, exact destructive-operation approval, additive migrations/populated rehearsals/reconciliation. Independent encrypted backup and measured restore; G4 RPO/RTO not assumed accepted. 09 |
| BK-D14 | Modular monolith/open-source stack, approximately Rp0 extra software; verify/pin versions at execution. No external queue/paid services; real backup infrastructure cost disclosed. 02/09 |
| BK-D15 / SUPERSEDED BY BK-D20 | Plan 1.0 estimated 36 tasks / 160 focused hours. Cash-session refinement requires honest additional design/backend/report coverage. |
| BK-D16 | IDR integer payable amounts, half-up per line; decimal qty up to 3 places and costs 6 places; Jakarta day, no physical backdating. Confirm actual units/tax fit at G1/G2. 03/15 |
| BK-D17 | Lightweight cash accountability: minimal CashRegister plus CashSession OPEN → CLOSED, explicit opening cash, one active session per drawer and responsible cashier, versioned race-safe close and immutable report. Return same day opens a new session; handover closes A then opens B. No persistent CLOSING, attendance/payroll scheduling, paid-in/out or simultaneous shared drawer. Daily owner totals derive from source facts and list session variance without double counting. Supersedes BK-D07. 03/17 |
| BK-D18 | Visual direction is dark navy/charcoal-blue + teal/aqua with quiet pastel neutrals; Inter is the proposed single family. Brand tokens control identity/primary emphasis while semantic danger/success/warning/info/focus remain independent. Exact logo asset and sampled colors are absent/unverified; all tokens remain proposed until UI01 and owner Gate A. Runtime BusinessProfile remains client-configurable. DESIGN/05 |
| BK-D19 | Repository is the continuity boundary: ChatGPT is planning/review/decision partner, Antigravity is one-task implementation executor, and neither conversation memory nor executor report alone is progress truth. Changed requirements enter canonical docs before execution; 00/11 evidence and commits carry status across fresh agents. 16/AGENTS |
| BK-D20 / SUPERSEDED BY BK-D21 | Plan 1.1 preserved 36 stable tasks and raised the bottom-up estimate to 166 focused hours (about 20.75 eight-hour days) for cash-session UI, attribution, close and aggregation. Retained as the pre-optimization checkpoint; it superseded BK-D15. |
| BK-D21 | Plan 1.1 keeps 36 tasks and revises the honest bottom-up estimate from 166 to 154 focused hours (19.25 eight-hour days). Engineering prerequisites are separated from owner/device completion holds: Gate A remains mandatory before production frontend; recorded technical outputs of BK10/BK20/BK25/BK31 may unblock listed independent engineering while reviews wait; BK34 requires every relevant Gate B/device/recovery task complete. Savings come from shared patterns/command infrastructure, consolidated evidence and avoiding duplicate full-suite runs—not weaker RBAC, transactions, inventory/cost/session integrity, audit, migrations, devices or recovery. External waiting is calendar risk, not engineering effort. Supersedes BK-D20 estimate/sequencing. 11/16 |

## Open policy register

| Gate | Missing owner/client facts | Stops |
| --- | --- | --- |
| G1 OPEN | Tax treatment/legal invoice/buyer requirements; commercial nota baseline only | Tax-enabled behavior and production pilot; confirm no additional mandatory policy before BK35 |
| G2 OPEN | Units/precision, opening quantity/volume, manufacturer barcodes, cost evidence, interchangeable versus serialized goods | Real master/opening data; BK18 acceptance, replay/onboarding capacity changes |
| G3 OPEN | Identify actual physical drawer(s)/labels; confirm explicit opening float (Rp0 permitted), one responsible cashier per active drawer, no simultaneous sharing, and physical funding of owner cash refunds. Paid-in/out and mid-session cash removal are absent | BK28 acceptance and pilot; replan if shared active drawer, drawer-funded refunds or extra cash movements are required |
| G4 OPEN | Independent backup destination/access/cost, acceptable RPO/RTO and measured drill | BK33 completion and production pilot |

G1–G4 do not block documentation planning. No owner answer is invented. Any required promotion of Later changes scope/estimates/dependencies before execution, never weakens integrity to hit the date.
