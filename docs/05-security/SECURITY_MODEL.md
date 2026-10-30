# Security model

| | |
| --- | --- |
| Status | CURRENT — written in RF-3A under D-055, 4 October 2026 (Asia/Jakarta); amended in RF-5 under D-087, 18 October 2026: CAP-15, SEC-CH-05, SEC-UP-05, SEC-OP-01, SEC-OP-03, SEC-OP-04 and the new SEC-OP-05; amended in RF-6 under D-088, 18 October 2026: the operator (question 20), the secrets of section 8 (question 18), SEC-SECRET-04, SEC-LOG-05, SEC-HDR-05, section 14 with the new SEC-OP-06 to SEC-OP-08, the test references and section 16; corrected under D-089, 18 October 2026: the recovery key used only off the VPS (section 8; finding B-1 of the RF-6 review), the settlement of request receipts (CAP-34, SEC-OP-05; finding B-2), and the open item T-23 (section 16); amended under D-096, 19 October 2026: SEC-SECRET-05 (N-09); amended by TASK-006, 26 October 2026: the database enforcement of SEC-LOG-05 and the open item T-23 (sections 9 and 16); amended by TASK-010, 30 October 2026: SEC-CH-02 (the shop's name, initials and accent for signed-in users) |
| Phase | P5 — security, authentication and authorization |
| Replaces | `docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md` and `docs/05-security/legacy/P5-SECURITY.md` (both LEGACY — SUPERSEDED); the security sections of `docs/04-architecture/legacy/P4-ARCHITECTURE.md` ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 8) |
| Decisions | D-007, D-013, D-032, D-033, D-035, D-037, D-039, D-042, D-043, D-047, D-048, D-049, D-051, D-052, D-054, D-056, D-069, D-072, D-073, D-087, D-088, D-089, D-096; [ADR-004](../adr/ADR-004-authentication.md), [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md) |
| Related | [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 5, [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) sections 8 and 9, [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md), [ARCHITECTURE](../04-architecture/ARCHITECTURE.md) |

Who may sign in and how, who may do what, how data is kept from the wrong eyes, and how the application resists attack. This document enforces the product and domain rules; it adds no business meaning. Every rule names its source: a decision `D-…`, a CURRENT document, or a legacy source key from [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 2. Rules are numbered `SEC-<area>-nn`, capabilities `CAP-nn` and threats `THR-nn`; identifiers are permanent.

In this document "Owner" is the user with role SUPER_ADMIN at an installation (DOMAIN_MODEL section 1). "Operator" is a person with technical access to the server who carries out a documented operator procedure at the Owner's request (D-048); an AI agent never acts as operator, on production or on staging, and holds no access to the server (D-088, question 20; [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) item 1).

## 1. Principles

| ID | Rule | Source |
| --- | --- | --- |
| SEC-PR-01 | Authorization is deny by default and enforced on the server for every request. Hiding a control is not authorization. | AGENTS rule 8, D-007, BR-DOM-07 |
| SEC-PR-02 | Authentication (who the user is) and authorization (what the user may do) are separate. The authentication library never defines authorization. | AICWDF §4A.8 |
| SEC-PR-03 | Every output channel follows a per-role allowlist. | D-007, REQ-ACC-04 |
| SEC-PR-04 | No hand-made cryptography for passwords, tokens or sessions; only the framework's vetted mechanisms. | L-04 §Autentikasi |
| SEC-PR-05 | A zero-cost target never justifies weaker security. | COST_POLICY |
| SEC-PR-06 | Every control in this document is proven by a test or a recorded check; configuration alone is not a pass. | L-P5, PROJECT_RULES section 5 |

## 2. Authentication profile

In the form of AICWDF §34A and §4A.4. [ADR-004](../adr/ADR-004-authentication.md) records the decision; this section is the operative profile, and the two must agree.

```text
AUTH PROFILE (AICWDF §34A)
- implementation:               LARAVEL_NATIVE — Laravel Fortify, rendered through Inertia and React (ADR-004)
- password profile:             AICWDF-COMPAT-8 — an Owner-selected usability profile (D-049);
                                not a claim of NIST compliance
- Google sign-in:               OFF — FD-03, approved by D-049
- Google registration behavior: N/A — Google is off; accounts are created only by the Owner;
                                there is no self-registration of any kind

GOOGLE IMPLEMENTATION CONTRACT (AICWDF §4A.4)
- Provider:                     none
- Client ID / Client Secret:    N/A — no Google credentials exist or are needed
- Redirect URI:                 N/A
- Allowed registration:         N/A — Owner-created accounts only
- Account-linking rule:         N/A
- Failure/cancel behavior:      N/A
- Labels:                       not shown — "Lanjutkan dengan Google" does not appear
```

| ID | Element | Rule | Source |
| --- | --- | --- | --- |
| SEC-AUTH-01 | Sign-in identifier | An email address and a password. The email is never verified and the application never sends email; no mail transport is configured in production. | D-049 |
| SEC-AUTH-02 | Account creation | Only the Owner creates staff accounts. There is no registration route. There is no default or demo account, and a production build seeds no account. The first Owner account comes from the bootstrap procedure (section 14). | D-049, D-048, BR-USR-03, L-04 |
| SEC-AUTH-03 | Staff first password and staff reset | A one-time link created by the Owner and handed over directly (section 3). | D-049, FD-04 |
| SEC-AUTH-04 | Owner forgotten password or lockout | The Owner recovery procedure on the server (section 14). | D-048, D-049, FD-04 |
| SEC-AUTH-05 | Password rule | AICWDF-COMPAT-8: at least 8 characters, with an uppercase letter, a lowercase letter, a number and a symbol; at most 128 characters; paste and password managers allowed; no forced periodic rotation; no call to an external breach service. Applied to every password set: first password, reset, own change, Owner recovery. | D-049, AICWDF §4A.5 |
| SEC-AUTH-06 | Password hashing | Argon2id through the framework's hashing driver; parameters benchmarked on the target server; a stored hash is upgraded on sign-in when parameters increase. | D-013, L-P5 |
| SEC-AUTH-07 | Advanced methods | No two-factor authentication, passkey, magic link, one-time code, Google or other social sign-in, or single sign-on in V1. "Remember me" is disabled, because a long-lived remember token would bypass the 12-hour limit. | D-049, D-039 |
| SEC-AUTH-08 | Failures | A failed sign-in gives one uniform message that never reveals whether the email exists, whether the account is deactivated or which field was wrong; attempts are throttled (section 11) and recorded in the security log (section 9). | L-P5, L-04 |
| SEC-AUTH-09 | Account state | Only an ACTIVE account with a usable password can sign in. DEACTIVATED and ANONYMIZED accounts are refused with the same uniform message. | BR-USR-05, D-043 |
| SEC-AUTH-10 | Session start and end | The session identifier is regenerated at sign-in. Signing out invalidates the session, rotates the CSRF token, clears the browser history encryption key and ends with a full page load, so that no page data stays in memory (section 7). Signing in also ends with a full page load. | L-P5, ADR-004 |
| SEC-AUTH-11 | Own password change | Requires the current password; on success the user's other sign-in sessions are revoked. | L-04, SEC-SESS-04 |
| SEC-AUTH-12 | Boundary | Authentication lives in the Access module. Domain records refer to the internal user identity only, never to the email or a provider value. | AICWDF §4A.9 |

## 3. Accounts, the single Owner and one-time links

| ID | Rule | Source |
| --- | --- | --- |
| SEC-ACC-01 | **Exactly one active Owner account.** After bootstrap there is always exactly one ACTIVE SUPER_ADMIN account. A SUPER_ADMIN account is created only by the bootstrap and Owner replacement procedures (section 14), never by any function of the product. SUPER_ADMIN is never combined with another role. Through the product, the Owner cannot change the Owner's own role, deactivate the Owner's own account or anonymize it, and no function of the product reactivates a deactivated SUPER_ADMIN account. The guarantee is: at most one ACTIVE SUPER_ADMIN account (TECHNICAL_RULES RACE-30). It is held by a partial unique index, and the runtime database role cannot change the SUPER_ADMIN role of an existing account ([SCHEMA](../04-architecture/SCHEMA.md) section 5.1). | D-048, D-032, BR-USR-04 |
| SEC-ACC-02 | Staff accounts are created by the Owner with the role CASHIER, OPERATIONS_ADMIN or both (D-032). A new account is ACTIVE but has no usable password until its first-password link is consumed. | D-049, D-032, BR-USR-03 |
| SEC-ACC-03 | **Email uniqueness.** An email address identifies at most one account. The comparison ignores letter case and outer spaces. A new account cannot use an email still held by an existing account, including a deactivated one: the Owner reactivates the old account (D-042) or anonymizes it first (D-043), after which the email is free. | D-049, D-042, D-043 |
| SEC-ACC-04 | **One-time links.** A link has a purpose (FIRST_PASSWORD or RESET) and is created by the Owner for a staff account, or by the bootstrap, recovery or replacement procedure for an Owner account. Its token is random, generated by the framework's password broker, and stored only as a hash. An account has at most one active link; creating a new one replaces the old one. A link expires **60 minutes** after it is created and can be consumed **once**. It is revoked when the Owner revokes it, when a new link replaces it, when it is consumed, when the account's email changes, and when the account is deactivated or anonymized. The link identifies the account by its internal identity, and its token is checked only against that account's current link. Because the framework's token repository keys tokens by email, the link is revoked in the same transaction before an email change, so a token can never match a later account that receives the freed address. | D-049, ADR-004 |
| SEC-ACC-05 | **Handing a link over.** The link is shown once to whoever created it, on screen, for direct handover to the person (how it is displayed — for example copy or QR code — is interface design in RF-4 and RF-5). It is returned only in the body of the `no-store` JSON response to the issuing request and held in the page's memory; it is never put in page props, flashed data, the sign-in session, browser history state or an application URL, so Back or a reload cannot show it again. The application never emails it. It is never kept in browser storage and never written to an application, proxy or web-server log, an audit payload or an error report. The token travels in the part of the URL that browsers do not send to the server (the fragment) and is submitted in a `POST` body, so request-line access logs cannot contain it. The page that consumes it loads no third-party resource, sends no referrer, and removes the fragment from the address bar and the browser history as soon as it has read the token. | D-049, BR-AUD-05, L-04 |
| SEC-ACC-06 | **Consuming a link.** The person sets a password that meets AICWDF-COMPAT-8. Under the account lock (TECHNICAL_RULES RACE-20) the server checks that the token exists, is unexpired and unused, and that the account is ACTIVE and not anonymized; then it sets the password, deletes the token, revokes every sign-in session of the account and writes an audit event, in one transaction. Any failure gives the uniform `LINK_INVALID` result. The person then signs in normally. | D-049, BR-USR-05 |
| SEC-ACC-07 | Expired link tokens are removed by a scheduled housekeeping command. | ADR-002, D-049 |
| SEC-ACC-08 | **Deactivation** revokes all sign-in sessions and links of the account in the same transaction; a command already in flight for that account is refused at validation (TECHNICAL_RULES RACE-04). | BR-USR-05, REQ-ACC-06 |
| SEC-ACC-09 | **Anonymization** (D-043) applies only to a DEACTIVATED staff account, or to a former Owner account deactivated by the Owner replacement procedure (D-048), by the Owner with recent authentication and a reason. It replaces the display name with a neutral, non-personal label derived from the internal identity (wording approved at P7-G2), replaces the email with a unique placeholder under the reserved `.invalid` domain derived from the internal identity, replaces the password hash with an unusable value, and revokes any link and session. The internal identity and every audit relationship stay; no historical record is rewritten (BR-USR-12). The original email becomes free for a new account; a returning person gets a new account (D-043). | D-043, D-049 |
| SEC-ACC-10 | **Own account fields.** Every user may change their own password (SEC-AUTH-11). A staff member's display name and email are changed by the Owner through user management. The Owner may change the Owner's own display name and email, with recent authentication. | D-049, BR-USR-04, DOMAIN_MODEL section 11.1 |

## 4. Sign-in sessions and recent authentication

| ID | Rule | Source |
| --- | --- | --- |
| SEC-SESS-01 | Sign-in sessions use the database session driver (ADR-002). The session cookie is `HttpOnly`, `Secure`, `SameSite=Lax`, host-only and scoped to the application path. Session data is encrypted at rest. The session identifier is regenerated at sign-in and when the user's privileges change. | D-005, D-013, L-P5 |
| SEC-SESS-02 | A sign-in session ends after **30 minutes** without user activity and **12 hours** after sign-in at most. Both limits are enforced by application middleware from two times stored in the session: the sign-in time and the time of the last user-initiated request. Requests the client sends without a user action — the stock attention refresh (BR-INV-41) and automatic repeats of a resolution — are recognized by the server and never update that time; a background marker sent by the client can only make a request not count. The framework's own idle lifetime is also set to 30 minutes, but because every request refreshes it, the middleware, not the framework, enforces the limit (ADR-004). The client applies the same two limits with its own timers (SEC-CH-02): when one is reached, it clears the history key and the application's browser storage (except unresolved command references, SEC-CH-05) and loads the sign-in page with a full page load. While a user is typing in a form, the client may send a user-activity request at most once a minute; unlike a background request, it counts as user activity. A test proves that a page with the stock attention refresh running still ends at 30 minutes. | D-039, D-013, BR-USR-06 |
| SEC-SESS-03 | Signing out, or a sign-in session ending, never closes, transfers or otherwise changes a cash session. | D-039, BR-USR-06, BR-CASH-11 |
| SEC-SESS-04 | Sign-in sessions are revoked — removed from the session store, so no old permission survives — on: sign-out (that session); own password change (the user's other sessions); link consumption, deactivation, role change and anonymization (all sessions of the account); Owner recovery and Owner replacement (all sessions of the affected Owner account); a restore from backup (all sessions of every account, ADR-007). | BR-USR-04, BR-USR-05, BR-CMD-06 |
| SEC-SESS-05 | **Recent authentication** is a password confirmation within the last **15 minutes** (the framework's password-confirmation timeout set to 900 seconds). It is required for every capability marked "yes" in the recent-authentication column of section 6. It belongs to the sign-in session and ends with it. | D-013, BR-USR-07 |
| SEC-SESS-06 | A user may be signed in on more than one device. Their commands are serialized by the locks of TECHNICAL_RULES section 7; a cash session still has one responsible user (D-009). | D-009, TECHNICAL_RULES |

## 5. Authorization model

| ID | Rule | Source |
| --- | --- | --- |
| SEC-AZ-01 | Every route requires an authenticated, ACTIVE account, except: the sign-in page and its submission, the one-time link page and its submission, a health check that reveals nothing, and static assets. Every authenticated route declares the capability it needs; a route without a declared capability is refused, and a route-inventory test proves that none exists. | AGENTS rule 8, AICWDF §14.2 |
| SEC-AZ-02 | A capability is granted by role (section 6). A combined account has exactly the union of the CASHIER and OPERATIONS_ADMIN capabilities and never an Owner-only capability. SUPER_ADMIN is held alone. | D-032, BR-USR-02 |
| SEC-AZ-03 | **Object scope.** CASHIER reaches only the Sales, receipts, cash sessions and session reports for which it is the actor or the responsible user. OPERATIONS_ADMIN reaches every receiving document the role can access, operational stock history and the catalogue, and nothing of the till, cash sessions or finance. The Owner reaches everything. A request for an object outside the actor's scope gets the same response as for an object that does not exist (TECHNICAL_RULES TR-ERR-03). | D-033, BR-SALE-03, BR-DOC-06, BR-CASH-21, BR-AUD-04 |
| SEC-AZ-04 | **In-transaction guard.** Inside the command's transaction, under the actor lock, the server checks again that the actor's account is ACTIVE and still holds the role the command needs, so a deactivation or role change that wins the race takes effect (TECHNICAL_RULES RACE-04). | BR-USR-05, REQ-ACC-09, L-04 |
| SEC-AZ-05 | The actor comes only from the sign-in session. A user identity, role, cashier or session owner sent by the browser is never trusted. | BR-DOM-06, L-04 |
| SEC-AZ-06 | Returning an original command result, a status or a document checks the current authorization and scope again; knowing an identifier, a number or a URL is never permission. | BR-CMD-03, BR-DOC-06 |
| SEC-AZ-07 | The interface shows only what the matrix allows, and the server refuses everything else directly; both are tested. | AICWDF §16 |
| SEC-AZ-08 | The Owner never acts as another cashier at the till; the Owner sells in the Owner's own cash session. | BR-CASH-08, BR-SALE-02 |

## 6. Permission matrix

Y = allowed; N = refused by the server; "own" = only objects within the actor's own scope (SEC-AZ-03). A **combined account** has every Y of the OPERATIONS_ADMIN and CASHIER columns and nothing else. "Recent auth" = SEC-SESS-05 required. This matrix agrees with PRODUCT_BLUEPRINT section 5; where a later decision changed a legacy rule, the decision is cited.

| CAP | Capability | Owner | OPERATIONS_ADMIN | CASHIER | Recent auth | Source |
| --- | --- | --- | --- | --- | --- | --- |
| CAP-01 | Sign in and out; view own account; change own password | Y | Y | Y | No | D-049, L-04 |
| CAP-02 | Product Search and scan lookup: operational view, never cost | Y | Y | Y, in the till; scan capture needs the user's own OPEN cash session | No | BR-CAT-23 to BR-CAT-27, BR-CAT-33 |
| CAP-03 | View product photos and thumbnails | Y | Y | Y | No | D-037, BR-CAT-45 |
| CAP-04 | Create and edit products and categories; deactivate and reactivate them | Y | Y | N | No | BR-CAT-02, BR-CAT-09, D-042 |
| CAP-05 | Change a selling price in master data | Y | Y | N | No | D-036, BR-CAT-13 |
| CAP-06 | Add a manufacturer barcode; generate an internal code; retire a barcode, recording its retirement reason | Y | Y | N | No | BR-CAT-22, D-052 |
| CAP-07 | Correct a misassigned manufacturer barcode: move an active token to the correct product, or re-attach a token retired as misassigned | Y | N | N | Yes | D-051, D-052 |
| CAP-08 | Print and reprint barcode labels | Y | Y | N | No | BR-CAT-35, BR-CAT-36 |
| CAP-09 | Upload, replace or remove a product photo | Y | Y | N | No | D-037, BR-CAT-46 |
| CAP-10 | CSV product import: upload, preview, confirm, cancel | Y | Y | N | No | D-037, BR-CAT-41, D-054 |
| CAP-11 | Receiving: create and edit a receiving document, including purchase cost, until confirmation; confirm it | Y | Y | N | No | D-033, BR-INV-12, BR-INV-14 |
| CAP-12 | Purchase cost on receiving documents. After an Owner correction or completion: the current cost, the mark "Dikoreksi Pemilik" and the cost at confirmation | Y, with the reason and full correction history | Y, never the reason or history | N | No | D-033, D-047, BR-INV-15 |
| CAP-13 | Manual issue ("Barang keluar") | Y, with an explicit entry | Y | N | No | BR-INV-18, D-018 |
| CAP-14 | Current stock, stock attention, operational stock history, without cost other than CAP-12 | Y | Y | Only the stock needed at checkout | No | BR-INV-43, BR-INV-44 |
| CAP-15 | Opening stock entry; set and release the onboarding freeze; before the release, withdraw a zero count and record the replacement of a reversed OPENING (D-087) | Y | N | N | Set, release, withdrawal and replacement: yes | BR-INV-23 to BR-INV-28, BR-INV-47, BR-INV-48 |
| CAP-16 | Stock adjustment | Y | N | N | Yes | BR-INV-31 |
| CAP-17 | Reversal of an eligible movement | Y | N | N | Yes | BR-INV-34 |
| CAP-18 | Reconciliation incidents: view; reopen blocked postings | Y | N | N | Reopen: yes | BR-INV-45, BR-INV-46 |
| CAP-19 | Correct or complete a cost after posting | Y | N | N | Yes | D-034, BR-COST-20, BR-COST-22 |
| CAP-20 | Cost evidence, correction history, valuation, average cost, cost of goods sold, gross profit, margin, inventory value, finance report and cost reports | Y | N | N | No | D-033, BR-COST-06 to BR-COST-08 |
| CAP-21 | Open a cash session as oneself; sell in one's own OPEN session; close it; read, print and export one's own sessions, session reports, Sales and receipts; reprint one's own receipts | Y, own | N | Y, own | No | D-009, BR-SALE-02, BR-SALE-03, BR-CASH-21 |
| CAP-22 | All cash sessions, Sales, receipts and session reports; daily summary; Owner review notes | Y | N | N | No | BR-CASH-21, BR-RPT-01, BR-CASH-19 |
| CAP-23 | Owner-assisted close of another user's session | Y | N | N | Yes | BR-CASH-18 |
| CAP-24 | Refunds and returns, paid from the drawer of a selected OPEN session or from the Owner's own funds | Y | N | N | Yes | D-035, D-045, D-046, BR-RFD-01 |
| CAP-25 | Create a staff account (CASHIER, OPERATIONS_ADMIN or both) and issue its first-password link | Y | N | N | Yes | D-049, D-032 |
| CAP-26 | Issue or revoke a staff reset link | Y | N | N | Yes | D-049 |
| CAP-27 | Change a staff account's roles (never to or from SUPER_ADMIN); change a staff member's display name or email; change the Owner's own display name or email | Y | N | N | Yes | D-032, BR-USR-04, SEC-ACC-10 |
| CAP-28 | Deactivate or reactivate a staff account (never a SUPER_ADMIN account) | Y | N | N | Yes | D-042, BR-USR-10, BR-USR-04 |
| CAP-29 | Anonymize a deactivated staff account, or a former Owner account deactivated by the Owner replacement procedure | Y | N | N | Yes | D-043, D-048 |
| CAP-30 | Change the BusinessProfile (a new revision, including the logo) | Y | N | N | Yes | BR-CFG-02, D-038 |
| CAP-31 | Configure cash registers (code, label, active) while no session is OPEN on them | Y | N | N | Yes | BR-CASH-02 |
| CAP-32 | System status; the whole business audit log; security log | Y | N | N | No | BR-AUD-04, REQ-AUD-02, REQ-OPS-04 |
| CAP-33 | Create a SUPER_ADMIN account; assign or remove SUPER_ADMIN; change one's own role; deactivate or anonymize the only active Owner account; reactivate a former Owner account | N | N | N | — | D-048, D-032, BR-USR-04 (operator procedures only, section 14) |
| CAP-34 | Resolve the uncertain outcome of a command (ADR-007); see the unresolved requests from the server's receipts, the Owner every user's (ADR-009); a conclusive resolution settles the receipts of its intent (ADR-009 section 1, D-089) | Y, any | Y, own | Y, own | No | BR-CMD-03, ADR-007, ADR-009 |

Within CAP-21 and CAP-22, OPERATIONS_ADMIN has no access to the till, Sales, cash sessions or session reports (BR-SALE-03, BR-CASH-21). Staff see only their part of the business audit (BR-AUD-04): a CASHIER sees the audit of its own Sales and sessions, within CAP-21; OPERATIONS_ADMIN sees the safe stock history, within CAP-14. CAP-32 is the whole log.

## 7. Sensitive channels

| ID | Channel | Rule | Source |
| --- | --- | --- | --- |
| SEC-CH-01 | Inertia page props and JSON responses | Built only from DTO allowlists per role and per object. Eloquent models are never serialized to the browser; hidden model attributes are not a privacy boundary. | L-P4 §Keamanan dan privacy, L-04 §DTO |
| SEC-CH-02 | Inertia shared props | Only: the signed-in user's internal identifier, display name, roles and capability flags; the recovery epoch; the idle limit and the sign-in session's absolute end time (SEC-SESS-02); flash message codes; the translation dictionary (a once prop, ADR-005); and, for a signed-in user only, the shop's name, its initials and its accent from the BusinessProfile revision in force, the non-sensitive configuration that every role sees in the shell and the document title (DESIGN_SYSTEM section 7.2; TASK-010) — never before sign-in, nor any other field of the BusinessProfile. No other business data, no other user's data, no cost. | L-04 §Laravel/Inertia sensitive-channel contract, ADR-005, TASK-010 scope item 6 |
| SEC-CH-03 | Validation errors, flashed old input, redirects | Passwords, password confirmations and one-time link tokens are never flashed, echoed or redirected; a newly issued link reaches the browser only as SEC-ACC-05 describes. Other old input returns only to the same user's session; purchase cost may return to the OPERATIONS_ADMIN user who entered it (D-033). | L-04, D-033 |
| SEC-CH-04 | Browser history, back-forward cache and in-memory caches | Inertia history encryption is on for every authenticated page; remembered form state is kept only in that encrypted history and never holds a password, a link token or a cost. The history key is cleared at sign-out, on a change of user, and by the client timers of SEC-SESS-02 when a limit is reached, so a server-side end is mirrored in the browser without waiting for a request. A page restored from history after the key is cleared cannot be decrypted and is fetched again from the server, which requires a valid sign-in session. A page restored from the back-forward cache revalidates the session before showing data. Sign-out and sign-in end with a full page load, which discards prefetched, deferred and once-loaded data held in memory. Authenticated responses are `Cache-Control: no-store, private`. The next user never sees the previous user's screens. | L-P4 §History Inertia, L-P5, L-04, REQ-ACC-04; ADR-004 (verified source) |
| SEC-CH-05 | Browser storage | No business data in browser storage. Session storage holds only the framework's history encryption key and harmless interface preferences. The minimal command references of ADR-007 (operation, intent, key and time; no payload, secret or cost) are kept in local storage under the user's internal identifier, so that they survive a reload, a closed tab, a sign-out or a change of user until they are resolved; another user's references are never shown or offered, and the server resolves an intent only for its actor or the Owner (CAP-34). A reference is removed as soon as the server reports its intent resolved. A draft that contains cost, such as a receiving draft (BR-INV-17), is kept in page memory only. At sign-out and on a change of user, all other application storage in the browser is cleared. Signing out while an unresolved reference exists shows a warning and offers to resolve it first. The server also keeps a receipt of every command request — actor, operation, intent, key, epoch and time, never the payload — so that a user's unresolved requests are listed, and their families blocked, on every device (ADR-009). | BR-SALE-22, BR-INV-17, BR-CMD-03, L-04 |
| SEC-CH-06 | HTTP caching | Authenticated pages and JSON are `no-store, private`. Versioned static assets may be cached publicly. Logos, product photos and thumbnails are served only to authenticated requests, with private caching allowed, because every role may see them and their storage keys never change. Nothing sensitive appears in a URL query string. | L-04, TECHNICAL_RULES TR-HTTP-09 |
| SEC-CH-07 | Documents, print, PDF and CSV exports | Rendered from per-role DTOs and authorized for every request. Receipts, session reports, labels and search results never contain cost or profit. CSV exports quote text, neutralize formula and control prefixes, keep negative variance numeric and keep rupiah, time-zone and session context. | BR-COST-08, BR-CAT-35, BR-CASH-22, BR-DOC-06 |
| SEC-CH-08 | Logs | Contain a request identifier, the actor's internal identifier, the route and the outcome code. Never passwords, link tokens, cookies, session identifiers, secrets, full request bodies or cost values. | BR-AUD-05, L-04, L-P5 |
| SEC-CH-09 | Frontend bundle | Contains no secret and no data; no `VITE_` variable holds a secret. The same bundle serves every user. | L-P4, L-04 |
| SEC-CH-10 | Error pages | Generic, translated, with no internal detail; debug output is off outside local development (TECHNICAL_RULES TR-ERR-02). | L-P5 |
| SEC-CH-11 | Command results and recovery | Returned through the current authorization (SEC-AZ-06), with the same DTO allowlists as the original page. | BR-CMD-03 |

## 8. Secrets

| Secret | Who holds it | Rule |
| --- | --- | --- |
| Application key | The application container, per environment | Encrypts sessions, cookies and history; production and staging keys differ |
| Database runtime credential | The application container, per environment | Cannot change the schema or immutable facts (PRODUCTION_DATA_SAFETY section 3) |
| Database migration credential | The controlled migration job only | Never available to the web runtime |
| Database backup credential | The backup process only | Minimum access needed to read for backup |
| Backup encryption recipient (public key) | The backup container only | Not a secret in itself; set per environment ([ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md) item 2) |
| Recovery key (the private key of the backups) and the storage account access | The operator, in a password manager outside the server, and a sealed copy held by the shop's Owner (D-088, question 18) | Never on the server, in the repository, in the platform or in CI; every decryption, in a drill or a restore too, runs on the operator's own machine or an isolated drill target off the VPS (ADR-010 items 2 and 3; [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) BKP-08 and section 9) |
| Storage key of the server | The backup container only | Restricted to the environment's bucket, without delete or governance-bypass rights (ADR-010 item 7) |
| Cloudflare API token | None: the certificate is a Cloudflare Origin CA certificate, so no token is stored on the server (ADR-011 item 8) | — |
| Origin certificate private key | The proxy's certificate files on the server | Readable only by root; never in the dashboard, a log or the repository ([ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) ENV-10) |
| Coolify administrator credentials, SSH keys, Cloudflare, backup store and heartbeat monitor accounts, heartbeat ping addresses | The operator, outside the repository | [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) sections 3 and 16 |

| ID | Rule | Source |
| --- | --- | --- |
| SEC-SECRET-01 | No secret is ever committed or exposed: not in the repository, a document, a log, an audit payload, a screenshot, a frontend bundle or a `VITE_` variable. The repository holds placeholders only (`.env.example`, rebuilt by the foundation Task, T-02). | AGENTS rule 9, L-04 |
| SEC-SECRET-02 | Each environment has its own secrets; staging never holds a production secret. | D-053, ADR-003 |
| SEC-SECRET-03 | Secrets live in the platform's per-environment configuration or in the operator's private store. The application checks at start-up that required settings exist, without printing their values, and refuses to start with a missing or default production secret. | L-04 §Konfigurasi, L-09 |
| SEC-SECRET-04 | Rotation and revocation procedures are [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 16 and, for the recovery key, [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) section 9 (RF-6). A suspected leak is an incident: rotate, revoke sessions where relevant, and record ([OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md) RB-05). | L-09, D-088 |
| SEC-SECRET-05 | Because the repository is public (OF-01), no document names a real IP address, domain, provider account, credential, backup key or production backup location (N-01). The location of a safety copy is not named either: the SSD's is held by the Owner (D-065), and the local path of the RF-1 safety bundle was removed from the current documents (D-096, N-09), which identify that bundle by file name, size and SHA-256 only. | OF-01, N-01, D-055, D-065, D-096 |

## 9. Audit and security log

| ID | Rule | Source |
| --- | --- | --- |
| SEC-LOG-01 | The business audit follows BR-AUD-01 to BR-AUD-06: every successful business change is audited in its own transaction and rolls back with it; events are append-only; the Owner reads all; staff read only their scope. | BR-AUD-01 to BR-AUD-06 |
| SEC-LOG-02 | Successful security-relevant changes are business audit events: account created, role changed, display name or email changed, account deactivated, reactivated or anonymized, link issued, revoked or consumed (never the token), own password changed, Owner recovery and Owner replacement, BusinessProfile and cash register changes, onboarding set and release. | BR-AUD-01, D-043, D-048, D-049 |
| SEC-LOG-03 | The **security log** is separate from the business audit and is written outside the business transaction, so that a rollback never erases it: sign-in successes and failures, throttling, link failures, password-confirmation failures, CSRF failures, authorization refusals, session revocations and operator procedure runs. It contains no secret, token, password or full payload, and only the Owner reads it. It is stored in the application database, written by the request after its business transaction has ended — committed or rolled back — and never inside it (TECHNICAL_RULES TR-TX-02); each entry is also written to the standard output log. Its storage is [SCHEMA](../04-architecture/SCHEMA.md) section 5.4 (`security_log_entries`); its retention is set in RF-6 (SEC-LOG-05). | BR-AUD-05, REQ-AUD-02, L-04, L-P5 |
| SEC-LOG-04 | The runtime database role may add audit events but never change or delete them; the privileges are [SCHEMA](../04-architecture/SCHEMA.md) section 10 (PRODUCTION_DATA_SAFETY section 3). | BR-AUD-03, PDS |
| SEC-LOG-05 | **Retention.** Security log entries are kept 365 days from `occurred_at` and then removed by the scheduled housekeeping (D-088); nothing removes a younger entry. The database holds this: a row security policy admits the runtime role's `DELETE` only for an entry older than 365 days ([SCHEMA](../04-architecture/SCHEMA.md) section 5.4, which also states its effect on the backup dump). Operational log lines on standard output follow the platform's size-based rotation ([ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 14). Security-relevant changes stay permanently in the business audit (SEC-LOG-02). | BR-KEEP-07, D-088 |

## 10. Threat model

Path, attacker, risk, control, and the negative proof required. Test references name acceptance suites or NEW, as written before RF-6; the suites of every threat are [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md) section 8, which prevails where they differ (D-088).

| ID | Path and attacker | Risk | Controls | Negative proof | Tests |
| --- | --- | --- | --- | --- | --- |
| THR-01 | Sign-in, one-time link, session; an unauthenticated attacker | Enumeration, brute force, credential stuffing, link replay, session fixation | Uniform errors; throttling (section 11); hashed, expiring, single-use links (section 3); session regeneration; secure cookies; revocation; no registration or public reset route | Invalid, reused, expired and revoked links refused; fixation refused; throttling enforced; no registration or "forgot password" route exists; no email is sent | AUTH01, SEC01, NEW |
| THR-02 | Guessed object identifiers; an authenticated staff member | IDOR or BOLA: another cashier's Sale or session, a finance report | Policies and object scope on every route; identical response for missing and out-of-scope objects | Direct requests by each role with other users' identifiers refused | AUTH01, SESSION10 |
| THR-03 | Role or field injection; an authenticated staff member | Privilege escalation; a combined account reaching an Owner right; mass assignment | Roles changed only by the Owner; server-side capability map; strict FormRequest allowlists; unknown fields refused | A role or another authoritative value (TECHNICAL_RULES TR-VAL-03) in the body of any request other than the Owner's user management refused; a combined account refused every Owner-only capability; SUPER_ADMIN never assignable | AUTH01, AUTH03, NEW |
| THR-04 | Search, sort and filter inputs | SQL injection; unbounded queries | Bound parameters only; allowlisted sort and filter fields; paging limits (TECHNICAL_RULES sections 11 and 12) | Injection payloads treated as text; oversize pages refused | SEC01, SEARCH02 |
| THR-05 | Text that reaches screens and documents: product names, notes, BusinessProfile text, CSV-imported values | Stored cross-site scripting | React escaping; no raw HTML from any input; CSP without inline script (section 12) | Script payloads render as text on screens, receipts and labels | SEC01 |
| THR-06 | Cross-site requests | CSRF | Framework request-forgery protection with origin check and token (section 12); `SameSite=Lax`; `GET` never writes | Cross-origin and tokenless writes refused | SEC01, AUTH01 |
| THR-07 | Framing by another site | Clickjacking | `frame-ancestors 'none'` and `X-Frame-Options: DENY` | The application refuses to render in a frame | SEC01 |
| THR-08 | Props, history, caches, browser storage, exports, errors, logs; another role or the next user on a shared device | Cost, profit or another user's data leaks | Section 7 | Payload inspection per role and channel; sign-out → other user → Back, Forward, reload show nothing earlier | AUTH02 |
| THR-09 | Uploads: logo, product photo, CSV | Polyglot files, decompression bombs, metadata leaks, SVG or HTML scripts, oversized or malformed CSV, spreadsheet formula injection | Section 13 | Each refusal case of section 13 proven; metadata removed; temporary CSV files removed and never served | IDENTITY01, SEC01, NEW |
| THR-10 | **Command injection** — scheduled commands (reconciliation, housekeeping, backup), operator procedures, backup and restore tooling, image processing, file names and paths derived from input, CSV content | Execution of attacker-controlled commands on the server | No user input ever reaches a shell. Any external program is started through the framework's process API with an argument list, never a shell string. `exec`, `shell_exec`, `system`, `passthru`, `popen`, string-command `proc_open` and backticks are forbidden in application code and checked by static analysis. Arguments of scheduled and backup commands come only from configuration. Stored files get generated random names; a user-supplied file name is never used as a path or an argument. Uploaded files are never executed or interpreted: CSV is parsed as data; images are decoded by a library with external delegates disabled. Operator procedures accept only validated, typed options and confirm the target environment. | Static rule passes in CI; file names and CSV values containing shell metacharacters are stored and shown as text and never reach a process; scheduled-command arguments cannot be influenced by stored data | NEW (closes OPEN_ITEMS T-04) |
| THR-11 | Server-side fetching | SSRF | No feature fetches a URL supplied by a user; logos and photos are uploaded, never imported from a URL | URL inputs refused | SEC01, IDENTITY01 |
| THR-12 | File access | Path traversal; unauthorized downloads | Generated storage keys; private storage outside the web root; downloads by object and authorization only | Manipulated paths and guessed media identifiers refused | SEC01, IDENTITY01 |
| THR-13 | Redirect parameters | Open redirect | Only local, allowlisted redirect targets | External redirect targets refused | SEC01 |
| THR-14 | Dependencies and build | Known vulnerabilities; supply-chain tampering | Pinned versions and lockfiles; advisory checks at the foundation Task and before every release; no floating production image tags | Advisory report recorded per release | SEC01 |
| THR-15 | Secrets, debug output, web root | Secret disclosure | Section 8; debug off; only the public directory is served; environment and private files denied | Environment, vendor, backup and private paths unreachable | SEC01 |
| THR-16 | Infrastructure: database port, platform dashboard, SSH, forwarded headers | Direct database access; takeover of the hosting platform; spoofed client IP that defeats rate limits | Private database network (ADR-003); restricted dashboard and SSH (RF-6); only known proxy hops trusted; trusted hosts | Database unreachable from outside; spoofed forwarding headers ignored | NEW (RF-6) |
| THR-17 | Backups, restore, operator actions | Exfiltration of backups; replay of old intents after restore; destructive operator mistakes | Encrypted independent backup with separate credentials (ADR-002); recovery epoch and session revocation (ADR-007); the restore runbook re-applies security-relevant account changes made after the backup point (SEC-OP-04); PRODUCTION_DATA_SAFETY; audited operator procedures; question 20 | Restored copy refuses stale intents, also when the same backup is restored twice; runtime role refuses schema and history changes; a corrupt or tampered backup file or manifest is detected before restore (RF-6 input); after a restore to an earlier point, deactivations, role removals and password resets made after it are in force again before the shop reopens | REC02, DB01, NEW (RF-6) |
| THR-18 | A shared counter device | The next user sees the previous user's data or acts in their session | Section 7 (history, storage, caching); sign-out on every user change; an idle limit counted from user actions only and mirrored by client timers (SEC-SESS-02) | Previous user's pages unreachable after sign-out and after the idle limit, including through Back and Forward and with a background refresh running | AUTH02, NEW |
| THR-19 | Staff misuse of their own role | A cashier refunding to themselves; Operations changing posted cost | Refunds, returns and cost corrections are Owner-only (CAP-19, CAP-24); every action audited against one identity | Staff refused on the server | RET01, AUTH01 |
| THR-20 | The single Owner account | Takeover of the account with the most rights, without two-factor authentication (accepted with D-049) | AICWDF-COMPAT-8; Argon2id; throttling; recent authentication for sensitive actions; session limits; Owner recovery verifies the Owner's identity outside the application (runbook, RF-6) | Sensitive actions refused without recent authentication | AUTH03, NEW |
| THR-21 | A one-time link in the hands of someone other than the staff member, including the Owner who issued it | That person sets the password and acts under the staff member's identity (BR-USR-08, BR-CASH-08). A residual risk inherent in the Owner-issued links of D-049, accepted by the Owner for V1 under these controls (D-056); the acceptance authorizes no email delivery, one-time code, two-factor authentication, passkey or other channel | Link shown once and only to its issuer (SEC-ACC-05); 60-minute expiry; single use; revocation; issuance and use audited; at handover the staff member is told to report a password they did not set, and the Owner then issues a reset link | Expired, consumed and revoked links refused; every issuance and consumption appears in the audit | NEW |

## 11. Rate limits

Counters use the database-backed limiter (ADR-002). The client address comes only from the trusted proxy chain (ADR-003). Buckets for signed-in requests are keyed by user, so the shop's shared internet address never turns a per-person limit into a limit for the whole shop; address buckets apply only before sign-in. Values are initial technical defaults; their effect and false positives are measured on the real server and in the pilot and changed only through change control (L-P5).

| Bucket | Limit | Key |
| --- | --- | --- |
| Sign-in attempts | 5 per minute | Email and client address together |
| Sign-in attempts | 10 per minute | Client address |
| Sign-in attempts | 100 per minute | Whole installation |
| One-time link submissions | 10 per minute; 100 per minute | Client address; whole installation |
| Password confirmation (recent authentication) | 5 per minute | User |
| Link issuing by the Owner | One new link per account per 60 seconds | Account (the broker's throttle) |
| Product Search and scan lookup | 300 per minute | User |
| Commands | 60 per minute | User |
| Product photo uploads | 200 per hour | User |
| Logo uploads | 20 per hour | User |
| CSV import previews | 30 per hour | User |
| Print, reprint and export requests | 60 per minute | User |
| Any authenticated request | 600 per minute | User |
| Any request before sign-in (sign-in and link pages, health check) | 120 per minute | Client address |

A throttled request gets `THROTTLED` with a retry time (TECHNICAL_RULES section 4) and a security log entry. The legacy limits for public password-reset requests are retired: there is no public reset request (D-049).

## 12. CSRF, security headers and content security policy

| ID | Rule | Source |
| --- | --- | --- |
| SEC-HDR-01 | **CSRF.** The framework's request-forgery middleware protects every state-changing request in both of its layers: origin verification from the browser's fetch-site header over HTTPS, and the CSRF token as fallback. Origin-only mode is not used and no route is excluded. `GET` never changes state (TECHNICAL_RULES TR-HTTP-02). | L-P5, D-013; ADR-004 (verified source) |
| SEC-HDR-02 | **Headers on every response:** `Strict-Transport-Security` with a one-year max-age once HTTPS is verified on the target (`includeSubDomains` only when every subdomain is verified; no preload unless decided); `X-Content-Type-Options: nosniff`; `Referrer-Policy: strict-origin-when-cross-origin` (`no-referrer` on the one-time link page); `Permissions-Policy` disabling camera, microphone, geolocation and payment; `X-Frame-Options: DENY`; `Cross-Origin-Opener-Policy: same-origin`. | L-P5 §Baseline konfigurasi keamanan |
| SEC-HDR-03 | **Content security policy** (baseline): `default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'nonce-…'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`. The per-request nonce is applied to the generated script and style tags. No `unsafe-inline` or `unsafe-eval`; an exception needs a recorded reason and a browser test. Fonts are self-hosted; no third-party origin is allowed. Styles that interface libraries inject at run time (for example the scroll lock of dialogs and sheets, or the page-visit progress indicator) receive the nonce or are moved into the bundled stylesheet. A successful frontend build is not a CSP pass; browser tests decide, and prove that no needed script or style is blocked. | L-P5, legacy P5 CSP baseline |
| SEC-HDR-04 | HTTPS only. HTTP redirects to HTTPS, except what certificate validation needs (ADR-003). | L-P5, ADR-003 |
| SEC-HDR-05 | Requests for unknown host names are refused (trusted hosts), and only the proxy hops in front of the application are trusted for forwarded headers (ADR-003; configured as [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 10 says and tested in SEC01 and SEC02). | L-P4, ADR-003, ADR-011 |

## 13. Uploads

| | Logo | Product photo | CSV product import |
| --- | --- | --- | --- |
| Who | Owner (CAP-30) | Owner, OPERATIONS_ADMIN (CAP-09) | Owner, OPERATIONS_ADMIN (CAP-10) |
| Accepted content, judged by decoding, never by name or declared type | PNG, JPEG, WebP | JPEG, PNG, WebP | Text CSV in UTF-8; a leading byte-order mark is removed |
| Refused | SVG, HTML, GIF, any other type, remote URLs | SVG, HTML, GIF, any other type, remote URLs | XLSX and other formats, other encodings, control characters other than line breaks, a cell beginning with `=` (formula), unknown or missing columns |
| File size | At most 5 MiB | At most 8 MiB | At most 2 MiB |
| Dimensions or rows | At most 16 megapixels | At most 24 megapixels | At most 5,000 data rows and one header row |
| Processing | Dimensions read from the header before the image is decoded; animated images refused; decoded, turned upright according to its orientation metadata, re-encoded and stripped of metadata | As for logos; stored as a normalized image of at most 2,048 pixels on the longest side, with thumbnails for the till. The browser may shrink an image before upload to save bandwidth, but the server limits apply regardless | Parsed as data, row by row, within the row limit; strict header; every cell validated; whole-file validation and preview before confirmation; validated again at confirmation; all rows or none (BR-CAT-38) |
| Storage | Private storage outside the web root, under a random key that never changes; never overwritten; never deleted (BR-CFG-03, BR-KEEP-06). Its checksum is recorded so that backup and restore can detect a corrupt or missing file (kept in `stored_files.sha256`, SCHEMA section 5.5; the backup manifest: RF-6) | Private, random key; the file of a replaced or removed photo is kept, no longer shown (D-042); checksum as for logos | A private temporary area only. Removed as soon as the import reaches its final lifecycle outcome: committed, refused at confirmation, or cancelled by the user. Housekeeping removes any temporary CSV file older than 24 hours, whatever its state, including one whose removal at its final outcome failed. Never backed up, never downloadable (D-054) |
| Served to | Authenticated users of every role (screens and receipts) | Authenticated users of every role | Nobody; it is never served |
| Backup | Included (REQ-OPS-01) | Included (BR-CAT-49) | Excluded (D-054) |
| Audit | The BusinessProfile revision (BR-CFG-02) | The photo change (BR-AUD-01) | The import result: products created, a row summary, the file's SHA-256 checksum, actor and time (D-054) |

| ID | Rule | Source |
| --- | --- | --- |
| SEC-UP-01 | The name and declared type of an uploaded file are never trusted and never used for storage, a path or a command (THR-10, THR-12). | L-04, L-P5 |
| SEC-UP-02 | Size limits are enforced at the proxy and web server, at PHP and in application validation; the outer limits are set just above the application limits so the application gives the clear refusal. | L-P4 §Deployment (input), L-P5 |
| SEC-UP-03 | Images are decoded and re-encoded by a maintained open-source library chosen by the foundation Task, with its licence recorded and any external delegates disabled. Decoding happens only after the header dimensions pass. | L-04, THR-09, THR-10 |
| SEC-UP-04 | The CSV import's file checksum ties the confirmation to the previewed file; a different file needs a new preview (TECHNICAL_RULES RACE-22). | D-037, D-054 |
| SEC-UP-05 | The CSV column list and dialect are [DOCUMENT_CONTRACTS](../03-workflows/DOCUMENT_CONTRACTS.md) DC-04 (D-087). The parser accepts the CSV that common spreadsheet programs export with Indonesian regional settings: a semicolon or comma separator read from the header, an optional byte-order mark, decimal commas and full stops between groups of three. | BR-CAT-39 |
| SEC-UP-06 | No file is ever executed from storage, and storage paths are not served directly by the web server. | L-P5, THR-12 |

## 14. Operator procedures

Design level only. The runbooks are [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md) and [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md), written in RF-6; the Owner approves them with the RF-6 checkpoint and again at gate RELEASE, and every procedure is rehearsed on staging before the pilot (suite OPPROC01). The operator — a person, never an AI agent — runs them on staging and production (D-088, question 20; PRODUCTION_DATA_SAFETY section 5). No production operation is authorized today (CURRENT_HANDOFF).

Common rules for every procedure:

- It is a console command of the application, run inside the target environment's application container.
- It never assumes production: the operator identifies the environment positively before it runs (PRODUCTION_DATA_SAFETY section 2).
- A one-time link it creates is printed only to the operator's terminal, never to a log.
- It writes a business audit event whose actor is the named procedure, not a user, with the operator's recorded note, and a security log entry. Events of scheduled commands name their command in the same way; a one-time link consumption names the account that consumed it (DOMAIN_MODEL section 4; a non-user actor is stored as SCHEMA section 3.4 describes).
- It accepts only typed, validated options (THR-10) and never prints a secret.
- It is rehearsed on staging before the pilot (D-049).

| ID | Procedure | Preconditions | Effect, in one transaction | Source |
| --- | --- | --- | --- | --- |
| SEC-OP-01 | **Bootstrap** of the first Owner | No account of any kind exists in the installation | Creates the one SUPER_ADMIN account with the given display name and email and issues its FIRST_PASSWORD link. It also inserts the installation state — the recovery epoch and the onboarding state `NOT_STARTED` — and the first BusinessProfile revision with the shop name it is given, so that a revision always exists (SCHEMA guard G-23; D-087). Refuses to run if any account exists; of two concurrent runs exactly one succeeds (TECHNICAL_RULES RACE-30). | BR-USR-03, REQ-ACC-05, D-048, D-087 |
| SEC-OP-02 | **Owner recovery** (forgotten password or lockout) | Exactly one active Owner account; the Owner's request and identity verified outside the application as the runbook prescribes | Issues a RESET link for the Owner account, replacing any earlier link, and revokes every sign-in session of that account. Name, email and role are unchanged. | D-048, D-049, FD-04 |
| SEC-OP-03 | **Owner replacement** (the shop changes hands, or another person becomes the Owner) | Exactly one active Owner account, named by its internal identifier; the authorization evidence the runbook requires (OPERATIONS_RUNBOOK RB-11, written in RF-6 and approved by the Owner with its checkpoint). The outgoing Owner may hold an OPEN cash session: the procedure runs, and the session stays OPEN and unchanged until the new Owner closes it with an Owner-assisted close and a physical count (D-087, the Owner's decision on T-17 d for this procedure; as D-069 (d) for staff; BR-CASH-11, RACE-28) | Deactivates the named Owner account (never deletes it), revokes its sessions and links, creates a new SUPER_ADMIN account for the new person and issues its FIRST_PASSWORD link. After commit exactly one Owner account is active; a repeated run finds the named account DEACTIVATED and is refused (TECHNICAL_RULES RACE-30). The new Owner may later anonymize the former account (D-043, D-048). | D-048, D-042, D-043, D-069, D-087 |
| SEC-OP-04 | **After a restore** | Part of the restore runbook (BACKUP_RECOVERY RB-01 phase D, written in RF-6) | Revokes every sign-in session and sets a recovery epoch never issued before (ADR-007), so that restoring the same backup twice still refuses stale intents; when the restored onboarding state is not `RELEASED`, it sets an onboarding restore hold (D-072). Before the shop reopens, the Owner's own requests from before the restore are reconciled with SEC-OP-05 (D-073), and the runbook identifies the security-relevant account changes made after the backup point — deactivations, role removals, link revocations, password resets, an Owner replacement — from records that survive the restore (the platform's operational logs, the Owner's own knowledge), re-applies them, and requires new passwords through links where there is doubt. | BR-CMD-06, REQ-CMD-03, BR-USR-05, D-048, D-072, D-073 |
| SEC-OP-05 | **Restore reconciliation** (D-087) | Part of the restore runbook (BACKUP_RECOVERY RB-01 phase E, written in RF-6), after SEC-OP-04; the Owner's decision for each item, recorded by the operator | For each request reported as `EPOCH_STALE` (ADR-009 section 3), records the Owner's outcome — not committed in the restored data — with a fence of its operation and intent in the current epoch, so the request can never commit and its reference is released; in the same transaction every PENDING or HELD receipt of that intent is settled as NOT_COMMITTED (ADR-009 section 1; D-089). For an onboarding restore hold, records the Owner's outcome: released before the restore (the release record is written and the state becomes `RELEASED`) or never released (the restored state continues as the same period). Every decision is audited (`RESTORE_RECONCILIATION`); the run is in the security log. | D-072, D-073, ADR-009 |
| SEC-OP-06 | **Cost recovery** (D-088), the reviewed recovery procedure of D-034 | A cost-incomplete mark whose correction was refused for the movement cap or the time budget; the Owner's written decision naming the mark, the cost source, the corrected amount and the reason (OPERATIONS_RUNBOOK RB-12); outside opening hours | Runs the same validation and replay as the correction command (RC-15) with a larger bound set for the run — at most ten times the movement cap and 10 minutes — under the product guard; publishes atomically a correction fact whose actor is the Owner, the corrected projections, a clearance of each active mark of that cost source with `RECOVERY_PROCEDURE`, and the audit event; or refuses and changes nothing. Its run is recorded under a command identity of the procedure, so a repeated run with the same mark returns the first result. Never used for an unsafe history. | D-034, D-063, BR-COST-27, SCHEMA section 5.8 |
| SEC-OP-07 | **Projection rebuild** (D-088) | A reconciliation incident (BR-INV-45, BR-INV-46); the Owner's request (OPERATIONS_RUNBOOK RB-04) | For one product, recomputes from immutable facts its stock balance, valuation and allocation projections; for one Sale, its running totals of refunds and returns; for one token, its active mapping — under the same guards as the commands that write them; writes the corrected projection values and an audit event `PROJECTION_REBUILT` (cost values only in its Owner-only data); never changes a fact; refuses and changes nothing when the facts themselves fail validation. The Owner then reopens postings with RC-16. | BR-INV-46, SCHEMA G-03 |
| SEC-OP-08 | **Restore drill record** (D-088) | A production restore drill run under BACKUP_RECOVERY RB-02 | Inserts one `restore_drills` row (SCHEMA section 5.12) with the kind, the backup identifier, the manifest's SHA-256, the result, the measured restore time and the operator's note; writes the security log entry. Changes no business data. | REQ-OPS-04, ADR-010 |

If the Owner only needs a new email address, no replacement is needed: the Owner changes it in the product (CAP-27).

## 15. Security tests — input to RF-6

These tests follow from this document. Since RF-6 they are cases of the suites of [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md) — AUTH01 to AUTH03, ACC01, LINK01, SIGNIN01, SEC01, SEC02, AUDIT01, IDENTITY01, OPPROC01, REC01 and REC02 — and section 8 there maps every threat (D-088).

- Route inventory: every authenticated route declares a capability; no registration or public reset route exists; no mail is sent by any flow.
- One-time links: expiry at 60 minutes, single use, replacement, revocation on deactivation, anonymization and email change, race with revocation (RACE-20), uniform failures, token absent from every log; the issued link appears only in the issuing JSON response, never in page props, history state or a URL; the fragment is removed after reading.
- Password profile AICWDF-COMPAT-8 on every password-setting path; Argon2id in use.
- Sessions: 30-minute idle and 12-hour absolute limits, also with the stock attention refresh running; client timers clear the history key, and Back after expiry shows nothing; no remember-me; revocation triggers of SEC-SESS-04; cash session unchanged after sign-out; unresolved command references kept for their user only.
- Recent authentication: every capability marked "yes" refused without it and accepted with it.
- Permission matrix: every CAP for each role and for the combined account, by direct request; object scope with guessed identifiers; exactly one active Owner (SEC-ACC-01, RACE-21).
- Sensitive channels: payload inspection per role for every channel of section 7; sign-out and change of user on one device.
- Headers, CSP and CSRF in a real browser.
- Rate limits, including spoofed forwarding headers.
- Uploads: every refusal case of section 13; metadata removed; CSV temporary file removed at each final outcome and by housekeeping; never served.
- Command injection: the static rule; metacharacters in file names and CSV values.
- Operator procedures rehearsed on staging, including the refusal of a second bootstrap and of a repeated replacement.
- Restore: a backup restored twice refuses stale intents; security-relevant account changes made after the backup point are re-applied; a corrupt backup file or manifest is detected (REC01, REC02).

## 16. Open items

| Item | Subject | Step |
| --- | --- | --- |
| Question 20 | Who may operate on production, including the operator procedures | RESOLVED — D-088: the operator, a person, never an AI agent (ADR-011) |
| Question 18 | Backup destination, cost and recovery key holder | RESOLVED — D-088 (ADR-010) |
| N-01 | Repository visibility; this document is written for a public repository | Before the first push |
| T-10 | Cloudflare and Coolify configuration, certificate method, firewall, dashboard and SSH access, trusted-proxy settings | RESOLVED in RF-6: [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md), ADR-011 |
| T-02 | `.env.example` rebuilt for Laravel with placeholders only | Foundation Task |
| T-03 | Storage of the single-active-Owner rule, audit privileges, frozen display information (BR-USR-12), non-user audit actors, the security log and file checksums — resolved by SCHEMA sections 3.4, 3.5, 5.1, 5.4, 5.5 and 10 | RESOLVED in RF-3B |
| T-10 and the restore runbook | Re-applying security-relevant account changes after a restore (SEC-OP-04); backup manifest | RESOLVED in RF-6: BACKUP_RECOVERY RB-01 phase E and section 5 |
| T-17 d | Deactivating a user who holds an OPEN cash session: decided for staff accounts by D-069 and for the Owner replacement procedure by D-087 (allowed; the session stays OPEN until an Owner-assisted close); enforcement and tests specified in RF-5 (TECHNICAL_RULES RACE-28) | RESOLVED in RF-5 |
| Receipt retention | How long resolved command request receipts are kept (ADR-009) | RESOLVED in RF-6: 30 days after the outcome (SCHEMA section 5.3) |
| Gate G3 | Whether staff share a device (the controls above already assume they may) | Before the pilot |
| T-23 | The runtime role can `DELETE` security log rows whatever their age (SCHEMA section 10); the retention of 365 days (SEC-LOG-05) needs enforcement in the database. Nonblocking finding of the RF-6 review, recorded only (D-089) | RESOLVED by TASK-006: the row security of SCHEMA section 5.4 (SEC-LOG-05) |
