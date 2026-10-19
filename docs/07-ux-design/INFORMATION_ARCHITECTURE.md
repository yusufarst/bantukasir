# Information architecture

| | |
| --- | --- |
| Status | CURRENT — written in RF-4A under D-070 and, after the stop on finding F-03, D-071; corrected after the independent final review under D-072 to D-075 and after the targeted re-verification of that remediation (D-075); 11 and 12 October 2026 (Asia/Jakarta); amended for the Owner's payment-method decision Q38 under D-077 and D-079 (STATIC_QRIS removed), 17 October 2026 (Asia/Jakarta); revised by TASK-002 under change control — the gate row (finding F-01), the working labels of COPY_DECK section 19 (finding F-03), 19 October 2026 (Asia/Jakarta) |
| Phase | P7 — information architecture, role-based navigation and page inventory |
| Gate | P7-G1, navigation and page structure (D-025): **APPROVED** by D-082, as committed in `d4c0594`. Revised by TASK-002 under change control (OPEN_ITEMS T-19, T-20; [COPY_DECK](COPY_DECK.md) section 22), whose package the Owner approves by the hash of each artifact at gate GATE-CC1 ([TASK_PLAN](../11-tasks/TASK_PLAN.md) section 3) |
| Decisions | D-017, D-018, D-024, D-025, D-032, D-033, D-036, D-039, D-040, D-047, D-049, D-067 to D-075, D-077 |
| Related | [USER_TASKS](USER_TASKS.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md), [UI_STATES](UI_STATES.md), [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) |

How BantuKasir is organized for the people who use it: the areas, each role's navigation, every page of V1 with who may reach it, and how the former design candidate and the legacy P7 coverage groups map onto the new pages. It presents rules already accepted in P1, P2, P5 and P6 and the Owner's decisions D-067 to D-069 and D-071 to D-075; it adds no business rule. Pages are numbered `UXP-nnn`; identifiers are permanent.

Indonesian labels in quotation marks are **working labels**, taken from the [GLOSSARY](../02-domain/GLOSSARY.md) where it has a term; they stay provisional until gate P7-G2 (terminology and copy, RF-4B). Nothing here is visual design (RF-4C, gate P7-G3) or an HTTP route (RF-5).

**Roles.** Owner = SUPER_ADMIN; Ops = OPERATIONS_ADMIN; Cashier = CASHIER; Combined = an account that holds CASHIER and OPERATIONS_ADMIN together and has exactly the union of their capabilities and never an Owner-only one (D-032, SEC-AZ-02). Capabilities are those of [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 6.

## 1. Principles

- **Organized by task, not by system capability.** Areas follow the user tasks of [USER_TASKS](USER_TASKS.md); the legacy coverage groups by capability (G01 to G18) are traced in section 12 but do not shape the navigation.
- **One name per destination.** A destination has the same label in the navigation, the page title, the sub-navigation and every link, for every role. A role's scope changes what a page lists (for example a cashier's own Sales), never its name.
- **Each role sees only what it may use.** Navigation and controls appear only for the capabilities the role holds (SEC-AZ-07); the server refuses everything else regardless.
- **Pages for detail, create and edit; dialogs only for review and confirmation** ([ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) UXA-05). Every child page has a contextual back action ([NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md)).
- **Corrections start from the record they correct.** A refund starts from its Sale, a reversal from its movement or document line, a cost correction from its cost source, an Owner-assisted close from its session. There is no separate corrections area.
- **One shared Product Search** serves every page that picks a product (BR-CAT-23; section 7).
- **Nothing outside V1 appears**, not even disabled (REQ-SCOPE-02). No page offers a tenant, outlet, warehouse or location (REQ-SCOPE-01).
- **Nothing is deleted.** No page has a delete control for master data or a business record; master data is deactivated and reactivated (D-042, D-044).

## 2. Areas

| Area | Pages | Contents |
| --- | --- | --- |
| A0 | UXP-000 to UXP-007 | Common: sign-in, account, shell and Owner home |
| A1 | UXP-101 to UXP-105 | Till |
| A2 | UXP-201 to UXP-207 | Sales and cash sessions |
| A3 | UXP-301 to UXP-308 | Products |
| A4 | UXP-401 to UXP-415 | Stock |
| A5 | UXP-501 to UXP-505 | Reports |
| A6 | UXP-601 to UXP-613 | Settings |

The page numbers group pages by family. Where a page sits in a role's navigation is section 4; for example the Owner reaches Sesi kas (UXP-205) under Laporan, a cashier as a destination of its own.

## 3. Access classes (G1-05)

Every page belongs to exactly one of two classes.

**Protected by capability.** Every page except UXP-001 and UXP-002. It requires a signed-in, ACTIVE account (SEC-AZ-01) and names the capabilities of SECURITY_MODEL section 6 that reach it; each control names its own (INTERACTION_INVENTORY). An object outside the actor's scope is treated as not found (TR-ERR-03). Every capability CAP-01 to CAP-34 is mapped (section 8) except **CAP-33**, which is an operator procedure only and has no page (D-048).

**Pre-authentication.** Only the exceptions of SEC-AZ-01: the sign-in page and the one-time link page. These pages use no capability. Each names its authentication and security requirements and its access restrictions:

| Page | Purpose | Authentication and security requirements | Access restrictions |
| --- | --- | --- | --- |
| UXP-001 "Masuk" | Sign in | SEC-AUTH-01, SEC-AUTH-07, SEC-AUTH-08, SEC-AUTH-09, SEC-AUTH-10; throttling SECURITY_MODEL section 11; CSRF section 12 (SEC-HDR-01); THR-01 | Reachable without a sign-in session; reveals neither whether an account or link exists nor its state; shows no business data; no "remember me" (SEC-AUTH-07) |
| UXP-002 "Atur kata sandi" | Consume a one-time link: first password (invitation activation, FIRST_PASSWORD) or reset (staff account recovery, RESET) | SEC-AUTH-03, SEC-AUTH-05, SEC-ACC-04, SEC-ACC-05, SEC-ACC-06, SEC-CH-03; throttling SECURITY_MODEL section 11; CSRF section 12 (SEC-HDR-01; `no-referrer`, SEC-HDR-02); THR-01 | Reachable without a sign-in session; reveals neither whether an account or link exists nor its state; shows no business data; the token is read from the URL fragment, never sent in a request line, removed from the address bar and history; the page loads no third-party resource and sends no referrer (SEC-ACC-05) |

- **CAP-01.** Its "sign in" part is realized by the pre-authentication page UXP-001. Its "sign out, view own account, change own password" parts are the protected pages and controls UXP-000 (account menu, "Keluar"), UXP-003 and UXP-004.
- **No registration and no public reset.** There is no sign-up page and no "forgot password" page (SEC-AUTH-02, FD-04, THR-01). A staff member's reset is a link that the Owner issues (UXP-604, UXP-606).
- **Owner recovery and Owner replacement** are operator procedures on the server with no page (SEC-AUTH-04, SEC-OP-02, SEC-OP-03). A link they produce is consumed on UXP-002 like any other.
- No new capability is introduced and no security rule is relaxed.

## 4. Navigation per role

| Role | Primary destinations, in order | Count | Sub-navigation | Home after sign-in |
| --- | --- | --- | --- | --- |
| Owner | Beranda · Kasir · Penjualan · Stok · Produk · Laporan · Pengaturan | 7 | Produk: Produk · Kategori · Impor produk (CSV). Stok: Stok · Riwayat stok · Barang masuk · Barang keluar · Stok awal · Insiden rekonsiliasi. Laporan: Rekap harian · Sesi kas · Laporan keuangan · Biaya perlu dilengkapi. Pengaturan: an index page (UXP-601) | Beranda (UXP-007) |
| Ops | Stok · Produk | 2 | Produk: Produk · Kategori · Impor produk (CSV). Stok: Stok · Riwayat stok · Barang masuk · Barang keluar | Stok (UXP-401) |
| Cashier | Kasir · Penjualan · Sesi kas | 3 | None | Kasir (UXP-101), or Buka sesi kas (UXP-104) when the user has no OPEN session of their own (BR-SALE-02) |
| Combined | Kasir · Penjualan · Sesi kas · Stok · Produk | 5 | As Ops for Produk and Stok | Kasir (UXP-101) when the user has an OPEN session of their own; otherwise Stok (UXP-401) |

- **Counts against G1-01:** Owner 7 (limit 7; the former candidate had 11), Ops 2 (limit 6), Cashier 3 (limit 4). The combined account's 5 destinations are exactly the union of the Cashier's and Ops's, with no Owner destination (G1-02).
- **No role switcher.** A combined account sees both sets at once; the move between selling and stock work is one navigation item (the planning agent's proposal under G1-02, accepted by the Owner in D-075; not a rule of D-032 or SEC-AZ-02).
- **Combined account's home.** Without an OPEN session of its own, a combined account lands on Stok (UXP-401) rather than on Buka sesi kas, because it does stock work as well as selling; "Kasir" then opens Buka sesi kas in one action (the planning agent's proposal under G1-03, accepted by the Owner in D-075; not a rule of D-032).
- **The "Kasir" destination** opens UXP-101 when the user holds an OPEN cash session of their own, and UXP-104 (Buka sesi kas) otherwise. Opening a session returns to UXP-101.
- **Account menu**, the same for every role: "Akun saya" (UXP-003), "Permintaan belum pasti" (UXP-006, only while the user has unresolved requests), "Keluar". The recent-authentication confirmation is a dialog of the shell, not a page (UXS-11).
- **Badges.** The Stok item carries the number of Habis and Menipis products (BR-INV-41); the Owner's Stok sub-navigation item "Insiden rekonsiliasi" carries the number of open incidents.
- **Device behaviour** of the navigation: [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) UXA-03.

## 5. Page inventory

The capability column of a protected page names the capabilities needed to open the page or to display its content, then the further capabilities its controls use (from [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md)). A control is shown only to a role that holds its capability. Section 8 uses the same union.

