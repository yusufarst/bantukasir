# BantuKasir — canonical design system

**Status: PROPOSED / NOT VISUALLY APPROVED.** This is the single source of truth for visual tokens/components/patterns. [05](docs/05-DESIGN-SYSTEM.md) owns workflow and gates; it must link here rather than duplicate tokens. UI01 refines these candidate values through actual tool-supported design work; UI02–UI04 reuse them. No prototype has started.

## Brand direction and asset status

The owner-selected BantuKasir direction is a clean modern wordmark using dark navy/charcoal-blue with teal/aqua on white or quiet neutral surfaces. It guides UI01 but is not a two-color UI rule. The exact BantuKasir source logo is **not present in this repository** as of Plan 1.1; only the preserved historical LATANSA asset exists. No logo review, import, color sampling or production derivative is claimed. UI01 must use the real owner-supplied source before extracting or approving brand colors.

BusinessProfile remains the runtime identity boundary. A future client can supply its logo, document identity and a constrained accessible accent without changing source code or semantic colors.

## Candidate baseline

| Domain | Proposed baseline |
| --- | --- |
| Font | Self-hosted **Inter** with system sans-serif fallback; one family everywhere, weights 400/500/600; verify license/files and dense-table/POS rendering in UI01 |
| Type | 12/16 caption, 14/20 table/control, 16/24 body, 20/28 section, 28/36 page; weights 400/500/600; tabular numerals for money |
| Spacing | 4, 8, 12, 16, 24, 32, 48 px; consistent density, no page-specific scale |
| Radius | 4 px compact controls; 8 px panels/dialogs; no pill-shaped default cards |
| Borders/shadows | 1 px #D1D5DB; none for ordinary surfaces; dialog/popover 0 8px 24px rgba(17,24,39,.12) |
| Neutral surfaces/text | Canvas #F7F9FA, surface #FFFFFF, subtle #EDF2F4; primary #17232B, secondary #52616B |
| Brand tokens | Provisional only: navy #183B4E, teal #0F766E, soft aqua #CCFBF1, tint #ECFEFF; chosen as direction, **not sampled from the absent logo** |
| Brand use | Primary CTA, selected navigation, restrained highlights and identity surfaces; runtime accent uses a contrast-tested allowlist/pair, never raw arbitrary CSS |
| Semantic tokens | Success #15803D / #DCFCE7; warning/LOW #A16207 / #FEF3C7; danger/OUT/delete #B91C1C / #FEE2E2; info #1D4ED8 / #DBEAFE; text/icon meaning accompanies color |
| Focus | Independent #2563EB visible 2 px outline with offset, never derived from brand accent |
| Icons | Lucide, consistent 18/20 px, labeled controls; verify license |
| Inputs/buttons | Clear label/help/error; 40 px desktop / 44 px touch height; one primary, secondary, danger and quiet language; no page-specific button styling |
| Tables | Stable headers, aligned numeric columns, pagination/filter, actionable row labels; no needless card-per-cell |
| Dialogs | One task; safe initial focus, trap/restore focus, Escape; destructive confirmation states consequence |
| Badges | Text + semantic color, no color-only status; LOW “Menipis”, OUT “Habis” |
| Motion | 120–180 ms functional transitions, no decorative motion; honor reduced motion |
| Charts | Optional only when answering a question better than a table; same type/colors, labeled axes/units, no fake data |

All hex values and Inter remain proposed until UI01 renders them with the real logo and Gate A approves the exact revision. Measure contrast in actual combinations (normal text >=4.5:1; large text/essential UI >=3:1). Brand colors never replace semantic danger/success/warning/info/focus. Tokens are not evidence of accessibility approval.

## Shared patterns and responsive rules

Desktop persistent sidebar and optional compact mode; phone drawer and task-first navigation. Explicit sidebar button; business logo is identity, not an ambiguous hidden action. Selected navigation uses the canonical brand tint/accent pair, while errors and stock state keep semantic colors. Role-visible navigation only. 375/768/1024/1440 widths plus 200% zoom; long tables use accessible containment or purposeful stacked summaries. Touch targets >=44 px. Do not hide critical totals/confirm actions under virtual keyboard.

Shared primitives: Button, Field, SearchPicker, ScanInput, DataTable, Dialog, Badge, PageHeader, EmptyState, ErrorState, PendingResult, Money, SessionIdentity, CashCount, PrintDocument. Inventory/POS reuse Product Search. Scan feedback changes draft quantity visibly; no save-success toast per scan. Unknown result freezes command and offers status/retry. Cashier cart keeps totals, responsible session and Bayar discoverable. UI states: loading, empty, validation, denied, stale/concurrent change, error, unknown result, confirmed success.

One question per dashboard section; LOW/OUT is current stock while sales metrics carry explicit period. Session reports separate opening cash, cash/transfer, expected/physical/variance; owner daily recap aggregates immutable sessions without double counting. Missing-cost reports say “Data biaya belum lengkap”. Thermal/A4/session print documents use high-contrast neutral styling, no app navigation, complete business identity/number and semantic text labels.

No screen may introduce a new font, radius scale, color, shadow or button language outside this file. Additions require a recorded design-system decision and, after Gate A, explicit owner approval before use.

## Frozen approval register

| Baseline | Bundles / evidence | Owner approval | State |
| --- | --- | --- | --- |
| BK-DS-1 candidate | UI01–UI04 not created | None | NOT APPROVED |

BK05 records exact prototype/commit revision, screenshots, token/pattern inventory and owner's approval. After Gate A that revision is FROZEN. Production consumes a single token file/component library mapped to this document; no page may invent parallel values. Any visual-system change needs explicit owner approval and updated baseline/evidence before use. Gate B checks drift on integrated screens. Tool references/licenses/Impeccable critique and actions are recorded in prototype review metadata, not invented here.
