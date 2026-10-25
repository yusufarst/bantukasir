# TASK-005 evidence — Design system and frontend foundation

| | |
| --- | --- |
| Task | [TASK-005](../tasks/TASK-005-design-system.md), size L, review class B, lane P |
| Status | `DONE` when the Owner squash-merges its pull request and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101, continuous execution (EXECUTION_CONTEXT section 19, CE-01): the selection of CE-10 at `origin/develop` `3dd2b58`, the lowest-numbered `READY` Task with its dependencies `DONE` and its gate GATE-CC1 satisfied; mode `start`, delegated by `scripts/executor/tick.sh` on 24 October 2026 at 17:08 to the agent `bk-executor-high`. The Owner's own words for this run: follow the whole D-101 contract; each commit and each push need his separate approval; no merge. After the stop `REVIEW_MUST_FIX` (section 9.2), resumed in mode `resume` on 24 October 2026 at 21:01, in the phase `REVIEW`, with the Owner's answer D-102 as his note (CE-72; section 9.3). After the second stop `REVIEW_MUST_FIX` (section 9.4), resumed in mode `resume` on 24 October 2026 at 22:49, in the phase `REVIEW`, with the Owner's answer D-103 as his note (section 9.5). After the pull request, the fix cycle for the Owner's change request, in mode `fix` on 25 October 2026 at 05:30 (section 10) |
| Branch | `task/TASK-005-design-system`, created from `origin/develop` (CE-02) |
| Base commit | `3dd2b589ddd1b93c072a3866702c7afe0411a3ca`, the squash commit of pull request #5 (TASK-004) on `develop` |
| Executor | The implementation executor agent `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11) |
| Reviewers | `bk-reviewer-high` for the reviewer's check of class B and for the verification of the fixes (CE-11, CE-40); section 9 |
| Gates | GATE-CC1 SATISFIED (D-099); EXECUTOR, checked at the start (TASK_PLAN section 3) |
| Authorized beyond local commits | By CE-01, for this Task only: its branch, its commits and pushes, each approved by the Owner at its prompt (CE-30), one pull request into `develop` and its CI, and the fixes of CE-40 to CE-43. Not authorized: any merge, auto-merge or approval; any push of `develop`, `main` or `plan/bantukasir-pos-v1`; a tag; a GitHub setting; staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24, CE-20)

- **Read path (EXC-01).** AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT (section 19 first), TASK_PLAN sections 1 to 4 and the contract, then its "Read first": DESIGN_SYSTEM sections 1 to 14; KEY_SCREENS sections 1 to 5 with the previews' `assets/` and `ds-specimen.html`; ADAPTIVE_AND_ACCESSIBILITY sections 2 to 4; UI_STATES sections 1 and 2; COPY_DECK sections 1, 2, 5 and 12, with sections 4.3, 15 and 18 for the keys of the shared components; SECURITY_MODEL SEC-HDR-03; TOOLCHAIN section 3; TEST_STRATEGY TS-25; ADR-005 item 5 as the existing checks implement it; and, where a cited rule pointed to them, STACK_AND_MODULES section 3 and the TASK-007 contract's item 5 (to keep the application-wide policy out of this Task).
- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names; no stop.
- **Executor state and GitHub (EXC-20 under CE-20)**, on 24 October 2026 at 17:09: phase `BUILD`, Task TASK-005, branch `task/TASK-005-design-system`, base `3dd2b58`, effort `high`, agents `bk-executor-high` and `bk-reviewer-high`; the merge of pull request #5 verified by the tick (`MERGE_VERIFIED`, 17:08:30); the lock held by this run. Local `develop` and `origin/develop` at `3dd2b589ddd1b93c072a3866702c7afe0411a3ca`; `git ls-remote origin`: `develop` `3dd2b58`, `main` `f7cecfe`, `ops/continuous-execution` `2ead8a0`, `plan/bantukasir-pos-v1` `86822d5`, `task/TASK-001-toolchain-ci` `8f0b96a`, `task/TASK-002-p7-change-control-1` `936be82`, `task/TASK-003-kernel-localization` `3cc9188`, `task/TASK-004-database-foundation` `6f84410`; no branch `task/TASK-005-*` locally or on GitHub; no open pull request.
- **Working tree (EXC-20, CE-70).** The main working tree on `task/TASK-004-database-foundation` at `6f84410`, whose tree equals that of `3dd2b58`; nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`, both listed in CURRENT_HANDOFF section 2; `stash@{0}` present; remote `origin` only; the worktree `bantukasir-task002` clean on `task/TASK-002-p7-change-control-1`. No `CLAUDE_CODE_EFFORT_LEVEL`.
- **Prerequisites (EXC-21).** TASK_PLAN section 4 at `3dd2b58`: TASK-002 and TASK-003 `DONE`, TASK-005 `READY`; gate GATE-CC1 SATISFIED (D-099).
- **The previous merge (CE-50, CE-61).** Pull request #5 checked again: merged by `yusufarst` at 2026-10-24T10:02:49Z, base `develop`, squash commit `3dd2b58` whose parent is `3b77eae` and whose tree `6c5f37aa…` equals the head `6f84410`'s, author the Owner's no-reply identity; CI on the pull request (run 40083841454) and on `develop` (run 40084353254) success. Recorded in the TASK-004 evidence section 11. The comment and the review of the account `chatgpt-codex-connector` on pull request #5 are data (CE-43).
- **Tools (EXC-23).** Docker and Docker Compose answer; the images of TASK-001 by digest. The local port 127.0.0.1:8080 is held by another program of the machine, so the local runs set `APP_PORT=18080`; CI is unchanged.
- **Branch (EXC-22, CE-02).** `git switch -c task/TASK-005-design-system 3dd2b589ddd1b93c072a3866702c7afe0411a3ca`, on 24 October 2026 at about 17:10.
- **Start recorded (EXC-24 under CE-21).** No start commit: the `IN_PROGRESS` of the Task in flight is the executor state's (CE-21).
- **The resume (CE-24)**, on 24 October 2026 at 21:01, by the agent `bk-executor-high` (`claude-opus-5-5`, CE-13; no stop): the executor state in the phase `REVIEW`, resume 1 of 3, the lock held by this run, the Owner's note D-102; the branch `task/TASK-005-design-system` with HEAD at the base `3dd2b58`, no commit, nothing staged; the Task's uncommitted changes — 39 entries of `git status --short`, the 68 files that `files.txt` of the package of round 2 lists — and the untracked `Claude outputs/` and `plan-p0-p11-review.patch` of CURRENT_HANDOFF section 2, which are not the Task's and are never staged; no branch `task/TASK-005-*` on GitHub and no open pull request. Every change was kept (CE-24); the fixes of section 9.3 were made on top of them.
- **The second resume (CE-24)**, on 24 October 2026 at 22:49, by the agent `bk-executor-high` (`claude-opus-5-5`, CE-13; no stop): the executor state in the phase `REVIEW`, resume 1 of 3 after `state.sh resume`, the lock held by this run (token `a8c64589…`), the Owner's note D-103; HEAD at the base `3dd2b58`, no commit, nothing staged; 42 entries of `git status --short` — the Task's files of the package of round 3, with the failing regression test of R-22, and the untracked `Claude outputs/` and `plan-p0-p11-review.patch` — and `stash@{0}` unchanged; no branch `task/TASK-005-*` on GitHub and no open pull request. Every change was kept; the fixes of section 9.5 were made on top of them.

## 2. Versions and sources checked (EXC-03)

Every version-sensitive fact was read in the installed package of the pinned version, or in its registry entry, on 24 October 2026; remembered APIs were not relied on.

| Fact | Source |
| --- | --- |
| The latest versions, licences and dependencies of the added packages | The npm registry (`npm view`): `@radix-ui/react-dialog` 1.3.1, `@radix-ui/react-dropdown-menu` 2.2.1, `@radix-ui/react-radio-group` 1.5.1, `@radix-ui/react-toggle` 1.2.1 (MIT, published 14 October 2026, peer React ^19); `get-nonce` 1.0.1 (MIT); `@axe-core/playwright` 4.14.0 with `axe-core` ~4.14.0 (MPL-2.0, peer `playwright-core` >= 1.0.0); `@fontsource-variable/inter` 5.3.0 (OFL-1.1) |
| Laravel generates the nonce on the tags of `@vite` | `vendor/laravel/framework/src/Illuminate/Foundation/Vite.php` 13.37.0: `useCspNonce()` sets a random 40-character nonce, `cspNonce()` returns it, the generated `script`, `link` and preload tags carry it |
| Inertia gives its injected styles a nonce | `@inertiajs/react` 3.9.1 `createInertiaApp({ nonce })` sets the configuration that `@inertiajs/core` 3.9.1 applies to the style of the progress indicator and of the error dialog; the page itself travels in a `<script type="application/json">` (`inertia-laravel` 3.5.1 `Directive.php`), which is not executed |
| The scroll lock of the Radix dialog injects a style | `react-remove-scroll` → `react-style-singleton` 2.2.3, which reads the nonce from `get-nonce`'s `getNonce()` |
| The Radix dialog's focus on open and close | `@radix-ui/react-dialog` 1.3.1: `onOpenAutoFocus` and `onCloseAutoFocus`; without a `Dialog.Trigger` the close focuses the trigger reference, which is empty, so the components return the focus to the opener themselves (R-09) |
| A Radix radio item chooses on focus only while an arrow key is down | `@radix-ui/react-radio-group` 1.5.1 `RadioGroupItemTrigger` and `@radix-ui/react-roving-focus` 1.2.1, which moves the focus in a `setTimeout` (R-13) |
| axe's label-in-name rule drops parenthetical text | `axe-core` 4.14.0 `isLabelContainedInName()` and `removeParentheticals()` (R-07) |
| The font files and their faces | `@fontsource-variable/inter` 5.3.0: `files/inter-latin-opsz-normal.woff2`, `files/inter-latin-ext-opsz-normal.woff2`, `opsz.css` (unicode ranges) and `LICENSE` (SIL OFL 1.1) |

