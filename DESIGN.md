---
name: BantuKasir UI01
description: Master visual language candidate for a clear, restrained GOODS-only POS.
colors:
  teal: "#0f766e"
  teal-hover: "#0b5d57"
  aqua: "#e7f5f2"
  navy: "#183b4e"
  navy-hover: "#244c60"
  surface-blue: "#edf2f7"
  blue-emphasis: "#dce6ed"
  ink: "#20343e"
  muted: "#586d76"
  canvas: "#f1f3f5"
  chrome: "#f7f8fa"
  surface-warm: "#fffdf7"
  surface-alert: "#fffafb"
  texture: "rgb(24 59 78 / 5%)"
  surface: "#ffffff"
  subtle: "#edf3f3"
  line: "#cbd6dc"
  divider: "#dce4e8"
  control-line: "#788f9a"
  success: "#216a43"
  success-soft: "#eaf5ee"
  warning: "#87541a"
  warning-soft: "#fbf2e2"
  danger: "#ac3540"
  danger-soft: "#fbeef0"
  info: "#245e99"
  info-soft: "#eef4fb"
  focus: "#2563eb"
typography:
  label:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 600
    lineHeight: 1.5
  badge:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    lineHeight: 1.5
  page:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "28px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  section:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "18px"
    fontWeight: 600
    lineHeight: 1.35
  body:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
  control:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 600
    lineHeight: 1.5
  table:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  caption:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.5
rounded:
  badge: "5px"
  control: "8px"
  avatar: "10px"
  panel: "12px"
  dialog: "14px"
  drawer: "16px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  panel: "20px"
  section: "24px"
  page: "32px"
  wide: "40px"
  major: "48px"
components:
  button-primary:
    backgroundColor: "{colors.teal}"
    textColor: "{colors.surface}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "9px 16px"
  button-primary-hover:
    backgroundColor: "{colors.teal-hover}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
  button-quiet:
    backgroundColor: "transparent"
    textColor: "{colors.teal}"
    rounded: "{rounded.control}"
  panel:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.panel}"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "10px 12px"
---

# Design System: BantuKasir

## Overview

**Master visual language: BK-DS-UI01-r6. Owner approved on 25 Sep 2026.** UI01 R6 retains Source Sans 3 based on the owner-provided LATANSA reference, approved by owner. The complete Gate A visual revision is not yet frozen. [05](docs/05-DESIGN-SYSTEM.md) owns review gates; BK05 still requires all UI01–UI04 bundles for complete Gate A.

**Creative North Star: “Kejelasan operasional, tenang dan tertata.”**

BantuKasir uses the supplied LATANSA CP01 files as the visual starting point: sidebar-led enterprise shell, compact topbar, clear hierarchy, activity/attention regions and readable operational tables. The refinement preserves that structure while using BantuKasir identity, proportional spacing and deliberate mobile reflow.

After owner approval of this UI01 revision, Antigravity must reuse this exact family for BK02–BK04 without another exploratory design pass. Reuse values and patterns, adapting only content, role visibility and workflow. A new token or materially different pattern requires an explicit documented exception; approval of UI01 does not approve unseen UI02–UI04 screens.

**Key Characteristics:**

- Restrained navy/teal identity on white and cool neutral surfaces.
- One humanist sans-serif family across all roles.
- Dense readable operational content with generous touch targets.
- Status meaning remains independent of brand color.

The supplied [BantuKasir SVG](assets/brand/bantukasir-logo.svg) is reused unchanged. CSS trims its surrounding blank canvas; the 136px desktop shell wordmark preserves the source proportions. The collapsed rail retains an explicit navigation control. Phones use a 116px wordmark beside the drawer control. UI colors harmonize with it; they are not claimed as exact sampled logo colors. BusinessProfile remains the future runtime identity boundary, with a constrained contrast-tested accent. LATANSA is a reference, not runtime branding.

## Colors

The frontmatter is the normative candidate palette and guides the CSS custom properties and component emphasis colors.

### Primary

Teal identifies primary form/filter actions; aqua supports calendar range selection. Navy identifies selected desktop navigation, the cashier review CTA, headings and numeric emphasis. Blue-neutral surfaces separate identity and financial context from stock warnings. Neither replaces status meaning.

