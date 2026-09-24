# UI01 — reusable pattern handoff

This implementation map makes **BK-DS-UI01-r6** reusable by Antigravity (owner approved 25 Sep 2026). [DESIGN](../../DESIGN.md) is the sole token/pattern authority. This file maps that authority to existing code; it does not introduce a second token set.

## Reuse procedure

1. Confirm owner approval of the exact UI01 revision in DESIGN and task 11. The current revision UI01 R6 is owner-approved.
2. Use [styles.css](styles.css) as the shared visual source. For the next authorized static bundle, reference it with `../ui01/styles.css`; do not fork its tokens or copy modified variants into each bundle. Keep route-specific content in the bundle's own HTML/JS.
3. Reuse the semantic markup and classes below. [script.js](script.js) is the concrete reference for all listed renderers. Extract repeated renderers into a shared helper only within a separately authorized task; this handoff is not a mandate for a framework migration.
4. Change data, access, and task composition as required by the selected bundle. Keep typography, palette, geometry, states and breakpoint behavior identical across roles.
5. Verify the selected bundle at 375/768/1024/1440px and check keyboard focus/empty/error/loading states. A new primitive requires a recorded exception; it does not require reopening the entire design direction.

The base stylesheet resolves font URLs relative to itself. Logo paths in markup must resolve to the supplied root `assets/brand/bantukasir-logo.svg`; preserve its original artwork and crop wrapper. Do not rename the font family or install a parallel icon/button package.

## Implementation map

| Reusable pattern | Existing selectors / renderer | Contract |
| --- | --- | --- |
| Type, color, spacing, depth | `:root`, base rules | Consume existing variables; exact values in DESIGN. Amounts/quantities use tabular numerals. |
| Responsive shell | `.shell`, `.sidebar`, `.content`; `render()` | 248px persistent sidebar / 72px icon rail from 1024px; main content and topbar share its offset. Desktop explicitly uses icon buttons to toggle; brand logo must not toggle the sidebar. Desktop outside clicks keep it open. |
| Desktop navigation | `.sidebar-brand`, `.navigation`, `.nav-group`, `.nav-item`; `navigation()` | Semantic nav links; `aria-current="page"`; role-visible destinations only. |
| Mobile drawer | `dialog.drawer`, `.mobile-brand`; `toggleSidebar()` | Explicit left icon opens; close button/backdrop dismisses; wordmark remains identity. Native modal keeps focus/inertness; account popover closes on first Escape, drawer on next. |
| Compact topbar | `.topbar`, `.topbar-left`, `.topbar-right`, `.crumb` | Route/business context, product search and stock/own-session bell; no right-side role text or sidebar control. The mobile navigation trigger is at top left. No push service. Date filter and role-scoped Lihat Laporan sit in the page header. |
| Page heading | `.page-heading`, `.heading-actions`; `heading()` | One h1, supporting text and wrap-safe action area. |
| Date/report utilities | showPeriod(), applyPeriod(), showReport(), exportExample(); .period-options, .date-custom, .export-actions | Real local filtering and download of synthetic data; Operations never receives financial export fields. |
| Summary block | `.metrics`, `.metric`, `.metric-title`, `.metric-value`, `.metric-note` | Label/value/context; semantic modifier only for real state. Money adds `.money`. |
| Panel | `.panel`, `.panel-head` | One meaningful region; shared border/radius/shadow. |
| Operational table | `.movement-toolbar`, `.table-tools`, table `data-column`; `movementRows()` | Product/time/ref/type/qty/status columns; intentional mobile record order. |
| Buttons | `.btn`, `.primary`, `.quiet`, `.icon-button` | Primary/secondary/quiet; labeled 44px icon control. Disabled uses native attribute. |
| Fields/forms | `.field`, `.form-stack`, `.search` | Visible labels and helpers; native validation; explicit error association. |
| Badge | `.badge.green`, `.amber`, `.red`, `.blue` | Passive semantic text, not color alone. |
| Attention row | `.attention-item`, `.copy`, `.attention-summary` | One action per full-width row, 3px priority marker, explicit priority label, count in title and relevant ATK context. |
| Empty | `.cart-empty`, `.empty-result` | Explain next action; keep task context and draft data. |
| Error | `.login-error`, `.inline-error`; login failure | Danger message + correction; `aria-invalid`, `aria-describedby`, focus recovery. |
| Loading | login submit | `aria-busy`, visible live status, disabled duplicate submit; preserve form geometry. |
| Account | `.account-dialog`, `.account-summary`, `.menu-action`; `account()` | Native auto popover 8px above footer, inset 12px within sidebar/drawer width. Keep the 72px rail fixed; use a 272px overlay beside it. Expanded/mobile account stays inset in navigation; clamp height with internal scrolling. Append inside modal drawer for keyboard access. Profile, owner-only system link and logout example. |
| Profile | `.profile`, `.profile-summary`, `.profile-fields`, `.profile-line`; `profile()` | Same identity/role as shell; responsive definition list. |
| Session identity | `.session-strip`, `.session-details`; `pos()` | Status + responsible cashier + drawer + opened time; consistent with current role/session. |
| No session | `.session-layout`, `.session-form`, `.session-guide`; `sessionForm()` | POS gated until explicit drawer/opening cash; Rp0 valid. |