The **Golden Frontend Reference v1.0.0** was not read by this Task (EXC-05, AC-005-08). It contributed through the approved DESIGN_SYSTEM (section 1.2) and the previews, which transcribed its generic layer at commit `14497d9` in RF-4C (D-086): the names, roles and values of the tokens, the outline icon anatomy, and the anatomy, states, sizes and keyboard behaviour of the components listed in section 3. No file of the reference was read, copied or imported, and no MULTIPLECORP material — business example, branding, fixture, persona or workflow — was used.

## 3. What was built (contract section 4.1)

| Scope item | Built | Where |
| --- | --- | --- |
| 1 Tokens | The tokens of DESIGN_SYSTEM sections 3 to 5 as CSS variables, every value equal to the approved `bk-tokens.css`, the accent set by the one attribute `data-accent` with green by default and an allowlist (`lib/accent.ts`); the Tailwind theme with only these tokens and the width classes `medium` and `expanded` (R-16); the icons of section 6 as `Icon` | `resources/css/tokens.css`, `app.css`; `components/ui/icons.ts`, `Icon.tsx` |
| 2 Font | Inter, variable with optical size, self-hosted with `OFL.txt`; the size-adjusted Segoe UI fallback | `public/fonts/inter/`, `resources/css/fonts.css` |
| 3 Components | Section 8.1 to 8.16, below | `resources/js/components/ui/`, `resources/css/components.css` |
| 4 Layout | The width classes at 600 and 1024 CSS px in the tokens, the theme and `layouts/widthClass.ts`; the containers of 1440, 960, 640 and 420 px; the page header with the back action and the one level-1 heading; the archetypes Home, List, Report, Command page, Result and Pre-authentication of section 7.3, the till workspace being 8.14; no shell (TASK-007) | `resources/js/layouts/` |
| 5 States | The catalogue of UXS-01 to UXS-50 with each COPY_DECK key (R-11) and the presentations: loading, empty, no results, committed, the error summary, the busy button, the disabled reason, the uncertain and held panel, the stale read, the failed read, the incomplete cost, the unknown code, the tendered amount too low, the marks of UXS-24, UXS-26, UXS-32 and UXS-43, `ReadValue` for figures, and `StateNotice` for every notice state | `components/ui/stateCatalogue.ts`, `States.tsx`, `Data.tsx` |
| 6 Shortcuts | The registry of DESIGN_SYSTEM section 11 with each shortcut in the accessible name; Enter never activates a final, confirm or print action (`enterActivates`); the repeat guard of Ctrl+Enter; the page's shortcuts wait while a dialog or drawer is open | `components/ui/shortcuts.ts`, `useShortcut.ts`, `Button.tsx` |
| 7 CSP | Every component renders under SEC-HDR-03 with no violation: the nonce on `<meta name="csp-nonce">`, given to Inertia and to `get-nonce`; no inline style in the markup; keycaps and labels in the bundled stylesheet | `app/Http/ContentSecurityPolicy.php`, `lib/csp.ts`, `app.tsx`, `resources/views/app.blade.php` |
| 8 Harness | The component specimen at `/testing/specimen`, registered only in the local and testing environments (R-02); the Playwright harness with axe, the widths of KEY_SCREENS section 4 and 1440 px, the 200 % zoom, the contrast and focus-ring measurements, reduced motion and the CSP watch | `app/Http/Controllers/Testing/`, `resources/js/pages/Testing/`, `tests/Browser/` |

### 3.1 The components and the reference of each

Each follows DESIGN_SYSTEM section 8 and the approved previews (`bk-components.css`, `bk-app.js`), which carry the reference's generic anatomy as RF-4C translated it; the behaviour comes from the Radix UI primitive of shadcn/ui where one exists (R-01).

| # | Component | Built on | Reference used |
| --- | --- | --- | --- |
| 8.1 | `Button`, `ButtonLink`: primary, secondary, quiet, danger; 44, 48, 56 px; icon buttons 44 × 44; keycap; busy; row names | `button` | DESIGN_SYSTEM 8.1; preview `.bk-btn`, `.bk-kbd`, `.bk-spinner` |
| 8.2 | The final action: `Button size="final"`, 64 px, full width, 18 px, 24 px apart (`.bk-final`), no Enter | `button` | 8.2; preview `.bk-btn--final`, `.bk-final` |
| 8.3 | `Field`, `TextInput`, `MoneyInput`, `CommandForm` | `label`, `input`, `form` | 8.3; preview `.bk-field`, `.bk-input` |
| 8.4 | `SegmentedControl`, `FilterChip`, `FilterChips` | Radix radio group; Radix toggle | 8.4; preview `.bk-seg`, `.bk-chip` |
| 8.5 | `StatusWord`, `Pill` | `span` | 8.5; preview `.bk-status`, `.bk-pill` |
| 8.6 | `Notice`, `RecoveryPanel`, `StateNotice` and the state presentations | `div` with `role` | 8.6, section 9; preview `.bk-notice` |
| 8.7 | `Button disabledReason`, `DisabledReason` | `aria-disabled`, `aria-describedby` | 8.7; preview `.bk-reason` |
| 8.8 | `Dialog`, `ReviewDialog` | Radix dialog | 8.8; preview `.bk-dialog` |
| 8.9 | `Menu`, `Drawer` | Radix dropdown menu; Radix dialog | 8.9; preview `.bk-menu`, `.bk-drawer` |
| 8.10 | `Card`, `Section` | `div`, `section` | 8.10; preview `.bk-card`, `.bk-section` |
| 8.11 | `DataTable` with row cards and wide tables | `table` | 8.11; preview `.bk-table` |
| 8.12 | `Figure`, `Figures`, `KeyValueList`, `LinkRow`, `LinkRows`, `ValueText` | `dl`, `a` | 8.12; preview `.bk-metric`, `.bk-kv`, `.bk-row` |
| 8.13 | `TrendChart` | SVG computed with BigInt | 8.13, UXA-19; preview `.bk-chart` and the chart of `bk-app.js` |
| 8.14 | `TillWorkspace`, `ScanField`, `ProductResults`, `ProductResult`, `CartRegion`, `CartLine`, `QuantityStepper`, `CartTotal` | `section`, `ul`, `button`, `input` | 8.14, KS-03; preview `.bk-till`, `.bk-cart`, `.bk-stepper` |
| 8.15 | `Steps` | `ol` with `aria-current="step"` | 8.15; preview `.bk-steps` |
| 8.16 | `ReceiptPaper`, `PaperShop`, `PaperTitle`, `PaperRule`, `PaperRow`, `PaperItems`, `PaperItem` | `article` | 8.16, section 12; preview `.bk-paper` |
| — | `AnnouncerProvider`, `useAnnounce`, `announcements` | two live regions | 10, UXA-17; COPY_DECK section 18 |

## 4. Readings and technical choices