### Neutral

Canvas is the page field; surface is the clean working panel; subtle separates supporting notes. Ink is primary text, muted is secondary text, line separates content, and control-line defines input/button boundaries.

### Named Rules

**The Semantic Independence Rule.** Success uses green, warning amber, danger red and information blue. Pair foreground with its matching soft background, and always include a text label or an explicit icon/shape.

**The Restrained Accent Rule.** Use teal for form/task emphasis and navy for selected desktop navigation; do not turn all metrics, badges or panel headings teal.

[Measured core text pairs](.impeccable/review/r2/contrast-check.json) range from 4.88:1 to 12.95:1. Normal text requires at least 4.5:1, large text and essential nontext controls at least 3:1. This measurement covers listed pairs, not a blanket accessibility certification. Focus uses an independent blue 3px outline with 3px offset.

## Typography

**Display and body font:** Source Sans 3, with system-ui/sans-serif fallback. Local OFL regular and semibold files remove runtime CDN dependency. Weight 600 supplies emphasis; the current font declaration maps 600–700 to the same semibold file, so do not introduce a distinct bold hierarchy.

The hierarchy in frontmatter covers shared use. Sidebar/activity labels use 15px; table headers and badges 12px; secondary labels 13px. Summary numbers use 28px on mobile and 34px on desktop. Currency summaries use 22px on mobile and at 768–1279px, and 26px on wider desktop. Page headings use 25px on phones. Login title uses 32px; the desktop login supporting statement alone uses 42px.

**The Single Family Rule.** No separate display, mono-money or role-specific font. Amounts and quantities use tabular numerals; Rupiah uses Indonesian separators and no unnecessary decimal places. Allow supporting labels to wrap instead of shrinking body text. Future unknown-cost states must say “Data biaya belum lengkap”; Laba Kotor is never Laba Bersih.

## Layout

The desktop sidebar is 248px wide, topbar 64px high and main content capped at 1512px. Main padding is 16px horizontally on phones, 32px from 768px, and 40px from 1440px. The observed shared spacing ladder is recorded above; 20px panel rhythm and 24px section spacing keep operational density consistent.

Below 1024px, the branded top-left navigation trigger opens a native modal drawer, at most 300px wide with at least 48px left for the outside-dismiss area. Clicking the explicit close button inside it or its backdrop closes it. From 1024px, show the persistent 248px sidebar; explicit icon buttons toggle a 72px icon rail. The wordmark acts as identity, not a sidebar toggle. The redundant right-side sidebar control and role label are removed. Desktop outside clicks preserve navigation, as explicitly chosen by the owner. Icon-rail links keep accessible names and tooltips; account identity remains at the bottom. Opening account from the rail preserves the 72px sidebar and page position. Its 272px overlay sits beside the rail, above the account trigger, within the application viewport. Expanded sidebar and mobile drawer menus remain inset 12px within navigation. This explicit rail exception replaces the earlier auto-expansion contract.

Summary blocks are two columns on phones and four from 768px. Attention appears before activity in full-width stacked panels below 768px; they share a two-column layout above. Operational tables become stacked records below 768px, preserving product, time, reference, signed quantity and status. At tablet/desktop, restore normal headers and aligned numeric columns. Avoid shrinking the entire desktop table into a phone.

Below 768px, POS uses separate catalog/cart views with a persistent unit-count/total cart action above the bottom navigation. From 768px catalog and cart sit side by side. Quantity controls support plus/minus, removing at zero, bounded by available stock; review payment remains a synthetic summary only. Session identity remains above both. Verify 375/768/1024/1440px for every later bundle using this same behavior; real device verification is still required at the relevant integration gate.

## Elevation & Depth

Panels use a defined line, an inset white highlight on summary blocks and modest two-layer ambient depth. Chrome and panel headers use a slightly distinct neutral; warning/out-of-stock summary surfaces receive restrained semantic tints. A fine 5px-pitch CSS dot texture uses the texture token on the page canvas, not on work panels. This is the owner's requested richer surface treatment, not a change of visual family. Do not apply modal shadows to ordinary cards.

