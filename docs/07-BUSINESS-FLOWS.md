# 07 — Business workflows

Owns cross-module responsibilities; [17](17-POS-SALES.md) owns POS atomicity, [06](06-INVENTORY-SPEC.md) inventory, [04](04-AUTH-RBAC-SECURITY.md) access and [15](15-FINANCE-PROFITABILITY.md) financial meaning.

## F01 — Configure and onboard

Owner activates account/TOTP, sets BusinessProfile and verified receipt/payment policy, creates locations/units and assigns operational roles. Operations prepares goods/service master with selling prices/aliases; owner applies the reviewed create-only batch. Services need no opening/monitoring. Validate actual scanners/labels and printer with demo data.

Owner defines cutover, activates location freeze and reviews staff counts/separate goods opening imports. Commands create ledger/items, never editable balances. Reconcile count/serials, verify private opening costs or expose incomplete evidence, enable goods monitoring and release freeze. Complete recovery/device/pilot gates before live trading. No competing balance sources.

## F02 — Receive goods

Operations selects destination, sender/source, reason and reference → scans existing alias or selects goods → registers new serial identity when needed → edits/reviews draft → confirms once → server document after atomic posting. Unknown code is retained for authorized registration, never auto-created. Owner enters acquisition cost separately; missing cost does not stop lawful receipt. Services cannot be received.

Supplier procurement/credit is outside Core; reference documents still explain each receipt. Label printing does not prove receipt.

## F03 — Counter sale

Cashier selects source location → scans/searches goods or adds a service → reviews quantity/prices/total → confirms handover/completed-service facts and payment → completes once. Server revalidates prices, payment and stock and commits the sale and goods issue together. No warehouse re-entry. A printed or onscreen receipt uses that sale number.

Unknown result freezes the attempt: recover original sale, do not charge again or start a replacement. Printer failure offers reprint. Service already completed is the baseline; deposits/staged work require Q02 scope revision first.

## F04 — Non-sale issue and direct transfer

Operations uses Barang Keluar only for non-sale consumption, loss handoff or other authorized reason/reference. Selling goods must use POS. Transfer requires both locations and immediate verified movement; travel with later receipt needs staged transit first. Same ledger and freeze/serial/availability checks apply.

## F05 — Cancellation, refund and correction

Unsubmitted basket may be edited/cancelled. Unknown/submitted sale cannot be discarded. Completed sale stays immutable. Owner verifies the original and uses 17 linked refund/return with reauthentication and reason. Physical return and money refund are distinct facts; neither silently implies the other.

Standalone inventory recording errors use eligible full reversal or count-based adjustment under 06. Sale-linked movements cannot be independently reversed. Staff raises the issue to the owner; no general maker-checker engine in Core.

## F06 — Restock

Owner/operations reads current LOW/OUT episode, stock by location and history, then organizes replenishment outside a purchase-order system. Optional target suggests a quantity, never buys automatically. Read/unread does not resolve attention. Receipt/return or lawful policy change reevaluates it; only NORMAL or explicit administrative monitoring removal closes the episode with its actual cause.

## F07 — Review business

Owner selects the reporting period → reviews net goods/service revenue, goods HPP, direct service costs and complete gross results → drills into sales and missing evidence. Operations views safe receipt/issue/restock and permitted sale totals; cashier views own sales/payment totals. These are not drawer reconciliation or net profit. Cash handover/shift needs are Q03.

## F08 — Account/session interruption

Disablement/revocation is enforced by backend guards; historical actors remain. Authentication expiry pauses checkout and requires the same actor to recover the attempt. Server cart/receipt lets the owner investigate an abandoned session. A second employee starts a separate authorized cart, never impersonates the first.

## F09 — Incident/recovery

Stop posting when integrity is uncertain; identify unresolved sale/stock command by durable reference. Owner decides business reopening, authorized operator restores under 09. Reconcile real cash/non-cash evidence, printed receipts and physical goods against snapshot loss. Never re-charge or replay missing restored commands by assumption.

## Later workflows

Reservations hold availability but not physical stock. Fulfillment consumes reservation and posts issue atomically; later B2B delivery/acceptance must integrate 17/15 without double revenue. Opname freezes locations for count/review/adjustment; proposals bind hash/version and one-use approval. Transit uses separate dispatch/receive legs. QC/quarantine/warranty/repair ServiceCase distinguishes customer custody from company goods.

Public work uses structured CMS draft/preview/owner publish and published product projections; operations may prepare drafts. Configured wa.me measures at most an outbound click. RFQ → lead → quotation is not a sale/revenue and requires its later commercial design. Do not make these prerequisites for counter POS.