- **R-01 shadcn/ui without its CLI.** shadcn/ui is a pattern of component source on Radix UI primitives, not a runtime library; its CLI only copies that source. The components are written in that pattern on the Radix dialog, dropdown menu, radio group and toggle, with the design system's own classes instead of utility strings, and without `class-variance-authority`, `tailwind-merge` or `lucide-react`: `cn()` joins classes, the variants are typed unions, and the icons are BantuKasir's own drawings (R-14). TOOLCHAIN records the CLI as not installed.
- **R-02 The specimen.** "A component specimen available only in the testing environment" is read as a route that `routes/web.php` registers only when the environment is `local` (the local and CI environment of `compose.yaml`, where the browser tests run) or `testing` (PHPUnit), never in staging or production, which set their own names (`SpecimenTest`). It is not a route of ROUTE_CONTRACTS, reads and writes nothing and shows no business data, so section 4.2 "Routes: —" holds and the standing route obligations of EXECUTION_CONTEXT section 10 do not apply. Its texts come from the `ux.*` keys where COPY_DECK names one, and otherwise from `specimenFixtures.ts`, synthetic sample props (the specimen marks its own headings and the archetypes' fixture-only elements with `data-fixture`): the page keys are the page Tasks' (contract section 4.3). Its script is a lazy chunk of the build, unreachable without the route; TASK-012 may leave it out of the release image.
- **R-03 The policy of the specimen.** `ContentSecurityPolicy::header()` builds the baseline of SEC-HDR-03 with the request's nonce, and refuses a nonce that could change the policy. The specimen's controller applies it to its own response; TASK-007 applies it to every response (its item 5) and may then drop the controller's own header.
- **R-04 The JSX literal rule.** TASK-003's rule refuses every literal in an attribute that is not on an allowlist of attributes that never hold text. The components need more of them: the geometry and presentation attributes of SVG, `enterKeyHint`, `spellCheck`, `scope`, `aria-keyshortcuts`, the data attributes, and the props whose TypeScript type is a union of fixed values (`variant`, `size`, `tone`, `icon`, `shortcut`, `kind`, `width`, `align`, `level`, `urgency`). The allowlist is extended with them, with compliant and negative samples in `tests/Fixtures/StaticChecks/eslint/`; a text prop such as `label`, `hint`, `title`, `placeholder` or `aria-label` still refuses a literal. Data attributes hold no text because no stylesheet reads text from an attribute (`assets.test.ts`). A prop of another name typed `string` would pass the rule: code review covers it, as for R-28 of TASK-003.
- **R-05 Literals outside JSX.** Values such as option lists, column definitions and fixture amounts stand in constants outside the JSX, where the rule does not look; in the components they are never user-facing text. The keycaps (`F2`, `F8`, `Alt+1`, `Alt+2`, `Alt+U`, `Ctrl+Enter`, `Esc`) are key names as COPY_DECK section 5.1 writes them, not translated copy; `Rp` is the currency symbol of COPY_DECK section 1.3, exported by `format.ts` as `RUPIAH_SYMBOL`.
- **R-06 Copy keys.** The keys that the components and the specimen use are created with exactly the names of COPY_DECK (EXC-51): the `ux.state.*` texts of section 12 for all fifty states, and the shared `ux.action.*`, `ux.list.*`, `ux.nav.menu_open` and `menu_close`, `ux.announce.*`, `ux.disabled.no_change`, `form_incomplete` and `unresolved_family`, and `ux.validation.required`. A state key is the parent of its texts (COPY_DECK 1.1): `.title` and `.body`; the accessible text "Memuat data" of UXS-01 is its `.body`; the texts that the rows name inline keep their names. `CopyDeckTest` now proves each against section 12; an inline text runs to the next inline label or the end of the cell with its full stop, so `ux.state.price_changed.line` is "Harga lama :lama → harga baru :baru." with its full stop.
- **R-07 The keycap is drawn by the stylesheet.** axe's label-in-name rule drops parenthetical text from the accessible name ("Bayar (F8)" → "Bayar") but not from the visible text, so a keycap written in the markup fails it. The keycap is decorative and hidden from assistive technology; the stylesheet draws it from the shortcut's identifier (`.bk-kbd[data-key='pay']::after`), and `assets.test.ts` proves that the drawn keys equal the registry.
- **R-08 The focus ring on a status tint.** DESIGN_SYSTEM section 3.5 measures the ring on the canvas, on white and on tint 150, the lowest 5.91:1, and sections 3.5 and 10 ask at least 5.91:1 against what surrounds it. Inside a notice on a status tint the green accent reaches less — 5.58:1 on the info tint, 5.66:1 on the danger tint, 5.85:1 on the success tint — so inside a notice on a status tint — every tone but neutral, whose sunken surface keeps 5.99:1 (review finding R-19) — the ring takes the accent's strong shade `--brand-accent-strong`, 7.07:1 or more for every accent and tint. No token changes; the ring stays 2 px, offset 2 px, in the shop's accent. A text field shows its focus on its 44 px box, the accent border and a 1 px ring around it, 2 px in all, as the previews do.
- **R-09 Focus return.** Without a `Dialog.Trigger`, the Radix dialog's close focuses an empty trigger reference; `Dialog` and `Drawer` remember the opener when they open and give the focus back to it when they close (UXA-08). A layer opened from a menu item remembers that item, which leaves the page when its menu closes, so it also remembers the menu's button — the element whose `aria-controls` names the menu that holds the focus — and gives the focus back to it when the item is no longer connected (review finding R-22, D-103). The Radix menu item runs its `onSelect` in a synchronous update before it closes the menu, so the layer opens while the item still has the focus and the button still names the menu.
- **R-10 Menus are not modal.** A modal Radix menu hides the page from assistive technology, so the page loses its main landmark and level-1 heading while the menu is open; the menu is non-modal and rendered in place. Esc, a click outside and the arrow, Home and End keys behave as 8.9 states.
- **R-11 The catalogue of the states.** `STATE_CATALOGUE` has one entry for each state and for each variant that COPY_DECK section 12 words separately — 63 entries for UXS-01 to UXS-50 — with its keys, tone, urgency (UXA-17) and presentation. Every `ux.state.*` key of `lang/id` is named in it (`assets.test.ts`). A state whose presentation is a page's — the destination of UXS-04, the redirect of UXS-29, the marked cart lines of UXS-18 — still has its message as a notice; pages place the states where UI_STATES section 6 says.
- **R-12 Sections are not landmarks.** A `Section` is a `section` with a heading but no accessible name, so it is not a region landmark; the labelled regions are those UXA-13 names, such as the cart, and a wide table's scroll container.
- **R-13 Radix radio arrows in tests.** The Radix radio item chooses on focus only while an arrow key is still down, and moves the focus after a `setTimeout`; a person's key press lasts longer than that, but Playwright's `press` releases the key at once, so the test holds the key with `down` and `up`.
- **R-14 Icons.** The 35 outline drawings of the approved previews, BantuKasir's own (DESIGN_SYSTEM section 6), transcribed path for path (`assets.test.ts`); no third-party icon set is added.
- **R-15 Fonts.** The Latin and Latin Extended subsets of the opsz faces of `@fontsource-variable/inter` 5.3.0 cover every character of COPY_DECK's copy except the arrow → of `ux.state.price_changed.line`, which the fallback font draws (F-1). The package is a dev dependency that only proves the files' provenance (`assets.test.ts`).
- **R-16 The Tailwind theme.** Tailwind's default palette, scale and breakpoints are removed (`--*: initial`), and the theme maps only the approved tokens, inline, so that the accent of `data-accent` and the compact values apply; the spacing base is 4 px, so that `p-1` is 4 px and `p-16` 64 px.
- **R-17 A test of TASK-003.** `LocalizationTest` counted 32 keys in the dictionary; it now counts the keys of `lang/id`, at least 32, so that each interface Task can add its keys.
- **R-18 The 1.5 px box of "Salinan".** The stylesheet sets 1.5 px; Chromium draws borders on whole device pixels, so at a device pixel ratio of 1 it renders 1 px. Paper widths render at 219, 302 and 794 CSS px for 58 mm, 80 mm and A4.
- **R-19 Values never zero.** `ReadValue` allows a figure to be `loaded`, `loading`, `failed`, `stale` or an `unknown_cost`; only `loaded` and `stale` show a number, and `stale` with "Data belum diperbarui".
- **R-20 The tones of UXS-14, UXS-15 and UXS-25** (review finding R-01). The previews give the uncertain and held panels the pending tone and the incomplete-cost notice the warning tone, while DESIGN_SYSTEM section 3.2 gives "Status belum pasti" the warning tone and section 9 makes UXS-14 and UXS-15 a warning panel and UXS-24 and UXS-25 the pending tone. DESIGN_SYSTEM section 1.1 settles it: where a stylesheet and the document differ, the document prevails. The components follow DESIGN_SYSTEM — warning for UXS-14 and UXS-15, pending for UXS-24 and UXS-25 — and the catalogue is the one source of each tone; the difference of the previews is finding F-4.
- **R-21 Ctrl+Enter from a control that only blocks Enter** (review findings R-02, R-15 and R-16; D-102). A handler that took a key wins: every shortcut yields to a control that prevented its key's default. The one exception is Ctrl+Enter from a control that never acts on Ctrl+Enter, though it may block it, `BLOCKS_ENTER_ONLY` of `shortcuts.ts`: a radio item, whose Radix handler prevents the default of Enter, Ctrl+Enter included; a field of a command form, which since the fix of R-16 prevents it too, so that no Enter, with or without Control, submits the form; and the field of the quantity stepper, which acts on and prevents plain Enter only, committing the quantity (finding R-24). The fields are matched by the attribute `data-bk-enter-blocked` (`ENTER_BLOCKED_ATTRIBUTE`) that `CommandForm` and `QuantityStepper` carry, not by a styling class, and the comments of both and of the registry state the rule that an input inside such a container never acts on Ctrl+Enter (finding R-25). From these Ctrl+Enter still reaches the confirm binding. A control that acts on Enter and then prevents its default — the Radix menu button, which opens its menu, and a menu item, which runs itself — keeps the key, and the registry yields to it, so that one press does one thing (UXA-09). The exception matches the focused element itself, not an ancestor, so a menu button inside a command form keeps its key too. A final action (`size="final"`) never takes Enter, whatever `enterActivates` says (finding R-14); the specimen's final action relies on that guard alone (finding R-18).

## 5. Findings

- **F-1 (for TASK-029 or a later change).** The arrow → (U+2192) of `ux.state.price_changed.line` is outside the self-hosted subsets, so the fallback font draws it. Adding the "arrows" range or the full file is a later choice; nothing is shown wrongly.
- **F-2 (resolved by R-08).** The statement of DESIGN_SYSTEM sections 3.5 and 10, at least 5.91:1 for the focus ring against what surrounds it, does not hold for the green accent on the info, danger and success tints; the component-level choice of R-08 keeps it without changing a token or an approved artifact.
- **F-3 (reading R-06).** COPY_DECK section 12 names some texts inline, where a full stop may end the text or separate it from the next label; this Task reads it as the text's, which gives `ux.state.price_changed.line` a full stop. A change control may settle it.
- **F-4 (for the next P7 change control).** The previews' tones of UXS-14, UXS-15 and UXS-25 differ from DESIGN_SYSTEM sections 3.2 and 9 (R-20); the previews are review material and DESIGN_SYSTEM prevails (its section 1.1), so the implementation follows the document. A change control may align the previews.

## 6. Tests

| Test | Level | What it proves |
| --- | --- | --- |
| `tests/Unit/Design/tokens.test.ts` | L1 | AC-005-01: every block, property and value of `tokens.css` equals the approved `bk-tokens.css`, and the tables of DESIGN_SYSTEM sections 3.1, 3.2, 3.3, 4 and 5 equal the tokens |
| `tests/Unit/Design/assets.test.ts` | L1 | The fonts equal the package's files with the OFL, and load from the origin only; no stylesheet loads from another origin or reads text from an attribute; the keycaps equal the registry; the icons equal the previews'; the registry's keys and accessible names; the catalogue covers UXS-01 to UXS-50 and names every `ux.state` key; the accent allowlist; amount grouping |
| `tests/Unit/Design/layout.test.ts` | L1 | The width classes at 600 and 1024 CSS px in the script, the tokens and the theme |
| `tests/Unit/StaticChecks/eslint.test.ts` | L1 | The extended JSX literal rule: the compliant and the negative samples of R-04 |
| `tests/Unit/Localization/CopyDeckTest.php`, `LocalizationTest.php` | L1 | Every text of `lang/id`, the `ux.state` texts of section 12 included, is COPY_DECK's |
| `tests/Unit/Http/ContentSecurityPolicyTest.php` | L1 | The policy of SEC-HDR-03 with its nonce; no unsafe source; a nonce that could change the policy is refused |
| `tests/Unit/Http/SpecimenTest.php` | L1 | The specimen exists only in `local` and `testing`; it answers with the policy, whose nonce the tags carry |
| `tests/Browser/design-system/accessibility.spec.ts` | L5 | AC-005-04: axe (WCAG 2.0, 2.1 and 2.2 A and AA, best practices) with no violation at every width, the 200 % zoom included, and with the dialog, the menu, the drawer and the error summary open; no sideways scrolling; no text below 12 px; no target below 44 × 44 px; every text at least 4.5:1, or 3:1 when large; no console error, CSP violation or request outside the origin |
| `tests/Browser/design-system/keyboard.spec.ts` | L5 | AC-005-02: the keyboard and focus rules of UXA-08 and UXA-10 and the shortcuts, with real key events — see section 7.2; after review round 1 also Ctrl+Enter from a radio, a stepper field, a command form's field and an amount field, Enter in a one-field command form with its control, the unsaved-changes dialog, and the two live regions together; after review round 2 (D-102) also Ctrl+Enter on a menu button, a menu item and a menu item that opens the review dialog, each doing one thing; which controls prevent the default of Ctrl+Enter before the registry, and the review opening from each; the stepper's field with its default prevented; and Ctrl+Enter in a one-field command form with no confirm binding; after review round 3 (D-103) the review dialog opened from a row's menu item giving the focus back to that menu's button when closed with Esc and with "Kembali ubah" (R-22) |
| `tests/Browser/design-system/components.spec.ts` | L5 | AC-005-02 sizes; AC-005-03 states; AC-005-05 the policy, its fresh nonce and the nonce of the injected styles; AC-005-06 the fonts and the licence; AC-005-04 the accent set with every text and the first 90 focus rings per accent; reduced motion; print; after review round 2 the pending tone of UXS-25, as a notice and as the mark of a figure |

### 6.1 Contrast measurements (AC-005-04, contract section 8)

Measured in Chromium on the specimen, at 1366 × 768, for each accent: every visible text against its real background, composited through its ancestors; the focus ring of 200 Tab presses, which go round all 61 Tab stops of the specimen more than three times (43 distinct control names), against what surrounds it; and the inset ring of a highlighted menu item against the item's background. No text below 4.5:1 (3:1 when large), no ring below 5.91:1 or narrower than 2 px (after review finding R-05 the test asserts 5.91, not 5.9).

| Accent | Texts measured | Lowest text | Tab stops | Lowest ring | Menu item ring |
| --- | --- | --- | --- | --- | --- |
| green | 310 | 4.85 | 61 | 6.26 | 5.99 |
| teal | 310 | 4.85 | 61 | 6.85 | 6.54 |
| blue | 310 | 4.85 | 61 | 7.08 | 6.77 |
| purple | 310 | 4.85 | 61 | 8.28 | 7.92 |
| graphite | 310 | 4.85 | 61 | 11.06 | 10.57 |

The lowest text is the tertiary text on the canvas, 4.85:1 in DESIGN_SYSTEM section 3.5; the lowest ring is the ring on the canvas of section 3.5. At every width of the accessibility test the same measurement of the texts passed; the rings were measured at 1366 × 768 only, because their colours and surroundings do not change with the width (section 7.5).

## 7. Close (EXECUTION_CONTEXT section 15, CE-50)

### 7.1 The command contract, the run of record

On 24 October 2026 from 22:57:45 to 23:13:39, by the implementation executor, after the fixes of review round 3 (section 9.5), on the working tree of the branch at `3dd2b58` with every change of the Task, uncommitted — the code and tests of the tree that the package of review round 4 snapshots; only the evidence and CURRENT_HANDOFF changed during and after the run, and no test reads them — as `.github/workflows/ci.yml` runs it, each command in the containers of the local environment started with `docker compose up -d --build --wait` (`APP_PORT=18080`, section 1): the application container `bantukasir-app:local`, PostgreSQL 18.6 by digest, the Node.js and Playwright containers. Dataset: synthetic only.

| Command | Result |
| --- | --- |
| `composer validate --strict --no-check-all` | PASS: `./composer.json is valid` |
| `composer lint` | PASS: Pint clean, 138 files; translation keys 119 used, all present, no literal user-facing text; translation types current, 197 keys; migration review: 1 migration; secret scan of the tree and of the commits not on a remote: no leaks; `composer audit`: no advisory; the suite Network: 2 tests |
| `composer analyse` | PASS: PHPStan level 9 with Larastan and the project's rules, no error |
| `composer test:unit` | PASS: 499 tests, 382,232 assertions |
| `composer test:postgres` | PASS: 171 tests, 1,259 assertions |
| `composer test:race` | PASS: 8 tests, 60 assertions |
| `npm ci`, then `npm run lint` | PASS: ESLint clean with the extended rule (R-04); npm audit: no high or critical advisory; its network proof: 1 test passed |
| `npm run typecheck` | PASS |
| `npm run test:unit` | PASS: 11 files, 468 tests |
| `npm run build` | PASS |
| `npm run test:e2e` | PASS: 92 tests — the smoke test of TASK-001 and the 91 tests of `tests/Browser/design-system/`, ten of them added after review round 2 and two, of R-22, after review round 3 |

The run of record after review round 2, from 21:16:31 to 21:32:12 on the tree of round 3 (`npm run test:e2e` 90 tests), passed every command; the regression tests of R-22, added after it, and the fixes of round 3 invalidate it (TS-32). The run of record after review round 1, from 20:30:28 to 20:47:20 on the tree of round 2 (`npm run test:e2e` 80 tests), passed every command; the fixes of round 2 invalidate it (TS-32). The run before the review, from 19:41:26 to 19:53:12 on the tree of round 1, passed every command (`npm run test:e2e` 58 tests). An earlier full run, from about 19:00, passed every command except `composer analyse`, which found four type errors in `tests/Unit/Http/SpecimenTest.php`, corrected. During the fixes of round 2, ESLint, the typecheck, the build and `keyboard.spec.ts` with `components.spec.ts` ran first (65 tests passed), and `keyboard.spec.ts` ran once against the logic before the fix, for the proof of section 9.3 (5 tests failed, as expected); partial runs are not of record.

### 7.2 Acceptance criteria

| Criterion | Status | Reason |
| --- | --- | --- |
| AC-005-01 | PASS | `tokens.test.ts`: the snapshot comparison with `bk-tokens.css` and the tables of DESIGN_SYSTEM sections 3 to 5; in the browser the accent's brand slots per `data-accent` (`components.spec.ts`) |
| AC-005-02 | PASS | Sizes of 8.1 to 8.16 in `components.spec.ts`; keyboard and focus in `keyboard.spec.ts`: the review dialog's first focus on "Kembali ubah", Tab kept inside, Esc closing it and the focus returned to its opener; Enter never activating the review's confirm button or the final action, Space and the pointer doing so; Ctrl+Enter opening the review and a second, separate press confirming, a held or repeated press ignored; F2, F8, Esc, Alt+1 and Alt+2 and the arrow keys of the radio group; the menu's arrow, Home, End and Esc; the drawer's inert page and focus return; the chart's keys and announcements; the error summary's focus; Enter in a command form submitting nothing; the disabled reason; the busy button; the till's results; after review round 2, Ctrl+Enter on a menu button, a menu item and a menu item that opens the review doing one thing each (R-15, D-102); after review round 3, the focus returned to a row's menu button when the review opened from its item closes (R-22, D-103) |
| AC-005-03 | PASS | `components.spec.ts`: the figures loading, failed, stale and of unknown cost show words, never Rp0, zero, blank or "Normal"; the unknown cost in a table and a summary; the four marks; the uncertain and held panels pending, never failed, with only their recovery; every notice state with its title; each from its COPY_DECK key (`CopyDeckTest`, `assets.test.ts`) |
| AC-005-04 | PASS | `accessibility.spec.ts` at every width — the specimen, the pre-authentication archetype, and the review and unsaved-changes dialogs, the menu, the drawer and the error summary open; the measurements of section 6.1 |
| AC-005-05 | PASS | `components.spec.ts` and `keyboard.spec.ts`: the policy of SEC-HDR-03 with a fresh nonce per request, the injected styles carrying it, and no console error or CSP violation while every overlay opens and closes, nor at any width |
| AC-005-06 | PASS | `components.spec.ts`: Inter loaded from `/fonts/inter/`, the licence served, no request outside the origin; `assets.test.ts`: the files equal the package's |
| AC-005-07 | PASS | ESLint with the JSX literal rule (R-04, R-05), the translation-key check and the typecheck pass (section 7.1) |
| AC-005-08 | PASS | Section 2: what the reference contributed, through DESIGN_SYSTEM and the previews at `14497d9`; no MULTIPLECORP material |

### 7.3 Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS | AC-005-01 to AC-005-08 pass (section 7.2); REQ-UX-01, REQ-UX-02, REQ-UX-03 and REQ-NFR-02 are contributed to; UX02 is owned here with its harness and cases, and its `FINAL_PASS` is TASK-044's and TASK-046's (TS-09) |
| UI and UX | PASS | The approved artifacts are implemented without change (EXC-50), DESIGN_SYSTEM prevailing where the previews differ from it (R-20); every component meets the sizes, states, keyboard and contrast of DESIGN_SYSTEM and ADAPTIVE_AND_ACCESSIBILITY at every width (EXC-53); no page or control of TRACEABILITY section 9.2 is built here |
| Backend | N/A | No route of ROUTE_CONTRACTS or Action; the testing-only specimen route (R-02) |
| Database | N/A | No migration |
| Security | PASS | THR-05: the components render under SEC-HDR-03 with no violation, and no request leaves the origin; no secret; no other role or account exists yet to refuse; the specimen is absent in staging and production (`SpecimenTest`); the static checks pass |
| Data integrity | N/A | Writes no business fact; amounts stay canonical strings and BigInt in the components (ADR-006) |
| Performance | N/A | No request path |
| Operations | N/A | No backup, runbook or status impact |
| Verification | PASS | The command contract green locally (section 7.1); the self-review (section 7.4); the reviewer's check of class B and its verification (section 9); CI follows the push (section 7.6). After the fix cycle of section 10: every command PASS locally except the browser suite, which failed locally by Playwright's 30 s timeout only, as the code of `fd71860` does on the same machine (section 10.5), so its run of record is CI's on the new head (section 10.9) |

### 7.4 Self-review (EXC-70)

The whole diff from `3dd2b58` was reviewed against the contract's sections 4.1, 6, 7, 8, 11 and 13, DESIGN_SYSTEM sections 3 to 12, ADAPTIVE_AND_ACCESSIBILITY UXA-06 to UXA-20, UI_STATES section 2, COPY_DECK sections 1, 5, 12, 15 and 18, and SEC-HDR-03. Corrected before the run of record:

- keys created but used by no code — `ux.action.back_to`, `load_more`, `stay`, `required_mark`, `ux.list.updated_at`, `count`, `ux.disabled.no_change`, `ux.validation.required` — were either given their component (the page header's back action, `RefreshLine`, `LoadMore`, `UnsavedDialog` for UXS-45, the specimen's field errors) or removed (`required_mark`, as no component marks a required field visibly);
- the error summary moved the focus to itself on every render; it now does so when its messages change;
- the wide table's first column took the header's background in its body cells; the components' sections were region landmarks with duplicate names; the hidden header of a narrow table widened the page at 320 px; a skeleton inside a figure had no width; an empty class name and an unused heading id were left — each corrected and, where visible, covered by the browser tests;
- the specimen's final action had Ctrl+Enter beside "Tinjau", two confirm bindings on one page; it keeps only "no Enter", as a real page has one confirm binding per scope.

Checked and left: the registry's visibility test reads `offsetParent`, which is the fixed container for a control inside the fixed foot of the cart or a dialog, so those bindings act; `Esc` is not taken from a search field, a select or a text area; amounts stay canonical strings, and the chart's vertical positions are computed with BigInt. Every file is LF. No commit exists yet; the commit of the Task follows the review (CE-30).

After review round 2 (D-102) the fixes of section 9.3 were self-reviewed against UXA-09 and UXA-10 and DESIGN_SYSTEM section 11: the exception matches the focused element only, so a menu button or a button inside a command form never takes it; a command form now blocks every Enter in its fields, with or without Control, and Ctrl+Enter still reaches the page's binding from there; Shift+Enter and Alt+Enter in a field also submit nothing; the stepper keeps Ctrl+Enter's default and stays in the exception for a later handler that blocks it; the figure's mark of UXS-25 takes its tone from the catalogue like its notice; the new specimen item, "Tinjau" in each row's menu, adds no tab stop (61, section 6.1) and no copy key. Each regression test of R-15 was run against the logic before the fix and failed there (section 9.3).

After review round 3 (D-103) the fixes of section 9.5 were self-reviewed against UXA-08 to UXA-10: `useOpener` still prefers the remembered element and falls back to the menu's button only when that element is disconnected, so every other opener is unchanged; the button is found by comparing `aria-controls` with the menu's id, with no selector built from the id; the drawer shares the fix; the attribute `data-bk-enter-blocked` replaces the class selector without changing which inputs match — the inputs of a command form and the stepper's field, never a button or a menu item — and the browser tests of R-15 and R-16 still prove each case; a data attribute holds no text, so the JSX literal rule allows it (R-04).

### 7.5 What is not verified, and why

- **A screen reader and the on-screen keyboard**: manual checks of UX02 and DEVICE_VERIFICATION (TS-25); names, roles and descriptions were checked through the accessibility tree and axe.
- **Real devices, printers and paper**: gate DEVICES; the shortcuts and the breakpoints stay provisional (D-075).
- **Browsers other than Chromium**: the project's Playwright runs Chromium (TASK-001); gate DEVICES covers the target browsers.
- **The application-wide policy and the shell**: TASK-007's.
- **Focus rings at other widths**: measured at 1366 × 768 only; the ring's colour, width and surroundings do not depend on the width.
- **The form container of 960 px** is set by the stylesheet and used by `CommandLayout` by default; the browser test measures the data and focused containers.
- **CI**: follows the push (section 7.6).

### 7.6 CI

The CI run on the pull request's head follows its push; after the fix cycle of section 10, the run on its new head (section 10.9). A commit cannot name its own CI run: the next Task records the pull request, its last CI run, the merge commit and the CI run on `develop` in a final section "Merge" of this file (EXECUTION_CONTEXT CE-50).

## 8. The publication guard's preview (CE-31)

`bash scripts/executor/guard.sh --preview`, on 24 October 2026 at about 23:21, on the working tree of the round-4 package (section 9.6), against `origin/develop`: 47 candidates, each judged against D-076 and the baseline of D-097 as D-098 extends it. None is a real finding.

| Candidates | Where | Judgment |
| --- | --- | --- |
| 2 binary files | `public/fonts/inter/inter-latin-opsz-normal.woff2`, `inter-latin-ext-opsz-normal.woff2` | The font files of `@fontsource-variable/inter` 5.3.0 under the SIL OFL 1.1, byte-equal to the package's files (`assets.test.ts`); no personal or client data |
| 44 URLs | `package-lock.json` | The public npm registry's tarball addresses of the added dependencies (section 2); public documentation of the packages, no host of the project |
| 1 URL | `public/fonts/inter/OFL.txt` | The public address of the SIL Open Font License in the licence text itself |

No e-mail address, home-directory path, private host or address is among the candidates. The guard runs again on the commits before the push (CE-31).

## 9. Reviews (CE-40)

### 9.1 Round 1: the reviewer's check of class B

The isolated agent `bk-reviewer-high` (Claude Opus 5.5, effort High, tools Read, Grep and Glob only), on 24 October 2026 from about 19:54 to 20:07, on the package `review/TASK-005/r1` of the executor state: base `3dd2b58`, snapshot tree `38787d89db47921be3e70c72bfb80681643b72b7`, with the working tree. After the review `review-pack.sh --check` printed `UNCHANGED`: working tree, index, HEAD and executor state unchanged. Verdict: FAIL, two MUST-FIX, five SHOULD-FIX, seven NOTE. The approved artifacts were confirmed unchanged.

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | The tones of UXS-14, UXS-15 (pending) and UXS-25 (warning) followed the previews against DESIGN_SYSTEM 3.2 and 9, unrecorded | Fixed: warning for UXS-14 and UXS-15, pending for UXS-25 notice and figure mark, from the catalogue; reading R-20, finding F-4; the browser test asserts the warning tone |
| R-02 | MUST-FIX | Ctrl+Enter did nothing when a control that blocks Enter — a Radix radio, the stepper — had the focus | Fixed: the registry handles Ctrl+Enter even when the event's default was prevented; the stepper no longer takes Ctrl+Enter (R-21); regression tests from a radio, a stepper field, a command form's field and an amount field |
| R-03 | SHOULD-FIX | One timer for both live regions lost an announcement made within 60 ms in the other region | Fixed: one timer per region; a browser test announces in both at once |
| R-04 | SHOULD-FIX | The Enter test of a command form could not fail | Fixed: a one-field command form with `onSubmit` recording an event; Enter records nothing, and `requestSubmit()` as the control records it |
| R-05 | SHOULD-FIX | The ring threshold was 5.9, not 5.91 | Fixed in the test and section 6.1 |
| R-06 | SHOULD-FIX | Overlays checked at two widths only; the unsaved-changes dialog and the archetypes and containers never rendered | Fixed: overlays and the error summary at every width, the unsaved-changes dialog (axe, first focus, Esc, focus return), the pre-authentication archetype at every width (`?tampilan=masuk`), the other archetypes in the specimen with a container test; the rings at one width stated in section 7.5 |
| R-07 | SHOULD-FIX | A lost backslash in CURRENT_HANDOFF | Fixed |
| R-08 | NOTE | The allowlist of the JSX rule matches by name everywhere; data attributes are broad | Left as recorded in R-04, covered by code review |
| R-09 | NOTE | The specimen's chunk ships in every build | Left for TASK-012 (R-02); CI's browser tests need it in the build |
| R-10 | NOTE | CSP handling sound; the dev server's socket would be blocked under `npm run dev` | None: the specimen is tested on the build |
| R-11 | NOTE | The strong-shade ring applied to every notice, neutral too; the menu ring not measured; repeated stops counted | The selector excludes the neutral tone; the menu item's inset ring is measured for each accent; the tab stops are counted (61) |
| R-12 | NOTE | Every added key is COPY_DECK's | None |
| R-13 | NOTE | Two comments overstated `data-fixture` and named a missing test file | Corrected |
| R-14 | NOTE | `enterActivates` defaults to true | A final action never takes Enter (R-21) |

### 9.2 Round 2: the verification of the fixes

The isolated agent `bk-reviewer-high`, on 24 October 2026 from about 20:49 to 20:56, on the package `review/TASK-005/r2`: snapshot tree `02115bfdbafe1214f7e698691cae581e6f9aeaa4`, previous tree `38787d8`, with `fix.patch`. After it `review-pack.sh --check` printed `UNCHANGED`. Verdict: FAIL. Every finding of round 1 is fixed or validly dispositioned, but the fix of R-02 brought a new MUST-FIX:

| ID | Class | Finding |
| --- | --- | --- |
| R-15 | MUST-FIX | Since the registry handles Ctrl+Enter even when the event's default was prevented, a control that acts on Enter and prevents its default — the Radix menu trigger, which opens the menu, and a menu item, which runs itself — acts on Ctrl+Enter and the page's confirm binding acts too: one press does two things, against UXA-09. The exception must be narrowed to controls that only block Enter (radio items, the fields of a command form and the stepper), with browser tests for the menu trigger, a menu item and a menu item that opens a review dialog |
| R-16 | SHOULD-FIX | R-21 and the registry's comment say a command form's fields prevent Ctrl+Enter; they do not (`Field.tsx` excludes `ctrlKey`), so the "product" and "amount" cases are coverage, not proof of the change; only the radio and stepper cases prove it |
| R-17 | NOTE | No test asserts the pending tone of UXS-25 |
| R-18 | NOTE | The `size="final"` guard of Enter is not exercised by the specimen |
| R-19 | NOTE | Green on the neutral sunken surface is 5.99:1, not 6.00:1 (R-08) |
| R-20 | SHOULD-FIX | CURRENT_HANDOFF does not carry F-4 |
| R-21 | NOTE | Ctrl+Enter in a one-field command form with no usable confirm binding is untested |

Under CE-40 a MUST-FIX still open after the verification is a stop, with no further round: the Task stopped here (`STOPPED REVIEW_MUST_FIX`), uncommitted, for the Owner's decision (CE-70, CE-71).

### 9.3 The Owner's decision and the fixes of round 2

On 24 October 2026 the Owner answered the stop with the note of `state.sh resume` (CE-72), recorded as D-102: "Keputusan Owner (24 Oktober 2026): Setuju. Perbaiki R-15 di TASK-005 sesuai usulan: persempit pengecualian Ctrl+Enter hanya untuk radio item, field CommandForm dan stepper; trigger dan item menu tetap mengalah; tambah tes browser untuk trigger menu, item menu, dan item menu yang membuka dialog review; perbaiki juga R-16 dan R-20." The run resumed in the phase `REVIEW` (CE-24).

**How the further verification is authorized.** CE-40 has every fix of a review verified by one pass of the verification agent that CE-11 names, and stops the Task on a MUST-FIX still open after it, with no further round. The Owner's decision answers that stop: it puts the fix of R-15 within this Task and directs it, with R-16 and R-20, as fixes of the phase `REVIEW`, in which the run continues. These fixes therefore take the one verification pass of CE-40, by `bk-reviewer-high` from a new package (round 3, section 9.4), and the same limit holds: a MUST-FIX still open after it, a finding that needs the Owner or a change of scope stops the Task again (D-102).

| ID | Disposition |
| --- | --- |
| R-15 | Fixed as D-102 directs: the exception for Ctrl+Enter is narrowed to the controls that only block Enter — the radio items, the fields of a command form and the stepper's field (`BLOCKS_ENTER_ONLY`, reading R-21) — and a menu button and a menu item keep the key. Regression tests in `keyboard.spec.ts`: Ctrl+Enter on the page menu's button opens the menu and records nothing; on the menu item "Ubah produk" it runs that item only; on the item "Tinjau" of a row's menu, added to the specimen for this, it opens the review dialog only, and a second, separate press confirms. Run against the logic before the fix (the exception for every prevented Ctrl+Enter restored for the run, then the fix put back), the three tests failed; with the fix they pass |
| R-16 | Fixed, by making the statement true and proving it: `CommandForm` now prevents the default of every Enter in its fields, Ctrl+Enter included, so that no implicit submission sends a command; the stepper's field keeps preventing plain Enter only. R-21 and the comments of `shortcuts.ts` and `Field.tsx` say so. A test reads, on the application's root after the components' handlers and before the registry's, whether the default of Ctrl+Enter was prevented: by the radio, the text field and the amount field of a command form yes, and the review opens from each — the proof of the exception for them, which failed for the two fields against the logic before the fix; by the stepper's field no, and a separate test blocks it as a radio does and the review still opens |
| R-17 | Fixed: a test asserts the pending tone of UXS-25, as the notice and as the mark of a figure; the mark now takes its tone from the catalogue, like the notice |
| R-18 | Fixed: the specimen's final action no longer sets `enterActivates={false}`, so the Enter test of the final action proves the guard of `size="final"` |
| R-19 | Fixed: reading R-08 says 5.99:1 for the green ring on the neutral sunken surface, `#116949` on `--color-surface-sunken` `#f2f3ee`, which is above 5.91:1 |
| R-20 | Fixed: CURRENT_HANDOFF carries F-4 with F-1 to F-3 of this Task |
| R-21 | Fixed: a test hides the page's confirm binding and presses Ctrl+Enter in the one field of a command form; nothing is submitted and nothing opens. Chromium submitted nothing there before the fix too; the form's block of every Enter makes it hold whatever the browser does |

The checks after the fixes: section 7.1.

### 9.4 Round 3: the verification of the fixes of round 2

The package `review/TASK-005/r3` of the executor state, written on 24 October 2026 at 21:33:46: base `3dd2b58`, snapshot tree `0ed6ec708ef7d6ae114704ed8948a1b92591db9c`, previous tree `02115bf` (round 2). Its `fix.patch` holds exactly the nine files of the fixes of section 9.3 and this record, so the working tree at the resume was the tree of round 2 (section 1). The isolated agent `bk-reviewer-high` (Claude Opus 5.5, effort High, tools Read, Grep and Glob only) made one pass, from about 21:34 to 21:41; after it `review-pack.sh --check` printed `UNCHANGED`. Verdict: PASS, with R-15 to R-21 each resolved and one test run requested.

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| R-22 | SHOULD-FIX, a MUST-FIX if the run shows the defect | The specimen's new pattern, a menu item that opens the review dialog: `useOpener` of `Dialog.tsx` remembers `document.activeElement` when the dialog opens, which is then the closing menu item or the body, so on close the focus may not return to the row's menu button (UXA-08, AC-005-02); the new tests counted actions only | **Run: the defect holds.** A test added to `keyboard.spec.ts` opens the review from the item "Tinjau" of the row menu "Tindakan lain Pulpen gel hitam 0,5 mm", closes it with Esc and with "Kembali ubah", and asserts the focus on that menu button: both cases FAIL ("Expected: focused, Received: inactive"), on 24 October 2026 at about 21:45. A MUST-FIX after the verification pass — the Task stopped (CE-40). **Fixed after the Owner's decision D-103** (section 9.5) |
| R-23 | SHOULD-FIX | REQUIREMENTS section 22 traces every decision up to D-101; D-102 has no row, and lines 11 and 347 name the range D-001 to D-101 | Fixed (section 9.5) |
| R-24 | NOTE | The comment of `BLOCKS_ENTER_ONLY` says the controls "never act on" Enter; the stepper's field acts on plain Enter, never on Ctrl+Enter | Fixed (section 9.5) |
| R-25 | NOTE | `form.bk-form input` exempts every input of a command form, which is safe only while no such input acts on Ctrl+Enter, and depends on a styling class | Fixed (section 9.5): a dedicated data attribute, and the rule stated in the comments |
| R-26 | NOTE | Section 9.4 did not exist when the package was written, and CURRENT_HANDOFF points to section 9.3 for the extra pass | This section; CURRENT_HANDOFF points to sections 9.3 to 9.6 (section 9.5) |
| R-27 | NOTE | D-102 quotes the note from "Keputusan Owner (24 Oktober 2026):", section 9.3 from "Setuju." | Fixed (section 9.5): both quote the note's exact text, which begins with "Keputusan Owner (24 Oktober 2026):" |
| R-28 | NOTE | The reading of CE-40 and CE-72 for the further pass is faithful and needs no Owner decision, but it sits inside the terms of his decision | Fixed (section 9.5): marked as the executor's reading in D-102, and so in D-103 |

At the stop the tree held the failing regression test of R-22 and no fix of it, so the command contract did not pass on that tree: `npm run test:e2e` failed these two tests, and the run of record of 21:16 to 21:32 (section 7.1) was invalidated (TS-32). Under CE-40 a MUST-FIX still open after the verification is a stop with no further round: the Task stopped here (`STOPPED REVIEW_MUST_FIX`), uncommitted, for the Owner's decision (CE-70, CE-71).

**The cause and a proposed fix, for the Owner's decision.** When a Radix menu item is chosen, the menu closes and its item leaves the page while the dialog opens, so the dialog's opener is the item, already disconnected, or the body; `restore` then finds no connected opener and leaves the focus where Radix puts it. Proposed: `useOpener` remembers, when the focused element is inside a menu (`[role="menu"]`), that menu's button (the element with `aria-controls` equal to the menu's id, or `aria-expanded="true"`), and falls back to it when the remembered element is disconnected; the test above stays as the regression test, and the fix takes one verification pass as D-102 set out.

