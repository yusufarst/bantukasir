# 10 — Architecture decisions and assumptions

## Effective baseline and historical reading rule

**Plan 3.1 owner acceptance: APPROVED — 20 September 2026.** The owner explicitly said “setuju Plan 3.1”. D46–D53 and the final policy register below govern the accepted unified Order baseline. R00.1 / RV01 is next eligible and remains unstarted; no visual Gate A/B or production authorization is implied.

The D01–D45 decisions, assumptions, gap analysis and policy register below preserve earlier checkpoints. Their original status/“current”/“pending” wording and Pxx/Rxx references are historical where superseded. Read them with the effective overrides at the end; they are not active task dependencies or requests to approve CP01. No historical decision is erased.

Initial baseline: **19 September 2026**. Refinement: **20 September 2026**. LOCKED means the design is binding until explicitly replaced. On 20 September 2026, the owner explicitly approved **D01–D34** in the focused brand-refinement request: “The owner now APPROVES architecture decisions D01–D34.” This approves their effective decisions and recorded supersessions; it does not revive D17's old denominator or approve any unbuilt prototype.

The same request designated the supplied logo official and required a pastel direction. D26's previous exact colors were provisional; D35 records their replacement under that instruction. The new token values are documented design choices awaiting CP01 visual review, not an invented owner approval of a rendered interface. Other approved decisions are unchanged.

Preserve decision history. A replacement names the prior ID, reason and affected specification/tasks. Do not reopen accepted decisions without a concrete requirement.

## Baseline decisions

| ID / status | Decision and reason | Tradeoff | Canonical source |
| --- | --- | --- | --- |
| D01 / LOCKED | Modular monolith, one PostgreSQL, requested stack and same-codebase worker | Microservices/external queues add unjustified operating cost | [02](02-ARCHITECTURE.md) |
| D02 / LOCKED | Append-only ledger and synchronous transactional balance projection | Repeated full sums are costly; asynchronous stock projection cannot authorize issue safely | [06](06-INVENTORY-SPEC.md) |
| D03 / LOCKED | READ COMMITTED, ordered product guards and durable receipts | Same-SKU throughput deliberately serialized; SERIALIZABLE would still require retry | [06](06-INVENTORY-SPEC.md) |
| D04 / LOCKED | One base unit/SKU, numeric(18,3), precision 0–3, serial integer, no Core pack conversion | Supports fractional cable without hidden scan multipliers | [06](06-INVENTORY-SPEC.md) |
| D05 / LOCKED | Basic serial identity in Core; QC/warranty/service later | Avoid later identity/ledger redesign without building full service lifecycle early | [03](03-DOMAIN-MODEL.md) |
| D06 / LOCKED | Browser scan-session → review → atomic commit; vendor-neutral HID and internal Code 128 | Per-beep posting and proprietary middleware rejected; camera/offline writes not Core prerequisites | [12](12-BARCODE-SCANNER.md) |
| D07 / LOCKED | Low stock uses aggregate eligible STORAGE availability and inclusive minimum | Physical totals can hide reserved/transit stock; per-location alert policy deferred | [13](13-NOTIFICATIONS.md) |
| D08 / LOCKED | Attention episode lasts until NORMAL; LOW/OUT once each | Partial recovery/read status never resets deduplication; no periodic same-state spam | [13](13-NOTIFICATIONS.md) |
| D09 / LOCKED | Durable inbox, opt-in Web Push and transactional outbox; at-least-once external delivery | Provider/device dependency remains; no paid notification service required | [13](13-NOTIFICATIONS.md) |
| D10 / LOCKED | Self-hosted Better Auth, DB sessions, fixed roles/permissions, owner TOTP, no public signup | Verify pinned library/adapter/security during P01; no custom cryptography or paid auth | [04](04-AUTH-RBAC-SECURITY.md) |
| D11 / LOCKED | Audit/security/health from ledger inception; backup/restore before pilot | Public site and advanced reporting cannot delay Core integrity | [11](11-BUILD-PLAN.md) |
| D12 / LOCKED | Direct transfer and minimum owner correction in Core; one full reversal/original | Routine staff operations need no approval; flexible approval and partial reversal deferred | [06](06-INVENTORY-SPEC.md) |
| D13 / LOCKED | Public allowlist projection of one Product Master; exact stock private | No duplicate public master or full-entity serialization with UI hiding | [02](02-ARCHITECTURE.md) |
| D14 / REFINED BY D27 | Standalone HTML as visual contract with owner prototype and implementation gates | Technical checks/screenshots do not replace owner approval | [05](05-DESIGN-SYSTEM.md) |
| D15 / LOCKED | Server UTC posting, WIB business dates, no physical-ledger backdating | Separate document/evidenced financial dates preserve actual posting order | [06](06-INVENTORY-SPEC.md) |
| D16 / LOCKED | Encrypted six-hour off-host backups, measured restore, no automatic ledger/receipt deletion | Same-VPS copies insufficient; stricter loss tolerance requires PITR decision | [09](09-DEPLOYMENT-OPS.md) |
| D17 / SUPERSEDED BY D31 | Baseline progress denominator was 55 tasks /30 Core | Retained as history only; current denominator belongs to 11 | [11](11-BUILD-PLAN.md) |
| D18 / EXTENDED BY D22/D20 | Scanner (12) and notifications (13) have separate canonical ownership | Avoid one oversized inventory specification or duplicated algorithms; import/finance now have 14/15 | [README](../README.md) |
| D19 / LOCKED | Later opname freezes locations; reservation is separate from physical ledger; transit uses dispatch/receipt postings | Rolling counts and reservation quantity edits rejected initially | [06](06-INVENTORY-SPEC.md) |

