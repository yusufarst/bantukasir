# Design system

| | |
| --- | --- |
| Status | CURRENT — written in RF-4C under D-086, 17 and 18 October 2026 (Asia/Jakarta) |
| Phase | P7 — visual design: tokens, typography, colour, layout, components and accessibility |
| Gate | P7-G3, visual design (D-025): **artifacts ready for the Owner's review — NOT APPROVED**. Approval is a separate Owner decision that names the checkpoint commit and the hash of each artifact (plan decision P-10) |
| Decisions | D-004, D-018, D-024, D-025, D-036, D-039, D-040, D-050, D-075, D-077, D-085, D-086 |
| Related | [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md), [COPY_DECK](COPY_DECK.md), [GLOSSARY](../02-domain/GLOSSARY.md), [UI_STATES](UI_STATES.md), [KEY_SCREENS](KEY_SCREENS.md), [previews](previews/index.html), [TOOLCHAIN](../00-governance/TOOLCHAIN.md) section 3 |

How BantuKasir V1 looks: its tokens, type, colour, layout and components, and how they meet the rules of ADAPTIVE_AND_ACCESSIBILITY (`UXA-nn`). This document adds no business rule and changes no approved document. Every text it shows is a COPY_DECK key; the few texts RF-4C needed that COPY_DECK does not name are proposals (section 13). [KEY_SCREENS](KEY_SCREENS.md) lists the previews that render it.

## 1. Status, sources and the reference

### 1.1 Sources and precedence

- The six P7-G1 documents (approved, D-082) and GLOSSARY and COPY_DECK (approved at gate P7-G2, D-086) govern. This document applies them; where it seems to differ from them, they prevail.
- The **Golden Frontend Reference v1.0.0** (D-085, TOOLCHAIN section 3) is the visual reference. Only its generic layer was read, at commit `14497d9`, as D-086 records. Its token values are transcribed here; none of its files is copied into this repository.
- [legacy/DESIGN.md](legacy/DESIGN.md), the draft of the former design candidate, was not used: it was made with a design-generation tool, which D-086 excludes. This document supersedes it.
- The rendered form of the tables below is `previews/assets/bk-tokens.css`, `bk-base.css` and `bk-components.css`. Where a stylesheet and this document differ, this document prevails.

### 1.2 What was taken from the reference

| Area | Taken | Not taken |
| --- | --- | --- |
| Tokens | Names, roles and values of the neutrals, text tiers, surfaces, borders, status tones, chart track, elevation, motion durations and curves, layers and radius; the 4 px spacing base | The MULTIPLECORP values of the `--brand-*` slots; the runtime density and text scaling |
| Type | Inter (variable, optical size), the weights, tight tracking on large sizes, tabular figures for amounts | — |
| Components | Anatomy, states, sizes and keyboard behaviour of buttons, fields, segmented controls, chips, status words, notices, cards, key-value rows, metrics, tables that become cards, dialogs, menus, drawer, side navigation, icon rail and tabs; the outline icon anatomy | Toasts, numbered list positions, the search palette, the bottom navigation, breadcrumbs |
| Patterns | Page archetypes, review before a command, disabled with a reason, result anatomy, live regions, focus return, reduced motion, progressive disclosure on the phone | The reference's responsive classes and its component copy |
| Never | The reference pages 01 to 04 and their blocks, fixtures, personas, business flows and copy, the page-skeleton brand, the logo mark and third-party marks (TOOLCHAIN section 3) | |

### 1.3 Reference defaults that BantuKasir changes

Each change is required by a BantuKasir rule; D-086 records them (TOOLCHAIN section 3). In the stylesheets each is marked "BK:".

