# 14 — Opening stock and deferred import

**Core: manual entry and bounded manual opening. CSV/XLSX import is Later**, deliberately cut to protect the one-month target. No import worker/staging/job framework. G2 must confirm actual catalog size; if manual onboarding is impractical, replan a safe small CSV task before execution and keep the hard task limit/schedule honest.

Owner establishes a documented business cutover and persistent onboarding flag that blocks ordinary stock/Sale posting while initial counts are entered. Do not hold DB locks during human counting. Create products with search-first duplicate checks, then review up to 100 product opening rows per command. Each positive opening has qty/unit precision/cost evidence or explicit UNKNOWN and count reference. Zero count recorded in onboarding checklist, no fake movement.

OPENING only for product without prior ledger, once per product under locks/unique guard. All rows in one submitted command commit atomically with ledger/balance/cost/audit/durable result. Invalid row rejects entire command; duplicate/retry returns original. Distinct commands cover disjoint products; never split one product opening or disguise it as receipt. Owner verifies all counts, barcode/LOW/OUT setup and unresolved cost coverage before releasing onboarding flag.

Normal later new product stock uses RECEIPT. Mistakes use eligible owner correction under [06](06-INVENTORY-SPEC.md), not reset/reimport. Real data only after G2 and authorized pilot cutover; synthetic fixtures remain isolated non-production.

If promoted later, simple UTF-8 CSV should be create-only product import separate from opening, strict headers/text barcode preservation, preview/duplicate errors, size/row caps, per-file atomic posting, idempotency and revalidation. No silent upsert/skip, partial business commits, spreadsheet formulas, unknown-column ignoring or automatic stock from master import. XLSX, enterprise queues and large migration tooling remain unplanned. This paragraph is a future safety boundary, not implementation authorization.
