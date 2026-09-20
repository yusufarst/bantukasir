# Agent rules â€” Retail Operations Platform

The repository is the sole source of truth. Explicit owner instructions supersede conflicting older assumptions only when recorded in canonical documentation.

## Recover context first

Before substantial work:
1. inspect `git status --short` and `git branch --show-current`;
2. read this file and `docs/00-CURRENT-STATE.md`;
3. read the active/next task in `docs/11-BUILD-PLAN.md`;
4. read `docs/01-PRD.md`, `docs/02-ARCHITECTURE.md`, `docs/10-DECISIONS.md`;
5. read task-specific canonical docs.

Preserve local user work. Never silently reset/delete unrelated files.

## Effective product model

Core is a brand-neutral retail operations system for GOODS and SERVICES. A single **Order** model supports instant POS, booking, DP/partial payments, later settlement, goods reservation, partial fulfillment, mixed goods/services and scheduled service jobs with milestones/progress.

Instant POS is a fast path through the same Order engine, not a separate commercial system.

Primary roles:
- `SUPER_ADMIN` â€” Pemilik;
- `OPERATIONS_ADMIN` â€” Admin Operasional;
- `CASHIER` â€” Kasir.

## Non-negotiable invariants

- Immutable stock-movement ledger; no direct balance editing.
- `available = onHand - reserved`; reservation never changes physical onHand.
- Payment/DP never causes stock OUT by itself.
- GOODS stock leaves only on verified fulfillment/issue.
- SERVICES never create fake stock balances or ledger legs.
- Payments are append-only; old payments are never overwritten.
- Order, payment, goods fulfillment and service progress states are separate.
- Cash received is not automatically revenue; revenue is not automatically cash received.
- Missing cost is not zero. Gross profit is never labeled net profit.
- Returns/refunds/corrections preserve original history.
- Critical writes use DB transactions, backend RBAC, idempotency and audit.
- No negative stock where prohibited; serialized identity cannot duplicate or issue twice.
- A scan edits a draft; a beep never means stock changed.
- Public/staff DTOs never leak private cost, margin, serial, audit/security or internal warehouse data.

## UX rule

Keep complexity in the system.

Cashier default: `Scan/Search â†’ Keranjang â†’ Bayar â†’ Struk`. Reveal **Pesanan / DP** fields only when needed.

Operations default answers: **apa yang harus dikerjakan hari ini?**

Owner default answers: **apa yang perlu perhatian dan bagaimana bisnis berjalan?**

Use progressive disclosure, useful defaults, keyboard/scanner efficiency on desktop, touch efficiency on mobile and Bahasa Indonesia labels. Users do not need to understand ledger, allocations or revenue-event terminology.

## Branding

Do not hardcode LATANSA as product identity. Runtime `BusinessProfile` owns name, logo, contact/document identity and constrained accent configuration. Semantic success/warning/danger/focus colors remain independent from brand colors. Preserve existing LATANSA assets as first-client/historical assets only.

## Cost policy

Target recurring software/SaaS cost is approximately Rp0:
- existing VPS + Docker Compose/Caddy;
- PostgreSQL for DB/search/jobs;
- self-hosted Better Auth;
- browser print/print-to-PDF;
- open-source barcode generation;
- Web Push + in-app inbox.

Do not add paid auth/database/queue/search/analytics/CMS/payment/notification SaaS without explicit approval. Reliable off-VPS backup remains mandatory; if no suitable existing destination exists, document the cost instead of weakening recovery.

## Development workflow

Implement vertical slices: database â†’ validation â†’ backend/service â†’ RBAC â†’ audit â†’ UI â†’ tests â†’ browser/device verification.

Major new interactions require a visual prototype Gate A before their production UI and Gate B after real integration. Prototypes are interleaved with the relevant slice; do not build the entire frontend first.

One active task at a time. Avoid unrelated refactoring or speculative ERP expansion. Never push, merge, deploy, publish or buy services without explicit authorization.

## Completion

A task is not complete merely because UI renders. Relevant migrations, validation, RBAC, audit, tests, lint, typecheck, production build, browser/mobile/device checks and docs must pass.

Update `docs/11-BUILD-PLAN.md` for status/evidence, `docs/10-DECISIONS.md` for real architecture decisions, and `docs/00-CURRENT-STATE.md` last after substantial work.
