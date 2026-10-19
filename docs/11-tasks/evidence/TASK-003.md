# TASK-003 evidence — Shared kernel, formatting and localization

| | |
| --- | --- |
| Task | [TASK-003](../tasks/TASK-003-kernel-localization.md), size M, review class A, lane P |
| Status | `VERIFYING` (section 11); the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | The Owner's instruction of 19 October 2026, "TASK-003: Shared kernel, formatting and localization (executor instruction)", prepared by the planning agent on 19 October 2026 and sent by the Owner the same day, approving its whole content |
| Branch | `task/TASK-003-kernel-localization`, created from `develop` (EXC-22) |
| Base commit | `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, the D-098 bookkeeping commit on `develop` (parent `b212819a1761ffc468283d6813bf6ec7581d097e`) |
| Executor | Implementation executor in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, as the Owner's instruction names it (D-031, D-095), and the desktop app's record of the session shows effort `xhigh`; a new session dedicated to TASK-003, started on 19 October 2026 at 22:02 |
| Gates | None besides EXECUTOR, checked at the start (TASK_PLAN section 3) |
| Authorized beyond local commits | The local environment (`docker compose up -d --build --wait` and `docker compose down`, never `-v`); project-local dependency changes only if a need is proven; after local verification, the publication guard, non-force pushes of the Task branch, one pull request into `develop` and watching its CI. Not authorized: any merge, any push of `develop`, `main`, `plan/bantukasir-pos-v1` or a tag, and any change to an approved P7 artifact, COPY_DECK, GLOSSARY, OPERATIONS_RUNBOOK, OPEN_ITEMS, SOURCE_OF_TRUTH or DECISIONS |

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

## 3. What was built (contract section 4.1)

| Item | Built | Files |
| --- | --- | --- |
| 1. Value objects | Money on `BigInteger`, signed and unbounded, with `assertStored()` (0 to 999,999,999,999) and `assertVariance()` (±999,999,999,999); Quantity on `BigDecimal` at the product's precision 0 to 3, signed, with `assertLine()`, `assertMovement()` and `assertStock()`; Cost on `BigDecimal` at scale 6, KNOWN or UNKNOWN, never negative, with `assertUnit()` and `assertValue()`. Canonical parsing (`CanonicalNumber`) refuses every non-canonical form before Brick Math sees it; `Money::lineTotal()` rounds half-up once and refuses an overflow; `Cost::mulDiv()` and `Cost::quotient()` divide once at scale 6, half-up (ADR-008 section 5); UNKNOWN propagates and is never zero | `app/Support/Numbers/` |
| 2. Eloquent casts | `MoneyCast` (`bigint`), `QuantityCast` (`numeric`, precision from an argument or from the row) and `CostCast` (a KNOWN amount column, or the amount and status pair of SCHEMA section 3.2), with no float either way; their database round-trip is TASK-004's | `app/Support/Casts/` |
| 3. Validation rules | `MoneyRule`, `QuantityRule` (line and movement quantities) and `CostRule`, refusing JSON numbers and every non-canonical form with the COPY_DECK section 16.1 messages | `app/Support/Validation/` |
| 4. Clock boundary | `Clock` (`#[Bind(SystemClock::class)]`), `SystemClock` (UTC, following Carbon's test time) and `TestClock` (controllable); `BusinessDate` (the Asia/Jakarta date of an instant); `Period` with `PeriodKind` (`today`, `7d`, `this_month`, `last_month`, `custom`) as pure functions of "now", half-open UTC ranges, and `InvalidPeriod` for the refusals of TECHNICAL_RULES section 11.4 | `app/Support/Time/` |
| 5. Formatters | `Format` on the server and `resources/js/lib/format.ts` on the client: money, negative money, quantity, cost on screen, date, date and time, time of day, day name, counts and the variance words; the WIB marker through the copy that carries it (R-07). Durations are not built: stop condition 7, finding F-1 | `app/Support/Formatting/Format.php`, `resources/js/lib/format.ts` |
| 6. Exact numbers in TypeScript | Scaled `BigInt` helpers (`parseExact`, `toCanonical`, `roundHalfUp`, `lineTotal`, `sumMoney`, `subtractMoney`, `compareMoney` and the ranges); lint rules refusing `Number()`, `parseFloat`, `parseInt`, unary `+`, `Intl.NumberFormat`, `Intl.DateTimeFormat` and the `toLocale…String` methods in interface code | `resources/js/lib/exact.ts`, `eslint.config.js` |
| 7. Localization | `lang/id/` with the twelve files of COPY_DECK section 1.1; locale and fallback `id`, no switcher; the dictionary shared once by `HandleInertiaRequests::shareOnce`; `t(key, replacements)` typed with a key and placeholder type generated from `lang/id/`; the build checks; `Lang::handleMissingKeysUsing` throwing in tests | `lang/id/`, `app/Support/Localization/Dictionary.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/lib/i18n.ts`, `resources/js/types/translation-keys.ts`, `resources/js/app.tsx`, `scripts/checks/translation-types.php`, `scripts/checks/translation-keys.php`, `tests/TestCase.php`, `tests/MissingTranslationKey.php` |

