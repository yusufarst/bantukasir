# ADR-004 — Authentication

```text
ID:               ADR-004
TITLE:            Authentication
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-048 and D-049 (Owner, questions 25 and 6); D-013 and D-039 (sessions, hashing, recent authentication);
                  AICWDF §4A.1 (implementation follows the stack)
```

## Context

AICWDF §4A.1 requires the authentication implementation to be decided once and recorded, so that executors reuse it. For a Laravel backend the framework default is Laravel-native authentication with the current official starter or auth stack. BantuKasir is a closed system: accounts are created by the Owner, staff may share a counter device (gate G3, UNKNOWN), and the application sends no email (zero recurring cost). The Owner decided the sign-in profile (D-049) and that there is exactly one active Owner account (D-048).

## Decision

1. **Implementation: LARAVEL_NATIVE.** Authentication is built on Laravel Fortify, the authentication backend of the current official Laravel starter kits, rendered through Inertia and React. Only the features BantuKasir needs are enabled: sign-in and sign-out, password confirmation (recent authentication), and changing one's own password. Disabled: public registration, email verification, two-factor authentication, passkeys and the public "forgot password" request. The WorkOS variant of the starter kits is not used. Sanctum, Passport and Socialite are not installed (AICWDF §4A.7; Google sign-in is off).
2. **Identifier.** The sign-in identifier is an email address. It is never verified and the application never sends email (D-049). No mail transport is configured in production.
3. **Accounts.** Only the Owner creates staff accounts. There is no self-registration. The first Owner account is created by a private one-time operator procedure. There is exactly one active SUPER_ADMIN account at any time; it is never created, combined, demoted, deactivated or anonymized through the product (D-048, D-032).
4. **One-time links.** A staff member sets the first password through a short-lived one-time link that the Owner creates and hands over directly, not by email; a staff password reset uses the same mechanism (D-049, FD-04). The links are built on Laravel's password broker token repository: the token is stored hashed, one active token per account, expiring, consumed once. A new link replaces the previous one. Because the broker's database repository keys tokens by email, a link is revoked before the account's email changes, and a link is checked only against the account named by its internal identity. Expiry, revocation, delivery and log rules are in [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 3.
5. **Owner recovery and replacement** are documented operator procedures on the server, rehearsed before the pilot (D-048, D-049; SECURITY_MODEL section 14).
6. **Password profile: AICWDF-COMPAT-8** — at least 8 characters with uppercase, lowercase, a number and a symbol; paste and password managers allowed; no forced periodic rotation (D-049). It is an Owner-selected usability profile. It is **not** described or claimed as NIST-compliant (AICWDF §4A.5). It replaces the 12-character minimum of the former design candidate.
7. **Hashing: Argon2id** through the framework's hashing driver, with parameters benchmarked on the target server (D-013, legacy P5 §Baseline konfigurasi keamanan).
8. **Sessions and recent authentication** follow D-013 and D-039: database sessions, 30 minutes without user activity (background refreshes do not count) and 12 hours absolute, both enforced by application middleware (the framework's own idle lifetime is refreshed by every request and has no absolute limit), recent authentication within 15 minutes for sensitive Owner actions; signing out never changes a cash session (SECURITY_MODEL section 4).
9. **Boundary.** Authentication stays behind the Access module (AICWDF §4A.9). Domain records reference the internal user identity only, never the email or any provider-specific value. Authorization is separate and provider-neutral (AICWDF §4A.8; SECURITY_MODEL sections 5 and 6).

The complete authentication profile in AICWDF §4A.4 and §34A form is SECURITY_MODEL section 2. This ADR and that section must agree; a difference is a defect to correct.

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| A username created by the Owner instead of an email (question 6, option B) | Declined by the Owner (D-049). |
| Google sign-in for existing accounts (question 6, option C) | Declined by the Owner (D-049): needs Google Cloud credentials and risks a personal Google session left signed in on the shop device. FD-03 approved. |
| Email-based reset and verification (AICWDF §4A.2 default core) | Needs a mail service; staff accounts are Owner-created and handed over in person. FD-04 approved (D-049). |
| More than one Owner account (question 25, option B) | Declined by the Owner (D-048). Adding a second Owner later is a small additive change that needs its own Owner decision. |
| The starter kit's authentication controllers without Fortify, or a hand-built authentication layer | The current official starter kits use Fortify; hand-built password, token or session cryptography is forbidden (legacy 04 §Autentikasi). |

## Consequences

- No email service and no cost (COST_POLICY).
- The single Owner can lock themselves out. Recovery then needs a person with technical access to the server; the procedure must be documented and rehearsed before the pilot (accepted with D-048 and D-049; DECISIONS section 5).
- Fortify's public reset request route is not exposed. The link consumption endpoint and the Owner's link-issuing action are application code on top of the broker; the foundation and authentication Tasks verify the exact APIs against the pinned version.
- Anonymization (D-043) replaces the email with a neutral, unusable value (SECURITY_MODEL section 3).

## Framework conformance

```text
ADR REQUIRED:      YES (AICWDF §4A.1: the authentication decision is recorded once)
DEVIATION:         FD-03 (Google sign-in off) and FD-04 (no email-based reset or verification), both APPROVED by D-049.
                   Otherwise LARAVEL_NATIVE as AICWDF §4A.1 and §4A.7 prescribe; AICWDF-COMPAT-8 as §4A.5 prescribes.
OWNER APPROVAL:    GIVEN — D-048, D-049
REASON:            closed shop system; no mail service; Owner-created accounts
RISK:              Owner lockout needs an operator; a one-time link handed over insecurely could be used by someone else
                   (mitigated by short expiry, single use and revocation); whoever holds a link, its issuer included,
                   can act as that staff member (SECURITY_MODEL THR-21; accepted for V1, D-056)
MIGRATION IMPACT:  none — no application exists
ROLLBACK IMPACT:   adding Google sign-in, email reset or a second Owner later is additive and needs an Owner decision
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://laravel.com/docs/starter-kits | 4 October 2026 | All Laravel 13.x starter kits use Laravel Fortify; a feature is disabled by removing it from the `features` array in `config/fortify.php` (for example `Features::registration()`); a WorkOS AuthKit variant exists. |
| https://laravel.com/docs/fortify | 4 October 2026 | Fortify features (`registration`, `resetPasswords`, `emailVerification`, `updateProfileInformation`, `updatePasswords`, `twoFactorAuthentication`, `passkeys`); routes `/reset-password/{token}`, `/user/confirm-password`; login throttling by the `EnsureLoginIsNotThrottled` middleware on the username and IP combination, configurable through `fortify.limiters.login`; `Fortify::authenticateUsing` for custom credential checks. |
| https://laravel.com/docs/passwords; https://api.laravel.com/docs/13.x/Illuminate/Auth/Passwords/PasswordBroker.html; https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Auth/Passwords/DatabaseTokenRepository.php; https://raw.githubusercontent.com/laravel/laravel/13.x/config/auth.php | 4 October 2026 | Password brokers are configured in `config/auth.php` with `expire` (minutes, skeleton default 60) and `throttle` (seconds between token creations, default 60); `PasswordBroker::createToken()`, `tokenExists()` and `deleteToken()` exist; the database repository stores the token hashed and deletes a user's existing token before creating a new one; `auth:clear-resets` removes expired tokens. |
| https://laravel.com/docs/validation | 4 October 2026 | `Password::min(8)->letters()->mixedCase()->numbers()->symbols()` and `Password::defaults()` express AICWDF-COMPAT-8; `uncompromised()` calls an external breach service and is not used (no external dependency is needed by the profile). |
| https://laravel.com/docs/hashing | 4 October 2026 | Argon2id is a supported driver (`HASH_DRIVER=argon2id` or `config/hashing.php`); bcrypt is the default and is not used. |
| https://laravel.com/docs/authentication; https://raw.githubusercontent.com/laravel/laravel/13.x/config/auth.php | 4 October 2026 | The `password.confirm` middleware uses `auth.password_timeout` in seconds (skeleton default 10800); BantuKasir sets 900 seconds (15 minutes, D-013). `Auth::logoutOtherDevices` and the `auth.session` middleware exist. |
| https://laravel.com/docs/session; https://raw.githubusercontent.com/laravel/laravel/13.x/config/session.php | 4 October 2026 | `lifetime` is the idle limit in minutes (default 120); cookies default to HTTP-only and SameSite `lax`; `secure` and `encrypt` are configurable; the session ID is regenerated on authentication with Fortify. No built-in absolute session limit was found, so the 12-hour limit is enforced by application middleware. |
| https://laravel.com/docs/13.x/csrf | 4 October 2026 | The `PreventRequestForgery` middleware in the `web` group checks the browser's `Sec-Fetch-Site` header first and allows a same-origin request; the header is sent only over HTTPS; otherwise it falls back to CSRF token validation; `preventRequestForgery(originOnly: true)` disables the token fallback and `except:` excludes routes (neither is used, SECURITY_MODEL SEC-HDR-01). |
| https://inertiajs.com/docs/v3/security/history-encryption | 4 October 2026 | The history encryption key is kept in the browser's session storage; after the key is cleared, decrypting a restored history state fails and Inertia requests the page from the server again; history is cleared with `Inertia::clearHistory()` on the server or `router.clearHistory()` in the client; encryption needs a secure context (HTTPS). The foundation Task checks it against the pinned version (STK-04). |

## Related

- [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) sections 2, 3, 4 and 14; [FRAMEWORK_DEVIATIONS](../00-governance/FRAMEWORK_DEVIATIONS.md) FD-03 and FD-04.
- Superseded legacy content: `docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md` §Autentikasi dan penjagaan mutation; `docs/05-security/legacy/P5-SECURITY.md` §Baseline konfigurasi keamanan (authentication part); `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Keamanan dan privacy implications (authentication part).
