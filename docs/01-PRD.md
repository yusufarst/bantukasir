# 01 — Product requirements

This document owns business outcomes, scope, priorities and dashboard information hierarchy. Technical mechanisms belong to the documents in the [index](../README.md).

## Business outcome and users

CV. Latansa Jogjakarta needs trustworthy stock, locations, movement history and timely attention. Staff perform daily operations. The owner monitors exceptions and approves sensitive changes.

**Success: two staff members can scan and post concurrently without losing integrity, while the owner trusts current stock and receives useful alerts without spam.**

| Initial user | Responsibility | Required outcome |
| --- | --- | --- |
| Owner, SUPER_ADMIN | Monitoring, policy, sensitive corrections, accounts, public publication | Clear actions and traceable decisions |
| Warehouse staff, INVENTORY_ADMIN | Receipt, issue, transfer | Fast scanning, editable drafts, reliable confirmation |
| Product/sales admin, PRODUCT_SALES_ADMIN | Product content and later sales | Appropriate data without stock mutation or cost access |

Initially both staff may be warehouse operators. Assign roles by actual work. The owner may explicitly combine roles; account count does not dictate a role split.

## Core MVP

- Individual accounts, secure sessions, authorization, account administration and recovery.
- Product Master for thousands of SKUs, server search/filter/pagination, categories, brands, units, tracking mode and stock thresholds; multiple warehouses/locations.
- XLSX/CSV product creation import, authorized export and bulk labels.
- Quantity inventory **and basic serialized identity from the start**: receipt, issue, location and history. QC, warranty and service follow later.
- Immutable ledger, synchronous balances, opening, receipt, issue, direct atomic transfer and owner correction/reversal.
- Separate quantity/serial opening imports, count reference, cutover freeze and atomic ledger posting under [14](14-BULK-IMPORT.md).
- Owner-only acquisition cost evidence from opening/receipt. Missing cost remains explicit and does not block warehouse posting. This is data preparation, not a profit report; see [15](15-FINANCE-PROFITABILITY.md).
- Internal barcodes, unambiguous manufacturer aliases, browser label printing, USB/Bluetooth HID, manual entry and scan → review → confirmation.
- Normal/low/out stock states, attention episodes, owner inbox, opt-in Web Push and a factual operational cockpit.
- Audit from the first transaction, traceable history and authorized export.
- Bahasa Indonesia UI, mobile-first layouts and productive desktop workflows, four Core prototype bundles and two visual review gates.
- Encrypted off-host backup, verified restore, basic health and a controlled pilot before operational stock use.

Core excludes the public site/catalog, checkout, full accounting, valuation/COGS/profit calculation, complex purchasing, reservation, staged transit, guided stock opname, general approval engine, QC, warranty/service, camera scanning, offline mutation, multiple companies/currencies, paid SaaS and WhatsApp APIs. Camera is a post-Core enhancement. Recording cost evidence does not mean profitability is implemented.

Direct transfer and minimum correction are Core because multiple locations and recording mistakes occur from day one. Normal operations MUST NOT require owner approval for every transaction.

## Business acceptance

| ID | Requirement | Evidence |
| --- | --- | --- |
| B01 | Staff operate independently | Authorized staff complete a scan session without owner intervention |
| B02 | Trustworthy balances | Every change traces to ledger, actor, time, location and reason/reference |
| B03 | Safe concurrency | Two users competing for the final unit cannot create negative or duplicate stock |
| B04 | Proactive attention | Minimum 5: 6→5 alerts once; 5→4 does not repeat; 3→20 resolves; 20→5 opens a new episode |
| B05 | Serialized identity | One item cannot leave twice or occupy two locations |
| B06 | Actionable owner cockpit | Current stock, period operations and exceptions come from real queries |
| B07 | Honest network recovery | Unknown outcomes can recover without duplicate posting |
| B08 | Recoverable operations | Restore reconciles ledger, balances, serials and episodes |
| B09 | Public repository safety | No secrets/operational data in Git; no private fields in public projections |
| B10 | Practical onboarding | Validate and preview 5,000 rows without overwriting products or setting stock fields |
| B11 | Financial truth | Match revenue and COGS; unknown is not zero; gross profit is never labeled net profit |
| B12 | Useful phone and desktop | Responsive hierarchy, drawer/rail, keyboard and touch controls pass 05 |