- Panel: `0 2px 3px rgb(24 59 78 / 2%), 0 5px 14px rgb(24 59 78 / 4%)`.
- Dialog/cart jump: `0 12px 32px rgb(24 59 78 / 16%)`.
- Modal backdrop: `rgb(24 43 53 / 36%)`.

Functional hover uses 180ms with cubic-bezier(.16,1,.3,1). On hover-capable devices metrics lift 2px with the hover shadow (0 6px 18px rgb(24 59 78 / 8%)); action chevrons shift 3px, and primary buttons lift 1px. The mobile drawer enters from 16px left. Reduced-motion disables every transition/animation. No decorative motion, glass or gradients. The owner-requested trend line chart has an operational purpose; it is not a decorative sparkline.

## Shapes

Controls and selected navigation share gently rounded 8px corners; panels use 12px; modal dialogs 14px. Compact status badges use 5px and identity avatars 10px. Ordinary boundaries are 1px. Use the existing roles rather than adding a page-specific radius.

## Components

The copy-ready implementation map is [UI01 pattern handoff](prototypes/ui01/PATTERNS.md). [UI01 review](prototypes/ui01/REVIEW.md) supplies live routes, screenshots, provenance and test limits.

| Pattern | Shared behavior |
| --- | --- |
| Buttons | Primary teal, secondary bordered white, quiet transparent teal. Minimum 44px height. Icon actions 44×44px with accessible names. Disabled states remain identifiable; one dominant task action per region. |
| Inputs/forms | Visible label, 46px minimum field height, white background, control boundary and help text. Errors are textual and associated with affected fields. Use the same focus outline everywhere. |
| Page header | Dashboard heading, supporting line, Hari Ini period trigger and Lihat Laporan action for Operations/Owner. Period choices: Hari Ini, 7 Hari, Bulan Ini (month to date), Bulan Lalu, Custom Range with two compact date fields opening an anchored single-month calendar; explicit Apply, with invalid endpoints prevented. The period filters activities, movements, incoming quantities and owner totals. Current stock stays explicitly labeled as current. |
| KPI/summary | One label/value with meaningful secondary comparison or status. Deltas derive from synthetic period totals versus the immediately preceding equal-duration period; a zero comparison says Belum ada pembanding. Current product/LOW/OUT counts derive from the same ATK sample catalog. No fabricated growth. |
| Panel/card | White container, shared edge/radius/depth, heading/content split; use panels for meaningful regions, not every cell. |
| Operational table | Search and type filter, stable headers, signed right-aligned quantities; mobile record reflow. Empty filtering explains how to recover. |
| Badges/status | Semantic foreground/background plus text; no clickable pill appearance for passive status. |
| Attention rows | Full-width structured rows with a 3px × 28px semantic priority marker, explicit priority label, count in the title, product/context and chevron. This owner-requested LATANSA pattern replaces number-heavy badges; priority remains understandable without color. |
| Empty state | Short title, practical next step and relevant icon; keep the container and task context. Cart and no-results examples are built. |
| Error state | Inline danger-soft message, concrete correction, field association and focus recovery. Login's demo selector can show a failed example. Do not discard draft data. |
| Loading state | Stable form footprint, textual progress, disabled duplicate action and aria-busy/live status. Login demonstrates a short simulated pending state; no backend completion is implied. |
| Desktop navigation | Grouped role-relevant destinations, active navy row, fixed brand/header and account footer. Do not expose owner-only destinations to staff. |
| Mobile drawer | Native dialog handles inert background, focus trapping and Escape. Explicit close control; return focus to trigger. |
| Topbar | Useful search and current-state bell only on the right; active POS hides both desktop and mobile global search in favor of its local product search; no repeated role label or sidebar toggle. Mobile uses a left navigation icon/wordmark and a search icon. Operations/Owner bell shows current LOW/OUT attention first and the latest three stock activity records second in a scrollable feed; Cashier sees own-session identity only. The panel is capped at 360px wide / 420px high (and viewport bounds). No notification service. |
| Account/profile | Compact avatar/name/role footer with chevrons opens a native auto popover 8px above its trigger, inset 12px within the sidebar/drawer. Rail mode stays fixed and opens a viewport-clamped 272px overlay beside the rail; expanded/mobile account stays inside navigation. Height is capped to available space with scrolling. Inside the mobile drawer it remains a DOM descendant. Escape closes the account first, then the drawer. Own display-name editing is a local prototype example. Email/role stay read-only. Account settings explain the authorized reset/access route; only Pemilik sees user/system administration. No theme, push-notification or 2FA preferences. |
| CashSession identity | Visible status, responsible cashier, drawer and opening time; opening cash explicitly accepts Rp0. No-session blocks POS entry. Identity must agree with role and all status indicators. |

