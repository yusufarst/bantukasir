# 05 — Design system and UX contract

Owns interaction hierarchy, brand neutrality, responsive behavior and visual review gates.

## Character

Premium, modern, restrained and operational. Avoid generic AI-dashboard styling, excessive gradients/glassmorphism, excessive rounded cards, decorative charts and repeated KPI facts.

Use strong typography, clear spacing, dense readable tables, useful empty/loading/error states, keyboard focus, touch targets and restrained motion. User-facing language is Bahasa Indonesia.

## Brand-neutral foundation

The universal UI must not depend on LATANSA or burgundy. Use neutral surfaces/high-contrast text, independent semantic success/warning/danger/info colors, visible focus and one constrained BusinessProfile accent where contrast is valid.

BusinessProfile may configure name/logo/contact/document identity and limited accent. Critical semantics never inherit brand color. Existing LATANSA assets are first-client/historical assets only.

Typography: Source Sans 3 unless implementation verification selects another suitable open-source option. Icons: Lucide. Use shadcn/ui selectively; not every surface is a rounded card.

## Progressive disclosure

### Kasir
Primary: `Scan / Cari → Keranjang → Bayar`.

Normal instant sale shows no mandatory customer/schedule/reservation fields. Defaults source location/register and keeps scanner/keyboard flow fast.

Secondary **Pesanan / DP** reveals customer, payment amount, schedule/due date, reservation and service-booking fields only when needed.

### Admin Operasional
Home prioritizes orders to prepare, fulfillment due, today's/late service jobs, LOW/OUT stock, incoming goods and operational exceptions.

### Pemilik
Home prioritizes critical exceptions, stock attention, active/late orders, outstanding balances, cashier variance and finance completeness.

## Navigation

Desktop baseline:
- Dasbor
- Kasir
- Pesanan
- Pekerjaan
- Pembayaran
- Barang & Jasa
- Inventory: Stok, Barang Masuk, Barang Keluar, Transfer, Restock, Riwayat
- Laporan
- Sistem: Pengguna, Audit, Profil Bisnis, Backup & Kesehatan

Render only authorized/implemented items. Desktop uses persistent sidebar and compact sticky topbar where useful. Mobile uses drawer/task-first screens.

## Business-language states

Examples:
- PARTIALLY_PAID → **Dibayar Sebagian**
- PARTIAL goods → **Barang Diserahkan Sebagian**
- IN_PROGRESS service → **Pekerjaan Berjalan**

Show independent status badges only when they answer distinct questions.

## Visual contracts — interleaved

Major interaction families use deterministic HTML/CSS prototypes before their production UI:

- **RV01** shell/auth/role workspaces, business identity/settings preview + fast POS skeleton;
- **RV02** goods/services, barcode/labels, receiving/inventory;
- **RV03** order/booking, DP/partial payment, reservation, partial fulfillment, service progress;
- **RV04** cashier shift, payment, receipt/reprint, refund and final fast-checkout states;
- **RV05** owner dashboard, restock attention, finance reports and system/recovery states.

Gate A approves prototype direction only. Gate B approves real integrated UI after technical/browser evidence. Prototypes are interleaved with vertical slices; all five do not block backend foundation work.

## Required states

Significant screens cover loading, empty, validation error, denied action when relevant, stale/concurrent edit, unknown critical-command result and confirmed success.

Verify keyboard/focus/reduced motion and 375/768/1024/1440/1920 widths plus useful 200% zoom behavior for affected screens.
