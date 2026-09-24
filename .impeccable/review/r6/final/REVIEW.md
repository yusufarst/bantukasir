# BK01 UI01 R6 — final continuation review

24 September 2026 · **BK-DS-UI01-r6 / WAITING OWNER REVIEW**. Current visual evidence for the owner findings supplied after the initial R6 review. Prior staged work is preserved. This candidate has no owner visual approval and authorizes no BK02 or production work.

Preview: http://127.0.0.1:4174/prototypes/ui01/index.html?review=r6-final#owner/dashboard

## Findings and remediation

| Area | Finding / severity | Final candidate |
| --- | --- | --- |
| Cashier desktop/tablet/landscape | High: long cart extended the workspace and pushed payment down. | Catalog/cart share bounded height from 768px; products and cart lines scroll internally. Summary stays in-panel. Short landscape compresses redundant headings/session details and summary; a complete item plus 44px quantity controls fits at 844×390. Full session facts remain one route away. |
| Cashier discovery | Medium: text-only discovery lacked quick object recognition; shell search duplicated POS search. | Eight restrained square ATK mock thumbnails with readable adjacent name/SKU/stock/price/add. Active POS omits global desktop/mobile search for both Cashier and Owner; local search is authoritative. |
| Owner/Operations period filter | High: custom mode still enlarged the entire panel, and presets exposed unnecessary endpoints. | Period panel maximum 360px. Presets have no date fields. Custom reveals Dari tanggal / Sampai tanggal; each opens a single anchored calendar capped at 336px. Month/year navigation, cross-year dates, keyboard arrows, nested Escape, Apply and Cancel verified. |
| Dates across roles | High: numeric-only dates contradicted the latest owner rule. | Shared Indonesian long-date formatter, for example 23 September 2026, 14:25:30. ISO remains machine data. Body-text checks found no numeric-only/ISO date displays across 40 screen cases and 23 destinations. Month-only summaries retain month/year. |
| Shared shell/account/chart | Regression risk | Existing family, role-safe navigation/account actions, chart tooltips and missing-cost honesty preserved. Sidebar scroll and rail account access do not shift the shell; mobile drawer and account route work by touch. |

## Verification

- [checks.json](checks.json): 40 screen/viewport cases at **375 / 440 / 768 / 1024 / 1440**, five period/chart/account interaction groups, seven POS viewport cases (including **844×390 and 1180×820**), and 23 role-permitted destinations. Checks include image loading, aligned panel bounds, stable document height after seven cart lines, internal scrolling, visible payment, one product search, session/cart retention and long dates. Zero captured page errors.
- [integrity-checks.json](integrity-checks.json): 23 routes and eight additional integrity groups: long cart/query/session retention; unknown/out barcode; null cost; keyboard cross-year navigation; invalid-range resolution and outside cancellation; desktop navigation scroll/rail stability; Owner POS state retention; reduced motion. Zero captured page errors.
- [touch-landscape-checks.json](touch-landscape-checks.json): three final groups covering 375×640 emulated touch date selection, minus-to-zero/account route, and 844×390 complete cart-row visibility/internal scroll/payment/no document growth. Zero captured page errors.
- `node --check prototypes/ui01/script.js`, `git diff --check`, and staged whitespace review are required closeout checks. No backend/device acceptance is implied.

The first rendered batch exposed excessive short-landscape height; a scoped correction compressed that layout. The final short-landscape test also required a complete cart row to fit, leading to tighter row spacing while retaining 44px quantity controls. Two test selectors were corrected: the outside target used the actual heading rather than a coordinate inside the popover, and account navigation uses a button rather than a link. These were test defects, not product failures. No further visual redesign was undertaken.

## Screenshots

All **24 PNGs** in this directory are local Chromium captures of this final continuation. Earlier R6 captures in the parent folder are historical. Cart captures deliberately scroll lists to demonstrate independent scrolling; a partial preceding row at the boundary is expected. The 844×390 capture was refreshed after its final spacing correction.

| Surface | Desktop / tablet | Mobile |
| --- | --- | --- |
| Cashier | [1440×900](cashier-cart-1440x900.png), [1024×768](cashier-cart-1024x768.png), [844×390 landscape](cashier-cart-844x390.png) | [Discovery](cashier-open-375.png), [cart](cashier-cart-375x800.png), [long cart](cashier-long-cart-375.png) |
| Period | [Presets](period-preset-1440.png), [custom fields](custom-fields-1440.png), [anchored calendar](anchored-calendar-1440.png) | [Presets](period-preset-375.png), [fields](custom-fields-375.png), [calendar](anchored-calendar-375.png) |
| Owner | [Dashboard](owner-dashboard-1440.png), [tooltip](chart-tooltip-1440.png), [incomplete cost](owner-incomplete-cost-1440.png) | [Dashboard](owner-dashboard-375.png), [tooltip](chart-tooltip-375.png) |
| Operations | [Dashboard](ops-dashboard-1440.png) | [Dashboard](ops-dashboard-375.png) |
| Shared overlays | [Account](account-1440.png), [notifications](notifications-1440.png) | [Account](account-375.png), [notifications](notifications-375.png) |

## Assets, scope and remaining owner decisions

The eight `prototypes/ui01/assets/products/atk-001.svg` through `atk-008.svg` files are original authored vector mock illustrations for these synthetic products, not product photography or fetched branded assets. They use the existing palette, square viewboxes and no external dependencies. The original logo and locally hosted OFL font remain unchanged. Tool/catalog provenance is retained in [TOOLS](../TOOLS.md); no additional paid service or copied component was introduced.

Owner should approve the final density, illustration treatment and field-driven calendar interaction. Hari Ini remains one truthful daily chart point: fixtures do not contain hourly transactions. Prototype date selection covers year 1000 through 23 September 2026; it is not production date-policy approval. Cashier payment is a local review summary, not a sale. Staff menus demonstrate presentation scope, not backend RBAC. Real-device, screen-reader, scanner/printer and production integration evidence remains outstanding. No critical blocker was found in the verified prototype paths.

**NEXT: OWNER VISUAL REVIEW.** Complete Gate A, BK02 and production execution remain held. No commit, push, merge or deployment was performed for this closeout.
