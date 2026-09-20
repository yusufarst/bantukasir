# 05 — Brand-neutral design system and visual approval

Owns UI language, tokens, navigation, prototypes and review. Product identity is configurable; LATANSA is an optional business configuration. Historical CP01 is preserved, unapproved and superseded as a direction. No replacement prototype is built in this planning session.

## Character and configurable identity

Restrained, professional retail workspaces for long cashier/operations sessions. Speed, legible quantities/prices and recovery clarity come first. Neutral light surfaces, deliberate spacing, flat controls and useful table density; no gradients/glass/glow, oversized KPI cards or generic template decoration.

BusinessProfile supplies business name/logo/primary/accent/contact and receipt header/footer. Core component semantics never depend on a specific client's colors. Logos preserve proportions and source; no generation/redesign/recoloring. If missing/unreadable at rail size, use a plain configured-name text treatment and menu icon, not an invented mark. Preserve LATANSA source and provenance under assets/brand; its use is optional, its burgundy palette is not universal.

Owner setting preview shows sample shell and receipt. Validate hex colors, derive only tested foreground/background pairs, reject inaccessible combinations before activation. Do not accept raw CSS or arbitrary font URLs. All profile changes affect new documents only; old receipts use snapshots.

## Tokens and interaction

| Role | Initial neutral target |
| --- | --- |
| Canvas / surface / subtle | #F6F7F9 / #FFFFFF / #EEF1F4 |
| Text / secondary | #18212F / #475569 |
| Essential control border / decorative divider | #64748B / #D8DEE7 |
| Default primary / hover / on-primary | #334155 / #1E293B / #FFFFFF |
| Selected background / text | #E2E8F0 / #18212F; visible edge and aria-current |
| Focus | #245B9E, 2 px ring with 2 px white separation |
| Success | #236246 on #EDF7F0 |
| Warning | #805400 on #FFF5DE |
| Error/destructive | #AB3039 on #FCEDEF |
| Information | #245B83 on #EAF3FA |

Primary/accent are configurable brand roles. Semantic colors/focus remain independent, with text/icons as well as color. These are design targets, not a rendered accessibility claim; each actual pairing/state is tested in RV01. Normal text ≥4.5:1, essential boundary/focus ≥3:1. No opacity-muting essential text.

Self-host Source Sans 3 400/600 with packaged license; system-ui fallback. Body/mobile input 16/24, desktop table 14/20, headings 24/32 phone and 28/36 desktop; tabular money/quantity, monospace only identifiers. Spacing 4/8/12/16/24/32/48; controls 44 px, pointer compact 36 px without reducing touch targets. Radius 4 px controls/8 px overlays, 1 px borders, shadows only overlays. Forms max 640 px, dashboard max 1440, cashier/table workspace up to 1680.

Lucide only, consistent 1.75–2 stroke; purposeful icons with Indonesian names, no decorative icon circles. Motion 150–200 ms subtle tint/fade, no input waiting; reduced motion removes spatial animation. No mandatory Apple-style spring library. Scanner feedback highlights row and text, never implies payment/stock saved.

## Role navigation and layouts

| Role | Landing and authorized destinations |
| --- | --- |
| Pemilik | Dasbor; Kasir; Penjualan; Produk & Jasa; Stok & Restok; Barang Masuk; Barang Keluar; Riwayat Stok; Laporan; Notifikasi; Pengaturan/Audit |
| Admin Operasional | Stok & Restok; Produk & Jasa; Barang Masuk; Barang Keluar; Riwayat Stok; Penjualan (safe history); Notifikasi |
| Kasir | Kasir; Shift Saya; Penjualan Saya |

Owner may separately assign CASHIER to operations when checkout is part of the job. Visible navigation is not authorization. Goods transfer lives within stock; cost evidence is a separate private owner flow, never hidden fields in an operations form.

Desktop ≥1024: 240 px sidebar/64 px rail. Phone/tablet: drawer; one internal brand/menu toggle, no accidental home navigation. Account/role/logout appears once at sidebar/drawer bottom. Menu preserves drafts and pauses scanner capture. Modal drawer traps focus and restores it; desktop navigation does not. H1 appears once; no marketing footer.

POS desktop: dominant basket with dedicated scan/search, quantity/price/subtotal columns and persistent payment summary. Payment has one explicit final action; scanner Enter never submits it. Service search is visible without simulating barcode stock. Keyboard shortcuts have visible equivalents and are tested for browser conflicts. Quantity edits retain focus; scanner resumes explicitly after modal/search/payment. Phone: scan/search → compact basket → sticky review/total → full-screen payment review; keyboard cannot cover errors/actions.

