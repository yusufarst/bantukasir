# 10 — BantuKasir decisions and gates

**Effective baseline: BantuKasir Plan 1.0, 21 September 2026.** Owner explicitly requested this independent GOODS-only replan. Decisions below are planning choices under that request; no prototype approval or implementation/deployment authorization is inferred.

Provenance: copied from yusufarst/latansa-inventory at f7cecfed1fcd0b183625e49f39dfeb736ab4e3a5. D01–D54 and prior plan approvals remain in Git history at that baseline. They are **superseded as effective instructions in this repository**; the source repository remains unchanged. This concise register replaces contradictory historical sections.

| ID | Decision / reason / tradeoff |
| --- | --- |
| BK-D01 | GOODS-only full-payment Sale replaces unified Order/services/DP/reservation/fulfillment matrix. Reduces delivery scope; deferred commerce requires later replan. 01/03/17 |
| BK-D02 | One stock pool, immutable movements and synchronous balances; no warehouse/location/serial/transfer engine. Serial need remains G2, not guessed. 06 |
| BK-D03 | Atomic Sale/Payment/issue/cost facts/receipt/audit/result with ordered locks and durable source intent. Printing after commit; bank transfer is human-confirmed recording. 02/17 |
| BK-D04 | Exactly three roles, backend object RBAC, owner-only private costs/profit; staff receiving may submit new cost evidence write-only. No TOTP/2FA V1; vetted self-hosted auth permits later extension. 04 |
| BK-D05 | One PostgreSQL Product Search and shared barcode registry; search-first creation, exact uniqueness + similar-product warning. Internal Code 128 BK-P namespace, USB/Bluetooth HID/manual; camera Later. 12 |
| BK-D06 | MWA with explicit UNKNOWN, immutable evidence/allocation revisions and bounded synchronous replay. No fake complete profit, latest-price HPP or net-profit label. 15 |
| BK-D07 | One immutable daily report/cashier/date; shared day lock + count version; finalize blocks later same-day Sales, racing activity forces recount. No shift/register/float engine. Operational fit is G3. 17 |
| BK-D08 | Minimal owner executed refund/return; independent qty/money bounds, saleable return restores historical cost. Owner funds separate from cashier sales collection; no hidden payout adjustment to expected cash. G3 must confirm. 17 |
| BK-D09 | One runtime BusinessProfile, thermal/A4 from frozen Sale snapshot; commercial nota, no tax equivalence assumption (G1). 01/17 |
| BK-D10 | Complete compact UI01–UI04 stage and owner Gate A before production frontend; DESIGN sole authority/frozen revision, integration Gate B. Actual free 21st + supported Impeccable workflow required; missing setup stops UI. 05 |
| BK-D11 | Manual catalog/opening Core; CSV/XLSX/enterprise import Later. Reassess actual onboarding volume at G2; no silent addition of import engine. 14 |
| BK-D12 | Query-based LOW/OUT dashboard/badge/list; no Web Push/episodes/outbox worker Core. 13 |
| BK-D13 | Protected production DB, exact destructive-operation approval, additive migrations/populated rehearsals/reconciliation. Independent encrypted backup and measured restore; G4 RPO/RTO not assumed accepted. 09 |
| BK-D14 | Modular monolith/open-source stack, approximately Rp0 extra software; verify/pin versions at execution. No external queue/paid services; real backup infrastructure cost disclosed. 02/09 |
| BK-D15 | 36 stable Core tasks, zero started; roughly 160 focused hours / 20 working days with prompt gates/data/devices. No spare engineering capacity assumed; delay/re-scope visibly if prerequisites fail. Later is unestimated topics, not extra tasks. 11 |
| BK-D16 | IDR integer payable amounts, half-up per line; decimal qty up to 3 places and costs 6 places; Jakarta day, no physical backdating. Confirm actual units/tax fit at G1/G2. 03/15 |

## Open policy register

| Gate | Missing owner/client facts | Stops |
| --- | --- | --- |
| G1 OPEN | Tax treatment/legal invoice/buyer requirements; commercial nota baseline only | Tax-enabled behavior and production pilot; confirm no additional mandatory policy before BK35 |
| G2 OPEN | Units/precision, opening quantity/volume, manufacturer barcodes, cost evidence, interchangeable versus serialized goods | Real master/opening data; BK18 acceptance, replay/onboarding capacity changes |
| G3 OPEN | One report per cashier/day sufficient; no shared drawer/multiple sessions; separate change float and owner-funded refunds operationally workable | BK28 acceptance and pilot; replan if reopening/drawer payouts are needed |
| G4 OPEN | Independent backup destination/access/cost, acceptable RPO/RTO and measured drill | BK33 completion and production pilot |

G1–G4 do not block documentation planning. No owner answer is invented. Any required promotion of Later changes scope/estimates/dependencies before execution, never weakens integrity to hit the date.
