# ADR-005 — Localization architecture

```text
ID:               ADR-005
TITLE:            Localization architecture
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-050 (Owner, question 5); D-020 (Indonesian is the primary interface language); D-018 (date format)
```

## Context

AICWDF §4B requires an Indonesian-first interface, English as a second language with a switcher, and forbids hard-coded user-facing copy (§4B.4). The Owner decided that every interface text uses translation keys from the first Task, that V1 ships Indonesian only with no language switcher, and that English is a later, separate scope decision (D-050, FD-02 approved). The mechanism is a technical choice, made here from current official documentation, with one aim: hard-coded copy must be impossible to merge, not merely discouraged.

## Decision

1. **One source of copy.** All user-facing text lives in Laravel translation files under `lang/id/`, as PHP array files grouped by module, addressed by stable English dot keys (for example `sales.payment.final_action_transfer`). Short keys are used instead of JSON files keyed by the default string, so that a missing translation is detectable and a key never doubles as copy. Nothing user-facing is written in PHP, Blade, TypeScript or JSX source as a literal.
2. **Locale.** The application locale and the fallback locale are both `id`. There is no language switcher, no locale detection and no English interface dictionary in V1; framework language files for other locales, if the scaffold publishes them, are never shown to users. Adding English later means adding an English dictionary and a switcher under a new Owner decision, without rewriting pages (D-050).
3. **Server-side copy.** Validation messages, flash messages, CSV export headers and any server-rendered text use `__()` or `trans()` with keys from `lang/id/`. Error responses carry a stable error code and a translation key, never English framework text ([TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 4).
4. **How copy reaches React.** The `HandleInertiaRequests` middleware shares the active locale's interface dictionary as an Inertia once prop (`Inertia::shareOnce`), which the client remembers across visits. The frontend reads it through one small typed helper `t(key, replacements)` in `resources/js/lib/`. No third-party internationalization runtime is added in V1; Indonesian has no plural forms that need one, and placeholders use the Laravel `:name` syntax. If measurement shows the dictionary is too large for one prop, it is split per module; the mechanism stays the same.
5. **Enforcement — hard-coded copy cannot be merged.**
   - ESLint rule `react/jsx-no-literals` with `noStrings` and `noAttributeStrings` set to `true`, so string literals are refused both as JSX children and in attributes such as `placeholder`, `title` and `aria-label`. A short allowlist covers only non-linguistic characters (punctuation and symbols). The lint step fails the build.
   - The key type of `t()` is generated from the `lang/id/` files, so an unknown key fails the TypeScript typecheck.
   - A check fails the build if any key used in PHP or TypeScript is missing from `lang/id/` (missing keys: 0, AICWDF §4B.5).
   - In tests, the translator's missing-key handler (`Lang::handleMissingKeysUsing`) throws, so a server-side lookup that would fall back to printing its key fails.
6. **Dates, numbers and money.** One formatting module per side formats from exact values (ADR-006): dates with the full Indonesian month name and dotted time, for example `01 Oktober 2026, 09.15.00` (D-018, BR-NUM-07); rupiah with dot thousands separators; quantities and costs from decimal strings. Month names come from the translation files. Formatting never depends on the browser's or server's locale data, and stored times, business dates and periods are not affected (BR-NUM-05 to BR-NUM-07).
7. **Copy content** is approved by the Owner at gate P7-G2 (RF-4). The glossary's Indonesian terms stay provisional until then ([GLOSSARY](../02-domain/GLOSSARY.md)). The localization contract (`docs/07-ux-design/LOCALIZATION.md`) is written in RF-4 and builds on this ADR.

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| Indonesian and English shipped and tested in V1 (question 5, option A) | Declined by the Owner (D-050). |
| No localization architecture (question 5, option C) | Declined by the Owner; it would break AICWDF §4B.4 and close the path to English. |
| A separate frontend dictionary in the JavaScript bundle (for example a third-party i18n library with JSON files) | Two dictionaries for server and client copy, or a second runtime to maintain. One Laravel source shared through Inertia keeps a single truth with first-party mechanisms. Reconsidered only if the shared prop proves too large. |
| JSON translation files keyed by the Indonesian text | The key would be the copy itself; renaming copy would change keys and a missing translation would silently print Indonesian source text. |

## Consequences

- Every interface Task writes keys and Indonesian copy together; no second-language work or switcher testing in V1 (FD-02).
- Pages cannot render text without the dictionary; the once prop is present on the first page of every visit sequence.
- Developers cannot type a quick literal label; the lint rule is intentionally strict. Non-linguistic exceptions are reviewed when added to the allowlist.

## Framework conformance

```text
ADR REQUIRED:      YES (records the localization mechanism before the first interface Task)
DEVIATION:         FD-02 (English interface deferred beyond V1; no switcher) — APPROVED by D-050.
                   AICWDF §4B.4 (no hard-coded copy) is met and enforced.
OWNER APPROVAL:    GIVEN — D-050
REASON:            one Indonesian shop and Indonesian-speaking staff in V1; keys keep English addable
RISK:              a later English retrofit still needs layout checks for longer strings (§18.11)
MIGRATION IMPACT:  none — no application exists
ROLLBACK IMPACT:   adding English is additive (a new locale directory and a switcher) under a new Owner decision
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://laravel.com/docs/localization | 4 October 2026 | The `lang` directory is created with `php artisan lang:publish`; PHP array files with short keys and JSON files keyed by the default string are both supported; the locale and fallback locale are set in `config/app.php` (`APP_LOCALE`, `APP_FALLBACK_LOCALE`); `__()` returns the key when a translation is missing; placeholders use `:name`. |
| https://api.laravel.com/docs/13.x/Illuminate/Translation/Translator.html | 4 October 2026 | `Translator::handleMissingKeysUsing(callable)` registers a handler for missing keys. |
| https://inertiajs.com/docs/v3/data-props/shared-data | 4 October 2026 | Shared data is defined in `HandleInertiaRequests::share`; all shared data is included in every response, so it is used sparingly; `Inertia::shareOnce` shares a once prop; props are read with `usePage().props`. |
| https://inertiajs.com/docs/v3/data-props/once-props | 4 October 2026 | Once props are remembered by the client and reused on later pages that include the same prop; `fresh()` and `until()` control refresh and expiry. |
| https://github.com/jsx-eslint/eslint-plugin-react/blob/master/docs/rules/jsx-no-literals.md | 4 October 2026 | `react/jsx-no-literals` refuses string literals in JSX; `noStrings` covers children and `noAttributeStrings` covers attributes; `allowedStrings` lists exceptions. |

## Related

- [FRAMEWORK_DEVIATIONS](../00-governance/FRAMEWORK_DEVIATIONS.md) FD-02; [REQUIREMENTS](../01-product/REQUIREMENTS.md) REQ-NFR-01, REQ-DOC-05.
- RF-4: `LOCALIZATION.md`, copy deck and gate P7-G2.
