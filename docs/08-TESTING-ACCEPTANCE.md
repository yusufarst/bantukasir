# 08 — Acceptance and Definition of Done

Specifications only; no runtime tests passed in this documentation task. Use Vitest/unit, real PostgreSQL integration and Playwright/browser tests where relevant; mocks cannot prove DB races/constraints. Test changes against populated isolated non-production migrations. No production destructive test/reset.

| Suite | Measurable acceptance |
| --- | --- |
| AUTH01 | API RBAC rejects guessed routes/IDs; disable/revoke prevents new writes; reset/invite expires and is single-use |
| AUTH02 | All staff search/history/errors/receipt/report/PDF/CSV/cache payloads omit private HPP/profit/cost history; receiving write-only cost input is not echoed |
| SEARCH01 | Name/SKU/category/brand and exact manufacturer/internal barcode work; leading zeros preserved; aliases globally unique under concurrent create |
| SEARCH02 | Server pagination capped at 100, stable order, no full-master fetch; receiving selects existing product; similar matches shown before create |
| INV01 | Every physical delta has movement/source/actor/time; ledger sum equals balance; receipt/opening duplicate request posts once |
| INV02 | Manual issue requires non-sale reason; two final-unit issues/sales cannot oversell; failure injection rolls back ledger/balance/cost/audit/result |
| INV03 | Owner reversal preserves original; duplicate reversal/negative outcome blocked; SALE_ISSUE cannot be independently reversed |
| POS01 | Exactly one Sale/full Payment/issue/receipt per source intent; rollback tested at every boundary; same/different-key duplicate submissions cannot duplicate |
| POS02 | Rp75,000 total, Rp100,000 tender → Rp25,000 change/Rp75,000 applied; insufficient cash rejected; transfer full amount/reference and human confirmation |
| POS03 | Lost response/status/reload retrieves original; disabled methods/price tampering/stale prices rejected; printer failure/reprint never duplicates facts |
| RET01 | Cumulative refund/return bounded under races; cash/transfer refunds recorded separately; refund without return adds no stock; saleable return once at original cost |
| STOCK01 | Minimum 5: 6 NORMAL, 5 LOW, 0 OUT; threshold edits and receipt refresh list/badge consistently; failed query is not empty/zero |
| FIN01 | MWA example in 15 produces Rp36,000 HPP/Rp24,000 gross; fractional/final-stock rounding and manual issue valuation correct |
| FIN02 | Unknown cost suppresses complete HPP/profit; explicit evidenced zero differs from blank; evidence revision replays downstream without rewriting original |
| FIN03 | Refund-only retains HPP; return recovers historical cost once; failed replay publishes nothing; reports respect consistent snapshot and date boundaries |
| DAY01 | Own-scope only including file URLs; transfer excluded from expected cash; Rp100,000 cash + Rp50,000 transfer, Rp98,000 physical → -Rp2,000 variance |
| DAY02 | Racing Sale before finalize invalidates old count; finalize first rejects Sale; no missing successful Sale; both payment methods use same day guard |
| DAY03 | Duplicate finalization incl. different keys yields one snapshot; conflict cannot overwrite; snapshot/audit failure rolls back finalization |
| DAY04 | Midnight/timezone/late prior-day finalization never mixes dates; after finalization same-day Sale blocked; original uncertain Sale still recoverable |
| DAY05 | PDF/CSV/reprint produces no financial mutation; formula-injection text safe; printer/export failure retries original snapshot; owner correction note preserves count |
| AUDIT01 | Owner filters sales/payments/stock/product-price/user/config by date/user/type/product/reference; staff denied audit administration |
| DB01 | Populated migration rehearsal + reconciliation pass, reviewed forward changes, exact destructive-operation prohibition enforced in scripts/runbook |
| REC01 | Isolated real restore reconciles Sale/Payment/receipt/ledger/cost/report/source IDs/audit/assets; original snapshots reproduce |
| REC02 | Recovery epoch rejects stale browser posting; post-backup possibly lost sales reviewed from evidence, never blind replay; measured G4 RPO/RTO |
| UX01 | Approved tokens reused; loading/empty/error/unknown/stale/denied/success; desktop/mobile/200% zoom/keyboard/focus/reduced motion |
| DEVICE01 | Actual USB HID, Bluetooth HID, manual input, repeated scans/CRLF/focus/disconnect and actual printed labels/receipt work; no simulated claim |
| RELEASE01 | Gate A/B evidence, no unresolved integrity/privacy/recovery blocker, G1–G4 disposition, owner pilot acceptance |

Initial non-production benchmark: 5,000 products, 100,000 movement lines, 2 concurrent cashiers. Measure on intended VPS/network: exact barcode p95 <=500 ms, product search p95 <=700 ms, 50-line Sale p95 <=2 s, ordinary period report p95 <=2 s. Document dataset/host/run count and investigate misses; do not weaken locks to pass. These are targets, not observed performance. Cost replay capacity measured separately in BK26.

Every applicable task DoD includes migration/constraints, validation, backend RBAC, audit, targeted unit/integration/race/recovery tests, lint/typecheck, production build, affected browser/mobile verification and docs evidence. Device checks at BK20/BK25/BK34; owner Gate B at BK10/BK20/BK25/BK31; final recovery/pilot checks at BK33–BK36. Any unrun check stays pending, not passed. Docs-only replanning requires diff/link/encoding/task-graph/scope checks, no app tests.