## Refinement decisions

| ID / status | Decision and reason | Tradeoff / effect | Canonical source |
| --- | --- | --- | --- |
| D20 / LOCKED | MWA per SKU across locations for interchangeable quantity and serialized goods; actual serial acquisition retained as evidence | FIFO adds unsupported layer complexity; specific identification reserved for future truly non-interchangeable/custom scope. Capture private evidence in Core; valuation after Sales | [15](15-FINANCE-PROFITABILITY.md) |
| D21 / LOCKED | First metric is Laba Kotor Penjualan Barang with matched accepted revenue/COGS; owner-only cost/profit permissions | No net-profit or whole-company claim; unknown evidence blocks complete metrics, not physical posting | [15](15-FINANCE-PROFITABILITY.md), [04](04-AUTH-RBAC-SECURITY.md) |
| D22 / LOCKED | Core XLSX/UTF-8 CSV, create-only products, one atomic business commit/file, 5,000-row cap | Any invalid/existing SKU blocks file. Staging batches are allowed; silent upsert/skip/partial business commits are not | [14](14-BULK-IMPORT.md) |
| D23 / LOCKED | Product import never touches stock. Quantity/serial opening jobs use the ledger, matching cutover freeze and durable receipts | Authorized opening worker alone has 5,000-line cap; interactive commands remain 200. Independent scope splitting only | [14](14-BULK-IMPORT.md) |
| D24 / LOCKED | Internal brand area is a single sidebar toggle; public logo is a home link | Avoid ambiguous dual actions. Desktop rail uses official mark; mobile uses a drawer | [05](05-DESIGN-SYSTEM.md) |
| D25 / LOCKED | Mobile-first task hierarchy and deliberately productive desktop layouts | Phone monitoring differs from desktop bulk review; mandatory viewport/keyboard/touch checks | [05](05-DESIGN-SYSTEM.md) |
| D26 / OWNER-APPROVED; PALETTE REFINED BY D35 | Burgundy-family identity, Source Sans 3, Lucide and restrained tokens/motion | Original #74263D/#5E1E31/#F7EDF0 were provisional before the official asset. D35 replaces those colors with the logo-derived pastel system; typography/icons/motion and no-redesign rule remain | [05](05-DESIGN-SYSTEM.md) |
| D27 / LOCKED | Five canonical HTML bundles, CP01–CP04 before production and CP05 later; reuse approved patterns directly | Refines D14 without removing either owner gate. Significant new workflows revise their CP; no second full frontend codebase | [05](05-DESIGN-SYSTEM.md) |
| D28 / LOCKED | Each complete repeated quantity token adds one; no timing-based duplicate modal; serials hard-deduplicate | Replaces baseline rapid-identical-scan interruption. Visible counts, undo, final review and real hardware tests protect accuracy | [12](12-BARCODE-SCANNER.md) |
| D29 / LOCKED | Server search/filter/pagination, PostgreSQL-native indexes first, limited useful bulk actions | No whole-master browser fetch, speculative mass edits or external search/queue service without measured need | [02](02-ARCHITECTURE.md), [14](14-BULK-IMPORT.md) |
| D30 / LOCKED | Attention-first cockpit and mandatory element/action/fact audit | Current stock separate from period finance/operations; no duplicate KPI/chart/list facts, fake charts or decorative UI | [01](01-PRD.md), [05](05-DESIGN-SYSTEM.md) |
| D31 / LOCKED | Current plan: 65 tasks, 34 Core, 13 phases; documentation adds no implementation credit | Replaces D17; consolidates prototypes and adds import/evidence/camera/finance dependencies. Version 2.1 explicitly covers CMS/wa.me without changing count | [11](11-BUILD-PLAN.md) |
| D32 / LOCKED | First-party database-managed structured public content, draft/preview/publish, owner-only publication/settings, published wa.me configuration | Supersedes prior PRODUCT_SALES_ADMIN publication permission; product draft preparation remains. No paid CMS, raw HTML editor, WhatsApp API or content-only redeploy. Click is not conversation/sale | [02](02-ARCHITECTURE.md), [04](04-AUTH-RBAC-SECURITY.md) |
| D33 / LOCKED | Existing VPS/domain, configurable origins/storage, local storage adapter and independent encrypted backup | No hardcoded VPS credentials/paths/ports or assumed internal hostname. Paid storage only if needed/approved; reliable off-host backup remains mandatory | [09](09-DEPLOYMENT-OPS.md) |
| D34 / LOCKED | Canonical documentation English; user-facing LATANSA UI Bahasa Indonesia | Latest owner instruction replaces the baseline Indonesian documentation language; technical identities remain unchanged | [AGENTS](../AGENTS.md), [05](05-DESIGN-SYSTEM.md) |
| D35 / DOCUMENTED; VISUAL REVIEW PENDING | Refine D26 using the unchanged official wine-burgundy/rose mark: neutral-heavy pastel surfaces, dark text, muted wine CTA and independent semantic/focus colors | Replaces provisional exact palette only. Internal 80/15/5 and public 70/20/10 are composition guidelines, not quotas. No metallic UI or logo edits. Computed pair contrast passes; CP01 still requires actual-size/accessibility and owner Gate A review. No task completion credit | [05](05-DESIGN-SYSTEM.md), [asset manifest](../assets/brand/README.md) |

