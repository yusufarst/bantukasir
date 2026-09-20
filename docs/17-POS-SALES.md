# 17 — POS, payment records, receipts and cashier shifts

Owns commercial sale/return/payment/receipt/shift contracts. [06](06-INVENTORY-SPEC.md) owns goods posting, [12](12-BARCODE-SCANNER.md) scanning, [15](15-FINANCE-PROFITABILITY.md) recognition/costs and [04](04-AUTH-RBAC-SECURITY.md) permissions. No production implementation exists.

## Accepted scope and boundaries

Owner confirmed: paid retail receipt plus A4 copy; tax needs verified before pilot; services already completed and fully paid; gross-profit reporting; cashier shift reconciliation required in Core. Default refund authorization is owner-only with reason and fresh authentication. No credit, deposits, installment/provider integration, mixed tender, gift cards or scheduled work. Future manual non-cash methods need an enabled method and verification SOP; Core cash flow is mandatory.

Sale completion attests immediate goods handover and already-completed service. Payment recorded is not proof of electronic settlement and printing is not proof of payment. Never recognize unperformed services by selling a stockless placeholder.

## Catalog, basket and pricing

Product uses commercialType GOODS/SERVICE. SaleLine snapshots type, SKU/name, category, unit/precision, quantity, base selling price/version, authorized override/reason, net amount and any configured tax treatment. GOODS additionally identifies source location and serialized units if relevant. SERVICE has no inventory location/serial; unit can be job/hour, exact precision and positive quantity.

Server owns arithmetic. Re-resolve active products and current price revisions at confirmation. Stale price/settings require review, never silently change what the cashier approved. Cashier cannot type an arbitrary unit price or discount. Owner may reduce a line price with reason and recent authentication in their own authorized sale; no shared PIN or borrowed owner session. Operations changes the catalog price for future baskets, with audit. Core has no basket-wide discounts/promotions. Overrides cannot produce negative line totals; free lines require owner reason and at least one positive net line in a normal paid sale. Fully free distributions use authorized non-sale inventory workflow until explicitly designed.

All commercial amounts are IDR exact decimal strings; selling prices/tenders/line totals have two decimal places, never JS float. quantity × price is half-up rounded per line to two places; header subtotal/discount/net/tax/total are sums of stored line values. Unit prices cannot exceed schema bounds. Costing precision is separately owned by 15. Cash denominations and any need for cash rounding must be confirmed before pilot; there is no silent rounding adjustment.

## Durable cart and lifecycle

SaleCart is a server-owned draft, created online with a client-generated UUID creation key and unique actor/key constraint. Repeated creation returns the same cart. It holds actor, shift, optimistic revision, minimal lines, lastValidatedAt and OPEN/CANCELLED/COMPLETED state; it is not revenue, reservation or stock. Save updates online; autosave is not checkout. Recover unresolved local creation key before opening a new cart.

Multiple devices/tabs may read their own cart, but stale version edits fail. Cashier can access own carts/sales; owner can investigate abandoned carts. Operations has safe sale-history access only. Editing a submitted/uncertain envelope is forbidden. COMMITTED evidence is the durable SaleCommandReceipt, not a optimistic browser state.

Draft → review → freeze attempt → submit → committed or uncertain. Definitive validation rejection returns to review, with a new attempt key on edits but the same cart identity. At most one completed Sale per cart forever. Cancellation before submission retains a minimal audit and produces no stock/payment. An uncertain cart cannot be cancelled/abandoned until its original result is resolved. Ordinary abandoned drafts may expire after eight hours only when no possibly submitted attempt exists; retained command identities do not expire.

## Shift and cash drawer reconciliation (Core)

One logical register identifies a physical drawer/location. Owner configures registers; each OPEN shift belongs to one cashier and register. Partial unique constraints allow at most one open/closing shift per register and one per cashier. No shared cashier accounts or concurrent shared drawer in Core; handover closes one shift then opens another. Multiple cashiers use distinct registers.

States: OPEN → CLOSING → CLOSED. Start requires counted opening float, actor/time/register and an idempotent opening command. Owner controls register assignment; float is cash custody, not revenue. No stock mutation occurs. Non-cash sales still require an open shift for attribution.

CashEvent is append-only: OPENING_FLOAT, SALE_CASH (net applied amount), REFUND_CASH, PAID_IN, PAID_OUT and handover references. Normal sale cash event is in the sale transaction. Manual paid-in/out requires owner authorization/reason/reference; it is not an expense journal and never contributes to gross profit. No direct expected-balance edits or negative expected cash; investigate discrepancies instead.