`lang/id/ux.php` holds 30 keys, each copied verbatim from COPY_DECK and proven equal to it by `CopyDeckTest`: the 12 month names and 7 day names of section 1.3, the 4 keys of section 1.4, and the 7 validation messages of section 16.1 that the rules of item 3 use. The other eleven files return an empty array. No page key was added.

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
| R-09 | Durations: not built (F-1). | Stop condition 7 |
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
| R-24 | No layout adaptation under STK-22: every file is in a folder of STACK_AND_MODULES section 3 (`app/Support/…`, `lang/id/`, `resources/js/lib/`, `resources/js/types/`, `scripts/checks/`, `tests/Unit/`, `tests/Feature/`, `tests/Fixtures/`). STACK_AND_MODULES and TOOLCHAIN are unchanged: no library was added. Brick Math 1.0.0, `PINNED` in TOOLCHAIN, now runs in the command contract. | Contract section 11 |

## 5. Findings

| ID | Finding | Status |
| --- | --- | --- |
| F-1 | **Stop condition 7 (EXECUTION_CONTEXT section 14).** COPY_DECK section 1.3 gives the format of durations — "A number and a unit word", for example "60 menit; 30 detik" — but names no key for the unit words, unlike the month and day names (`ux.format.month.*`, `ux.format.day.*`). A duration formatter would have to write "menit" and "detik" as literals or invent keys; neither is allowed (COPY_DECK section 1.1, EXC-51). The duration formatter is therefore not built; everything independent of it is. The texts that need it are `ux.state.throttled`, `ux.state.session_warning`, `ux.announce.session_warning` (TASK-006) and `config.system_status.age` (TASK-039), whose `:durasi` holds a duration. Keys for the unit words — at least seconds and minutes, and whatever unit `config.system_status.age` shows — need COPY_DECK change control and the Owner's approval by hash before a Task builds the formatter | Open: the Owner decides the change control |
| F-2 | Laravel 13.37's validator looks up four optional groups of translations — `validation.attributes`, `validation.custom`, `validation.custom.<attribute>.<rule>` and `validation.values.<attribute>.<value>` — and falls back silently when they are absent. A handler that throws on every missing key fails every validation, so the handler lets exactly these four through (R-17). A consequence for every later FormRequest: a built-in rule such as `required` without a COPY_DECK message throws in tests, because its default `validation.<rule>` would be printed | Handled; noted for later Tasks |
| F-3 | ADR-006 item 4 and ADR-008 section 5 write `RoundingMode::HALF_UP`; Brick Math removed that constant in 0.15.0, and the pinned 1.0.0 names the same rule `RoundingMode::HalfUp`. The rule itself is met. The ADRs are not changed here (contract section 11) | Recorded for the planning agent |
| F-4 | In Laravel 13.37, `Translator::handleMissingTranslationKey()` turns missing-key handling off before calling the handler and turns it back on only when the handler returns; after the handler throws once, that translator ignores later missing keys. Each test has a new application, so this matters only after a first throw within one test, which already fails it | Recorded |
| F-5 | The client keeps a once prop only while it moves between pages that include it (Inertia v3 documentation). The dictionary is shared on every Inertia response by the middleware, so every page includes it | Recorded |
| F-6 | Brick Math runs its native calculator, because the image has neither GMP nor BCMath. It is exact; its speed is a matter for the performance Tasks (TASK-043, TASK-048) | Recorded |
| F-7 | COPY_DECK names no validation message for a cost field, and its "too large" message for quantities names the line maximum 99.999.999,999, which does not fit a minimum-stock field (maximum 9.999.999.999,999). `CostRule` takes its messages from the FormRequest, and `QuantityRule` serves line and movement quantities; the Tasks that add a cost field (TASK-021, TASK-025) or a minimum-stock field (TASK-015) take their messages from COPY_DECK, through change control where it has none | Recorded for those Tasks |

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

The standing obligations of EXECUTION_CONTEXT section 10 do not apply: TASK-003 adds no route, table, command, read, page, operator procedure or document. The route of `DictionaryOncePropTest` is registered by that test only.

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

Counts: 58 canonical, 11 line totals, 2 Sale totals, 12 rounding, 73 format and 7 placeholder cases.

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

## 7. Close (EXECUTION_CONTEXT section 15)

### 7.1 The command contract, the run of record

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

An earlier run on the working tree, from 23:01 to 23:12, before the commits, found two type errors in the new Vitest files in `npm run typecheck` and `npm run build`; they were corrected (section 7.4), and every command then passed.

### 7.2 Acceptance criteria