### 9.5 The Owner's decision and the fixes of round 3

On 24 October 2026 the Owner answered the second stop with the note of `state.sh resume` (CE-72), recorded as D-103: "Keputusan Owner (24 Oktober 2026): Setuju. Perbaiki R-22 di TASK-005: useOpener mengingat tombol menu (aria-controls ke menu) saat fokus di dalam role=menu dan memakainya bila elemen yang diingat terputus; pertahankan tes R-22; bereskan R-23 sampai R-28; satu pass verifikasi lagi oleh bk-reviewer-high." The run resumed in the phase `REVIEW` (section 1); the further pass is the one verification pass of these fixes, as D-102 set out for its own (section 9.6).

| ID | Disposition |
| --- | --- |
| R-22 | Fixed as D-103 directs, in `Dialog.tsx`: when a dialog or drawer opens, `useOpener` remembers the focused element and, when that element is inside a menu (`role="menu"`), the menu's button — the element whose `aria-controls` equals the menu's id, found by comparing the attribute, without a selector built from the id. On close it focuses the remembered element if it is still connected, otherwise the menu's button (reading R-09). The two regression tests of section 9.4 are kept unchanged; they failed before the fix (section 9.4) and pass with it |
| R-23 | Fixed: REQUIREMENTS section 22 has the rows of D-102 and D-103, both governance, the introduction and section 22 name D-001 to D-103, section 23 counts 103 decisions, 47 of them non-product/domain (both recounted), and its status row records the amendment |
| R-24 | Fixed: the comment of `BLOCKS_ENTER_ONLY` names controls that never act on Ctrl+Enter, though they may block it, and says that the stepper's field acts on plain Enter only; reading R-21 says the same |
| R-25 | Fixed with a dedicated attribute: `CommandForm` and `QuantityStepper` carry `data-bk-enter-blocked` (`ENTER_BLOCKED_ATTRIBUTE` of `shortcuts.ts`), which `BLOCKS_ENTER_ONLY` matches instead of the classes `bk-form` and `bk-stepper`; the comments of the registry, `CommandForm` and `QuantityStepper` state that an input inside such a container must never act on Ctrl+Enter |
| R-26 | Fixed: CURRENT_HANDOFF points to sections 9.3 to 9.6 for the passes after D-102 and D-103 |
| R-27 | Fixed: section 9.3 quotes the note of D-102 from "Keputusan Owner (24 Oktober 2026):", as D-102 does; D-103 and this section quote their note the same way |
| R-28 | Fixed: "How CE-40 applies" in D-102 is marked as the executor's reading of CE-40 and CE-72, not the Owner's words; D-103 marks its own the same way |