| ID | Working name | Working label | Area | Type | Purpose | Parent | Access class | Roles and capabilities, or requirements | Primary action | Requirements | Devices |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXP-000 | Application shell | "Kerangka aplikasi" | A0 | Shell | Frame of every protected page: role navigation, area sub-navigation, account menu, sign-out, global banners and the dialogs shared by all pages (recent authentication, sign-out warning, sign-in session ending) | — | Protected | Owner, Ops, Cashier, Combined · to open or display: CAP-01, CAP-34; its controls also use: CAP-02, CAP-04, CAP-10, CAP-11, CAP-13, CAP-14, CAP-15, CAP-18, CAP-20, CAP-21, CAP-22, CAP-25, CAP-32 | — | REQ-ACC-03, REQ-ACC-07, REQ-UX-02, REQ-NFR-01 | PC and tablet: side navigation; phone: navigation drawer (ADAPTIVE_AND_ACCESSIBILITY UXA-03) |
| UXP-001 | Sign in | "Masuk" | A0 | Sign-in form | Sign in with email and password | — | Pre-authentication | Visitor without a sign-in session. Requirements: SEC-AUTH-01, SEC-AUTH-07, SEC-AUTH-08, SEC-AUTH-09, SEC-AUTH-10; throttling SECURITY_MODEL section 11; CSRF section 12 (SEC-HDR-01); THR-01. Restrictions: Reachable without a sign-in session; reveals neither whether an account or link exists nor its state; shows no business data | Masuk | REQ-ACC-05, REQ-ACC-07, REQ-NFR-07 | All device classes; single column |
| UXP-002 | Set password (one-time link) | "Atur kata sandi" | A0 | One-time link form | Set the first password (FIRST_PASSWORD) or a reset password (RESET) of a staff account, or of the Owner account after an operator procedure, from a one-time link | — | Pre-authentication | Visitor without a sign-in session. Requirements: SEC-AUTH-03, SEC-AUTH-05, SEC-ACC-04, SEC-ACC-05, SEC-ACC-06, SEC-CH-03; throttling SECURITY_MODEL section 11; CSRF section 12 (SEC-HDR-01; `no-referrer`, SEC-HDR-02); THR-01. Restrictions: Reachable without a sign-in session; reveals neither whether an account or link exists nor its state; shows no business data | Simpan kata sandi | REQ-ACC-05, REQ-NFR-07 | All device classes; single column |
| UXP-003 | My account | "Akun saya" | A0 | Account | See one's own name, email and roles; reach password change; the Owner reaches the change of the Owner's own name and email | UXP-000 (account menu) | Protected | Owner, Ops, Cashier, Combined · to open or display: CAP-01; its controls also use: CAP-27 | Ubah kata sandi | REQ-ACC-05, REQ-ACC-06 | All device classes |
| UXP-004 | Change password | "Ubah kata sandi" | A0 | Edit | Change one's own password (current password required; other sign-in sessions end) | UXP-003 | Protected | Owner, Ops, Cashier, Combined · to open or display: CAP-01 | Simpan kata sandi | REQ-ACC-05 | All device classes |
| UXP-005 | Change Owner name and email | "Ubah nama dan email" | A0 | Edit | The Owner changes the Owner's own display name or email (SEC-ACC-10) | UXP-003 | Protected | Owner · to open or display: CAP-27; recent authentication for the commands of CAP-27 | Simpan perubahan | REQ-ACC-08 | All device classes |
| UXP-006 | Unresolved requests | "Permintaan belum pasti" | A0 | List | Resolve the signed-in user's own commands whose outcome is not yet known (ADR-007 sections 4 and 5) | UXP-000 | Protected | Owner, Ops, Cashier, Combined · to open or display: CAP-34; its controls also use: CAP-11, CAP-13, CAP-21 | Cek status | REQ-SALE-08, REQ-CMD-01, REQ-RFD-09 | All device classes |
| UXP-007 | Owner home | "Beranda" | A0 | Home | The Owner's start of day: today's figures, cash sessions now open, and the items that need the Owner's attention, each linking to its page | — (top-level) | Protected | Owner · to open or display: CAP-22, CAP-14, CAP-18, CAP-20, CAP-32; its controls also use: CAP-15 | — | REQ-RPT-04, REQ-INV-10, REQ-OPS-04 | Phone first (FD-05); PC and tablet |
| UXP-101 | Till | "Kasir" | A1 | Till workspace | Scan or search products, build the cart, go to payment, all in the user's own OPEN cash session | — (top-level) | Protected | Owner, Cashier, Combined · to open or display: CAP-21, CAP-02, CAP-03, CAP-14; its controls also use: CAP-34 | Bayar | REQ-SALE-01, REQ-SALE-02, REQ-SALE-06, REQ-SCAN-01, REQ-SCAN-02, REQ-SCAN-03, REQ-SCAN-04, REQ-CAT-07, REQ-CAT-10 | PC with keyboard and USB HID scanner; tablet with touch and Bluetooth HID scanner (D-039); phone not a primary cashier device |
| UXP-102 | Payment screen | "Pembayaran" | A1 | Payment | The one payment screen with one final action (D-040): choose CASH or BANK_TRANSFER and complete the Sale | UXP-101 | Protected | Owner, Cashier, Combined · to open or display: CAP-21; its controls also use: CAP-34 | Selesaikan / Dana sudah diterima — Selesaikan | REQ-SALE-04, REQ-SALE-05, REQ-SALE-06, REQ-SALE-07, REQ-SALE-08, REQ-PAY-01, REQ-PAY-02, REQ-PAY-03, REQ-PAY-04, REQ-SALE-09 | PC: keyboard path; tablet: final action large and apart from other controls (BR-SALE-14) |
| UXP-103 | Receipt after a sale | "Struk" | A1 | Result | Show the committed receipt with no further confirmation (BR-SALE-14); print it; start the next sale | UXP-101 | Protected | Owner, Cashier, Combined · to open or display: CAP-21; its controls also use: CAP-02 | Transaksi baru | REQ-SALE-04, REQ-DOC-01, REQ-DOC-02, REQ-DOC-03, REQ-DOC-05, REQ-SALE-11 | PC and tablet; receipt print through the browser (58 mm, 80 mm, A4) |
| UXP-104 | Open cash session | "Buka sesi kas" | A1 | Command page | Open a cash session as oneself: choose an available cash register, enter the opening cash (Rp0 valid), review, confirm | — (the Kasir destination when the user has no OPEN session) | Protected | Owner, Cashier, Combined · to open or display: CAP-21; its controls also use: CAP-34 | Tinjau → Buka sesi | REQ-CASH-02, REQ-CASH-03, REQ-SALE-01 | PC and tablet |
| UXP-105 | Close own cash session | "Tutup sesi kas" | A1 | Close flow | Close one's own OPEN session: preview, count the drawer, review, close; recount when new activity arrived | UXP-101 | Protected | Owner, Cashier, Combined · to open or display: CAP-21; its controls also use: CAP-34 | Tinjau → Tutup sesi | REQ-CASH-05, REQ-CASH-06, REQ-CASH-07, REQ-CASH-08, REQ-CASH-13 | PC and tablet; scan capture paused during the count |
| UXP-201 | Sales | "Penjualan" | A2 | List | Find a Sale by date or number: a cashier's own Sales (CAP-21), every Sale for the Owner (CAP-22) | — (top-level) | Protected | Owner, Cashier, Combined · to open or display: CAP-21, CAP-22 | — | REQ-SALE-12, REQ-RPT-05, REQ-DOC-04 | All device classes; table becomes a list on narrow screens |
| UXP-202 | Sale detail | "Detail penjualan" | A2 | Detail | One Sale: its lines (one per product, D-067), payment, linked refunds and returns; reprint the receipt; the Owner starts a refund or return | UXP-201 | Protected | Owner, Cashier, Combined · to open or display: CAP-21, CAP-22, CAP-24 | Cetak ulang struk | REQ-DOC-01, REQ-DOC-03, REQ-DOC-04, REQ-RFD-07, REQ-SALE-12 | All device classes |
| UXP-203 | Refund and return | "Pengembalian uang dan retur" | A2 | Command page | The Owner refunds money, takes back goods, or both, against the original Sale, with an explicitly chosen source of money, a reason, review and recent authentication | UXP-202 | Protected | Owner · to open or display: CAP-24; its controls also use: CAP-34; recent authentication for the commands of CAP-24 | Tinjau → Konfirmasi pengembalian | REQ-RFD-01, REQ-RFD-02, REQ-RFD-03, REQ-RFD-04, REQ-RFD-05, REQ-RFD-06, REQ-RFD-08, REQ-RFD-09, REQ-ACC-08 | Phone first (FD-05); PC and tablet |
| UXP-204 | Refund recorded | "Pengembalian tercatat" | A2 | Result | Confirm the committed refund; for a drawer-funded refund, tell the Owner to take the cash from that drawer now and hand it over (D-046; D-068) | UXP-203 | Protected | Owner · to open or display: CAP-24 | Kembali ke detail penjualan | REQ-RFD-03, REQ-RFD-09 | Phone first (FD-05); PC and tablet |
| UXP-205 | Cash sessions | "Sesi kas" | A2 | List | Find a cash session: one's own (CAP-21) or every session (CAP-22) | — (top-level for Cashier and Combined; Laporan sub-navigation for the Owner) | Protected | Owner, Cashier, Combined · to open or display: CAP-21, CAP-22 | — | REQ-CASH-12, REQ-RPT-05 | All device classes |
| UXP-206 | Cash session detail and session report | "Detail sesi kas" | A2 | Detail | An OPEN session's running figures and its close path; a CLOSED session's immutable report with print and CSV export; for the Owner, drawer-funded refunds beside the variance and the Owner review notes (D-068, D-069) | UXP-205 | Protected | Owner, Cashier, Combined · to open or display: CAP-21, CAP-22, CAP-23 | Cetak laporan (CLOSED) / Tutup sesi (OPEN) | REQ-CASH-09, REQ-CASH-11, REQ-CASH-12, REQ-RPT-04, REQ-DOC-04 | All device classes |
| UXP-207 | Owner-assisted close | "Tutup sesi oleh Pemilik" | A2 | Close flow | The Owner closes another user's OPEN session with a count, a reason and recent authentication; the responsible user does not change | UXP-206 | Protected | Owner · to open or display: CAP-23; its controls also use: CAP-34; recent authentication for the commands of CAP-23 | Tinjau → Tutup sesi | REQ-CASH-11, REQ-CASH-06, REQ-CASH-08, REQ-ACC-08 | Phone first (FD-05); PC and tablet |
| UXP-301 | Products | "Produk" | A3 | List | Find products through the shared Product Search with category, brand and status filters; start a new product | — (top-level) | Protected | Owner, Ops, Combined · to open or display: CAP-02, CAP-03, CAP-04; its controls also use: CAP-08 | Tambah produk | REQ-CAT-01, REQ-CAT-07, REQ-CAT-12 | Phone first (FD-05); PC and tablet |
| UXP-302 | Product detail | "Detail produk" | A3 | Detail | One product: data, selling price and its history, stock, photo, barcodes, labels; deactivate or reactivate | UXP-301 | Protected | Owner, Ops, Combined · to open or display: CAP-02, CAP-03, CAP-04, CAP-06, CAP-07, CAP-08, CAP-09, CAP-14, CAP-20; its controls also use: CAP-05 | Ubah | REQ-CAT-01, REQ-CAT-04, REQ-CAT-05, REQ-CAT-10, REQ-CAT-11, REQ-CAT-12, REQ-CAT-13 | Phone first (FD-05) |
| UXP-303 | Create product (search first) | "Tambah produk" | A3 | Create | Search for the exact barcode and SKU and for similar names first; then create the product with stock 0 (BR-CAT-04, BR-CAT-03) | UXP-301, or the working page that opened it (UXP-405, UXP-413) | Protected | Owner, Ops, Combined · to open or display: CAP-04, CAP-05, CAP-06, CAP-02 | Simpan produk | REQ-CAT-01, REQ-CAT-02, REQ-CAT-03, REQ-CAT-04, REQ-CAT-05 | Phone first (FD-05) |
| UXP-304 | Edit product | "Ubah produk" | A3 | Edit | Change product data, category, minimum stock and the selling price (a new price revision) | UXP-302 | Protected | Owner, Ops, Combined · to open or display: CAP-04, CAP-05 | Simpan perubahan | REQ-CAT-01, REQ-CAT-06, REQ-CAT-08, REQ-CAT-12 | Phone first (FD-05) |
| UXP-305 | Barcode misassignment correction | "Koreksi salah pasang barcode" | A3 | Command page | The Owner moves a manufacturer barcode from the wrong product to the correct one, or re-attaches one retired as a misassignment (D-051, D-052) | UXP-302 | Protected | Owner · to open or display: CAP-07, CAP-02; its controls also use: CAP-34; recent authentication for the commands of CAP-07 | Tinjau → Konfirmasi koreksi | REQ-CAT-13, REQ-CAT-05, REQ-ACC-08 | Phone first (FD-05) |
| UXP-306 | Print barcode labels | "Cetak label barcode" | A3 | Print | Print or reprint Code 128 labels of one product through the browser; never with cost | UXP-302 | Protected | Owner, Ops, Combined · to open or display: CAP-08 | Cetak | REQ-CAT-11 | PC with label printer (gate DEVICES) |
| UXP-307 | Categories | "Kategori" | A3 | List | Create, rename, deactivate and reactivate categories | — (Produk sub-navigation) | Protected | Owner, Ops, Combined · to open or display: CAP-04 | Tambah kategori | REQ-CAT-01, REQ-CAT-12 | Phone first (FD-05) |
| UXP-308 | CSV product import | "Impor produk (CSV)" | A3 | Command page | Upload a CSV file of new products, see every row checked in a preview, then confirm all rows or none (D-037, D-054, D-069 for category names) | — (Produk sub-navigation) | Protected | Owner, Ops, Combined · to open or display: CAP-10; its controls also use: CAP-04, CAP-34 | Impor | REQ-CAT-09, REQ-NFR-07 | PC preferred for the preview table; phone usable |
| UXP-401 | Stock | "Stok" | A4 | List | Current stock of every product with stock attention (Habis, Menipis, Normal), filters and the time of the last refresh | — (top-level) | Protected | Owner, Ops, Combined · to open or display: CAP-14, CAP-02; its controls also use: CAP-16 | — | REQ-INV-10, REQ-INV-11 | Phone first (FD-05) |
| UXP-402 | Stock history | "Riwayat stok" | A4 | List | Movements, filtered by product, type and date; the Owner starts an adjustment from here | — (Stok sub-navigation; also opened from a product) | Protected | Owner, Ops, Combined · to open or display: CAP-14, CAP-16; its controls also use: CAP-02 | — | REQ-INV-01, REQ-INV-11, REQ-INV-13, REQ-RPT-05 | Phone first (FD-05); wide table on PC |
| UXP-403 | Movement detail | "Detail mutasi stok" | A4 | Detail | One movement with its source, actor, time and reason; for the Owner its cost evidence and the reversal and cost-correction entries | UXP-402 | Protected | Owner, Ops, Combined · to open or display: CAP-14, CAP-17, CAP-19, CAP-20; its controls also use: CAP-11, CAP-13, CAP-22 | — | REQ-INV-01, REQ-INV-09, REQ-COST-04, REQ-COST-05 | Phone first (FD-05) |
| UXP-404 | Receiving documents | "Barang masuk" | A4 | List | Confirmed receiving documents; start a new receiving | — (Stok sub-navigation) | Protected | Owner, Ops, Combined · to open or display: CAP-11 | Catat barang masuk | REQ-INV-04 | Phone first (FD-05) |
| UXP-405 | New receiving | "Catat barang masuk" | A4 | Command page | Enter lines (existing products, quantity, purchase cost KNOWN or UNKNOWN), review, confirm; the draft lives in page memory only (BR-INV-17, D-069) | UXP-404 | Protected | Owner, Ops, Combined · to open or display: CAP-11, CAP-12, CAP-02, CAP-04; its controls also use: CAP-34 | Tinjau → Konfirmasi barang masuk | REQ-INV-03, REQ-INV-04, REQ-INV-05, REQ-INV-13, REQ-COST-03 | PC preferred for long documents; tablet and phone usable |
| UXP-406 | Receiving document detail | "Detail barang masuk" | A4 | Detail | A confirmed receiving document with its purchase cost as each role may see it (D-033, D-047); Owner correction and reversal entries per line | UXP-404 | Protected | Owner, Ops, Combined · to open or display: CAP-11, CAP-12, CAP-17, CAP-19; its controls also use: CAP-14, CAP-20 | — | REQ-INV-04, REQ-INV-05, REQ-COST-04 | Phone first (FD-05) |
| UXP-407 | Manual issues | "Barang keluar" | A4 | List | Recorded non-sale issues; start a new one. The Owner's explicit "Barang keluar" entry (D-018) | — (Stok sub-navigation) | Protected | Owner, Ops, Combined · to open or display: CAP-13 | Catat barang keluar | REQ-INV-06 | Phone first (FD-05) |
| UXP-408 | New manual issue | "Catat barang keluar" | A4 | Command page | Enter lines and the non-sale reason, review stock before, change and after, confirm | UXP-407 | Protected | Owner, Ops, Combined · to open or display: CAP-13, CAP-02; its controls also use: CAP-34 | Tinjau → Konfirmasi barang keluar | REQ-INV-02, REQ-INV-03, REQ-INV-06 | Phone first (FD-05) |
| UXP-409 | Manual issue detail | "Detail barang keluar" | A4 | Detail | A confirmed manual issue; the Owner's reversal entry per line | UXP-407 | Protected | Owner, Ops, Combined · to open or display: CAP-13, CAP-17; its controls also use: CAP-14 | — | REQ-INV-06, REQ-INV-09 | Phone first (FD-05) |
| UXP-410 | Stock adjustment | "Sesuaikan stok" | A4 | Command page | The Owner adjusts stock with signed quantities, a reason, a count and a reference, review and recent authentication | UXP-402 or UXP-401 (the page that opened it) | Protected | Owner · to open or display: CAP-16, CAP-02; its controls also use: CAP-34; recent authentication for the commands of CAP-16 | Tinjau → Konfirmasi penyesuaian | REQ-INV-08, REQ-INV-02, REQ-ACC-08 | Phone first (FD-05) |
| UXP-411 | Reversal of a movement | "Batalkan mutasi" | A4 | Command page | The Owner reverses one eligible OPENING, RECEIPT or MANUAL_ISSUE exactly, once, with a reason and recent authentication | UXP-403, UXP-406 or UXP-409 (the page that opened it) | Protected | Owner · to open or display: CAP-17; its controls also use: CAP-34; recent authentication for the commands of CAP-17 | Tinjau → Konfirmasi pembatalan | REQ-INV-09, REQ-COST-08, REQ-ACC-08 | Phone first (FD-05) |
| UXP-412 | Opening stock and onboarding | "Stok awal" | A4 | Hub | The Owner sets and releases the onboarding freeze, which is allowed only once per business-data lifecycle, also across a restore of an older backup (D-071, D-072), follows the opening stock checklist and starts opening stock entries; after the release the page shows the completed onboarding read only; while the onboarding state of restored business data waits for the Owner's restore reconciliation, it shows UXS-50, with no start control and, where the restored data show the onboarding freeze on, with entry and release disabled with the reason (UXS-13), otherwise without them | — (Stok sub-navigation, Owner) | Protected | Owner · to open or display: CAP-15; its controls also use: CAP-14, CAP-34; recent authentication for the commands of CAP-15 | Input stok awal | REQ-INV-07 | PC preferred; phone usable |
| UXP-413 | Opening stock entry | "Input stok awal" | A4 | Command page | Enter up to 100 product rows with counts (zero counts go to the checklist), cost KNOWN or UNKNOWN and count references; review; confirm all or none | UXP-412 | Protected | Owner · to open or display: CAP-15, CAP-02, CAP-04; its controls also use: CAP-34 | Tinjau → Konfirmasi stok awal | REQ-INV-07, REQ-COST-03 | PC preferred; tablet usable |
| UXP-414 | Reconciliation incidents | "Insiden rekonsiliasi" | A4 | List | Products whose postings the daily reconciliation blocked | — (Stok sub-navigation, Owner) | Protected | Owner · to open or display: CAP-18 | — | REQ-INV-12 | Phone first (FD-05) |
| UXP-415 | Incident detail | "Detail insiden" | A4 | Detail | One incident: what did not match, the blocked product; the Owner reopens postings with recent authentication | UXP-414 | Protected | Owner · to open or display: CAP-18; its controls also use: CAP-14; recent authentication for the commands of CAP-18 | Buka kembali mutasi | REQ-INV-12, REQ-ACC-08 | Phone first (FD-05) |
| UXP-501 | Daily summary | "Rekap harian" | A5 | Report | The Owner's figures of one Asia/Jakarta business day and the cash sessions with their variance | — (Laporan sub-navigation; Owner home of the Laporan destination) | Protected | Owner · to open or display: CAP-22 | — | REQ-RPT-04, REQ-RPT-05, REQ-PAY-04, REQ-DOC-05 | Phone first (FD-05) |
| UXP-502 | Finance report | "Laporan keuangan" | A5 | Report | Sales, refunds, cost of goods sold and gross profit for a period, with completeness and an interactive trend chart (D-018) | — (Laporan sub-navigation) | Protected | Owner · to open or display: CAP-20 | — | REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-06, REQ-COST-09, REQ-PAY-04 | Phone first (FD-05); chart readable at 360 px |
| UXP-503 | Costs to complete | "Biaya perlu dilengkapi" | A5 | List | Cost sources still UNKNOWN and products whose reporting is marked incomplete; the Owner's worklist for cost completion | — (Laporan sub-navigation) | Protected | Owner · to open or display: CAP-20, CAP-19 | — | REQ-COST-03, REQ-COST-05, REQ-RPT-03 | Phone first (FD-05) |
| UXP-504 | Cost correction | "Lengkapi biaya" when the cost is UNKNOWN, "Koreksi biaya" otherwise | A5 | Command page | The Owner corrects or completes one acquisition cost after posting, with a reason, review and recent authentication; reports are recalculated | The page that opened it (UXP-503, UXP-505, UXP-406 or UXP-403) | Protected | Owner · to open or display: CAP-19; its controls also use: CAP-34; recent authentication for the commands of CAP-19 | Tinjau → Konfirmasi koreksi | REQ-COST-05, REQ-COST-06, REQ-COST-09, REQ-ACC-08 | Phone first (FD-05) |
| UXP-505 | Product cost history | "Riwayat biaya" | A5 | Detail | One product's cost evidence, corrections, average cost and inventory value | The page that opened it (UXP-302, UXP-503, or UXP-006 after a resolved cost correction) | Protected | Owner · to open or display: CAP-20, CAP-19 | — | REQ-COST-01, REQ-COST-04, REQ-COST-09 | Phone first (FD-05) |
| UXP-601 | Settings | "Pengaturan" | A6 | Settings index | Index of the Owner's settings and oversight pages | — (top-level) | Protected | Owner · to open or display: CAP-25, CAP-30, CAP-31, CAP-32 | — | REQ-ACC-10, REQ-CFG-01, REQ-CFG-04, REQ-OPS-04, REQ-AUD-02 | Phone first (FD-05) |
| UXP-602 | Users | "Pengguna" | A6 | List | Every account with role, status and whether it holds an OPEN cash session; start a new staff account | UXP-601 | Protected | Owner · to open or display: CAP-25, CAP-27, CAP-28 | Tambah pengguna | REQ-ACC-01, REQ-ACC-10, REQ-ACC-12 | Phone first (FD-05) |
| UXP-603 | Create staff account | "Tambah pengguna" | A6 | Create | Create a CASHIER, OPERATIONS_ADMIN or combined account and issue its first-password link | UXP-602 | Protected | Owner · to open or display: CAP-25; recent authentication for the commands of CAP-25 | Simpan dan buat tautan | REQ-ACC-02, REQ-ACC-05, REQ-ACC-06, REQ-ACC-08 | Phone first (FD-05) |
| UXP-604 | User detail | "Detail pengguna" | A6 | Detail | One account: roles, status, link status and any OPEN cash session; reset link, deactivate (allowed with an OPEN session, D-069), reactivate, anonymize | UXP-602 | Protected | Owner · to open or display: CAP-26, CAP-27, CAP-28, CAP-29, CAP-23; its controls also use: CAP-22, CAP-25; recent authentication for the commands of CAP-25, CAP-26, CAP-28, CAP-29 | Ubah | REQ-ACC-06, REQ-ACC-10, REQ-ACC-11, REQ-ACC-12, REQ-ACC-08 | Phone first (FD-05) |
| UXP-605 | Edit staff account | "Ubah pengguna" | A6 | Edit | Change a staff member's display name, email or roles (CASHIER, OPERATIONS_ADMIN or both; never SUPER_ADMIN) | UXP-604 | Protected | Owner · to open or display: CAP-27; recent authentication for the commands of CAP-27 | Simpan perubahan | REQ-ACC-02, REQ-ACC-06, REQ-ACC-08 | Phone first (FD-05) |
| UXP-606 | One-time link handover | "Tautan kata sandi" | A6 | Handover | Show a newly issued one-time link once, for direct handover to the staff member (SEC-ACC-05) | UXP-604 | Protected | Owner · to open or display: CAP-25, CAP-26 | Selesai | REQ-ACC-05 | Phone first (FD-05); the staff member may scan it from the screen |
| UXP-607 | BusinessProfile | "Profil usaha" | A6 | Detail | The shop identity in force and its earlier versions | UXP-601 | Protected | Owner · to open or display: CAP-30 | Ubah profil usaha | REQ-CFG-01, REQ-CFG-02, REQ-SCOPE-03 | Phone first (FD-05) |
| UXP-608 | Change BusinessProfile | "Ubah profil usaha" | A6 | Edit | Create a new BusinessProfile version, including the logo | UXP-607 | Protected | Owner · to open or display: CAP-30; recent authentication for the commands of CAP-30 | Simpan sebagai versi baru | REQ-CFG-01, REQ-CFG-03, REQ-ACC-08 | Phone first (FD-05) |
| UXP-609 | Cash registers | "Laci kas" | A6 | List | Configured cash registers and whether a session is OPEN on each | UXP-601 | Protected | Owner · to open or display: CAP-31 | Tambah laci kas | REQ-CFG-04, REQ-CASH-01 | Phone first (FD-05) |
| UXP-610 | Cash register form | "Tambah laci kas" for a new register, "Ubah laci kas" for an existing one | A6 | Create / Edit | Create a cash register, or change its code, label or active flag while no session is OPEN on it | UXP-609 | Protected | Owner · to open or display: CAP-31; recent authentication for the commands of CAP-31 | Simpan | REQ-CFG-04, REQ-ACC-08 | Phone first (FD-05) |
| UXP-611 | System status | "Status sistem" | A6 | Status | Backup, restore, disk space and reconciliation health; anything unproven shows "Belum Terverifikasi" | UXP-601 | Protected | Owner · to open or display: CAP-32; its controls also use: CAP-18 | — | REQ-OPS-04, REQ-INV-12 | Phone first (FD-05) |
| UXP-612 | Business audit log | "Riwayat aktivitas" | A6 | List | The whole business audit, filtered by date, user, type, product, cash session and reference | UXP-601 | Protected | Owner · to open or display: CAP-32; its controls also use: CAP-22 | — | REQ-AUD-01, REQ-AUD-02 | PC preferred; phone usable |
| UXP-613 | Security log | "Riwayat keamanan" | A6 | List | Sign-in, throttling, link, confirmation, CSRF, refusal and revocation events | UXP-601 | Protected | Owner · to open or display: CAP-32 | — | REQ-AUD-02, REQ-NFR-07 | PC preferred; phone usable |

