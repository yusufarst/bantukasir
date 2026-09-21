# 02 — Architecture

Plan 1.0: modular monolith, one Next.js/TypeScript application and PostgreSQL database. Drizzle migrations, Zod, self-hosted Better Auth, server RBAC, first-party reporting. Verify versions/advisories and pin lockfile in BK06. No required general worker, external queue/search or notification infrastructure.

Modules: identity/access; configuration; catalog/search/barcode; inventory/costing; Sale/payment/documents; daily reconciliation; reports/audit; operations/recovery. Proposed paths: `src/modules/<domain>`, `src/components`, `src/app`, `db`, `tests`, `ops`; not existing code.

## Transaction contract

Critical commands require authenticated authorized actor, validated envelope, source intent ID, stable idempotency key, server fingerprint, DB transaction, audit and durable result. Key scope includes actor + operation. Same key/payload returns original result; changed payload conflicts. Unique source intent prevents a new retry key duplicating a Sale. Recovery rechecks authorization. Retain receipts with business facts.

Use READ COMMITTED and shared lock order: command identity → actor/config guards (stable order) → cashier-day guard when applicable → existing Sale for corrections → product guards sorted by ID → balance/cost projection → append facts/result. Omit irrelevant guards; never reverse order. Disable/config writers use matching guards. Upsert unique cashier-day guard before locking; checkout/finalization share it. No human/printer/network wait inside transactions. Bounded deadlock retries reuse original identity.

Sale transaction creates Sale/lines/full Payment/SALE_ISSUE/balances/cost source and valuation facts/ReceiptSnapshot/audit/result together. Failure at any step rolls back all. No standalone payment endpoint may leave an incomplete Sale. Cash/transfer are manually recorded evidence, not atomic bank settlement. Confirm transfer receipt before posting; uncertain outcome resolves before requesting another transfer.

Inventory commands update immutable movement and synchronous balance/cost projection atomically. [06](06-INVENTORY-SPEC.md), [15](15-FINANCE-PROFITABILITY.md), [17](17-POS-SALES.md) own details.

## Read boundaries

One Product Search handles name/SKU/category/brand/exact barcode with PostgreSQL indexes and bounded server pagination. No full master fetch. Context filters/role DTOs are separate from matching logic; staff payloads/caches exclude costs. LOW/OUT derives from balance/minimumStock, without event delivery.

Daily report snapshot commits with physical count, cutoff/version, finalization, audit and result. Rendering/export reauthorize every request. Owner reports use a consistent DB snapshot and explicit cost completeness.

## Operations

Immutable logo revisions use private configured storage and retained document references. BusinessProfile is runtime data; receipts freeze its revision. Browser thermal/A4/PDF and safe CSV avoid paid services. Compose runs app/PostgreSQL/Caddy plus scheduled backup/reconciliation commands. Private DB, HTTPS, separate runtime/migration/backup credentials. [09](09-DEPLOYMENT-OPS.md) binds all maintenance.