| # | Reference default | BantuKasir | Rule |
| --- | --- | --- | --- |
| DV-01 | Phone below 768 px, tablet 768 to 1024 px, desktop from 1025 px | Compact below 600 px, medium 600 to 1023 px, expanded from 1024 px | UXA-02 (D-075) |
| DV-02 | Desktop density and text scaling (`--du`, `--tu`), controls of 32 to 36 px in dense places | No runtime scaling; body text at least 14 px and secondary text at least 12 px at every width; every target at least 44 × 44 px | UXA-06, UXA-07, UXA-26 |
| DV-03 | Field border `#e4e4e2` (1.27:1 on white) | Field, choice and switch boundary `#858890` (3.55:1 on white, 3.32:1 on the canvas) | UXA-18 |
| DV-04 | Phone: app bar with the brand and search, bottom navigation, account in the drawer. Tablet: icon rail and drawer | Compact: top bar with the menu button, the page title and the account menu, and the drawer; no bottom navigation. Medium and expanded: side navigation with labels; the icon rail only on the till | UXA-03 |
| DV-05 | No explicit back action on the phone; breadcrumbs on larger screens | The back action of NAVIGATION_CONTRACTS on every page that has one, at every width; no breadcrumbs | NC-01, NC-02 |
| DV-06 | Reduced motion keeps short fades | Transitions and animations are removed | UXA-20 |
| DV-07 | Toasts for events without a field | No toasts: the outcome is shown on the success destination (UXS-04) or in place, and announced in a live region | UI_STATES UXS-04, UXA-13 |
| DV-08 | MULTIPLECORP brand values; a lime call-to-action and signal colour | The BantuKasir accent set in the brand slots (section 3.3); no lime | D-085, SCHEMA `accent_token` |

## 2. Principles

1. **The shop's words, the shop's work.** Every label is a COPY_DECK key; the screen follows the order of the task in USER_TASKS.
2. **Calm surfaces, strong signals.** Neutral surfaces, one accent for the primary action, the current place and the focus; status always as a word with a dot or an icon.
3. **Never ambiguous about money.** Amounts are large, tabular and never cut (UXA-15, UXA-16); an uncertain command is shown as not yet known, never as failed (UXS-14).
4. **Nothing that does nothing.** A control that cannot be used shows why, next to it (UXS-13).
5. **Comfortable by default.** Clear space between sections; density only in wide data tables (UXA-26).

## 3. Colour

### 3.1 Neutrals and text

The reference's fixed values, except the field boundary (DV-03).

| Token | Value | Use |
| --- | --- | --- |
| `--color-canvas` | `#f7f8f3` | Page background |
| `--color-surface` | `#ffffff` | Cards, fields, dialogs, receipt paper |
| `--color-surface-sunken` | `#f2f3ee` | Wells, disabled controls, the paper stage |
| `--color-border-subtle`, `--color-border`, `--color-border-strong` | `#f0f1ef`, `#e4e4e2`, `#d8d8d6` | Card edges and dividers (decorative) |
| `--color-border-input`, `-hover` | `#858890`, `#6b6d73` | Fields, choice controls, switches |
| `--color-text-primary` | `#0f1014` | Titles, values, body |
| `--color-text-strong` | `#3a3b42` | Emphasised secondary text |
| `--color-text-secondary` | `#55565c` | Hints, labels of figures, reasons |
| `--color-text-tertiary` | `#6b6d73` | Meta lines, axis labels |
| `--color-text-placeholder` | `#75767a` | Placeholders, on white fields only |
| `--color-text-disabled` | `#9b9da1` | Disabled controls only, always with a reason (UXS-13) |

### 3.2 Status tones

Background, text and dot per tone, the reference's values: success `#e3f4e9` / `#236b49` / `#319664`; warning `#fff1e2` / `#8a4a0e` / `#d98a3d`; pending `#fef1d8` / `#7a5a22` / `#cb9d4e`; danger `#fde7e8` / `#b4373f` / `#d0454c`; info `#e2ecf4` / `#0f2b4f` / `#5b8db5`; neutral `#f2f3ee` / `#3a3b42` / `#9b9da1`. Solid danger `#b4373f` (hover `#9e2f36`) carries a white label. The tones map to states, never to decoration: Habis and errors take danger, Menipis and "Status belum pasti" take warning, an UNKNOWN cost takes pending, Normal takes neutral.

### 3.3 Accent set

The shop chooses one accent in its BusinessProfile (`accent_token`, SCHEMA). Only the brand slots change; the default and the accent of the pre-authentication pages is `green`. The names are proposals (section 13).

| `accent_token` | Name (proposed) | Primary | Hover | Press | Accent text | Tint 150 |
| --- | --- | --- | --- | --- | --- | --- |
| `green` (default) | Hijau | `#116949` | `#0f5c40` | `#0e533a` | `#0e583d` | `#e6f4ef` |
| `teal` | Toska | `#0f5f6c` | `#0d535e` | `#0c4b55` | `#0c4f5a` | `#e6f2f4` |
| `blue` | Biru | `#215497` | `#1e4d8a` | `#1c4882` | `#1d4b86` | `#e5ecf6` |
| `purple` | Ungu | `#583a88` | `#51367d` | `#4c3276` | `#4f3479` | `#ece7f4` |
| `graphite` | Grafit | `#34383d` | `#2d3034` | `#282b2f` | `#2a2e32` | `#ecedef` |