Total: **61 pages** — 59 protected by capability, 2 pre-authentication.

## 6. Decided and special entry points

- **"Barang keluar" for the Owner (D-018).** A named item of the Stok sub-navigation for every role that holds CAP-13, the Owner included; never hidden in an overflow menu.
- **Product creation from a working page.** On UXP-405 (receiving) and UXP-413 (opening stock), "Tambah produk" for an unknown code opens UXP-303 as a full page over the working page, so that the draft in page memory is kept (BR-INV-17, D-069, BR-CAT-31; UXA-05).
- **Refunds and returns** start only from the Sale detail (UXP-202) and only for the Owner (CAP-24).
- **Owner-assisted close** starts from the session detail (UXP-206) or, for a user who holds an OPEN session, from the user detail (UXP-604; D-069).
- **Reversal** starts from the movement (UXP-403) or the document line (UXP-406, UXP-409); **adjustment** from stock history (UXP-402) or a stock row (UXP-401); **cost correction** from the cost worklist (UXP-503), the cost history (UXP-505), the receiving line (UXP-406) or the movement (UXP-403).
- **Unresolved requests** (UXP-006) are reached from the account menu, a banner on every page while any exist, and the uncertain panels of command pages.
- **No receiving draft page.** Receiving drafts stay in page memory for V1 (D-069, T-17 a); the absence is explicit in INTERACTION_INVENTORY.

