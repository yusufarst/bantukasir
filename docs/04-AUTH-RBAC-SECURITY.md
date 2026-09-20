# 04 — Authentication, permissions and security

Authorize every backend access. [02](02-ARCHITECTURE.md) owns public projections; [06](06-INVENTORY-SPEC.md) posting/audit atomicity; [09](09-DEPLOYMENT-OPS.md) secret operations and retention.

## Authentication and accounts

Use self-hosted Better Auth with PostgreSQL/Drizzle, library password hashing and database sessions. Do not invent authentication or cryptography. No public signup. Owner invitations are expiring, single-use. Verify the pinned library/adapter configuration, signup disablement, reset and 2FA during R02.

Session cookies: HttpOnly, Secure on HTTPS, SameSite=Lax, host-only. Disable auth cookie caching so revocation is checked promptly. Initial absolute session lifetime is eight hours; no extension beyond that limit or shared-device remember-me. Every command checks current session and active user. References: [session management](https://better-auth.com/docs/concepts/session-management), [email/password](https://better-auth.com/docs/authentication/email-password).

Owner TOTP is mandatory before go-live; keep recovery codes offline. Use [library 2FA](https://better-auth.com/docs/plugins/2fa). Require authentication refreshed within five minutes for role/security changes, account disablement, stock corrections, refunds, cash adjustments/variance review and emergency shift closure. Staff may use TOTP without a phone prompt on every scan. Map the entire auth UI to Bahasa Indonesia.

Bootstrap once from an authorized operator's trusted terminal with hidden input. Disable bootstrap after first owner creation. No public bootstrap endpoint, fixed demo credentials, command-history password or secret in Git. Owner-generated staff invitation/reset links follow identity verification, private manual delivery and a 30-minute expiry. Hash application-managed tokens and exclude them from logs. SMTP is optional. Credentials are never displayed again.

Lost-owner recovery uses offline codes; emergency host recovery requires verified ownership, audit, old-session revocation and 2FA reenrollment. Password minimum 12 characters, support managers/paste, enforce the library's safe maximum and never silently truncate. Rate-limit login/reset by account and trusted source without account enumeration. Generic UI: **Email atau kata sandi tidak sesuai.** Staff MUST NOT share an account.

## Permission matrix

Three fixed roles, default deny, explicit owner role assignment. Combining OPERATIONS_ADMIN and CASHIER is allowed for actual dual duty; never combine owner merely to bypass a missing staff permission. Owner cannot bypass invariants. Core access is single-company/all active locations; later location scoping must apply end to end.

| Action | SUPER_ADMIN / Pemilik | OPERATIONS_ADMIN / Admin Operasional | CASHIER / Kasir |
| --- | --- | --- | --- |
| Accounts, roles, security, business/tax/payment configuration, registers | Yes | No | No |
| Goods/service safe master read | Yes | Yes | POS projection only |
| Catalog create/edit, selling price revisions, category/brand maintenance | Yes | Yes | No |
| Identity/tracking/unit change after use | Forbidden; new identity/correction policy | Forbidden | Forbidden |
| Master archive, locations/units administration | Yes, invariant checks | No | No |
| Barcode register/retire/print, no-stock serial registration | Yes | Yes | Resolve only |
| Manufacturer serial identity correction | Yes, reason | No | No |
| Inventory read/history/non-sale receive/issue/transfer/export | Yes | Yes | Sale availability only |
| Minimum/reorder target/monitoring and stockAttention.read | Yes | Yes, reason/audit | No |
| Opening/freeze/adjust/reverse | Yes, reauthentication | Prepare counts only | No |
| Master import prepare / apply | Yes / Yes | Yes / No | No / No |
| Goods opening import prepare / apply | Yes / Yes | Yes / No | No / No |
| Safe master export | Yes | Yes | No |
| sale.create/checkout, own cart and own sales/reprints | Yes | Only with separately assigned CASHIER | Yes |
| Safe all-sale history/reprints and operational sales totals | Yes | Yes, buyer PII excluded | Own sales only |
| Cashier price override/discount | Owner only, reason/re-auth in own sale | No; catalog edits separate | No |
| refund.execute / saleReturn.post | Yes, reason/re-auth | No | No |
| shift.open/count/close own zero-variance shift | Yes | Only with CASHIER | Yes |
| Shift variance review, emergency close, paid-in/out | Yes, reason/re-auth | No | No |
| shift.read | All | Own only if CASHIER | Own |
| Acquisition/service cost evidence read/write/verify | Yes | No | No |
| Goods HPP, service cost, margin/profit, valuation/report/export | Yes | No | No |
| Security/full audit, health/backup and recovery management | Yes | No | No |
| Own operational inbox/push | Yes | Yes | No stock alerts |
| Public content/settings/publication (later) | Yes | Product draft preparation only | No |
| Advanced reservation/approval/repair/RFQ (later) | Define at phase gate | No implicit new permission | No |

Raw backup/restore is an authorized operator procedure, never arbitrary browser shell execution. Owner may inspect status and authorize controlled recovery. Staff sale projection includes type/SKU/name/unit/selling price and eligible availability only; it excludes cost, suppliers, broad serial/stock history and other cashiers' drafts.

Safe operational reports include sale/refund counts and recorded selling totals, receipt/issue and stock attention. They do not include COGS/profit or buyer contact details. Cashier can only see own shift/payment/sales; closed original-sale shift is never reopened by a refund. Count UI hides expected cash until submission; API must enforce this too.

Exclude private finance from stock/product DTOs, autocomplete, labels, receipts, staff reports/import errors, generic audit and public caches. Owner evidence is a separate endpoint/form. General audit may state **Bukti biaya diperbarui**, without amounts. Private financial audit carries restricted amounts. Do not put purchase cost in a field hidden by CSS.

Import jobs belong to authorized preparer/owner; prepare never implies apply. Workers recheck the confirmed executor's current authority and intent expiry. Private files/errors need authenticated expiring access and redaction, not public URLs.

Later public owner-only draft/preview/publish settings remain under 02. Operations may prepare permitted product drafts, never publish. Public responses use allowlist projections; no buyer, exact stock, serial, location, cost or private sales data.

## Enforcement

1. HTTP adapter validates session cookie, origin/CSRF and structure.
2. Service obtains actor server-side and checks current permission plus object ownership, including inbox, subscription and command receipt.
3. Stock transactions recheck under user guard in the [06](06-INVENTORY-SPEC.md) lock order.
4. Repositories receive validated scope and return allowlisted DTOs. Middleware/layout alone is insufficient.

Workers use limited service actors, not owner passwords. They cannot assign roles or post arbitrary corrections. Preserve at least one active owner through a shared owner-management guard, including concurrent disable/demotion/reset requests.

Core owner corrections are authorized execution with re-authentication and reason, not maker-checker separation. Later staff proposals may receive owner approval. Label owner emergency corrections honestly; never invent a second reviewer.

## Threat controls

| Risk | Required control and verification |
| --- | --- |
| IDOR/role bypass | Negative tests for permissions, other users' IDs and revoked roles |
| CSRF/cross-origin calls | Configured Origin checks, appropriate library/custom CSRF defense, POST mutations, secure cookies |
| XSS/injection | React escaping, constrained/sanitized published content, reviewed CSP, parameterized Drizzle/raw SQL |
| Brute force | Measured rate limits, safe failure audit, no account enumeration; trust forwarded IP only from Caddy |
| Malicious barcode/payload | Length/character/document limits; never execute URLs/HTML or log raw payloads |
| Replay/races | Unique receipts, locks and DB invariants, tested with real PostgreSQL |
| Cache leaks | Internal no-store; no auth/stock service-worker caching; cross-user tests |
| Upload/SSRF | No arbitrary URL fetching; validate type/signature/size; decode/re-encode images; authorized private access |
| Spreadsheet formulas | Sanitize export formula/control prefixes, proper quoting and column allowlists; spreadsheet smoke test |
| Vulnerable dependencies | Lockfile and advisory review during upgrades/releases; build success is not security proof |
| Compromised runtime/DB | Least privilege, private DB network, separate migrator, rotation, encrypted backup and incident procedure |
| CMS/redirect abuse | Owner-only publication, optimistic revisions, safe placeholder allowlist, fixed wa.me origin, no draft leaks |

## Audit

Minimum fields: eventId, schemaVersion, server occurredAt, actorId/serviceActor, internal action, entityType/ID, requestId, reason/reference, safe before/after and outcome. Successful business audit is immutable and mandatory in the same transaction. Reference stock ledger before/after rather than copying large payloads.

Failed login/denied access use a separate, rate-limited security log, not a rolled-back stock transaction. Never log credentials/tokens/raw bodies. Security log sink failure raises an operational alert; required privilege/security audit failure blocks the change.

Human audit text uses versioned Indonesian templates, for example **Petugas Gudang Demo mencatat 2 unit Barang Contoh keluar dari Gudang Utama.** Internal codes are restricted diagnostics. Minimize personal data. Runtime append-only controls are not absolute tamper-proofing against database administrators; off-host backups, separate operational logs and restricted admin access complement them.

## Public repository

Secrets belong only in secure runtime configuration. No fallback values or secrets in NEXT_PUBLIC variables. Missing required values fail the component startup with variable names only. Fixtures/screenshots use demo data. Never commit dumps, real password hashes, push tokens, recovery codes, private host details or customer contacts.

If a secret is committed: stop propagation, revoke/rotate, audit use, then coordinate history cleanup. Deleting the next line/commit alone is insufficient. Never copy the secret into public incident notes.
