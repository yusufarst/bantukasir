# 03 — Conceptual domain model

Business contracts, not migrations. M = Core, L = later. One business/database; no tenant discriminator or cross-company queries. [17](17-POS-SALES.md) owns commercial lifecycle, [06](06-INVENTORY-SPEC.md) goods invariants.

## Catalog and identity

| Entity | Identity and constraints |
| --- | --- |
| User / Role / AuthSession (M) | Individual immutable actor identity; INVITED/ACTIVE/DISABLED; three fixed roles under 04; revocable library sessions; historical users never deleted |
| BusinessProfileRevision (M) | Singleton configured company with immutable activated revisions: display/legal name as supplied, logo asset, color choices, address/contact and document header/footer; optimistic edit/preview/activate; owner only |
| BusinessPolicyRevision (M) | Verified payment/receipt/tax policy, currency IDR, business timezone Asia/Jakarta; owner activation/audit; regulatory meaning is not inferred |
| Product (M) | Permanently unique normalized SKU; commercialType GOODS or SERVICE, name/category/unit/precision, active/version, selling price revision; one reusable master |
| GoodsProfile (M) | Exactly one for GOODS: trackingMode QUANTITY/SERIALIZED, minimum/monitoring/reorder target, costing eligibility; forbidden for SERVICE |
| SellingPriceRevision (M) | Product, IDR amount, effective server time, actor/reason/version; optimistic update; no historical sale recalculation |
| Category / Brand / Unit (M) | Unique normalized codes; referenced records cannot be deleted; unit precision 0–3, serial zero; brand optional |
| Barcode (M) | Unique normalized token, exactly one goods Product or SerializedItem target; ACTIVE/RETIRED; multiple aliases allowed, never reassigned/reused |
| SerializedItem (M) | GOODS/SERIALIZED only; internal ID/code; manufacturer serial unique within product; REGISTERED/IN_STOCK/ISSUED, position/lastLineId/version |
| MediaAsset (M logo; L products) | Immutable logical key/checksum and validated derivative; receipt/profile references prevent deletion; no user filesystem paths |

SERVICE is selected by SKU/name search and does not receive inventory barcodes in Core. It has no GoodsProfile, StockBalance, SerializedItem, StockHealth, opening row or movement allocation. Units such as job/hour do not imply stock. Material sold alongside work is a separate GOODS line; non-sale consumables use inventory issue with an explicit cost reference, never hidden service stock.

commercialType locks after any sale/ledger reference; tracking/unit/precision lock after ledger or sale use. Correct misclassification by a new item and audited deactivation, not rewriting history. Goods deactivation requires zero stock/no open operations; service deactivation requires no active submitted sale. Sale snapshots remain readable; future drafts revalidate. SKU/barcodes never become available for reuse.

## Physical inventory

| Entity | Contract |
| --- | --- |
| Warehouse / StockLocation (M) | Unique codes, active state; STORAGE Core; parent immutable after use; no deactivation with stock/open operations |
| StockMovement / StockMovementLine (M) | POSTED immutable header/legs; type/source/reason/actor/time; product/location signed delta, serial where relevant, unit snapshot, before/after, product inventorySequence; RESTRICT references |
| StockBalance (M) | Unique goods/location, exact onHand/reserved/version; onHand ≥0, 0≤reserved≤onHand; reserved=0 before reservation extension; only command service writes |
| CommandReceipt (M) | Standalone stock command actor/key/session fingerprint, one movement and safe result in same transaction; permanent |
| OnboardingFreeze / ImportJob / ImportRow / ImportCommit (M) | Count/cutover scope, private staged typed data and immutable applied manifest; lifecycle/limits under 14 |
| InventoryReservation / StockOpname / Approval (L) | Event-backed remaining allocation; location freeze/count revision; approval hash/expiry/one-use consumption under 06 |

Transfers are two legs with zero global quantity delta; standalone owner correction/reversal never edits original history. A sale's stock movement is generated only through the sale command transaction; returns are linked receipts.

## POS, money and shifts