Expected closing cash = opening float + cash sale totals − executed cash refunds + authorized paid-in − authorized paid-out. Tendered cash minus change equals the SALE_CASH amount; do not count tender and sale again. Non-cash totals are separately shown and excluded from drawer cash. Unexecuted refund instructions do not reduce expected cash.

Closing begins under the shift guard, blocking new sales/cash operations. Resolve every committed/uncertain attempt, then cashier submits a blind count (expected cash hidden until submission). Store immutable count, expected amount, difference, actor/time and notes. Owner reviews nonzero variance with a reason; no automatic balancing entry. Zero-variance close can be finalized by the cashier. Recounts are appended revisions with supersession, never edits to prior counts. Closed shifts never accept backdated transactions; owner emergency close requires reauthentication, reason, unresolved-attempt investigation and explicitly recorded missing count if impossible, labeled **Belum Direkonsiliasi** rather than balanced.

Owner may resume an unfinalized CLOSING shift with reason/version check only if no final close exists. Uncertain close/open requests recover by original key. Cash handover records recipient/reference and accepted counted amount; bank deposits are outside accounting scope and must be represented honestly as authorized drawer outflow if cash leaves.

Completed shift history is immutable. Refund of an old sale belongs to the current executing owner's open register shift, referencing the original sale/shift; never rewrite original closing cash. No open eligible drawer means no cash-refund execution. A refund pending outside execution is not a refund-paid claim. Owner review may occur without physical cash access, but execution must name its actual register/actor.

## Sale command and atomicity

One logical sale has actor/key receipt, cart identity and canonical fingerprint version. Fingerprint includes cart/revision, shift/register, sorted lines/serials, quantity, reviewed prices/settings versions, discounts/reasons, fulfillment attestation, method, applied/tender/change and optional customer reference. Client actor/hash/time/totals are not trusted. Same key with different intent conflicts; a new key for a completed cart cannot create another sale.

READ COMMITTED, one connection/transaction; use ordered guards:
job if any → command receipt → user/role guard → business/policy and referenced master guards → shift/register → cart/existing sale/refund aggregate → locations → product guards → balances → serials → reservation/approval if later.
Skip unused classes. All writers use this order; draft edits never lock products then cart. Shift closure and refund/checkout share the shift guard; settings changes share the policy guard. Commercial aggregate locks precede inventory locks. Receipt replay rechecks current read permissions.

Within checkout:
1. Claim idempotency, validate actor, cart version and OPEN owned shift; reject recovery-epoch mismatch.
2. Lock/read current products, source availability, identities, prices and enabled payment policy; confirm recorded fulfillment. Call the inventory command service with the existing transaction/context, never an internal HTTP call or nested independent commit.
3. Validate cash tender ≥ total, change = tender − total, applied = total. Full payment only; non-cash requires exact total and verified method reference. Do not store card PAN/CVV or sensitive payment credentials.
4. Insert immutable Sale/SaleLines, unique sale number, PaymentRecord, CashEvent for cash, receipt identity/content snapshot, and linked RevenueEvents. A GOODS basket produces one linked SALE ISSUE movement with line allocations. SERVICE-only sale has no movement.
5. Inventory service posts signed GOODS legs, balances, positions, health/episodes/inbox/outbox and audit. All mandatory sale/payment/shift/audit facts and SaleCommandReceipt result are committed together; any failure rolls everything back.
6. Mark cart completed in the same transaction; COMMIT then respond with number/time/document and result. Receipt rendering/reprint, push and financial valuation run afterward.

Every GOODS SaleLine quantity must equal its committed issue allocation; serial identity must match. Service lines cannot reference movement legs. Enforce unique sale/cart, sale/issue source and scoped line allocations plus deferred cross-table validation. One sale may create no more than one initial movement. Standalone inventory CommandReceipt remains 1:1 with movement; POS receipt belongs to the commercial aggregate and references the composed movement, without a second independently executable stock command.

No external payment API/printing/network wait in a DB transaction. External cash/transfer is physically outside the database: if money was handed over but commit fails or is unknown, retain the attempt, verify status, return/hold money using the operating SOP. Do not imply database rollback returned physical cash. A recorded non-cash method remains a record, not automated provider settlement.

## Recovery and concurrency