Each accent also defines tints 50, 100 and 200 for hover and selected backgrounds (`bk-tokens.css`).

### 3.4 Rules

- The accent marks the primary action, the current navigation item, the focus ring, selected choices and the one trend series. It never signals a state.
- No state is carried by colour alone (UXA-18): a status is a word with a dot or icon; a variance has a sign and words (`−Rp6.500 (kurang)`, UXA-16).
- Status dots are decorative next to their word; the word and, for warning and danger, an icon carry the state.
- Receipts print black on white, whatever the accent.

### 3.5 Contrast

WCAG 2.x ratios computed from the token values. The rendered text of every preview was also measured against its real background (KEY_SCREENS section 4).

| Pair | Ratio |
| --- | --- |
| Primary, strong, secondary and tertiary text on white | 19.01, 11.15, 7.31, 5.17 |
| The same on the canvas | 17.81, 10.44, 6.85, 4.85 |
| Tertiary text on the sunken surface | 4.64 |
| Placeholder on a white field | 4.54 |
| Field boundary on white and on the canvas; hover boundary on white | 3.55, 3.32, 5.17 |
| Status text on its own tint: success, warning, pending, danger, info, neutral | 5.62, 6.17, 5.67, 4.99, 11.86, 9.99 |
| White on solid danger, and on its hover | 5.90, 7.21 |

| Accent | White on primary (hover) | Focus ring on canvas / white / tint 150 | Accent text on white / tint 150 |
| --- | --- | --- | --- |
| `green` | 6.69 (8.00) | 6.26 / 6.69 / 5.91 | 8.46 / 7.47 |
| `teal` | 7.31 (8.69) | 6.85 / 7.31 / 6.39 | 9.20 / 8.05 |
| `blue` | 7.55 (8.45) | 7.08 / 7.55 / 6.35 | 8.74 / 7.35 |
| `purple` | 8.84 (9.66) | 8.28 / 8.84 / 7.28 | 10.00 / 8.24 |
| `graphite` | 11.80 (13.26) | 11.06 / 11.80 / 10.08 | 13.68 / 11.68 |

## 4. Typography

Inter, variable, with optical size; fallback a size-adjusted Segoe UI, then the system sans-serif. Amounts, quantities and times use tabular figures. SKUs and barcode tokens use the monospace stack and may break anywhere (UXA-15).

| Role | Size / line (px) | Weight | Compact (below 600 px) | Use |
| --- | --- | --- | --- | --- |
| Display | 40 / 48, tracking −0.02em | 700 | 34 / 40 | Till total, change, payment total |
| Title | 28 / 36, −0.02em | 700 | 24 / 30 | The page's level-1 heading |
| Heading | 20 / 28, −0.01em | 600 | 18 / 24 | Section headings |
| Subheading | 16 / 24 | 600 | — | Card titles, cart lines |
| Figure | 24 / 32, −0.01em | 700 | 17 to 20 / 28 (fits the width) | Report and home figures |
| Body large | 16 / 24 | 400 | — | Introductions, field values |
| Body | 14 / 20 | 400 | 15 / 22 | Body text (minimum 14) |
| Label | 14 / 20 | 500 | — | Field labels |
| Button | 15 / 20; large 16 / 24; final 18 / 24 | 600 | — | Buttons |
| Navigation | 15 / 20 | 500, current 600 | — | Side navigation, drawer, tabs |
| Meta | 12 / 16 | 400 to 500 | — | Secondary text, pills, axis labels (minimum 12) |
| Mono | 13 / 18 | 400 | — | SKU, barcode, references |

Sentence case everywhere, as COPY_DECK writes it. Emphasis is weight, never colour alone. No text is smaller than 12 px, including badges and the receipt preview (UXA-06).

## 5. Spacing, radius, elevation, motion and layers

- **Spacing:** 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 56, 64 px. Gutter 24 px expanded, 20 px medium, 16 px compact; card padding 20 px (16 compact); gap between sections 24 px (20 compact); grid gap 16 px (12 compact).
- **Radius:** 4, 6, 8 (controls), 10 (cards, large buttons), 12 (dialogs, auth card), full (pills, badges).
- **Elevation:** card `0 1px 3px` at 4 % opacity; menu, modal, drawer and the fixed pay bar have their own soft shadows (reference values).
- **Motion:** 80 to 320 ms with the reference's standard, enter and exit curves, for hover and state changes only. Nothing is carried by motion alone; with reduced motion, transitions and animations are removed (DV-06, UXA-20).
- **Layers:** raised 2, bar 30, popover 80, drawer 95, modal 100, live regions 110.