Operations remains closest to the reference and sees stock work only. Owner shares the same shell with HPP/Laba Kotor and administration. Cashier shares tokens and components with two task-focused destinations. Pemilik exposes 14 permitted navigation destinations versus Operations' seven and Cashier's two, with Pengguna/Profil Bisnis/Sistem in grouped navigation and Pemilik account administration links. These are existing capabilities and UI01 navigation previews, not new workflow screens. Client-side demo role switching is not backend authorization.

All displayed dates use Indonesian month names, for example 23 September 2026; combined date/time is 23 September 2026, 14:25:30 (Jakarta context). ISO values are internal only. Month summaries may use September 2026. This owner-directed R6 rule supersedes the R3 numeric date rule. ATK examples are Kertas HVS A4, Pulpen Gel, Buku Nota, Map Folder, Spidol Whiteboard, Binder Clip, Tinta Printer and Staples. The sample CSV action exports the selected period only: operational movement fields for Operations, with a separate financial summary for Owner. It demonstrates report/export intent without a full report workflow.

UI01 establishes the entry and primitive patterns only. Product operations, full checkout/receipt workflow and reports remain BK02–BK04. Loading/error examples establish their visual language; unknown-result recovery, concurrency, denied and print states still need their domain-specific content in the later authorized bundle, using these same primitives.

## Do's and Don'ts

### Do:

- **Do** reuse this candidate system and its existing primitives; after owner approval, treat its exact visual revision as the master for BK02–BK04.
- **Do** keep Bahasa Indonesia labels, explicit state/period/context, tabular amounts and 44px touch actions.
- **Do** adapt content and access by role while keeping identical component geometry, typography and color meaning.
- **Do** preserve original logo artwork and use a constrained runtime identity boundary.
- **Do** record the approved UI01 revision and owner evidence before calling this system frozen.

### Don't:

- **Don't** invent another visual language, token scale or page-specific button family in later bundles.
- **Don't** introduce decorative charts, excessive cards/pills, gradients, glass, theme or notification preferences.
- **Don't** expose private cost/profit to staff or imply that this static prototype enforces real RBAC.
- **Don't** treat review evidence as owner approval, or begin another BK task without authorization.

Approval register: **BK-DS-UI01-r6 — UI01 owner-approved on 25 Sep 2026**. BK01 is COMPLETE. BK05 later freezes the complete Gate A inventory. BK02–BK04 are now authorized to use this baseline; production frontend requires complete Gate A (BK05).

## R6 responsive and interaction contract

R6 is the owner-directed continuation, owner-approved on 25 Sep 2026. Preserve the LATANSA-led family; do not copy historical R3/R4 geometry over this baseline.

- Bottom navigation below 768px: Owner Dashboard/Laporan/Menu/Akun; Operations Dashboard/Stok/Menu/Akun; Cashier Kasir/Keranjang/Sesi saya/Akun. These are existing role destinations; full destination screens remain later bundles. Reserve safe-area/content padding. The cashier total action sits above this bar and disappears in cart view.
- Notifications contain only current stock exceptions and three recent operational records, or the cashier's own session. Header/footer remain visible; the middle feed scrolls. This is not an unread-message service or Web Push.
- Period panel is at most 360px wide, viewport minus 24px. Presets show no date fields; Custom Range reveals Dari tanggal and Sampai tanggal. Each opens the same 336px maximum single-month popover, anchored below or above its field and clamped to the viewport. No permanent calendar or blank canvas.
- Owner line chart compares Penjualan (solid navy) and Laba Kotor (dashed teal), with numeric axis, exact selected period and accessible detail list. A one-day selection shows points. Synthetic dates without fixture sales are explicitly zero; long periods group into at most 24 date buckets. This zero rule does not apply to unknown acquisition costs. Known fixture costs are the default. A tested null-cost variant suppresses HPP/profit and says Data biaya belum lengkap; full financial reporting remains UI04.
- Profile editing changes only the in-memory display name; it never grants roles, changes authentication email or changes the responsible actor of an existing CashSession. Account settings explain access/reset scope and link only Owner to existing user administration. Data resets on reload.
- Desktop sidebar resizing does not rerender the working content or reset the draft. Account access never changes sidebar width. Calendar, cart controls, focus and role exclusions are verified in the R6 packet.