| Scenario | Required behavior |
| --- | --- |
| Repeated quantity scans | Each complete token adds one draft base unit; serials deduplicate under 12 |
| Unknown alias | Keep for search; no automatic product or stock creation; cashier cannot register it |
| Stock shortage / two final-unit cashiers | Revalidate locked balances; one sale succeeds, other fails atomically and returns to review |
| Duplicate submit / double-click | Same key/cart produces same result; no duplicate charge record or stock |
| Browser loses response | Freeze key/payload/cart; status/retry original; absence or 5xx is not definitive rollback |
| Login expires / tab closes | Same actor reauthenticates and recovers server cart/receipt; owner can inspect; no cross-account replay |
| Shift closes versus checkout | Shared guard order serializes; already-committed sale is in expected cash, subsequent checkout is rejected |
| Printer unavailable | Sale remains complete; retry rendering/print original number; no inventory rollback |
| Price/product changed | Reject stale reviewed revision for re-review; historical completed lines unaffected |
| Restore from old snapshot | Posting paused; recovery epoch invalidates pre-restore submission envelopes. Reconcile real receipts/cash before any new authorized attempt |

Permanent receipt/cart uniqueness prevents normal replay; it cannot reconstruct transactions missing after disaster restore. 09 defines the recovery fence and evidence-based reconciliation.

## Receipt and document contract

Core document is **Struk Penjualan**: thermal (58/80 mm after device tests) and A4 rendering of the same paid sale, with print-to-PDF via browser. A4 copy may be titled **Bukti Penjualan Lunas**; it is not an independently numbered invoice, receivable or tax invoice. Formal legal/tax requirements remain Q01's pre-pilot check; do not claim regulatory equivalence.

Persist snapshot in checkout, even if PDF/HTML rendering later fails: sale number, UTC/WIB date/time, cashier identity, business name/logo asset revision/address/contact/header/footer, currency, goods/service type and lines, quantities/units, unit prices, authorized discount amounts, subtotal/net/tax if approved, total, method, tender/change for cash, optional minimal buyer name/reference and original/refund references. No costs/margins/supplier details. Source numbering is server-generated with unique constraint (e.g. S-YYYYMMDD-sequence), gaps permitted, never browser counters; opaque ID is the primary key.

Reprints use the original snapshot, stored template version and retained logo asset, same sale number and **Salinan** label. Current refund status is an explicitly dated linked annotation, not rewriting the original sale. Audit print requests with actor/time/copy count; browser print dialog cannot prove physical print. No public predictable receipt URLs; authenticated access, no-store and authorized exports. No automatic email/message delivery.

Default retail buyer is optional and minimal; mandatory institutional/tax identity requires confirmed purpose/access/retention before activation. No ID-card data, phone or address is collected merely to sell a pen.

## Refunds and physical returns

Only owner may authorize/execute Core refunds with fresh authentication and reason. Original completed sale/payment/revenue remain immutable; display PARTIALLY_REFUNDED/REFUNDED from linked events. Draft cancellation is not a completed-sale void. Incorrect completed sale uses traceable refund/return and, if appropriate, a new sale.

RefundCommand locks current shift if cash, original Sale and remaining line allocations before inventory guards. Full or partial quantity credit uses original discounted unit allocation; cumulative refunded quantity/value cannot exceed original eligible amount, serial cannot return twice, final portion consumes rounding residual. A pure price concession is a bounded amount-only credit and consumes remaining refundable value; it restores no stock/cost. Goods/service tax credit rules require configured confirmed tax policy.

Commercial return and refund may be combined in one atomic command or recorded separately with source links. Track credited quantities/amounts and physically returned quantities independently so neither duplicates the other. Verified saleable goods return uses RECEIPT/RETURN with original sale-line/issue, actual location, reused serial and historical financial allocation. Refund without return creates no goods ledger and reverses no goods COGS. Service refund never creates a movement and does not erase incurred service costs.

Damaged/non-saleable returns cannot be received into sellable stock under Core. If custody of such returns is required, implement quarantine/non-sellable disposition under a scope decision before accepting them into platform stock. A cash refund can still be recorded without restock. This limitation must be visible in owner workflow/SOP.

Money refund record, cash event if executed, credit/revenue correction, optional GOODS return, cost-source reference, audit and command result commit together. A money refund requires actual execution attestation; a pending intention is a draft. Physical cash/provider action cannot be rolled back by software; interrupted execution follows evidence-based recovery, never automatic repeated payout. Non-cash refunds remain disabled until an explicit manual verification SOP exists. Do not use inventory reversal to evade commercial limits.

