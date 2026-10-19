# TASK-003 evidence — Shared kernel, formatting and localization

| | |
| --- | --- |
| Task | [TASK-003](../tasks/TASK-003-kernel-localization.md), size M, review class A, lane P |
| Status | `DONE` (D-100); the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | The Owner's instruction of 19 October 2026, "TASK-003: Shared kernel, formatting and localization (executor instruction)", prepared by the planning agent on 19 October 2026 and sent by the Owner the same day, approving its whole content |
| Branch | `task/TASK-003-kernel-localization`, created from `develop` (EXC-22) |
| Base commit | `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, the D-098 bookkeeping commit on `develop` (parent `b212819a1761ffc468283d6813bf6ec7581d097e`) |
| Executor | Implementation executor in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, as the Owner's instruction names it (D-031, D-095), and the desktop app's record of the session shows effort `xhigh`; a new session dedicated to TASK-003, started on 19 October 2026 at 22:02 |
| Gates | None besides EXECUTOR, checked at the start (TASK_PLAN section 3) |
| Authorized beyond local commits | The local environment (`docker compose up -d --build --wait` and `docker compose down`, never `-v`); project-local dependency changes only if a need is proven; after local verification, the publication guard, non-force pushes of the Task branch, one pull request into `develop` and watching its CI. Not authorized: any merge, any push of `develop`, `main`, `plan/bantukasir-pos-v1` or a tag, and any change to an approved P7 artifact, COPY_DECK, GLOSSARY, OPERATIONS_RUNBOOK, OPEN_ITEMS, SOURCE_OF_TRUTH or DECISIONS |
| Remediation | The Owner's instruction of 20 October 2026, "TASK-003 remediation: the duration formatter (F-1) and the adversarial findings (executor instruction)", prepared by the planning agent on 19 October 2026 and revised on 20 October 2026, sent by the Owner on 20 October 2026 with the planning agent's decision on the review (`TASK-003-Adversarial-Review-Decision.md`) and the reviewer's report (`TASK-003-Adversarial-Review-Report.md`, SHA-256 `b8a2012a7f65f8bec7416d52a4c8b09a9cec9f07adc86544f69b29c7cd7b591e`, checked); section 10. It authorized `git fetch origin`, the merge of `origin/develop` into the branch (EXC-36), the changes of its sections 3 and 4, the documents of its section 5 and of the decision's section 4 — OPEN_ITEMS T-26 and its TASK_PLAN section 7 row among them — local commits, the local environment, the publication guard, non-force pushes of the Task branch and watching the CI of pull request #3; not any merge into `develop`, any other push, a change to COPY_DECK, `lang/id` beyond the two duration keys, an approved P7 artifact or another document, a fix the decision does not list as FIX, or a business rule |
| Remediation executor | Implementation executor in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, as the instruction names it, and the desktop app's record of the session shows effort `xhigh`; a new session dedicated to the remediation, not the reviewer's, started on 20 October 2026 at 02:29 |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24)

- **Read path (EXC-01).** AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT, TASK_PLAN sections 1 to 4 and the TASK-003 contract, then the sources of its section 3.
- **Git state (EXC-20)**, checked on 19 October 2026 at 22:03 with `GIT_OPTIONAL_LOCKS=0`: branch `develop` at `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, equal to `origin/develop` after a fetch; nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`; `.claude/settings.local.json` ignored by the user-global rule (D-097); `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" present; the ref under `refs/codex/turn-diffs/` present; remote `origin` only; the only lock is `.git/objects/maintenance.lock`. `git ls-remote origin`: `develop` `fc0055b`, `main` `f7cecfe`, `plan/bantukasir-pos-v1` `86822d5`, `task/TASK-001-toolchain-ci` `8f0b96a`.
- **CURRENT_HANDOFF section 1.** `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md` prints `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, then `b212819a1761ffc468283d6813bf6ec7581d097e`: the D-098 bookkeeping is committed.
- **Prerequisites (EXC-21).** TASK_PLAN section 4 on `fc0055b`: TASK-001 `DONE`, TASK-003 `READY`, no gate in its row besides EXECUTOR; totals `TODO` 47, `READY` 2, `DONE` 1.
- **Docker.** Docker engine 29.8.0 answers; Docker Compose v5.5.1; the BantuKasir environment is stopped (no container); the volume `bantukasir_db-data` exists. Another Compose project of the machine, unrelated to BantuKasir, was running; it was not touched.
- **Tools on the host.** Git 2.55.0.windows.3. PHP, Composer and the command contract run in the project's containers (TASK-001).
- **Branch (EXC-22).** `git switch -c task/TASK-003-kernel-localization` at `fc0055b`, on 19 October 2026 at 22:08.
- **Start recorded (EXC-24).** The Task's first commit, `TASK-003: start`, sets TASK-003 `IN_PROGRESS` in TASK_PLAN section 4 (totals `TODO` 47, `READY` 1, `IN_PROGRESS` 1, `DONE` 1, recounted from the rows) and creates this file.
- **Local environment.** `docker compose up -d --build --wait` at 22:09 started `app` and `db`, both healthy, on the kept volume; it stayed up for the Task's checks.

## 2. Versions and sources checked (EXC-03)

Context7 is not verified in this environment (TOOLCHAIN section 1). Every version-sensitive fact was checked in the installed source of the pinned version and, where it has one, in its official documentation, on 19 October 2026. No dependency was added or changed: `composer.lock` and `package-lock.json` are unchanged, and the only `package.json` change is the `build` script (section 4, R-18).

| Component, pinned | Source checked | Fact relied on |
| --- | --- | --- |
| Brick Math 1.0.0 (`vendor/composer/installed.json`) | `vendor/brick/math/src` (`RoundingMode.php`, `BigNumber.php`, `BigDecimal.php`, `NumberSyntax.php`); CHANGELOG at the tag `1.0.0` on github.com/brick/math | 1.0.0 is the first stable release, with no change from 0.20.0. `RoundingMode` is an enum whose upper snake case constants such as `HALF_UP` were removed in 0.15.0; `RoundingMode::HalfUp` "behaves as for Up if the discarded fraction is >= 0.5", and Up rounds away from zero: the equivalent of PHP's `HalfAwayFromZero`, which is the rule of ADR-006 item 4 and ADR-008 section 5 (finding F-3). `BigDecimal::dividedBy($that, $scale, $roundingMode)` divides once at the scale; `toScale()` rounds once; `strippedOfTrailingZeros()` keeps the scale non-negative; `jsonSerialize()` returns a string ("the JSON output is always a string"); `of()` accepts exponents and a leading `+`, so the kernel checks the canonical form itself before calling it. The image has neither GMP nor BCMath, so Brick Math runs its native calculator (`CalculatorRegistry::get()` is `NativeCalculator`), which is exact |
| Laravel 13.37.0 | laravel.com/docs/13.x (localization, eloquent-mutators, container); api.laravel.com/docs/13.x (Translator); `vendor/laravel/framework/src` (`Translation/Translator.php`, `Validation/Concerns/FormatsMessages.php`, `Contracts/Validation/ValidationRule.php`, `Contracts/Database/Eloquent/CastsAttributes.php`, `Container/Attributes/Bind.php`) | `Translator::handleMissingKeysUsing(callable|null)` "registers a callback that is responsible for handling missing translation keys"; placeholders `:name`, `:Name` and `:NAME`, replaced by `strtr` (longest first, never searched again); locale and fallback in `config/app.php`; a custom cast's `set()` returns an array to set several columns, and cast parameters follow a `:`; `#[Bind]` on an interface names its implementation; the validator probes the optional translations `validation.attributes`, `validation.custom`, `validation.custom.*` and `validation.values.*` and falls back when they are absent (finding F-2) |
| Inertia, Laravel adapter 3.5.1 | inertiajs.com/docs/v3/data-props/once-props; `vendor/inertiajs/inertia-laravel/src` (`Middleware.php`, `ResponseFactory.php`, `OnceProp.php`, `PropsResolver.php`, `View/Components/App.php`) | The middleware's `shareOnce(Request)` is evaluated with `share()` and registered with `Inertia::shareOnce`; a once prop is left out when the request's `X-Inertia-Except-Once-Props` header names it, with its metadata kept; the root view embeds the first page in `<script data-page="app" type="application/json">` |
| Inertia, React adapter 3.9.1 | `node_modules/@inertiajs/core/dist/index.js` and its types | The client sends `X-Inertia-Except-Once-Props` with the once props it holds and puts the held value back into each later page (`mergeOncePropsIntoResponse`); `createInertiaApp`'s `setup` receives `props.initialPage` |
| Carbon 3.14.2 | `vendor/nesbot/carbon` and a run in the container | `Carbon::setTestNow()` also sets `CarbonImmutable::now()`; an instant serializes to JSON as `2026-10-01T02:15:00.000000Z`; `diffInDays()` returns a float, so the kernel counts days with `DateTime::diff()->days` |
| PHPUnit 13.4.3 | a run | `assertContainsOnly()` is gone; `assertContainsOnlyString()` replaces it |
| ESLint 10.13.0, TypeScript 6.0.3, Vitest 5.0.5, Node.js 24.22.0 | Proven by the negative samples and tests of section 7 | `no-restricted-syntax` selectors; a block of a later flat-config entry replaces the rule's options; TypeScript diagnostics TS2345 and TS2554; Node.js applies a changed `TZ` at run time |

Checked by the remediation on 20 October 2026 (section 10), in the installed source of the pinned versions and by runs in the containers; no dependency was added or changed:

| Component, pinned | Source checked | Fact relied on |
| --- | --- | --- |
| Laravel 13.37.0 | `Foundation/Http/Middleware/TrimStrings.php` and `TransformsRequest.php`; `Foundation/Configuration/Middleware.php`; `Validation/ValidationException.php` | `TrimStrings::transform()` receives the dot path of each value (`cleanArray` joins the keys with dots) and trims with `Str::trim`, for the query string and for the JSON or form body; `Middleware::replace($search, $replace)` swaps a global middleware; `ValidationException::summarize()` looks up "The given data was invalid." with `get()` and "(and :count more error)" or "(and :count more errors)" with `choice()` |
| Inertia, Laravel adapter 3.5.1 | `src/PropsResolver.php`, `src/Middleware.php`, `src/ResponseFactory.php`, `src/Support/Header.php` | A request is partial when `X-Inertia-Partial-Component` equals the rendered component; a loaded once prop is left out only of a visit that is not partial; on a partial request a prop is kept unless `only` leaves it out or `except` names it; shared props live on the response factory, which `share()` merges into and `flushShared()` empties, for the life of the process |
| PHPStan 2.3.1, PHP-Parser 5.9.0 | `vendor/phpstan/phpstan` (the `Type` API) and runs of the float rule on its samples | `Type::isInteger()`, `isFloat()`, `isArray()`, `getIterableValueType()`, `getConstantScalarValues()`, `getConstantScalarTypes()` and `getConstantStrings()`; `Node\Arg` and `Node\VariadicPlaceholder` in `FuncCall::$args` |
| ESLint 10.13.0 with esquery 1.7.0, typescript-eslint 8.71.2, eslint-plugin-react 7.37.5 | A probe in the node container, then the samples of section 6 | Regular expressions with the `u` flag (`/\p{L}/u`) in attribute selectors; `:not()` and `:matches()` with descendant and child selectors; field selectors (`Identifier.object`); `:first-child` among a call's arguments; `[expressions.length=0]`; the TypeScript parser reads `.js`, `.jsx`, `.mts` and `.cts` files given in `files` |

## 3. What was built (contract section 4.1)