## Assumptions and validation

| ID | Assumption / current state | Validate or change before |
| --- | --- | --- |
| A01 | One business, one owner and two staff; both staff may be inventory admins | Document review; assign actual roles without elevating staff to owner |
| A02 | One initial site may grow; inventory roles access all company locations | Pilot; design end-to-end scope authorization first if needed |
| A03 | No required batch/expiry/consignment tracking established | Importing affected stock; revise identity/ledger before use if needed |
| A04 | Base units only, cable up to three decimals, no pack conversion | Master creation |
| A05 | No negative stock/backdated physical posting; corrections reflect verified facts | Owner review and operating SOP |
| A06 | Manufacturer serial unique per product after trim/uppercase | Serial import; revise normalization first if truly case-sensitive |
| A07 | HID Enter devices; 50×30 mm is only an initial label test | P04/P06 actual scanner/printer tests |
| A08 | Owner device supports acceptable push or owner explicitly accepts the actual channel limitation | P05 setup and P06.5 HTTPS/closed-tab acceptance |
| A09 | Six-hour RPO/four-hour RTO acceptable; independent recovery storage available | Pilot; owner accepts measured result or require stronger backup |
| A10 | Sensitive corrections are infrequent enough for owner execution | Pilot; inspect UX/process causes before broadening permissions |
| A11 | 5,000 SKUs/20,000 serials/100,000 ledger legs are benchmark data, not company facts | Performance tests and actual scale review |
| A12 / UPDATED | Client already owns a VPS and current business domain (see README). Actual host access, internal hostname, backup destination, printer and WhatsApp number remain unset | Relevant deployment/public/device phases; never request or store secrets in public docs |
| A13 | Initial financial coverage is interchangeable goods in IDR; custom manufacturing/services need later policy | Costing eligibility and P11 activation |
| A14 / UPDATED | Official colored mark received, reviewed and preserved unchanged; no separate monochrome, wordmark or banner received | Use original on white for CP01; test small-size rail legibility. Obtain an official small-use export only if necessary; no generated substitute |
| A15 | One atomic 5,000-row job fits bounded target resources | P02/P03 measured admission tests; explicitly revise caps if it does not |
| A16 | Source Sans 3/Lucide are selected; actual assets/license copies are not packaged yet | CP01/package verification |
| A17 | Optional first-party WhatsApp click tracking is sufficient; no chat/sale outcome integration required | P09 inquiry design |