## 7. Shared Product Search (BR-CAT-23)

One search behaviour — exact barcode first, then SKU, then name; category and brand filters; server paging; a newer search supersedes an older one (BR-CAT-24, BR-CAT-25, TR-PAGE-04) — appears wherever a product is chosen. Results never show cost (BR-CAT-26).

| Page | Use | Scanner input |
| --- | --- | --- |
| UXP-101 Kasir | Add to the cart | Yes; needs the user's own OPEN session (BR-CAT-33) |
| UXP-301 Produk | Find a product to open | Yes |
| UXP-303 Tambah produk | Search first before creating (BR-CAT-04) | Yes, in the barcode field |
| UXP-305 Koreksi salah pasang barcode | Choose the correct product | Yes |
| UXP-103 Struk | Start the next sale: a scan or a search hands the product to the till (UXA-11) | Yes; needs the user's own OPEN session (BR-CAT-33) |
| UXP-401 Stok | Find a product's stock | Yes |
| UXP-402 Riwayat stok | Filter by product | Yes |
| UXP-405 Catat barang masuk | Add a receiving line | Yes |
| UXP-408 Catat barang keluar | Add an issue line | Yes |
| UXP-410 Penyesuaian stok | Add an adjustment line | Yes |
| UXP-413 Input stok awal | Add an opening row | Yes |

