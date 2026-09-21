# BantuKasir — canonical design system

**Status: PROPOSED / NOT VISUALLY APPROVED.** This is the single source of truth for visual tokens/components/patterns. [05](docs/05-DESIGN-SYSTEM.md) owns workflow and gates; it must link here rather than duplicate tokens. UI01 refines these candidate values through actual tool-supported design work; UI02–UI04 reuse them. No prototype has started.

## Candidate baseline

| Domain | Proposed baseline |
| --- | --- |
| Font | Self-hosted Source Sans 3, sans-serif fallback; verify font license/assets in UI01 |
| Type | 12/16 caption, 14/20 table/control, 16/24 body, 20/28 section, 28/36 page; weights 400/600; tabular numerals for money |
| Spacing | 4, 8, 12, 16, 24, 32, 48 px; consistent density, no page-specific scale |
| Radius | 4 px compact controls; 8 px panels/dialogs; no pill-shaped default cards |
| Borders/shadows | 1 px #D1D5DB; none for ordinary surfaces; dialog/popover 0 8px 24px rgba(17,24,39,.12) |
| Surfaces/text | Canvas #F8FAFC, surface #FFFFFF, subtle #F1F5F9; primary #111827, secondary #475569 |
| Accent | Candidate #1D4ED8 on white; runtime constrained allowlist with tested foreground/background pairs |
| Semantics | Success #166534, warning #92400E, danger #B91C1C; textual/icon meaning accompanies color |
| Focus | Independent #2563EB visible 2 px outline with offset, never replaced by brand accent |
| Icons | Lucide, consistent 18/20 px, labeled controls; verify license |
| Inputs/buttons | Clear label/help/error; 40 px desktop / 44 px touch height; primary, secondary, danger, quiet variants |
| Tables | Stable headers, aligned numeric columns, pagination/filter, actionable row labels; no needless card-per-cell |
| Dialogs | One task; safe initial focus, trap/restore focus, Escape; destructive confirmation states consequence |
| Badges | Text + semantic color, no color-only status; LOW “Menipis”, OUT “Habis” |
| Motion | 120–180 ms functional transitions, no decorative motion; honor reduced motion |
| Charts | Optional only when answering a question better than a table; same type/colors, labeled axes/units, no fake data |

Proposed colors require measured contrast in actual combinations (normal text >=4.5:1; large text/essential UI >=3:1). Tokens are not evidence of accessibility approval.

## Shared patterns and responsive rules

Desktop persistent sidebar and optional compact mode; phone drawer and task-first navigation. Explicit sidebar button; business logo is identity, not an ambiguous hidden action. Role-visible navigation only. 375/768/1024/1440 widths plus 200% zoom; long tables use accessible containment or purposeful stacked summaries. Touch targets >=44 px. Do not hide critical totals/confirm actions under virtual keyboard.

Shared primitives: Button, Field, SearchPicker, ScanInput, DataTable, Dialog, Badge, PageHeader, EmptyState, ErrorState, PendingResult, Money, PrintDocument. Inventory/POS reuse Product Search. Scan feedback changes draft quantity visibly; no save-success toast per scan. Unknown result freezes command and offers status/retry. Cashier cart keeps totals and Bayar discoverable. UI states: loading, empty, validation, denied, stale/concurrent change, error, unknown result, confirmed success.

One question per dashboard section; LOW/OUT is current stock while sales metrics carry explicit period. Daily reports separate cash/transfer/count/variance. Missing-cost reports say “Data biaya belum lengkap”. Thermal/A4 use high-contrast print styling, no app navigation, complete business identity/number.

## Frozen approval register

| Baseline | Bundles / evidence | Owner approval | State |
| --- | --- | --- | --- |
| BK-DS-1 candidate | UI01–UI04 not created | None | NOT APPROVED |

BK05 records exact prototype/commit revision, screenshots, token/pattern inventory and owner's approval. After Gate A that revision is FROZEN. Production consumes a single token file/component library mapped to this document; no page may invent parallel values. Any visual-system change needs explicit owner approval and updated baseline/evidence before use. Gate B checks drift on integrated screens. Tool references/licenses/Impeccable critique and actions are recorded in prototype review metadata, not invented here.
