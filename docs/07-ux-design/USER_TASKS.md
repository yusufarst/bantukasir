# User tasks

| | |
| --- | --- |
| Status | CURRENT — written in RF-4A under D-070 and, after the stop on finding F-03, D-071; corrected after the independent final review under D-072 to D-075; 11 October 2026 (Asia/Jakarta) |
| Phase | P7 — user tasks for the information architecture |
| Gate | P7-G1, navigation and page structure (D-025): **artifacts ready for the Owner's review — NOT APPROVED**. Approval is a separate Owner decision that names the RF-4A checkpoint commit and the hash of each artifact (D-070) |
| Decisions | D-017, D-018, D-024, D-025, D-032, D-033, D-036, D-039, D-040, D-047, D-049, D-067 to D-075 |
| Related | [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md), [UI_STATES](UI_STATES.md), [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) |

What each person who uses BantuKasir is trying to do, how often, on which device and in which journey. The [information architecture](INFORMATION_ARCHITECTURE.md) is built from these tasks, not from the system's capabilities. This document adds no business rule: every task traces to a journey of [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 6, capabilities of [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 6 and requirements of [REQUIREMENTS](../01-product/REQUIREMENTS.md). Tasks are numbered `UXT-nn`; identifiers are permanent.

Indonesian labels in quotation marks are **working labels**, taken from the [GLOSSARY](../02-domain/GLOSSARY.md) where it has a term; they stay provisional until gate P7-G2 (terminology and copy, RF-4B). Nothing here is visual design (RF-4C, gate P7-G3) or an HTTP route (RF-5).