No unanswered question blocks this focused documentation checkpoint. Architecture approval is recorded. Actual physical-data, permissions, recovery and financial-scope assumptions still require operational validation. Pending prototype visual review, small-size asset checks and host evidence are not implementation completion.

## Reference and version policy

Primary references are linked near their claims in canonical documents: PostgreSQL locking/isolation/constraints/backup, Next.js auth, Better Auth sessions/2FA, MDN barcode/push, OWASP file upload, WAI disclosure, Source Sans/Lucide licenses and IAS 2/IFRS 15 concepts. These support specific mechanisms, not a vendor endorsement of LATANSA's architecture.

Dependencies are not installed/pinned yet. P01 verifies current compatible stable versions/advisories and commits a lockfile. Major upgrades require risk review and a replacement decision if semantics change.

## Major retail replanning — 20 September 2026

This section is the effective override of conflicting historical rows above. D01–D35 and A01–A17 above are preserved as the prior baseline, including their original approval wording; that wording does not approve the replacement design or any prototype. The owner explicitly authorized this major replanning. Decisions below are architect-selected under that authority; unresolved business policies are listed separately and are not owner-approved by silence.

### Gap analysis completed before revision

| Area | Repository evidence / gap | Replacement and impact |
| --- | --- | --- |
| Actual state | main has local edits to 00/11/prototype registry, untracked CP01, scratch images and skills; no package.json, app or migrations found | Preserve all assets/code; reconcile contradictory “no prototypes” claims; no runtime completion credit |
| Product | 01 prioritizes warehouse stock and excludes checkout | Retail operations Core includes POS, goods/services, receipt and finance; public acquisition moves later |
| Sales integrity | 06 is strong but 03/07 defer revenue to RFQ/deal acceptance; no sale/payment/receipt aggregate | Add canonical 17; one sale command composes inventory inside the same transaction; durable cart deduplication |
| Services | 15 excludes installation/services; ServiceCase means repair custody | Product commercialType GOODS/SERVICE; SERVICE has no stock; completed-service sales and explicit direct-cost evidence |
| Roles | 04 separates inventory from product/sales admins | Replace with SUPER_ADMIN, OPERATIONS_ADMIN, CASHIER; operations can maintain catalog/prices and receive goods; costs remain owner-only |
| Finance | P10 sales/P11 valuation; goods-only reporting | Core MWA goods COGS, service direct costs, separate and combined completeness; no net-profit claim |
| Documents/payment | No normal checkout, tender/change or printer recovery | Immutable sale snapshots; cash recording; same-number thermal/A4 receipt; legal invoice/tax remains a business gate |
| Refunds | D12 full inventory reversal cannot represent partial commercial refunds | Preserve standalone reversal; add sale-linked bounded partial return/refund commands; no arbitrary sale movement reversal |
| Brand | 05 and CP01 hardwire LATANSA rose/burgundy; LT- internal barcode namespace | Single-company runtime BusinessProfile; neutral tokens; new RP- namespace, preserve existing issued identities |
| Stock attention | 13 already has durable episodes; owner-only recipients | Reuse algorithm; include authorized operations admins; services excluded |
| Import | 14 supports goods-only creation and bans selling prices | Version 2 typed goods/service master with selling price, unchanged create-only/atomic opening guarantees |
| Recovery/cost | Existing self-hosted stack and six-hour dump design viable, but lost retail cash/sales need explicit recovery | Cost audit in 09; sale/payment/document reconciliation, recovery epoch; owner RPO acceptance before pilot |
| UI/build | Old four Core bundles omit POS and real finance; 65/34 count no longer describes need | Five new RV bundles; plan 3.0, 46 tasks/36 Core; no carryover approval |
| Prototype evidence | CP01 REVIEW says INITIAL_DRAFT, CSS says V4; source uses different tokens, missing WOFF2 files, screenshots have variants | Historical evidence only; no inferred exact-revision approval or fresh browser/accessibility claim |

### Replacement decisions