Current evidence: [.impeccable/review/r6/REVIEW.md](.impeccable/review/r6/REVIEW.md). Historical screenshot packets are not current visual proof. Actual device/assistive-technology and production integration checks remain later gates.

### R6 precise reuse rules

- Period options are drafts, including presets; only Terapkan changes the active period. Batal/close/Escape/outside dismissal discard the draft. Month select/year input support cross-month/year dates from year 1000 to the synthetic maximum 23 September 2026. End dates before start are disabled; moving start after end moves end to start with explicit feedback. Arrow keys move day focus across month/year boundaries. First Escape dismisses the nested calendar, next Escape dismisses the period panel. Calendar days and primary controls are at least 44px high.
- Owner trend plots daily points through 31 days and groups longer ranges into at most 24 buckets. Hover, click, tap and roving keyboard expose full date/range, Penjualan and Laba Kotor. Unknown cost breaks the profit line and discloses incompleteness in KPI/tooltip/export. Known explicit zero cost is valid. Single-day fixtures remain a daily point with a truthful no-hourly-detail note. Do not fabricate intraday values.
- Account choices: Owner Profil akun / Pengaturan / Keluar; Operations and Cashier Profil akun / Keluar. Editing and account-access help have one home inside profile. First/second click toggles; outside/Escape dismiss. Opening never changes sidebar width. Capture navigation scroll before drawer hides; restore per role and keep selected destination visible.
- Cashier has explicit Scan for USB/Bluetooth HID or manual barcode, not camera. Active POS exposes one local search. Compact 1:1 authored ATK SVG mock thumbnails accompany names/SKU/stock/price and 44px add controls. From 768px, matched-height catalog/cart panels contain independently scrolling products/lines, with total/payment fixed in the cart panel. Short landscape hides redundant headings and keeps compact session identity; full session facts remain on Sesi Kas Saya. Below 768px, catalog/cart swap and the total action stays above the four-item nav. Query/cart/session survive permitted routes; OUT/over-stock cannot add and minus-to-zero removes a line.
- Mobile identity retains responsible cashier, drawer, status and opened date/time. Opening cash remains available on Sesi Kas Saya. A 44px session-link arrow replaces a full extra text row on phones.
- Line #cbd6dc defines panel/chrome boundaries; divider #dce4e8 separates list records. Navy supports emphasis; teal marks task actions, with independent amber/red/green/blue status. Do not add role-specific token families.


## UI02 candidate composition extension — BK02

25 September 2026; **COMPLETE (owner-approved)**, not a replacement of approved BK-DS-UI01-r6. UI01 source/tokens remain unchanged. UI02 loads its stylesheet directly and carries a reference copy of the shell behavior with explicit local/approved-UI01 route mapping.

Reuse the existing panel, field, button, semantic badge, native dialog, sidebar/account and bottom-navigation language for product operations. Catalog uses a 25-record table on desktop and labeled records on phones, with collapsible filters below 768px. Receiving/opening/issue use paired lookup/draft panels on desktop and stacked panels with bounded results and a draft jump above bottom navigation on phones. The jump follows the existing mobile cashier action pattern; it never confirms a movement. Correction review groups current quantity, signed delta and quantity after confirmation, while explicitly retaining the original fact. Hidden controls must also be excluded from required-field validation.

No new token family, brand treatment, product-image capability, import or production component library is introduced. Barcode labels use a local MIT Code128 renderer; physical label size remains provisional. Operations cost entry is write-only in the review/result/history presentation. UI02 remains synthetic and requires backend authorization in later implementation. See [UI02 patterns](prototypes/ui02/PATTERNS.md) and [current review](.impeccable/review/bk02/REVIEW.md). Gate A remains OPEN; BK03 is authorized to start next.