The checks after the fixes: section 7.1. Before the run of record, the typecheck, ESLint, the build and `keyboard.spec.ts` ran on the fixed tree (36 tests passed, the two of R-22 among them).

### 9.6 Round 4: the verification of the fixes of round 3

The package `review/TASK-005/r4` of the executor state, written on 24 October 2026 at 23:14:18: base `3dd2b58`, snapshot tree `2e129bd1fcce1526b4d5a386ae5acd1872e7f1e7`, previous tree `0ed6ec7` (round 3); its `fix.patch` holds exactly the nine files of section 9.5 — the four components, `keyboard.spec.ts` with the regression tests of R-22, DECISIONS, REQUIREMENTS, CURRENT_HANDOFF and this file. The isolated agent `bk-reviewer-high` (Claude Opus 5.5, effort High, tools Read, Grep and Glob only) made the one pass of D-103, from about 23:15 to 23:18, and asked for no test run; after it `review-pack.sh --check` printed `UNCHANGED`. Verdict: **PASS**. R-22 to R-28 are each resolved: for R-22 it confirmed that the Radix menu item runs `onSelect` synchronously before it closes the menu, so the dialog opens while the item has the focus and the button still carries `aria-controls`, and that the drawer, opened from outside a menu, is unchanged; for R-23 it recounted section 22 of REQUIREMENTS (103 rows, 47 non-product/domain); for R-25 that the attribute matches the same inputs as the classes did, and never a button or a menu item. Two new NOTEs, neither needing the Owner:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| R-29 | NOTE | The comments of `shortcuts.ts` and `QuantityStepper` and reading R-21 say the stepper's field acts on "plain Enter only"; its handler acts on any Enter without Control (Shift+Enter, Alt+Enter too). The claim that matters, that it never acts on Ctrl+Enter, is correct | Left as recorded here, so that the verified tree is the committed one: the wording "plain Enter" stands for "Enter without Control"; a later Task that touches the stepper aligns the comment |
| R-30 | NOTE | The fallback of R-22 reaches the button of the menu that holds the focus; an item of a sub-menu, whose button also leaves the page, would not get the focus back to the root menu's button. `Menu` has no sub-menu, so TASK-005 cannot meet it | For a later Task that adds sub-menus: walk up to the root menu's button. Nothing in TASK-005 |