| ID | Replaces / preserves | Chosen decision, alternative and impact |
| --- | --- | --- |
| D36 | Supersedes inventory-first sequencing in D11/D30/D31 and old 01/11; preserves D02/D03 | Retail operations Core: master → receive → POS/payment/receipt/issue → restock → owner reporting. Reject merely appending POS after public/RFQ. 01/07/11 define scope/order |
| D37 | Replaces D10 role semantics, D32 product-admin name and A01 staffing assumption; auth controls retained | Three fixed roles from 04, permission sets beneath them; operations owns routine catalog/prices/barcodes/stock, cashier owns POS only, private finance remains owner-only. No dynamic permission builder |
| D38 | Extends D05/D13/D22; replaces goods-only A13 | One Product Master with GOODS/SERVICE and goods-only inventory profile. Reject fake service stock and separate duplicate catalogs. Repair ServiceCase stays later; typed import v2 in 14 |
| D39 | Extends D02/D03/D06/D12/D15 | Atomic sale/payment record/document/revenue fact/GOODS issue/audit/attention/receipt; service-only sale has zero movement. Full standalone reversal retained; bounded partial commercial refunds and returns use 17. Reject stock outbox eventual consistency and separate inventory re-entry |
| D40 | Replaces old later-sales document assumptions | Recorded full payment and immediate handover/completed service baseline; cash first, no provider/capture API. Immutable retail receipt plus A4 rendering selected. Formal tax invoice, tax policy and staged work require Q01/Q02 disposition before affected implementation |
| D41 | Supersedes D20 timing, D21 goods-only scope, A13 and old P11; preserves MWA rationale and privacy | MWA per interchangeable goods SKU in Core; services use verified sale-line direct costs. Publish separate/combined gross results only with complete data. Reject guessed service cost and net-profit label. 15 owns policy |
| D42 | Supersedes universal brand requirements in D24/D26/D35, A14's prerequisite and D33's fixed client-domain implication | Runtime singleton BusinessProfile, identity snapshots on receipts; neutral design and independent semantics. Preserve official LATANSA asset without universal use. Reject multi-tenant SaaS complexity. 02/05/09 |
| D43 | Refines D06 internal namespace only; D28 scan behavior retained | New RP-P/RP-I codes, permanent legacy-code compatibility if actually issued; aliases unchanged, never rewrite identity due to rebranding. 12 |
| D44 | Supersedes D27 bundles, D31 denominator/phase order and old executor designation; retains D14/D25 gates | Plan 3.0: 46 tasks, 36 Core, 10 phases, RV01–RV05 before production. CP01 unapproved historical artifact, no completion credit. Next eligible prototype R01.1 only in a future authorized execution session |
| D45 | Refines D16/D33 operations, extends D18 ownership | Near-zero recurring software cost with cost audit; encrypted off-host recovery still mandatory. RPO ≤6h/RTO ≤4h remains proposed pilot target requiring retail-loss acceptance. Recovery epoch prevents blind stale replay. Add 17 as sole POS owner |

Unaffected semantics remain binding: D01, D02–D04, D07–D09, D19, D23, D25, D28–D29 and D34. D05 still retains basic serial goods in Core; its later “service” means repair lifecycle only. D13 safe public projection and D32 structured CMS/publication/wa.me remain later; operations replaces the former product draft preparer. D17 is historical, D18 is extended by 17, D22 retains create-only atomic import with the v2 field contract. D24 retains the internal navigation-toggle interaction with configurable identity. D30 retains attention-first hierarchy while period finance is now Core. Every D01–D35 row is accounted for here or in the replacement table.

### Historical material owner policy register — superseded where stated

The table below preserves the policy state and old task references from the interrupted Astra replanning. The final Plan 3.1 policy register supplies the effective gates. Q02 and Q03 are resolved by D46–D51; historical R01.2 POS references below are not current dependencies.

