# 05 — Design system and visual approval

This document owns UI language, visual rules, prototype scope and review evidence. Brand refinement date: 20 September 2026. The owner approved architecture decisions D01–D34 and supplied the official colored mark. The pastel direction is an explicit owner requirement. The exact palette below is the resulting design specification; it is not owner approval of an unbuilt visual contract. No prototype exists or has visual approval. [01](01-PRD.md) owns dashboard priorities, [14](14-BULK-IMPORT.md) imports, and [15](15-FINANCE-PROFITABILITY.md) financial meaning.

## Character and navigation

Professional, restrained, precise B2B/B2G operations. Quality comes from typography, spacing, alignment, hierarchy and interaction. The official mark supports a wine-burgundy/dusty-rose family, translated into flat pastel surfaces with dark readable text. Its metallic highlights, bevels and dimensional shading belong to the unchanged logo only: no metallic buttons, gloss, gradients, glass, glow or fake luxury effects. Default internal theme is neutral light; dark mode is not required. Default shadcn/Tailwind styling is not LATANSA's identity.

Show only implemented, authorized modules. Owner navigation: **Dasbor, Produk, Inventaris, Barang Masuk, Barang Keluar, Riwayat Stok, Notifikasi**. **Pengaturan** contains warehouse/location/accounts and system details; audit is owner-only. Transfer belongs within inventory/history rather than a third repeated primary stock shortcut. Inventory staff land on Barang Keluar or their last authorized workspace; product admins on Produk. Serial detail belongs within product/stock detail. Import belongs in Products or opening setup. No filler coming-soon entries.

## Typography and tokens