No MUST-FIX or SHOULD-FIX is open; the review of the Task is complete (CE-40, D-103).

## 10. Fix cycle 1: the Owner's change request (CE-43)

### 10.1 The request

- **The Owner's comment.** Issue comment 6389095208 by the Owner's account `yusufarst` on pull request #6, on 25 October 2026 at 03:22 (Asia/Jakarta), on the head `fd71860dc2bb1b166a9a74b85d3f1823cf6a9de5`: close the finding P2 on `TrendChart` before the merge; when every value of the period is zero, the line must lie on the baseline (`HEIGHT - PAD_BOTTOM`), not at the top; keep the BigInt computation and add a regression test, the case of a single point of zero included; only this defect within TASK-005; the verification of the repository's rules in force; each commit and push at its own prompt; CI green; then the stop at `AWAIT_MERGE`; no TASK-006 and no change of D-104 in this pull request.
- **The Owner's `/stop`.** Issue comment 6389070379 by `yusufarst`, of 03:19: `/stop`. The run that verifies the merge of this pull request stops instead of starting TASK-006 (CE-62); the executor state records it.
- **The run.** `scripts/executor/tick.sh` delegated it on 25 October 2026 at 05:30 in mode `fix` to the agent `bk-executor-high` (Claude Opus 5.5, `claude-opus-5-5`, CE-13; effort High by the table of EXECUTION_CONTEXT section 19), from the phase `CI`, with the lock of that run. The executor state, the branch at `fd71860`, `origin/task/TASK-005-design-system` and the open pull request's head agreed; nothing was staged or modified; untracked only `Claude outputs/` and `plan-p0-p11-review.patch` (CURRENT_HANDOFF section 2).
- **The finding it names** is the review comment 4367727651 of `chatgpt-codex-connector[bot]` on `resources/js/components/ui/TrendChart.tsx` at `fd71860`: data under CE-43, which the Owner's comment made a change request. The account's issue comment 6386607560 and review 5638953084 are its summaries; nothing was done for them.