Labels are printed from the product (UXP-302 → UXP-306), so the label page needs no search of its own.

## 8. Capability coverage (G1-05)

Every capability of SECURITY_MODEL section 6 against the protected pages and the interactions that use it. CAP-33 has no page by rule.

| Capability | Pages | Interactions | Note |
| --- | --- | --- | --- |
| CAP-01 | UXP-000, UXP-003, UXP-004 | UXI-022, UXI-023, UXI-024, UXI-026, UXI-028, UXI-031, UXI-032, UXI-033, UXI-034, UXI-035, UXI-038, UXI-045, UXI-047, UXI-048, UXI-049, UXI-050 | The sign-in part is UXP-001 (pre-authentication) |
| CAP-02 | UXP-000, UXP-101, UXP-103, UXP-301, UXP-302, UXP-303, UXP-305, UXP-401, UXP-402, UXP-405, UXP-408, UXP-410, UXP-413 | UXI-005, UXI-009, UXI-066, UXI-067, UXI-068, UXI-069, UXI-089, UXI-159, UXI-160, UXI-162, UXI-164, UXI-177, UXI-178, UXI-179, UXI-185, UXI-191, UXI-213, UXI-219, UXI-234, UXI-245, UXI-256, UXI-264, UXI-268, UXI-276, UXI-292, UXI-300 | — |
| CAP-03 | UXP-101, UXP-301, UXP-302 | UXI-067, UXI-162 | — |
| CAP-04 | UXP-000, UXP-301, UXP-302, UXP-303, UXP-304, UXP-307, UXP-308, UXP-405, UXP-413 | UXI-005, UXI-009, UXI-010, UXI-161, UXI-165, UXI-166, UXI-168, UXI-178, UXI-180, UXI-181, UXI-182, UXI-183, UXI-184, UXI-185, UXI-186, UXI-187, UXI-188, UXI-189, UXI-202, UXI-203, UXI-204, UXI-205, UXI-209, UXI-235, UXI-293 | — |
| CAP-05 | UXP-302, UXP-303, UXP-304 | UXI-165, UXI-182, UXI-186, UXI-187, UXI-189 | — |
| CAP-06 | UXP-302, UXP-303 | UXI-171, UXI-172, UXI-173, UXI-182 | — |
| CAP-07 | UXP-302, UXP-305 | UXI-174, UXI-190, UXI-191, UXI-192, UXI-193, UXI-194, UXI-195, UXI-196, UXI-197, UXI-198 | — |
| CAP-08 | UXP-301, UXP-302, UXP-306 | UXI-163, UXI-175, UXI-199, UXI-200, UXI-201 | — |
| CAP-09 | UXP-302 | UXI-169, UXI-170 | — |
| CAP-10 | UXP-000, UXP-308 | UXI-011, UXI-206, UXI-207, UXI-208, UXI-210, UXI-211, UXI-212 | — |
| CAP-11 | UXP-000, UXP-006, UXP-403, UXP-404, UXP-405, UXP-406 | UXI-014, UXI-056, UXI-224, UXI-230, UXI-231, UXI-232, UXI-233, UXI-234, UXI-236, UXI-237, UXI-238, UXI-239, UXI-240, UXI-241, UXI-242, UXI-243, UXI-245, UXI-251 | — |
| CAP-12 | UXP-405, UXP-406 | UXI-236, UXI-246, UXI-247 | — |
| CAP-13 | UXP-000, UXP-006, UXP-403, UXP-407, UXP-408, UXP-409 | UXI-015, UXI-056, UXI-224, UXI-252, UXI-253, UXI-254, UXI-255, UXI-256, UXI-257, UXI-258, UXI-259, UXI-260, UXI-261, UXI-262, UXI-263, UXI-264, UXI-267 | — |
| CAP-14 | UXP-000, UXP-007, UXP-101, UXP-302, UXP-401, UXP-402, UXP-403, UXP-406, UXP-409, UXP-412, UXP-415 | UXI-006, UXI-012, UXI-013, UXI-061, UXI-067, UXI-167, UXI-213, UXI-214, UXI-215, UXI-216, UXI-218, UXI-219, UXI-220, UXI-222, UXI-223, UXI-224, UXI-229, UXI-248, UXI-265, UXI-287, UXI-288, UXI-303 | Cashier: only the stock needed at checkout, shown in the till's search results (UXP-101) |
| CAP-15 | UXP-000, UXP-007, UXP-412, UXP-413 | UXI-016, UXI-030, UXI-065, UXI-285, UXI-286, UXI-287, UXI-289, UXI-290, UXI-292, UXI-294, UXI-295, UXI-296, UXI-297, UXI-298, UXI-299, UXI-300 | — |
| CAP-16 | UXP-401, UXP-402, UXP-410 | UXI-217, UXI-221, UXI-268, UXI-269, UXI-270, UXI-271, UXI-272, UXI-273, UXI-274, UXI-275, UXI-276 | — |
| CAP-17 | UXP-403, UXP-406, UXP-409, UXP-411 | UXI-227, UXI-250, UXI-266, UXI-277, UXI-278, UXI-279, UXI-280, UXI-281, UXI-282, UXI-283, UXI-284 | — |
| CAP-18 | UXP-000, UXP-007, UXP-414, UXP-415, UXP-611 | UXI-017, UXI-063, UXI-301, UXI-302, UXI-303, UXI-304, UXI-305, UXI-375 | — |
| CAP-19 | UXP-403, UXP-406, UXP-503, UXP-504, UXP-505 | UXI-226, UXI-249, UXI-314, UXI-317, UXI-318, UXI-319, UXI-320, UXI-321, UXI-322, UXI-323, UXI-324 | — |
| CAP-20 | UXP-000, UXP-007, UXP-302, UXP-403, UXP-406, UXP-502, UXP-503, UXP-505 | UXI-007, UXI-020, UXI-021, UXI-062, UXI-176, UXI-225, UXI-247, UXI-308, UXI-309, UXI-310, UXI-311, UXI-312, UXI-313, UXI-315, UXI-316, UXI-325 | — |
| CAP-21 | UXP-000, UXP-006, UXP-101, UXP-102, UXP-103, UXP-104, UXP-105, UXP-201, UXP-202, UXP-205, UXP-206 | UXI-002, UXI-003, UXI-004, UXI-056, UXI-070, UXI-071, UXI-072, UXI-073, UXI-074, UXI-075, UXI-077, UXI-078, UXI-079, UXI-080, UXI-081, UXI-082, UXI-083, UXI-084, UXI-085, UXI-087, UXI-088, UXI-089, UXI-090, UXI-091, UXI-092, UXI-093, UXI-094, UXI-095, UXI-096, UXI-097, UXI-098, UXI-099, UXI-100, UXI-101, UXI-103, UXI-104, UXI-105, UXI-106, UXI-107, UXI-108, UXI-109, UXI-110, UXI-111, UXI-112, UXI-113, UXI-115, UXI-116, UXI-117, UXI-119, UXI-121, UXI-135, UXI-137, UXI-138, UXI-139, UXI-141, UXI-142, UXI-143, UXI-148 | — |
| CAP-22 | UXP-000, UXP-007, UXP-201, UXP-202, UXP-205, UXP-206, UXP-403, UXP-501, UXP-604, UXP-612 | UXI-001, UXI-003, UXI-007, UXI-018, UXI-019, UXI-059, UXI-060, UXI-112, UXI-113, UXI-114, UXI-115, UXI-116, UXI-117, UXI-119, UXI-120, UXI-121, UXI-135, UXI-136, UXI-137, UXI-138, UXI-141, UXI-142, UXI-143, UXI-144, UXI-145, UXI-146, UXI-147, UXI-148, UXI-224, UXI-306, UXI-307, UXI-340, UXI-379 | — |
| CAP-23 | UXP-206, UXP-207, UXP-604 | UXI-140, UXI-149, UXI-150, UXI-151, UXI-152, UXI-153, UXI-154, UXI-155, UXI-156, UXI-157, UXI-158, UXI-340 | — |
| CAP-24 | UXP-202, UXP-203, UXP-204 | UXI-118, UXI-122, UXI-123, UXI-124, UXI-125, UXI-126, UXI-127, UXI-128, UXI-129, UXI-130, UXI-131, UXI-132, UXI-133, UXI-134 | — |
| CAP-25 | UXP-000, UXP-601, UXP-602, UXP-603, UXP-604, UXP-606 | UXI-008, UXI-326, UXI-332, UXI-333, UXI-335, UXI-336, UXI-337, UXI-338, UXI-339, UXI-343, UXI-354, UXI-355, UXI-356, UXI-357 | — |
| CAP-26 | UXP-604, UXP-606 | UXI-342, UXI-344, UXI-354, UXI-355, UXI-356, UXI-357 | — |
| CAP-27 | UXP-003, UXP-005, UXP-602, UXP-604, UXP-605 | UXI-046, UXI-051, UXI-052, UXI-053, UXI-054, UXI-334, UXI-341, UXI-349, UXI-350, UXI-351, UXI-352, UXI-353 | — |
| CAP-28 | UXP-602, UXP-604 | UXI-332, UXI-334, UXI-345, UXI-346 | — |
| CAP-29 | UXP-604 | UXI-347 | — |
| CAP-30 | UXP-601, UXP-607, UXP-608 | UXI-327, UXI-358, UXI-359, UXI-360, UXI-361, UXI-362, UXI-364, UXI-365, UXI-366 | — |
| CAP-31 | UXP-601, UXP-609, UXP-610 | UXI-328, UXI-367, UXI-368, UXI-369, UXI-370, UXI-371, UXI-372, UXI-373 | — |
| CAP-32 | UXP-000, UXP-007, UXP-601, UXP-611, UXP-612, UXP-613 | UXI-008, UXI-064, UXI-329, UXI-330, UXI-331, UXI-374, UXI-376, UXI-377, UXI-378, UXI-379, UXI-380, UXI-381, UXI-382, UXI-383, UXI-384, UXI-385 | — |
| CAP-33 | None | None | Operator procedures only (D-048; SECURITY_MODEL section 14) |
| CAP-34 | UXP-000, UXP-006, UXP-101, UXP-102, UXP-104, UXP-105, UXP-203, UXP-207, UXP-305, UXP-308, UXP-405, UXP-408, UXP-410, UXP-411, UXP-412, UXP-413, UXP-504 | UXI-025, UXI-027, UXI-029, UXI-036, UXI-037, UXI-055, UXI-056, UXI-057, UXI-076, UXI-085, UXI-086, UXI-099, UXI-102, UXI-109, UXI-130, UXI-156, UXI-196, UXI-211, UXI-242, UXI-262, UXI-274, UXI-282, UXI-290, UXI-298, UXI-321 | The Owner's resolution of another user's intent ("Y, any") is designed with the workflow recovery contracts in RF-5 (ADR-007 section 5) |