| Criterion | Status | Reason |
| --- | --- | --- |
| AC-003-01 | PASS | 1.5 × Rp3.333 gives Rp5.000 (L1), half-up once per line (L1 to L11, S1), six-decimal cost (`CostArithmeticTest`, R8 to R12), quantities with 0 to 3 decimals (N30 to N48), an overflow refused (L10, L11), on the server and the client; no floating point (the float static check and the lint rules) |
| AC-003-02 | PASS | Every non-canonical form of ADR-006 item 2 is refused on both sides (N5 to N29 and the other refused N cases) and by the three rules; JSON numbers are refused by the rules; the value objects serialize as strings, also through a model (`ValueObjectsTest`, `CastsTest`) |
| AC-003-03 | PASS | `composer analyse` passes on `app/Support` and `app/Domain` with the float rule; the rule reports its negative sample, two new lines included (`PhpstanRulesTest`) |
| AC-003-04 | BLOCKED for durations; PASS for every other format | Money, negative money, quantity, cost on screen, date, date and time, time of day, counts, day names and the variance words give exactly the COPY_DECK texts on the server and the client from the same table (F1 to F73), also under other time zones and locale settings; the WIB marker is the copy's (R-07). Durations wait for COPY_DECK keys of their unit words (F-1) |
| AC-003-05 | PASS | B1 to B8 and T1 to T19, with the test clock |
| AC-003-06 | PASS | A key missing from `lang/id/` fails `composer lint` (the key check), `npm run typecheck` and `npm run build` (TS2345); a literal in JSX fails lint (TASK-001's samples, still passing); a missing server key throws in tests; the dictionary reaches React once per full page load (`DictionaryOncePropTest`, `app.tsx`) |

### 7.3 Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | BLOCKED | AC-003-01, 02, 03, 05 and 06 pass; AC-003-04 passes except durations, which wait for COPY_DECK change control (F-1). REQ-COST-02, REQ-NFR-01, REQ-NFR-09 and REQ-DOC-05 are carried as section 5 of the contract says; the shares of FIN01, POS02, SESSION09, FIN03 and L10N01 are early evidence, not `FINAL_PASS` (TS-09) |
| UI and UX | N/A | No page or control |
| Backend | N/A | No route or Action; the middleware shares one more prop |
| Database | N/A | No migration; the casts' round-trip on PostgreSQL is TASK-004's |
| Security | PASS | No secret in the tree or the commits (section 7.1); the float check, the exact-number lint rules and the literal check refuse their samples; every non-canonical number and JSON number is refused, never repaired. The contract names no threat path, and with no route there is no refusal of a role to prove |
| Data integrity | N/A | Writes no business fact |
| Performance | N/A | No request path; F-6 for the performance Tasks |
| Operations | N/A | No backup, runbook or status impact |
| Verification | BLOCKED | The command contract is green locally (section 7.1) and the self-review is done (section 7.4); CI runs on the pull request after the push (section 8); the reviewer's check, the adversarial review of class A (EXC-71) and the Owner's acceptance are pending |

### 7.4 Self-review (EXC-70)

The whole diff `fc0055b..HEAD` was reviewed against contract sections 4.1, 6, 7 and 11, ADR-005, ADR-006, ADR-008 section 5 and the readings of the instruction. Corrections made before the commits:

- the casts' `set()` refused a non-object at run time but PHPStan read their parameter as the value object; the parameter is documented as any value, so the run-time refusal stays visible to the analysis;
- the typed `t()` reported a key missing from `lang/id/` as a wrong number of arguments; its replacement type was made distributive (R-16);
- the missing-key handler failed every validation through the validator's probes (F-2, R-17);
- the generator of the key type was moved into `scripts/checks/`, the folder of STACK_AND_MODULES section 3;
- test code that PHPStan level 9 or the typecheck refused was corrected; the application code had no finding.

No other defect was found. Every commit message reads "TASK-003: …" under the Owner's identity, with no trailer and no mention of an AI model, agent or tool. While the commits were split, three staged removals of `.gitkeep` placeholders were unstaged with `git restore --staged` and staged again with the commit they belong to; the working tree was not touched. Every file is LF.

### 7.5 What is not verified, and why

- **Durations** (F-1): not built.
- **CI**: it runs after the push (section 8).
- **The casts on PostgreSQL**: TASK-004 proves their round-trip (contract item 2).
- **A real browser**: no page exists yet, so the dictionary in a browser page, `t()` in React and the formatters under a browser set to another locale are proven in Node (Vitest) and through the HTTP kernel, not in Chromium; the Playwright smoke test of TASK-001 still passes.
- **Server locales**: the container has only the locales `C`, `C.utf8` and `POSIX`; the server cases ran under those, three application locales and four time zones.
- **`npm ci`**: not run locally; the lockfile is unchanged and the existing `node_modules` was used. CI runs `npm ci`.
- **The adversarial review of class A** (EXC-71): pending, in a separate session under its own instruction.

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

## 8. Publication

Under the instruction's step 6 onwards: the publication guard over `git rev-list task/TASK-003-kernel-localization --not origin/develop`, the push of the Task branch without force, one pull request into `develop` and its CI. Their results, the pull request and every CI run are in the executor's report to the Owner, and the repository records them with the bookkeeping that follows, as it did for TASK-001 (D-098).

## 9. Reviews

- **Self-review** (EXC-70): section 7.4.
- **Reviewer's check** (EXC-70): pending.
- **Adversarial review of class A** (EXC-71; contract section 8, "the adversarial review of the arithmetic"): pending, in a separate session under its own instruction. Its findings, fixes and their verification are recorded here when it has run.