## Copy-ready primitives

Use semantic HTML and existing styles; replace the illustrative words with the selected workflow's Bahasa Indonesia content.

```html
<div class="page-heading">
  <div><h1>Judul halaman</h1><p>Konteks pekerjaan yang singkat.</p></div>
  <div class="heading-actions"><button class="btn primary">Tindakan utama</button></div>
</div>

<section class="panel" aria-labelledby="region-title">
  <div class="panel-head"><h2 id="region-title">Judul bagian</h2></div>
  <!-- Use the established table/list/form body that fits this task. -->
</section>

<div class="field">
  <label for="amount">Uang tunai awal (Rp)</label>
  <input id="amount" type="number" min="0" step="1" required aria-describedby="amount-help">
  <small id="amount-help">Masukkan 0 jika laci belum berisi uang.</small>
</div>

<span class="badge green">Sesi aktif</span>
<button class="btn">Kembali</button>
<button class="btn quiet">Lihat riwayat</button>
```

Keep table structure semantic on desktop and retain the data-column names for mobile presentation. Quantity is signed and right-aligned. Add meaningful headings and field IDs; never duplicate IDs while reusing a snippet.

Loading uses the existing control, disables only the pending action and marks its form `aria-busy="true"`; a status region announces progress. Error replaces that progress with a concrete message and restores the action. The login demo exposes both successful and failed results; the pending state is intentionally brief. Later async operations must bind this pattern to real results, not fixed timers.

## Role and scope boundaries

- Admin Operasional keeps the reference's stock dashboard hierarchy and never receives HPP, profit, POS/session or security content.
- Pemilik and Kasir reuse these exact primitives; their access and workflow alter content, not the design system.
- Cashier demo state resets when changing role to avoid showing another person's session as “Sesi Kas Saya”. The UI01 session is only an in-memory example; backend identity, locks and command recovery follow canonical product docs in the later implementation task.
- No full product CRUD, checkout/payment/receipt, report or session-reconciliation screen is delivered here. Those belong to their authorized bundles and inherit this master.

## Approval and remaining decisions

**Candidate direction:** UI01 R3 adopts Source Sans 3 based on the owner-provided LATANSA reference, alongside the original BantuKasir logo, reference-led enterprise structure, restrained navy/teal, independent semantics, mobile-first behavior and exact role boundaries. Final owner visual approval remains pending.

**Remaining visual decision:** Owner explicitly APPROVED UI01 R6 on 25 Sep 2026. There is no separate unresolved font or alternate visual concept. Future workflow-specific screens require their normal owner review, not another foundational design pass.

The owner-approved revision is recorded in DESIGN and 11. Complete Gate A still includes UI02–UI04/BK05. BK02 is now authorized as the next task; production frontend requires complete Gate A (BK05).

R3 surface reuse: retain the established palette, geometry and Source Sans 3. Desktop shell wordmark is 136px wide (116px on phones in R5); motion tokens are 180ms / cubic-bezier(.16,1,.3,1), disabled for reduced motion. Reuse topbar(), toggleSidebar(), positionPopover(), showPeriod(), applyPeriod() and showReport(). Hari Ini uses the fixed 23 September 2026 example day; 7 Hari is inclusive; Bulan Ini starts on the first day of that month; Bulan Lalu selects the previous calendar month. Custom Range now uses the R6 field-driven picker below; start ≤ end and no future sample dates. Display dates use Indonesian month names (23 September 2026), superseding R3 numeric dates. Use formatDate() for all visible dates and hh:mm:ss for detailed times. Period filtering updates activity, movements, incoming quantities and owner totals; current stock is explicitly independent. Demo CSV exports are scoped by role and period, with no backend or full UI04 report implementation.