## 9. Requirement coverage (G1-04)

Every requirement of [REQUIREMENTS](../01-product/REQUIREMENTS.md) either maps to at least one page or states why it has no interface.

| Requirement | Pages | Note |
| --- | --- | --- |
| REQ-SCOPE-01 | Interface consequence only | No page or control offers a tenant, outlet, warehouse or location (INFORMATION_ARCHITECTURE section 1) |
| REQ-SCOPE-02 | All pages | No control of a capability outside V1 exists or looks active (INTERACTION_INVENTORY section 3) |
| REQ-SCOPE-03 | UXP-607 | — |
| REQ-ACC-01 | UXP-602 | — |
| REQ-ACC-02 | UXP-603, UXP-605 | — |
| REQ-ACC-03 | UXP-000 | — |
| REQ-ACC-04 | All pages | Each interaction's "visible to" column; the server's allowlists are RF-5 and SECURITY_MODEL section 7 |
| REQ-ACC-05 | UXP-001, UXP-002, UXP-003, UXP-004, UXP-603, UXP-606 | — |
| REQ-ACC-06 | UXP-003, UXP-603, UXP-604, UXP-605 | — |
| REQ-ACC-07 | UXP-000, UXP-001 | — |
| REQ-ACC-08 | UXP-005, UXP-203, UXP-207, UXP-305, UXP-410, UXP-411, UXP-415, UXP-504, UXP-603, UXP-604, UXP-605, UXP-608, UXP-610 | — |
| REQ-ACC-09 | No interface | Server ordering of guards; the interface shows its outcomes as UXS-17 and UXS-09 |
| REQ-ACC-10 | UXP-601, UXP-602, UXP-604 | — |
| REQ-ACC-11 | UXP-604 | — |
| REQ-ACC-12 | UXP-602, UXP-604 | — |
| REQ-CFG-01 | UXP-601, UXP-607, UXP-608 | — |
| REQ-CFG-02 | UXP-607 | — |
| REQ-CFG-03 | UXP-608 | — |
| REQ-CFG-04 | UXP-601, UXP-609, UXP-610 | — |
| REQ-CAT-01 | UXP-301, UXP-302, UXP-303, UXP-304, UXP-307 | — |
| REQ-CAT-02 | UXP-303 | — |
| REQ-CAT-03 | UXP-303 | — |
| REQ-CAT-04 | UXP-302, UXP-303 | — |
| REQ-CAT-05 | UXP-302, UXP-303, UXP-305 | — |
| REQ-CAT-06 | UXP-304 | — |
| REQ-CAT-07 | UXP-101, UXP-301 | — |
| REQ-CAT-08 | UXP-304 | — |
| REQ-CAT-09 | UXP-308 | — |
| REQ-CAT-10 | UXP-101, UXP-302 | — |
| REQ-CAT-11 | UXP-302, UXP-306 | — |
| REQ-CAT-12 | UXP-301, UXP-302, UXP-304, UXP-307 | — |
| REQ-CAT-13 | UXP-302, UXP-305 | — |
| REQ-SCAN-01 | UXP-101 | — |
| REQ-SCAN-02 | UXP-101 | — |
| REQ-SCAN-03 | UXP-101 | — |
| REQ-SCAN-04 | UXP-101 | — |
| REQ-INV-01 | UXP-402, UXP-403 | — |
| REQ-INV-02 | UXP-408, UXP-410 | — |
| REQ-INV-03 | UXP-405, UXP-408 | — |
| REQ-INV-04 | UXP-404, UXP-405, UXP-406 | — |
| REQ-INV-05 | UXP-405, UXP-406 | — |
| REQ-INV-06 | UXP-407, UXP-408, UXP-409 | — |
| REQ-INV-07 | UXP-412, UXP-413 | — |
| REQ-INV-08 | UXP-410 | — |
| REQ-INV-09 | UXP-403, UXP-409, UXP-411 | — |
| REQ-INV-10 | UXP-007, UXP-401 | — |
| REQ-INV-11 | UXP-401, UXP-402 | — |
| REQ-INV-12 | UXP-414, UXP-415, UXP-611 | — |
| REQ-INV-13 | UXP-402, UXP-405 | — |
| REQ-COST-01 | UXP-505 | — |
| REQ-COST-02 | Display only | ADAPTIVE_AND_ACCESSIBILITY UXA-16 |
| REQ-COST-03 | UXP-405, UXP-413, UXP-503 | — |
| REQ-COST-04 | UXP-403, UXP-406, UXP-505 | — |
| REQ-COST-05 | UXP-403, UXP-503, UXP-504 | — |
| REQ-COST-06 | UXP-504 | — |
| REQ-COST-07 | UXP-203 | Goods condition per line decides whether cost is restored; the computation is the server's |
| REQ-COST-08 | UXP-411 | — |
| REQ-COST-09 | UXP-502, UXP-504, UXP-505 | — |
| REQ-COST-10 | No interface | Recorded by the server in the Sale transaction |
| REQ-SALE-01 | UXP-101, UXP-104 | — |
| REQ-SALE-02 | UXP-101 | — |
| REQ-SALE-03 | UXP-101, UXP-102 | Line totals and the total shown are the server's rounding (BR-SALE-06) |
| REQ-SALE-04 | UXP-102, UXP-103 | — |
| REQ-SALE-05 | UXP-102 | — |
| REQ-SALE-06 | UXP-101, UXP-102 | — |
| REQ-SALE-07 | UXP-102 | — |
| REQ-SALE-08 | UXP-006, UXP-102 | — |
| REQ-SALE-09 | UXP-102 | — |
| REQ-SALE-10 | UXP-501 | Computed by the server; shown through the daily summary |
| REQ-SALE-11 | UXP-103 | — |
| REQ-SALE-12 | UXP-201, UXP-202 | — |
| REQ-PAY-01 | UXP-102 | — |
| REQ-PAY-02 | UXP-102 | — |
| REQ-PAY-03 | UXP-102 | — |
| REQ-PAY-04 | UXP-102, UXP-501, UXP-502 | — |
| REQ-PAY-05 | UXP-102 | Only CASH and BANK_TRANSFER appear; no provider placeholder (D-077) |
| REQ-DOC-01 | UXP-103, UXP-202 | — |
| REQ-DOC-02 | UXP-103 | — |
| REQ-DOC-03 | UXP-103, UXP-202 | — |
| REQ-DOC-04 | UXP-201, UXP-202, UXP-206 | — |
| REQ-DOC-05 | UXP-103, UXP-501 | — |
| REQ-CASH-01 | UXP-609 | — |
| REQ-CASH-02 | UXP-104 | — |
| REQ-CASH-03 | UXP-104 | — |
| REQ-CASH-04 | UXP-202, UXP-206 | Each Sale shows its session; each session lists its Sales' totals |
| REQ-CASH-05 | UXP-105 | — |
| REQ-CASH-06 | UXP-105, UXP-207 | — |
| REQ-CASH-07 | UXP-105 | — |
| REQ-CASH-08 | UXP-105, UXP-207 | — |
| REQ-CASH-09 | UXP-206 | — |
| REQ-CASH-10 | UXP-104, UXP-105 | A new session for each work period; handover is close then open (UXS-48) |
| REQ-CASH-11 | UXP-206, UXP-207 | — |
| REQ-CASH-12 | UXP-205, UXP-206 | — |
| REQ-CASH-13 | UXP-105 | — |
| REQ-RFD-01 | UXP-203 | — |
| REQ-RFD-02 | UXP-203 | — |
| REQ-RFD-03 | UXP-203, UXP-204 | — |
| REQ-RFD-04 | UXP-203 | — |
| REQ-RFD-05 | UXP-203 | — |
| REQ-RFD-06 | UXP-203 | — |
| REQ-RFD-07 | UXP-202 | — |
| REQ-RFD-08 | UXP-203 | — |
| REQ-RFD-09 | UXP-006, UXP-203, UXP-204 | — |
| REQ-RPT-01 | UXP-502 | — |
| REQ-RPT-02 | UXP-502 | — |
| REQ-RPT-03 | UXP-502, UXP-503 | — |
| REQ-RPT-04 | UXP-007, UXP-206, UXP-501 | — |
| REQ-RPT-05 | UXP-201, UXP-205, UXP-402, UXP-501 | — |
| REQ-RPT-06 | UXP-502 | — |
| REQ-AUD-01 | UXP-612 | — |
| REQ-AUD-02 | UXP-601, UXP-612, UXP-613 | — |
| REQ-CMD-01 | UXP-006 | — |
| REQ-CMD-02 | Pages of critical mutations, each with a review step | UXP-104, UXP-105, UXP-203, UXP-207, UXP-305, UXP-308, UXP-405, UXP-408, UXP-410, UXP-411, UXP-412, UXP-413, UXP-504; the payment screen UXP-102 is the one exception (D-040). Master-data, configuration and user-management pages are not critical mutations (ADR-007 section 1) and confirm without a review step |
| REQ-CMD-03 | UXP-006 | UXS-15 (EPOCH_STALE; "Cek status" stays available and returns the outcome of the restore reconciliation, D-073), UXS-09 and UXS-50 after a restore; the restore itself is RF-6 |
| REQ-CMD-04 | No interface | Server retries; the interface stays in UXS-06 or UXS-14 |
| REQ-OPS-01 | UXP-611 | Backup status only; the backup itself is RF-6 |
| REQ-OPS-02 | No interface | Production data safety (PRODUCTION_DATA_SAFETY) |
| REQ-OPS-03 | No interface | Release boundary (PRODUCT_BLUEPRINT section 12) |
| REQ-OPS-04 | UXP-007, UXP-601, UXP-611 | — |
| REQ-OPS-05 | All pages | No deletion control for business records or master data (INTERACTION_INVENTORY section 3) |
| REQ-OPS-06 | No interface | Staging environment |
| REQ-NFR-01 | UXP-000 | — |
| REQ-NFR-02 | UXP-101 to UXP-105 | ADAPTIVE_AND_ACCESSIBILITY UXA-01, UXA-25 |
| REQ-NFR-03 | No page | Gate DEVICES; ADAPTIVE_AND_ACCESSIBILITY UXA-07, UXA-14, UXA-23 |
| REQ-NFR-04 | No page | Performance targets; the interface reports slowness as UXS-06 ("masih diproses") |
| REQ-NFR-05 | No interface | Recovery point and time (question 18) |
| REQ-NFR-06 | No interface | Recurring cost |
| REQ-NFR-07 | UXP-001, UXP-002, UXP-308, UXP-613 | — |
| REQ-NFR-08 | No interface | Data integrity on the server |
| REQ-NFR-09 | Display only | ADAPTIVE_AND_ACCESSIBILITY UXA-16 |
| REQ-NFR-10 | UXP-103 | No tax line on any receipt or document |
| REQ-UX-01 | All pages | These six documents are the P7-G1 artifacts; G2 and G3 follow (D-025) |
| REQ-UX-02 | UXP-000 | — |
| REQ-UX-03 | All pages | ADAPTIVE_AND_ACCESSIBILITY (exact targets) |