## 6. Icons

Outline icons on a 24-unit grid, stroke 1.75, round caps and joins, drawn for BantuKasir to the reference's anatomy (no reference or third-party mark). Sizes 24, 20 and 16 px. An icon accompanies a word; an icon-only control (the till rail, the menu and close buttons) has an accessible name and, on the rail, a visible tooltip. Set used by the previews: home, till, receipt, drawer, tag, boxes, chart, sliders, menu, close, search, filter, plus, minus, trash, chevron, arrow-left, check, warning, danger, info, clock, refresh, printer, user, logout, scan, image, eye, eye-off, cash, bank, calendar, more. Production may take the equivalent glyphs of the stack's icon set if they keep this anatomy.

## 7. Layout

### 7.1 Width classes

Compact below 600 px, medium 600 to 1023 px, expanded from 1024 px (UXA-02). Containers: data pages up to 1440 px, forms 960 px, focused flows 640 px, sign-in 420 px. Wide data tables may scroll inside their own container, with the first column kept visible; nothing else scrolls sideways, also at 640 px (200 % zoom of 1280 × 800, UXA-12).

### 7.2 Application shell

- **Expanded and medium:** side navigation with labels, 248 px (216 px medium): the shop's initials and name, the role's destinations in the order of INFORMATION_ARCHITECTURE section 4 with the Stok badge, the account menu at the foot. The current item has the accent tint, a 3 px indicator and weight 600.
- **Till (UXP-101 to UXP-105):** the side navigation collapses to a 76 px icon rail with visible tooltips and accessible names (UXA-03).
- **Compact:** a 56 px top bar with the menu button, the page title and the account menu; the drawer opens from the left, makes the page inert and closes with Esc, the close button or the scrim, returning focus to the menu button. No bottom navigation.
- **Sub-navigation:** tabs across the top of the main column at medium and expanded width, wrapping onto another row when they do not fit (UXA-12); at compact width a menu button that shows the current item, under the title in the top bar. Badges as in the navigation.
- **Account menu:** "Akun saya", "Permintaan belum pasti" with its count only while requests are unresolved, "Keluar"; arrow keys move, Esc closes and returns focus.
- **Banners:** the unresolved-requests banner (UXI-029, UXI-030) under the top of the main column, with its action.
- **Landmarks:** banner, navigation and main; one level-1 heading equal to the page label; the document title ":halaman — :toko", and the page label alone before sign-in (UXA-13).

### 7.3 Page archetypes

| Archetype | Layout | Example |
| --- | --- | --- |
| Home | Panels in contract order, each with its as-of line | KS-02 Beranda |
| Till workspace | Search and results beside the cart; the total and "Bayar" fixed at the foot of the cart; one column below 760 px of container width with "Bayar" fixed at the bottom (UXA-25) | KS-03 Kasir |
| Command page with review | Form card; "Tinjau" opens a review dialog whose confirm button is the final action | KS-06, KS-07, KS-09 |
| Result | Success notice naming what was saved, the next action first, then the document | KS-05 Struk |
| List | Search and filters, the refresh line with its time, a table that becomes cards below 640 px of container width, "Muat lebih banyak" | KS-08 Stok |
| Report | Period choice, completeness, figures, chart with a table view | KS-10 Laporan keuangan |
| Pre-authentication | One centred card; no shell, no business data, no codename | KS-01 Masuk |

## 8. Components

Anatomy and states as the reference describes them, with the BantuKasir sizes. Each is built from the stack's own components in RF-5 (section 14).