## R5 foundations (retained except R6 overrides below)

- `bottomNavigation()` / `.bottom-nav`: four labeled role-specific destinations below 768px; same routes as sidebar. Reserve bottom safe area.
- `setCartView()` / `.pos-layout.cart-active`: mobile catalog/cart swap; desktop side-by-side. `renderCart()` renders 44px minus/plus, line totals, remove, stock cap and aggregate count/total. Subtracting the last unit removes the line and focuses the cart heading. UI01 payment action opens a synthetic summary only.
- `ownerChart()` / `.line-chart`: period-aligned solid navy revenue/dashed teal gross-profit lines, axis and accessible `details` values. One day is a point; R6 uses daily points through 31 days and at most 24 buckets for longer periods. Never use missing cost as zero.
- `showAttention()` / `.notification-feed`: current critical stock first, three compact recent records next; cashier own session only; 360px width / 420px height maximum, internal scrolling. No preference or push feature.
- `profile()`, `editProfile()`, `accountSettings()`: profile identity/access, in-memory name edit, read-only email/role, role-safe account guidance and Owner-only administration links. Escape/cancel preserves previous values. No production account writes.
- `positionPopover()`: actual shared width calculation, viewport clamp and reflow after calendar expansion. Rail account does not expand navigation or move content. This supersedes earlier rail-expansion examples.
- Desktop `toggleSidebar()` updates navigation only; retain cashier draft/search. Preserve outside-click desktop state and dismiss mobile drawer outside.

Use the R6 review packet for current evidence. R3/R4 screenshots and text are historical. Owner approved UI01 R6 on 25 Sep 2026.

## R6 current interaction overrides

- `showPeriod`, `updatePeriodDOM`, `showCalendar`, `positionCalendar`, `updateCalendarDOM`, `applyPeriod`: 360px maximum period panel with two-column presets; date fields hidden until Custom Range. A field opens a nested 336px maximum single-month calendar. Month/year and arrow navigation support distant/cross-year selection. End-before-start is disabled; a later start advances end with feedback. Calendar days are 44px high. Apply alone commits; cancel/outside closes without changing period; Escape closes child before parent. Dates always pass through `formatDate` (23 September 2026); times use hh:mm:ss.
- `ownerChart`, `drawChart`, `showChartPoint`: responsive plot with daily points through 31 days; longer periods at most 24 buckets. Tooltip on pointer/click/tap/focus; left/right/Home/End navigation and Escape dismissal. Accessible detail list remains. Null cost suppresses gross value and breaks that line; explicit known zero cost remains valid.
- `account`: three Owner destinations, two staff destinations; no duplicate Edit/Pengaturan akun entries. Profile contains own edit and access help. Native light-dismiss plus trigger toggle preserve sidebar width.
- `navScroll`, `render`, drawer scroll/close handling: remember visible navigation before rebuilding/hiding; preserve role scroll and selected item. Main route may scroll to its heading without resetting navigation.
- `pos`, `productRows`, `renderCart`: authoritative POS-local search (global desktop/mobile search omitted on active POS for Cashier and Owner); eight authored square ATK SVG mock thumbnails, decorative alt text because adjacent names identify products. From 768px matched-height flex panels bound catalog/cart lists; both lists scroll internally and summary remains in-panel. Compact short-landscape composition preserves controls and session route access. Phone catalog/cart swap retains fixed total above bottom navigation. Barcode errors, stock limits, query/cart/session behavior remain unchanged.
- `lowStock`: always `0 < stock <= minimum`; derive attention, counts and product status from the same fixture.
- `.session-link`, `.session-opening-cash`: compact phone identity; full opening cash is on the existing session view. `viewport-fit=cover` enables safe-area spacing.

Current evidence: [R6 final continuation](../../.impeccable/review/r6/final/REVIEW.md). Prototype years are 1000–2026 with maximum date 23 September 2026; internal ISO values never appear as display dates. Production date-policy validation belongs to later implementation. No backend/security or full checkout claim.