Requirements: **134**; with at least one page or "all pages": 120; with no interface of their own: 14.

## 10. Journeys (G1-04)

| Journey | Name | Path through the pages |
| --- | --- | --- |
| J-01 | Open a cash session | UXP-001 → "Kasir" → UXP-104 (choose register, opening cash) → review dialog → "Buka sesi" → UXP-101 |
| J-02 | Sell | UXP-101 (scan or search; quantities) → "Bayar" → UXP-102 (CASH or BANK_TRANSFER; one final action) → UXP-103 (receipt; print) → next sale; reprint later from UXP-202 |
| J-03 | Close a cash session | UXP-101 → "Tutup sesi kas" → UXP-105 (preview → count → review → "Tutup sesi"; recount on UXS-19) → UXP-206 (report; print; CSV). Owner-assisted: UXP-206 → UXP-207 → UXP-206 |
| J-04 | Add products | UXP-301 → "Tambah produk" → UXP-303 (search first) → UXP-302 (price, barcodes, photo) → UXP-306 (labels); or UXP-308 (CSV) → UXP-301 |
| J-05 | Receive, issue, follow stock attention | UXP-404 → UXP-405 → review → UXP-406; UXP-407 → UXP-408 → review → UXP-409; UXP-401 and the Stok badge |
| J-06 | Onboard the shop | UXP-301 and UXP-303 (products) → UXP-412 ("Mulai masa input stok awal") → UXP-413 (rows; review; confirm) → UXP-412 (checklist; "Akhiri masa input stok awal") |
| J-07 | Correct | Cost: UXP-503 → UXP-504; adjustment: UXP-402 → UXP-410; reversal: UXP-403, UXP-406 or UXP-409 → UXP-411; refund and return: UXP-201 → UXP-202 → UXP-203 → UXP-204 |
| J-08 | Oversee | UXP-007; UXP-501; UXP-502; UXP-205 and UXP-206; UXP-611; UXP-612; UXP-613; users UXP-602 to UXP-606; BusinessProfile UXP-607 and UXP-608; cash registers UXP-609 and UXP-610 |

## 11. The former design candidate

The candidate of D-024 is not approved and is not a baseline (REQ-UX-01). Its destinations and dialogs are traced here so that no capability disappears silently.

### 11.1 Destinations