Use self-hosted Source Sans 3 WOFF2, weights 400/600, font-display swap, system-ui/sans-serif fallback. Use tabular numerals for quantities/money; test 0/O/1/I with demo SKUs. System monospace is limited to codes/serials. Include the license when packaging assets. Sources: [Adobe Source Sans](https://github.com/adobe-fonts/source-sans), [OFL license](https://github.com/adobe-fonts/source-sans/blob/release/LICENSE.md). No fonts/dependencies are installed in this documentation phase.

| Token | Prototype target |
| --- | --- |
| Body/input/table | Body/mobile input 16/24 px; desktop table 14/20; phone list 16/22 |
| Headings | H1 phone 24/32, desktop 28/36; H2 20/28; metrics 24/32; public hero 32/40 phone, at most 48/56 desktop |
| Weight/case | 400 body, 600 emphasis, normal tracking, sentence case; no decorative uppercase eyebrows |
| Colors | Use the exact role-based palette below; earlier provisional colors are superseded |
| Spacing | 4/8/12/16/24/32/48 px |
| Radius/border/shadow | Controls 4 px, dialogs/sheets 8 px, border 1 px; shadow only for useful overlay depth |
| Gutters | Phone 16 px, tablet 24 px, desktop 32 px |
| Content width | Forms max 640 px; reading copy 68 characters; dashboard max 1,440 px content area; product/scan workspace up to 1,680 px |
| Shell | Sidebar 240 px expanded/64 px rail; topbar 56 px |
| Controls/density | Default 44 px; optional pointer-desktop 36 px compact; important touch hit areas remain 44 px |
| Rows | Desktop 44 px standard/36 px pointer compact; phone at least 56 px for two lines |
| Overlays | Confirmation max 480 px; form dialog max 640 px; long phone forms become full-screen |

Validate rendered tokens, contrast and real-logo alignment at CP01. At most two density modes; do not carry compact desktop preference automatically to touch. Whitespace must separate meaningful groups.

## Official-logo-derived pastel palette

The [source manifest](../assets/brand/README.md) identifies the unchanged 6250 × 6250 PNG. Visual inspection shows deep wine shadows, burgundy faces and muted rose highlights on white. A read-only 16-pixel-grid sample, excluding near-neutral background pixels, produced prominent 16-channel-wide bin centers #481828, #581828 and #682838, plus a lighter #A84858 face/highlight family. These are approximate image observations, not official flat brand swatches: lighting and texture vary across the logo.

The selected UI values deliberately soften/desaturate that family. They replace the provisional #74263D/#5E1E31/#F7EDF0 palette, rather than treating those old values as official. Pastel means light surfaces and calm composition, never pale low-contrast text. Do not sample metallic gradients into controls.

| Role / token | Exact value | Use |
| --- | --- | --- |
| App canvas / canvas | #FAF8F9 | Near-white rose-neutral page background |
| Primary surface / surface | #FFFFFF | Tables, fields, dialogs and official-logo placement |
| Secondary surface / subtle | #F5F0F2 | Quiet grouped regions and hover on neutral rows |
| Primary text / text | #30262A | Body, titles, key quantities |
| Secondary text / text-muted | #64565D | Supporting text, timestamps and placeholders that must remain readable |
| Divider / divider | #DFD4D9 | Decorative separators only; not the sole field boundary or state cue |
| Control boundary / border-control | #98848C | Required input boundaries against white/subtle surfaces |
| Primary brand / brand | #8F4059 | Muted wine-rose, small primary actions and labeled brand emphasis |
| Soft brand surface / brand-soft | #F3E3E8 | Dusty-rose supporting surfaces; use dark text |
| Strong brand anchor / brand-strong | #572337 | Sparse section/selected anchors and pressed primary action |
| Primary CTA | #8F4059 background, #FFFFFF text/icon | One primary task; destructive actions use semantic red instead |
| Primary hover / brand-hover | #78334A background, #FFFFFF text/icon | Pointer hover; pressed uses #572337 |
| Secondary CTA | #FFFFFF background, #572337 text, #98848C border | Hover #F5F0F2; pressed #F3E3E8; keep label and boundary |
| Selected / brand-selected | #EDD3DD background, #572337 text/icon/edge | Selected navigation/row; add aria-current/selected and a visible edge/check |
| Focus / focus | #315FA8 | Independent blue focus ring, 2 px with 2 px white separation from filled controls |
| Sidebar | #FFFFFF base; #64565D idle labels; #EDD3DD selected with #572337 label/edge | No full-height dark burgundy panel; logo retains white backing |
| Public navigation | #FFFFFF base, #30262A labels; #78334A hover/active text | Active underline; #8F4059 primary inquiry CTA; logo on white |

Internal composition guideline: approximately **80% neutral /15% soft brand /5% strong accents** by perceived surface area. This is not a quota or a reason to add decorative blocks. Dense warehouse screens may use even less brand color. Public layouts may move toward **70/20/10** through restrained rose sections and typography; navigation and technical tables stay calm. 60/30/10 is optional, not mandatory. Dark text and meaningful semantic statuses are not artificial accent-budget limits.

### Independent semantic colors

| Meaning | Text / icon / strong fill | Soft background | Application |
| --- | --- | --- | --- |
| Success | #236246 | #EDF7F0 | Scanner token accepted, verified success; distinguish draft acceptance from committed posting in copy |
| Warning | #805400 | #FFF5DE | Stok Menipis, caution requiring attention |
| Error / destructive | #AB3039 | #FCEDEF | Stok Habis, scanner rejection, errors and destructive actions |
| Information | #245B83 | #EAF3FA | Neutral process guidance, pending/uncertain explanation; not a success claim |

Semantic intent takes priority over branding. Never make success green into rose or encode low/out stock as brand tints. Labels and Lucide status icons are mandatory where color conveys meaning. In particular, distinguish red destructive actions from wine primary actions through explicit wording, icon and separate placement. Soft status fills are not sufficient boundaries by themselves. Use the strong semantic color for an essential outline/indicator. A filled destructive button uses #AB3039 with white text; hover/pressed uses a distinct outline plus unchanged safe fill until a new color pair is tested.

### Contrast verification and boundaries

Computed from opaque sRGB values using linearized relative luminance and (lighter + 0.05)/(darker + 0.05). Ratios below are rounded for reporting; acceptance uses unrounded values. Normal text requires at least 4.5:1. Meaningful non-text controls, indicators and focus against adjacent light surfaces require at least 3:1. Keep the stricter normal-text target for all operational labels. See [WCAG 2.2 contrast requirements](https://www.w3.org/TR/WCAG22/).

| Foreground / background | Ratio | Purpose |
| --- | ---: | --- |
| #30262A / #FAF8F9 | 13.83:1 | Primary text on canvas |
| #64565D / #F5F0F2 | 6.15:1 | Secondary text on subtle surface |
| #FFFFFF / #8F4059 | 6.90:1 | Primary CTA |
| #FFFFFF / #78334A | 8.86:1 | Primary hover |
| #572337 / #EDD3DD | 8.80:1 | Selected label/indicator |
| #8F4059 / #F3E3E8 | 5.57:1 | Brand emphasis on pastel |
| #98848C / #FFFFFF | 3.49:1 | Required control boundary |
| #98848C / #F5F0F2 | 3.10:1 | Required control boundary on subtle |
| #315FA8 / #EDD3DD | 4.49:1 | Non-text focus on selected background; not approved as small blue text there |
| #315FA8 / #FAF8F9 | 5.96:1 | Focus on canvas |
| #236246 / #EDF7F0 | 6.59:1 | Success text/icon |
| #805400 / #FFF5DE | 6.08:1 | Warning text/icon |
| #AB3039 / #FCEDEF | 5.76:1 | Error text/icon |
| #245B83 / #EAF3FA | 6.45:1 | Information text/icon |

Use input fields on white/subtle, not directly on selected rose backgrounds with the same low-contrast border. Essential borders on deeper tints use #572337 or the relevant verified semantic foreground. Never use divider color for a required input outline, pastel text on pastel, opacity to mute essential text, or color alone for selected/error states. Inline links are underlined. Focus needs white separation around dark filled controls; a blue ring directly touching wine is not an approved pairing. Preserve visible focus in forced-colors mode and check keyboard/zoom/reduced motion at CP01.

**Palette-pair contrast: PASS. Rendered interface accessibility: NOT YET REVIEWED.** No HTML exists, so these calculations are not WCAG conformance, logo-size legibility, browser or owner visual approval. Any new combination, opacity, gradient, state or surface requires another contrast check and review evidence.

## Icons and motion

Use **Lucide only**, outline stroke 1.75–2 selected consistently at CP01. Sizes: 14 px metadata, 16 default, 18 navigation/important controls, 20 only when useful. Icon size is separate from hit area. Import individual icons and preserve the [Lucide license](https://github.com/lucide-icons/lucide/blob/main/LICENSE).

Suggested semantics: Package/products, Warehouse/inventory, ArrowDownToLine/receipt, ArrowUpFromLine/issue, History, Bell, PanelLeftClose/Open. Verify glyphs against the pinned version. Important actions use icon plus Indonesian label; clear icon-only controls require accessible names and tooltips. No emoji, 3D/cartoon icons, mixed families or decorative icon circles. Omit an icon when text is clearer.

| State | Feedback |
| --- | --- |
| Hover/active | Small tint/border change; no layout jump |
| Focus | Visible 2 px ring/offset; never clipped by tables/sheets |
| Selected | Tint/border plus semantic selected state; not color alone |
| Loading | Process text, structured skeleton if needed, aria-busy; no fake numbers or continuous shimmer |
| Accepted scan | Last-row highlight 150–250 ms plus added quantity/unit text; not a saved-transaction toast |
| Rejected scan | Persistent inline error and optional distinct sound; never sound/color alone |
| Posting result | Document number after commit; specific error/recovery action; risky errors do not auto-dismiss |
| Menus/dialogs/drawers | Small fade/translation 150–200 ms, correct focus behavior |
| Sidebar | Width transition 180–220 ms without overlap; toggle retains focus |

Respect prefers-reduced-motion: remove spatial effects/shimmer and preserve immediate state feedback. Input never waits for animation. No bounce, parallax, glow, dramatic zoom, continuous motion, animated gradients or decorative scroll reveal. CSS is sufficient unless a measured need justifies more.

## Shell and brand toggle

**The internal brand area is one navigation-toggle button, not a home link.** The owner has a separate Dasbor navigation item. Users without dashboard permission must not reach it through the logo.

| State | Behavior |
| --- | --- |
| Expanded desktop | Supplied official mark with ordinary LATANSA text and always-visible collapse glyph inside one button; text is an interface label, not an invented wordmark. Label **Ciutkan navigasi**, aria-expanded=true, aria-controls |
| Collapsed rail | Official mark toggles expansion; label/tooltip **Buka navigasi**, aria-expanded=false; no navigation side effect |
| Closed phone/tablet drawer | One menu/brand trigger opens navigation; no narrow icon rail |
| Open drawer | Close button, Escape, outside interaction and destination selection close it; restore focus appropriately |

Enter/Space activate the toggle; no nested home link. Tooltips work on hover and focus, but are not the sole accessible name. Follow [WAI disclosure semantics](https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/). Modal drawers trap focus and make the background inert; desktop navigation does not.

Persistent sidebar at ≥1024 px, expanded by default. Use drawer at 768–1023 px and on phones. Store only nonsensitive expanded/rail preference per browser/user. Logout clears account identity. Resizing into phone mode closes the drawer regardless of desktop preference. Opening navigation preserves filters/drafts and pauses scanning; resume explicitly after focus returns.

Account area appears **once**, at sidebar/drawer bottom. Its popover contains role, account settings and **Keluar**. Rail has a labeled account button. Do not repeat profile, role or logout in header/dashboard. Topbar holds necessary navigation trigger/context; H1 appears once in the page header. Breadcrumbs only for real detail hierarchy. No marketing footer inside the app.

Barang Masuk/Keluar are staff navigation destinations. Session review/save controls perform different work and may appear within those screens. Do not repeat identical navigation shortcuts in header, cards or floating controls. Products toolbar owns one **Tambah Produk** action and a secondary import action.

## Responsive behavior

Design task order at 375 px first; then intentionally adapt at 768, 1024, 1440 and 1920 px. Also test 360 px and 200% zoom. Never hide safety information to meet a breakpoint.

| Pattern | 375 px | 768 px | 1024 px | 1440 px and larger |
| --- | --- | --- | --- | --- |
| Shell | Header/drawer | Drawer, wide work area | Persistent sidebar/rail | Same sidebar; more useful workspace |
| Cockpit | Canonical vertical priority from 01 | Dominant attention list; flat summaries | Wider attention/context | Approximately 2/3 attention and 1/3 context when useful; no card mosaic |
| Products/stock | Dense two-line rows, full-screen detail, filter sheet | Core-column table | Full table, sticky header/search | More useful columns and optional detail panel |
| Import | Status, summary, errors, history; no bulk apply UI | Paged review and confirmation | Table, error filter, summary | Table plus error context; never render 5,000 rows at once |
| Scan | Context, input/last result, lines, one review action | Separate review area if space permits | Keyboard/HID session table | Dominant table plus 320–360 px context summary |
| Form/detail | One column; long forms full-screen | Max 640 px; related field pairs only | Grouped fields and relevant context | Limited reading width |
| Public catalog | One search, filter sheet, compact technical rows/tiles | Responsive filtering/specs | Persistent useful search/filter | Wider specifications/comparison |

Intentional horizontal scrolling is allowed for comparison/import tables with an indicator and stable identity column, never the whole page. Do not turn every row into a giant card. Mobile sticky actions respect safe areas and the virtual keyboard; never cover errors or duplicate final confirmation.

## UI language and formats

All user-visible text MUST be Bahasa Indonesia: authentication, tooltips, pagination, validation, alerts, human audit, charts, labels/reports, page title/metadata, loading/empty/error, offline and 404/500. Map library/Zod messages rather than leaking English defaults. Documentation stays English.

| Internal concept | UI label |
| --- | --- |
| Dashboard / Inventory / Warehouse | Dasbor / Inventaris / Gudang |
| RECEIPT / ISSUE / TRANSFER | Barang Masuk / Barang Keluar / Transfer Stok |
| ADJUSTMENT / REVERSAL / OPENING | Penyesuaian Stok / Pembalikan / Saldo Awal |
| NORMAL / LOW / OUT | Normal / Menipis / Habis |
| Low/out notification | Stok Menipis / Stok Habis |
| REGISTERED / IN_STOCK / ISSUED | Terdaftar / Tersedia di Gudang / Sudah Keluar |
| DRAFT / PUBLISHED / ARCHIVED | Draf / Terbit / Diarsipkan |
| Role labels | Pemilik / Admin Inventaris / Admin Produk dan Penjualan |
| Login / Logout / Save / Retry | Masuk / Keluar / Simpan / Coba Lagi |

Centralize mappings. Preserve brand/model names and technical identifiers such as SKU, RFQ, QC, USB, Bluetooth, Wi-Fi, PLC and barcode. Do not translate scanned identity. Format with id-ID and explicit units/WIB; example **19 Sep 2026, 09.15 WIB**. Quantity **12,500 m** means 12.5 meters when three-place precision is relevant; never ambiguously parse thousands separators.

## Components and states

| Component | Rule |
| --- | --- |
| Buttons | Primary, secondary, text, destructive only; one primary task per view; loading preserves width/action name |
| Fields/selectors | Permanent labels, explicit required/optional state, concise help, inline errors plus summary; server combobox for large references |
| Tables | Clear headers, right-aligned quantities/units, real filters/sorts/pagination, stable action column; no pill around every value |
| Selection | Checkbox for multi-select, radio for exclusive choice; header selects visible page only unless cross-page selection is explicit |
| Tabs | Related subviews of one object/task; no nested tabs for simple state filters |
| Popovers | Secondary actions/compact filters; never hide primary save/review |
| Dialog/sheet | Context decisions vs temporary detail/filter; dangerous confirmation names object and effect |
| Status/badge | Label/icon/color; badge only when lifecycle affects action; no redundant Active badge under an active-only filter |
| Empty/skeleton | Distinguish no records, no filter matches, load failure and zero; one useful action, no large filler illustration |
| Tooltip | Supplemental information; never the sole location of an important denial reason |
| Chart | Must define decision question, data source, scope/period/unit, missing-data behavior and table alternative |

Table defaults: desktop 50 rows, phone 25; options 25/50/100. One search field and pagination control. Search debounce 250 ms, cancel stale requests, reset page on filter change. Server sort whitelist with ID tie-breaker. URL may hold nonsensitive filters; private/serial search must not leak into analytics/referrers/logs. Row name is a clear detail link; avoid conflicting nested row actions.

Product form groups: identity, classification, owner stock policy and optional description. Financial evidence is a separate private flow. Keep destructive actions apart from save. Preserve input on validation failure.

| Condition | Indonesian copy | Recovery |
| --- | --- | --- |
| Unknown barcode | Barcode tidak ditemukan. | Check label/search product; no auto-created stock |
| Duplicate serial | Unit ini sudah ada dalam sesi. | Highlight existing row |
| Insufficient stock | Stok tidak mencukupi. | Show current quantity/location and review |
| Concurrent edit | Data berubah. Tinjau kembali. | Load current version/diff without overwriting draft |
| Unknown save outcome | Hasil penyimpanan belum dapat dipastikan. | Check original status, no new transaction |
| Invalid location/quarantine | Lokasi tidak tersedia. / Barang sedang dikarantina. | Resolve physical eligibility, no bypass |
| Denied action | Anda tidak memiliki akses untuk tindakan ini. | Safe back/close without private fields |
| Import format/duplicate | Format berkas tidak sesuai. / SKU sudah terdaftar. | Row/field/error report under 14 |

Posting confirmation states type, source/destination, line/item count, unique serials and reason/reference. Use **Simpan Barang Keluar**, not generic OK. One final button, disabled honestly while waiting. Distinguish **Ditambahkan ke sesi, belum disimpan** from **Transaksi berhasil disimpan**. Inbox read state is not stock recovery.

Accessibility target: WCAG 2.2 AA-level keyboard/contrast/form behavior, visible focus, semantic labels, concise aria-live feedback, normal text contrast ≥4.5:1, important touch target ≥44 px, usable 200% zoom and reduced motion. These are acceptance targets, not an audit claim. Manage dialog focus/return. Scanner Enter MUST NOT submit the stock form.

## Mandatory anti-AI-slop and redundancy audit

Each CP and significant revision records these checks in REVIEW.md:

1. Inventory each element's unique purpose: comprehension, navigation, state, business information, safety, action or trust. Remove purposeless elements.
2. Inventory actions. Retain one clear control when two do the same thing in one viewport. Any exception needs a concrete workflow/safety reason.
3. Inventory facts. Do not repeat one count as a card, chart and summary. Detail is allowed when it adds identity, cause or action.
4. Reject generic SaaS/default shadcn templates, excessive cards/pills/radius/whitespace, gradients/glass/glow, random shadows, icon circles, blobs, emoji/3D and filler copy.
5. Verify sources and scope for every metric/chart/company claim. No invented customers, testimonials, projects or certification badges.
6. Verify phone priority, productive desktop density, focus/hit areas and motion. Record removed/simplified elements and why remaining elements are necessary.

Normal health and no-attention states need no decorative chart. Account/company information must not repeat across the internal shell. The owner reviews all relevant states, not just an ideal screenshot.

## Official brand and public design

The official colored mark has been received and preserved unchanged at assets/brand/source/latansa-official-colored-mark.png. The [asset manifest](../assets/brand/README.md) records owner provenance, dimensions, byte count and SHA-256. No separate monochrome/wordmark/banner was supplied. No optimized or production derivative exists; assets/brand/optimized/ remains a future location, not a task in this pass.

Never redesign, stretch, recolor or crop logo geometry. Preserve the supplied white background and whitespace; no CSS filters, blend modes, masking or automatic background removal. Use a white logo area so the original integrates cleanly. The supplied asset is a mark, not a wordmark. Check its actual small-size legibility in CP01 expanded/rail layouts; request an official small-use export only if needed. Missing monochrome is not a blocker for a faithful light-surface prototype. Do not synthesize a monochrome variant or assume a dark-surface treatment is approved.

Optional banner photography may be cropped/repositioned/omitted when appropriate, especially on phone; preserve any embedded logo. Do not force the layout around a banner.

Public site: factual identity and supply/manufacturing activity → product search/families → evidenced capabilities → relevant contact. No full-screen empty hero, generic feature-card grid, fake customer logos or exaggerated copy. Public logo links to **Beranda**, distinct from the internal toggle.

Catalog emphasizes search, category/brand/specification filters and comparison of at most three compatible products. No marketplace cart, flash sale or false scarcity. One contextual primary CTA: **Ajukan RFQ** once available, otherwise **Konsultasikan Produk** via configured wa.me. Avoid repeated header/hero/floating copies. Company/product/SEO copy is edited through the structured CMS under 02. Add CMS editing/preview as CP02 variants and public rendering in CP05; it does not justify a sixth prototype family.

## Prototype structure and approval

Planned structure:

```text
prototypes/README.md
prototypes/<pattern-id>/
  index.html
  styles.css
  demo.js       optional deterministic states
  assets/       publishable local assets
  REVIEW.md
```

No production API, DB, cookies, credentials or external analytics. Avoid CDN dependencies. Demo states carry **Data Demo** labels.

Artifact states: PROPOSED, UNDER_REVIEW, APPROVED, SUPERSEDED; distinct from build-task status. REVIEW.md records pattern ID, revision/commit or content hash, asset-manifest version, pages/states, viewports, browser/accessibility results, anti-slop/redundancy audit, date, feedback and exact owner approval reference. Never invent approval. Significant post-approval changes create a new UNDER_REVIEW revision; Git retains history. Registry points to one active revision per CP.

1. Define FE/FS requirements and relevant normal/empty/loading/error/offline/permission/confirmation states.
2. Create standalone HTML/CSS for a new canonical pattern or significant workflow; verify real browser/keyboard/viewports.
3. Present the concrete revision; task remains **[V] WAITING FOR OWNER VISUAL REVIEW**.
4. **Gate A:** owner approves the HTML revision. Record evidence before implementing the pattern.
5. Implement with real data; verify tests/browser and fidelity.
6. **Gate B:** owner reviews the actual implementation/states. FE/FS stays [V] until approval and all technical checks permit [x].

A prototype task may complete at Gate A; that is not application completion. BE can complete after technical verification. Reusing an approved pattern with unchanged structure/behavior/states, or minor typo/spacing fixes, needs no new HTML. Reference its CP ID and still review the actual frontend. Hierarchy, navigation, scan behavior, responsive layout or significant new states require a revised canonical prototype.

## Five canonical bundles

| Order / ID | Planned folder | Scope |
| --- | --- | --- |
| 1 / CP01 | prototypes/cp01-shell-auth/ | Expanded/rail/drawer, brand toggle/account, login/reset/2FA, tokens/focus/feedback |
| 2 / CP02 | prototypes/cp02-products-import/ | Master, search/table/form/detail/labels; product/quantity-opening/serial-opening import; errors/results; owner evidence; later CMS editor/preview variant |
| 3 / CP03 | prototypes/cp03-inventory-scan/ | One session pattern with receipt/issue/transfer variants; stock/serial/history/corrections and network/duplicate states |
| 4 / CP04 | prototypes/cp04-owner-cockpit/ | Attention/inbox/push, operations/system exceptions; demo financial variation, absent from production Core |
| 5 / CP05 | prototypes/cp05-public-catalog/ | Public home/catalog/detail/comparison and later RFQ entry |

CP01–CP04 establish Core before production. CP05 follows later. Advanced workflows revise their existing CP and pass Gate A when significant. Architecture approval is not visual approval of an unbuilt screen. [11](11-BUILD-PLAN.md) alone owns task progress. The owner designated **P00.1** as Gemini Antigravity's next execution task: prepare the CP01 shell/auth HTML visual contract with the official mark and this pastel system, then stop at Gate A. P00.2 waits for that approval. This planning checkpoint contains no HTML; [16](16-EXECUTOR-HANDOFF.md) defines the execution handoff.