**Roles.** Owner = SUPER_ADMIN; Ops = OPERATIONS_ADMIN; Cashier = CASHIER; Combined = an account that holds CASHIER and OPERATIONS_ADMIN together and has exactly the union of their capabilities and never an Owner-only one (D-032, SEC-AZ-02). Capabilities are those of [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 6.

## 1. Frequency classes

| Class | Meaning |
| --- | --- |
| Per transaction | Repeated for every customer at the till |
| Many times a day | Several times in a working day |
| Daily | About once per working day or per work period |
| Weekly | A few times a week or less |
| Rare | Occasional; often triggered by a problem |
| Once | At most once for the shop's business data: the onboarding period, which a reinstallation, a server migration or a restore of an older backup does not repeat (D-071, D-072) |

"Rare but risky" marks a task that changes money, stock or access and is protected by a review step, a reason and, where SECURITY_MODEL marks it, recent authentication.

## 2. Who works with BantuKasir

| Role | Work | Main device |
| --- | --- | --- |
| Cashier | Sells at the till in their own cash session, opens and closes that session, prints receipts | PC kasir (keyboard, USB HID scanner) or tablet kasir (touch, Bluetooth HID scanner) (D-039) |
| Ops | Manages the catalogue, receives goods, records non-sale issues, watches stock | Any supported device; phone first (FD-05) |
| Combined | Both of the above, as one person with one identity (BR-USR-08) | Till devices for selling; any device for stock work |
| Owner | Everything above, plus oversight, corrections, refunds, users and configuration; sells only in the Owner's own session (BR-CASH-08) | Any supported device; phone first (FD-05) |

## 3. Tasks

| ID | Task (working label) | Goal | Roles | Frequency | Main device | Journey | Capabilities | Requirements | Pages | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXT-01 | "Masuk" | Start work under one's own identity | Cashier, Ops, Combined, Owner | Daily | Any | — | Pre-authentication | REQ-ACC-05 | UXP-001 | Ordinary |
| UXT-02 | "Buka sesi kas" | Take responsibility for one cash register with a counted opening amount | Cashier, Combined, Owner | Daily | PC kasir or tablet kasir | J-01 | CAP-21 | REQ-CASH-02, REQ-CASH-03 | UXP-104 | Ordinary |
| UXT-03 | "Jual, bayar tunai" | Sell goods paid in cash and give the change | Cashier, Combined, Owner | Per transaction | PC kasir or tablet kasir | J-02 | CAP-21, CAP-02, CAP-03, CAP-14 | REQ-SALE-01, REQ-SALE-04, REQ-PAY-02, REQ-SCAN-01, REQ-SCAN-02 | UXP-101, UXP-102, UXP-103 | Ordinary |
| UXT-04 | "Jual, bayar transfer bank" | Sell goods paid by bank transfer, confirmed from merchant evidence | Cashier, Combined, Owner | Per transaction | PC kasir or tablet kasir | J-02 | CAP-21, CAP-02 | REQ-PAY-03, REQ-SALE-04 | UXP-101, UXP-102, UXP-103 | Ordinary |
| UXT-05 | "Jual, bayar QRIS statis" | Sell goods paid by static QRIS, confirmed from merchant evidence | Cashier, Combined, Owner | Per transaction | PC kasir or tablet kasir | J-02 | CAP-21, CAP-02 | REQ-PAY-03, REQ-PAY-04, REQ-SALE-04 | UXP-101, UXP-102, UXP-103 | Ordinary |
| UXT-06 | "Cetak struk; cetak ulang" | Give the customer a receipt; print it again when needed | Cashier, Combined, Owner | Per transaction | PC kasir or tablet kasir | J-02 | CAP-21 | REQ-DOC-01, REQ-DOC-02, REQ-DOC-03 | UXP-103, UXP-202 | Ordinary |
| UXT-07 | "Tangani harga berubah, stok kurang atau produk nonaktif saat bayar" | Finish the sale correctly after a refusal, without a silent change of total | Cashier, Combined, Owner | Many times a day | PC kasir or tablet kasir | J-02 | CAP-21 | REQ-SALE-02, REQ-SALE-07 | UXP-101, UXP-102 | Ordinary |
| UXT-08 | "Pulihkan permintaan yang statusnya belum pasti" | Know whether a sale or posting was saved, without charging twice | Cashier, Combined, Owner, Ops | Rare | Any | J-02, J-05, J-07 | CAP-34 | REQ-SALE-08, REQ-CMD-01 | UXP-102, UXP-006 | Rare but risky |
| UXT-09 | "Tutup sesi kas" | Count the drawer and hand the session over with a correct report | Cashier, Combined, Owner | Daily | PC kasir or tablet kasir | J-03 | CAP-21 | REQ-CASH-05, REQ-CASH-06, REQ-CASH-07 | UXP-105, UXP-206 | Ordinary |
| UXT-10 | "Lihat penjualan dan sesi kas sendiri; cetak atau unduh laporan sesi" | Look up one's own Sales and session reports | Cashier, Combined, Owner | Daily | Any | J-03 | CAP-21 | REQ-SALE-12, REQ-CASH-12 | UXP-201, UXP-202, UXP-205, UXP-206 | Ordinary |
| UXT-11 | "Ganti kata sandi; keluar" | Keep one's own account safe; end work on a shared device | Cashier, Ops, Combined, Owner | Daily | Any | — | CAP-01 | REQ-ACC-05, REQ-ACC-07 | UXP-003, UXP-004, UXP-000 | Ordinary |
| UXT-12 | "Aktifkan akun dari tautan" | Set the first password, or a new one after a reset (for the Owner only after an operator procedure) | Cashier, Ops, Combined, Owner | Rare | Any | — | Pre-authentication | REQ-ACC-05 | UXP-002 | Ordinary |
| UXT-13 | "Cari produk dan cek stok" | Answer "do we have it, at what price, how many" | Ops, Combined, Owner | Many times a day | Any device; phone first (FD-05) | J-05 | CAP-02, CAP-14 | REQ-CAT-07, REQ-INV-11 | UXP-301, UXP-401, UXP-302 | Ordinary |
| UXT-14 | "Catat barang masuk" | Bring delivered goods into stock with their purchase cost, or with cost marked unknown | Ops, Combined, Owner | Daily | Any device; phone first (FD-05) | J-05 | CAP-11, CAP-12 | REQ-INV-04, REQ-INV-05 | UXP-404, UXP-405, UXP-406 | Ordinary |
| UXT-15 | "Catat barang keluar (bukan penjualan)" | Record goods that left for a non-sale reason | Ops, Combined, Owner | Weekly | Any device; phone first (FD-05) | J-05 | CAP-13 | REQ-INV-06 | UXP-407, UXP-408, UXP-409 | Ordinary |
| UXT-16 | "Pantau stok perlu restok" | See what is out or low and needs ordering | Ops, Combined, Owner | Daily | Any device; phone first (FD-05) | J-05 | CAP-14 | REQ-INV-10 | UXP-401, UXP-000 | Ordinary |
| UXT-17 | "Tambah produk (cari dulu)" | Add a new product without creating a duplicate | Ops, Combined, Owner | Weekly | Any device; phone first (FD-05) | J-04 | CAP-04, CAP-05, CAP-06 | REQ-CAT-01, REQ-CAT-03 | UXP-303, UXP-302 | Ordinary |
| UXT-18 | "Ubah data produk, harga jual atau stok minimum" | Keep product data and prices current | Ops, Combined, Owner | Weekly | Any device; phone first (FD-05) | J-04 | CAP-04, CAP-05 | REQ-CAT-01, REQ-CAT-08 | UXP-302, UXP-304 | Ordinary |
| UXT-19 | "Kelola barcode dan foto produk" | Add a manufacturer barcode, generate an internal one, retire one; set the product photo | Ops, Combined, Owner | Weekly | Any device; phone first (FD-05) | J-04 | CAP-06, CAP-09 | REQ-CAT-04, REQ-CAT-05, REQ-CAT-10 | UXP-302 | Ordinary |
| UXT-20 | "Cetak label barcode" | Label goods that have no usable barcode | Ops, Combined, Owner | Weekly | PC with label printer | J-04 | CAP-08 | REQ-CAT-11 | UXP-306 | Ordinary |
| UXT-21 | "Impor produk baru dari CSV" | Create many new products at once, all or none | Ops, Combined, Owner | Rare | PC | J-04 | CAP-10 | REQ-CAT-09 | UXP-308 | Ordinary |
| UXT-22 | "Kelola kategori; nonaktifkan atau aktifkan kembali produk" | Keep the catalogue tidy without deleting anything | Ops, Combined, Owner | Rare | Any device; phone first (FD-05) | J-04 | CAP-04 | REQ-CAT-12 | UXP-307, UXP-302 | Ordinary |
| UXT-23 | "Lihat keadaan hari ini dan hal yang perlu perhatian" | Start the day knowing sales, open sessions and problems | Owner | Daily | Any device; phone first (FD-05) | J-08 | CAP-22, CAP-14, CAP-18, CAP-20, CAP-32 | REQ-RPT-04, REQ-INV-10, REQ-OPS-04 | UXP-007 | Ordinary |
| UXT-24 | "Rekap harian" | Check one day's sales, refunds and every session's variance | Owner | Daily | Any device; phone first (FD-05) | J-08 | CAP-22 | REQ-RPT-04 | UXP-501, UXP-206 | Ordinary |
| UXT-25 | "Tinjau sesi kas; tambah catatan tinjauan" | Understand a variance and record an explanation without changing the report | Owner | Daily | Any device; phone first (FD-05) | J-03, J-08 | CAP-22 | REQ-CASH-09, REQ-CASH-12 | UXP-205, UXP-206 | Ordinary |
| UXT-26 | "Tutup sesi oleh Pemilik" | Close a session its responsible user could not close | Owner | Rare | Any device; phone first (FD-05) | J-03 | CAP-23 | REQ-CASH-11 | UXP-206, UXP-207 | Rare but risky |
| UXT-27 | "Pengembalian uang dan retur" | Refund a customer and take back goods correctly, from the right source of money | Owner | Rare | Any device; phone first (FD-05) | J-07 | CAP-24 | REQ-RFD-01, REQ-RFD-02, REQ-RFD-08, REQ-RFD-09 | UXP-201, UXP-202, UXP-203, UXP-204 | Rare but risky |
| UXT-28 | "Laporan keuangan dan tren" | See sales, cost of goods sold and gross profit for a period | Owner | Weekly | Any device; phone first (FD-05) | J-08 | CAP-20 | REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-06 | UXP-502 | Ordinary |
| UXT-29 | "Lengkapi atau koreksi biaya" | Make cost and profit figures complete and correct | Owner | Weekly | Any device; phone first (FD-05) | J-07 | CAP-19, CAP-20 | REQ-COST-05, REQ-COST-06 | UXP-503, UXP-504, UXP-505 | Rare but risky |
| UXT-30 | "Penyesuaian stok" | Bring recorded stock in line with a physical count | Owner | Rare | Any device; phone first (FD-05) | J-07 | CAP-16 | REQ-INV-08 | UXP-402, UXP-410 | Rare but risky |
| UXT-31 | "Pembatalan mutasi" | Undo a wrong receiving, manual issue or opening entry exactly | Owner | Rare | Any device; phone first (FD-05) | J-07 | CAP-17 | REQ-INV-09 | UXP-403, UXP-411 | Rare but risky |
| UXT-32 | "Koreksi salah pasang barcode" | Move a manufacturer barcode to the right product | Owner | Rare | Any device; phone first (FD-05) | J-04 | CAP-07 | REQ-CAT-13 | UXP-302, UXP-305 | Rare but risky |
| UXT-33 | "Masa input stok awal" | Enter the shop's opening stock while sales are frozen, check it, then release; allowed only once per business-data lifecycle, also across a restore of an older backup (D-071, D-072) | Owner | Once | PC | J-06 | CAP-15 | REQ-INV-07 | UXP-412, UXP-413 | Rare but risky |
| UXT-34 | "Tangani insiden rekonsiliasi" | Reopen a product's postings after its facts are checked | Owner | Rare | Any device; phone first (FD-05) | J-07 | CAP-18 | REQ-INV-12 | UXP-414, UXP-415 | Rare but risky |
| UXT-35 | "Kelola pengguna" | Create staff accounts, hand over links, change roles, deactivate, reactivate, anonymize | Owner | Rare | Any device; phone first (FD-05) | J-08 | CAP-25, CAP-26, CAP-27, CAP-28, CAP-29 | REQ-ACC-06, REQ-ACC-10, REQ-ACC-11 | UXP-602, UXP-603, UXP-604, UXP-605, UXP-606 | Rare but risky |
| UXT-36 | "Ubah profil usaha" | Keep the shop identity on receipts and documents correct | Owner | Rare | Any device; phone first (FD-05) | J-08 | CAP-30 | REQ-CFG-01 | UXP-607, UXP-608 | Ordinary |
| UXT-37 | "Atur laci kas" | Configure the cash registers of the shop | Owner | Rare | Any device; phone first (FD-05) | J-08 | CAP-31 | REQ-CFG-04 | UXP-609, UXP-610 | Ordinary |
| UXT-38 | "Status sistem, riwayat aktivitas, log keamanan" | Check that backups and reconciliation are healthy; look up who did what | Owner | Weekly | Any; PC for the logs | J-08 | CAP-32 | REQ-OPS-04, REQ-AUD-02 | UXP-611, UXP-612, UXP-613 | Ordinary |
| UXT-39 | "Ubah nama atau email Pemilik" | Keep the Owner's own account details current | Owner | Rare | Any device; phone first (FD-05) | — | CAP-27 | REQ-ACC-08 | UXP-005 | Ordinary |

The combined account's tasks are exactly the union of the Cashier's and Ops's tasks; it has none of the Owner's tasks (D-032). The Owner performs the Cashier tasks in the Owner's own cash session (BR-SALE-02, BR-CASH-08) and every Ops task. No task exists for anything outside V1 ([PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 8): no discounts or price overrides, no split or partial payment, no orders, no shifts, no paid-in or paid-out, no stock-taking beyond counts and adjustments, no language switch.

## 4. Priority for navigation

The order of each role's destinations puts the most frequent work first and oversight and settings last; destinations shared by several roles keep the same relative order, so that a combined account and the Owner meet them in the same sequence. INFORMATION_ARCHITECTURE section 4 applies this order.

| Role | Destinations by frequency | Home after sign-in |
| --- | --- | --- |
| Cashier | Kasir (per transaction) · Penjualan (daily) · Sesi kas (daily) | Kasir, or Buka sesi kas when the user has no OPEN session |
| Ops | Stok (many times a day; its sub-navigation holds Riwayat stok, Barang masuk and Barang keluar) · Produk (weekly) | Stok |
| Combined | Kasir · Penjualan · Sesi kas · Stok · Produk | Kasir when the user has an OPEN session; otherwise Stok |
| Owner | Beranda (daily: today's figures and attention) · Kasir (per transaction when the Owner sells) · Penjualan (daily) · Stok (daily) · Produk (weekly) · Laporan (daily for Rekap harian and Sesi kas; weekly for Laporan keuangan and Biaya perlu dilengkapi) · Pengaturan (rare) | Beranda |

## 5. Step budgets for time-critical tasks

Counted in user actions. An action is one deliberate step: a scan, a key press or shortcut that activates a control, a click or tap, or typing one amount. Moving the focus (Tab, arrow keys) is not counted. The keyboard shortcuts are provisional: accepted by the Owner (D-075) and verified later (ADAPTIVE_AND_ACCESSIBILITY UXA-09). The budgets are checked against INTERACTION_INVENTORY (G1-10).

| Task | Device | Budget (actions) | Path | Notes |
| --- | --- | --- | --- | --- |
| Sale paid CASH | PC | 3 | "Bayar" (F8) → type the amount received, or "Uang Pas" (Alt+U) → "Selesaikan" (Ctrl+Enter) | Tunai is chosen when the payment screen opens (G1-10; accepted by the Owner, D-075) |
| Sale paid CASH | Tablet | 3 | Tap "Bayar" → tap "Uang Pas", or type the amount → tap "Selesaikan" | As above |
| Sale paid BANK_TRANSFER | PC and tablet | 3 | "Bayar" (F8) → choose "Transfer bank" (Alt+2) → "Dana sudah diterima — Selesaikan" (Ctrl+Enter) | The reference is optional and not counted |
| Sale paid STATIC_QRIS | PC and tablet | 3 | "Bayar" (F8) → choose "QRIS statis" (Alt+3) → "Dana sudah diterima — Selesaikan" (Ctrl+Enter) | As above |
| Next sale after a receipt | PC and tablet | 0 | Scan the first item on UXP-103 (or 1 action: "Transaksi baru") | Printing is a separate, optional action |
| Add an item to the cart | PC and tablet | 1 | One scan per base unit; a repeated scan adds to the same line (D-067) | A quantity edit is one more action |
| Open a cash session | PC and tablet | 3 | On UXP-104 (reached through "Kasir"): type the opening cash → "Tinjau" (Ctrl+Enter) → "Buka sesi" (Ctrl+Enter); choosing the register is one more action when more than one is free | Review step kept (BR-CASH-05) |
| Close one's own cash session | PC and tablet | 5 | From UXP-101: "Tutup sesi" → "Lanjut hitung" (Ctrl+Enter) → type the physical cash → "Tinjau" (Ctrl+Enter) → "Tutup sesi" (Ctrl+Enter) | Counting the drawer happens between the second and third action; a recount repeats from the preview (BR-CASH-14); review step kept (BR-CASH-13) |

For every payment method there is one payment screen with one final action and no checkbox (D-040, BR-SALE-10 to BR-SALE-14). The receipt appears without a further confirmation (BR-SALE-14). Opening and closing a session keep their review step (BR-SALE-15).

## 6. Rare but risky Owner tasks

These tasks are rare, so their pages favour clarity over speed: each starts from the record it changes and shows what will change before it is confirmed; the protections are those of the rules cited.

| Task | Starts from | Protection |
| --- | --- | --- |
| "Tutup sesi oleh Pemilik" (UXT-26) | UXP-206 (the session) | Preview, count and review; mandatory reason; recent authentication; the responsible user does not change (BR-CASH-18) |
| "Pengembalian uang dan retur" (UXT-27) | UXP-202 (the Sale) | Review; mandatory reason; one explicitly chosen source of money; recent authentication; cumulative bounds (BR-RFD-02, BR-RFD-03, BR-RFD-06) |
| "Lengkapi atau koreksi biaya" (UXT-29) | UXP-503, UXP-505, UXP-406 or UXP-403 (the cost source) | Review of the old and new value; mandatory reason; recent authentication (BR-COST-20) |
| "Penyesuaian stok" (UXT-30) | UXP-402 or UXP-401 (the product) | Review of stock before, change and after; reason, count and reference; recent authentication (BR-INV-31) |
| "Pembatalan mutasi" (UXT-31) | UXP-403, UXP-406 or UXP-409 (the movement or line) | Review of the exact inverse; reason; recent authentication (BR-INV-34) |
| "Koreksi salah pasang barcode" (UXT-32) | UXP-302 (the product) | Review; reason; recent authentication (BR-CAT-50, BR-CAT-51) |
| "Masa input stok awal" (UXT-33) | UXP-412 | Review of every entry; confirmation dialogs with recent authentication to start and to end the onboarding (BR-INV-24, BR-INV-28) |
| "Tangani insiden rekonsiliasi" (UXT-34) | UXP-415 (the incident) | Confirmation dialog; recent authentication (BR-INV-46) |
| "Kelola pengguna" (UXT-35) | UXP-602 and UXP-604 (the account) | Confirmation dialogs; a reason to anonymize; recent authentication (SECURITY_MODEL section 6) |

UXT-08 (resolving an uncertain request) is also rare but risky; it belongs to every role and is protected by the rule that the request is never repeated as a new one ([UI_STATES](UI_STATES.md) section 4).
