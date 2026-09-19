# 10 — Architecture decisions and assumptions

Initial baseline: **19 September 2026**. Refinement: **20 September 2026**. LOCKED means the design is binding for subsequent agents until explicitly replaced; it does **not** claim owner document/visual approval. Owner review is pending.

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
| D26 / LOCKED | Burgundy, Source Sans 3, Lucide and restrained tokens/motion | Replaces earlier blue assumption; candidate colors require asset/contrast review; no logo redesign or default shadcn identity | [05](05-DESIGN-SYSTEM.md) |
| D27 / LOCKED | Five canonical HTML bundles, CP01–CP04 before production and CP05 later; reuse approved patterns directly | Refines D14 without removing either owner gate. Significant new workflows revise their CP; no second full frontend codebase | [05](05-DESIGN-SYSTEM.md) |
| D28 / LOCKED | Each complete repeated quantity token adds one; no timing-based duplicate modal; serials hard-deduplicate | Replaces baseline rapid-identical-scan interruption. Visible counts, undo, final review and real hardware tests protect accuracy | [12](12-BARCODE-SCANNER.md) |
| D29 / LOCKED | Server search/filter/pagination, PostgreSQL-native indexes first, limited useful bulk actions | No whole-master browser fetch, speculative mass edits or external search/queue service without measured need | [02](02-ARCHITECTURE.md), [14](14-BULK-IMPORT.md) |
| D30 / LOCKED | Attention-first cockpit and mandatory element/action/fact audit | Current stock separate from period finance/operations; no duplicate KPI/chart/list facts, fake charts or decorative UI | [01](01-PRD.md), [05](05-DESIGN-SYSTEM.md) |
| D31 / LOCKED | Current plan: 65 tasks, 34 Core, 13 phases; documentation adds no implementation credit | Replaces D17; consolidates prototypes and adds import/evidence/camera/finance dependencies. Version 2.1 explicitly covers CMS/wa.me without changing count | [11](11-BUILD-PLAN.md) |
| D32 / LOCKED | First-party database-managed structured public content, draft/preview/publish, owner-only publication/settings, published wa.me configuration | Supersedes prior PRODUCT_SALES_ADMIN publication permission; product draft preparation remains. No paid CMS, raw HTML editor, WhatsApp API or content-only redeploy. Click is not conversation/sale | [02](02-ARCHITECTURE.md), [04](04-AUTH-RBAC-SECURITY.md) |
| D33 / LOCKED | Existing VPS/domain, configurable origins/storage, local storage adapter and independent encrypted backup | No hardcoded VPS credentials/paths/ports or assumed internal hostname. Paid storage only if needed/approved; reliable off-host backup remains mandatory | [09](09-DEPLOYMENT-OPS.md) |
| D34 / LOCKED | Canonical documentation English; user-facing LATANSA UI Bahasa Indonesia | Latest owner instruction replaces the baseline Indonesian documentation language; technical identities remain unchanged | [AGENTS](../AGENTS.md), [05](05-DESIGN-SYSTEM.md) |

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
| A14 | Official colored/monochrome logos and legible rail mark have not been supplied here | CP01 owner approval; do not generate substitutes |
| A15 | One atomic 5,000-row job fits bounded target resources | P02/P03 measured admission tests; explicitly revise caps if it does not |
| A16 | Source Sans 3/Lucide are selected; actual assets/license copies are not packaged yet | CP01/package verification |
| A17 | Optional first-party WhatsApp click tracking is sufficient; no chat/sale outcome integration required | P09 inquiry design |

No unanswered question blocks this documentation checkpoint. Actual physical-data, permissions, recovery and financial-scope assumptions MUST be validated before operational use. Pending owner/asset/host evidence is not implementation completion.

## Reference and version policy

Primary references are linked near their claims in canonical documents: PostgreSQL locking/isolation/constraints/backup, Next.js auth, Better Auth sessions/2FA, MDN barcode/push, OWASP file upload, WAI disclosure, Source Sans/Lucide licenses and IAS 2/IFRS 15 concepts. These support specific mechanisms, not a vendor endorsement of LATANSA's architecture.

Dependencies are not installed/pinned yet. P01 verifies current compatible stable versions/advisories and commits a lockfile. Major upgrades require risk review and a replacement decision if semantics change.