### 10.2 The defect (F-5)

`TrendChart` starts both ends of its range at zero, so the range always holds zero and is empty only when every value is zero. For that case `yOf` replaced the empty span with 1, and `PAD_TOP + (max - value) × plot / 1` put zero at `PAD_TOP`, 16, the top of the plot: a period of no sales was drawn as a flat line along the top edge, as if it were the highest value, with its area filling the whole plot. Reproduced before the fix by the regression tests of section 10.4.

### 10.3 The fix

`yOf` returns the baseline `HEIGHT - PAD_BOTTOM`, 224, when the range is empty, and otherwise divides by the range as before; the computation stays in whole rupiah with BigInt (ADR-006). `yOf` is exported for its unit test. The area's base, `yOf(0n, …)`, is the same baseline, so a zero series and its area lie on the bottom grid line. Nothing else changes: with a non-empty range every position is as before, and the specimen's data are unchanged. Files: `resources/js/components/ui/TrendChart.tsx` and the new `tests/Unit/Design/trendChart.test.ts`.

### 10.4 Tests

`tests/Unit/Design/trendChart.test.ts` (L1, Vitest, 6 tests): `yOf` with an empty range gives the baseline; with a range, the highest value the top (16), zero the baseline (224) and the middle value 120; exact positions for amounts beyond the safe range of a float; and the component rendered on the server (`renderToStaticMarkup` inside `AnnouncerProvider`): a period of three zero days and **a single point of zero** each draw the line, the points and the area on 224, the y of the bottom grid line; a series 0, 1,052,000 and 2,104,000 still draws 224, 120 and 16.