| # | Component | BantuKasir specification |
| --- | --- | --- |
| 8.1 | Buttons | Primary (accent fill), secondary (white, accent text), quiet (text), danger (solid danger). Heights 44, 48 (large), 56 (extra large); radius 8, 10 from 56. Icon buttons 44 × 44. A keycap shows the shortcut only with a fine pointer that can hover from 600 px; the shortcut is always in the accessible name. While sending: spinner, "Sedang diproses", name updated, shortcut hidden, repeat blocked, the form frozen and the back action closed with its reason (UXS-06, UXI-084) |
| 8.2 | Final payment action | One per payment screen (D-040): full width of the panel, 64 px, 18 px label, at least 24 px from other controls (UXA-07); never the initial focus; Enter does not activate it (UXA-10) |
| 8.3 | Fields | Label above, 44 px control, 3.55:1 boundary, hint below, error tied to the field; optional fields marked "(opsional)"; amount fields group thousands as typed; 16 px input text on touch devices so the page does not zoom |
| 8.4 | Choices | Segmented control for two or three exclusive options (payment method, receipt size, cash register), radio semantics with arrow keys; filter chips as toggle buttons (`aria-pressed`) with a check icon |
| 8.5 | Status | A word with a dot (neutral, success) or an icon (warning, danger); pills of 24 px for counts and marks; never colour alone |
| 8.6 | Notices and recovery panels | Tone, icon, title, body, actions; `role="status"` or `alert` by urgency. The uncertain panel names what must not happen meanwhile and offers "Kirim ulang" and "Cek status" (UXS-14, UXS-15) |
| 8.7 | Disabled with a reason | The control keeps its place, looks disabled (`aria-disabled`), and its reason sits under it and is tied to it (`aria-describedby`); a link to the cause where one exists (UXS-13) |
| 8.8 | Dialogs | Centred, 12 px radius, scrim; only for the uses of UXA-05. Initial focus on the first safe control ("Kembali ubah"), never the final action; focus kept inside; Esc closes; focus returns to the opener |
| 8.9 | Menus and drawer | Menu button with `aria-expanded`; items as `menuitem`, arrow keys, Home and End, Esc. Drawer: modal, inert page, 44 px close button |
| 8.10 | Cards and sections | White surface, subtle edge, 10 px radius, 20 px padding; a section has a heading (visible, or for assistive technology when the page title already names it) |
| 8.11 | Tables and row cards | Header row with scope; numbers right-aligned and never wrapped; below 640 px of container width each row becomes a card with labelled values; product names clamp at two lines with the full name in the link (UXA-15); row actions name their row |
| 8.12 | Figures, key-value lists and rows | Figure: label, value (never wrapped or abbreviated), help line, completeness mark (UXS-24, UXS-25). Key-value lists for summaries; link rows of at least 56 px |
| 8.13 | Trend chart | One series, Penjualan neto per Asia/Jakarta business day of the period, in the accent with a light area; grid lines `#e6e8eb`; reachable with Tab; arrow keys, Home and End move between points; each point shows and announces its date and value; "Tampilkan sebagai tabel" shows the same data (UXA-19) |
| 8.14 | Till workspace | Search field with scan capture and F2, results with stock and price, cart lines with quantity stepper and remove, the total and "Bayar" (F8) fixed; the cart is a labelled region and scrolls inside its area (UXA-15) |
| 8.15 | Steps | Numbered steps of a guided flow (Tutup sesi kas: preview, count), the current step marked by text and weight |
| 8.16 | Receipt paper | Paper at 58 mm, 80 mm and A4, black on white, 12 px minimum; long product names wrap in full on their own line, never cut, with quantity × price and the line total on the next line; SKU in monospace; the copy marker "Salinan" in sentence case, bold, in a box (section 12) |

## 9. States

UI_STATES defines every state and COPY_DECK its words; the design gives each one place:

| States | Look |
| --- | --- |
| UXS-01 Loading | Skeleton blocks in the shape of the content, and the loading text for assistive technology |
| UXS-02, UXS-03 Empty, no results | Centred message in the content area; no results offers "Hapus filter" |
| UXS-04 Committed | Success notice on the destination, naming what was saved |
| UXS-05 Validation | Field error under the field and a summary at the top of the form, which takes focus |
| UXS-06 Submitting | The pressed action shows the spinner and "Sedang diproses"; edits and repeats blocked |
| UXS-13 Disabled with a reason | Section 8.7 |
| UXS-14, UXS-15 Uncertain, held | Warning panel, the request frozen and shown as not yet known, never as failed; the till keeps the request across reloads (UXP-006) |
| UXS-17, UXS-18, UXS-19 Data changed, price changed, recount | Warning notice at the place of the change with its action |
| UXS-22 Failed read | The figure or panel says it could not be loaded, never zero, with "Coba lagi" |
| UXS-24, UXS-25 Unknown and incomplete cost | Pending tone with text; never zero or blank |

## 10. Accessibility

