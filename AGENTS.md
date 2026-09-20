# Agent rules — Retail Operations Platform

Applies throughout this repository and to all executors. The repository is the source of truth; explicit owner instructions take precedence. The 20 September 2026 major retail replanning supersedes conflicting earlier product/phase/brand assumptions through D36–D45. D01–D35 remain historical decisions, with effective status in docs/10-DECISIONS.md. Never silently change safety invariants.

## Recover context

1. Inspect git status --short and git branch --show-current. Preserve local user work. main is the default canonical branch; do not return to the historical planning branch.
2. Read this file, docs/00-CURRENT-STATE.md and the active/next row in docs/11-BUILD-PLAN.md. Select one task and verify dependencies and policy gates before editing.
3. Search with rg before broad reading. README.md maps canonical ownership; 17 owns POS.
4. This is a planning-only checkpoint. Do not resume old CP01, scaffold production or restart Antigravity automatically. Old P00.1/CP01 is unapproved and superseded as a visual direction, not deleted.
5. Next eligible future execution is R01.1 / RV01. Follow 16 and actual current state. R01.2 requires Q01–Q03 disposition. Production requires approved RV01–RV05 and explicit owner implementation authorization.

## Product and safety

- Core is retail goods/services: master → receive → scan/search → basket → payment/receipt → atomic goods issue → restock → owner gross reporting.
- Canonical docs are English. All UI/errors/reports/human audit/notifications are Bahasa Indonesia.
- Product commercialType GOODS/SERVICE is separate from goods trackingMode. Services never have stock balances, serial stock, reorder alerts or ledger legs.
- Immutable ledger is quantity truth. No arbitrary balance edits, negative stock, deleted history, silent SQL repair or offline financial/stock mutation.
- All stock writers use 06 command services, one PostgreSQL transaction, backend authorization, ordered locks, idempotency and audit.
- POS completion includes sale, payment record, receipt snapshot, revenue fact and goods issue in one transaction under 17. Printer/push/valuation work happens after commit and never recreates a sale.
- A scan edits a draft. Show success only after confirmed commit; preserve original identity during uncertainty.
- Use SUPER_ADMIN, OPERATIONS_ADMIN, CASHIER and the explicit matrix in 04. Staff never receive owner role merely to do routine work. Check every API/action/job/object.
- Costs/COGS/margin/profit are owner-only by default, including DTOs, exports, errors and audit. Unknown is not zero. Laba Kotor is not Laba Bersih.
- Single-business deployment with runtime configurable identity; no hardcoded LATANSA palette/name/domain/code namespace as universal identity. Preserve official assets without redesigning them.
- Goods attention uses episodes in 13; read state never clears stock problems.
- Thousands of SKUs are normal. 14 requires typed create-only master import, validate/preview/confirm and atomic file apply. Opening is separate and ledger-based.
- Public features are later: allowlist projections of the same master, structured DB content, owner publication, configured wa.me only. A click proves no conversation/sale.
- Prefer mature open-source/self-hosted software and existing infrastructure. Extra paid services require explicit approval; cost savings cannot weaken recovery/security.
- No secrets, real customer data, dumps or production configuration in Git. .env.example has empty values only. No fallback secrets/fixed credentials/host-specific source paths.

## Visual and implementation gates

Classify tasks [BE], [FE] or [FS]. Major new interactions: requirements → isolated HTML/CSS with safe demo data → browser verification → owner Gate A → production integration → real-data technical/browser checks → owner Gate B.

Approved revision is the visual contract. Reuse its patterns directly; significant deviations revise it. RV01–RV05 replace old CP bundles for Core. Prototypes access no production API/database. Tests/screenshots are never owner approval. FE/FS stays [V] until the applicable explicit revision approval; BE may complete after technical verification. Stop at each required visual gate.

Premium means clear hierarchy, fast operation, restrained styling and useful density. Verify desktop/mobile/keyboard, independent semantic colors, contrast, consistent Lucide icons, and each element/action/fact's purpose. No replacement logos or generic decorative dashboard.

## Execution and handoff

- One active build task and at most one [~]; no unrelated refactoring or speculative ERP expansion.
- Lifecycle [ ] → [~] → [T] → [V] if visual → [x]. [!] is an actual blocker with an exact unblock condition.
- Q01–Q03 in 10 are pending business policies, not inferred approval. Stop affected work; independent prerequisites may proceed only when authorized.
- Run targeted tests and relevant 08 gates. Never claim an unrun test passed.
- Stop/report contradictory specs, required invariant changes, missing required credentials, destructive migrations, unapproved paid dependency or material scope change.
- Never push, merge, publish, deploy, message third parties or buy services without authorization.
- Preserve user work and Git history. Keep one canonical owner per topic, link rather than duplicate.
- Update actual task/evidence in 11, real decisions in 10, and 00 LAST. Planning completion is not implementation completion.
- Final reports are concise, with real checks/limits and no sensitive payloads or long transcripts.
