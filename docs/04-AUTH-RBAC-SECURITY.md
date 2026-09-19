# 04 — Authentication, permissions and security

Authorize every backend access. [02](02-ARCHITECTURE.md) owns public projections; [06](06-INVENTORY-SPEC.md) posting/audit atomicity; [09](09-DEPLOYMENT-OPS.md) secret operations and retention.

## Authentication and accounts

Use self-hosted Better Auth with PostgreSQL/Drizzle, library password hashing and database sessions. Do not invent authentication or cryptography. No public signup. Owner invitations are expiring, single-use. Verify the pinned library/adapter configuration, signup disablement, reset and 2FA during P01.

Session cookies: HttpOnly, Secure on HTTPS, SameSite=Lax, host-only. Disable auth cookie caching so revocation is checked promptly. Initial absolute session lifetime is eight hours; no extension beyond that limit or shared-device remember-me. Every command checks current session and active user. References: [session management](https://better-auth.com/docs/concepts/session-management), [email/password](https://better-auth.com/docs/authentication/email-password).

Owner TOTP is mandatory before go-live; keep recovery codes offline. Use [library 2FA](https://better-auth.com/docs/plugins/2fa). Require authentication refreshed within five minutes for role/security changes, account disablement and owner stock corrections. Staff may use TOTP without a phone prompt on every scan. Map the entire auth UI to Bahasa Indonesia.

Bootstrap once from an authorized operator's trusted terminal with hidden input. Disable bootstrap after first owner creation. No public bootstrap endpoint, fixed demo credentials, command-history password or secret in Git. Owner-generated staff invitation/reset links follow identity verification, private manual delivery and a 30-minute expiry. Hash application-managed tokens and exclude them from logs. SMTP is optional. Credentials are never displayed again.

Lost-owner recovery uses offline codes; emergency host recovery requires verified ownership, audit, old-session revocation and 2FA reenrollment. Password minimum 12 characters, support managers/paste, enforce the library's safe maximum and never silently truncate. Rate-limit login/reset by account and trusted source without account enumeration. Generic UI: **Email atau kata sandi tidak sesuai.** Staff MUST NOT share an account.

## Permission matrix

Fixed permission sets; explicit owner assignment may combine roles. Default deny. SUPER_ADMIN cannot bypass inventory invariants. L means later, not a control that should appear before implementation.

| Permission/action | SUPER_ADMIN | INVENTORY_ADMIN | PRODUCT_SALES_ADMIN |
| --- | :---: | :---: | :---: |
| users.manage, roles.assign, security.manage | Yes | — | — |
| products.readInternal, excluding private costs/suppliers | Yes | Yes | Yes |
| products.writeContent, including permitted product drafts | Yes | — | Yes |
| products.createInventoryIdentity | Yes | — | — |
| products.archive, stockPolicy.manage | Yes | — | — |
| products.publish (L) | Yes | — | — |
| publicContent.manage, publicContent.preview, publicContent.publish (L) | Yes | — | — |
| publicSettings.manage, including wa.me destination/templates (L) | Yes | — | — |
| locations.manage | Yes | — | — |
| inventory.read, serial.read positions/history | Yes | Yes | — |
| availability.read aggregate | Yes | Yes | Yes |
| inventory.receive/issue/transfer | Yes | Yes | — |
| barcode.resolve/print, serial.register | Yes | Yes | — |
| barcode.manageAliases, serial.correctIdentity | Yes | — | — |
| inventory.opening/adjust/reverse | Yes | — | — |
| inventory.exportHistory without costs | Yes | Yes | — |
| products.importPrepare | Yes | — | Yes |
| products.importApply | Yes | — | — |
| inventory.openingImportPrepare | Yes | Yes | — |
| inventory.openingImportApply, onboardingFreeze.manage | Yes | — | — |
| products.export safe master fields | Yes | Yes | Yes |
| costEvidence.read/write/verify (Core) | Yes | — | — |
| finance.readProfit/export, valuation.publish (L) | Yes | — | — |
| audit.readAll, ops.read, ownerDashboard.read | Yes | — | — |
| notification.readOwn/readOwnState, push.manageOwn | Yes | Yes | Yes |
| sales.manage RFQ/leads/quotations (L) | Yes | — | Yes |
| reservation.request/releaseOwn, sales scope (L) | Yes | — | Yes |
| correction.request, opname.count (L) | Yes | Yes | — |
| approval.decide, opname.approve (L) | Yes | — | — |

Core inventory roles cover all active company locations. No partial tenant/warehouse scoping. If scoped access is introduced later, apply it to queries, barcode lookup, exports, commands and jobs.

Product admin prepares bulk product data; owner applies inventory identity/minimum as a batch. Inventory staff prepare counts/opening; owner finalizes. This does not require approval for routine receipt/issue. Product content permission does not grant tracking/unit edits, publication or stock mutation.

Staff stock history may show relevant operators but not the full security audit. Sales staff may see authorized selling prices, never acquisition cost, COGS, margin or profit, including hidden API fields. A finance role requires an explicit later decision.

Exclude costs from stock/product DTOs, autocomplete, labels, public metadata/cache, generic audit before/after, staff exports and import errors. Financial audit is private; general audit may say **Bukti biaya diperbarui** with actor/reference and no amounts. Revenue is private too. A public RFQ confirmation cannot read the sales ledger.

Import jobs are readable by an authorized preparer or owner, not arbitrary colleagues. Prepare permission never implies apply. Recheck executor status/permissions when the worker applies; do not borrow an unrestricted owner service account. Files/errors are private with authenticated, expiring download access. Reject unexpected financial columns without copying their sensitive values into broadly visible error output.

Only SUPER_ADMIN manages/publishes site content, product publication and wa.me settings. Preview is authenticated, no-store/noindex; public requests cannot select draft revision IDs. Editing drafts never changes published content until publish. Validate and sanitize structured text/links/media; reject executable HTML and arbitrary redirects.

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