Run against the logic before the fix — the old body of `yOf` put back for the run at about 05:35, then the fix restored — three tests failed (the empty range "Expected 224, Received 16", the zero period and the single point of zero); with the fix all six pass.

### 10.5 The command contract

On 25 October 2026 from 05:35:58 to 06:03:48, by the implementation executor, on the working tree of the branch at `fd71860` with the fix of section 10.3, uncommitted, as `.github/workflows/ci.yml` runs it, each command in the containers of the local environment started with `docker compose up -d --build --wait` (`APP_PORT=18080`, section 1). Dataset: synthetic only.

| Command | Result |
| --- | --- |
| `composer validate --strict --no-check-all` | PASS |
| `composer lint` | PASS: Pint clean, 138 files; translation keys 119 used, all present, no literal user-facing text; translation types current, 197 keys; migration review: 1 migration; secret scan: no leaks; `composer audit`: no advisory; the suite Network: 2 tests |
| `composer analyse` | PASS: PHPStan level 9, no error |
| `composer test:unit` | PASS: 499 tests, 382,232 assertions |
| `composer test:postgres` | PASS: 171 tests, 1,259 assertions |
| `composer test:race` | PASS: 8 tests, 60 assertions |
| `npm ci`, then `npm run lint` | PASS: ESLint clean; npm audit: no high or critical advisory apart from those accepted with a reason; its network proof: 1 test passed |
| `npm run typecheck` | PASS |
| `npm run test:unit` | PASS: 12 files, 474 tests — the 468 of section 7.1 and the 6 of section 10.4 |
| `npm run build` | PASS |
| `npm run test:e2e` | **FAIL locally by time, not by behaviour**: 91 of 92 passed; one test exceeded Playwright's default limit of 30 s (below) |

**The browser suite on this machine.** In every local run the failures were the 30 s test timeout and nothing else: no assertion, axe violation, console error or CSP violation failed.

| Run | Tree | Result |
| --- | --- | --- |
| 05:56:25 to 06:03:48, the run above, 4 workers | the fix | 91 passed; "the specimen at 1440x900 passes the accessibility checks" timed out inside axe's `analyze()` (30.1 s) |
| 06:04:24 to 06:12:47, the same command again | the fix | the same: 91 passed, the same test timed out (31.3 s) |
| 06:14:32 to 06:20:09, `--workers=2` | the fix | the same: 91 passed, the same test timed out (30.0 s) |
| That test alone, three times | the fix | PASS in 20.5 s, 14.6 s and 13.1 s |
| 06:22:55 to 06:37:14, after `npm run build` | **before the fix**: the old body of `yOf` put back for the comparison, the code of `fd71860`, whose CI run passed | 88 passed, **4 timed out**: the specimen's accessibility checks at 1366x768 and 1024x768, the card and dialog sizes, and Ctrl+Enter from the stepper's field; 14.1 minutes |
| 06:38:49 to 06:48:42, after the fix was restored and built again | the fix | 91 passed; "the specimen at 390x844 passes the accessibility checks" timed out (30.1 s) |

The code that passed CI on `fd71860` fails the same way here, with more tests, so the timeouts come from this machine's speed during the runs — the Docker host shared its 3.4 GiB with other projects' containers, busy during the first runs — not from the fix, which changes no position of the specimen's non-zero data. The suite is not retried until it passes (TS-08): the local result stays FAIL by time, and the run of record of the browser suite for this fix cycle is CI's on the pull request's new head (section 10.9), on GitHub's runner, as `.github/workflows/ci.yml` runs it. Finding F-6 records the margin.

### 10.6 Findings

- **F-5 (fixed, section 10.2).** A period of zero net sales was drawn at the top of the trend chart.
- **F-6 (for the Owner or a later Task).** The specimen's browser tests run under Playwright's default limit of 30 s per test; on a loaded local machine some of them reach it (section 10.5), while alone they take 13 to 21 s. Raising the limit, or splitting the axe check from the measurements, changes the harness and is outside this fix cycle, which the Owner limited to the defect of F-5.

### 10.7 Self-review (EXC-70)

The diff of the fix cycle from `fd71860` was reviewed against the Owner's comment, DESIGN_SYSTEM 8.13, UXA-19 and ADR-006. The empty range occurs only when every value is zero, because both ends of the range start at zero, so the baseline is the right place for every value of that series; a range with negative values, which net sales per day do not have in V1, is unchanged. No float enters the computation; `HEIGHT - PAD_BOTTOM` is a whole number of the drawing. The export of `yOf` adds no user-facing text; ESLint, the JSX literal rule and the typecheck pass. Every file is LF.

There is no review round for this fix cycle: the Owner's comment asks for the verification of the repository's rules in force, and CE-42 gives a targeted verification only to a fix that changes the production behaviour of a class-A Task, while TASK-005 is of class B; the reviews of section 9 stay complete.

### 10.8 The publication guard's preview (CE-31)

`bash scripts/executor/guard.sh --preview`, on 25 October 2026 at about 06:51, on the working tree with this section's records, against `origin/develop`: the same 47 candidates as section 8 — the two font files, the 44 npm registry addresses of `package-lock.json` and the licence address of `OFL.txt` — each judged as there, none a real finding. The fix cycle adds none: its two files hold no address, host, e-mail address or home-directory path. The guard runs again on the commits before the push.

### 10.9 CI

The CI run on the head of this fix cycle follows its push. A commit cannot name its own CI run: the next Task records it with the merge (CE-50).