[08](08-TESTING-ACCEPTANCE.md) owns technical acceptance and performance targets. Targets are not measured results.

## Owner cockpit

Primary question: **what needs attention?** The dashboard is not a welcome page or a repeated shortcut menu. Canonical priority:

1. Real critical exceptions: inventory integrity, stopped operations or critical backup failure. Blocking approvals join this level after the module exists. Hide the section when empty.
2. Current inventory attention: one actionable list, OUT before LOW. Show state counts once in its heading/filter, without duplicate cards or charts.
3. Financial summary when supported: revenue, COGS, gross profit and gross margin in one compact group. This section is absent in Core.
4. Period operations: receipts/issues with document drill-down.
5. Noncritical approvals requiring a decision, once implemented.
6. Up to five meaningful recent documents with actor and traceability.
7. Trends only when they answer a distinct question with sufficient data. Normal system health is not a permanent dashboard card.

Stock rows show name/SKU, available quantity/unit, minimum, condition age and location/history drill-down. Within each state, sort by available/minimum ratio, oldest episode, then SKU. Do not divide OUT rows by a zero threshold. Reading the inbox does not remove unresolved stock attention. When all stock is normal, show one concise line.

| Information | Definition | Single primary placement |
| --- | --- | --- |
| Low/out stock | Mutually exclusive counts of actively monitored products under 13 | Attention list filter/heading |
| Available/location | Same inventory read model used by staff, with snapshot time | Row/detail; never sum unrelated units |
| Receipts/issues | Count RECEIPT/ISSUE documents within postedAt business-time range; exclude opening, transfer, adjustment and reversal | Flat operations strip and document drill-down |
| Reversed original | Still part of its original document count, clearly linked to reversal | Document detail/list |
| Active SKU count | Product count, not item quantity | Products page, absent from default cockpit |
| Profit | Verified eligible revenue/COGS under 15 | One financial group |
| System state | Actionable incidents | Critical exception area; full details under settings |

One **Periode** selector governs finance and period operations: **Hari Ini**, **7 Hari**, **Bulan Ini**, **Bulan Lalu**, **Rentang Tanggal**. Core defaults to today; finance-enabled cockpit defaults to the current month. Operation labels follow the selected period. Current stock attention, reservations and pending approvals remain current state and MUST NOT be filtered by that selector.

Phone layouts preserve this order. Desktop adds useful details/context without promoting secondary information above urgency. No repeated product/stock action shortcuts already present in navigation.

Distinguish loading, inactive feature, valid empty state, incomplete data, error and stale snapshot. A failed query is not zero. Stale data may be read with its timestamp, but cannot authorize mutation. Prototype finance uses explicit **Data Demo** labels and never enters production. A subset report cannot claim whole-company or net profit.

## Public website and later capabilities

After reliable Core: reservation/transit/opname/approval, QC/warranty/service, a corporate B2B/B2G website and catalog, RFQ/leads/quotations/sales, then valuation/gross profit and broader reporting. Independent later work may be reordered through the official [plan](11-BUILD-PLAN.md).

The website uses one Product Master and owner-controlled publication. Company information, hero/banner, About, contacts/hours/address, section/footer copy, featured products, product descriptions/images/specifications, inquiry copy and SEO must be editable through a lightweight first-party CMS. Routine changes require no developer, Git commit or redeploy. Safe structured fields, draft/preview/publish and audit are required. No paid external CMS or unrestricted HTML editor.

WhatsApp uses owner-configured wa.me destination and message templates. A click event measures an outbound click only. It does not prove a conversation or sale. Architecture and publishing behavior are owned by [02](02-ARCHITECTURE.md).

## Business assumptions

One company; UI locale id-ID; business days Asia/Jakarta; no negative stock or physical-ledger backdating; one base unit per SKU; no MVP pack conversion. Batch/expiry/consignment needs have not been established and must be resolved before importing affected stock. Numbered assumptions belong to [10](10-DECISIONS.md).