| Item | Built | Files |
| --- | --- | --- |
| 1. Value objects | Money on `BigInteger`, signed and unbounded, with `assertStored()` (0 to 999,999,999,999) and `assertVariance()` (±999,999,999,999); Quantity on `BigDecimal` at the product's precision 0 to 3, signed, with `assertLine()`, `assertMovement()` and `assertStock()`; Cost on `BigDecimal` at scale 6, KNOWN or UNKNOWN, never negative, with `assertUnit()` and `assertValue()`. Canonical parsing (`CanonicalNumber`) refuses every non-canonical form before Brick Math sees it; `Money::lineTotal()` rounds half-up once and refuses an overflow; `Cost::mulDiv()` and `Cost::quotient()` divide once at scale 6, half-up (ADR-008 section 5); UNKNOWN propagates and is never zero | `app/Support/Numbers/` |
| 2. Eloquent casts | `MoneyCast` (`bigint`), `QuantityCast` (`numeric`, precision from an argument or from the row) and `CostCast` (a KNOWN amount column, or the amount and status pair of SCHEMA section 3.2), with no float either way; their database round-trip is TASK-004's | `app/Support/Casts/` |
| 3. Validation rules | `MoneyRule`, `QuantityRule` (line and movement quantities) and `CostRule`, refusing JSON numbers and every non-canonical form with the COPY_DECK section 16.1 messages | `app/Support/Validation/` |
| 4. Clock boundary | `Clock` (`#[Bind(SystemClock::class)]`), `SystemClock` (UTC, following Carbon's test time) and `TestClock` (controllable); `BusinessDate` (the Asia/Jakarta date of an instant); `Period` with `PeriodKind` (`today`, `7d`, `this_month`, `last_month`, `custom`) as pure functions of "now", half-open UTC ranges, and `InvalidPeriod` for the refusals of TECHNICAL_RULES section 11.4 | `app/Support/Time/` |
| 5. Formatters | `Format` on the server and `resources/js/lib/format.ts` on the client: money, negative money, quantity, cost on screen, date, date and time, time of day, day name, counts, durations and the variance words; the WIB marker through the copy that carries it (R-07). Durations were built by the remediation with the keys of CC-21 (R-25; F-1, section 5) | `app/Support/Formatting/Format.php`, `resources/js/lib/format.ts` |
| 6. Exact numbers in TypeScript | Scaled `BigInt` helpers (`parseExact`, `toCanonical`, `roundHalfUp`, `lineTotal`, `sumMoney`, `subtractMoney`, `compareMoney` and the ranges); lint rules refusing `Number()`, `parseFloat`, `parseInt`, unary `+`, `Intl.NumberFormat`, `Intl.DateTimeFormat` and the `toLocale…String` methods in interface code | `resources/js/lib/exact.ts`, `eslint.config.js` |
| 7. Localization | `lang/id/` with the twelve files of COPY_DECK section 1.1; locale and fallback `id`, no switcher; the dictionary shared once by `HandleInertiaRequests::shareOnce`; `t(key, replacements)` typed with a key and placeholder type generated from `lang/id/`; the build checks; `Lang::handleMissingKeysUsing` throwing in tests | `lang/id/`, `app/Support/Localization/Dictionary.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/lib/i18n.ts`, `resources/js/types/translation-keys.ts`, `resources/js/app.tsx`, `scripts/checks/translation-types.php`, `scripts/checks/translation-keys.php`, `tests/TestCase.php`, `tests/MissingTranslationKey.php` |

`lang/id/ux.php` holds 32 keys, each copied verbatim from COPY_DECK and proven equal to it by `CopyDeckTest`: the 12 month names and 7 day names of section 1.3, the 2 unit words of durations of section 1.3 (`ux.format.duration.minute` "menit" and `ux.format.duration.second` "detik", CC-21, added by the remediation), the 4 keys of section 1.4, and the 7 validation messages of section 16.1 that the rules of item 3 use. The other eleven files return an empty array. No page key was added.

## 4. Readings and technical choices

The readings of the instruction (its section 3), each as applied:

- **Formats.** The formats of COPY_DECK sections 1.3 and 1.4 as they stand at `fc0055b`; no percent format and no T-19 or T-20 proposal. Month and day names come from `ux.format.*`; the formatters give the same output under other time zones and locale settings (section 7, AC-003-04).
- **Keys.** `lang/id/` holds the files of COPY_DECK section 1.1, the `ux.format.*` keys and the keys the Task's own code uses (the seven validation messages), and no page key. The one text with no key, the unit words of durations, is stop condition 7 (F-1).
- **Exactness.** No floating point in money, quantity or cost, enforced by the float static check, now extended to calls that return a float (R-20); every value travels as a string; non-canonical input is refused; a line total is rounded half-up once (ADR-006 item 4); cost uses scale 6 with the single half-up rounding of ADR-008 section 5.
- **Clock.** STK-31: the clock is UTC with a controllable test clock; the business date is the Asia/Jakarta date; the five half-open periods are pure functions (BR-COST-37).
- **Client.** Scaled `BigInt`; the lint rules; the dictionary shared once through `Inertia::shareOnce`; `t()` typed from a key type generated from `lang/id/`; a build check failing on a missing key; `Lang::handleMissingKeysUsing` throwing in tests.
- **Samples.** The negative samples are in `tests/Fixtures/StaticChecks/`, which the build, the linters and the analysis exclude; none holds a secret-looking value (EXC-63).

The technical choices, for the reviewer:

| ID | Choice | Why |
| --- | --- | --- |
| R-01 | The leaves of `ux.format.month.*` and `ux.format.day.*` are the English names in snake_case (`january`, `monday`). | COPY_DECK section 1.3 names the two groups and gives the texts in order but not the leaves; section 1.1 asks for stable English snake_case keys. `CopyDeckTest` ties each leaf to its place in the rows "Months" and "Days" |
| R-02 | A canonical string matches `^-?(0|[1-9][0-9]*)(\.[0-9]+)?$`: a sign only for a signed kind (money, quantity), never `-0`; a point is followed by a digit; trailing zeros within the kind's scale are accepted ("1.50" at precision 2), and the fractional digits as written count against it ("1.0" is refused at precision 0). Every value is written back in its shortest form ("1.5", "12000"). A canonical string of more than 40 characters is outside every range and refused before Brick Math. | ADR-006 item 2 lists what is refused; it forbids no trailing zero; one output form keeps transport and tests simple |
| R-03 | An UNKNOWN cost travels as the string `"UNKNOWN"` (`Cost::UNKNOWN`), never `0` or a number. | ADR-006 item 1's "explicit UNKNOWN marker" |
| R-04 | A quantity is shown without trailing zeros, never with more decimals than its precision: "1.250,5 kg" from `numeric(13,3)` "1250.500". | The example of COPY_DECK section 1.3 |
| R-05 | A negative quantity and a negative cost figure take the minus sign U+2212 of negative money. | COPY_DECK section 1.3 names U+2212 for money; one sign for every negative figure |
| R-06 | A cost on screen is whole rupiah, half-up — ties away from zero, also for a negative figure such as a gross loss — once, at the end; the formatter takes a KNOWN cost or a signed figure, and an UNKNOWN cost is refused, never shown as an amount. | BR-NUM-03, ADR-008 section 5, UXS-24 |
| R-07 | "WIB" is part of the copy that a rule asks for — `reporting.as_of` ("Data per :waktu WIB") and `documents.session_report.time_zone` — which the Tasks that show them add; the formatters give `:waktu`. No separate WIB format or key is built. | COPY_DECK section 1.3 (Time zone) and those two keys |
| R-08 | A count is its number with dot thousands separators ("5.000"); the counted noun is in the copy that holds the count. | COPY_DECK section 1.3 (Counts) and section 1.1 (no sentence built from keys) |
| R-09 | Durations: built by the remediation, as reading R-25. | F-1 closed by CC-21 and D-099 (section 5) |
| R-10 | `today` is [today 00:00, tomorrow 00:00); `7d` is today and the six dates before; `this_month` ends at the "now" given, the report's snapshot; `last_month` is the whole previous month; `custom` is inclusive and ends at the start of the day after its last date. The kernel refuses a custom period that starts after its end, ends after today in Asia/Jakarta or is longer than 366 days, with a reason code; the page refuses it at its fields (TASK-040). | BR-COST-37, BR-NUM-06, INTERACTION_CONTRACTS section 3.4 ("until now ends at the snapshot"), TECHNICAL_RULES section 11.4 |
| R-11 | The clock is an interface bound by `#[Bind]` to `SystemClock`, which reads Carbon's clock in UTC, so Laravel's own test time controls it too; `TestClock` stands still until a test moves it. It lives in `app/Support/Time` so that every later Task's tests can bind it. | STK-31; no service provider is needed |
| R-12 | The client shows instants in Asia/Jakarta with the fixed offset UTC+07:00 — Western Indonesia Time has had no daylight saving since 1964 — read only through `getUTC…` methods; the server uses the time-zone database. Both give the same results on the table of cases. | No browser time zone or locale data takes part |
| R-13 | Instants reach the client as Carbon serializes them, `YYYY-MM-DDTHH:MM:SS.ffffffZ`; the client accepts 0 to 6 decimals of a second and the `Z`, and refuses anything else. Business dates travel as `YYYY-MM-DD`. | Carbon 3.14.2's JSON form |
| R-14 | Validation messages: `MoneyRule` uses `ux.validation.money` for every non-canonical form, `money_min` for a negative amount and `money_max` above the range; `QuantityRule` uses `quantity_positive` for a non-canonical form, zero or a negative value, `quantity_whole` or `quantity_decimals` for too many decimals, and `quantity_max` above the range; a blank field is the FormRequest's `required`. `QuantityRule` serves line and movement quantities only; a minimum-stock field has no COPY_DECK "too large" message (F-7). `CostRule` takes its two messages from the FormRequest, because COPY_DECK names none for a cost field (F-7). | COPY_DECK section 16.1 |
| R-15 | The interface dictionary is the twelve files of COPY_DECK section 1.1, flattened to full dot keys, shared as the once prop `dictionary`, installed by `app.tsx` from the first page and read by `t()`; a key that the dictionary lacks makes `t()` throw, never show the key. | ADR-005 item 4 |
| R-16 | `scripts/checks/translation-types.php` generates `resources/js/types/translation-keys.ts`: every key with the names of its `:name` placeholders. `t()` requires exactly those replacements, and its type is distributive, so a key missing from `lang/id/` is reported as the wrong key (TS2345). | ADR-005 item 5 |
| R-17 | The missing-key handler of tests throws for every missing key except the four groups the validator probes and never prints (F-2); the default message `validation.<rule>` of a built-in rule throws, because it would be printed. | ADR-005 item 5: "a lookup that would fall back to printing its key fails" |
| R-18 | "A missing key fails the build": `npm run build` runs `tsc --noEmit` before `vite build`, so the typed `t()` fails it; `composer lint` runs the key check and `translation-types.php --check`, which fails when the key type differs from `lang/id/`. The key check also refuses literal text in Blade views and in the PHP messages that reach the user (`abort()`, `abort_if()`, `abort_unless()`, `$fail()`, `withMessages()`, `withErrors()`), the part that the TASK-001 evidence (section 7.4) left to TASK-003. | ADR-005 item 5, L10N01, TEST_STRATEGY section 6 |
| R-19 | Without type information a lint selector cannot tell an exact value from another number, so the conversions are refused in all interface code (`resources/js/**`); `parseInt`, `Intl.DateTimeFormat` and the `toLocale…String` methods are refused too. The kernel's TypeScript needs none of them: it reads digits itself. | ADR-006 item 5, ADR-005 item 6 |
| R-20 | The float static check also refuses, in `app/Support` and `app/Domain`, a call whose type is or may be a float, such as Brick Math's `toFloat()` or Carbon's `diffInDays()`; two lines were added to its negative sample. | AC-003-03 |
| R-21 | Kernel refusals are exceptions: `NonCanonicalNumber` (an `InvalidArgumentException`), `NumberOutOfRange` (a `RangeException`) and `InvalidPeriod` with its reason; the Tasks with commands map them to `VALIDATION_FAILED`. | TECHNICAL_RULES section 4 |
| R-22 | Money is unbounded as a value, so report aggregates stay exact (TECHNICAL_RULES section 11.1, "no fixed cap"); a stored or entered value is checked where it is stored or entered. | BR-NUM-04 |
| R-23 | The shared tables of cases are JSON files in `tests/Unit/Support/cases/`, read by PHPUnit and Vitest alike. | One table, two runs (AC-003-04) |
| R-24 | No layout adaptation under STK-22: every file is in a folder of STACK_AND_MODULES section 3 (`app/Support/…`, `lang/id/`, `resources/js/lib/`, `resources/js/types/`, `scripts/checks/`, `tests/Unit/`, `tests/Feature/`, `tests/Fixtures/`). STACK_AND_MODULES and TOOLCHAIN are unchanged: no library was added. Brick Math 1.0.0, `PINNED` in TOOLCHAIN, now runs in the command contract. The remediation adds `app/Http/Middleware/TrimStrings.php` beside the existing `HandleInertiaRequests`, in the folder Laravel gives middleware, and no other layout. | Contract section 11 |

The readings of the remediation (section 10), R-25 for the duration formatter and R-26 onward for the findings of the class-A review, each where several mechanisms give the decision's required outcome:

| ID | Choice | Why |
| --- | --- | --- |
| R-25 | Durations: `Format::duration($amount, $unit)` on the server and `formatDuration(amount, unit)` on the client. The caller chooses the unit, `minute` or `second`; the amount is a whole number of 0 or more — a PHP `int` on the server, a safe integer or a `bigint` on the client. The output is the amount in the count format, a space (U+0020) and the unit word of `ux.format.duration.minute` or `ux.format.duration.second`: "60 menit", "30 detik", "1.500 detik". There is no conversion between units and no combined unit. A negative amount, an amount that is not an integer — a float, a numeric string — and an unknown unit are refused (`InvalidArgumentException`, `RangeError`). The client's `formatCount` now refuses every value that is not a safe integer or a `bigint`, a string included. | COPY_DECK section 1.3, "a number and a unit word" (CC-21) |
| R-26 | A-01: `App\Http\Middleware\TrimStrings` replaces Laravel's `TrimStrings` in the global middleware (`Middleware::replace`). It trims exactly as before and records, in an attribute of the request, the dot path of every value it changed, for the query string and for JSON, urlencoded and multipart bodies; `TrimmedInput::wasTrimmed($attribute)` lets `MoneyRule`, `QuantityRule` and `CostRule` refuse a value at such a path with the message they give a non-canonical value (`ux.validation.money`, `ux.validation.quantity_positive`, the invalid message of `CostRule`). The path is the attribute that the validator gives the rule, for example `lines.1.qty`, so the field's name does not matter; text fields keep their trimming. A path trimmed in the query string also refuses the same path of the body, which fails closed. Empty strings and `ConvertEmptyStringsToNull` are unchanged: a field's `required` or `nullable` rule handles them. | The decision's preferred mechanism |
| R-27 | A-05: the client computes the calendar in integers — Howard Hinnant's `days_from_civil` and `civil_from_days`, the proleptic Gregorian calendar of PHP — and accepts and formats exactly the business dates the server accepts, 0001-01-01 to 9999-12-31; year 0000 is refused on both sides. `format.ts` no longer uses `Date` at all, also for instants (R-31). No new range rule is added. | Parity, as the decision requires |
| R-28 | A-08: lint refuses a string literal or a template element with a letter or a digit anywhere inside a JSX expression container or a spread attribute of `.tsx` and `.jsx` interface code. Not refused: the first argument of `t()` (also `i18n.t`); anything inside the allowlisted attributes that never hold text — `className`, `class`, `type`, `role`, `key`, `id`, `htmlFor`, `for`, `name`, `href`, `src`, `srcSet`, `rel`, `target`, `method`, `action`, `autoComplete`, `inputMode`, `encType`, `accept`, `dir`, `lang`, `tabIndex`, `form`, `style`, `data-testid` and the `aria-*` attributes that hold states or references; the same keys in a spread object; object keys; the operands of `===`, `!==`, `==` and `!=`, which yield a boolean; and the name of a computed member. Digits stay refused, as before the remediation. The seventh form of the review — a literal bound to a variable and rendered later — is a limit of lint: a syntactic rule cannot follow it without refusing every lettered string in interface code; code review covers it (EXC-70, EXC-71). | The decision, A-08 |
| R-29 | A-09 is split. (a) The test handler (`Tests\MissingTranslationKey::isFrameworkProbe`) lets exactly the three lookups of Laravel's `ValidationException::summarize()` through — "(and :count more error)", "(and :count more errors)" and "The given data was invalid." — so that a request with several invalid fields answers 422 in tests, with each field's COPY_DECK message. (b) The production body of a 422 response, which must never carry that English summary, belongs to the error body of RT-07 that TASK-008 builds (AC-008-08): OPEN_ITEMS T-26, mapped to TASK-008 in TASK_PLAN section 7. The review's test of the production text is therefore not adopted here. | The decision, A-09; TASK-008 scope item 7 |
| R-30 | A-13: a zero divisor of `Cost::quotient()`, and so of `Cost::mulDiv()`, is refused with `NumberOutOfRange` ("cost divisor"), one of R-21's kernel exceptions, before Brick Math divides; an UNKNOWN value stays UNKNOWN whatever the divisor. | R-21 |
| R-31 | A-14: both formatters refuse an instant before 1964-01-01 00:00 in Asia/Jakarta, `1963-12-31T17:00:00Z` (Unix time −189414000), the scope of the client's fixed offset (R-12): the server compares the instant's Unix seconds and throws `InvalidArgumentException`, the client compares the instant's `YYYY-MM-DDTHH:MM:SS` text and throws `RangeError`. Business dates stay as in A-05; `BusinessDate::ofInstant()` is unchanged. | The decision, A-14 |
| R-32 | A-15: `scripts/checks/translation-types.php` refuses a placeholder whose name starts with an upper-case letter (`:Name`, `:NAME`; any Unicode upper-case letter) in any text of `lang/id`, in `--check` and in generation alike, before it writes anything. The client's `upperFirst` is unchanged; no COPY_DECK text uses such a placeholder. The differential placeholder cases are generated within what `lang/id` may hold: lower-case placeholders and names (R-41). | The decision, A-15 |
| R-33 | A-02: in `exactValueDirectories` the float rule (`tests/PHPStan/FloatInExactValuesRule.php`) now also refuses every expression whose type is, may be or holds in an array a float; arithmetic (`+`, `-`, `*`, `%` and their assignments), numeric comparison (`<`, `<=`, `>`, `>=`, `<=>`, `==`, `!=`), increment, decrement and sign of an operand that is not an integer; integer arithmetic next to the ends of the integer range (a constant of magnitude 2^62 or more, such as `PHP_INT_MAX`); `(string)` and `(int)` casts of a value that may be a float; `sort`, `rsort`, `asort`, `arsort`, `ksort`, `krsort` without `SORT_STRING` and `array_unique` with another flag; `max()` and `min()` of values that are not integers; a `printf`-family format that is not a literal or that converts through a float; `settype()`; `json_decode()`; and the name of a float function passed as a callable. The project's own exact-value code needed one change and no allowlist entry: `BusinessDate::compareTo()` compares its two `YYYY-MM-DD` strings with `strcmp()` instead of `<=>`, with the same result. | The decision, A-02 |
| R-34 | A-03: the exact-number rules (`eslint.config.js`) apply to every script extension under `resources/js` — `.ts`, `.tsx`, `.mts`, `.cts`, `.js`, `.jsx`, all with the TypeScript parser — and refuse the names `Number`, `parseFloat`, `parseInt`, `Intl`, `JSON` and `Date` wherever they are referenced: called, passed as a callback, aliased, destructured, through `window`, `globalThis`, `self` or `global`, or reached by computed access with a string or a static template literal. Allowed: the integer-only members `Number.isSafeInteger`, `Number.isInteger`, `Number.MAX_SAFE_INTEGER` and `Number.MIN_SAFE_INTEGER`; `JSON.stringify`; `Date.now()` for a timer; `Date` as a type; a property or key of the same name. Also refused: the `toLocale…` members and the local-time members of `Date` (`getHours`, `getTimezoneOffset`, `toDateString` and the like), by name or computed access, and unary `+`. `JSON.parse` is allowed in no module today; a later Task that needs it may allow it in one module that keeps exact values as strings. Limit: a `Date` that reaches interface code from the DOM or a library and is turned into text by `toString()` or a template cannot be told from other `toString()` calls without type information; interface code cannot create one, and code review covers the rest. | The decision, A-03 |
| R-35 | A-06, TypeScript: lint refuses, outside `resources/js/lib/i18n.ts` and the generated `resources/js/types/translation-keys.ts`, every type assertion (`as`, `<…>`) whose type names `TranslationKey` or `TranslationPlaceholders`, alone or inside another type (`TranslationKey[]`, `TranslationKey \| undefined`), or the type of `t()` (`Parameters<typeof t>`); every reference to `TranslationPlaceholders`, the internal of `t()`; and a type alias that only renames or narrows the key type (`type K = TranslationKey`, `Extract<TranslationKey, …>`). A key typed `TranslationKey` without an assertion — a value of a typed map, a parameter — stays allowed, because the typecheck proves it. The key check reads `t(` also as a member (`i18n.t(`), with space, a line break or a comment before the key. Limit: a key type derived in another module through an indexed access, a type predicate that lies or `any` is not syntactic; code review covers it. | The decision, A-06 |
| R-36 | A-06, PHP, and A-07: `scripts/checks/translation-keys.php` reads PHP by tokens. Lookups: `__`, `\__`, `trans` with arguments, `trans_choice`, the `Lang` facade as `Lang`, `\Lang`, by its full name or under an alias of a `use` statement, `get` and `choice` on `app('translator')`, `\app('translator')`, `resolve('translator')` and `trans()`, with positional or named arguments; in Blade the same inside echoes, PHP blocks, directives and bound attributes, and `@lang` and `@choice`. A key that is not a single string literal is refused (`NOT A CONSTANT KEY`), and a literal key missing from `lang/id` (`MISSING`). Sinks, where a literal with a letter is refused (`LITERAL`): those of the review — the values of `messages()`, `Inertia::render` and `inertia()` props, `with()` and `flash()`, `response()->json()`, `errors()->add()`, the messages and attributes of `Validator::make`, and the message arguments of `new CostRule` — with their neighbours `attributes()`, `Inertia::share`, `Session::flash`, `Response::json`, `Validator::validate`, `validator()` and `validate()`, and the existing `abort`, `abort_if`, `abort_unless`, `$fail`, `withMessages` and `withErrors`. Not text: an array key, an array offset, a comparison operand, and the arguments of another call (a route name, a configuration key), except the functions that pass text on (`sprintf`, `ucfirst`, `trim`, `e` and the like). Blade: text between tags, every attribute except the reviewed allowlist `NON_TEXT_ATTRIBUTES` of the script and the `content` of `<meta name="viewport">`, literals echoed or bound to a component attribute, the second argument of `@section` and `@yield`, and the array values given to any directive. The allowlist holds attributes that never carry text: `accept`, `action`, `allow`, `as`, `autocomplete`, `autofocus`, `charset`, `checked`, `class`, `colspan`, `cols`, `crossorigin`, `d`, `data-page`, `data-testid`, `decoding`, `dir`, `disabled`, `enctype`, `fill`, `for`, `form`, `headers`, `height`, `hidden`, `href`, `http-equiv`, `id`, `inputmode`, `integrity`, `lang`, `list`, `loading`, `max`, `maxlength`, `media`, `method`, `min`, `minlength`, `multiple`, `name`, `nonce`, `novalidate`, `open`, `pattern`, `readonly`, `referrerpolicy`, `rel`, `required`, `role`, `rows`, `rowspan`, `sandbox`, `scope`, `selected`, `size`, `sizes`, `spellcheck`, `src`, `srcset`, `step`, `stroke`, `style`, `tabindex`, `target`, `translate`, `type`, `viewbox`, `width`, `wrap`, `xmlns`, and the `aria-*` attributes that hold states or references. | The decision, A-06 and A-07 |
| R-37 | A-10: `HandleInertiaRequests::shareOnce()` leaves the dictionary out of a partial reload — a request with `X-Inertia-Partial-Component`, from `router.reload()` with `only`, `except` or `reset` — unless its `X-Inertia-Partial-Data` names `dictionary`, because Inertia 3.5.1 leaves a loaded once prop out of full visits only. Its `handle()` first drops a dictionary that an earlier request of the same process left on Inertia's response factory, which keeps shared props for the life of the process (in tests, or in a long-running worker). `t()` keeps the dictionary installed from the first page of the full page load. | The decision, A-10 |
| R-38 | A-12: `QuantityCast::set()` writes the quantity at the row's precision through `Quantity::fromBigDecimal()`, so a quantity with more significant decimals than the row's precision is refused (`NonCanonicalNumber`) before any write, and so is one whose row has no precision yet: the precision is set first. `MoneyCast::set()` refuses an amount outside the `bigint` range, −9223372036854775808 to 9223372036854775807 (`NumberOutOfRange`). | ADR-006 item 3 |
| R-39 | A-19: `TestClock` takes a `DateTimeInterface`, or a string that is an absolute instant: `YYYY-MM-DD`, `T` or a space, `HH:MM`, optional seconds with up to six decimals, and `Z` or an offset `±HH:MM` or `±HHMM`. Anything else — `now`, `tomorrow`, a date alone, a date and time without an offset, a time-zone name, a Unix timestamp — is refused with `InvalidArgumentException`. | The decision, A-19 |
| R-40 | A-11 and A-18: the factories of Money, Quantity and Cost, `CanonicalNumber::parse()`, `Format::count()` and `Format::duration()` take `mixed` as their native parameter type, keep their PHPDoc type, which level 9 still checks at every call, and check the type at run time: a value that is not a string (for `Money::fromDatabase`, an int or a string; for `Cost::fromDatabase`, a string or null) is refused with `NonCanonicalNumber`, and a count or a duration that is not an int with `InvalidArgumentException`. PHP's coercive typing would otherwise have turned a float into a string or an int before the method saw it. | The decision, A-11 and A-18 |
| R-41 | The differential run of the review, adopted (decision section 3): `tests/Unit/Support/Differential/generate-cases.mjs` writes the inputs with the fixed seed 20261020; `expected.php`, run by hand, computes the server's outcome of every case once with `ServerOutcomes` and writes it, with the `ux.format` texts, into the same file; `DifferentialCasesTest` on the server and `tests/Unit/Support/differential.test.ts` on the client only read the committed table `tests/Unit/Support/cases/differential.json`, or the file that `DIFFERENTIAL_CASES` names, and require the expected outcome, the client also against independent BigInt oracles and in other time zones with every source of locale data made to throw. No test writes a file, and neither run depends on the other. The committed table is generated at scale 0.1 (8,869 outcomes); the full-size table of the review, scale 1, ran once locally outside the tracked tree (section 6.2). Durations joined the categories; the dates and instants include year 0000, year 10000 and the boundaries of A-14. | Decision section 3 |

## 5. Findings

| ID | Finding | Status |
| --- | --- | --- |
| F-1 | **Stop condition 7 (EXECUTION_CONTEXT section 14).** COPY_DECK section 1.3 gives the format of durations — "A number and a unit word", for example "60 menit; 30 detik" — but names no key for the unit words, unlike the month and day names (`ux.format.month.*`, `ux.format.day.*`). A duration formatter would have to write "menit" and "detik" as literals or invent keys; neither is allowed (COPY_DECK section 1.1, EXC-51). The duration formatter is therefore not built; everything independent of it is. The texts that need it are `ux.state.throttled`, `ux.state.session_warning`, `ux.announce.session_warning` (TASK-006) and `config.system_status.age` (TASK-039), whose `:durasi` holds a duration. Keys for the unit words — at least seconds and minutes, and whatever unit `config.system_status.age` shows — need COPY_DECK change control and the Owner's approval by hash before a Task builds the formatter | Closed by CC-21 and D-099: TASK-002 added `ux.format.duration.minute` "menit" and `ux.format.duration.second` "detik" to COPY_DECK section 1.3, approved by hash at gate GATE-CC1; the remediation added them to `lang/id` and built the formatter (R-25). A unit above minutes for `config.system_status.age` stays with OPEN_ITEMS T-25, for TASK-039 |
| F-2 | Laravel 13.37's validator looks up four optional groups of translations — `validation.attributes`, `validation.custom`, `validation.custom.<attribute>.<rule>` and `validation.values.<attribute>.<value>` — and falls back silently when they are absent. A handler that throws on every missing key fails every validation, so the handler lets exactly these four through (R-17). A consequence for every later FormRequest: a built-in rule such as `required` without a COPY_DECK message throws in tests, because its default `validation.<rule>` would be printed | Handled; noted for later Tasks |
| F-3 | ADR-006 item 4 and ADR-008 section 5 write `RoundingMode::HALF_UP`; Brick Math removed that constant in 0.15.0, and the pinned 1.0.0 names the same rule `RoundingMode::HalfUp`. The rule itself is met. The ADRs are not changed here (contract section 11) | Recorded for the planning agent |
| F-4 | In Laravel 13.37, `Translator::handleMissingTranslationKey()` turns missing-key handling off before calling the handler and turns it back on only when the handler returns; after the handler throws once, that translator ignores later missing keys. Each test has a new application, so this matters only after a first throw within one test, which already fails it | Recorded |
| F-5 | The client keeps a once prop only while it moves between pages that include it (Inertia v3 documentation). The dictionary is shared on every Inertia response by the middleware, so every page includes it | Recorded |
| F-6 | Brick Math runs its native calculator, because the image has neither GMP nor BCMath. It is exact; its speed is a matter for the performance Tasks (TASK-043, TASK-048) | Recorded |
| F-7 | COPY_DECK names no validation message for a cost field, and its "too large" message for quantities names the line maximum 99.999.999,999, which does not fit a minimum-stock field (maximum 9.999.999.999,999). `CostRule` takes its messages from the FormRequest, and `QuantityRule` serves line and movement quantities; the Tasks that add a cost field (TASK-021, TASK-025) or a minimum-stock field (TASK-015) take their messages from COPY_DECK, through change control where it has none | Recorded for those Tasks |
| F-8 | Found by the remediation after the merge of `develop` (section 10.2): COPY_DECK section 1.3 now also holds the percent format "One decimal with a decimal comma, directly followed by the percent sign" (32,0%), added by TASK-002 as CC-03 and approved by D-099. It is not among the formats that the contract's item 5 lists, nor in the remediation instruction or the decision, so it is not built: building it would be a scope the instruction does not authorize (its section "Not authorized"). AC-003-04 is read against the formats of contract item 5. The Task that first shows a percentage, or a decision of the planning agent, takes it | Assigned to TASK-040 (its scope item 2), built in the server and client formatters with shared cases (D-100) |

## 6. Tests

The tests of contract section 7, and what each proves:

| Test | Level, suite | Proves |
| --- | --- | --- |
| `tests/Unit/Support/Numbers/NumberCasesTest.php`, `tests/Unit/Support/exact.test.ts` | L1, `composer test:unit`, `npm run test:unit` | The shared table `numbers.json` on the server and the client: canonical strings, line and Sale totals (FIN01), half-up rounding |
| `tests/Unit/Support/Numbers/ValueObjectsTest.php` | L1 | The ranges of TECHNICAL_RULES section 11.1 at their boundaries; exact money arithmetic beyond the stored range; the reading of `bigint` and `numeric` values; UNKNOWN never zero and propagating; JSON transport as strings |
| `tests/Unit/Support/Numbers/CostArithmeticTest.php` | L1 | ADR-008 section 5: issue allocation, the final issue, a tie at the sixth decimal, one rounding per computation, the cumulative return rule without residue, unit cost and moving weighted average |
| `tests/Unit/Support/Casts/CastsTest.php` | L1 | The three casts in and out of a model without a database, floats and raw values refused both ways, JSON serialization as strings |
| `tests/Unit/Support/Validation/NumberRulesTest.php` | L1 | The three rules with their COPY_DECK messages, JSON numbers refused |
| `tests/Unit/Support/Formatting/FormatCasesTest.php`, `tests/Unit/Support/format.test.ts` | L1 | The shared table `formatting.json` on the server and the client, then again under other time zones — America/Los_Angeles, Pacific/Kiritimati and UTC on both, and Asia/Tokyo on the client — and other locale settings: on the server the process locales `C`, `C.utf8` and `POSIX` and the application locales `en`, `en_US` and `fr`; on the client `LANG` and `LC_ALL` set to `en_US.UTF-8` and every source of locale data — `Intl`, `navigator` and the `toLocale…String` methods of `Date`, `Number` and `BigInt` — made to throw if read |
| `tests/Unit/Support/Time/BusinessDateTest.php`, `PeriodTest.php`, `ClockTest.php` | L1 | Business dates across Asia/Jakarta midnight, the five periods as half-open UTC ranges and the custom refusals, all from the test clock; the clock binding |
| `tests/Unit/Localization/CopyDeckTest.php` | L1 (L10N01) | Every key of `lang/id/` and its text, verbatim from COPY_DECK |
| `tests/Unit/Localization/LocalizationTest.php` | L1 (L10N01) | The twelve files; locale and fallback `id`; a missing server key throws; the validator's probes do not, a built-in rule's default message does; the dictionary; a key that is not snake_case refused |
| `tests/Unit/Localization/PlaceholderCasesTest.php`, `tests/Unit/Support/i18n.test.ts` | L1 | The shared table `placeholders.json` through Laravel's translator and through `t()`; `t()` before the dictionary, with a bad dictionary and with a missing key |
| `tests/Feature/Localization/DictionaryOncePropTest.php` | L4, `composer test:postgres` | Through the HTTP kernel and a route that exists only in the test: a full page load carries the dictionary; a later visit of a client that holds it does not receive it; a visit of a client that does not hold it does |
| `tests/Unit/StaticChecks/TranslationKeysTest.php` | L1, negative samples | Missing keys and literal text in PHP messages and Blade fail; the compliant samples pass |
| `tests/Unit/StaticChecks/TranslationTypesTest.php` | L1, negative samples | A stale key type fails `--check`; the generated one passes |
| `tests/Unit/StaticChecks/typecheck.test.ts` | L1, negative samples | `t()` with a key missing from `lang/id/` (TS2345), without its placeholders (TS2554), with an extra one (TS2554) and with a missing value (TS2345) fails the typecheck; a correct call passes |
| `tests/Unit/StaticChecks/eslint.test.ts` | L1, negative samples | `Number()`, `parseFloat`, `Number.parseFloat`, unary `+`, `Intl.NumberFormat`, `toLocaleDateString`, `parseInt` and `window.Number` fail lint, also in TSX beside the literal rule; BigInt and integer checks pass |
| `tests/Unit/StaticChecks/PhpstanRulesTest.php` | L1, negative samples | The float rule reports its samples, now including `toFloat()` and `diffInDays()` (lines 31 and 32), and not the sample outside exact-value code |

Added or extended by the remediation (section 10), each a regression test of a finding of the class-A review or a case of its section 5 that the suite lacked:

| Test | Level, suite | Proves |
| --- | --- | --- |
| `tests/Feature/Validation/CanonicalNumberInputTest.php` | L4, `composer test:postgres` | A-01, A-09 (a): through the HTTP kernel and the global middleware, a number padded with any character that `TrimStrings` removes — space, tab, LF, CR LF, U+00A0, U+200B, U+FEFF, U+3000, NUL — is refused with the rule's own message for money, quantity and cost, in JSON, urlencoded and multipart requests, whatever the field is called (`harga_jual`, `lines.1.qty`); a text field keeps its trimming; JSON numbers are refused; two invalid fields answer 422 with both messages |
| `tests/Unit/Localization/LocalizationTest.php` | L1 | A-09 (a): exactly the three summary lookups of `ValidationException::summarize()` are probes, no other text |
| `tests/Unit/Support/Numbers/NoFloatTest.php` | L1 | A-11, A-18: from a file without `strict_types`, floats, ints and booleans given to every factory of Money, Quantity and Cost and to `CanonicalNumber::parse()` are refused; `Format::count()` refuses 1.5, 2.0, "5", null and true |
| `tests/Unit/Support/Numbers/CanonicalAndRangeTest.php` | L1 | A-13: a zero divisor of `Cost::mulDiv()` and `Cost::quotient()` is `NumberOutOfRange`; with the review's area 1 and 2 cases: 73 non-canonical forms refused by every kind, the ranges at the boundary and one past it, precisions, cost scale 6 and UNKNOWN, 20,000 random line totals against an integer oracle, no double rounding |
| `tests/Unit/Support/Formatting/FormatCasesTest.php`, `tests/Unit/Support/format.test.ts`, `tests/Unit/Support/cases/formatting.json` | L1 | F-1: the duration cases (R-25); A-05: business dates of the years 0001 to 0099 and their day names on both sides; A-14: instants before 1964-01-01 00:00 in Asia/Jakarta refused on both sides; A-16: cost figures with more than six decimals refused on both sides, and by `Format::cost()` itself |
| `tests/Unit/Support/client-edges.test.ts` | L1 | A-04: ten values that are not strings — JSON numbers, an unsafe one, a `bigint`, null, undefined, true, an array, objects — refused with `NonCanonicalNumberError` by the 13 exported helpers that reach `parseExact`; A-17: eight scales that are not non-negative integers refused by `roundHalfUp` and `unitsAt`; with the review's client cases: 27 malformed instants refused, counts, and `t()` never printing a key |
| `tests/Unit/Support/Casts/CastsRoundTripTest.php` | L1 | A-12: `set()` refuses a quantity with more decimals than the row's precision and one whose row has no precision yet, and money outside the `bigint` range, before any write; 3,000 random round trips without loss; wrong types refused both ways |
| `tests/Unit/Support/Time/PeriodOracleTest.php` | L1 | A-19: the test clock refuses 14 relative, local or partial strings and accepts absolute instants with `Z` or an offset; with the review's area 6 oracle: 4,000 random instants from 1970 to 2199, the business date and the five periods against an integer oracle, and the named boundaries |
| `tests/Unit/StaticChecks/PhpstanRulesTest.php`, `tests/Fixtures/StaticChecks/Php/Floats/FloatBypassSamples.php`, `FloatControls.php` | L1, negative samples | A-02: the float rule reports the 14 operations it let through (B04 to B10, B13, B17, B20, B27 to B30) and 16 neighbours (B31 to B48), the 12 already reported stay reported, and exact code as the kernel writes it is not reported |
| `tests/Unit/StaticChecks/eslint.test.ts` and its samples `exact-number-bypasses.ts`, `exact-number-bypass.{mts,cts,js,jsx}`, `exact-number-controls.ts`, `jsx-literal-bypasses.tsx`, `jsx-literal-controls.tsx`, `key-assertions.ts`, `key-assertions-allowed.ts` | L1, negative samples | A-03: the 13 forms of the review and 22 neighbours, 3 of them other script extensions; A-08: the six forms and 10 neighbours; A-06: 13 assertions to `TranslationKey`; their controls pass, and `i18n.ts` and the generated key file may assert |
| `tests/Unit/StaticChecks/TranslationKeysTest.php` and the samples in `tests/Fixtures/StaticChecks/translation/bypass/` | L1, negative samples | A-06: every entry point of the translator with a missing or non-literal key in PHP (21 lines), Blade (3) and TypeScript (5); A-07: the 12 sinks of the review with their neighbours (18 PHP lines, 10 Blade lines); 28 controls are not reported |
| `tests/Unit/StaticChecks/TranslationTypesTest.php` | L1, negative samples | A-15: a capitalized placeholder fails `--check` and the generation, without writing |
| `tests/Feature/Localization/DictionaryOncePropTest.php` | L4 | A-10: a partial reload with `only`, `except` or `reset` does not carry the dictionary unless its `only` names it |
| `tests/Unit/Support/Differential/DifferentialCasesTest.php`, `tests/Unit/Support/differential.test.ts`, `tests/Unit/Support/cases/differential.json` | L1 | The differential run of the review (R-41): 8,869 outcomes of 19 categories on the server and the client, against the committed expected outcomes and independent BigInt oracles, and the formats again under four other time zones, process locales and application locales on the server and four time zones with throwing locale data on the client |
| `tests/Unit/Support/Numbers/CostArithmeticTest.php`, `tests/Unit/Support/Validation/NumberRulesTest.php` | L1 | The review's area 2 and 7 cases the suite lacked: the cumulative return rule over 2,000 random partial-return sequences; a five-thousand-digit amount and a trailing zero beyond precision 1 with their messages |
| `tests/Feature/Numbers/PdoTypesTest.php` | L2, `composer test:postgres` | The review's area 3 case: what PDO gives on PostgreSQL 18.6 for `bigint`, `numeric` and their aggregates, read with a SELECT of literals only, and that the kernel reads each without a float |

The standing obligations of EXECUTION_CONTEXT section 10 do not apply: TASK-003 adds no route, table, command, read, page, operator procedure or document. The routes of `DictionaryOncePropTest` and `CanonicalNumberInputTest` are registered by those tests only.

### 6.1 The table of cases (contract section 8)

Every case below ran on the server (PHPUnit) and on the client (Vitest) on the revision of section 7 and passed on both; "refused" means the input is refused with the kernel's exception.

#### Canonical strings

| # | Kind | Input | Expected | Server | Client |
| --- | --- | --- | --- | --- | --- |
| N1 | money | `"0"` | `"0"` | PASS | PASS |
| N2 | money | `"75000"` | `"75000"` | PASS | PASS |
| N3 | money | `"-2000"` | `"-2000"` | PASS | PASS |
| N4 | money | `"999999999999"` | `"999999999999"` | PASS | PASS |
| N5 | money | `"-0"` | refused | PASS | PASS |
| N6 | money | `"00"` | refused | PASS | PASS |
| N7 | money | `"075000"` | refused | PASS | PASS |
| N8 | money | `"+75000"` | refused | PASS | PASS |
| N9 | money | `"75000.0"` | refused | PASS | PASS |
| N10 | money | `"75000.5"` | refused | PASS | PASS |
| N11 | money | `"75.000"` | refused | PASS | PASS |
| N12 | money | `"75,000"` | refused | PASS | PASS |
| N13 | money | `"1.234.567"` | refused | PASS | PASS |
| N14 | money | `"1 234 567"` | refused | PASS | PASS |
| N15 | money | `"1_000"` | refused | PASS | PASS |
| N16 | money | `"7.5e4"` | refused | PASS | PASS |
| N17 | money | `"75E3"` | refused | PASS | PASS |
| N18 | money | `"0x10"` | refused | PASS | PASS |
| N19 | money | `" 75000"` | refused | PASS | PASS |
| N20 | money | `"75000 "` | refused | PASS | PASS |
| N21 | money | `"75000\n"` | refused | PASS | PASS |
| N22 | money | `""` | refused | PASS | PASS |
| N23 | money | `"-"` | refused | PASS | PASS |
| N24 | money | `"Rp75000"` | refused | PASS | PASS |
| N25 | money | `"NaN"` | refused | PASS | PASS |
| N26 | money | `"Infinity"` | refused | PASS | PASS |
| N27 | money | `"١٢"` | refused | PASS | PASS |
| N28 | money | `"７５"` | refused | PASS | PASS |
| N29 | money | `"10000000000000000000000000000000000000000"` | refused | PASS | PASS |
| N30 | quantity, precision 3 | `"1.5"` | `"1.5"` | PASS | PASS |
| N31 | quantity, precision 3 | `"1.500"` | `"1.5"` | PASS | PASS |
| N32 | quantity, precision 3 | `"0.001"` | `"0.001"` | PASS | PASS |
| N33 | quantity, precision 3 | `"0.0"` | `"0"` | PASS | PASS |
| N34 | quantity, precision 3 | `"-1.5"` | `"-1.5"` | PASS | PASS |
| N35 | quantity, precision 3 | `"99999999.999"` | `"99999999.999"` | PASS | PASS |
| N36 | quantity, precision 3 | `"1.2345"` | refused | PASS | PASS |
| N37 | quantity, precision 3 | `"-0.000"` | refused | PASS | PASS |
| N38 | quantity, precision 3 | `"1."` | refused | PASS | PASS |
| N39 | quantity, precision 3 | `".5"` | refused | PASS | PASS |
| N40 | quantity, precision 3 | `"01.5"` | refused | PASS | PASS |
| N41 | quantity, precision 3 | `"1,5"` | refused | PASS | PASS |
| N42 | quantity, precision 3 | `"1.5e0"` | refused | PASS | PASS |
| N43 | quantity, precision 2 | `"1.50"` | `"1.5"` | PASS | PASS |
| N44 | quantity, precision 2 | `"1.505"` | refused | PASS | PASS |
| N45 | quantity, precision 1 | `"1250.5"` | `"1250.5"` | PASS | PASS |
| N46 | quantity, precision 0 | `"3"` | `"3"` | PASS | PASS |
| N47 | quantity, precision 0 | `"3.0"` | refused | PASS | PASS |
| N48 | quantity, precision 0 | `"1.5"` | refused | PASS | PASS |
| N49 | cost | `"12000"` | `"12000"` | PASS | PASS |
| N50 | cost | `"12000.000000"` | `"12000"` | PASS | PASS |
| N51 | cost | `"3333.333333"` | `"3333.333333"` | PASS | PASS |
| N52 | cost | `"0.000001"` | `"0.000001"` | PASS | PASS |
| N53 | cost | `"0"` | `"0"` | PASS | PASS |
| N54 | cost | `"999999999999.999999"` | `"999999999999.999999"` | PASS | PASS |
| N55 | cost | `"1.0000001"` | refused | PASS | PASS |
| N56 | cost | `"-1"` | refused | PASS | PASS |
| N57 | cost | `"1e-6"` | refused | PASS | PASS |
| N58 | cost | `"12.000,5"` | refused | PASS | PASS |

#### Line totals and Sale totals (FIN01)

| # | Case | Quantity × unit price | Expected | Server | Client |
| --- | --- | --- | --- | --- | --- |
| L1 | FIN01: 1.5 × Rp3.333 = Rp5.000 | `"1.5"` (precision 1) × `"3333"` | `"5000"` | PASS | PASS |
| L2 | a tie rounds up | `"2.5"` (precision 1) × `"1"` | `"3"` | PASS | PASS |
| L3 | a tie below one rupiah rounds up | `"0.001"` (precision 3) × `"500"` | `"1"` | PASS | PASS |
| L4 | below the tie rounds down | `"0.001"` (precision 3) × `"499"` | `"0"` | PASS | PASS |
| L5 | two decimals | `"1.25"` (precision 2) × `"3"` | `"4"` | PASS | PASS |
| L6 | three decimals | `"0.333"` (precision 3) × `"3"` | `"1"` | PASS | PASS |
| L7 | whole units | `"3"` (precision 0) × `"25000"` | `"75000"` | PASS | PASS |
| L8 | a free product | `"1.5"` (precision 1) × `"0"` | `"0"` | PASS | PASS |
| L9 | the largest stored amount | `"1"` (precision 0) × `"999999999999"` | `"999999999999"` | PASS | PASS |
| L10 | an overflow is refused | `"2"` (precision 0) × `"500000000000"` | refused | PASS | PASS |
| L11 | the largest quantity at the largest price is refused | `"99999999.999"` (precision 3) × `"999999999999"` | refused | PASS | PASS |
| S1 | half-up once per line, then the exact sum: two lines of 1.5 × Rp3.333 | lines `"5000"` + `"5000"` | `"10000"` | PASS | PASS |
| S2 | a Sale total of three lines | lines `"75000"` + `"1"` + `"0"` | `"75001"` | PASS | PASS |

#### Half-up rounding

| # | Value | Scale | Expected | Server | Client |
| --- | --- | --- | --- | --- | --- |
| R1 | `"10.5"` | 0 | `"11"` | PASS | PASS |
| R2 | `"10.49"` | 0 | `"10"` | PASS | PASS |
| R3 | `"-10.5"` | 0 | `"-11"` | PASS | PASS |
| R4 | `"-10.49"` | 0 | `"-10"` | PASS | PASS |
| R5 | `"0.5"` | 0 | `"1"` | PASS | PASS |
| R6 | `"-0.5"` | 0 | `"-1"` | PASS | PASS |
| R7 | `"0.4999999"` | 0 | `"0"` | PASS | PASS |
| R8 | `"1.0000005"` | 6 | `"1.000001"` | PASS | PASS |
| R9 | `"1.00000049"` | 6 | `"1"` | PASS | PASS |
| R10 | `"66.6666666666"` | 6 | `"66.666667"` | PASS | PASS |
| R11 | `"33.3333333333"` | 6 | `"33.333333"` | PASS | PASS |
| R12 | `"12"` | 6 | `"12"` | PASS | PASS |

#### Formats (COPY_DECK sections 1.3 and 1.4)

| # | Format | Input | Expected | Server | Client |
| --- | --- | --- | --- | --- | --- |
| F1 | money | `"1234567"` | `"Rp1.234.567"` | PASS | PASS |
| F2 | money | `"0"` | `"Rp0"` | PASS | PASS |
| F3 | money | `"-2000"` | `"−Rp2.000"` | PASS | PASS |
| F4 | money | `"999"` | `"Rp999"` | PASS | PASS |
| F5 | money | `"1000"` | `"Rp1.000"` | PASS | PASS |
| F6 | money | `"75000"` | `"Rp75.000"` | PASS | PASS |
| F7 | money | `"999999999999"` | `"Rp999.999.999.999"` | PASS | PASS |
| F8 | money | `"-999999999999"` | `"−Rp999.999.999.999"` | PASS | PASS |
| F9 | money | `"1.5"` | refused | PASS | PASS |
| F10 | money | `"1.000"` | refused | PASS | PASS |
| F11 | quantity | `"1250.5"`, precision 3, unit `"kg"` | `"1.250,5 kg"` | PASS | PASS |
| F12 | quantity | `"3"`, precision 0, unit `"pcs"` | `"3 pcs"` | PASS | PASS |
| F13 | quantity | `"1250.5"`, precision 1 | `"1.250,5"` | PASS | PASS |
| F14 | quantity | `"0.001"`, precision 3 | `"0,001"` | PASS | PASS |
| F15 | quantity | `"1.50"`, precision 2 | `"1,5"` | PASS | PASS |
| F16 | quantity | `"99999999.999"`, precision 3 | `"99.999.999,999"` | PASS | PASS |
| F17 | quantity | `"-1.5"`, precision 1 | `"−1,5"` | PASS | PASS |
| F18 | quantity | `"1000000"`, precision 0 | `"1.000.000"` | PASS | PASS |
| F19 | quantity | `"1.2345"`, precision 3 | refused | PASS | PASS |
| F20 | quantity | `"3.0"`, precision 0 | refused | PASS | PASS |
| F21 | cost | `"12000"` | `"Rp12.000"` | PASS | PASS |
| F22 | cost | `"11999.5"` | `"Rp12.000"` | PASS | PASS |
| F23 | cost | `"11999.499999"` | `"Rp11.999"` | PASS | PASS |
| F24 | cost | `"3333.333333"` | `"Rp3.333"` | PASS | PASS |
| F25 | cost | `"0.5"` | `"Rp1"` | PASS | PASS |
| F26 | cost | `"0.499999"` | `"Rp0"` | PASS | PASS |
| F27 | cost | `"-10.5"` | `"−Rp11"` | PASS | PASS |
| F28 | cost | `"-10.499999"` | `"−Rp10"` | PASS | PASS |
| F29 | cost | `"UNKNOWN"` | refused | PASS | PASS |
| F30 | cost | `"1.0000001"` | refused | PASS | PASS |
| F31 | date | `"2026-10-01"` | `"01 Oktober 2026"` | PASS | PASS |
| F32 | date | `"2026-01-15"` | `"15 Januari 2026"` | PASS | PASS |
| F33 | date | `"2026-02-28"` | `"28 Februari 2026"` | PASS | PASS |
| F34 | date | `"2026-03-01"` | `"01 Maret 2026"` | PASS | PASS |
| F35 | date | `"2026-04-30"` | `"30 April 2026"` | PASS | PASS |
| F36 | date | `"2026-05-09"` | `"09 Mei 2026"` | PASS | PASS |
| F37 | date | `"2026-06-10"` | `"10 Juni 2026"` | PASS | PASS |
| F38 | date | `"2026-07-17"` | `"17 Juli 2026"` | PASS | PASS |
| F39 | date | `"2026-08-17"` | `"17 Agustus 2026"` | PASS | PASS |
| F40 | date | `"2026-09-30"` | `"30 September 2026"` | PASS | PASS |
| F41 | date | `"2026-11-11"` | `"11 November 2026"` | PASS | PASS |
| F42 | date | `"2026-12-31"` | `"31 Desember 2026"` | PASS | PASS |
| F43 | date | `"2028-02-29"` | `"29 Februari 2028"` | PASS | PASS |
| F44 | date | `"2026-02-30"` | refused | PASS | PASS |
| F45 | date | `"2026-1-5"` | refused | PASS | PASS |
| F46 | date | `"01-10-2026"` | refused | PASS | PASS |
| F47 | dateTime | `"2026-10-01T02:15:00.000000Z"` | `"01 Oktober 2026, 09.15.00"` | PASS | PASS |
| F48 | dateTime | `"2026-10-17T06:45:00Z"` | `"17 Oktober 2026, 13.45.00"` | PASS | PASS |
| F49 | dateTime | `"2026-10-18T16:59:59.999999Z"` | `"18 Oktober 2026, 23.59.59"` | PASS | PASS |
| F50 | dateTime | `"2026-10-18T17:00:00.000000Z"` | `"19 Oktober 2026, 00.00.00"` | PASS | PASS |
| F51 | dateTime | `"2026-12-31T17:00:00Z"` | `"01 Januari 2027, 00.00.00"` | PASS | PASS |
| F52 | dateTime | `"2028-02-28T17:00:00Z"` | `"29 Februari 2028, 00.00.00"` | PASS | PASS |
| F53 | dateTime | `"2026-03-08T10:30:00Z"` | `"08 Maret 2026, 17.30.00"` | PASS | PASS |
| F54 | timeOfDay | `"2026-10-17T06:45:00Z"` | `"13.45"` | PASS | PASS |
| F55 | timeOfDay | `"2026-10-18T16:59:59Z"` | `"23.59"` | PASS | PASS |
| F56 | timeOfDay | `"2026-10-18T17:00:00Z"` | `"00.00"` | PASS | PASS |
| F57 | timeOfDay | `"2026-10-19T02:05:00Z"` | `"09.05"` | PASS | PASS |
| F58 | dayName | `"2026-10-19"` | `"Senin"` | PASS | PASS |
| F59 | dayName | `"2026-10-20"` | `"Selasa"` | PASS | PASS |
| F60 | dayName | `"2026-10-21"` | `"Rabu"` | PASS | PASS |
| F61 | dayName | `"2026-10-22"` | `"Kamis"` | PASS | PASS |
| F62 | dayName | `"2026-10-23"` | `"Jumat"` | PASS | PASS |
| F63 | dayName | `"2026-10-24"` | `"Sabtu"` | PASS | PASS |
| F64 | dayName | `"2026-10-25"` | `"Minggu"` | PASS | PASS |
| F65 | count | `3` | `"3"` | PASS | PASS |
| F66 | count | `12` | `"12"` | PASS | PASS |
| F67 | count | `0` | `"0"` | PASS | PASS |
| F68 | count | `5000` | `"5.000"` | PASS | PASS |
| F69 | count | `1234567` | `"1.234.567"` | PASS | PASS |
| F70 | count | `-1` | refused | PASS | PASS |
| F71 | variance | `"-2000"` | `"−Rp2.000 (kurang)"` | PASS | PASS |
| F72 | variance | `"5000"` | `"Rp5.000 (lebih)"` | PASS | PASS |
| F73 | variance | `"0"` | `"Rp0 (sesuai)"` | PASS | PASS |
| F74 | date | `"0001-01-01"` | `"01 Januari 0001"` | PASS | PASS |
| F75 | date | `"0004-02-29"` | `"29 Februari 0004"` | PASS | PASS |
| F76 | date | `"0050-06-15"` | `"15 Juni 0050"` | PASS | PASS |
| F77 | date | `"0099-12-31"` | `"31 Desember 0099"` | PASS | PASS |
| F78 | date | `"0100-01-01"` | `"01 Januari 0100"` | PASS | PASS |
| F79 | date | `"0100-02-29"` | refused | PASS | PASS |
| F80 | date | `"0000-01-01"` | refused | PASS | PASS |
| F81 | date | `"9999-12-31"` | `"31 Desember 9999"` | PASS | PASS |
| F82 | dateTime | `"1963-12-31T17:00:00Z"` | `"01 Januari 1964, 00.00.00"` | PASS | PASS |
| F83 | dateTime | `"1963-12-31T16:59:59.999999Z"` | refused | PASS | PASS |
| F84 | dateTime | `"1963-12-31T16:30:00Z"` | refused | PASS | PASS |
| F85 | dateTime | `"1960-06-01T00:00:00Z"` | refused | PASS | PASS |
| F86 | dateTime | `"0050-06-15T03:04:05Z"` | refused | PASS | PASS |
| F87 | dateTime | `"9999-12-31T16:59:59Z"` | `"31 Desember 9999, 23.59.59"` | PASS | PASS |
| F88 | timeOfDay | `"1963-12-31T17:00:00Z"` | `"00.00"` | PASS | PASS |
| F89 | timeOfDay | `"1963-12-31T16:59:59Z"` | refused | PASS | PASS |
| F90 | timeOfDay | `"1900-01-01T00:00:00Z"` | refused | PASS | PASS |
| F91 | dayName | `"0001-01-01"` | `"Senin"` | PASS | PASS |
| F92 | dayName | `"0004-02-29"` | `"Minggu"` | PASS | PASS |
| F93 | dayName | `"0050-06-15"` | `"Rabu"` | PASS | PASS |
| F94 | dayName | `"0099-12-31"` | `"Kamis"` | PASS | PASS |
| F95 | dayName | `"0100-01-01"` | `"Jumat"` | PASS | PASS |
| F96 | dayName | `"9999-12-31"` | `"Jumat"` | PASS | PASS |
| F97 | dayName | `"0000-01-01"` | refused | PASS | PASS |
| F98 | cost | `"0.4999999"` | refused | PASS | PASS |
| F99 | cost | `"-0.5000001"` | refused | PASS | PASS |

F74 to F99 were added by the remediation for findings A-05, A-14 and A-16 (section 10).

#### Durations (COPY_DECK section 1.3, CC-21; R-25)

| # | Amount | Unit | Expected | Server | Client |
| --- | --- | --- | --- | --- | --- |
| D1 | `0` | `minute` | `"0 menit"` | PASS | PASS |
| D2 | `1` | `minute` | `"1 menit"` | PASS | PASS |
| D3 | `60` | `minute` | `"60 menit"` | PASS | PASS |
| D4 | `1500` | `minute` | `"1.500 menit"` | PASS | PASS |
| D5 | `0` | `second` | `"0 detik"` | PASS | PASS |
| D6 | `1` | `second` | `"1 detik"` | PASS | PASS |
| D7 | `60` | `second` | `"60 detik"` | PASS | PASS |
| D8 | `1500` | `second` | `"1.500 detik"` | PASS | PASS |
| D9 | `30` | `second` | `"30 detik"`, the COPY_DECK example | PASS | PASS |
| D10 | `-1` | `minute` | refused, a negative amount | PASS | PASS |
| D11 | `-1` | `second` | refused, a negative amount | PASS | PASS |
| D12 | `1.5` | `minute` | refused, not an integer | PASS | PASS |
| D13 | `0.5` | `second` | refused, not an integer | PASS | PASS |
| D14 | `"60"` | `minute` | refused, a string | PASS | PASS |
| D15 | `60` | `hour` | refused, an unknown unit | PASS | PASS |
| D16 | `60` | `minutes` | refused, an unknown unit | PASS | PASS |
| D17 | `60` | `Minute` | refused, an unknown unit | PASS | PASS |
| D18 | `60` | `""` | refused, an unknown unit | PASS | PASS |

#### Placeholders

| # | Case | Text | Expected | Server | Client |
| --- | --- | --- | --- | --- | --- |
| P1 | the variance of COPY_DECK section 1.4 | `":uang (:keterangan)"` | `"−Rp2.000 (kurang)"` | PASS | PASS |
| P2 | a placeholder used twice | `":jumlah dari :jumlah"` | `"3 dari 3"` | PASS | PASS |
| P3 | the longest placeholder wins | `":jumlah, :jumlah_baris"` | `"3, 12"` | PASS | PASS |
| P4 | capitalized and upper-case forms | `":Nama; :NAMA; :nama"` | `"Budi; BUDI; budi"` | PASS | PASS |
| P5 | a replaced value is not searched again | `":produk dan :jumlah"` | `":jumlah dan 2"` | PASS | PASS |
| P6 | an unknown placeholder stays | `"Paling banyak :maks baris."` | `"Paling banyak :maks baris."` | PASS | PASS |
| P7 | a colon that is not a placeholder | `"Zona waktu: WIB (:waktu)"` | `"Zona waktu: WIB (13.45)"` | PASS | PASS |

Counts: 58 canonical, 11 line totals, 2 Sale totals, 12 rounding, 99 format, 18 duration and 7 placeholder cases. Beside this table, the differential cases of section 6.2 run on both sides.

#### Business dates (server, with the test clock)

| # | Instant (UTC) | Business date | Result |
| --- | --- | --- | --- |
| B1 | `2026-10-18T16:59:59.999999Z`, the last microsecond before Asia/Jakarta midnight | 2026-10-18 | PASS |
| B2 | `2026-10-18T17:00:00.000000Z`, Asia/Jakarta midnight | 2026-10-19 | PASS |
| B3 | `2026-10-19T00:00:00Z`, UTC midnight, 07:00 in Asia/Jakarta | 2026-10-19 | PASS |
| B4 | `2026-10-19T23:59:59Z` | 2026-10-20 | PASS |
| B5 | `2026-12-31T17:00:00Z`, the new year in Asia/Jakarta | 2027-01-01 | PASS |
| B6 | `2026-12-31T16:59:59Z` | 2026-12-31 | PASS |
| B7 | `2028-02-28T17:00:00Z`, a leap day | 2028-02-29 | PASS |
| B8 | `2026-10-19T06:30:00+07:00`, another offset | 2026-10-19 | PASS |

#### Report periods (server, with the test clock; half-open UTC ranges)

| # | Now (UTC) | Period | Dates | [start, end) | Result |
| --- | --- | --- | --- | --- | --- |
| T1 | 2026-10-19T05:00:00Z | today | 2026-10-19 | [2026-10-18T17:00:00Z, 2026-10-19T17:00:00Z) | PASS |
| T2 | 2026-10-18T17:00:00Z, Asia/Jakarta midnight | today | 2026-10-19 | [2026-10-18T17:00:00Z, 2026-10-19T17:00:00Z) | PASS |
| T3 | 2026-10-18T16:59:59Z | today | 2026-10-18 | [2026-10-17T17:00:00Z, 2026-10-18T17:00:00Z) | PASS |
| T4 | 2026-10-19T05:00:00Z | 7d | 2026-10-13 to 2026-10-19 | [2026-10-12T17:00:00Z, 2026-10-19T17:00:00Z) | PASS |
| T5 | 2026-10-02T05:00:00Z | 7d, across a month | 2026-09-26 to 2026-10-02 | [2026-09-25T17:00:00Z, 2026-10-02T17:00:00Z) | PASS |
| T6 | 2026-10-19T05:00:00Z | this_month | 2026-10-01 to 2026-10-19 | [2026-09-30T17:00:00Z, 2026-10-19T05:00:00Z) | PASS |
| T7 | 2026-10-31T17:30:00Z | this_month, half an hour into it | 2026-11-01 | [2026-10-31T17:00:00Z, 2026-10-31T17:30:00Z) | PASS |
| T8 | 2026-10-19T05:00:00Z | last_month | 2026-09-01 to 2026-09-30 | [2026-08-31T17:00:00Z, 2026-09-30T17:00:00Z) | PASS |
| T9 | 2026-01-10T00:00:00Z | last_month, across a year | 2025-12-01 to 2025-12-31 | [2025-11-30T17:00:00Z, 2025-12-31T17:00:00Z) | PASS |
| T10 | 2026-03-31T05:00:00Z | last_month, February | 2026-02-01 to 2026-02-28 | [2026-01-31T17:00:00Z, 2026-02-28T17:00:00Z) | PASS |
| T11 | 2026-02-28T17:30:00Z | last_month, after Asia/Jakarta midnight | 2026-02-01 to 2026-02-28 | [2026-01-31T17:00:00Z, 2026-02-28T17:00:00Z) | PASS |
| T12 | 2026-10-19T05:00:00Z | custom 2026-10-01 to 2026-10-19 | inclusive | [2026-09-30T17:00:00Z, 2026-10-19T17:00:00Z) | PASS |
| T13 | 2026-10-19T05:00:00Z | custom, one date | 2026-10-05 | [2026-10-04T17:00:00Z, 2026-10-05T17:00:00Z) | PASS |
| T14 | 2026-10-19T05:00:00Z | custom, 366 days | 2025-10-19 to 2026-10-19 | [2025-10-18T17:00:00Z, 2026-10-19T17:00:00Z) | PASS |
| T15 | 2026-10-18T17:00:00Z | custom ending today, just after Asia/Jakarta midnight | 2026-10-19 | [2026-10-18T17:00:00Z, 2026-10-19T17:00:00Z) | PASS |
| T16 | 2026-10-19T05:00:00Z | custom 2026-10-10 to 2026-10-09 | refused, `START_AFTER_END` | — | PASS |
| T17 | 2026-10-19T05:00:00Z | custom ending 2026-10-20 | refused, `END_AFTER_TODAY` | — | PASS |
| T18 | 2026-10-18T16:59:59Z | custom ending 2026-10-19, one second before Asia/Jakarta midnight | refused, `END_AFTER_TODAY` | — | PASS |
| T19 | 2026-10-19T05:00:00Z | custom, 367 days | refused, `TOO_LONG` | — | PASS |

The start of a period is inside it and its end outside it; two consecutive days meet without a gap or an overlap.

### 6.2 The differential cases (R-41)

The differential run of the class-A review, adopted by the remediation: generated with the fixed seed 20261020, the expected outcomes computed once by the server kernel and committed with the cases, then run by `DifferentialCasesTest` on the server — again under Pacific/Kiritimati, America/St_Johns, Asia/Kathmandu and Australia/Lord_Howe with the process locales `C.utf8`, `POSIX`, `C` and the application locales `en`, `fr`, `id_ID`, `ar` — and by `differential.test.ts` on the client, again under America/St_Johns, Pacific/Chatham, Asia/Kathmandu and Etc/GMT+12 with `LANG` and `LC_ALL` set to Arabic and Thai and every source of locale data made to throw. Error outcomes are compared by class for the parse categories (`NonCanonicalNumber` with `NonCanonicalNumberError`, `NumberOutOfRange` with `NumberOutOfRangeError`); the client refuses the UNKNOWN marker in `parseCost` by design. Line totals, roundings and `round6(V × q / Q)` are also checked against independent BigInt oracles.

| Category | Committed, scale 0.1 | Full size, scale 1 |
| --- | --- | --- |
| parse, parseKind | 1,448 each | 5,048 each |
| lineTotal | 768 | 7,518 |
| sums, minus, compare | 150, 250, 250 | 1,500, 2,500, 2,500 |
| round, mulDiv | 500, 400 | 5,000, 4,000 |
| placeholders | 400 | 4,000 |
| formatMoney, formatVariance | 316 each | 3,016 each |
| formatQuantity, formatCostFigure | 408, 419 | 4,008, 4,019 |
| formatDate, formatDayName | 318 each | 3,018 each |
| formatDateTime, formatTimeOfDay | 419 each | 4,019 each |
| formatCount, formatDuration | 159, 163 | 1,509, 1,513 |
| All outcomes | 8,869 | 68,269 |

The committed table runs in the command contract (section 7.1). The full-size table ran once, locally, on 20 October 2026 from 03:55:32 to 03:56:14 on `4105a0870bae0bd4bcf6950de17df192a9a8d2ba`: generated into `storage/framework/testing/` (ignored by Git, 3,308,144 bytes), its expected outcomes written by `expected.php`, then `DifferentialCasesTest` PASS (3 tests, in 7.4 s) and `differential.test.ts` PASS (22 tests) through `DIFFERENTIAL_CASES`; the file was deleted afterwards.

## 7. Close (EXECUTION_CONTEXT section 15)

### 7.1 The command contract, the run of record

The run of record of the remediation (section 10), on 20 October 2026 from 03:56:27 to 04:04:03, by the implementation executor, on the revision `4105a0870bae0bd4bcf6950de17df192a9a8d2ba`, the remediation's last code commit; the record commit that follows changes documents only, and during the run the working tree held its documents, which no command reads but the secret scan of the tree. In the local environment of TASK-001 on the Owner's Windows machine: the application container `bantukasir-app:local` and PostgreSQL 18.6, with the Node.js and Playwright containers for the npm commands; `tests/Adversarial/` did not exist. Dataset: synthetic only, created by the tests.

| Command | Result |
| --- | --- |
| `composer validate --strict --no-check-all` | PASS: `./composer.json is valid` |
| `composer lint` | PASS: Pint clean, 89 files; "Translation keys: 36 used, all present in lang/id; no literal user-facing text"; "Translation types: resources/js/types/translation-keys.ts is current, 32 keys"; migration review 0 migrations; secret scan of the tree and of the 13 commits not on a remote: no leaks; `composer audit`: no advisory; the suite Network: 2 tests, 11 assertions |
| `composer analyse` | PASS: PHPStan level 9 with Larastan and the project's rules, the widened float rule included, no error |
| `composer test:unit` | PASS: 473 tests, 381,681 assertions |
| `composer test:postgres` | PASS: 82 tests, 262 assertions |
| `composer test:race` | PASS: 1 test, 6 assertions |
| `npm run lint` | PASS: ESLint clean; npm audit: no high or critical advisory apart from those accepted with a reason; its network proof passed |
| `npm run typecheck` | PASS: no error |
| `npm run test:unit` | PASS: 8 files, 444 tests |
| `npm run build` | PASS: `tsc --noEmit`, then the Vite build |
| `npm run test:e2e` | PASS: the Playwright smoke test, 1 passed |

The whole run took 7 minutes 36 seconds, against 7 minutes 53 seconds for the run on the merge commit `5f6e291` before the remediation (section 10.2): the adopted tests, the differential cases among them, add no measurable time.

#### The first run of record, invalidated by the remediation

Run on 19 October 2026 from 23:18:34 to 23:25:48, by the implementation executor, on the revision `e7a27ca549564e0c3940bc95cfeca648b0d357a8` (the code of the Task; the close commit changes documents only), in the local environment of TASK-001 on the Owner's Windows machine: the application container `bantukasir-app:local` and PostgreSQL 18.6, with the Node.js and Playwright containers for the npm commands. Dataset: synthetic only, created by the tests.

| Command | Result |
| --- | --- |
| `composer validate --strict --no-check-all` | PASS: `./composer.json is valid` |
| `composer lint` | PASS: Pint clean; "Translation keys: 34 used, all present in lang/id; no literal user-facing text"; "Translation types: resources/js/types/translation-keys.ts is current, 30 keys"; migration review 0 migrations; secret scan of the tree and of the 8 commits not on a remote: no leaks; `composer audit`: no advisory; the suite Network: 2 tests passed |
| `composer analyse` | PASS: PHPStan level 9 with Larastan and the project's rules, no error |
| `composer test:unit` | PASS: 304 tests, 850 assertions |
| `composer test:postgres` | PASS: 4 tests, 16 assertions (`DictionaryOncePropTest` and TASK-001's smoke test) |
| `composer test:race` | PASS: 1 test, 6 assertions (TASK-001's smoke test) |
| `npm run lint` | PASS: ESLint clean; npm audit: no high or critical advisory; its network proof passed |
| `npm run typecheck` | PASS: no error |
| `npm run test:unit` | PASS: 6 files, 191 tests |
| `npm run build` | PASS: `tsc --noEmit`, then the Vite build |
| `npm run test:e2e` | PASS: the Playwright smoke test, 1 passed |

An earlier run on the working tree, from 23:01 to 23:12, before the commits, found two type errors in the new Vitest files in `npm run typecheck` and `npm run build`; they were corrected (section 7.4), and every command then passed. The remediation's changes invalidate this run (TS-32); the run of record above replaces it.

### 7.2 Acceptance criteria

As after the remediation, with the findings of the class-A review that decision section 5 assigns to each criterion (section 10):

| Criterion | Status | Reason |
| --- | --- | --- |
| AC-003-01 | PASS | 1.5 × Rp3.333 gives Rp5.000 (L1), half-up once per line (L1 to L11, S1, and 20,000 random line totals against an integer oracle), six-decimal cost (`CostArithmeticTest`, R8 to R12, the cumulative return rule over 2,000 random sequences), quantities with 0 to 3 decimals (N30 to N48), an overflow refused (L10, L11), on the server and the client, also on the differential cases against BigInt oracles (section 6.2); no floating point (the float static check and the lint rules). Hardening: A-12 (the casts refuse at `set()` what the row cannot hold), A-13 (a zero divisor is a kernel refusal) and A-17 (a scale is a non-negative integer) |
| AC-003-02 | PASS | Every non-canonical form of ADR-006 item 2 is refused on both sides (N5 to N29, the other refused N cases, 73 forms of `CanonicalAndRangeTest` and the parse category of the differential cases) and by the three rules; JSON numbers are refused by the rules and, since A-04, by every client helper; since A-01 a number that the global trimming changed is refused with the rule's message, never repaired, at the HTTP boundary for JSON, urlencoded and multipart requests (`CanonicalNumberInputTest`); the value objects serialize as strings, also through a model (`ValueObjectsTest`, `CastsTest`) |
| AC-003-03 | PASS | `composer analyse` passes on `app/Support` and `app/Domain` with the float rule, widened by A-02 (R-33): it reports the 14 operations it let through and 16 neighbours, the 12 others stay reported, and exact code as the kernel writes it is accepted (`PhpstanRulesTest`); the lint rules refuse the 13 forms of A-03 in every script extension (`eslint.test.ts`); A-11 and A-18: the factories, `CanonicalNumber::parse()` and `Format::count()` refuse a float at run time (`NoFloatTest`) |
| AC-003-04 | PASS | Money, negative money, quantity, cost on screen, date, date and time, time of day, durations, counts, day names and the variance words give exactly the COPY_DECK texts on the server and the client from the same table (F1 to F99, D1 to D18) and on the differential cases, also under other time zones and locale settings; the WIB marker is the copy's (R-07). Durations are built with the keys of CC-21 (F-1, R-25); A-05: the client accepts and formats the business dates 0001 to 9999 like the server; A-14: both refuse an instant before 1964 in Asia/Jakarta; A-15: a capitalized placeholder fails the build check; A-16: `Format::cost()` refuses a figure with more than six decimals. Read against the formats of contract item 5; the percent format that CC-03 added to COPY_DECK section 1.3 is outside the remediation's scope (F-8) |
| AC-003-05 | PASS | B1 to B8 and T1 to T19, with the test clock, and the period oracle over 4,000 random instants (`PeriodOracleTest`). Hardening: A-19, the test clock accepts only absolute instants |
| AC-003-06 | PASS | A key missing from `lang/id/` fails `composer lint` (the key check), `npm run typecheck` and `npm run build` (TS2345); since A-06 the key check finds every entry point of the translator and refuses a key that is not a literal, and lint refuses an assertion to `TranslationKey`; since A-07 the key check reads every user-facing sink of PHP and Blade; a literal in JSX fails lint anywhere inside an expression container or a spread attribute (A-08, six forms; the seventh is a limit of lint, R-28); a missing server key throws in tests, and a request with several invalid fields answers 422 (A-09 (a)); the dictionary reaches React once per full page load and not on a partial reload that does not ask for it (A-10, `DictionaryOncePropTest`, `app.tsx`) |

### 7.3 Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | AC-003-01 to AC-003-06 pass (section 7.2), durations included. REQ-COST-02, REQ-NFR-01, REQ-NFR-09 and REQ-DOC-05 are carried as section 5 of the contract says; the shares of FIN01, POS02, SESSION09, FIN03 and L10N01 are early evidence, not `FINAL_PASS` (TS-09) |
| UI and UX | N/A | No page or control |
| Backend | N/A | No route or Action; the Inertia middleware shares one more prop, and since the remediation the global trimming of input records the paths it changed (A-01) |
| Database | N/A | No migration; the casts' round-trip on PostgreSQL is TASK-004's |
| Security | PASS | No secret in the tree or the commits (section 7.1); the float check, the exact-number lint rules and the literal check refuse their samples and the review's bypasses; every non-canonical number and JSON number is refused, never repaired, also at the HTTP boundary (A-01). The contract names no threat path, and with no route there is no refusal of a role to prove |
| Data integrity | N/A | Writes no business fact |
| Performance | N/A | No request path; F-6 for the performance Tasks |
| Operations | N/A | No backup, runbook or status impact |
| Verification | PASS | The command contract is green locally on the remediation's last code commit (section 7.1); the self-review is done (sections 7.4 and 10.6); the class-A adversarial review ran in a separate session (EXC-71) and every finding the decision lists as FIX is fixed, with a regression test that failed before the fix and passes after it (section 9); CI runs on pull request #3 after the push (section 8). Still pending, each on its own instruction: the planning agent's verification of the fixes, and a targeted re-review if it asks for one (decision section 6); the Owner's acceptance; the merge |

### 7.4 Self-review (EXC-70)

The whole diff `fc0055b..HEAD` was reviewed against contract sections 4.1, 6, 7 and 11, ADR-005, ADR-006, ADR-008 section 5 and the readings of the instruction. Corrections made before the commits:

- the casts' `set()` refused a non-object at run time but PHPStan read their parameter as the value object; the parameter is documented as any value, so the run-time refusal stays visible to the analysis;
- the typed `t()` reported a key missing from `lang/id/` as a wrong number of arguments; its replacement type was made distributive (R-16);
- the missing-key handler failed every validation through the validator's probes (F-2, R-17);
- the generator of the key type was moved into `scripts/checks/`, the folder of STACK_AND_MODULES section 3;
- test code that PHPStan level 9 or the typecheck refused was corrected; the application code had no finding.

No other defect was found. Every commit message reads "TASK-003: …" under the Owner's identity, with no trailer and no mention of an AI model, agent or tool. While the commits were split, three staged removals of `.gitkeep` placeholders were unstaged with `git restore --staged` and staged again with the commit they belong to; the working tree was not touched. Every file is LF.

### 7.5 What is not verified, and why

- **The fixes of the class-A review**: verified by the planning agent on 20 October 2026, with no targeted re-review needed (section 9; D-100).
- **The limits recorded as readings**: the seventh form of A-08 (R-28), a `Date` turned into text when it comes from outside interface code (R-34) and a key type derived in another module (R-35) are not caught by lint; code review covers them. The production body of a 422 response is TASK-008's (A-09 (b), OPEN_ITEMS T-26).
- **The percent format** of COPY_DECK section 1.3 (CC-03): not built (F-8).
- **The full-size differential run**: once, locally (section 6.2); the command contract and CI run the committed table at scale 0.1.
- **CI**: it runs after the push (section 8).
- **The casts on PostgreSQL**: TASK-004 proves their round-trip (contract item 2).
- **A real browser**: no page exists yet, so the dictionary in a browser page, `t()` in React and the formatters under a browser set to another locale are proven in Node (Vitest) and through the HTTP kernel, not in Chromium; the Playwright smoke test of TASK-001 still passes.
- **Server locales**: the container has only the locales `C`, `C.utf8` and `POSIX`; the server cases ran under those, three application locales and four time zones.
- **`npm ci`**: not run locally; the lockfile is unchanged and the existing `node_modules` was used. CI runs `npm ci`.
### 7.6 Commits and files

The commits on `fc0055b`, under the Owner's identity:

| Commit | Message |
| --- | --- |
| `55d0f8fd13dd9d95c2f5732e6c085037246d5443` | TASK-003: start |
| `ebb1d7ef09d035ff54e95d0931f8ce9ace4317c4` | TASK-003: add exact Money, Quantity and Cost values on Brick Math |
| `f917b98f54f26c27a32dff4357d33e47907958f4` | TASK-003: add the clock boundary, business dates and report periods |
| `c507133a6270ecfa59a84851a52bf41023c8c775` | TASK-003: add the lang/id dictionary, the server formatters and the missing-key handler of tests |
| `9fda2870c4a653686fd921181aa2a6888e077f1c` | TASK-003: add Eloquent casts and validation rules for canonical numbers |
| `f52544b8e56f570590b49489ddbdf8d0f39437a4` | TASK-003: share the interface dictionary once through Inertia |
| `cf6d9028c099393df5c75bbd8e828673a8244268` | TASK-003: add the client exact-number helpers, formatters and typed t() |
| `e7a27ca549564e0c3940bc95cfeca648b0d357a8` | TASK-003: add the localization build checks and the exact-number lint rules |
| The commit that sets this status | TASK-003: close to VERIFYING |

From `fc0055b` to `e7a27ca`: 91 files, 72 added, 15 modified and 4 removed (the `.gitkeep` placeholders of `app/Support`, `lang/id`, `resources/js/lib` and `resources/js/types`). Modified: `app/Http/Middleware/HandleInertiaRequests.php`, `composer.json` (the `lint` script), `eslint.config.js`, `package.json` (the `build` script), `resources/js/app.tsx`, `scripts/checks/translation-keys.php`, `vitest.config.ts` (the `@` alias), `tests/TestCase.php`, `tests/PHPStan/FloatInExactValuesRule.php`, the float negative sample, four tests of `tests/Unit/StaticChecks/`, and `docs/11-tasks/TASK_PLAN.md` (the start). The close commit changes this file, TASK_PLAN section 4 and CURRENT_HANDOFF.

The commits of the remediation (section 10), on `0ee8ab7` (the close commit "TASK-003: close to VERIFYING"), under the Owner's identity, with no trailer and no mention of an AI model, agent or tool:

| Commit | Message |
| --- | --- |
| `5f6e291165d0e0ff31520ad54dc07d8ea8a5e81c` | TASK-003: merge develop, with TASK-002 and D-099, into the Task branch (parents `0ee8ab7` and `78ca172`) |
| `67210f3b6b43983d231096fc80c9c788987eb572` | TASK-003: add the duration formatter with the unit words of CC-21 |
| `3d9a07c25baedba37d5d41ba8151ac2ed97c41a0` | TASK-003: refuse padded numbers at the HTTP boundary and let the validation summary through in tests (A-01, A-09) |
| `ccf7ab394d699288c043e0ae8666d9cc0a9bbe92` | TASK-003: refuse values that are not strings in the kernel factories, and a zero cost divisor (A-11, A-13) |
| `f58b14b5380ad274f5343b1b38f66c1bcbad9e81` | TASK-003: refuse early instants, long cost figures and counts that are not integers in the server formatter (A-11, A-14, A-16, A-18) |
| `07a077497326e3d906844df708faf63a396f2766` | TASK-003: refuse JS numbers and bad scales in the client helpers and compute the client calendar in integers (A-04, A-05, A-14, A-16, A-17) |
| `af375fe12a801ba62f7b2786ba835415f6512346` | TASK-003: refuse in the casts what the row cannot hold before any write (A-12) |
| `1aebe36e3b0b0ee8949aa1d3381653687b301a36` | TASK-003: accept only absolute instants in the test clock, with the period oracle (A-19) |
| `3ba5353f33e2ab2c5c89fb2d9b1f1595a26502d6` | TASK-003: widen the float rule to the floating-point operations of the review (A-02) |
| `70d6f61eceb84df40e317cf208a8a0015b64a89d` | TASK-003: widen the exact-number, key-assertion and JSX literal lint rules (A-03, A-06, A-08) |
| `7137b93c0bd5b76ce386838b1529a02b3c369864` | TASK-003: read every translator entry point and user-facing sink in the key check (A-06, A-07) |
| `7a891be33f16b60eb4f7bfe6e6dda4c981fbe8e5` | TASK-003: keep the dictionary out of partial reloads that do not ask for it (A-10) |
| `2b831771e30dbfc7ffb2966355d1f2d1c1e412a5` | TASK-003: refuse capitalized placeholders in lang/id (A-15) |
| `4105a0870bae0bd4bcf6950de17df192a9a8d2ba` | TASK-003: adopt the review's differential run and boundary tests |
| The commit that records this file | TASK-003: record the remediation of the class-A review and the durations |

From `0ee8ab7` to `4105a08`, outside `docs/` (which only the merge of `develop` changed until the record commit): 67 files, 32 added and 35 modified. The record commit changes this file, OPEN_ITEMS (T-26), TASK_PLAN section 7 (the T-26 row) and CURRENT_HANDOFF, and no code.

## 8. Publication

Under the instruction's step 6 onwards: the publication guard over `git rev-list task/TASK-003-kernel-localization --not origin/develop`, the push of the Task branch without force, one pull request into `develop` and its CI. Their results, the pull request and every CI run are in the executor's report to the Owner, and the repository records them with the bookkeeping that follows, as it did for TASK-001 (D-098). That push opened pull request #3.

The remediation (section 10) pushes the branch again without force, after the publication guard over `git rev-list task/TASK-003-kernel-localization --not origin/task/TASK-003-kernel-localization` against the baseline of D-097 as D-098 extends it, and watches the CI of pull request #3. The merge commit from `develop` brings only content already on `develop`. The guard, the push and the CI run are in the executor's report to the Owner, as before.

## 9. Reviews

- **Self-review** (EXC-70): sections 7.4 and 10.6.
- **Reviewer's check** (EXC-70): the planning agent's decision on the class-A review (below) records its check of the findings against `0ee8ab7`.
- **Adversarial review of class A** (EXC-71; contract section 8, "the adversarial review of the arithmetic"): run by Claude Opus 5.5 (`claude-opus-5-5`) at effort Max, in a new session separate from the executor's, on 20 October 2026 from 00:21:19 to 01:13:22, on `task/TASK-003-kernel-localization` at `0ee8ab7547198c5e5c72dd49d193ec735d3a4f44`; its command contract was green, and nothing was committed, pushed or fixed. Its report, `TASK-003-Adversarial-Review-Report.md`, 136,052 bytes, SHA-256 `b8a2012a7f65f8bec7416d52a4c8b09a9cec9f07adc86544f69b29c7cd7b591e` (checked by the remediation on 20 October 2026), found 10 MUST-FIX, 7 SHOULD-FIX and 2 NOTE findings. The planning agent's decision, `TASK-003-Adversarial-Review-Decision.md` (20 October 2026), marks every finding FIX except two parts that it records instead: the seventh form of A-08 (a reading) and A-09 (b) (OPEN_ITEMS T-26, for TASK-008).
- **Verification by the planning agent** (20 October 2026): PASS. Each finding meets the required outcome of the decision against the code at `3cc9188`. The planning agent ruled acceptable, as limits of lint covered by code review, three items:
  - the `toString()` of a `Date` from outside interface code (R-34);
  - an alias of the global object;
  - a key type derived in another module (R-35).

  No targeted re-review was needed (D-100).

Each finding with the decision, its regression test, the test's result before the fix (the run of section 10.4 on `67210f3` with the new tests in the working tree) and after it (section 10.5 and the run of record, section 7.1), the fix and its commit:

| ID | Class | Decision | Regression test | Before | After | Fix | Commit |
| --- | --- | --- | --- | --- | --- | --- | --- |
| A-01 | MUST-FIX | FIX | `CanonicalNumberInputTest`: padded numbers in JSON (33 cases), in a form (33), in a multipart request, under other field names | FAIL, 68 cases | PASS | The global trimming records the paths it changed; the three rules refuse a value at such a path (R-26) | `3d9a07c` |
| A-02 | MUST-FIX | FIX | `PhpstanRulesTest::test_the_float_rule_reports_every_float_operation_of_the_review`, samples `FloatBypassSamples.php`; controls `FloatControls.php` | FAIL (the 14 tags and the neighbours unreported); controls PASS | PASS | The float rule's new checks (R-33); `BusinessDate::compareTo()` with `strcmp()` | `3ba5353` |
| A-03 | MUST-FIX | FIX | `eslint.test.ts`: every tagged line of `exact-number-bypasses.ts`, `.mts`, `.cts`, `.js` and `.jsx`; controls `exact-number-controls.ts` | FAIL, 5 tests; controls PASS | PASS | The exact-number rules on every name, alias, computed access and script extension (R-34) | `70d6f61` |
| A-04 | MUST-FIX | FIX | `client-edges.test.ts`: 10 values that are not strings through 13 helpers, and a big JSON number | FAIL, 85 cases; null, undefined, true, and 1.5 as money, were already refused | PASS | `parseExact` refuses a value that is not a string before the pattern | `07a0774` |
| A-05 | MUST-FIX | FIX, by parity | `formatting.json` F74 to F81 and F91 to F97 on both sides; the date categories of the differential cases | FAIL on the client, 8 table cases and the differential date categories; PASS on the server | PASS | The client calendar in integers, 0001 to 9999 (R-27) | `07a0774` |
| A-06 | MUST-FIX | FIX | PHP and Blade: `TranslationKeysTest::test_every_bypass_of_the_review_is_reported_and_no_control_is`; TypeScript: `eslint.test.ts`, every tagged line of `key-assertions.ts`, and the key check's TypeScript samples | FAIL, both | PASS | The token-based key check (R-36); the lint rule against key assertions (R-35) | `7137b93`, `70d6f61` |
| A-07 | MUST-FIX | FIX | `TranslationKeysTest::test_every_bypass_of_the_review_is_reported_and_no_control_is`, samples `LiteralSinks.php` and `sinks.blade.php` | FAIL | PASS | The sinks and the Blade attributes of the key check, with the reviewed allowlist (R-36) | `7137b93` |
| A-08 | MUST-FIX | FIX six forms; the seventh is a NOTE | `eslint.test.ts`: every tagged line of `jsx-literal-bypasses.tsx`; controls `jsx-literal-controls.tsx` | FAIL; the controls FAIL too, because the earlier selector refused every literal in braces | PASS | The JSX literal selectors with their exclusions (R-28); the seventh form recorded as a limit of lint | `70d6f61` |
| A-09 | MUST-FIX | Split | (a) `CanonicalNumberInputTest::test_two_invalid_fields_give_a_validation_response_with_both_messages`, `LocalizationTest::test_exactly_the_three_summary_lookups_of_a_validation_exception_are_probes` | (a) FAIL, both | (a) PASS | (a) The three summary lookups are probes of the test handler (R-29); (b) recorded as OPEN_ITEMS T-26 for TASK-008, not fixed here | `3d9a07c` |
| A-10 | MUST-FIX | FIX | `DictionaryOncePropTest::test_a_partial_reload_carries_the_dictionary_only_when_its_only_names_it`, 6 cases | FAIL, the 2 `except` cases | PASS | `shareOnce()` leaves the dictionary out of a partial reload unless `only` names it; `handle()` drops a dictionary left by an earlier request (R-37) | `7a891be` |
| A-11 | SHOULD-FIX | FIX (assigned) | `NoFloatTest::test_a_float_never_becomes_an_exact_value`, 13 cases | FAIL, 13 cases | PASS | Run-time refusal of a value that is not a string in every factory and `CanonicalNumber::parse()` (R-40) | `ccf7ab3`; its test with `f58b14b` |
| A-12 | SHOULD-FIX | FIX (assigned) | `CastsRoundTripTest`: excess decimals, no precision yet, money outside `bigint` | FAIL, 3 tests | PASS | `QuantityCast::set()` at the row's precision; `MoneyCast::set()` range of `bigint` (R-38) | `af375fe` |
| A-13 | SHOULD-FIX | FIX (assigned) | `CanonicalAndRangeTest::test_a_division_by_zero_is_a_refusal_the_kernel_names` | FAIL (`DivisionByZeroException`) | PASS | `Cost::quotient()` refuses a zero divisor with `NumberOutOfRange` (R-30) | `ccf7ab3` |
| A-14 | SHOULD-FIX | FIX (assigned) | `FormatCasesTest::test_an_instant_before_1964_in_asia_jakarta_is_refused`; `formatting.json` F82 to F90 on both sides; the instant categories of the differential cases | FAIL on both sides | PASS | Both formatters refuse an instant before `1963-12-31T17:00:00Z` (R-31) | `f58b14b`, `07a0774` |
| A-15 | SHOULD-FIX | FIX (assigned) | `TranslationTypesTest::test_a_capitalized_placeholder_fails_the_check_and_the_generation` | FAIL | PASS | The translation-types check refuses `:Name` and `:NAME` (R-32) | `2b83177` |
| A-16 | SHOULD-FIX | FIX (assigned) | `FormatCasesTest::test_a_cost_figure_with_more_than_six_decimals_is_refused`; `formatting.json` F98 and F99 | FAIL on the server; the client already refused | PASS | `Format::cost()` refuses a figure with more than six decimals | `f58b14b` |
| A-17 | SHOULD-FIX | FIX (assigned) | `client-edges.test.ts`: eight scales that are not non-negative integers | FAIL for the review's scale −1; the other seven already threw, by accident, from `BigInt` | PASS | `roundHalfUp` and `unitsAt` refuse such a scale with `RangeError` | `07a0774` |
| A-18 | NOTE | FIX, with A-11 | `NoFloatTest::test_a_count_is_never_truncated_from_another_type`, 5 cases | FAIL, 4 cases and 1 error (a `TypeError` for null) | PASS | `Format::count()` refuses a value that is not an int at run time (R-40) | `f58b14b` |
| A-19 | NOTE | FIX (assigned) | `PeriodOracleTest::test_the_test_clock_refuses_an_instant_that_is_not_absolute`, 14 cases | FAIL, 14 cases | PASS | `TestClock` accepts only absolute instants (R-39) | `1aebe36` |

The rest of decision section 3 is adopted too: the static-check bypasses are fixtures under `tests/Fixtures/StaticChecks/`, each asserted refused, with their controls asserted accepted; the differential run is deterministic, writes no file while the tests run and needs neither run before the other, with the full-size run once locally (section 6.2); the other tests of the report that assert a boundary of its section 5 joined the suites (section 6), `PdoTypesTest` with `composer test:postgres`. `tests/Adversarial/` was never created.

## 10. Remediation of the class-A review and the durations

### 10.1 Preflight (instruction section 1)

Checked on 20 October 2026 from 02:31 to 02:33, Git run with `GIT_OPTIONAL_LOCKS=0`:

- **The branch**: `task/TASK-003-kernel-localization` at `0ee8ab7547198c5e5c72dd49d193ec735d3a4f44`, equal to `origin/task/TASK-003-kernel-localization`; nothing staged or modified; untracked exactly the three files of `Claude outputs/` and `plan-p0-p11-review.patch`; `stash@{0}` present; no `.git/index.lock`; `tests/Adversarial/` absent.
- **`develop`**, after `git fetch origin`: `origin/develop` at `78ca172989a7e9876a3ffbc472a08235a4a99ffd`. TASK_PLAN section 4 there shows TASK-002 `DONE` and gate GATE-CC1 `SATISFIED — D-099`; DECISIONS holds D-099; OPEN_ITEMS holds T-25, its last T-row; COPY_DECK holds `ux.format.duration.minute` and `ux.format.duration.second` in section 1.3 and CC-21 in section 22.1.
- **The report**: SHA-256 `b8a2012a7f65f8bec7416d52a4c8b09a9cec9f07adc86544f69b29c7cd7b591e`, as the instruction gives it; 136,052 bytes.
- **The executor**: `claude-opus-5-5`; effort XHigh as the instruction names it, and the desktop app's record of the session shows `xhigh`; the session started on 20 October 2026 at 02:29, after the reviewer's session ended at 01:13.
- **The local environment**: stopped at the start; `docker compose up -d --build --wait` at 02:38, `app` and `db` healthy on the kept volume. Another Compose project of the machine was running and was not touched.

### 10.2 The merge of `develop` (EXC-36)

`git merge --no-ff --no-commit origin/develop` on 20 October 2026, committed at 02:37:46, merged the ten commits of `develop` since `fc0055b` — TASK-002 with its merge `0e9a393` and the D-099 bookkeeping `78ca172` — without conflict in code, COPY_DECK or an approved artifact. Two shared registers conflicted and were resolved by EXC-35:

- **TASK_PLAN section 4**: `develop`'s rows taken, TASK-002 `DONE`; TASK-003's row kept `VERIFYING`; the totals recounted from the 50 rows: `TODO` 47, `READY` 0, `IN_PROGRESS` 0, `BLOCKED` 0, `VERIFYING` 1, `DONE` 2, `SUPERSEDED` 0, as the instruction expects.
- **CURRENT_HANDOFF**: `develop`'s text taken, then TASK-003's own facts applied again: its status block and the TASKS line (`VERIFYING` on the branch), its findings F-1, F-2, F-3 and F-7 in section 7 with F-1 now keyed by CC-21, and its lines of the TOOLCHAIN and TESTS fields.

The merge commit is `5f6e291165d0e0ff31520ad54dc07d8ea8a5e81c` (parents `0ee8ab7` and `78ca172`). The command contract ran again on it from 02:39:03 to 02:46:56, every command `PASS`: `composer lint` with 34 keys and 30 key types; `composer test:unit` 304 tests and 850 assertions; `composer test:postgres` 4 and 16; `composer test:race` 1 and 6; `npm run test:unit` 6 files and 191 tests; `npm run test:e2e` 1 passed. The merge brought into COPY_DECK section 1.3 the percent format of CC-03 as well (F-8).

### 10.3 The duration formatter (instruction section 3)

The two keys of CC-21, verbatim, in `lang/id/ux.php`; the key type regenerated (32 keys); `Format::duration()` and `formatDuration()` (R-25); the 18 duration cases D1 to D18 in the shared table `formatting.json`, run by PHPUnit and Vitest; `CopyDeckTest` ties each key to the sentence of COPY_DECK section 1.3 that names it and to the row "Durations". Commit `67210f3`.

### 10.4 The regression tests before the fixes

Every regression test of section 9 was written first and run on 20 October 2026 from 03:12:59 to 03:16:55 against the unfixed code, `67210f3` with the new tests in the working tree, the differential table's expected outcomes then computed by the unfixed server:

| Suite | Result before the fixes |
| --- | --- |
| PHPUnit `Unit` | 473 tests: 48 failures and 1 error, each a case of a finding (section 9) |
| PHPUnit `Postgres` | 82 tests: 71 failures, 69 of `CanonicalNumberInputTest` (A-01, A-09) and 2 of `DictionaryOncePropTest` (A-10) |
| Vitest | 444 tests: 120 failed, in `client-edges.test.ts` (A-04, A-17), `format.test.ts` (A-05, A-14), `differential.test.ts` (the date and instant categories, A-05 and A-14) and `eslint.test.ts` (A-03, A-06, A-08) |

### 10.5 The regression tests after the fixes

The same suites on the fixed working tree, from 03:49:44 to 03:53:44, before the commits: PHPUnit `Unit` 473 tests and 381,681 assertions, `PASS`; PHPUnit `Postgres` 82 tests and 262 assertions, `PASS`; Vitest 8 files and 444 tests, `PASS`. The differential table's expected outcomes were computed again by the fixed server before this run. The fixes were then committed, each with its tests (section 7.6), and the command contract ran on the last code commit (section 7.1).

### 10.6 Self-review of the remediation (EXC-70)

The diff `0ee8ab7..HEAD` was reviewed against the instruction, the decision's required outcomes, ADR-005, ADR-006 and the readings. Corrections made before the commits: the float rule first left `PHP_INT_MAX + $units` and `(string)` of an invalid sum unreported, so it gained the overflow and cast checks of R-33; the project's own exact-value code needed one change, `BusinessDate::compareTo()` with `strcmp()`; the exact-number rules first refused a property named `parseInt` of an object type, so property signatures are excluded; the key check first missed a key after a comment, so comments are skipped; the passing sample of the key check now counts 8 lookups instead of 7, because `@lang` in a Blade view is read; the dictionary of an earlier request stayed on Inertia's response factory within one test, so `handle()` drops it (R-37); Pint's `fully_qualified_strict_types` and `single_quote` fixes were applied to two new files; and test code that PHPStan level 9, ESLint or the typecheck refused was corrected. Every commit message reads "TASK-003: …" under the Owner's identity, with no trailer and no mention of an AI model, agent or tool. Every file is LF. `tests/Adversarial/` was never created.

## 11. Close: acceptance, merge and `DONE` (20 October 2026)

Under the Owner's instruction of 20 October 2026, "TASK-003 close: acceptance (D-100), merge of pull request #3 and bookkeeping", which the planning agent prepared the same day after its verification of the remediation (section 9). Executed by the implementation executor in Claude Code Desktop on the Owner's Windows machine, in the main working tree: Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, as the instruction names it (D-031, D-095), and the desktop app's record of the session shows effort `xhigh`; a new session dedicated to the close, started on 20 October 2026 at 04:40. Git ran with `GIT_OPTIONAL_LOCKS=0`.

### 11.1 The Owner's decision

"Owner menerima TASK-003 (D-100): cabang `task/TASK-003-kernel-localization` pada `3cc9188a05dc12126f7397c64b2ecd040752fa82` (PR #3, CI 39325756602 SUCCESS), dengan seluruh temuan review adversarial kelas A ditangani sesuai keputusan dan verifikasi planning agent tanggal 20 Oktober 2026, termasuk batas lint yang dicatat (R-28, R-34, R-35) dan T-26 untuk TASK-008. Format persen (F-8) dibangun oleh TASK-040 sesuai kontraknya. Owner menyetujui merge PR #3 ke `develop` dengan merge commit lokal atas identitas Owner dan menerima TASK-003 sebagai DONE setelah merge dan CI-nya berhasil sesuai instruksi ini." (D-100)

### 11.2 Preflight

Checked on 20 October 2026 from 04:44 to 04:45:

- **The main working tree**: on `task/TASK-003-kernel-localization` at `3cc9188a05dc12126f7397c64b2ecd040752fa82`; nothing staged or modified; untracked only the three files of `Claude outputs/` and `plan-p0-p11-review.patch`; `tests/Adversarial/` absent.
- **Local refs**: `develop` at `78ca172989a7e9876a3ffbc472a08235a4a99ffd`; `git worktree list` shows `develop` checked out nowhere, and the worktree `bantukasir-task002` on `task/TASK-002-p7-change-control-1` at `936be82`.
- **Remote refs**, by an anonymous `git ls-remote origin`: `develop` `78ca172`; `task/TASK-003-kernel-localization` and `refs/pull/3/head` `3cc9188`; `task/TASK-002-p7-change-control-1` `936be82`; `task/TASK-001-toolchain-ci` `8f0b96a`; `main` `f7cecfe`; `plan/bantukasir-pos-v1` `86822d5`.
- **Pull request #3**: `OPEN`, base `develop`, head `3cc9188`, mergeable. **CI run 39325756602**: `success`, head `3cc9188`, event `pull_request`.
- **Tools**: no container of BantuKasir was running, so `docker compose down` was not needed; the volume and the image are kept. Gitleaks in `bantukasir-app:local`, run with `--network none`, prints `v8.30.1`.
- **The branch brought up to date (EXC-36)**: `git merge origin/develop` on the Task branch printed "Already up to date."; HEAD stayed `3cc9188`.

### 11.3 Merge commit

`git switch develop` (HEAD `78ca172`), then `git merge --no-ff` of `task/TASK-003-kernel-localization`, without conflict, committed at 04:45:50:

- **SHA**: `386f1388994282d143c105d815fa587244755d72`.
- **Parents**: `78ca172989a7e9876a3ffbc472a08235a4a99ffd`, then `3cc9188a05dc12126f7397c64b2ecd040752fa82`.
- **Tree**: `0b675dcbf27487b40f60bf602cb0fc4a8f814bc3`, equal to that of `3cc9188`.
- **Identity**: the Owner's configured identity as author and committer.
- **Message**: exactly the one line "Merge TASK-003 (pull request #3): shared kernel, formatting and localization"; `git interpret-trailers --parse` prints nothing. The executor's first check of the line count counted the blank line that `--format=%B` appends and stopped; the raw commit (`git cat-file commit`) shows the one line and its newline only.

### 11.4 Guard, push and CI

- **The guard**: `git rev-list develop --not origin/develop origin/task/TASK-003-kernel-localization` lists `386f138` only, and `git rev-list --objects` over the same range lists that commit object only, with no new tree or blob; its metadata is the Owner's identity and the one-line message. PASS.
- **The push**: `git ls-remote` unchanged since the preflight; `git push origin refs/heads/develop:refs/heads/develop` once, without force, at 04:49: `78ca172..386f138`. `ls-remote` then shows `develop` at `386f138`, with every other ref unchanged.
- **Pull request #3**: `MERGED` at 04:49:07, with merge commit `386f138`.
- **CI run 39330437264** (`ci`, event `push`, head `386f138`), from 04:49:07 to 04:51:12: `success`.

### 11.5 Status

AC-003-01 to AC-003-06 pass (section 7.2). What the Verification row of section 7.3 lists as still pending is done: the planning agent's verification, with no targeted re-review needed (section 9), the Owner's acceptance (section 11.1) and the merge with its CI (sections 11.3 and 11.4). Every dimension of the Definition of Done is PASS or N/A. TASK-003 is `DONE` (D-100; TASK_PLAN section 4). TASK-004 and TASK-005 are `READY`.

### 11.6 This bookkeeping

- **The five files**: DECISIONS (the row and terms of D-100, the next free identifier D-101); REQUIREMENTS (sections 22 and 23); TASK_PLAN (its Decisions row, the rows of TASK-003, TASK-004 and TASK-005 with the recounted totals, and section 6); this evidence (its status, F-8, sections 7.5 and 9, and this section); and CURRENT_HANDOFF, last.
- **The commit**: "docs: record the acceptance of TASK-003 (D-100)", on `386f138`, on `develop`.
- **Its SHA and its CI run** are in the executor's report.