Shift view: open float, current sales/payment totals, owner cash events; blind cash count hides expected value until submitted; subsequent variance/review/handover is distinct. Emergency close cannot look balanced. Receipt preview supports thermal/A4, paper width/page breaks, original/copy/refund annotation.

Catalog uses server-paged dense rows, type filter Barang/Jasa and type-specific fields. Goods locations/serial/minimum disappear structurally for services; cost stays private. Mobile receiving retains context/last scan/undo. Import phone supports status/errors, tablet/desktop full review/apply. Owner dashboard hierarchy follows 01; no repeated fact in chart/card/list.

## Language and states

All UI/errors/metadata/print/export/human audit use Bahasa Indonesia. Technical identifiers and actual product names stay intact.

| Concept | Label |
| --- | --- |
| GOODS / SERVICE | Barang / Jasa |
| SUPER_ADMIN / OPERATIONS_ADMIN / CASHIER | Pemilik / Admin Operasional / Kasir |
| POS / receipt / A4 copy | Kasir / Struk Penjualan / Bukti Penjualan Lunas |
| OPENING / RECEIPT / ISSUE / TRANSFER | Saldo Awal / Barang Masuk / Barang Keluar / Transfer Stok |
| NORMAL / LOW / OUT | Normal / Menipis / Habis |
| Draft accepted scan | Ditambahkan ke keranjang, belum disimpan |
| Committed sale | Penjualan berhasil dicatat |
| Uncertain | Hasil penyimpanan belum dapat dipastikan. Periksa status. |
| Unknown code / duplicate serial | Barcode tidak ditemukan. / Unit ini sudah ada dalam sesi. |
| Short stock / stale price | Stok tidak mencukupi. / Harga berubah. Tinjau kembali. |
| Cost incomplete | Data biaya belum lengkap |
| Shift / opening float / closing count / variance | Shift Kasir / Modal Kas Awal / Kas Terhitung / Selisih Kas |
| Unreconciled shift | Belum Direkonsiliasi |

id-ID numbers, explicit units/IDR/WIB. No ambiguous grouping parse. Separate loading, valid empty, no matches, denied, error, stale, incomplete, uncertain, committed and print-unavailable states. Negative gross result is Rugi Kotor; no Laba Bersih. Tax absent means not configured, not a verified zero rate.

## Five new Core visual bundles

| ID / future folder | Contract |
| --- | --- |
| RV01 / prototypes/rv01-shell-identity/ | Neutral shell/auth, all role navigation, business profile/theme preview, expanded/rail/drawer, focus and optional logo |
| RV02 / prototypes/rv02-pos-shifts/ | Fast mixed basket, payment/cash/change, unknown/concurrent/uncertain states, shift float/blind count/variance/handover, receipt thermal/A4/reprint, owner refund/return |
| RV03 / prototypes/rv03-catalog-onboarding/ | Goods/services/prices/barcodes/labels, create-only import, quantity/serial opening, owner evidence and type errors |
| RV04 / prototypes/rv04-inventory/ | Receiving/non-sale issue/transfer, stock/serial/history/owner correction and mobile scanning |
| RV05 / prototypes/rv05-owner-operations/ | Owner and operations attention/inbox, period goods/service finance with incomplete costs, audit/backup/recovery exceptions |

R01.1 creates RV01 in a future execution session. Each subsequent bundle waits for the prior owner Gate A; all five precede production authorization. Public/advanced modules later extend appropriate bundles and define a public contract only when authorized. CP01 approval cannot be transferred.

## Verification and gates

Standalone HTML/CSS and optional deterministic JS, local licensed assets, safe **Data Demo** fixtures; no production API/DB/auth credentials/CDN analytics. REVIEW.md binds exact revision/hash, pages/states, viewports, interactions, asset provenance, limitations and explicit owner approval reference. Registry holds one effective revision.

Required widths: 375/768/1024/1440/1920 plus 360 and 200% zoom; actual keyboard/focus/screen-reader status, touch, reduced-motion, contrast and print preview/device verification. Screenshots do not replace interactions. No fresh browser pass is claimed from historical CP01.

Before Gate A perform element/action/fact purpose audit: remove repeated buttons/facts, decorative cards/pills/charts, invented metrics, filler copy and wasteful spacing. Preserve useful desktop density and phone task order.

Lifecycle: technical/browser checks → [V] owner Gate A for prototype → approved pattern integration → technical/browser checks → [V] owner Gate B. Only explicit approval of that revision plus technical evidence permits [x]. New hierarchy/interaction/state needs prototype revision; direct reuse needs no second full HTML implementation. Neither gate approves business policy by implication.

