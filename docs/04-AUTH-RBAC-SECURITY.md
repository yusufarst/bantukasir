# 04 — Authentication, RBAC and security

Backend permission + object scope on every request, including history/status/print/PDF/CSV/media. Hiding UI never grants security. Exactly three fixed roles; no dynamic role builder.

| Capability | SUPER_ADMIN | OPERATIONS_ADMIN | CASHIER |
| --- | --- | --- | --- |
| Search/scan operational product DTO | Yes | Yes | Yes |
| Product/category/master selling price/barcode/labels | Yes | Yes, nonnegative price with audit | No |
| Receive/manual issue/stock/restock/operational history | Yes | Yes | No |
| Opening/adjustment/reversal/sensitive correction | Yes | No | No |
| POS cash/transfer/own history/own daily report | Yes | No | Yes |
| Private acquisition cost/HPP/gross profit | Yes | No | No |
| Refund/return and all-cashier report review | Yes | No | No |
| Users/config/security/audit/recovery | Yes | No | No |

Owner checkout attributes Sale to the owner's own cashier-day, never impersonates another user. Operations has no checkout grant in this minimal baseline.

Receiving requires cost evidence or explicit UNKNOWN. Operations may **submit newly supplied cost evidence** from the incoming document (write-only field); response/history/search/export must not return that value, historical acquisition costs, valuation or HPP. Staff can instead mark “Biaya perlu dilengkapi pemilik”; owner supplies evidence later. This does not grant cost-read permission. Clear submitted sensitive fields after commit; never persist them in browser recovery storage or logs. After reload recover by server command reference, not a stored cost payload. Owner-only correction of cost evidence is audited.

## Authentication

Self-hosted Better Auth after implementation-time compatible-version/security verification. Individual accounts, password hashing through vetted library, secure HttpOnly/Secure cookies, appropriate SameSite, trusted origin/CSRF protection, server sessions, login/reset rate limiting, no public signup. Owner bootstrap is a one-time private operator procedure; no default password/demo user in production. Single-use expiring invite/reset via securely conveyed link needs no paid email service. No token in logs. Disable/password reset/revoke invalidates sessions and privileged writes.

No TOTP/2FA in V1; auth adapter boundary permits later addition without custom cryptography. Sensitive owner refund/user/recovery/config actions require recent authentication.

## Privacy and audit

Allowlist DTOs separately for operational, cashier-document and owner-finance scopes. Staff must not receive purchase cost/evidence history, HPP, margin/profit, unrestricted audit/security or recovery details. No public report URLs/shared sensitive response caches. Cashier own scope comes from authenticated actor, never trusted cashierId.

Audit important successful changes within the business transaction: Sale/payment/stock/product/price/config/user/correction/finalization. Dedicated owner Log/Riwayat filters date/user/type/product/reference. Operations sees safe stock history; cashier sees own Sale/report history only. Never log passwords, tokens, session cookies or private full payloads. Retain historical actor identities and original facts.

Validate environment at startup without displaying secret values. No secrets in Git/screenshots or NEXT_PUBLIC variables. Validate/re-encode logo uploads (PNG/JPEG/WebP, bounded dimensions/size), reject executable/SVG/HTML uploads and arbitrary URL fetching. Private storage, random immutable keys and authorized downloads. [09](09-DEPLOYMENT-OPS.md) owns recovery.