| ID | Proposed baseline / unresolved business meaning | Exact gate |
| --- | --- | --- |
| Q01 | Retail paid receipt and A4 copy, optional customer name/reference, no formal/tax invoice claim. Confirm mandatory buyer identity, tax registration/treatment, inclusive/exclusive pricing and any required legal invoice | Before R01.2 POS contract; no tax-enabled/legal-invoice production without confirmed policy and revised scope |
| Q02 | Only immediate goods handover and already-completed services paid in full. Owner verifies actual direct service costs; initial report is gross profit. Confirm deposits, staged work, credit sales or true net profit requirements | Before R01.2 and R06.2; if required, replan recognition/expenses instead of treating cash as revenue |
| Q03 | Owner-only refunds with reauthentication/reason, proportional bounded line credits, no cashier discounts. Cash drawer opening/closing/count variance deferred; payment totals are not drawer reconciliation | Before R01.2; if shifts/other refund authority required, add scope and regenerate counts |
| Q04 | Six-hour potential data loss and four-hour recovery, independent encrypted destination and real recovery access | Before R07.2/pilot; choose WAL/PITR if unacceptable, never silently accept loss |
| Q05 | Base units, no packs/batches/expiry/consignment; actual serial normalization and interchangeable costing | Before affected master/opening import; do not ingest unsupported stock |

Q01–Q03 were surfaced to the owner during replanning. Q04–Q05 are operational acceptance gates, not reasons to buy software or request secrets in Git. Existing A02–A12/A15–A17 remain technical assumptions where consistent with plan 3.0; old Pxx deadlines are historical, replaced by the new build dependencies. A08 push capability and A09 recovery acceptance are still required before pilot.


## Owner-approved unified order flow — 20 September 2026

The owner explicitly approved the unified flow covering instant POS plus DP, booking, staged goods fulfillment, later payment and service progress. The following decisions supersede conflicting immediate/full-payment-only assumptions in D36–D45 without erasing history.

The complete Plan 3.1 baseline was explicitly accepted on **20 September 2026**: **“setuju Plan 3.1”**. This records planning acceptance only. No RV prototype or production implementation is approved by this statement.

| ID | Decision |
| --- | --- |
| D46 / LOCKED | One Order engine is the commercial source of truth. Instant POS is a fast path through Order, not a separate Sale truth. |
| D47 / LOCKED | Payment history is append-only and independent from order, goods and service status. DP/partial/final payments are Core. |
| D48 / LOCKED | InventoryReservation and partial GoodsFulfillment are Core. Reservation reduces available only; fulfillment posts physical ISSUE. Payment alone never moves stock. |
| D49 / LOCKED | Scheduled SERVICE work is Core through ServiceJob, milestone/progress and verified completion. SERVICE itself has no stock. Repair/customer-custody service remains later. |
| D50 / LOCKED | Management finance separates order value, payments collected, outstanding balance, recognized revenue, HPP/direct service cost and gross profit. GOODS revenue follows fulfillment; SERVICE revenue follows verified completion. |
| D51 / LOCKED | Cashier shift reconciliation is Core: opening float, cash events, blind closing count, variance and immutable history. |
| D52 / LOCKED | UX uses progressive disclosure: ordinary cashier path stays Scan/Search → Keranjang → Bayar → Struk; Pesanan/DP reveals deferred-order complexity only when needed. |
| D53 / LOCKED | Plan 3.1 replaces D44's incomplete plan-3.0 count: 47 tasks / 39 Core across R00–R09, with RV01–RV05 interleaved before the relevant production UI rather than frontloading the entire frontend. |

### Policy register after owner flow approval

- Q01 remains OPEN: confirm formal tax/legal invoice needs, tax treatment and mandatory buyer identity before pilot and before enabling that behavior. The accepted receipt/A4 and payment-evidence baseline can be designed without claiming legal/tax equivalence. Q01 does not block R00.1.
- Q02 is RESOLVED by D46–D50: deposits, staged work and later settlement are required; gross-profit reporting remains the accepted initial profit scope.
- Q03 is RESOLVED by D51: cashier shift reconciliation is required in Core; refund execution remains owner-only by default.
- Q04 remains a pre-pilot recovery acceptance gate.
- Q05 remains a pre-import inventory-assumption validation gate.

D40's immediate/full-payment baseline is superseded by D46–D50. D44's 46/36 draft count is superseded by D53. All unaffected inventory/security/cost/backup decisions continue to apply.

Effective reading of related historical details: D36's flow now includes deferred Orders; D37's cashier-only POS boundary yields to the current three-role duties in 04 (operations may record authorized payments using their own shift for cash). D39's composed sale transaction applies only to the instant Order fast path; deferred payment, goods fulfillment and service completion remain independent commands. D41's cost evidence and privacy remain, with OrderLine/fulfillment/completion references under 15/17 rather than separate Sale truth. D19's reservation timing is superseded by D48; staged transit/opname remain later. D27/D44's frontloaded prototypes and old task numbers are superseded by D53 and the interleaved tracker in 11.