| Role | Former destination | New place |
| --- | --- | --- |
| Owner | Dashboard | UXP-007 Beranda; UXP-501 Rekap harian |
| Owner | Katalog barang | UXP-301 Produk (with UXP-302 to UXP-308) |
| Owner | Stok & riwayat | UXP-401 Stok; UXP-402 Riwayat stok |
| Owner | Barang masuk | UXP-404 to UXP-406, under Stok |
| Owner | Barang keluar | UXP-407 to UXP-409, under Stok (the Owner's explicit entry, D-018) |
| Owner | Kasir POS | UXP-101 to UXP-105 Kasir |
| Owner | Penjualan | UXP-201, UXP-202; refunds UXP-203, UXP-204 |
| Owner | Keuangan | UXP-502 Laporan keuangan and UXP-503 to UXP-505, under Laporan; corrections moved to the records they correct |
| Owner | Sesi kas | UXP-205 to UXP-207, under Laporan |
| Owner | Audit & sistem | UXP-611 to UXP-613, under Pengaturan |
| Owner | Pengaturan | UXP-601 to UXP-610 |
| Cashier | Kasir POS | UXP-101 to UXP-105 Kasir |
| Cashier | Transaksi saya | UXP-201 Penjualan (own scope); one name for every role |
| Cashier | Sesi saya | UXP-205 Sesi kas (own scope); one name for every role |
| All | Account; opening stock; sign-in | UXP-003 to UXP-005; UXP-412 and UXP-413; UXP-001 and UXP-002 |

### 11.2 Dialogs

The re-foundation analysis (section 20) counted 19 dialog types reachable by clicking. The candidate's source opens 27 titled dialogs, two of them with a variant title (29 titles in all), and 9 review dialogs through its review helper, two of them with variants (12 titles in all). All are traced below and in section 11.3, so the 19 are covered. Two dialogs of the unrelated export-template pages in the same file are outside the candidate.

| Former dialog title | New place |
| --- | --- |
| (product name) — product detail | UXP-302 page |
| Tambah barang / Edit master barang | UXP-303 and UXP-304 pages |
| Label barang | UXP-306 page |
| Kategori, brand & unit | UXP-307 page; brand and unit are product fields |
| Buka sesi kas | UXP-104 page with a review dialog; the mandatory checkbox is removed |
| Tutup sesi kas | UXP-105 page |
| Assisted close oleh Pemilik | UXP-207 page |
| Atur jumlah barang | The quantity field of a cart line on UXP-101; no dialog |
| Pembayaran penuh | UXP-102 payment screen; one final action, no checkbox, no second dialog (D-040) |
| Snapshot receipt original | UXP-103 Struk; reprint on UXP-202 |
| Laporan sesi as-closed | UXP-206 Detail sesi kas |
| Reversal sumber original / Koreksi count fisik | UXP-411 Pembatalan mutasi / UXP-410 Penyesuaian stok |
| Koreksi penjualan | UXP-203 Pengembalian uang dan retur |
| Lengkapi bukti biaya | UXP-504 Koreksi biaya |
| Pilih periode laporan | The period controls of UXP-502 |
| Ekspor laporan Pemilik | Not carried: no CURRENT requirement asks for a finance export (REQUIREMENTS; COSTING section 9); the session CSV export stays on UXP-206 (REQ-CASH-12). A finance export would be a new scope decision |
| Batch count opening awal | UXP-413 Input stok awal |
| Batas opening atomik | A refusal state of UXP-413 (UXS-05, BR-INV-25) |
| Kelola akun pengguna | UXP-604 Detail pengguna |
| Undang pengguna | UXP-603 Tambah pengguna and UXP-606; SUPER_ADMIN is never offered (CAP-33) |
| Review keamanan akun | The reset-link and deactivation actions of UXP-604 with the shared recent-authentication dialog |
| Review revoke sesi | Not carried: no capability revokes one's own sessions directly; changing one's password ends the other sessions (SEC-AUTH-11) |
| Pengaturan akun (password change) | UXP-004 Ubah kata sandi (AICWDF-COMPAT-8, D-049) |
| Edit profil akun | UXP-005 for the Owner; staff names and emails are changed by the Owner (SEC-ACC-10) |
| Edit register | UXP-610 Form laci kas |
| Review recovery | Not carried as a page: restore is an operator procedure (SEC-OP-04, RF-6); its effects appear as UXS-15 and on UXP-611 |
| Original result belum terverifikasi | UXS-14 and UXS-15 with UXP-006 |

### 11.3 Review dialogs

| Former review title | New place |
| --- | --- |
| Tinjau batch opening | The review dialog of UXP-413 Input stok awal |
| Tinjau opening / Tinjau barang keluar / Tinjau barang masuk | The review dialogs of UXP-413, UXP-408 and UXP-405 |
| Tinjau pembukaan sesi | The review dialog of UXP-104 Buka sesi kas |
| Tinjau penutupan sesi | The review dialogs of UXP-105 Tutup sesi kas and UXP-207 Tutup sesi oleh Pemilik |
| Tinjau pembayaran final | Not carried: the payment screen UXP-102 is the one confirmation, with one final action and no second review (D-040, BR-SALE-14) |
| Tinjau fakta koreksi baru | The review dialogs of UXP-410 Penyesuaian stok and UXP-411 Pembatalan mutasi |
| Tinjau refund uang / Tinjau return fisik | The review dialog of UXP-203 Pengembalian uang dan retur |
| Tinjau revision profil bisnis | UXP-608 Ubah profil usaha, saved with recent authentication; configuration is not a critical mutation, so no separate review step (ADR-007 section 1) |
| Review onboarding freeze | The review of "Akhiri masa input stok awal" on UXP-412, with recent authentication (BR-INV-28) |

## 12. Legacy P7 coverage groups

The groups of `docs/07-ux-design/legacy/P7-COVERAGE.md` (LEGACY — SUPERSEDED by these documents, [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md)).

| Group | Name | New pages | Note |
| --- | --- | --- | --- |
| P7-G01 | Auth dan account | UXP-001, UXP-002, UXP-003, UXP-004, UXP-005; Owner user administration UXP-602 to UXP-606 | The first Owner account comes from the bootstrap operator procedure and has no page (SEC-OP-01). "Revoke sesi" as a separate action is not carried: sessions end through deactivation, role change or a password change (SEC-SESS-04) |
| P7-G02 | Shell, dashboard dan utilities | UXP-000, UXP-007 | Navigation reorganized by task (INFORMATION_ARCHITECTURE section 4) |
| P7-G03 | Catalog dan search-first | UXP-301, UXP-302, UXP-303, UXP-304, UXP-307 | Brand is a free-text product field and unit a product field (BR-CAT-10); there is no brand or unit list page |
| P7-G04 | Barcode dan internal label | UXP-302 (barcode section), UXP-305, UXP-306 | — |
| P7-G05 | Receiving | UXP-404, UXP-405, UXP-406 | "Write-only" cost is superseded by D-033 and D-047; drafts stay in page memory (D-069) |
| P7-G06 | Manual issue non-sale | UXP-407, UXP-408, UXP-409 | The Owner's explicit entry is the Stok sub-navigation item "Barang keluar" (D-018) |
| P7-G07 | Opening awal | UXP-412, UXP-413 | Correction of an onboarding entry is pending T-17 b and intentionally hidden |
| P7-G08 | Current stock, restock dan history | UXP-401, UXP-402, UXP-403; the Stok badge in UXP-000 | — |
| P7-G09 | Register dan own session entry | UXP-104; cash registers UXP-609, UXP-610 | — |
| P7-G10 | POS draft, scanner dan cart | UXP-101 | One line per product (D-067); no undo control (not required by a CURRENT rule); quantity edit and removal remain |
| P7-G11 | Full payment, result dan recovery | UXP-102, UXP-103, UXP-006 | STATIC_QRIS added (D-036) and removed again (D-077); the review dialog and checkbox are removed (D-040) |
| P7-G12 | Close, own history dan immutable report | UXP-105, UXP-201, UXP-202, UXP-205, UXP-206, UXP-207 | Review notes append-only (D-069) |
| P7-G13 | Owner stock/cost correction | UXP-410, UXP-411, UXP-503, UXP-504, UXP-505 | Corrections are reached from the record they correct, not from the finance report |
| P7-G14 | Refund dan physical return | UXP-203, UXP-204 | Source of money and the D-068 instruction added |
| P7-G15 | Finance, periods, audit dan scoped reports | UXP-501, UXP-502, UXP-612; staff scope through UXP-201, UXP-205, UXP-402 | — |
| P7-G16 | Runtime identity, config dan system status | UXP-607, UXP-608, UXP-609, UXP-610, UXP-611 | Payment-method configuration is not carried: the methods are a fixed list checked by the server (BR-PAY-07, TR-VAL-04) |
| P7-G17 | Original documents dan exports | UXP-103, UXP-202 (receipt print and reprint), UXP-206 (session report print and CSV), UXP-306 (labels) | — |
| P7-G18 | Incident dan recovery state | UXP-006, UXP-414, UXP-415, UXP-611; UI_STATES UXS-14, UXS-15, UXS-16 | Restore and the recovery epoch are operator procedures (SEC-OP-04, RF-6); no page |

## 13. Owner decisions applied

| Decision | Where it shows |
| --- | --- |
| D-067 (question 35, option A): one Sale line per product | UXP-101: a product already in the cart gets one more unit on its line, which is highlighted; UXP-102 and UXP-103 show one line per product; UXP-203 offers one line per product |
| D-068 (question 29, option A) | UXP-204: the instruction to take the cash now; UXP-105 and UXP-207 step 1: the drawer-funded refunds with the reminder; UXP-206: refunds beside the variance and "Tambah catatan tinjauan" for the Owner |
| D-069 (T-17 a): receiving drafts in page memory | No draft page; UXP-405 has the unsaved-changes guard and the advice to split a large receiving |
| D-069 (T-17 d): deactivation with an OPEN session | UXP-604: "Nonaktifkan" enabled; the OPEN session and "Tutup sesi oleh Pemilik" shown |
| D-069 (T-17 f): append-only review notes | UXP-206: "Tambah catatan tinjauan" may be used again; no edit or delete control |
| D-069 (T-17 h): category names in CSV import | UXP-308: row errors for a missing or ambiguous active category name; confirmation disabled; no category is created by the import |
| D-071 (question 36, finding F-03, option A): at most one onboarding period per business-data lifecycle | UXP-412: "Mulai masa input stok awal" only while onboarding has never been released; the release review states that the period cannot be started again; after the release the page is read only. Later corrections: UXP-410, UXP-411, UXP-504. The backend enforcement is RF-5 |
| D-072 (question 37): onboarding and a restore of earlier business data | UXP-412: once onboarding has been released, a restore of an older backup offers neither a new start nor a continuation; a period never released may continue as the same original period; while the restored onboarding state cannot be verified, UXS-50 and no start, entry or release until the Owner's restore reconciliation. Enforcement and recovery: RF-5, RF-6 |
| D-073 (finding M-2): server-authoritative recovery after a restore | UXP-006 and every uncertain panel: "Cek status" stays available for a request refused as EPOCH_STALE and returns the outcome of the restore reconciliation; the reference is released only by a verified outcome or an audited reconciliation recorded with a safe fence (NAVIGATION_CONTRACTS NC-13 (e); UI_STATES UXS-15) |
| D-074 (finding m-1): duplicate actions across devices | NAVIGATION_CONTRACTS NC-13 (c) states that the block acts only in the browser that holds the request; the backend mitigation is proposed in RF-5 (OPEN_ITEMS N-07) |
| D-075: accepted G1 proposals | Section 4: the combined account's home on Stok without an OPEN session and no role switcher; UXP-102: "Tunai" chosen when the screen opens; ADAPTIVE_AND_ACCESSIBILITY UXA-02 and UXA-09: breakpoints and shortcuts, provisional |
| T-17 b: correction of an onboarding entry — OPEN | The controls are intentionally hidden on UXP-403 (reversal of an OPENING while onboarding is on) and UXP-412; UXP-413 does not offer a product that already has a zero-count entry; pending the Owner's decision |

## 14. Left to later steps

- Final wording of every label, message and state (RF-4B, gate P7-G2).
- Visual design, the type scale, colours and the constrained accent set; key screens (RF-4C, gate P7-G3). The accent field of UXP-608 stays hidden until then.
- HTTP routes, methods, handlers and the full interaction contracts of AICWDF section 14 (RF-5, P-11): the route column of INTERACTION_INVENTORY says "RF-5".
- The backend enforcement of D-067 to D-069 listed in D-069's terms and the RF-4A package section 20 (RF-5).
- How the Owner resolves another user's uncertain intent, and the lost-reference rule (ADR-007 section 5, RF-5).
- The CSV column list and dialect (SEC-UP-05, RF-5); the document contract of receipts and the copy marker (RF-5).
- No V1 page offers an explicit zero cost to Ops or Combined: they mark the cost unknown and the Owner completes it on UXP-504 (BR-COST-04). SCHEMA section 5.8 allows for an Ops-entered zero that the Owner confirms and leaves that workflow to RF-5; offering it would need the Owner's decision and a change to these documents.
- T-17 b, and the application of D-069's deactivation rule to the Owner replacement procedure (SEC-OP-03), before RF-5.
- The race-safe and durable backend enforcement of D-071 — at most one onboarding period per business-data lifecycle, also after a reinstallation of the application or a server migration — with its tests, and the alignment of the onboarding rule texts (BR-INV-24; REQ-INV-07; DOMAIN_MODEL section 5; SCHEMA section 5.7; TECHNICAL_RULES section 7.2) (RF-5, D-071).
- The existing mechanisms that back NC-13 (source intent and fences, ADR-007), named in RF-5, and the backend mitigation, with its tests, of the gap of NC-13 on another device, in another browser or after cleared browser data (D-074; OPEN_ITEMS N-07). NC-13 adds no server refusal; a new one would first need the Owner's decision.
- The enforcement and recovery of D-072, with tests: a released onboarding stays released after a restore of an older backup, and a period that the restored data still show as running is closed without continuing; a period never released may continue as the same original period; an onboarding state that cannot be verified is held (UXS-50) until the Owner's reconciliation in the restore runbook (RF-5, RF-6).
- The server-authoritative recovery of requests refused as EPOCH_STALE (D-073), with tests: how a reconciliation outcome and its safe fence are recorded, audited and returned to "Cek status", including how this extends the resolution of ADR-007 section 4 beyond one recovery epoch, and how the restore runbook resolves the Owner's own references before re-applying the security changes of SECURITY_MODEL SEC-OP-04 (RF-5, RF-6).
- How BR-CASH-16 is met in an Owner-assisted close when the responsible user's unresolved checkouts are held in that user's browser: the close relies on the activity version (BR-CASH-13), and the recovery contracts of RF-5 decide the rest (ADR-007 section 5).