| Entity | Contract |
| --- | --- |
| Register (M) | Owner-configured logical drawer and source location; version/active; cannot deactivate while a shift is open |
| CashierShift (M) | Register/cashier, OPEN/CLOSING/CLOSED, timestamps and actor; one open/closing shift per register and cashier; guard for sale/refund/close |
| CashEvent (M) | Append-only shift/opening/sale/refund/paid-in/out event, signed cash amount, actor/source/reason; unique business source; never an expense journal |
| ShiftCount / ShiftClose (M) | Counted/expected/difference and review/handover snapshot; count revisions preserved; unverified emergency close distinguishable |
| SaleCart (M) | Actor/create key, shift, optimistic revision and draft lines; OPEN/CANCELLED/COMPLETED; nonfinancial and nonreserving; one completed sale forever |
| Sale / SaleLine (M) | Unique number and cart; immutable completed snapshots of identity/prices/type/units/amounts/fulfillment; GOODS movement allocations only |
| SaleCommandReceipt (M) | Actor/key fingerprint, cart/command aggregate, result IDs; permanent; optional movement link for service-only sale; no separate stock retry path |
| PaymentRecord (M) | Immutable full applied amount/method/tender/change/reference/attestation; sale-linked, actor/server time; no card secrets or implied gateway settlement |
| SaleDocumentSnapshot (M) | Sale/number, business/line/payment/template/asset versions; unique original receipt; thermal/A4 are representations of one document |
| PrintRequest (M) | Actor/document/time/copy/reason; requested printing, not physical success |
| CommercialReturn / ReturnLine (M) | Original sale line/issue/serial, actual quantity/location, movement and actor; cumulative physical-return bounds |
| Refund / RefundLine (M) | Original sale/payment/line allocation, amount/quantity, reason/execution method and optional physical return link; immutable; cumulative credit bounds |
| RevenueEvent (M) | Completed goods handover/service fulfillment or linked credit, net amount, type/category snapshot, recordedAt/recognizedAt and source; never a quotation |

Sale display refund status derives from linked Refunds; Sale facts never transition back to a draft. Shift cash differs from revenue and payment collection: opening float and paid-in/out are not revenue; non-cash payment is not drawer cash. See 17 for races, recovery and refund money/stock separation.

## Finance

| Entity | Contract |
| --- | --- |
| AcquisitionCostEvidence (M) | Versioned receipt/opening/adjustment source cost and components; MISSING/DRAFT/VERIFIED, owner-only |
| ServiceCostEvidence (M) | Sale SERVICE line, fulfilled quantity/scope, actual direct labor/subcontract/material/other supported components, source, verification and revisions; explicit zero requires evidence |
| ValuationRun / CostEvent / CostAllocation (M) | Policy/evidence revision, fixed source watermark and ordered goods pool; PENDING/BLOCKED/READY/PUBLISHED/SUPERSEDED; unique run/source kind; immutable published amounts |
| FinancialReportSnapshot (M) | Period/scope/watermarks/source completeness/versions/calculatedAt, owner-only; atomic publication and auditable revision |
| DispatchClearing (L) | Later shipment-before-acceptance extension only; counter sale matches issue and fulfillment immediately |

Finance never writes physical balances. GOODS costing eligibility is separate from SERIALIZED tracking. SERVICE direct costs never use MWA or automatically assume zero. A reference to inventory-consumed material must have unique financial allocation to avoid double charging it.

## Attention, audit and later domains

StockHealth (goods only) stores evaluated NORMAL/LOW/OUT or null, version and active AttentionEpisode. One open episode/product; one LOW and OUT NotificationEvent/episode. UserNotification is unique event/recipient; readAt never resolves stock. Outbox/Delivery uses leases/fencing and append-only attempts; subscriptions are sensitive and user-owned. AuditEvent is append-only with actor/source/time/reason/safe details; finance audit is restricted.

Later public SiteContentRevision/PublicSettingsRevision/ProductPublicationRevision/PublicationRecord use structured draft/preview/publish and allowlisted snapshots; safe product projections never include exact stock/costs. OutboundClickEvent is not a lead/sale. RFQ/Lead/Quotation/Deal are later, separate from immediate Sale. QCRecord/Warranty/ServiceCase handle quality/repair/custody, not SERVICE catalog sales.

## Cross-entity enforcement

Opaque server IDs; document number gaps allowed. Critical FKs use RESTRICT, never cascade history deletion. UNIQUE/CHECK/FK enforce local constraints; deferred validation plus ordered guards enforce sale-to-ledger equality, goods-only entities and cumulative return/refund bounds. No cross-table CHECK expressions.

Quantity numeric(18,3), precision 0–3; JSON decimal strings. Monetary/cost precision and rounding belong to 17/15. All mutable drafts/master/settings use optimistic version checks. Time is UTC server timestamptz; business ranges WIB. Demo/test data is isolated. 09 owns retention and restoration.