The design meets the targets of ADAPTIVE_AND_ACCESSIBILITY; the previews show it:

- Targets at least 44 × 44 px with 8 px between them (UXA-07); text at least 14 and 12 px (UXA-06).
- Focus ring 2 px in the accent, offset 2 px, at least 5.91:1 against what surrounds it (section 3.5, UXA-08); visible on every control, the chart and the dialog's controls.
- Visual order equals Tab order; one level-1 heading; landmarks; labelled cart; tables with header cells (UXA-13).
- Every accessible name contains its visible label and, where one exists, its shortcut (UXA-09, UXA-13).
- Live regions announce cart changes, unknown codes, loaded data and chart points (polite) and urgent outcomes (assertive).
- Reduced motion removes transitions and animations (UXA-20).
- Reflow without sideways page scrolling down to 320 px of content width and at 640 px (UXA-12).

## 11. Keyboard

The provisional shortcuts of UXA-09 (D-075), as the previews implement and RF-4C verified them in a desktop browser (KEY_SCREENS section 4). Behaviour on the target browsers and devices is confirmed at gate DEVICES.

| Key | Action | Where |
| --- | --- | --- |
| F2 | Focus the search field | Kasir, Struk |
| F8 | "Bayar" | Kasir |
| Alt+1, Alt+2 | "Tunai", "Transfer bank" | Pembayaran |
| Alt+U | "Uang Pas" | Pembayaran, with Tunai |
| Ctrl+Enter | The final action of the payment, "Tinjau" or "Lanjut hitung", and the confirm button of a review dialog; a press that is held or repeats from the step before is ignored | Pembayaran, command pages, review dialogs |
| Esc | Back or cancel: closes a dialog, a menu or the drawer; on Pembayaran returns to the cart | Everywhere |
| Enter | Ends a scan or search and runs the lookup; submits sign-in; never activates a command, a final action or a print button (UXA-10) | Fields |

## 12. Print

Receipts print through the browser from the same snapshot in three templates (UXA-23): thermal 58 mm and 80 mm, and A4 "Nota Penjualan". Print styles show only the paper. The preview draws the paper at its real width in millimetres; the A4 page may scroll inside its stage on a narrow screen, as a long document may (UXA-12). The copy marker "Salinan", which every reprint carries (BR-DOC-04), prints at the top in sentence case, bold, in a 1.5 px box; where it is printed in the final layout is the document contract (RF-5). Real paper widths and printers are confirmed at gate DEVICES.

## 13. Copy proposals

Texts that RF-4C needed and COPY_DECK does not name. They are proposals: each enters COPY_DECK only by change control (COPY_DECK section 1.1) before a Task uses it. The previews keep them in `BK_COPY_PROPOSED`.

| Key | Proposed text | Where |
| --- | --- | --- |
| `config.profile.accent_option.green`, `.teal`, `.blue`, `.purple`, `.graphite` | Hijau, Toska, Biru, Ungu, Grafit | Names of the accent colours (UXI-363) |
| `reporting.finance.table_date` | Tanggal | Date column of the trend table (UXP-502) |
| `reporting.period.label` | Periode | Legend of the period choice (UXP-502) |
| `ux.action.more_actions` | Tindakan lain | Accessible name of an overflow button |

The gross margin is shown with one decimal and a decimal comma, for example `32,0%`; UXA-16 names no percent format, so this format is a proposal too.

## 14. For RF-5

- Tokens become CSS variables in the stack's own stylesheet and the Tailwind theme; the accent set is applied per BusinessProfile with the token names of section 3.3.
- Components are built with shadcn/ui before new primitives (TOOLCHAIN section 1), keeping the sizes and behaviour of section 8.
- Fonts are self-hosted with their licence file (SECURITY_MODEL SEC-HDR-03, TOOLCHAIN section 3); the previews load Inter from a pinned public address for review only.
- Every text comes from the translation keys of COPY_DECK (D-050, ADR-005).

## 15. Left to later steps

| Item | Step |
| --- | --- |
| Approval of this document and of the key screens | Gate P7-G3, the Owner's decision |
| The copy proposals of section 13 | COPY_DECK change control |
| Shortcuts on the target browsers, touch-target adequacy, real paper widths and printers, the tablet's on-screen keyboard with a scanner | Gate DEVICES |
| The document contracts of the receipt and the session report, including where the copy marker prints | RF-5 |
| The stack implementation of tokens and components | RF-5 and the Tasks of RF-7 |
