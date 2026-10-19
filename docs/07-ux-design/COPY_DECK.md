# Copy deck

| | |
| --- | --- |
| Status | CURRENT — written in RF-4B under D-083 and corrected under D-084, 17 October 2026 (Asia/Jakarta); revised by TASK-002 under change control (section 22), 19 October 2026 (Asia/Jakarta) |
| Phase | P7 — terminology and copy (AICWDF sections 4B and 18; REQ-NFR-01) |
| Gate | P7-G2, terminology and copy (D-025): **APPROVED** by D-086, with [GLOSSARY](../02-domain/GLOSSARY.md), as committed in `551b4eb`. Revised by TASK-002 under change control (section 22), whose package the Owner approves by the hash of each artifact at gate GATE-CC1 ([TASK_PLAN](../11-tasks/TASK_PLAN.md) section 3) |
| Decisions | D-018, D-024, D-025, D-033, D-040, D-043, D-047, D-049, D-050, D-051, D-052, D-068, D-069, D-071, D-072, D-073, D-075, D-077, D-082, D-083, D-084, D-086, D-087, D-088 |
| Structure | The six documents of gate P7-G1, approved by D-082: [USER_TASKS](USER_TASKS.md), [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md), [UI_STATES](UI_STATES.md), [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) |
| Related | [GLOSSARY](../02-domain/GLOSSARY.md), [ADR-005](../adr/ADR-005-localization-architecture.md), [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 4, [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) |

Every word the shop sees in V1, in plain Indonesian: page titles and navigation, every control of every page, dialogs, states, error messages, recovery panels, disabled reasons, validation messages, documents and screen-reader announcements. Each text has a copy key, which names where it lives in the translation files of [ADR-005](../adr/ADR-005-localization-architecture.md).

This document adds no business rule and changes no page, control, state or navigation of the six P7-G1 documents. Those documents say which pages, controls and states exist, who sees them and where they lead; their Indonesian labels in quotation marks are working labels. This document gives the final wording, and section 19 lists every working label it changes, with the reason. Every message states a rule that already exists; the rule is cited where the text depends on it.

Agent-facing text here is English; the copy itself is Indonesian. Nothing here is visual design (RF-4C, gate P7-G3), an HTTP route or a document contract (RF-5).

## 1. Conventions

This section is the localization contract that ADR-005 item 7 asks RF-4 to write: how copy is keyed, filled and formatted. ADR-005 remains the decision on the mechanism; where the two meet, ADR-005 prevails.

### 1.1 Copy keys

- A key is a stable English dot key in `snake_case` (ADR-005 item 1). Its first segment names the translation file in `lang/id/`; the rest names the place and the text.
- Files: `ux` (the shell, shared actions, states, formats, validation and announcements), `access` (sign-in, account, users and one-time links), `commands` (unresolved requests and recovery), `sales` (till, payment, receipt, Sales, refunds and returns), `cash` (cash sessions, review notes, cash registers), `catalog` (products, categories, barcodes, labels, CSV import), `inventory` (stock, history, receiving, manual issue, adjustment, reversal, onboarding, incidents), `costing` (cost worklist, correction and history, cost fields), `reporting` (Owner home, daily summary, finance report), `config` (settings index, BusinessProfile, system status), `audit` (business audit log, security log) and `documents` (receipt, Nota Penjualan, session report, labels, CSV export headers).
- The state keys `ux.state.*` are those of [UI_STATES](UI_STATES.md) section 2. A state key is the parent of its texts: `ux.state.loading.title`, `ux.state.held.epoch_stale.body`.
- A key named in this document is created with exactly this name by the first interface Task that uses it. A text that this document does not name is added here by change control before it ships (PROJECT_RULES section 6); no Task invents shop-facing wording. A key, once implemented, is not renamed without a reason recorded in the Task.
- One key holds one complete text. Code never builds a sentence by joining keys; a variable part is a placeholder.

### 1.2 Placeholders

- Placeholders use the Laravel `:name` syntax (ADR-005 item 4) with Indonesian names that say what they hold: `:produk`, `:jumlah`, `:uang`, `:nomor`, `:waktu`, `:tanggal`, `:nama`, `:laci`, `:kode`, `:satuan`, `:maks`. `:waktu` holds a date with its time and `:tanggal` a date (section 1.3); `:jam` holds a time of day and `:durasi` a duration.
- A placeholder receives a value already formatted under section 1.3. Money and quantities never reach copy as raw numbers.
- A placeholder never carries a value the viewer's role may not see (TECHNICAL_RULES TR-ERR-02; SECURITY_MODEL SEC-CH-01).

### 1.3 Formats

| What | Format | Example | Source |
| --- | --- | --- | --- |
| Money | `Rp` directly followed by whole rupiah with a dot as thousands separator | Rp1.234.567; Rp0 | BR-NUM-01, UXA-16 |
| Negative money | The minus sign − before `Rp`, and words beside it where the sign carries meaning (a variance, section 1.4) | −Rp2.000 (kurang) | UXA-16, BR-CASH-12 |
| Quantity | Decimal comma, no more decimals than the product's quantity precision, dot thousands separator, followed by the product's unit when shown alone | 1.250,5 kg; 3 pcs | BR-NUM-02, UXA-16 |
| Cost on screen | Whole rupiah, rounded half-up at the end; the six-decimal value is never shown | Rp12.000 | BR-NUM-03 |
| Date and time | Two-digit day, full Indonesian month name, year, a comma and the time with dots | 01 Oktober 2026, 09.15.00 | D-018, BR-NUM-07, REQ-DOC-05 |
| Date | As above without the time | 01 Oktober 2026 | D-018 |
| Time of day | Hours and minutes with a dot | 13.45 | D-018, BR-NUM-07 |
| Time zone | Times are Asia/Jakarta. The zone is written, as "WIB", only where a rule asks for it: the as-of line of a report and the export of a session report | Data per 17 Oktober 2026, 13.45.00 WIB | BR-COST-38, BR-CASH-22 |
| Sale and cash session numbers | As stored | 20261005-000123 | D-062 |
| Months | Januari, Februari, Maret, April, Mei, Juni, Juli, Agustus, September, Oktober, November, Desember | — | D-018 |
| Days | Senin, Selasa, Rabu, Kamis, Jumat, Sabtu, Minggu | — | — |
| Durations | A number and a unit word | 60 menit; 30 detik | — |
| Counts | A number and the counted noun, no plural form | 3 produk; 12 baris | ADR-005 item 4 |
| Percentage | One decimal with a decimal comma, directly followed by the percent sign | 32,0% | DESIGN_SYSTEM section 13; UXA-16 |

Month and day names live under `ux.format.month.*` and `ux.format.day.*` (ADR-005 item 6). Formatting never depends on the browser's or the server's locale data.

### 1.4 Variance words

| Key | Copy | When |
| --- | --- | --- |
| `ux.format.variance_short` | kurang | Physical cash below expected cash: the variance is negative |
| `ux.format.variance_over` | lebih | Physical cash above expected cash: the variance is positive |
| `ux.format.variance_even` | sesuai | The variance is Rp0 |
| `ux.format.variance` | :uang (:keterangan) | For example −Rp2.000 (kurang); Rp5.000 (lebih); Rp0 (sesuai) |

The word is always shown with the amount, so the meaning never rests on colour or sign alone (UXA-16, UXA-18).

### 1.5 Precedence

- For wording, this document and [GLOSSARY](../02-domain/GLOSSARY.md) prevail over the working labels of the six P7-G1 documents and over Indonesian labels written in other CURRENT documents. The rule those documents state is unchanged.
- An Indonesian label written in an Owner decision keeps the decided form (section 2.2).
- This document and GLOSSARY agree: GLOSSARY gives the term, this document the sentence that uses it. A difference between them is a defect, corrected by change control; until then GLOSSARY gives the term.
- Gate P7-G2 approved this wording (D-086). A later change is approved by hash through change control (section 22).

## 2. Writing rules

### 2.1 Rules

| ID | Rule |
| --- | --- |
| CW-01 | **Plain Indonesian.** Short sentences in everyday words, addressed to the user as "Anda". The established shop words "scan", "barcode", "SKU", "CSV", "email", "input", "restok" and "transfer" are used; their formal replacements ("pindai", "kode batang", "surel") are not. |
| CW-02 | **One concept, one term.** Every concept uses its GLOSSARY term and no synonym. The words of GLOSSARY section 13 never appear. |
| CW-03 | **Sentence case.** Only the first word and names are capitalized. Role names are written Pemilik, Admin Operasional and Kasir when they name the role. A page title keeps its capital letter wherever it appears inside other copy ("Kembali ke Detail produk"). Labels worded in a decision keep the decided form (section 2.2). |
| CW-04 | **One name per destination** (INFORMATION_ARCHITECTURE section 1). A navigation item, a list row link and a button that only opens a page use that page's title; for a task page the title is a verb phrase ("Catat barang masuk"). A back action is "Kembali ke " followed by the destination's title, or "Kembali ke halaman awal" for the role's home (NC-01, NC-12); a step back inside one page names the step ("Kembali ke pratinjau", "Kembali ubah"). A button inside a dialog, a banner or a blocked state that opens a page starts with "Buka" ("Buka Permintaan belum pasti"); a call to action in a blocked state or inside a message may say the task instead ("Selesaikan pembayaran yang belum pasti", "Buat kategori di halaman Kategori"). An attention item on Beranda names the condition it reports ("Perlu restok") and opens the page where it is handled. A page with two modes takes the title of the control that opened it: UXP-504 (Lengkapi biaya or Koreksi biaya) and UXP-610 (Tambah laci kas or Ubah laci kas). |
| CW-05 | **Buttons say what happens.** A button starts with a verb and names its action; the final action of a review names the command ("Konfirmasi barang masuk"). A business action is never labelled "OK", "Ya", "Submit" or "Proses"; the one use of "kirim" is "Kirim ulang", which repeats the same request (UXS-14). A cancel is "Batal". |
| CW-06 | **Messages: what happened, then what to do.** A definite refusal says that nothing was saved ("Tidak ada yang disimpan"). An uncertain outcome is never called a failure: it says "belum pasti", that the request may already be saved, and what must not happen meanwhile (UI_STATES section 1). |
| CW-07 | **Confirmations state the consequence**, including when it cannot be undone or when something stays as it is; the confirm button repeats the action's verb. |
| CW-08 | **Nothing is revealed** outside the viewer's scope: one uniform text for every sign-in, one-time link and password-confirmation failure (TR-ERR-04, SEC-AUTH-08); one text for "not found" and "outside your scope" (TR-ERR-03); no cost, reason of a cost correction, cost of goods sold, profit, margin or inventory value in any text a staff role sees (D-033, D-047, SEC-CH-01). |
| CW-09 | **No blame, no alarm.** No exclamation mark, no text in capitals on screen, no emoji. A field error says what is needed, not that the user was wrong. |
| CW-10 | **Disabled means explained.** Every disabled control shows its reason as a sentence (UXS-13, section 15). |
| CW-11 | **Accessible names** contain the visible label; where a shortcut exists it follows in parentheses: "Bayar (F8)". The shortcuts are provisional (D-075, UXA-09); section 5.1 lists them. A row action names its row in the accessible name: "Ubah laci kas Laci 1". |
| CW-12 | **Hints never replace labels.** A placeholder inside a field is a hint; the label stays visible. Examples use synthetic stationery goods only (PRODUCT_BLUEPRINT section 4). |
| CW-13 | **Nothing outside V1** appears in any text (REQ-SCOPE-02): no discount, price override, split, partial or deposit payment, credit, QRIS, card, e-wallet, payment gateway, order, shift, paid-in or paid-out, stock-taking, XLSX, camera scan, language switch, registration, password recovery by email, two-factor or Google sign-in. The only payment methods named are Tunai and Transfer bank (D-077). |
| CW-14 | **The shop's identity, never the codename.** "BantuKasir" never appears in the shop interface or on documents; the shop name comes from the BusinessProfile (REQ-SCOPE-03, BR-DOM-11). |
| CW-15 | **Amounts are never shortened** (no "1,2 jt") and never truncated (UXA-15). |

### 2.2 Labels worded in a decision

These labels keep the form the decision gives them, also where CW-03 would write them otherwise.

| Label | Decision or rule | Use |
| --- | --- | --- |
| Uang Pas | D-040, BR-SALE-11 | Exact-cash action |
| Selesaikan | D-040 | Final action, Tunai |
| Dana sudah diterima — Selesaikan | D-040, BR-SALE-12 | Final action, Transfer bank |
| Tunai; Transfer bank | D-075, D-077 | The two payment methods |
| Biaya perlu dilengkapi pemilik | D-033, BR-COST-03 | An UNKNOWN cost |
| Dikoreksi Pemilik | D-047, BR-INV-15 | The Owner's correction mark |
| Barang keluar | D-018 | Manual issue |
| Salah pasang | D-051 ("salah pasang") | The misassignment reason of a barcode retirement |
| Ada transaksi baru; hitung ulang kas | BR-CASH-14 | Recount required |
| Tetap buat produk baru | BR-CAT-04 | Create after a similar-product warning |
| Data biaya belum lengkap | BR-COST-38 | Incomplete cost figures |
| Hari Ini; 7 Hari; Bulan Ini; Bulan Lalu; Rentang Kustom | BR-COST-37 | Report periods |
| Mulai masa input stok awal | D-071 | Start of onboarding |
| Belum Terverifikasi | REQ-OPS-04 (L-09) | A system-status item not proven by evidence |
| Nota Penjualan | BR-DOC-02 | Title of the A4 rendering of a receipt |

## 3. Roles and people

| Key | Copy | Note |
| --- | --- | --- |
| `access.role.super_admin` | Pemilik | SUPER_ADMIN. Never offered in a form (CAP-33) |
| `access.role.operations_admin` | Admin Operasional | OPERATIONS_ADMIN |
| `access.role.cashier` | Kasir | CASHIER. The till page has the same name (section 4); the context, a role or a page, keeps them apart |
| `access.role.list` | :peran | The roles of one account joined by a comma, in the order Kasir, Admin Operasional; a combined account shows "Kasir, Admin Operasional" (D-032) |
| `access.role.super_admin.description` | Pemilik toko. Mengatur pengguna, laci kas, profil usaha, laporan, koreksi, dan pengembalian uang. | Shown on Akun saya (UXP-003) |
| `access.role.operations_admin.description` | Mengelola produk, kategori, barcode, barang masuk, barang keluar, dan stok. | Shown with the role choice of UXP-603 and UXP-605 |
| `access.role.cashier.description` | Menjual dengan sesi kas miliknya sendiri. | As above |
| `access.role.choice_help` | Pilih Kasir, Admin Operasional, atau keduanya. | UXI-336, UXI-350 |
| `access.user.status.active` | Aktif | ACTIVE |
| `access.user.status.deactivated` | Nonaktif | DEACTIVATED |
| `access.user.status.anonymized` | Dianonimkan | ANONYMIZED |
| `access.user.anonymized_name` | Pengguna dianonimkan :nomor | The neutral display name of SEC-ACC-09; `:nomor` is derived from the internal identity (its derivation: RF-5). Historical records keep the name they recorded (BR-USR-12) |
| `access.user.anonymized_email` | Tidak ada (akun dianonimkan) | Shown in place of the unusable placeholder address of SEC-ACC-09, which is never displayed |
| `access.user.holds_session` | Memegang sesi kas | UXI-334 |
| `ux.actor.system` | Sistem | An event without a user, for example the daily reconciliation |
| `ux.actor.operator` | Prosedur operator | An operator procedure (SECURITY_MODEL section 14) |

## 4. Navigation and page titles

### 4.1 Page titles

The title is the level-1 heading of the page and the first part of the browser's document title (UXA-13). Navigation items, sub-navigation items and links to a page use the same key (CW-04). "Changed" refers to the working label of INFORMATION_ARCHITECTURE section 5; section 19 gives the reasons.

| Page | Key | Title | Changed |
| --- | --- | --- | --- |
| UXP-000 | — | No title: the shell frames every protected page | — |
| UXP-001 | `access.sign_in.title` | Masuk | — |
| UXP-002 | `access.set_password.title` | Atur kata sandi | — |
| UXP-003 | `access.account.title` | Akun saya | — |
| UXP-004 | `access.change_password.title` | Ubah kata sandi | — |
| UXP-005 | `access.owner_details.title` | Ubah nama dan email | — |
| UXP-006 | `commands.unresolved.title` | Permintaan belum pasti | — |
| UXP-007 | `reporting.home.title` | Beranda | — |
| UXP-101 | `sales.till.title` | Kasir | — |
| UXP-102 | `sales.payment.title` | Pembayaran | — |
| UXP-103 | `sales.receipt.title` | Struk | — |
| UXP-104 | `cash.open.title` | Buka sesi kas | — |
| UXP-105 | `cash.close.title` | Tutup sesi kas | — |
| UXP-201 | `sales.list.title` | Penjualan | — |
| UXP-202 | `sales.detail.title` | Detail penjualan | — |
| UXP-203 | `sales.refund.title` | Pengembalian uang dan retur | — |
| UXP-204 | `sales.refund_result.title` | Pengembalian tercatat | — |
| UXP-205 | `cash.sessions.title` | Sesi kas | — |
| UXP-206 | `cash.session_detail.title` | Detail sesi kas | — |
| UXP-207 | `cash.assisted_close.title` | Tutup sesi oleh Pemilik | — |
| UXP-301 | `catalog.products.title` | Produk | — |
| UXP-302 | `catalog.product_detail.title` | Detail produk | — |
| UXP-303 | `catalog.product_create.title` | Tambah produk | — |
| UXP-304 | `catalog.product_edit.title` | Ubah produk | — |
| UXP-305 | `catalog.barcode_correction.title` | Koreksi salah pasang barcode | — |
| UXP-306 | `catalog.labels.title` | Cetak label barcode | — |
| UXP-307 | `catalog.categories.title` | Kategori | — |
| UXP-308 | `catalog.csv_import.title` | Impor produk (CSV) | — |
| UXP-401 | `inventory.stock.title` | Stok | — |
| UXP-402 | `inventory.history.title` | Riwayat stok | — |
| UXP-403 | `inventory.movement.title` | Detail mutasi stok | — |
| UXP-404 | `inventory.receipts.title` | Barang masuk | — |
| UXP-405 | `inventory.receipt_new.title` | Catat barang masuk | — |
| UXP-406 | `inventory.receipt_detail.title` | Detail barang masuk | — |
| UXP-407 | `inventory.issues.title` | Barang keluar | — |
| UXP-408 | `inventory.issue_new.title` | Catat barang keluar | — |
| UXP-409 | `inventory.issue_detail.title` | Detail barang keluar | — |
| UXP-410 | `inventory.adjustment.title` | Sesuaikan stok | Yes — was "Penyesuaian stok" |
| UXP-411 | `inventory.reversal.title` | Batalkan mutasi | Yes — was "Pembatalan mutasi" |
| UXP-412 | `inventory.onboarding.title` | Stok awal | — |
| UXP-413 | `inventory.opening_entry.title` | Input stok awal | — |
| UXP-414 | `inventory.incidents.title` | Insiden rekonsiliasi | — |
| UXP-415 | `inventory.incident_detail.title` | Detail insiden | — |
| UXP-501 | `reporting.daily.title` | Rekap harian | — |
| UXP-502 | `reporting.finance.title` | Laporan keuangan | — |
| UXP-503 | `costing.worklist.title` | Biaya perlu dilengkapi | — |
| UXP-504 | `costing.correction.title_complete`; `costing.correction.title_correct` | Lengkapi biaya (when the cost is UNKNOWN); Koreksi biaya (otherwise) | Yes — was "Koreksi biaya" for both |
| UXP-505 | `costing.history.title` | Riwayat biaya | Yes — was "Riwayat biaya produk" |
| UXP-601 | `config.settings.title` | Pengaturan | — |
| UXP-602 | `access.users.title` | Pengguna | — |
| UXP-603 | `access.user_create.title` | Tambah pengguna | — |
| UXP-604 | `access.user_detail.title` | Detail pengguna | — |
| UXP-605 | `access.user_edit.title` | Ubah pengguna | — |
| UXP-606 | `access.link_handover.title` | Tautan kata sandi | — |
| UXP-607 | `config.profile.title` | Profil usaha | — |
| UXP-608 | `config.profile_edit.title` | Ubah profil usaha | — |
| UXP-609 | `cash.registers.title` | Laci kas | — |
| UXP-610 | `cash.register_form.title_new`; `cash.register_form.title_edit` | Tambah laci kas (new); Ubah laci kas (edit) | Yes — was "Form laci kas" |
| UXP-611 | `config.system_status.title` | Status sistem | — |
| UXP-612 | `audit.activity.title` | Riwayat aktivitas | — |
| UXP-613 | `audit.security.title` | Riwayat keamanan | Yes — was "Log keamanan" |

Pages: **61**, each with one title (UXP-504 and UXP-610 have one title per mode).

Detail pages name their object under the title:

| Key | Copy | Page |
| --- | --- | --- |
| `sales.detail.subtitle` | Penjualan :nomor | UXP-202 |
| `cash.session_detail.subtitle` | Sesi kas :nomor · :laci | UXP-206 |
| `catalog.product_detail.subtitle` | :produk | UXP-302, UXP-304, UXP-306, UXP-505 |
| `inventory.movement.subtitle` | :jenis · :produk | UXP-403 |
| `inventory.receipt_detail.subtitle` | Barang masuk :waktu | UXP-406 |
| `inventory.issue_detail.subtitle` | Barang keluar :waktu | UXP-409 |
| `inventory.incident_detail.subtitle` | :produk | UXP-415 |
| `access.user_detail.subtitle` | :nama | UXP-604, UXP-605 |

### 4.2 Document title

| Key | Copy | Use |
| --- | --- | --- |
| `ux.shell.document_title` | :halaman — :toko | Protected pages; `:toko` is the shop name of the BusinessProfile in force (UXA-13, REQ-SCOPE-03) |
| `ux.shell.document_title_public` | :halaman | UXP-001 and UXP-002, which show no business data (INFORMATION_ARCHITECTURE section 3) |

### 4.3 Navigation

Primary destinations per role are those of INFORMATION_ARCHITECTURE section 4; the order is unchanged.

| UXI | Item | Key | Copy |
| --- | --- | --- | --- |
| UXI-001 | Navigation | `reporting.home.title` | Beranda |
| UXI-002 | Navigation | `sales.till.title` | Kasir |
| UXI-003 | Navigation | `sales.list.title` | Penjualan |
| UXI-004 | Navigation | `cash.sessions.title` | Sesi kas |
| UXI-005 | Navigation | `catalog.products.title` | Produk |
| UXI-006 | Navigation | `inventory.stock.title` | Stok |
| UXI-007 | Navigation | `ux.nav.reports` | Laporan |
| UXI-008 | Navigation | `config.settings.title` | Pengaturan |
| UXI-009 | Sub-navigation of Produk | `catalog.products.title` | Produk |
| UXI-010 | Sub-navigation of Produk | `catalog.categories.title` | Kategori |
| UXI-011 | Sub-navigation of Produk | `catalog.csv_import.title` | Impor produk (CSV) |
| UXI-012 | Sub-navigation of Stok | `inventory.stock.title` | Stok |
| UXI-013 | Sub-navigation of Stok | `inventory.history.title` | Riwayat stok |
| UXI-014 | Sub-navigation of Stok | `inventory.receipts.title` | Barang masuk |
| UXI-015 | Sub-navigation of Stok | `inventory.issues.title` | Barang keluar |
| UXI-016 | Sub-navigation of Stok | `inventory.onboarding.title` | Stok awal |
| UXI-017 | Sub-navigation of Stok | `inventory.incidents.title` | Insiden rekonsiliasi |
| UXI-018 | Sub-navigation of Laporan | `reporting.daily.title` | Rekap harian |
| UXI-019 | Sub-navigation of Laporan | `cash.sessions.title` | Sesi kas |
| UXI-020 | Sub-navigation of Laporan | `reporting.finance.title` | Laporan keuangan |
| UXI-021 | Sub-navigation of Laporan | `costing.worklist.title` | Biaya perlu dilengkapi |

"Laporan" is the one destination without a page of its own: it opens Rekap harian (UXP-501, NAVIGATION_CONTRACTS).

| Key | Copy | Use |
| --- | --- | --- |
| `ux.nav.stock_badge.label` | :jumlah produk perlu restok | Accessible name of the Stok badge (UXI-006); the badge shows the number only. It counts Habis and Menipis products (BR-INV-41) |
| `ux.nav.incidents_badge.label` | :jumlah insiden perlu ditangani | Accessible name of the count on Insiden rekonsiliasi (UXI-017) |
| `ux.nav.menu_open` | Buka menu | UXI-022, compact width |
| `ux.nav.menu_close` | Tutup menu | UXI-022 while the drawer is open |
| `ux.nav.main` | Menu utama | Accessible name of the navigation landmark (UXA-13) |
| `ux.nav.sub` | Submenu :halaman | Accessible name of a sub-navigation |

## 5. Shared actions

Controls that recur on many pages. A page row in sections 6 to 11 that uses one of them names its key.

| Key | Copy | Use |
| --- | --- | --- |
| `ux.action.cancel` | Batal | Cancel of every create, edit and command page, of inline forms and of every dialog (NC-03); sends nothing |
| `ux.action.back_to` | Kembali ke :halaman | Contextual back (NC-01); `:halaman` is the destination's title |
| `ux.action.back_home` | Kembali ke halaman awal | Back to the role's home (NC-12) |
| `ux.action.review` | Tinjau | Opens the review step of a command (BR-CMD-04) |
| `ux.action.edit_again` | Kembali ubah | Leaves a review dialog and returns to the form, keeping it |
| `ux.action.resend` | Kirim ulang | Repeats the same frozen request (UXS-14); never a new request |
| `ux.action.check_status` | Cek status | Asks the server for the outcome (ADR-007 section 4) |
| `ux.action.view_result` | Lihat hasil | Opens the result page of a request found saved (UXI-056) |
| `ux.action.open_unresolved` | Buka Permintaan belum pasti | Opens UXP-006 from a dialog, a banner or a disabled reason |
| `ux.action.load_more` | Muat lebih banyak | Next page of search results (BR-CAT-25) |
| `ux.action.next_page` | Berikutnya | Paging |
| `ux.action.previous_page` | Sebelumnya | Paging |
| `ux.action.filter` | Filter | Opens the filters of a search |
| `ux.action.clear_filters` | Hapus filter | Clears search and filters (UXS-03) |
| `ux.action.apply` | Terapkan | Applies a custom range (UXI-309) |
| `ux.action.reload` | Muat ulang | Reads again (UXS-10, UXS-21, UXI-215, UXI-374) |
| `ux.action.retry` | Coba lagi | Repeats a failed read (UXS-22) |
| `ux.action.save` | Simpan | Inline forms and the cash register form |
| `ux.action.save_changes` | Simpan perubahan | Edit pages |
| `ux.action.done` | Selesai | Ends a handover page (UXI-357) |
| `ux.action.remove_line` | Hapus baris | Removes a line from an unsaved draft; deletes nothing that was saved (INTERACTION_INVENTORY section 3) |
| `ux.action.deactivate` | Nonaktifkan | Takes a product, category, account or cash register out of new use; reversible (D-042) |
| `ux.action.reactivate` | Aktifkan kembali | Returns it to use |
| `ux.action.show_password` | Tampilkan kata sandi | Password visibility toggle |
| `ux.action.hide_password` | Sembunyikan kata sandi | The same toggle while the password is shown |
| `ux.action.stay` | Tetap di halaman ini | Unsaved-changes dialog, default (UXS-45) |
| `ux.action.leave` | Tinggalkan | Unsaved-changes dialog |
| `ux.action.optional` | (opsional) | Appended to the label of an optional field |
| `ux.action.required_mark` | Wajib | Accessible name of the mark of a required field |
| `ux.list.updated_at` | Diperbarui :waktu | Time of the last refresh of a live list (BR-INV-41) |
| `ux.list.count` | :jumlah hasil | Result count of a list |
| `ux.list.search_hint` | Ketik untuk mencari | Hint of a search field without its own hint |
| `ux.action.more_actions` | Tindakan lain | Accessible name of an overflow button that holds a row's or a page's further actions (DESIGN_SYSTEM section 13) |

### 5.1 Shortcuts in accessible names

The provisional shortcuts of UXA-09 (D-075) follow the visible label in the accessible name, in parentheses (CW-11).

| Shortcut | Added to | Controls |
| --- | --- | --- |
| (F2) | `sales.till.search` | UXI-066, UXI-089 |
| (F8) | `sales.cart.pay` | UXI-075 |
| (Alt+1); (Alt+2) | `sales.payment.method_cash`; `sales.payment.method_transfer` | UXI-078 |
| (Alt+U) | `sales.payment.exact_cash` | UXI-080 |
| (Ctrl+Enter) | The final action of the payment screen; `ux.action.review`; `cash.close.continue_count`; the confirm button of every review dialog | UXI-083; UXI-096, UXI-105, UXI-127, UXI-152, UXI-193, UXI-239, UXI-259, UXI-271, UXI-279, UXI-295, UXI-318; UXI-103, UXI-150; UXI-097, UXI-107, UXI-128, UXI-154, UXI-194, UXI-240, UXI-260, UXI-272, UXI-280, UXI-289, UXI-296, UXI-319 |
| (Esc) | `sales.payment.back_to_cart`; `ux.action.edit_again`; the cancel of every dialog (UXA-08) | UXI-084; UXI-098, UXI-108, UXI-129, UXI-155, UXI-195, UXI-241, UXI-261, UXI-273, UXI-281, UXI-297, UXI-320 |

## 6. Common pages: sign-in, account, shell and Owner home (A0)

Sections 6 to 11 follow [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md) page by page. Every interaction `UXI-nnn` appears in at least one row. Visibility, conditions, actions and results are those of the inventory; these rows give only the words. A row without an interaction identifier is text that belongs to the page, such as a field hint or a success message.

### 6.1 UXP-000 Shell

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-001 to UXI-022 | Navigation, sub-navigation, menu button | Section 4.3 | — | — |
| UXI-023 | Account menu button | `ux.shell.account_menu` | Menu akun :nama | Accessible name; the button shows the display name |
| UXI-024 | Account menu item | `access.account.title` | Akun saya | — |
| UXI-025 | Account menu item | `commands.unresolved.menu_item` | Permintaan belum pasti (:jumlah) | Only while the user has unresolved requests: references in this browser or request receipts on the server (D-087) |
| UXI-026 | Account menu item | `access.sign_out` | Keluar | Never closes a cash session (BR-CASH-11) |
| UXI-027, UXI-028 | Sign-out warning, title | `access.sign_out_warning.title` | Masih ada permintaan yang statusnya belum pasti | SEC-CH-05 |
| UXI-027, UXI-028 | Sign-out warning, body | `access.sign_out_warning.body` | Catatan permintaan itu tetap ada di peramban ini. Masuk lagi di perangkat dan peramban ini untuk mengecek statusnya. Sebaiknya cek dulu sebelum keluar. | The references stay in this browser only (SEC-CH-05, NC-13 (c)) |
| UXI-027 | Sign-out warning, primary | `ux.action.open_unresolved` | Buka Permintaan belum pasti | Changed: was "Lihat permintaan" (CW-04) |
| UXI-028 | Sign-out warning, secondary | `access.sign_out_warning.leave` | Tetap keluar | — |
| UXI-029 | Banner, text | `commands.unresolved.banner` | Ada permintaan yang statusnya belum pasti. | — |
| UXI-029 | Banner, action | `ux.action.open_unresolved` | Buka Permintaan belum pasti | Changed: was "Lihat" (CW-04) |
| UXI-030 | Onboarding banner, text | `ux.state.onboarding.title`; `ux.state.onboarding.body` | Section 12 (UXS-27) | Shown to every role |
| UXI-030 | Onboarding banner, Owner action | `inventory.onboarding.open` | Buka Stok awal | Owner only (CAP-15) |
| UXI-031 to UXI-033 | Password confirmation dialog | `ux.state.recent_auth.*` | Section 12 (UXS-11) | The field label is `ux.state.recent_auth.password`, "Kata sandi"; the buttons are "Konfirmasi" (UXI-032) and `ux.action.cancel` (UXI-033) |
| UXI-034, UXI-035 | Sign-in-ending dialog | `ux.state.session_warning.*` | Section 12 (UXS-23) | "Tetap masuk" (UXI-034); `access.sign_out`, "Keluar" (UXI-035) |
| UXI-036, UXI-037 | Shared uncertain panel | `ux.state.uncertain.*`, `ux.action.resend`, `ux.action.check_status` | Sections 12 and 14 | The panel names the request's family (section 14.1) |
| UXI-038 | Unsaved-changes dialog | `ux.state.unsaved.*`, `ux.action.stay`, `ux.action.leave` | Section 12 (UXS-45) | "Tetap di halaman ini" is the default |

### 6.2 UXP-001 Masuk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-039 | Field | `access.sign_in.email` | Email | Opens the email keyboard (UXA-14) |
| UXI-039 | Field | `access.sign_in.password` | Kata sandi | — |
| UXI-040 | Toggle | `ux.action.show_password`; `ux.action.hide_password` | Tampilkan kata sandi; Sembunyikan kata sandi | — |
| UXI-041 | Button | `access.sign_in.submit` | Masuk | No "remember me" (SEC-AUTH-07) and no password-recovery link (FD-04) |
| — | Failure | `ux.state.signin_failed.sign_in` | Section 12 (UXS-42): Gagal masuk; Periksa email dan kata sandi Anda, lalu coba lagi. | One text for every failure (SEC-AUTH-08) |
| — | Note after the end of a sign-in session | `ux.state.signed_out.body` | Section 12 (UXS-09) | — |

### 6.3 UXP-002 Atur kata sandi

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `access.set_password.intro` | Buat kata sandi untuk akun Anda. | One text for a first password, a reset and a link from an operator procedure, so the page reveals nothing about the link (SEC-ACC-05) |
| UXI-042 | Field | `access.set_password.new` | Kata sandi baru | — |
| UXI-042 | Field | `access.set_password.repeat` | Ulangi kata sandi baru | — |
| UXI-042 | Rule shown with the fields | `access.password_rule` | Minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol. Boleh ditempel dari pengelola kata sandi. | AICWDF-COMPAT-8 (SEC-AUTH-05) |
| UXI-043 | Button | `access.set_password.submit` | Simpan kata sandi | — |
| — | Success | `access.set_password.success` | Kata sandi tersimpan. Masuk dengan email dan kata sandi baru Anda. | The link "Masuk" then becomes primary |
| UXI-044 | Link | `access.set_password.to_sign_in`; `access.sign_in.title` | Ke halaman masuk; after a success: Masuk | — |
| — | Lost answer | `access.set_password.lost_answer` | Hasilnya belum diterima. Coba masuk dengan kata sandi baru. Jika gagal, minta tautan baru kepada Pemilik, karena tautan ini mungkin sudah terpakai. | UI_STATES section 5 |
| — | Failure | `ux.state.link_invalid.*` | Section 12 (UXS-41) | Uniform (SEC-ACC-06) |

### 6.4 UXP-003 Akun saya

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Field label | `access.account.name` | Nama tampilan | — |
| — | Field label | `access.account.email` | Email | — |
| — | Field label | `access.account.roles` | Peran | Values: section 3 |
| — | Note for staff | `access.account.staff_note` | Nama dan email Anda hanya dapat diubah oleh Pemilik. | Staff have no control for them (SEC-ACC-10) |
| UXI-045 | Button | `access.change_password.title` | Ubah kata sandi | — |
| UXI-046 | Button | `access.owner_details.title` | Ubah nama dan email | Owner only. Changed: was "Ubah nama atau email" (CW-04) |

### 6.5 UXP-004 Ubah kata sandi

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-047 | Field | `access.change_password.current` | Kata sandi sekarang | — |
| UXI-047 | Fields | `access.set_password.new`; `access.set_password.repeat` | Kata sandi baru; Ulangi kata sandi baru | With `access.password_rule` |
| UXI-048 | Button | `access.set_password.submit` | Simpan kata sandi | — |
| — | Success | `access.change_password.success` | Kata sandi diubah. Sesi masuk Anda di perangkat dan peramban lain sudah diakhiri. | SEC-AUTH-11 |
| — | Wrong current password | `ux.state.signin_failed.current_password` | Section 12 (UXS-42) | — |
| — | Lost answer | `access.change_password.lost_answer` | Hasilnya belum diterima. Ulangi sekali lagi. Jika kata sandi sekarang ditolak, berarti perubahan sudah tersimpan dan kata sandi baru yang berlaku. | UI_STATES section 5 |
| UXI-049 | Button | `ux.action.cancel` | Batal | — |
| UXI-050 | Back | `ux.action.back_to` | Kembali ke Akun saya | — |

### 6.6 UXP-005 Ubah nama dan email

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-051 | Field | `access.owner_details.name` | Nama tampilan | — |
| UXI-051 | Field | `access.owner_details.email` | Email | — |
| UXI-051 | Hint | `access.email_help` | Email ini dipakai untuk masuk. Aplikasi tidak pernah mengirim email. | D-049, SEC-AUTH-01 |
| UXI-052 | Button | `ux.action.save_changes` | Simpan perubahan | Needs password confirmation (UXS-11) |
| — | Success | `access.owner_details.success` | Nama dan email disimpan. | — |
| UXI-053 | Button | `ux.action.cancel` | Batal | — |
| UXI-054 | Back | `ux.action.back_to` | Kembali ke Akun saya | — |

### 6.7 UXP-006 Permintaan belum pasti

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `commands.unresolved.intro` | Hasil permintaan berikut belum diketahui. Cek statusnya, dan jangan mengulang pekerjaan yang sama sebelum hasilnya diketahui. | BR-CMD-03 |
| — | Row | `commands.unresolved.row` | :jenis · dikirim :waktu | `:jenis` is the family label of section 14.1 |
| UXI-055 | Row button | `ux.action.check_status` | Cek status | Results: section 14.2 |
| UXI-056 | Row link | `ux.action.view_result` | Lihat hasil | After COMMITTED |
| UXI-057 | Back | `ux.action.back_to`; `ux.action.back_home` | Kembali ke :halaman; Kembali ke halaman awal | — |
| UXI-058 | Row of another user's request, for the Owner | `commands.requests.other_user` | Permintaan :nama | INTERACTION_CONTRACTS section 2.2 (D-087); with `commands.unresolved.row` and `ux.action.check_status` |
| UXI-058 | Help | `commands.requests.other_user_help` | Cek status permintaan ini atas nama Pemilik. Jika belum tersimpan, permintaan ini tidak akan pernah tersimpan. | The Owner's check writes the fence (ADR-007 section 4) |
| UXI-055 | Row known only from the server | `commands.requests.from_server` | Dikirim dari perangkat lain atau sebelum data peramban dihapus. | A request receipt without a reference in this browser (INTERACTION_CONTRACTS section 2.2; D-087) |
| — | Lost-reference rule | `commands.requests.lost_help` | Jika permintaan tidak ada di daftar ini dan tidak ada di riwayat, tunggu :menit menit sejak dikirim. Setelah itu permintaan itu pasti tidak tersimpan dan Anda dapat mulai lagi. | INTERACTION_CONTRACTS section 2.4; `:menit` is the record-check wait of TR-TIME-03 |
| — | Empty | `commands.unresolved.empty` | Tidak ada permintaan yang statusnya belum pasti. | UXS-02 |

### 6.8 UXP-007 Beranda

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Panel title | `reporting.home.today` | Hari ini | Its figures use the labels of section 10.1; which figures the panel shows is key-screen design (RF-4C) |
| UXI-059 | Link | `reporting.daily.title` | Rekap harian | Changed: was "Lihat rekap harian" (CW-04) |
| UXI-060 | List title | `reporting.home.open_sessions` | Sesi kas yang sedang dibuka | — |
| UXI-060 | Row | `reporting.home.open_session_row` | :laci · :nama · dibuka :waktu | — |
| UXI-060 | Empty | `reporting.home.open_sessions_empty` | Tidak ada sesi kas yang sedang dibuka. | — |
| — | Section title | `reporting.home.attention` | Perlu perhatian | — |
| UXI-061 | Attention item | `reporting.home.attention_restock` | Perlu restok (:jumlah) | An attention item names the condition it reports (CW-04) |
| UXI-062 | Attention item | `reporting.home.attention_cost` | Biaya perlu dilengkapi (:jumlah) | — |
| UXI-063 | Attention item | `reporting.home.attention_incidents` | Insiden rekonsiliasi (:jumlah) | — |
| UXI-064 | Attention item | `reporting.home.attention_status` | Status sistem perlu dicek | Shown when an item is failed or Belum Terverifikasi |
| UXI-065 | Attention item | `reporting.home.attention_onboarding` | Masa input stok awal aktif | — |
| — | Nothing to report | `reporting.home.attention_empty` | Tidak ada yang perlu perhatian saat ini. | — |

## 7. Till, Sales and cash sessions (A1 and A2)

### 7.1 UXP-101 Kasir

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Session header | `sales.till.session` | Sesi kas :nomor · :laci | The user's own OPEN session |
| UXI-066 | Field | `sales.till.search` | Cari atau scan produk | Accessible name adds "(F2)" |
| UXI-066 | Hint | `sales.till.search_hint` | Scan barcode, atau ketik nama atau SKU | — |
| UXI-067 | Result row action | `sales.till.add_to_cart`; `sales.till.add_to_cart_named` | Tambah ke keranjang; accessible name: Tambah :produk ke keranjang | The whole row is the target |
| UXI-067 | Result row stock | `sales.till.result_stock` | Stok :jumlah | The stock needed at checkout only (CAP-14) |
| UXI-067 | Thumbnail | `catalog.photo.thumbnail_alt`; `catalog.photo.placeholder_alt` | Foto :produk; Belum ada foto | D-037 |
| UXI-067 | Inactive result | `ux.state.inactive.label` | Nonaktif | Read only; cannot be added (UXS-32) |
| UXI-068 | Button | `ux.action.load_more` | Muat lebih banyak | — |
| UXI-069 | Filters | `ux.action.filter`; `catalog.field.category`; `catalog.field.brand` | Filter; Kategori; Merek | — |
| — | Cart region | `sales.cart.title` | Keranjang | A labelled region (UXA-13) |
| — | Empty cart | `sales.cart.empty` | Keranjang masih kosong. Scan atau cari produk. | — |
| — | Line total | `sales.cart.line_total` | Subtotal | — |
| — | Sale total | `sales.cart.total` | Total | — |
| UXI-070 | Line field | `sales.cart.quantity` | Jumlah | Decimals only up to the product's precision |
| UXI-071 | Line buttons | `sales.cart.increase`; `sales.cart.decrease` | Accessible names: Tambah satu :satuan :produk; Kurangi satu :satuan :produk | The visible labels are the symbols + and − |
| UXI-072 | Line button | `sales.cart.remove`; `sales.cart.remove_named` | Hapus dari keranjang; accessible name: Hapus :produk dari keranjang | — |
| UXI-073 | Overflow button | `sales.cart.clear` | Kosongkan keranjang | — |
| UXI-073 | Confirmation, title | `sales.cart.clear_confirm.title` | Kosongkan keranjang? | — |
| UXI-073 | Confirmation, body | `sales.cart.clear_confirm.body` | Semua baris di keranjang dihapus. Belum ada yang tersimpan. | The cart is a draft (BR-DOM-09) |
| UXI-073 | Confirmation, buttons | `sales.cart.clear`; `ux.action.cancel` | Kosongkan keranjang; Batal | — |
| UXI-074 | Button | `sales.cart.accept_new_prices` | Setuju harga baru | UXS-18 |
| UXI-075 | Button | `sales.cart.pay` | Bayar | Accessible name adds "(F8)"; disabled reasons: section 15 |
| UXI-076 | Link | `sales.till.resolve_payment` | Cek pembayaran yang belum pasti | The page's primary action while a checkout is unresolved (NC-13). Changed: was "Selesaikan pembayaran yang belum pasti" |
| UXI-077 | Link in the session header | `cash.close.title` | Tutup sesi kas | Changed: was "Tutup sesi" (CW-04) |

### 7.2 UXP-102 Pembayaran

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Total | `sales.payment.total` | Total | — |
| UXI-078 | Method group | `sales.payment.method` | Metode pembayaran | — |
| UXI-078 | Methods | `sales.payment.method_cash`; `sales.payment.method_transfer` | Tunai; Transfer bank | The only two methods (D-077). Accessible names add "(Alt+1)" and "(Alt+2)". Tunai is chosen when the screen opens (D-075) |
| UXI-079 | Field | `sales.payment.tendered` | Uang diterima | Tunai |
| UXI-080 | Button | `sales.payment.exact_cash` | Uang Pas | D-040; accessible name adds "(Alt+U)" |
| — | Change | `sales.payment.change` | Kembalian | Shown live and large (UXA-11) |
| — | Missing amount | `ux.state.tender_low.body` | Kurang :uang | UXS-34 |
| — | Transfer guidance | `sales.payment.transfer_help` | Pastikan dana sudah masuk ke rekening toko, misalnya dari aplikasi bank toko, notifikasi di perangkat toko, atau mutasi rekening. Tangkapan layar atau ponsel pelanggan saja tidak cukup. | BR-PAY-03 |
| UXI-081 | Field | `sales.payment.reference` + `ux.action.optional` | Referensi pembayaran (opsional) | Transfer bank. The key holds "Referensi pembayaran"; the optional marker is appended (section 5) |
| UXI-082 | Field | `sales.payment.customer_name` + `ux.action.optional` | Nama pelanggan (opsional) | The key holds "Nama pelanggan" |
| UXI-082 | Hint | `sales.payment.customer_name_help` | Dicetak di struk. | BR-SALE-08 |
| UXI-083 | Final action, Tunai | `sales.payment.final_cash` | Selesaikan | D-040; accessible name adds "(Ctrl+Enter)" |
| UXI-083 | Final action, Transfer bank | `sales.payment.final_transfer` | Dana sudah diterima — Selesaikan | D-040; pressing it is the confirmation that the funds were received (BR-SALE-12); accessible name adds "(Ctrl+Enter)" |
| UXI-083 | Submitting | `ux.state.submitting.title` | Sedang diproses | UXS-06 |
| UXI-084 | Button | `sales.payment.back_to_cart` | Kembali ke keranjang | Accessible name adds "(Esc)" |
| UXI-085, UXI-086 | Uncertain panel | `ux.state.uncertain.*`; `commands.family.sale.must_not` | Sections 12 and 14 | — |
| UXI-087 | Button | `sales.payment.restart_from_cart` | Mulai lagi dari keranjang | After NOT_COMMITTED only |

### 7.3 UXP-103 Struk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Confirmation | `sales.receipt.saved` | Penjualan :nomor tersimpan. | No further confirmation step (BR-SALE-14) |
| UXI-088 | Button | `sales.receipt.new_sale` | Transaksi baru | GLOSSARY: "transaksi" only in this phrase and in BR-CASH-14 |
| UXI-089 | Field | `sales.till.search` | Cari atau scan produk | — |
| UXI-089 | Hint | `sales.receipt.search_hint` | Scan produk berikutnya untuk memulai transaksi baru. | UXA-11 |
| UXI-090 | Button | `sales.receipt.print` | Cetak struk | — |
| UXI-090 | Hint | `sales.receipt.print_check` | Pastikan struk benar-benar tercetak. | A closed print dialog is not proof of ink (BR-DOC-04) |
| UXI-091 | Choice | `sales.receipt.size`; `sales.receipt.size_58`; `sales.receipt.size_80`; `sales.receipt.size_a4` | Ukuran struk; 58 mm; 80 mm; A4 | Changed: was "Ukuran: 58 mm / 80 mm / A4" |
| UXI-092 | Link | `sales.detail.title` | Detail penjualan | Changed: was "Lihat detail penjualan" (CW-04) |
| UXI-093 | Back | `ux.action.back_to` | Kembali ke Kasir | Changed: capital K, the page title (CW-03) |

### 7.4 UXP-104 Buka sesi kas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `cash.open.intro` | Hitung uang di laci, lalu buka sesi kas atas nama Anda. | BR-CASH-05 |
| UXI-094 | Field | `cash.open.register` | Laci kas | Lists active registers without an OPEN session |
| UXI-095 | Field | `cash.open.opening_cash` | Modal awal | — |
| UXI-095 | Hint | `cash.open.opening_cash_help` | Jumlah uang di laci saat ini. Rp0 boleh. | BR-CASH-04 |
| UXI-096 | Button | `ux.action.review` | Tinjau | — |
| UXI-097 | Review dialog, title | `cash.open.review_title` | Tinjau buka sesi kas | — |
| UXI-097 | Review dialog, body | `cash.open.review_body` | Laci kas :laci, modal awal :uang. Anda menjadi penanggung jawab sesi kas ini sampai Anda menutupnya. | BR-CASH-01, BR-CASH-07 |
| UXI-097 | Review dialog, confirm | `cash.open.confirm` | Buka sesi | — |
| UXI-098 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-099 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.open_session.must_not` | Sections 12 and 14 | — |
| UXI-100 | Button | `ux.action.cancel` | Batal | — |
| — | Success | `cash.open.success` | Sesi kas :nomor dibuka. | — |

### 7.5 UXP-105 Tutup sesi kas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Steps | `cash.close.step_preview`; `cash.close.step_count` | Pratinjau; Hitung uang | — |
| — | Preview figures | `cash.figure.opening_cash`; `cash.figure.sale_count`; `cash.figure.cash_payments`; `cash.figure.transfer_payments`; `cash.figure.drawer_refunds`; `cash.figure.expected_cash` | Modal awal; Jumlah penjualan; Pembayaran tunai; Pembayaran transfer bank; Pengembalian dari laci kas; Uang seharusnya di laci | No cost (BR-CASH-20) |
| UXI-101 | Section | `cash.close.drawer_refunds` | Pengembalian dari laci kas di sesi ini | — |
| UXI-101 | Reminder | `cash.close.drawer_refunds_reminder` | Uang pengembalian ini seharusnya sudah tidak ada di laci. | D-068 |
| UXI-101 | Row | `cash.close.drawer_refund_row` | :uang · penjualan :nomor · :waktu | — |
| UXI-102 | Note | `cash.close.unresolved_note` | Ada penjualan di sesi ini yang statusnya belum pasti. Cek statusnya dulu sebelum menghitung laci. | BR-CASH-16 |
| UXI-102 | Link | `cash.close.resolve_unresolved` | Cek permintaan yang belum pasti | Changed: was "Selesaikan permintaan belum pasti" |
| UXI-103 | Button | `cash.close.continue_count` | Lanjut hitung | Accessible name adds "(Ctrl+Enter)" |
| UXI-104 | Field | `cash.close.physical_cash` | Uang fisik di laci | — |
| UXI-104 | Hint | `cash.close.physical_cash_help` | Hitung semua uang di laci, termasuk modal awal. Rp0 boleh. | BR-CASH-12 |
| UXI-105 | Button | `ux.action.review` | Tinjau | — |
| UXI-106 | Button | `cash.close.back_to_preview` | Kembali ke pratinjau | — |
| UXI-107 | Review dialog, title | `cash.close.review_title` | Tinjau tutup sesi kas | — |
| UXI-107 | Review dialog, figures | `cash.figure.physical_cash`; `cash.figure.expected_cash`; `cash.figure.variance` | Uang fisik di laci; Uang seharusnya di laci; Selisih | The variance with its word (section 1.4) |
| UXI-107 | Review dialog, note | `cash.close.review_note` | Setelah ditutup, sesi kas tidak bisa dibuka kembali dan hitungannya tidak bisa diubah. | BR-CASH-19 |
| UXI-107 | Review dialog, confirm | `cash.close.confirm` | Tutup sesi | — |
| UXI-108 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-109 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.close_session.must_not` | Sections 12 and 14 | — |
| UXI-110 | Button | `ux.action.cancel` | Batal | — |
| UXI-111 | Back | `ux.action.back_to` | Kembali ke Kasir | Changed: capital K (CW-03) |
| — | Session closed meanwhile | `ux.state.session_closed.close` | Section 12 (UXS-30) | With the link to the session's report (NAVIGATION_CONTRACTS UXP-105; also UXP-207) |
| — | Success | `cash.close.success` | Sesi kas :nomor ditutup. | — |

### 7.6 UXP-201 Penjualan

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Scope note | `sales.list.own_scope` | Menampilkan penjualan Anda sendiri. | Cashier and Combined (SEC-AZ-03) |
| UXI-112 | Filter | `sales.list.filter_date` | Tanggal | — |
| UXI-113 | Search | `sales.list.search_number` | Nomor penjualan | — |
| UXI-114 | Filters | `sales.list.filter_cashier`; `sales.list.filter_register` | Kasir; Laci kas | Owner only. "Kasir" here is the user who made the Sale (BR-DOC-01) |
| UXI-115 | Columns | `sales.list.col_number`; `sales.list.col_time`; `sales.list.col_total`; `sales.list.col_method`; `sales.list.col_cashier` | Nomor; Waktu; Total; Metode; Kasir | — |
| UXI-116 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| — | Empty | `sales.list.empty` | Belum ada penjualan. | UXS-02 |

### 7.7 UXP-202 Detail penjualan

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Field labels | `sales.detail.time`; `sales.detail.cashier`; `sales.detail.customer` | Waktu; Kasir; Nama pelanggan | — |
| — | Lines | `sales.detail.lines`; `catalog.field.sku`; `sales.detail.quantity`; `sales.detail.unit_price`; `sales.detail.line_total`; `sales.detail.total` | Barang; SKU; Jumlah; Harga; Subtotal; Total | One line per product (D-067) |
| — | Payment | `sales.detail.payment`; `sales.payment.tendered`; `sales.payment.change`; `sales.detail.reference` | Pembayaran; Uang diterima; Kembalian; Referensi | — |
| UXI-117 | Button | `sales.detail.reprint` | Cetak ulang struk | With `sales.receipt.size`; the reprint carries the copy marker (BR-DOC-04) |
| UXI-118 | Button | `sales.refund.title` | Pengembalian uang dan retur | Owner only. Changed: was "Pengembalian uang / retur" (CW-04) |
| UXI-119 | Link | `sales.detail.session_link` | Sesi kas :nomor | — |
| UXI-120 | Section | `sales.detail.refunds` | Pengembalian uang dan retur | Owner only (SEC-AZ-03) |
| UXI-120 | Money entry | `sales.detail.refund_entry` | :uang · :cara · :sumber · :waktu | — |
| UXI-120 | Goods entry | `sales.detail.return_entry` | :jumlah :produk · :kondisi · :waktu | — |
| UXI-120 | Reason | `sales.detail.refund_reason` | Alasan: :alasan | — |
| UXI-121 | Back | `ux.action.back_to` | Kembali ke :halaman | — |

### 7.8 UXP-203 Pengembalian uang dan retur

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `sales.refund.intro` | Pilih barang dan uang yang dikembalikan untuk penjualan :nomor. Penjualan asli tidak berubah. | BR-RFD-12 |
| UXI-122 | Line field | `sales.refund.return_quantity` | Jumlah barang diretur | Changed: was "Jumlah barang kembali" (GLOSSARY: retur barang) |
| UXI-122 | Line field | `sales.refund.condition` | Kondisi | — |
| UXI-122 | Options | `sales.refund.condition_saleable`; `sales.refund.condition_damaged` | Layak jual; Tidak layak jual | — |
| UXI-122 | Hint | `sales.refund.condition_help` | Barang layak jual masuk lagi ke stok. Barang tidak layak jual tidak menambah stok. | BR-RFD-11 |
| UXI-122 | Line field | `sales.refund.amount` | Uang dikembalikan | — |
| UXI-122 | Remaining bounds | `sales.refund.remaining_quantity`; `sales.refund.remaining_amount` | Sisa bisa diretur: :jumlah; Sisa bisa dikembalikan: :uang | BR-RFD-03 |
| UXI-123 | Field | `sales.refund.method` | Cara pengembalian uang | Changed: was "Cara pembayaran kembali" (GLOSSARY: pengembalian uang) |
| UXI-123 | Options | `sales.payment.method_cash`; `sales.payment.method_transfer` | Tunai; Transfer bank | BR-RFD-05 |
| UXI-124 | Field | `sales.refund.source` | Sumber uang | Tunai only |
| UXI-124 | Option, a session | `sales.refund.source_session` | :laci · :nama · uang seharusnya :uang | Only OPEN sessions (BR-RFD-07) |
| UXI-124 | Option, the Owner | `sales.refund.source_owner` | Dana Pemilik | — |
| UXI-124 | Hint | `sales.refund.source_help` | Pilih satu sumber uang. Tidak ada pilihan otomatis, dan satu pengembalian tidak dibagi ke beberapa sumber. | BR-RFD-06, BR-RFD-16 |
| UXI-125 | Field | `sales.refund.reference` | Referensi pengembalian uang | Changed: was "Referensi pembayaran kembali". Whether a drawer-funded refund's reference is typed or generated is RF-5 (SCHEMA section 16) |
| UXI-126 | Field | `sales.refund.reason` | Alasan | Mandatory |
| UXI-127 | Button | `ux.action.review` | Tinjau | — |
| UXI-128 | Review dialog, title | `sales.refund.review_title` | Tinjau pengembalian uang dan retur | — |
| UXI-128 | Review dialog, lines | `sales.refund.review_total`; `sales.refund.review_method`; `sales.refund.review_source`; `sales.refund.review_goods`; `sales.refund.review_reason` | Total dikembalikan: :uang; Cara: :cara; Sumber uang: :sumber; Barang diretur: :ringkasan; Alasan: :alasan | — |
| UXI-128 | Review dialog, drawer note | `sales.refund.review_drawer_note` | Ambil uang dari laci hanya setelah pengembalian ini tercatat. | D-046, BR-RFD-17 |
| UXI-128 | Review dialog, confirm | `sales.refund.confirm` | Konfirmasi pengembalian | Needs password confirmation (UXS-11) |
| UXI-128 | Uncertain, drawer-funded | `commands.family.refund_drawer.must_not` | Jangan ambil uang dari laci sampai pengembalian ini tercatat. Jangan buat pengembalian baru. | The working label "Jangan ambil uang dari laci", completed (BR-RFD-17) |
| UXI-129 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-130 | Uncertain panel | `ux.action.resend`; `ux.action.check_status` | Kirim ulang; Cek status | Section 14 |
| UXI-131 | Button | `ux.action.cancel` | Batal | — |
| UXI-132 | Back | `ux.action.back_to` | Kembali ke Detail penjualan | Changed: capital D (CW-03) |

### 7.9 UXP-204 Pengembalian tercatat

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-133 | Instruction, drawer-funded | `sales.refund_result.take_cash` | Ambil :uang dari :laci sekarang dan serahkan kepada pelanggan. | The most prominent element (D-046) |
| UXI-133 | Guidance | `sales.refund_result.take_cash_before` | Serahkan sebelum modal awal sesi berikutnya di laci kas ini dihitung. | D-068 |
| — | Other refunds | `sales.refund_result.recorded` | Pengembalian uang :uang tercatat. | Owner-funded or Transfer bank |
| — | Goods only | `sales.refund_result.return_recorded` | Retur barang tercatat. | A return without money |
| UXI-134 | Button | `ux.action.back_to` | Kembali ke Detail penjualan | Changed: capital D (CW-03) |

### 7.10 UXP-205 Sesi kas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Scope note | `cash.sessions.own_scope` | Menampilkan sesi kas Anda sendiri. | Cashier and Combined |
| UXI-135 | Filters | `cash.sessions.filter_status`; `cash.sessions.filter_date` | Status; Tanggal | — |
| UXI-135 | Status values | `cash.session.status_open`; `cash.session.status_closed` | Dibuka; Ditutup | — |
| UXI-136 | Filters | `cash.sessions.filter_register`; `cash.sessions.filter_responsible` | Laci kas; Penanggung jawab | Owner only |
| UXI-137 | Columns | `cash.sessions.col_number`; `cash.sessions.col_register`; `cash.sessions.col_responsible`; `cash.sessions.col_opened`; `cash.sessions.col_closed`; `cash.sessions.col_variance` | Nomor; Laci kas; Penanggung jawab; Dibuka; Ditutup; Selisih | — |
| UXI-138 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| — | Empty | `cash.sessions.empty` | Belum ada sesi kas. | — |

### 7.11 UXP-206 Detail sesi kas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Status | `cash.session.status_open`; `cash.session.status_closed` | Dibuka; Ditutup | Figures of an OPEN session: those of section 7.5; of a CLOSED one: the report labels of section 17.2 |
| UXI-139 | Button | `cash.close.title` | Tutup sesi kas | Changed: was "Tutup sesi" (CW-04) |
| UXI-140 | Button | `cash.assisted_close.title` | Tutup sesi oleh Pemilik | Owner only |
| UXI-141 | Button | `cash.session_detail.print` | Cetak laporan | — |
| UXI-142 | Button | `cash.session_detail.download_csv` | Unduh CSV | — |
| UXI-143 | Section | `cash.session_detail.drawer_refunds` | Pengembalian dari laci kas | — |
| UXI-143 | Owner guidance, positive variance | `cash.session_detail.positive_variance_guidance` | Selisih lebih bisa berasal dari uang pengembalian yang masih ada di laci saat dihitung. Jika begitu, ambil uang itu dari laci ini dan serahkan kepada pelanggan sebelum modal awal sesi berikutnya dihitung, lalu jelaskan dengan catatan tinjauan. | D-068 |
| UXI-144 | Section | `cash.session_detail.notes` | Catatan tinjauan Pemilik | Owner only |
| UXI-144 | Entry | `cash.session_detail.note_meta` | Oleh :nama, :waktu | — |
| UXI-144 | Empty | `cash.session_detail.notes_empty` | Belum ada catatan tinjauan. | — |
| UXI-144 | Hint | `cash.session_detail.notes_help` | Catatan tidak mengubah hitungan, selisih, atau laporan. Catatan tidak bisa diubah atau dihapus; untuk koreksi, tulis catatan baru. | D-069 (f), BR-CASH-19 |
| UXI-145 | Button | `cash.session_detail.add_note` | Tambah catatan tinjauan | — |
| UXI-146 | Field | `cash.session_detail.note` | Catatan | 1 to 2.000 characters |
| UXI-146 | Button | `cash.session_detail.save_note` | Simpan catatan | — |
| UXI-147 | Button | `ux.action.cancel` | Batal | — |
| UXI-148 | Back | `ux.action.back_to` | Kembali ke :halaman | — |
| — | Success | `cash.session_detail.note_saved` | Catatan tinjauan disimpan. | — |

### 7.12 UXP-207 Tutup sesi oleh Pemilik

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-149 | Preview | `cash.assisted_close.responsible`; `cash.open.register` | Penanggung jawab; Laci kas | Figures and the refund reminder as section 7.5; the responsible user's unresolved checkouts with `cash.close.unresolved_note`, `commands.unresolved.row` and `ux.action.check_status` (INTERACTION_CONTRACTS section 2.5; D-087) |
| UXI-150 | Button | `cash.close.continue_count` | Lanjut hitung | — |
| UXI-151 | Fields | `cash.close.physical_cash`; `cash.assisted_close.reason` | Uang fisik di laci; Alasan | Both mandatory |
| UXI-152 | Button | `ux.action.review` | Tinjau | — |
| UXI-153 | Button | `cash.close.back_to_preview` | Kembali ke pratinjau | — |
| UXI-154 | Review dialog, title | `cash.assisted_close.review_title` | Tinjau tutup sesi oleh Pemilik | — |
| UXI-154 | Review dialog, body | `cash.assisted_close.review_body` | Penanggung jawab sesi kas ini tetap :nama. Anda tercatat sebagai yang menutup. | BR-CASH-18; with the figures and `cash.close.review_note` of section 7.5 |
| UXI-154 | Review dialog, confirm | `cash.close.confirm` | Tutup sesi | Needs password confirmation (UXS-11) |
| UXI-155 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-156 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.close_session.must_not` | Sections 12 and 14 | — |
| UXI-157 | Button | `ux.action.cancel` | Batal | — |
| UXI-158 | Back | `ux.action.back_to` | Kembali ke Detail sesi kas | Changed: was "Kembali ke detail sesi" (CW-04) |

## 8. Products (A3)

### 8.1 Product fields and values

Used on UXP-301 to UXP-308, in Product Search and in the stock pages.

| Key | Copy | Note |
| --- | --- | --- |
| `catalog.field.sku` | SKU | — |
| `catalog.field.name` | Nama | — |
| `catalog.field.category` | Kategori | Only active categories can be chosen (BR-CAT-09) |
| `catalog.field.brand` | Merek | Optional; shown with `ux.action.optional` in forms |
| `catalog.field.unit` | Satuan | — |
| `catalog.field.unit_help` | Satuan untuk stok dan penjualan, misalnya pcs, rim, atau kg. | BR-CAT-06 |
| `catalog.field.precision` | Jumlah desimal | — |
| `catalog.field.precision_help` | 0 untuk barang yang dihitung utuh. 1 sampai 3 untuk barang yang bisa dijual sebagian, misalnya 1,5 kg. | BR-NUM-02 |
| `catalog.field.unit_lock_help` | Satuan dan jumlah desimal tidak bisa diubah setelah produk punya mutasi stok. | BR-CAT-07 |
| `catalog.field.selling_price` | Harga jual | Whole rupiah |
| `catalog.field.min_stock` | Stok minimum | — |
| `catalog.field.min_stock_help` | Produk ditandai Menipis bila stoknya lebih dari 0 dan tidak lebih dari angka ini. | BR-INV-40 |
| `catalog.field.barcode` | Barcode | — |
| `catalog.field.manufacturer_barcode` | Barcode pabrik | Optional at creation |
| `catalog.field.status` | Status | — |
| `catalog.status.active` | Aktif | — |
| `catalog.status.inactive` | Nonaktif | — |
| `catalog.photo.thumbnail_alt` | Foto :produk | — |
| `catalog.photo.placeholder_alt` | Belum ada foto | The placeholder image (BR-CAT-44) |

### 8.2 UXP-301 Produk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-159 | Field | `catalog.products.search` | Cari produk | — |
| UXI-159 | Hint | `catalog.products.search_hint` | Scan barcode, atau ketik nama atau SKU | — |
| UXI-160 | Filters | `catalog.field.category`; `catalog.field.brand`; `catalog.field.status` | Kategori; Merek; Status | Values Aktif and Nonaktif |
| UXI-161 | Button | `catalog.product_create.title` | Tambah produk | — |
| UXI-162 | Row | `catalog.field.sku`; `catalog.field.selling_price`; `inventory.state.*` | SKU; Harga jual; Habis, Menipis or Normal | No cost (BR-CAT-26); an inactive product shows "Nonaktif" |
| UXI-163 | Row overflow | `catalog.labels.title` | Cetak label barcode | Changed: was "Cetak label" (CW-04) |
| UXI-164 | Paging | `ux.action.load_more` | Muat lebih banyak | — |
| — | Empty | `catalog.products.empty` | Belum ada produk. | — |
| — | Empty, for a role that may create | `catalog.products.empty_action` | Tambah produk pertama, atau impor dari CSV. | Owner, Ops, Combined |

### 8.3 UXP-302 Detail produk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Sections | `catalog.product_detail.section_data`; `catalog.product_detail.section_price`; `catalog.product_detail.section_stock`; `catalog.product_detail.section_photo`; `catalog.product_detail.section_barcodes` | Data produk; Harga jual; Stok; Foto produk; Barcode | — |
| UXI-165 | Button | `catalog.product_edit.title` | Ubah produk | Changed: was "Ubah" (CW-04) |
| UXI-166 | Overflow | `ux.action.deactivate`; `ux.action.reactivate` | Nonaktifkan; Aktifkan kembali | — |
| UXI-166 | Deactivate, title | `catalog.product.deactivate_confirm.title` | Nonaktifkan :produk? | — |
| UXI-166 | Deactivate, body | `catalog.product.deactivate_confirm.body` | Produk nonaktif tidak bisa dijual, diterima, dikeluarkan, atau disesuaikan, tetapi tetap tampil di riwayat dan dokumen. Anda dapat mengaktifkannya kembali. | BR-CAT-08, D-042 |
| UXI-166 | Reactivate, title | `catalog.product.reactivate_confirm.title` | Aktifkan kembali :produk? | — |
| UXI-166 | Reactivate, body | `catalog.product.reactivate_confirm.body` | Produk ini bisa dijual dan dicatat lagi. | — |
| UXI-166 | Dialog buttons | `ux.action.deactivate` or `ux.action.reactivate`; `ux.action.cancel` | Nonaktifkan or Aktifkan kembali; Batal | — |
| UXI-166 | Success | `catalog.product.deactivated`; `catalog.product.reactivated` | Produk dinonaktifkan.; Produk diaktifkan kembali. | — |
| UXI-167 | Section | `catalog.product_detail.stock_now`; `inventory.state.*` | Stok :jumlah; Habis, Menipis or Normal | — |
| UXI-167 | Link | `inventory.history.title` | Riwayat stok | — |
| UXI-168 | Section | `catalog.product_detail.price_history` | Riwayat harga jual | — |
| UXI-168 | Entry | `catalog.product_detail.price_entry` | :uang · mulai :waktu · oleh :nama | BR-CAT-13 |
| UXI-169 | Buttons | `catalog.photo.upload`; `catalog.photo.replace` | Unggah foto; Ganti foto | — |
| UXI-169 | Hint | `catalog.photo.rules` | JPG, PNG, atau WebP, paling besar 8 MB. | SECURITY_MODEL section 13 |
| UXI-169 | Success | `catalog.photo.saved` | Foto disimpan. | — |
| UXI-170 | Button | `catalog.photo.remove` | Lepas foto | — |
| UXI-170 | Confirmation, title | `catalog.photo.remove_confirm.title` | Lepas foto produk? | — |
| UXI-170 | Confirmation, body | `catalog.photo.remove_confirm.body` | Foto tidak ditampilkan lagi. Berkasnya tetap disimpan. | D-042 |
| UXI-170 | Confirmation, buttons | `catalog.photo.remove`; `ux.action.cancel` | Lepas foto; Batal | — |
| UXI-170 | Success | `catalog.photo.removed` | Foto dilepas. | — |
| — | Barcode row | `catalog.barcode.kind_manufacturer`; `catalog.barcode.kind_internal`; `catalog.barcode.status_active`; `catalog.barcode.status_retired` | Barcode pabrik; Barcode internal; Aktif; Dipensiunkan | — |
| — | Retired barcode | `catalog.barcode.retired_reason` | Dipensiunkan · :alasan | `:alasan` is the reason label in lower case |
| UXI-171 | Button | `catalog.barcode.add_manufacturer` | Tambah barcode pabrik | — |
| UXI-171 | Inline field | `catalog.field.barcode` | Barcode | — |
| UXI-171 | Hint | `catalog.barcode.token_help` | Scan atau ketik barcode dari kemasan. | — |
| UXI-171 | Inline buttons | `catalog.barcode.save`; `ux.action.cancel` | Simpan barcode; Batal | — |
| UXI-171 | Success | `catalog.barcode.added` | Barcode ditambahkan. | — |
| UXI-172 | Button | `catalog.barcode.generate_internal` | Buat barcode internal | — |
| UXI-172 | Hint | `catalog.barcode.generate_help` | Untuk barang tanpa barcode pabrik. Cetak labelnya dengan Cetak label barcode. | BR-CAT-18 |
| UXI-172 | Success | `catalog.barcode.generated` | Barcode internal :kode dibuat. | — |
| UXI-173 | Row action | `catalog.barcode.retire` | Pensiunkan barcode | Changed: was "Nonaktifkan barcode": a retired barcode is never reactivated; only the Owner's misassignment correction attaches a manufacturer barcode retired as a misassignment again, as a new mapping (D-052) |
| UXI-173 | Dialog, title | `catalog.barcode.retire_confirm.title` | Pensiunkan barcode :kode? | — |
| UXI-173 | Dialog, body, manufacturer barcode | `catalog.barcode.retire_confirm.body` | Barcode yang dipensiunkan tidak bisa dipakai lagi, kecuali Pemilik memasangnya ke produk yang benar karena salah pasang. Pilih alasannya. | BR-CAT-19, BR-CAT-51 |
| UXI-173 | Dialog, body, internal code | `catalog.barcode.retire_confirm.body_internal` | Barcode internal yang dipensiunkan tidak pernah dipakai lagi. Pilih alasannya. | BR-CAT-18, BR-CAT-19 |
| UXI-173 | Reason | `catalog.barcode.reason_misassignment` | Salah pasang | Stored as MISASSIGNMENT (SCHEMA section 5.6) |
| UXI-173 | Reason hint, manufacturer barcode | `catalog.barcode.reason_misassignment_help` | Barcode ini terpasang pada produk yang salah. Pemilik dapat memasangnya ke produk yang benar dengan Koreksi salah pasang barcode. | D-051, D-052 |
| UXI-173 | Reason | `catalog.barcode.reason_other` | Alasan lain | Stored as OTHER |
| UXI-173 | Reason hint | `catalog.barcode.reason_other_help` | Misalnya kemasan berganti atau barcode tidak dipakai lagi. Barcode ini tidak bisa dipakai lagi. | D-052 |
| UXI-173 | Dialog buttons | `catalog.barcode.retire`; `ux.action.cancel` | Pensiunkan barcode; Batal | — |
| UXI-173 | Success | `catalog.barcode.retired` | Barcode dipensiunkan. | — |
| UXI-174 | Overflow | `catalog.barcode_correction.title` | Koreksi salah pasang barcode | Owner only |
| UXI-175 | Button | `catalog.labels.title` | Cetak label barcode | Changed: was "Cetak label" (CW-04) |
| UXI-176 | Link | `costing.history.title` | Riwayat biaya | Owner only |
| UXI-177 | Back | `ux.action.back_to` | Kembali ke Produk, or Kembali ke :halaman | Changed: was "Kembali ke daftar produk" (CW-04) |

### 8.4 UXP-303 Tambah produk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Step 1 | `catalog.product_create.step_search` | Cari dulu | — |
| — | Step 1, intro | `catalog.product_create.search_intro` | Periksa dulu apakah produk ini sudah ada, agar tidak tercatat dua kali. | BR-CAT-04 |
| UXI-178 | Fields | `catalog.field.barcode`; `catalog.field.sku`; `catalog.field.name` | Barcode; SKU; Nama | — |
| UXI-178 | Button | `catalog.product_create.check` | Cek | — |
| UXI-179 | Exact match | `catalog.product_create.exact_match` | Produk dengan barcode atau SKU ini sudah ada: | Followed by the link, the product's name |
| UXI-180 | Button | `catalog.product_create.create_anyway` | Tetap buat produk baru | BR-CAT-04 |
| UXI-181 | Nothing found | `catalog.product_create.nothing_found` | Tidak ada produk yang sama atau mirip. | — |
| UXI-181 | Button | `catalog.product_create.continue` | Lanjut isi data | — |
| UXI-182 | Form | Section 8.1 | SKU; Nama; Kategori; Merek (opsional); Satuan; Jumlah desimal; Harga jual; Stok minimum; Barcode pabrik (opsional) | With the hints of section 8.1 |
| UXI-182 | Hint | `catalog.product_create.zero_stock_help` | Produk baru mulai dengan stok 0. Stoknya bertambah lewat Barang masuk. | BR-CAT-03, BR-INV-16 |
| UXI-182 | Hint, the Owner while the onboarding freeze is on | `catalog.product_create.zero_stock_help_onboarding` | Produk baru mulai dengan stok 0. Selama masa input stok awal, isi stoknya dengan Input stok awal. | BR-INV-24 |
| UXI-183 | Button | `catalog.product_create.save` | Simpan produk | — |
| UXI-183 | Success | `catalog.product_create.success` | Produk :produk ditambahkan. | — |
| UXI-184 | Button | `ux.action.cancel` | Batal | — |
| UXI-185 | Back | `ux.action.back_to` | Kembali ke :halaman | — |

### 8.5 UXP-304 Ubah produk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-186 | Form | Section 8.1 | Nama; Kategori; Merek (opsional); Stok minimum; Harga jual; Satuan; Jumlah desimal | Satuan and Jumlah desimal disabled with `catalog.field.unit_lock_help` after the first movement |
| UXI-186 | Price change | `catalog.product_edit.price_change` | Harga lama :lama → harga baru :baru | — |
| UXI-186 | Hint | `catalog.product_edit.price_help` | Harga baru berlaku untuk penjualan berikutnya. Keranjang yang sedang terbuka akan meminta persetujuan harga baru. | BR-SALE-05, BR-CAT-14 |
| UXI-187 | Button | `ux.action.save_changes` | Simpan perubahan | — |
| UXI-187 | Success | `catalog.product_edit.success` | Perubahan produk disimpan. | — |
| UXI-188 | Button | `ux.action.cancel` | Batal | — |
| UXI-189 | Back | `ux.action.back_to` | Kembali ke Detail produk | Changed: capital D (CW-03) |

### 8.6 UXP-305 Koreksi salah pasang barcode

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `catalog.barcode_correction.intro` | Pindahkan barcode pabrik dari produk yang salah ke produk yang benar. Riwayatnya tetap tersimpan. | D-051 |
| UXI-190 | Field | `catalog.field.barcode` | Barcode | Manufacturer barcodes only |
| UXI-190 | Mapping, active | `catalog.barcode_correction.current_mapping` | Sekarang terpasang pada :produk | — |
| UXI-190 | Mapping, retired as a misassignment | `catalog.barcode_correction.retired_mapping` | Dipensiunkan karena salah pasang dari :produk | D-052 |
| UXI-191 | Field | `catalog.barcode_correction.correct_product` | Produk yang benar | — |
| UXI-192 | Field | `catalog.barcode_correction.reason` | Alasan | Mandatory |
| UXI-193 | Button | `ux.action.review` | Tinjau | — |
| UXI-194 | Review dialog, title | `catalog.barcode_correction.review_title` | Tinjau koreksi salah pasang barcode | — |
| UXI-194 | Review dialog, a move | `catalog.barcode_correction.review_body_move` | Barcode :kode dipindahkan dari :lama ke :baru. Penjualan sebelumnya tetap tercatat pada produk saat itu. | D-051 |
| UXI-194 | Review dialog, a re-attachment | `catalog.barcode_correction.review_body_reattach` | Barcode :kode, yang dipensiunkan karena salah pasang dari :lama, dipasang ke :baru. | D-052 |
| UXI-194 | Review dialog, confirm | `catalog.barcode_correction.confirm` | Konfirmasi koreksi | Needs password confirmation (UXS-11) |
| UXI-194 | Success | `catalog.barcode_correction.success` | Barcode :kode sekarang terpasang pada :produk. | — |
| UXI-195 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-196 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.barcode_correction.must_not` | Sections 12 and 14 | — |
| UXI-197 | Button | `ux.action.cancel` | Batal | — |
| UXI-198 | Back | `ux.action.back_to` | Kembali ke Detail produk | Changed: capital D (CW-03) |

### 8.7 UXP-306 Cetak label barcode

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-199 | Fields | `catalog.labels.barcode`; `catalog.labels.quantity` | Barcode; Jumlah label | "Barcode" only when the product has more than one active barcode |
| UXI-199 | Hint | `catalog.labels.size_help` | Ukuran label 50 × 30 mm. | Candidate size, confirmed at gate DEVICES (BR-CAT-36) |
| UXI-200 | Button | `catalog.labels.print` | Cetak | — |
| UXI-201 | Back | `ux.action.back_to` | Kembali ke Detail produk | Changed: capital D (CW-03) |

### 8.8 UXP-307 Kategori

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-202 | Filter | `catalog.field.status` | Status | Aktif; Nonaktif |
| UXI-203 | Button | `catalog.categories.add` | Tambah kategori | — |
| UXI-203 | Inline field | `catalog.categories.name` | Nama kategori | — |
| UXI-203 | Inline buttons | `ux.action.save`; `ux.action.cancel` | Simpan; Batal | — |
| UXI-203 | Success | `catalog.categories.added` | Kategori :kategori ditambahkan. | — |
| UXI-204 | Row action | `catalog.categories.rename`; `catalog.categories.rename_named` | Ubah nama; accessible name: Ubah nama kategori :kategori | — |
| UXI-204 | Inline buttons | `ux.action.save`; `ux.action.cancel` | Simpan; Batal | — |
| UXI-204 | Success | `catalog.categories.renamed` | Nama kategori disimpan. | — |
| UXI-205 | Row action | `ux.action.deactivate`; `ux.action.reactivate` | Nonaktifkan; Aktifkan kembali | — |
| UXI-205 | Deactivate, title | `catalog.category.deactivate_confirm.title` | Nonaktifkan kategori :kategori? | — |
| UXI-205 | Deactivate, body | `catalog.category.deactivate_confirm.body` | Kategori nonaktif tidak bisa dipilih untuk produk. Produk yang sudah memakainya tidak berubah. Anda dapat mengaktifkannya kembali. | BR-CAT-09, D-042 |
| UXI-205 | Reactivate, title | `catalog.category.reactivate_confirm.title` | Aktifkan kembali kategori :kategori? | — |
| UXI-205 | Reactivate, body | `catalog.category.reactivate_confirm.body` | Kategori ini bisa dipilih lagi untuk produk. | — |
| UXI-205 | Success | `catalog.category.deactivated`; `catalog.category.reactivated` | Kategori dinonaktifkan.; Kategori diaktifkan kembali. | — |
| — | Empty | `catalog.categories.empty` | Belum ada kategori. | — |

### 8.9 UXP-308 Impor produk (CSV)

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-206 | Section | `catalog.csv_import.format_title` | Format kolom | The column list is DOCUMENT_CONTRACTS DC-04 (SEC-UP-05) |
| UXI-206 | Column headers | `catalog.csv_import.column.sku`; `catalog.csv_import.column.name`; `catalog.csv_import.column.category`; `catalog.csv_import.column.brand`; `catalog.csv_import.column.base_unit`; `catalog.csv_import.column.quantity_precision`; `catalog.csv_import.column.selling_price`; `catalog.csv_import.column.minimum_stock`; `catalog.csv_import.column.barcode` | SKU; Nama; Kategori; Merek; Satuan; Desimal; Harga jual; Stok minimum; Barcode | DOCUMENT_CONTRACTS DC-04, in its order; the file's header is matched against these texts after trimming and without regard to letter case |
| UXI-206 | Text | `catalog.csv_import.format_intro` | Gunakan berkas CSV dengan pengodean UTF-8 dan judul kolom persis seperti daftar di bawah. Kolom lain tidak diterima. SKU dan barcode dibaca sebagai teks, sehingga angka nol di depan tetap ada. | BR-CAT-39 |
| UXI-206 | Text | `catalog.csv_import.scope_note` | Impor hanya membuat produk baru dengan stok 0. Produk yang sudah ada tidak diubah, dan stok serta biaya tidak ikut diimpor. | BR-CAT-37, BR-CAT-38 |
| UXI-206 | Text | `catalog.csv_import.limits` | Paling besar 2 MB dan 5.000 baris data. | BR-CAT-39 |
| UXI-207 | Button | `catalog.csv_import.choose_file` | Pilih berkas CSV | — |
| — | Preview, title | `catalog.csv_import.preview_title` | Pratinjau impor | — |
| — | Preview, summary | `catalog.csv_import.preview_summary` | :siap baris siap diimpor, :salah baris perlu diperbaiki. | — |
| — | Preview, rule | `catalog.csv_import.all_or_nothing` | Semua baris diimpor bersama, atau tidak ada yang diimpor. | BR-CAT-38 |
| UXI-208 | Row errors | Section 16.3 | Including "Tidak ada kategori aktif dengan nama ini." and "Nama kategori cocok dengan lebih dari satu kategori aktif." | D-069 (h) |
| UXI-209 | Link | `catalog.csv_import.create_category_link` | Buat kategori di halaman Kategori | A call to action in a message (CW-04); the import never creates a category |
| UXI-210 | Button | `catalog.csv_import.confirm` | Impor :jumlah produk | — |
| UXI-210 | Success | `catalog.csv_import.success` | :jumlah produk baru ditambahkan. | — |
| UXI-211 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.csv_import.must_not` | Sections 12 and 14 | — |
| UXI-212 | Button | `catalog.csv_import.cancel` | Batalkan impor | Opens the unsaved-changes dialog with `ux.state.unsaved.body_import` |
| — | Empty | `catalog.csv_import.empty` | Belum ada berkas yang dipilih. | After a cancel |

## 9. Stock (A4)

### 9.1 Stock values and line fields

| Key | Copy | Note |
| --- | --- | --- |
| `inventory.state.out` | Habis | OUT (BR-INV-40) |
| `inventory.state.low` | Menipis | LOW |
| `inventory.state.normal` | Normal | NORMAL; never shown for a failed or stale read (BR-INV-41) |
| `inventory.type.opening` | Stok awal | OPENING |
| `inventory.type.receipt` | Barang masuk | RECEIPT |
| `inventory.type.sale_issue` | Keluar karena penjualan | SALE_ISSUE |
| `inventory.type.manual_issue` | Barang keluar | MANUAL_ISSUE (D-018) |
| `inventory.type.adjustment` | Penyesuaian stok | ADJUSTMENT |
| `inventory.type.reversal` | Pembatalan | REVERSAL |
| `inventory.type.return_receipt` | Retur masuk stok | RETURN_RECEIPT |
| `inventory.issue_reason.damaged` | Rusak | BR-INV-19 |
| `inventory.issue_reason.internal_use` | Pemakaian internal | — |
| `inventory.issue_reason.lost` | Hilang | — |
| `inventory.issue_reason.sample` | Sampel | — |
| `inventory.issue_reason.other` | Lainnya | — |
| `inventory.line.product` | Produk | — |
| `inventory.line.quantity` | Jumlah | In the product's unit and precision |
| `inventory.line.stock_before` | Stok sebelum | Review of an issue or adjustment |
| `inventory.line.change` | Perubahan | Signed |
| `inventory.line.stock_after` | Stok sesudah | — |
| `inventory.line.search` | Cari atau scan produk | The search field of every stock command page |
| `inventory.line.search_hint` | Scan barcode, atau ketik nama atau SKU | — |
| `costing.field.line_cost` | Biaya | The total for the line |
| `costing.field.line_cost_help` | Total biaya perolehan untuk jumlah ini, termasuk biaya sampai barang diterima. | BR-COST-02 |
| `costing.field.cost_unknown` | Biaya belum diketahui | Marks the cost UNKNOWN; it is then shown as "Biaya perlu dilengkapi pemilik" |
| `costing.field.cost_unknown_help` | Pemilik akan melengkapinya nanti. | BR-COST-03, D-033 |
| `costing.field.zero_evidence` | Bukti atau alasan biaya nol | Owner only (BR-COST-04) |

### 9.2 UXP-401 Stok

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-213 | Field | `inventory.stock.search` | Cari produk | With `catalog.products.search_hint` |
| UXI-214 | Filters | `inventory.stock.filter_state`; `catalog.field.category` | Keadaan stok; Kategori | Values: Habis, Menipis, Normal |
| UXI-215 | Refresh | `ux.list.updated_at`; `ux.action.reload` | Diperbarui :waktu; Muat ulang | BR-INV-41 |
| UXI-216 | Columns | `inventory.stock.col_product`; `catalog.field.sku`; `inventory.stock.col_stock`; `catalog.field.min_stock`; `inventory.stock.col_state` | Produk; SKU; Stok; Stok minimum; Keadaan | — |
| UXI-217 | Row overflow | `inventory.adjustment.title` | Sesuaikan stok | Owner only |
| UXI-218 | Paging | `ux.action.load_more` | Muat lebih banyak | — |
| — | Empty | `inventory.stock.empty` | Belum ada produk. | — |

### 9.3 UXP-402 Riwayat stok

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-219 | Filters | `inventory.history.filter_product`; `inventory.history.filter_type`; `inventory.history.filter_date` | Produk; Jenis mutasi; Tanggal | Types: section 9.1 |
| UXI-220 | Columns | `inventory.history.col_time`; `inventory.history.col_product`; `inventory.history.col_type`; `inventory.history.col_quantity`; `inventory.history.col_actor`; `inventory.history.col_source` | Waktu; Produk; Jenis; Jumlah; Oleh; Sumber | The quantity is signed, for example +5 pcs or −2 pcs |
| UXI-221 | Button | `inventory.adjustment.title` | Sesuaikan stok | Owner only |
| UXI-222 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| UXI-223 | Back | `ux.action.back_to` | Kembali ke :halaman | — |
| — | Empty | `inventory.history.empty` | Belum ada mutasi stok. | — |

### 9.4 UXP-403 Detail mutasi stok

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Fields | `inventory.movement.type`; `inventory.movement.product`; `inventory.movement.quantity`; `inventory.movement.time`; `inventory.movement.actor`; `inventory.movement.reason`; `inventory.movement.reference` | Jenis; Produk; Jumlah; Waktu; Oleh; Alasan; Referensi | — |
| UXI-224 | Source | `inventory.movement.source`; `inventory.movement.source_receipt`; `inventory.movement.source_issue`; `inventory.movement.source_sale`; `inventory.movement.source_opening`; `inventory.movement.source_adjustment`; `inventory.movement.source_return`; `inventory.movement.source_pair` | Sumber; Barang masuk :waktu; Barang keluar :waktu; Penjualan :nomor; Stok awal :waktu; Penyesuaian stok :waktu; Retur penjualan :nomor; :jenis :waktu | For Ops a Sale source is text without a link (SEC-AZ-03) |
| — | Reversal links | `inventory.movement.reversed_by`; `inventory.movement.reverses` | Dibatalkan oleh mutasi :waktu; Membatalkan mutasi :waktu | BR-INV-34 |
| UXI-225 | Section | `costing.evidence.title` | Bukti biaya | Owner only |
| UXI-225 | Status | `costing.status.known`; `costing.status.unknown`; `costing.status.zero` | Biaya diketahui; Biaya perlu dilengkapi pemilik; Biaya nol (berbukti) | D-033, BR-COST-04 |
| UXI-225 | Fields | `costing.evidence.total`; `costing.evidence.corrections` | Total biaya; Koreksi | — |
| UXI-226 | Button | `costing.correction.title_correct`; `costing.correction.title_complete` | Koreksi biaya; Lengkapi biaya | "Lengkapi biaya" when UNKNOWN |
| UXI-227 | Button | `inventory.reversal.title` | Batalkan mutasi | Owner only |
| UXI-228 | Button | `inventory.reversal.title` | Batalkan mutasi | For an OPENING while onboarding is active (D-087, T-17 b); the replacement entry follows on UXP-413 (`inventory.opening_entry.replace`) |
| UXI-229 | Back | `ux.action.back_to` | Kembali ke :halaman | — |

### 9.5 UXP-404 Barang masuk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-230 | Filter | `inventory.receipts.filter_date` | Tanggal | — |
| UXI-230 | Search | `inventory.receipts.search` | Cari referensi atau produk | Changed: was "Referensi atau produk" (a search field says what it searches) |
| UXI-231 | Button | `inventory.receipt_new.title` | Catat barang masuk | — |
| UXI-232 | Columns | `inventory.receipts.col_time`; `inventory.receipts.col_reference`; `inventory.receipts.col_lines`; `inventory.receipts.col_actor` | Waktu; Referensi; Baris; Oleh | — |
| UXI-233 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| — | Empty | `inventory.receipts.empty` | Belum ada barang masuk. | — |

### 9.6 UXP-405 Catat barang masuk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Draft note | `inventory.receipt_new.draft_note` | Draf ini hanya ada di halaman ini. Jika halaman dimuat ulang atau ditutup, atau sesi masuk berakhir, draf hilang. Pecah barang masuk yang panjang menjadi beberapa dokumen. | D-069 (a), BR-INV-17 |
| UXI-234 | Field | `inventory.line.search` | Cari atau scan produk | — |
| UXI-235 | Button | `catalog.product_create.title` | Tambah produk | Changed: was "Buat produk baru" (CW-04); opens UXP-303 over this page with the code filled in |
| UXI-236 | Line fields | `inventory.line.quantity`; `costing.field.line_cost`; `costing.field.cost_unknown` | Jumlah; Biaya; Biaya belum diketahui | With the hints of section 9.1 |
| UXI-236 | Owner, Biaya 0 | `costing.field.zero_evidence` | Bukti atau alasan biaya nol | — |
| UXI-237 | Line button | `ux.action.remove_line` | Hapus baris | — |
| UXI-238 | Fields | `inventory.receipt_new.reference`, `inventory.receipt_new.document_date`, each + `ux.action.optional` | Referensi (opsional); Tanggal dokumen (opsional) | The keys hold "Referensi" and "Tanggal dokumen"; the optional marker is appended (section 5) |
| UXI-238 | Hint | `inventory.receipt_new.document_date_help` | Hanya catatan. Waktu barang masuk tetap waktu konfirmasi. | BR-INV-05 |
| UXI-239 | Button | `ux.action.review` | Tinjau | — |
| UXI-240 | Review dialog, title | `inventory.receipt_new.review_title` | Tinjau barang masuk | — |
| UXI-240 | Review dialog, summary | `inventory.receipt_new.review_lines`; `inventory.receipt_new.review_unknown` | :jumlah baris.; :jumlah baris dengan biaya belum diketahui. | The unknown-cost lines are highlighted |
| UXI-240 | Review dialog, note | `inventory.receipt_new.review_note` | Setelah dikonfirmasi, stok bertambah dan dokumen ini tidak bisa diubah. Biayanya hanya bisa dikoreksi Pemilik. | BR-INV-14, D-033 |
| UXI-240 | Review dialog, confirm | `inventory.receipt_new.confirm` | Konfirmasi barang masuk | — |
| UXI-241 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-242 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.stock_posting.must_not` | Sections 12 and 14 | — |
| UXI-243 | Button | `ux.action.cancel` | Batal | The unsaved-changes dialog uses `ux.state.unsaved.body_receiving` |
| UXI-244 | Draft saving | — | No copy | Not provided by decision (D-069 (a)) |
| UXI-245 | Back | `ux.action.back_to` | Kembali ke Barang masuk | Changed: was "Kembali ke daftar barang masuk" (CW-04) |
| — | Success | `inventory.receipt_new.success` | Barang masuk tercatat. | — |

### 9.7 UXP-406 Detail barang masuk

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Fields | `inventory.receipts.col_time`; `inventory.receipts.col_reference`; `inventory.receipt_new.document_date`; `inventory.receipts.col_actor` | Waktu; Referensi; Tanggal dokumen; Oleh | — |
| UXI-246 | Line cost | `costing.field.purchase_cost` | Biaya | The purchase cost on a receiving document (D-033) |
| UXI-246 | After an Owner correction | `costing.mark.owner_corrected`; `costing.field.cost_now`; `costing.field.cost_at_confirmation` | Dikoreksi Pemilik; Biaya sekarang; Biaya saat dikonfirmasi | D-047; never the reason or the history for Ops and Combined |
| UXI-246 | Completed after an UNKNOWN | `costing.field.cost_was_unknown` | Saat dikonfirmasi: biaya belum diketahui | BR-INV-15 |
| UXI-247 | Owner section | `costing.field.correction_history` | Riwayat koreksi | Owner only |
| UXI-247 | Entry | `costing.field.correction_entry` | :lama → :baru · :waktu · oleh :nama · alasan: :alasan | — |
| UXI-248 | Link | `inventory.movement.title` | Detail mutasi stok | — |
| UXI-249 | Button | `costing.correction.title_correct`; `costing.correction.title_complete` | Koreksi biaya; Lengkapi biaya | Owner only |
| UXI-250 | Button | `inventory.reversal.title` | Batalkan mutasi | Owner only. Changed: was "Batalkan baris" (CW-04; "Batal" is the cancel of a form) |
| UXI-251 | Back | `ux.action.back_to` | Kembali ke Barang masuk, or Kembali ke :halaman | Changed: was "Kembali ke daftar barang masuk" (CW-04) |

### 9.8 UXP-407 Barang keluar

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-252 | Filters | `inventory.issues.filter_date`; `inventory.issues.filter_reason` | Tanggal; Alasan | Reasons: section 9.1 |
| UXI-253 | Button | `inventory.issue_new.title` | Catat barang keluar | — |
| UXI-254 | Columns | `inventory.issues.col_time`; `inventory.issues.col_reason`; `inventory.issues.col_lines`; `inventory.issues.col_actor` | Waktu; Alasan; Baris; Oleh | — |
| UXI-255 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| — | Empty | `inventory.issues.empty` | Belum ada barang keluar. | — |

### 9.9 UXP-408 Catat barang keluar

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `inventory.issue_new.intro` | Catat barang yang keluar bukan karena penjualan. | BR-INV-18, BR-INV-22 |
| UXI-256 | Field | `inventory.line.search` | Cari atau scan produk | — |
| UXI-257 | Line | `inventory.line.quantity`; `ux.action.remove_line` | Jumlah; Hapus baris | — |
| UXI-258 | Fields | `inventory.issue_new.reason`; `inventory.issue_new.note` + `ux.action.optional` | Alasan; Catatan (opsional) | The key holds "Catatan"; the optional marker is appended (section 5). Reasons: section 9.1 |
| UXI-259 | Button | `ux.action.review` | Tinjau | — |
| UXI-260 | Review dialog, title | `inventory.issue_new.review_title` | Tinjau barang keluar | — |
| UXI-260 | Review dialog, lines | `inventory.line.stock_before`; `inventory.line.change`; `inventory.line.stock_after` | Stok sebelum; Perubahan; Stok sesudah | BR-INV-19 |
| UXI-260 | Review dialog, confirm | `inventory.issue_new.confirm` | Konfirmasi barang keluar | — |
| UXI-261 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-262 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.stock_posting.must_not` | Sections 12 and 14 | — |
| UXI-263 | Button | `ux.action.cancel` | Batal | — |
| UXI-264 | Back | `ux.action.back_to` | Kembali ke Barang keluar | Changed: was "Kembali ke daftar barang keluar" (CW-04) |
| — | Success | `inventory.issue_new.success` | Barang keluar tercatat. | — |

### 9.10 UXP-409 Detail barang keluar

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Fields | `inventory.issues.col_time`; `inventory.issues.col_reason`; `inventory.issue_new.note`; `inventory.issues.col_actor` | Waktu; Alasan; Catatan; Oleh | — |
| UXI-265 | Link | `inventory.movement.title` | Detail mutasi stok | — |
| UXI-266 | Button | `inventory.reversal.title` | Batalkan mutasi | Owner only. Changed: was "Batalkan baris" (CW-04) |
| UXI-267 | Back | `ux.action.back_to` | Kembali ke Barang keluar, or Kembali ke :halaman | Changed: was "Kembali ke daftar barang keluar" (CW-04) |

### 9.11 UXP-410 Sesuaikan stok

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `inventory.adjustment.intro` | Samakan stok tercatat dengan hasil hitung fisik. | BR-INV-31 |
| UXI-268 | Field | `inventory.line.search` | Cari atau scan produk | — |
| UXI-269 | Line direction | `inventory.adjustment.add`; `inventory.adjustment.subtract` | Tambah; Kurangi | — |
| UXI-269 | Line fields | `inventory.line.quantity`; `costing.field.line_cost`; `costing.field.cost_unknown`; `costing.field.zero_evidence` | Jumlah; Biaya; Biaya belum diketahui; Bukti atau alasan biaya nol | Cost fields on a positive line only (BR-INV-32) |
| UXI-269 | Line button | `ux.action.remove_line` | Hapus baris | — |
| UXI-270 | Fields | `inventory.adjustment.reason`; `inventory.adjustment.count_reference`; `inventory.adjustment.reference` | Alasan; Referensi hitung; Referensi | All mandatory (BR-INV-31) |
| UXI-270 | Hints | `inventory.adjustment.count_reference_help`; `inventory.adjustment.reference_help` | Catatan hitung fisiknya, misalnya nomor lembar hitung.; Nomor dokumen pendukung. | — |
| UXI-271 | Button | `ux.action.review` | Tinjau | — |
| UXI-272 | Review dialog, title | `inventory.adjustment.review_title` | Tinjau penyesuaian stok | — |
| UXI-272 | Review dialog, lines | `inventory.line.stock_before`; `inventory.line.change`; `inventory.line.stock_after` | Stok sebelum; Perubahan; Stok sesudah | — |
| UXI-272 | Review dialog, confirm | `inventory.adjustment.confirm` | Konfirmasi penyesuaian | Needs password confirmation (UXS-11) |
| UXI-273 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-274 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.stock_posting.must_not` | Sections 12 and 14 | — |
| UXI-275 | Button | `ux.action.cancel` | Batal | — |
| UXI-276 | Back | `ux.action.back_to` | Kembali ke :halaman | — |
| — | Success | `inventory.adjustment.success` | Stok disesuaikan. | — |

### 9.12 UXP-411 Batalkan mutasi

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-277 | Summary | `inventory.reversal.source`; `inventory.reversal.inverse`; `inventory.reversal.stock_now`; `inventory.reversal.stock_after` | Mutasi yang dibatalkan; Mutasi kebalikannya; Stok sekarang; Stok sesudah | No quantity field (BR-INV-34) |
| UXI-277 | Explanation | `inventory.reversal.explanation` | Pembatalan mencatat mutasi kebalikan dengan jumlah yang sama persis. Mutasi asli tetap tercatat. Satu mutasi hanya bisa dibatalkan sekali. | BR-INV-34, BR-INV-35 |
| UXI-278 | Field | `inventory.reversal.reason` | Alasan | Mandatory |
| UXI-279 | Button | `ux.action.review` | Tinjau | — |
| UXI-280 | Review dialog, title | `inventory.reversal.review_title` | Tinjau pembatalan mutasi | — |
| UXI-280 | Review dialog, confirm | `inventory.reversal.confirm` | Konfirmasi pembatalan | Needs password confirmation (UXS-11) |
| UXI-281 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-282 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.reversal.must_not` | Sections 12 and 14 | — |
| UXI-283 | Button | `ux.action.cancel` | Batal | — |
| UXI-284 | Back | `ux.action.back_to` | Kembali ke :halaman | — |
| — | Success | `inventory.reversal.success` | Mutasi dibatalkan. | — |

### 9.13 UXP-412 Stok awal

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Not started | `inventory.onboarding.not_started` | Masa input stok awal belum dimulai. | — |
| — | Running | `inventory.onboarding.active_since` | Masa input stok awal aktif sejak :waktu. | With the banner of UXS-27 |
| — | Completed | `inventory.onboarding.completed` | Masa input stok awal selesai pada :waktu. Halaman ini hanya untuk dilihat. | D-071 |
| — | Completed, corrections | `inventory.onboarding.corrections_help` | Koreksi stok sekarang memakai Sesuaikan stok, Batalkan mutasi, atau Koreksi biaya. | BR-INV-29, D-071 |
| — | Held after a restore | `ux.state.onboarding_held.*` | Section 12 (UXS-50) | D-072 |
| UXI-285 | Button | `inventory.onboarding.start` | Mulai masa input stok awal | D-071 |
| UXI-285 | Confirmation, title | `inventory.onboarding.start_confirm.title` | Mulai masa input stok awal? | — |
| UXI-285 | Confirmation, body | `inventory.onboarding.start_confirm.body` | Selama masa ini, penjualan dan pencatatan stok biasa berhenti sampai Anda mengakhirinya. Masa input stok awal hanya bisa dijalankan sekali untuk data usaha ini. | BR-INV-24, D-071 |
| UXI-285 | Confirmation, buttons | `inventory.onboarding.start`; `ux.action.cancel` | Mulai masa input stok awal; Batal | Needs password confirmation (UXS-11) |
| UXI-285 | Success | `inventory.onboarding.started` | Masa input stok awal dimulai. | — |
| UXI-286 | Button | `inventory.opening_entry.title` | Input stok awal | — |
| UXI-287 | Checklist | `inventory.onboarding.checklist` | Daftar periksa stok awal | — |
| UXI-287 | Filter | `inventory.onboarding.filter_counted`; `inventory.onboarding.filter_not_counted` | Sudah dihitung; Belum dihitung | — |
| UXI-287 | Columns | `inventory.onboarding.col_product`; `inventory.onboarding.col_counted`; `inventory.onboarding.col_cost` | Produk; Jumlah dihitung; Status biaya | — |
| UXI-287 | Zero count | `inventory.onboarding.zero_count` | Dihitung nol | BR-INV-27 |
| UXI-287 | Empty | `inventory.onboarding.checklist_empty` | Belum ada produk yang dihitung. | — |
| UXI-288 | Row link | `inventory.movement.title` | Detail mutasi stok | Accessible name of the row link |
| UXI-289 | Button | `inventory.onboarding.release` | Akhiri masa input stok awal | — |
| UXI-289 | Review dialog, title | `inventory.onboarding.release_review.title` | Tinjau akhir masa input stok awal | — |
| UXI-289 | Review dialog, checks | `inventory.onboarding.release_review.checks` | Sebelum mengakhiri, periksa jumlah hitung semua produk, barcode dan stok minimum, serta biaya yang masih belum diketahui. | BR-INV-28 |
| UXI-289 | Review dialog, counts | `inventory.onboarding.release_review.counts` | :dihitung produk sudah dihitung, :belum belum dihitung, :biaya baris dengan biaya belum diketahui. | — |
| UXI-289 | Review dialog, warning | `inventory.onboarding.release_review.warning` | Setelah diakhiri, masa input stok awal tidak bisa dimulai lagi, juga setelah data dipulihkan dari cadangan lama. | D-071, D-072 |
| UXI-289 | Review dialog, buttons | `inventory.onboarding.release`; `ux.action.cancel` | Akhiri masa input stok awal; Batal | Needs password confirmation (UXS-11) |
| UXI-289 | Success | `inventory.onboarding.released` | Masa input stok awal selesai. Penjualan dan pencatatan stok bisa dilakukan lagi. | — |
| UXI-290 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.onboarding.must_not` | Sections 12 and 14 | — |
| UXI-291 | Button | `inventory.onboarding.withdraw_zero` | Tarik hitungan nol | For a zero-count entry (RC-14; D-087) |
| UXI-291 | Review dialog, title | `inventory.onboarding.withdraw_zero_review.title` | Tarik hitungan nol? | — |
| UXI-291 | Review dialog, body | `inventory.onboarding.withdraw_zero_review.body` | Hitungan nol ini tetap tercatat di riwayat, lalu produk ini bisa dihitung lagi. | Append-only (D-087) |
| UXI-291 | Review dialog, reason | `inventory.onboarding.withdraw_zero_review.reason` | Alasan | RC-14 `reason` |
| UXI-291 | Review dialog, buttons | `inventory.onboarding.withdraw_zero`; `ux.action.cancel` | Tarik hitungan nol; Batal | Needs password confirmation (UXS-11) |
| UXI-291 | Success | `inventory.onboarding.zero_withdrawn` | Hitungan nol ditarik. | — |
| UXI-291 | Button | `inventory.opening_entry.replace` | Catat ulang stok awal | For a product whose OPENING was reversed (RC-48; D-087) |

### 9.14 UXP-413 Input stok awal

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-292 | Field | `inventory.line.search` | Cari atau scan produk | — |
| UXI-292 | Not eligible | `inventory.opening_entry.not_eligible` | Sudah punya mutasi stok; tidak bisa diisi stok awal. | BR-INV-26 |
| UXI-292 | Already counted zero | `inventory.opening_entry.already_zero` | Sudah dihitung nol. | Entering it again is not offered; a withdrawn zero count makes the product eligible again (D-087) |
| UXI-292 | Reversed OPENING | `inventory.opening_entry.replace_eligible` | Stok awal dibatalkan; bisa dicatat ulang. | The replacement entry of RC-48 (D-087) |
| UXI-294 | Replacement, reason | `inventory.opening_entry.replace_reason` | Alasan | RC-48 `reason`; one product per replacement |
| UXI-296 | Review dialog, title of a replacement | `inventory.opening_entry.replace_review_title` | Tinjau stok awal pengganti | RC-48 (D-087) |
| — | Success of a replacement | `inventory.opening_entry.replace_success` | Stok awal pengganti tersimpan. | — |
| UXI-293 | Button | `catalog.product_create.title` | Tambah produk | Changed: was "Buat produk baru" (CW-04) |
| UXI-294 | Row fields | `inventory.line.quantity`; `costing.field.line_cost`; `costing.field.cost_unknown`; `costing.field.zero_evidence`; `inventory.adjustment.count_reference`; `ux.action.remove_line` | Jumlah; Biaya; Biaya belum diketahui; Bukti atau alasan biaya nol; Referensi hitung; Hapus baris | Cost and count reference on positive rows |
| UXI-294 | Hint | `inventory.opening_entry.quantity_help` | Isi 0 untuk produk yang sudah dihitung tetapi tidak ada stoknya. | BR-INV-27 |
| — | Limit | `inventory.opening_entry.limit_note` | Paling banyak 100 baris. Semua baris disimpan bersama, atau tidak ada yang disimpan. | BR-INV-25 |
| UXI-295 | Button | `ux.action.review` | Tinjau | — |
| UXI-296 | Review dialog, title | `inventory.opening_entry.review_title` | Tinjau stok awal | — |
| UXI-296 | Review dialog, confirm | `inventory.opening_entry.confirm` | Konfirmasi stok awal | — |
| UXI-297 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-298 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.opening_stock.must_not` | Sections 12 and 14 | — |
| UXI-299 | Button | `ux.action.cancel` | Batal | — |
| UXI-300 | Back | `ux.action.back_to` | Kembali ke Stok awal | — |
| — | Success | `inventory.opening_entry.success` | Stok awal tersimpan. | — |

### 9.15 UXP-414 Insiden rekonsiliasi

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-301 | Filter | `inventory.incidents.filter_status` | Status | — |
| UXI-301 | Values | `inventory.incident.status_blocked`; `inventory.incident.status_reopened` | Mutasi diblokir; Mutasi dibuka kembali | Changed: was "Terbuka / Dibuka kembali" (an open incident means postings are blocked) |
| UXI-302 | Columns | `inventory.incidents.col_product`; `inventory.incidents.col_detected`; `inventory.incidents.col_status` | Produk; Ditemukan; Status | — |
| — | Empty | `inventory.incidents.empty` | Tidak ada insiden rekonsiliasi. | — |

### 9.16 UXP-415 Detail insiden

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Fields | `inventory.incident.detected`; `inventory.incident.mismatch`; `inventory.incident.blocked_product` | Ditemukan; Yang tidak cocok; Produk yang diblokir | BR-INV-45 |
| UXI-303 | Link | `inventory.history.title` | Riwayat stok | Changed: was "Riwayat stok produk" (CW-04) |
| UXI-304 | Button | `inventory.incident.reopen` | Buka kembali mutasi | — |
| UXI-304 | Confirmation, title | `inventory.incident.reopen_confirm.title` | Buka kembali mutasi :produk? | — |
| UXI-304 | Confirmation, body | `inventory.incident.reopen_confirm.body` | Lakukan ini hanya setelah fakta-faktanya diperiksa dan direkonsiliasi. Setelah dibuka kembali, produk ini bisa dijual dan dicatat lagi. | BR-INV-46 |
| UXI-304 | Confirmation, buttons | `inventory.incident.reopen`; `ux.action.cancel` | Buka kembali mutasi; Batal | Needs password confirmation (UXS-11) |
| UXI-304 | Success | `inventory.incident.reopened` | Mutasi dibuka kembali. | — |
| UXI-305 | Back | `ux.action.back_to` | Kembali ke Insiden rekonsiliasi | Changed: was "Kembali ke daftar insiden" (CW-04) |

## 10. Reports and cost (A5)

### 10.1 Report figures

Used on Beranda (UXP-007), Rekap harian (UXP-501) and Laporan keuangan (UXP-502). Every figure is the Owner's (CAP-20, CAP-22).

| Key | Copy | Hint key and hint | Source |
| --- | --- | --- | --- |
| `reporting.figure.gross_sales` | Penjualan bruto | `reporting.figure.gross_sales_help`: Nilai penjualan selesai sebelum pengembalian uang. | BR-COST-34, BR-COST-35 |
| `reporting.figure.refunds` | Pengembalian uang | — | BR-COST-36 |
| `reporting.figure.net_sales` | Penjualan neto | `reporting.figure.net_sales_help`: Penjualan bruto dikurangi pengembalian uang. | BR-COST-35 |
| `reporting.figure.sale_count` | Jumlah penjualan | — | BR-COST-34 |
| `reporting.figure.payments_cash` | Pembayaran tunai | — | CASH and BANK_TRANSFER separately (BR-COST-34, D-077) |
| `reporting.figure.payments_transfer` | Pembayaran transfer bank | — | — |
| `reporting.figure.refund_payouts_cash` | Pengembalian dibayar tunai | — | Refund payouts separately (BR-COST-34) |
| `reporting.figure.refund_payouts_transfer` | Pengembalian dibayar transfer bank | — | — |
| `reporting.figure.quantity_sold` | Barang terjual | — | — |
| `reporting.figure.quantity_returned` | Barang diretur | — | — |
| `reporting.figure.cogs` | HPP | `reporting.figure.cogs_help`: Harga pokok penjualan: biaya barang yang terjual, dikurangi biaya barang retur yang layak jual. | BR-COST-35 |
| `reporting.figure.gross_profit` | Laba kotor | `reporting.figure.gross_profit_help`: Penjualan neto dikurangi HPP. Biaya usaha lain tidak termasuk. | BR-COST-01, BR-COST-35 |
| `reporting.figure.margin` | Margin | `reporting.figure.margin_help`: Laba kotor dibagi penjualan neto. Hanya tampil bila data biaya lengkap dan penjualan neto lebih dari Rp0. | BR-COST-34 |

| Key | Copy | Source |
| --- | --- | --- |
| `reporting.as_of` | Data per :waktu WIB | BR-COST-38 |
| `reporting.complete` | Data biaya lengkap | — |
| `reporting.unknown_lines` | Baris dengan biaya belum diketahui: :jumlah | BR-COST-38 |
| `reporting.marked_products` | Produk dengan laporan ditandai belum lengkap: :jumlah | BR-COST-27, BR-COST-38 |
| `reporting.last_correction` | Koreksi biaya terakhir: :waktu | BR-COST-38 |
| `reporting.no_correction` | Belum ada koreksi biaya | — |
| `reporting.margin_hidden_zero` | Margin tidak ditampilkan karena penjualan neto Rp0 atau kurang. | BR-COST-34 |
| `reporting.cash_note` | Modal awal dan selisih kas bukan penjualan dan tidak masuk laba kotor. | BR-COST-39 |

### 10.2 UXP-501 Rekap harian

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-306 | Date control | `reporting.daily.previous_day`; `reporting.daily.next_day`; `reporting.daily.pick_date` | Hari sebelumnya; Hari berikutnya; Pilih tanggal | Asia/Jakarta days (BR-RPT-01) |
| — | Figures | Section 10.1 | — | — |
| UXI-307 | Sessions | `reporting.daily.sessions` | Sesi kas | — |
| UXI-307 | Columns | `cash.sessions.col_responsible`; `cash.sessions.col_register`; `cash.sessions.col_opened`; `cash.sessions.col_closed`; `cash.sessions.filter_status`; `cash.sessions.col_variance` | Penanggung jawab; Laci kas; Dibuka; Ditutup; Status; Selisih | BR-RPT-02 |
| UXI-307 | Crossing midnight | `reporting.daily.crosses_midnight` | Melewati tengah malam | Shown once, with its full time span (BR-RPT-02) |
| UXI-307 | Still open | `reporting.daily.active_sessions` | Masih dibuka | — |
| — | Note | `reporting.cash_note` | Modal awal dan selisih kas bukan penjualan dan tidak masuk laba kotor. | BR-RPT-01 |
| — | Empty | `reporting.daily.empty` | Belum ada penjualan pada tanggal ini. | — |

### 10.3 UXP-502 Laporan keuangan

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-308 | Periods | `reporting.period.today`; `reporting.period.last_7_days`; `reporting.period.this_month`; `reporting.period.last_month` | Hari Ini; 7 Hari; Bulan Ini; Bulan Lalu | BR-COST-37 (section 2.2) |
| UXI-308 | Legend | `reporting.period.label` | Periode | Legend of the period choice (DESIGN_SYSTEM section 13) |
| UXI-309 | Custom period | `reporting.period.custom` | Rentang Kustom | — |
| UXI-309 | Fields | `reporting.period.start`; `reporting.period.end` | Dari tanggal; Sampai tanggal | Inclusive (BR-COST-37) |
| UXI-309 | Buttons | `ux.action.apply`; `ux.action.cancel` | Terapkan; Batal | "Batal" keeps the previous period |
| — | Period shown | `reporting.period.range` | :awal – :akhir | — |
| UXI-310 | Chart | `reporting.finance.trend`; `reporting.finance.trend_label` | Tren; accessible name: Grafik tren :ukuran | `:ukuran` is a figure label of section 10.1 |
| UXI-310 | Point | `reporting.finance.trend_point` | :tanggal: :nilai | Shown and announced on pointer, focus or touch (D-018, UXA-19) |
| UXI-311 | Toggle | `reporting.finance.show_table`; `reporting.finance.show_chart` | Tampilkan sebagai tabel; Tampilkan sebagai grafik | The second label while the table is shown |
| UXI-311 | Table, date column | `reporting.finance.table_date` | Tanggal | Date column of the trend table (DESIGN_SYSTEM section 13) |
| UXI-312 | Link | `costing.worklist.title` | Biaya perlu dilengkapi | When figures are incomplete |
| — | Figures and completeness | Section 10.1; UXS-25 | — | — |
| — | Empty | `reporting.finance.empty` | Belum ada penjualan pada periode ini. | UXS-02 |

### 10.4 UXP-503 Biaya perlu dilengkapi

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Intro | `costing.worklist.intro` | Biaya yang belum diketahui dan produk yang laporannya ditandai belum lengkap. Lengkapi agar HPP dan laba kotor lengkap. | BR-COST-38 |
| UXI-313 | Filter | `costing.worklist.filter_product` | Produk | — |
| — | Columns | `costing.worklist.col_product`; `costing.worklist.col_source`; `costing.worklist.col_status` | Produk; Sumber biaya; Status | — |
| — | Status values | `costing.status.unknown`; `costing.status.marked_incomplete` | Biaya perlu dilengkapi pemilik; Laporan ditandai belum lengkap | BR-COST-27 |
| — | Source | `costing.worklist.source_entry` | :jenis :waktu | For example "Barang masuk 01 Oktober 2026, 09.15.00" |
| UXI-314 | Row button | `costing.correction.title_complete`; `costing.correction.title_correct` | Lengkapi biaya; Koreksi biaya | — |
| UXI-315 | Row link | `costing.history.title` | Riwayat biaya | — |
| UXI-316 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| — | Empty | `costing.worklist.empty` | Semua biaya sudah lengkap. | — |

### 10.5 UXP-504 Lengkapi biaya or Koreksi biaya

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Current value | `costing.correction.current` | Biaya sekarang: :biaya | `:biaya` is an amount or "Biaya perlu dilengkapi pemilik" |
| — | History | `costing.correction.history` | Riwayat | With `costing.field.correction_entry` |
| UXI-317 | Field | `costing.correction.new_cost` | Biaya baru | The total |
| UXI-317 | Hint | `costing.correction.new_cost_help` | Total biaya perolehan untuk jumlah pada sumber ini. | BR-COST-02 |
| UXI-317 | Zero cost | `costing.status.zero`; `costing.field.zero_evidence` | Biaya nol (berbukti); Bukti atau alasan biaya nol | BR-COST-04 |
| UXI-317 | Field | `costing.correction.reason` | Alasan | Mandatory |
| UXI-318 | Button | `ux.action.review` | Tinjau | — |
| UXI-319 | Review dialog, title | `costing.correction.review_title` | Tinjau koreksi biaya | — |
| UXI-319 | Review dialog, body | `costing.correction.review_body` | Biaya lama :lama → biaya baru :baru. HPP, nilai persediaan, dan laba kotor produk ini dihitung ulang. | D-034, BR-COST-23 |
| UXI-319 | Review dialog, confirm | `costing.correction.confirm` | Konfirmasi koreksi | Needs password confirmation (UXS-11) |
| UXI-319 | Success | `costing.correction.success` | Biaya disimpan. Laporan sudah dihitung ulang. | BR-COST-25 |
| UXI-320 | Review dialog | `ux.action.edit_again` | Kembali ubah | — |
| UXI-321 | Uncertain panel | `ux.action.resend`; `ux.action.check_status`; `commands.family.cost_correction.must_not` | Sections 12 and 14 | Includes SERVICE_BUSY (D-063) |
| UXI-322 | Button | `ux.action.cancel` | Batal | — |
| UXI-323 | Back | `ux.action.back_to` | Kembali ke :halaman | — |

### 10.6 UXP-505 Riwayat biaya

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Sections | `costing.evidence.title`; `costing.history.corrections`; `costing.history.average_cost`; `costing.history.inventory_value` | Bukti biaya; Koreksi; Biaya rata-rata; Nilai persediaan | Owner only (CAP-20) |
| — | Evidence entry | `costing.history.evidence_entry` | :jenis :waktu · :jumlah · :biaya | — |
| UXI-324 | Row button | `costing.correction.title_correct`; `costing.correction.title_complete` | Koreksi biaya; Lengkapi biaya | — |
| UXI-325 | Back | `ux.action.back_to` | Kembali ke :halaman | — |

## 11. Settings (A6)

### 11.1 UXP-601 Pengaturan

| UXI | Element | Key | Copy | Description key and text |
| --- | --- | --- | --- | --- |
| UXI-326 | Link | `access.users.title` | Pengguna | `config.settings.users_desc`: Akun staf, peran, dan tautan kata sandi. |
| UXI-327 | Link | `config.profile.title` | Profil usaha | `config.settings.profile_desc`: Nama toko, logo, dan teks pada struk dan dokumen. |
| UXI-328 | Link | `cash.registers.title` | Laci kas | `config.settings.registers_desc`: Laci kas yang dipakai untuk sesi kas. |
| UXI-329 | Link | `config.system_status.title` | Status sistem | `config.settings.status_desc`: Cadangan data, pemulihan, ruang penyimpanan, dan rekonsiliasi. |
| UXI-330 | Link | `audit.activity.title` | Riwayat aktivitas | `config.settings.activity_desc`: Siapa mengubah apa, dan kapan. |
| UXI-331 | Link; changed: was "Log keamanan" | `audit.security.title` | Riwayat keamanan | `config.settings.security_desc`: Upaya masuk, tautan kata sandi, dan akses yang ditolak. |

### 11.2 UXP-602 Pengguna

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-332 | Filters | `access.users.filter_status`; `access.users.filter_role` | Status; Peran | Values: section 3 |
| UXI-333 | Button | `access.user_create.title` | Tambah pengguna | — |
| UXI-334 | Columns | `access.users.col_name`; `access.users.col_email`; `access.users.col_roles`; `access.users.col_status` | Nama; Email; Peran; Status | With `access.user.holds_session` |
| UXI-335 | Back | `ux.action.back_to` | Kembali ke Pengaturan | — |
| — | Empty | `access.users.empty` | Belum ada akun staf. | — |

### 11.3 UXP-603 Tambah pengguna

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-336 | Fields | `access.user_form.name`; `access.user_form.email`; `access.user_form.roles` | Nama tampilan; Email; Peran | Roles: Kasir, Admin Operasional, or both; never Pemilik (CAP-33) |
| UXI-336 | Hints | `access.email_help`; `access.role.choice_help`; `access.role.*.description` | Section 6.6 and section 3 | — |
| UXI-337 | Button | `access.user_create.submit` | Simpan dan buat tautan | Needs password confirmation (UXS-11) |
| UXI-337 | Hint | `access.user_create.submit_help` | Akun dibuat, lalu tautan kata sandi pertama ditampilkan sekali untuk Anda serahkan langsung. | SEC-ACC-05 |
| — | Email held by another account | `access.user_form.email_taken` | Email ini sudah dipakai akun lain. | SEC-ACC-03 |
| — | Email held by a deactivated account | `access.user_form.email_taken_deactivated` | Email ini masih dipakai akun nonaktif. Aktifkan kembali akun itu, atau anonimkan dulu agar emailnya bisa dipakai. | SEC-ACC-03, NAVIGATION_CONTRACTS UXP-603 |
| — | Success | `access.user_create.success` | Pengguna :nama ditambahkan. | Then UXP-606 shows the link once |
| UXI-338 | Button | `ux.action.cancel` | Batal | — |
| UXI-339 | Back | `ux.action.back_to` | Kembali ke Pengguna | Changed: was "Kembali ke daftar pengguna" (CW-04) |

### 11.4 UXP-604 Detail pengguna

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Fields | `access.account.name`; `access.account.email`; `access.account.roles`; `access.users.col_status` | Nama tampilan; Email; Peran; Status | — |
| — | Link state | `access.user_detail.link_status`; `access.link.status_none`; `access.link.status_active` | Tautan kata sandi; Tidak ada tautan aktif; Aktif sampai :waktu | SEC-ACC-04 |
| — | Password state | `access.user_detail.password_not_set` | Belum mengatur kata sandi | SEC-ACC-02 |
| — | The Owner's own account | `access.user_detail.owner_note` | Peran akun Pemilik tidak bisa diubah, dan akun ini tidak bisa dinonaktifkan atau dianonimkan dari aplikasi. | D-048; explains the absence of UXI-348 |
| UXI-340 | Section | `access.user_detail.open_session` | Sesi kas yang masih dibuka | — |
| UXI-340 | Links | `cash.session_detail.title`; `cash.assisted_close.title` | Detail sesi kas; Tutup sesi oleh Pemilik | Changed: "Lihat sesi" became "Detail sesi kas" (CW-04) |
| UXI-341 | Button | `access.user_edit.title` | Ubah pengguna | Changed: was "Ubah" (CW-04) |
| UXI-342 | Button | `access.link.issue_reset` | Buat tautan atur ulang kata sandi | — |
| UXI-342 | Confirmation, title | `access.link.issue_reset_confirm.title` | Buat tautan atur ulang kata sandi untuk :nama? | — |
| UXI-342, UXI-343 | Confirmation, body | `access.link.issue_confirm.body` | Tautan berlaku 60 menit dan hanya bisa dipakai sekali. Tautan lama yang masih aktif tidak berlaku lagi. Tautan ditampilkan sekali saja untuk Anda serahkan langsung. | SEC-ACC-04, SEC-ACC-05 |
| UXI-342, UXI-343 | Confirmation, buttons | `access.link.issue`; `ux.action.cancel` | Buat tautan; Batal | Needs password confirmation (UXS-11) |
| UXI-343 | Button | `access.link.reissue_first` | Buat ulang tautan kata sandi pertama | — |
| UXI-343 | Confirmation, title | `access.link.reissue_first_confirm.title` | Buat ulang tautan kata sandi pertama untuk :nama? | — |
| UXI-344 | Button | `access.link.revoke` | Cabut tautan | — |
| UXI-344 | Confirmation, title | `access.link.revoke_confirm.title` | Cabut tautan kata sandi :nama? | — |
| UXI-344 | Confirmation, body | `access.link.revoke_confirm.body` | Tautan ini tidak bisa dipakai lagi. Anda dapat membuat tautan baru bila perlu. | — |
| UXI-344 | Confirmation, buttons | `access.link.revoke`; `ux.action.cancel` | Cabut tautan; Batal | — |
| UXI-344 | Success | `access.link.revoked` | Tautan dicabut. | — |
| UXI-345 | Button | `ux.action.deactivate` | Nonaktifkan | — |
| UXI-345 | Confirmation, title | `access.user.deactivate_confirm.title` | Nonaktifkan :nama? | — |
| UXI-345 | Confirmation, body | `access.user.deactivate_confirm.body` | Akun ini tidak bisa masuk sampai diaktifkan kembali. Semua sesi masuk dan tautannya berakhir sekarang. | SEC-ACC-08, BR-USR-10 |
| UXI-345 | Confirmation, open cash session | `access.user.deactivate_confirm.open_session` | Sesi kas :nomor tetap dibuka sampai Anda menutupnya dengan Tutup sesi oleh Pemilik. | D-069 (d) |
| UXI-345 | Confirmation, buttons | `ux.action.deactivate`; `ux.action.cancel` | Nonaktifkan; Batal | — |
| UXI-345 | Success | `access.user.deactivated` | Akun dinonaktifkan. | — |
| UXI-346 | Button | `ux.action.reactivate` | Aktifkan kembali | — |
| UXI-346 | Confirmation, title | `access.user.reactivate_confirm.title` | Aktifkan kembali :nama? | — |
| UXI-346 | Confirmation, body | `access.user.reactivate_confirm.body` | Akun ini bisa dipakai lagi dengan perannya sekarang. | BR-USR-10 |
| UXI-346 | Success | `access.user.reactivated` | Akun diaktifkan kembali. | — |
| UXI-347 | Button | `access.user.anonymize` | Anonimkan akun | — |
| UXI-347 | Confirmation, title | `access.user.anonymize_confirm.title` | Anonimkan akun :nama? | — |
| UXI-347 | Confirmation, body | `access.user.anonymize_confirm.body` | Nama dan email akun ini diganti dengan label netral, dan ini tidak bisa dibatalkan. Akun tetap nonaktif dan tidak bisa dipakai lagi. Catatan lama tetap menyimpan nama saat itu. | D-043, BR-USR-11, BR-USR-12 |
| UXI-347 | Confirmation, field | `access.user.anonymize_reason` | Alasan | Mandatory |
| UXI-347 | Confirmation, buttons | `access.user.anonymize`; `ux.action.cancel` | Anonimkan akun; Batal | — |
| UXI-347 | Success | `access.user.anonymized` | Akun dianonimkan. | — |
| UXI-348 | Controls on the active Owner account | — | No copy | Not provided by rule (CAP-33); see `access.user_detail.owner_note` |
| UXI-349 | Back | `ux.action.back_to` | Kembali ke Pengguna, or Kembali ke Riwayat aktivitas | Changed: was "Kembali ke daftar pengguna" (CW-04) |

### 11.5 UXP-605 Ubah pengguna

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-350 | Fields | `access.user_form.name`; `access.user_form.email`; `access.user_form.roles` | Nama tampilan; Email; Peran | — |
| UXI-350 | Hints | `access.user_edit.email_change_help`; `access.user_edit.role_change_help` | Mengubah email mencabut tautan kata sandi yang masih aktif.; Mengubah peran mengakhiri semua sesi masuk akun ini. | SEC-ACC-04, SEC-SESS-04 |
| UXI-351 | Button | `ux.action.save_changes` | Simpan perubahan | Needs password confirmation (UXS-11) |
| UXI-351 | Success | `access.user_edit.success` | Perubahan pengguna disimpan. | — |
| UXI-352 | Button | `ux.action.cancel` | Batal | — |
| UXI-353 | Back | `ux.action.back_to` | Kembali ke Detail pengguna | Changed: capital D (CW-03) |

### 11.6 UXP-606 Tautan kata sandi

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-354 | Purpose | `access.link_handover.for`; `access.link.purpose_first`; `access.link.purpose_reset` | Tautan :tujuan untuk :nama; kata sandi pertama; atur ulang kata sandi | — |
| UXI-354 | Expiry | `access.link_handover.expires` | Berlaku sampai :waktu (60 menit). | SEC-ACC-04 |
| UXI-354 | Shown once | `access.link_handover.once` | Tautan ini hanya ditampilkan sekali. | SEC-ACC-05 |
| UXI-354 | Code image | `access.link_handover.qr_alt` | Kode QR tautan kata sandi untuk :nama | — |
| UXI-355 | Button | `access.link_handover.copy` | Salin tautan | — |
| UXI-355 | Announcement | `access.link_handover.copied` | Tautan disalin. | — |
| UXI-356 | Guidance | `access.link_handover.guidance` | Serahkan tautan ini langsung kepada :nama. Minta :nama segera memberi tahu Anda jika kata sandinya ternyata sudah diatur orang lain. | THR-21, D-056 |
| UXI-357 | Button | `ux.action.done` | Selesai | — |

### 11.7 UXP-607 Profil usaha

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Fields | `config.profile.shop_name`; `config.profile.address`; `config.profile.contact`; `config.profile.email`; `config.profile.header_text`; `config.profile.footer_text`; `config.profile.document_label`; `config.profile.logo` | Nama toko; Alamat; Kontak; Email; Teks atas dokumen; Teks bawah dokumen; Label dokumen; Logo | Changed: "Kepala dokumen", "Kaki dokumen" and "Identitas dokumen" became "Teks atas dokumen", "Teks bawah dokumen" and "Label dokumen" (plain words for what is printed) |
| — | Versions | `config.profile.current_version`; `config.profile.versions` | Versi yang berlaku; Versi sebelumnya | BR-CFG-02 |
| UXI-358 | Button | `config.profile_edit.title` | Ubah profil usaha | — |
| UXI-359 | Version row | `config.profile.version_row`; `config.profile.version_expand` | Versi :nomor · :waktu · oleh :nama; accessible name: Tampilkan isi versi :nomor | Versions are never deleted |
| UXI-360 | Back | `ux.action.back_to` | Kembali ke Pengaturan | — |

### 11.8 UXP-608 Ubah profil usaha

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-361 | Fields | Section 11.7 | Nama toko; Alamat; Kontak; Email (opsional); Teks atas dokumen; Teks bawah dokumen; Label dokumen | Lengths: TECHNICAL_RULES section 11.3 |
| UXI-361 | Hints | `config.profile.header_text_help`; `config.profile.footer_text_help`; `config.profile.document_label_help` | Dicetak di bagian atas struk dan dokumen.; Dicetak di bagian bawah struk dan dokumen, misalnya ucapan terima kasih.; Awalan atau label yang dicetak bersama nomor dokumen. | BR-CFG-01, D-062 |
| UXI-362 | Logo | `config.profile.choose_logo`; `config.profile.replace_logo` | Pilih logo; Ganti logo | — |
| UXI-362 | Hint | `config.profile.logo_rules` | PNG, JPEG, atau WebP, paling besar 5 MB. | BR-CFG-04 |
| UXI-363 | Accent | `config.profile.accent` | Warna aksen | The accent set of DESIGN_SYSTEM section 3.3 (D-086, D-087) |
| UXI-363 | Options | `config.profile.accent_option.green`; `config.profile.accent_option.teal`; `config.profile.accent_option.blue`; `config.profile.accent_option.purple`; `config.profile.accent_option.graphite` | Hijau; Toska; Biru; Ungu; Grafit | The colour is never named by colour alone: each option shows its name (UXA-18) |
| UXI-364 | Button | `config.profile_edit.submit` | Simpan sebagai versi baru | Needs password confirmation (UXS-11) |
| UXI-364 | Hint | `config.profile_edit.version_help` | Struk dan dokumen lama tetap memakai versi saat dibuat. | BR-CFG-03 |
| UXI-364 | Success | `config.profile_edit.success` | Profil usaha disimpan sebagai versi baru. | — |
| UXI-365 | Button | `ux.action.cancel` | Batal | — |
| UXI-366 | Back | `ux.action.back_to` | Kembali ke Profil usaha | — |

### 11.9 UXP-609 Laci kas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-367 | Button | `cash.register_form.title_new` | Tambah laci kas | — |
| — | Columns | `cash.registers.col_code`; `cash.registers.col_label`; `cash.registers.col_status`; `cash.registers.col_session` | Kode; Label; Status; Sesi kas | Status: Aktif or Nonaktif |
| — | Session values | `cash.registers.session_open`; `cash.registers.session_none` | Dibuka oleh :nama; Tidak ada | — |
| UXI-368 | Row button | `cash.register_form.title_edit`; `cash.register_form.edit_named` | Ubah laci kas; accessible name: Ubah laci kas :laci | Changed: was "Ubah" (CW-04) |
| UXI-369 | Back | `ux.action.back_to` | Kembali ke Pengaturan | — |
| — | Empty | `cash.registers.empty` | Belum ada laci kas. | — |

### 11.10 UXP-610 Tambah laci kas or Ubah laci kas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-370 | Fields | `cash.register_form.code`; `cash.register_form.label`; `cash.register_form.active` | Kode; Label; Aktif | — |
| UXI-370 | Hint | `cash.register_form.help` | Kode, label, dan status hanya bisa diubah saat tidak ada sesi kas yang dibuka di laci kas ini. | BR-CASH-02 |
| UXI-371 | Button | `ux.action.save` | Simpan | Needs password confirmation (UXS-11) |
| UXI-371 | Success | `cash.register_form.success` | Laci kas disimpan. | — |
| UXI-372 | Button | `ux.action.cancel` | Batal | — |
| UXI-373 | Back | `ux.action.back_to` | Kembali ke Laci kas | Changed: was "Kembali ke daftar laci kas" (CW-04) |

### 11.11 UXP-611 Status sistem

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| — | Items | `config.system_status.backup`; `config.system_status.restore`; `config.system_status.disk`; `config.system_status.reconciliation` | Cadangan data terakhir; Uji pemulihan terakhir; Ruang penyimpanan; Rekonsiliasi harian | REQ-OPS-04. The evidence behind each item: OPERATIONS_RUNBOOK section 5 |
| — | Item | `config.system_status.backup_store` | Penyimpanan cadangan | The backup store's usage against its free allowance (ADR-010 item 11), shown by the Owner's answer of 19 October 2026 (section 22); its evidence: OPERATIONS_RUNBOOK section 5 |
| — | Values | `config.system_status.ok_at`; `config.system_status.failed_at`; `config.system_status.age`; `config.system_status.disk_free` | Berhasil :waktu; Gagal :waktu; :durasi yang lalu; Tersisa :ukuran dari :total | — |
| — | Value | `config.system_status.backup_store_usage` | Terpakai :ukuran dari :total | `:total` is the store's free allowance |
| — | Not proven | `ux.state.unverified.label` | Belum Terverifikasi | UXS-43; never a passing look |
| UXI-374 | Button | `ux.action.reload` | Muat ulang | — |
| UXI-375 | Link | `inventory.incidents.title` | Insiden rekonsiliasi | When an incident is open |
| UXI-376 | Back | `ux.action.back_to` | Kembali ke Pengaturan | — |

### 11.12 UXP-612 Riwayat aktivitas

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-377 | Filters | `audit.activity.filter_date`; `audit.activity.filter_user`; `audit.activity.filter_type`; `audit.activity.filter_product`; `audit.activity.filter_session`; `audit.activity.filter_reference` | Tanggal; Pengguna; Jenis; Produk; Sesi kas; Referensi | REQ-AUD-02 |
| UXI-378 | Columns | `audit.activity.col_time`; `audit.activity.col_user`; `audit.activity.col_action`; `audit.activity.col_reference` | Waktu; Pengguna; Tindakan; Referensi | — |
| UXI-378 | Expanded row | `audit.activity.details`; `audit.activity.changes`; `audit.activity.reason` | Rincian; Perubahan; Alasan | Accessible name of the expander: `audit.activity.expand`, "Tampilkan rincian" |
| — | Action labels | `audit.action.*` | Section 11.14 | — |
| UXI-379 | Reference link | `audit.activity.reference_link` | :referensi | The object's number or name; an object without a page is plain text |
| UXI-380 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| UXI-381 | Back | `ux.action.back_to` | Kembali ke Pengaturan | — |
| — | Empty | `audit.activity.empty` | Belum ada aktivitas. | — |

### 11.13 UXP-613 Riwayat keamanan

| UXI | Element | Key | Copy | Note |
| --- | --- | --- | --- | --- |
| UXI-382 | Filters | `audit.security.filter_date`; `audit.security.filter_type`; `audit.security.filter_user` | Tanggal; Jenis; Pengguna | — |
| UXI-383 | Columns | `audit.security.col_time`; `audit.security.col_type`; `audit.security.col_actor`; `audit.security.col_subject`; `audit.security.col_outcome`; `audit.security.col_address` | Waktu; Jenis; Pengguna; Akun terkait; Hasil; Alamat jaringan | Never a secret, token or password (SEC-LOG-03) |
| UXI-383 | Event types | `audit.security.type.sign_in_succeeded`; `.sign_in_failed`; `.throttled`; `.link_failed`; `.password_confirmation_failed`; `.csrf_failed`; `.authorization_refused`; `.session_revoked`; `.operator_procedure_run` | Berhasil masuk; Gagal masuk; Dibatasi karena terlalu banyak percobaan; Tautan kata sandi gagal dipakai; Konfirmasi kata sandi gagal; Permintaan dari halaman lama ditolak; Akses ditolak; Sesi masuk diakhiri; Prosedur operator dijalankan | The nine values of SCHEMA section 5.4 |
| UXI-383 | Actors | `audit.security.actor.anonymous`; `ux.actor.operator`; `audit.security.actor.scheduled` | Belum masuk; Prosedur operator; Tugas terjadwal | A user shows the display name |
| UXI-383 | Outcome | `audit.security.outcome.succeeded`; `audit.security.outcome.refused` | Berhasil; Ditolak | The labels of other outcome codes come with the codes (RF-5, section 21.2) |
| UXI-384 | Paging | `ux.action.next_page`; `ux.action.previous_page` | Berikutnya; Sebelumnya | — |
| UXI-385 | Back | `ux.action.back_to` | Kembali ke Pengaturan | — |
| — | Empty | `audit.security.empty` | Belum ada catatan keamanan. | — |

### 11.14 Audit action labels

The business audit records an action code per event (SCHEMA section 5.4); the list of codes is INTERACTION_CONTRACTS section 8. Each code takes the label of its family below, so that Riwayat aktivitas reads in shop words.

| Key | Copy | Family (TECHNICAL_RULES section 7.2; SECURITY_MODEL SEC-LOG-02) |
| --- | --- | --- |
| `audit.action.sale` | Penjualan | Sale checkout |
| `audit.action.open_session` | Buka sesi kas | Open cash session |
| `audit.action.close_session` | Tutup sesi kas | Close, own |
| `audit.action.assisted_close` | Tutup sesi oleh Pemilik | Close, Owner-assisted |
| `audit.action.refund` | Pengembalian uang dan retur | Refund or return |
| `audit.action.receipt` | Barang masuk | Receiving confirmation |
| `audit.action.manual_issue` | Barang keluar | Manual issue |
| `audit.action.adjustment` | Penyesuaian stok | Adjustment |
| `audit.action.opening_stock` | Stok awal | Opening stock |
| `audit.action.reversal` | Pembatalan mutasi | Reversal |
| `audit.action.cost_correction` | Koreksi biaya | Cost correction or completion |
| `audit.action.cost_incomplete_mark` | Laporan biaya ditandai belum lengkap | The mark of BR-COST-27 |
| `audit.action.product_create` | Produk ditambahkan | Product creation |
| `audit.action.product_edit` | Produk diubah | Product edit |
| `audit.action.price_change` | Harga jual diubah | Price change |
| `audit.action.product_deactivate`; `audit.action.product_reactivate` | Produk dinonaktifkan; Produk diaktifkan kembali | — |
| `audit.action.category_create`; `audit.action.category_rename`; `audit.action.category_deactivate`; `audit.action.category_reactivate` | Kategori ditambahkan; Nama kategori diubah; Kategori dinonaktifkan; Kategori diaktifkan kembali | — |
| `audit.action.photo_change`; `audit.action.photo_remove` | Foto produk diubah; Foto produk dilepas | — |
| `audit.action.barcode_add`; `audit.action.barcode_generate`; `audit.action.barcode_retire` | Barcode ditambahkan; Barcode internal dibuat; Barcode dipensiunkan | — |
| `audit.action.barcode_correction` | Koreksi salah pasang barcode | — |
| `audit.action.csv_import` | Impor produk (CSV) | — |
| `audit.action.onboarding_start`; `audit.action.onboarding_release` | Masa input stok awal dimulai; Masa input stok awal diakhiri | — |
| `audit.action.reconciliation_block`; `audit.action.reopen` | Mutasi diblokir rekonsiliasi; Mutasi dibuka kembali | — |
| `audit.action.review_note` | Catatan tinjauan ditambahkan | — |
| `audit.action.profile_change` | Profil usaha diubah | — |
| `audit.action.register_create`; `audit.action.register_change` | Laci kas ditambahkan; Laci kas diubah | — |
| `audit.action.user_create`; `audit.action.role_change`; `audit.action.details_change` | Pengguna ditambahkan; Peran diubah; Nama atau email diubah | — |
| `audit.action.user_deactivate`; `audit.action.user_reactivate`; `audit.action.user_anonymize` | Akun dinonaktifkan; Akun diaktifkan kembali; Akun dianonimkan | — |
| `audit.action.link_issue`; `audit.action.link_revoke`; `audit.action.link_consume` | Tautan kata sandi dibuat; Tautan kata sandi dicabut; Tautan kata sandi dipakai | Never the token |
| `audit.action.password_change` | Kata sandi diubah | — |
| `audit.action.owner_recovery`; `audit.action.owner_replacement` | Pemulihan akses Pemilik; Penggantian Pemilik | Operator procedures |
| `audit.action.print`; `audit.action.export` | Dokumen dicetak; Laporan diunduh | BR-AUD-06 |
| `audit.action.opening_stock_replace` | Stok awal dicatat ulang | `OPENING_STOCK_REPLACE`, RC-48 (D-087) |
| `audit.action.zero_count_withdraw` | Hitungan nol ditarik | `ZERO_COUNT_WITHDRAW`, RC-14 (D-087) |
| `audit.action.onboarding_restore_hold` | Stok awal ditahan setelah pemulihan | `ONBOARDING_RESTORE_HOLD`, OP-04 (D-072) |
| `audit.action.restore_reconciliation` | Rekonsiliasi pemulihan | `RESTORE_RECONCILIATION`, OP-05 (D-072, D-073) |
| `audit.action.projection_rebuilt` | Data stok dan biaya dibangun ulang dari mutasi | `PROJECTION_REBUILT`, OP-07 (D-088) |

## 12. States

The states of [UI_STATES](UI_STATES.md) section 2, with their wording. The parent key is the copy key UI_STATES gives each state. A state shows its title, its body and, where it has one, its action; a state that only marks a value (UXS-24, UXS-26, UXS-32, UXS-43) shows its label. "Changed" refers to the working label.

| State | Key | Title or label | Body | Action | Changed |
| --- | --- | --- | --- | --- | --- |
| UXS-01 Loading | `ux.state.loading` | Memuat | Accessible: Memuat data | — | — |
| UXS-02 Empty | `ux.state.empty` | Belum ada data | The page's own empty text, from sections 6 to 11 | The page's primary action, when the role holds it | — |
| UXS-03 No results | `ux.state.no_results` | Tidak ada hasil | Tidak ada yang cocok dengan pencarian atau filter ini. | Hapus filter | — |
| UXS-04 Committed | `ux.state.committed` | Berhasil disimpan | The page's success text, which names what was saved (sections 6 to 11). A Sale shows its receipt (BR-SALE-14) | — | — |
| UXS-05 Validation error | `ux.state.validation` | Periksa isian | Ada isian yang perlu diperbaiki. Tidak ada yang disimpan. | — (each field shows its message, section 16) | — |
| UXS-06 Submitting | `ux.state.submitting` | Sedang diproses | After a few seconds, `ux.state.submitting.still`: Masih diproses. Tunggu sebentar. | — | — |
| UXS-07 Permission denied | `ux.state.forbidden` | Anda tidak memiliki akses | Halaman atau tindakan ini tidak tersedia untuk akun Anda. | Kembali ke halaman awal | — |
| UXS-08 Not found | `ux.state.not_found` | Tidak ditemukan | Data yang Anda cari tidak ditemukan. | Kembali ke :halaman, or Kembali ke halaman awal | The working label became the body; one text for missing and outside scope (TR-ERR-03) |
| UXS-09 Sign-in session ended | `ux.state.signed_out` | Sesi masuk berakhir | Sesi masuk Anda sudah berakhir. Masuk lagi untuk melanjutkan. Sesi kas yang masih dibuka tidak ikut tertutup. | — (shown on UXP-001) | — |
| UXS-10 Page must be reloaded | `ux.state.reload` | Muat ulang halaman | Halaman ini perlu dimuat ulang sebelum dapat dipakai. Tidak ada yang disimpan. | Muat ulang | — |
| UXS-11 Recent authentication | `ux.state.recent_auth` | Konfirmasi kata sandi | Untuk melanjutkan, masukkan kata sandi Anda. Isian dan tinjauan Anda tetap seperti semula. | Konfirmasi (`ux.state.recent_auth.confirm`); Batal. Field: Kata sandi (`ux.state.recent_auth.password`) | Changed: was "Verifikasi ulang" (GLOSSARY) |
| UXS-12 Too many attempts | `ux.state.throttled` | Terlalu banyak percobaan | Belum ada yang diproses. Coba lagi dalam :durasi. | — | — |
| UXS-13 Disabled with a reason | `ux.state.disabled` | Tidak dapat dipakai sekarang | The reason of section 15 | Where the reason is an unresolved request: Buka Permintaan belum pasti | The link changed: was "Lihat permintaan" (CW-04) |
| UXS-14 Uncertain | `ux.state.uncertain` | Status belum pasti | Permintaan sudah dikirim, tetapi hasilnya belum diterima. Mungkin sudah tersimpan. Then the family's text of section 14.1 | Kirim ulang; Cek status | — |
| UXS-15 Held, after IDEMPOTENCY_CONFLICT | `ux.state.held.idempotency_conflict` | Status belum pasti | Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan. Cek statusnya untuk mengetahui hasilnya. Then the family's text of section 14.1 | Cek status | — |
| UXS-15 Held, after EPOCH_STALE | `ux.state.held.epoch_stale` | Status belum pasti | Permintaan ini dibuat sebelum data dipulihkan dari cadangan. Hasilnya diketahui setelah rekonsiliasi pemulihan oleh Pemilik. Sampai itu selesai, statusnya tetap belum pasti. Then the family's text of section 14.1 | Cek status | — |
| UXS-16 Request no longer valid | `ux.state.abandoned` | Permintaan lama sudah tidak berlaku | Permintaan ini tidak tersimpan dan tidak akan pernah tersimpan. Anda dapat mulai lagi. | The page's restart, for example Mulai lagi dari keranjang | — |
| UXS-17 Data changed | `ux.state.stale_version` | Data sudah berubah | Data ini berubah setelah Anda mulai. Periksa nilai terbaru di samping nilai yang Anda tinjau, lalu konfirmasi lagi. Tidak ada yang disimpan. | — | — |
| UXS-18 Price changed | `ux.state.price_changed` | Harga berubah | Harga :jumlah produk berubah sejak dimasukkan ke keranjang. Periksa harga dan total yang baru. Line: `ux.state.price_changed.line`, Harga lama :lama → harga baru :baru. Total: `ux.state.price_changed.total`, Total baru :uang | Setuju harga baru | — |
| UXS-19 Recount required | `ux.state.recount` | Ada transaksi baru; hitung ulang kas | Ada penjualan atau pengembalian dari laci setelah pratinjau dibuat. Sesi kas masih dibuka. Hitung ulang uang di laci, lalu tutup lagi. | — (step 1 with a new preview) | — |
| UXS-19, an unresolved checkout | `ux.state.recount.unresolved_checkouts` | Ada penjualan yang statusnya belum pasti | Sesi kas masih dibuka karena ada penjualan yang statusnya belum pasti. Cek status penjualan itu dulu, lalu hitung ulang uang di laci dan tutup lagi. | — (step 1 with a new preview, where the unresolved checkout is listed with "Cek status") | D-088; the Owner's answer of 19 October 2026 (section 22) |
| UXS-20 No connection | `ux.state.offline` | Tidak ada koneksi | Penyimpanan berhenti sampai koneksi kembali. Isian di halaman ini tetap ada selama halaman tidak ditutup. | — | — |
| UXS-21 Not refreshed | `ux.state.stale_read` | Data belum diperbarui | Data terakhir diperbarui :waktu dan mungkin sudah berubah. | Muat ulang | — |
| UXS-22 Could not load | `ux.state.load_failed` | Gagal memuat | Bagian ini tidak dapat dimuat. Bagian lain tetap bisa dipakai. | Coba lagi | — |
| UXS-23 Sign-in session about to end | `ux.state.session_warning` | Sesi masuk akan berakhir | Anda akan keluar otomatis dalam :durasi karena tidak ada aktivitas. | Tetap masuk (`ux.state.session_warning.stay`); Keluar | — |
| UXS-23, the 12-hour limit | `ux.state.session_warning.absolute` | Sesi masuk akan berakhir | Sesi masuk ini berakhir pukul :jam, batas 12 jam sejak masuk. Batas ini tidak bisa diperpanjang. | — | UXA-21 |
| UXS-24 Unknown cost | `ux.state.cost_unknown` | Biaya perlu dilengkapi pemilik | — (shown in place of the amount, never Rp0 or blank) | For the Owner: Lengkapi biaya | — |
| UXS-25 Incomplete cost figures | `ux.state.cost_incomplete` | Data biaya belum lengkap | HPP dan laba kotor belum lengkap karena ada biaya yang belum diketahui atau laporan yang ditandai belum lengkap. Margin tidak ditampilkan sampai data biaya lengkap. Then the counts of section 10.1 | Biaya perlu dilengkapi | — |
| UXS-26 Owner correction mark | `ux.state.owner_corrected` | Dikoreksi Pemilik | With `costing.field.cost_now` and `costing.field.cost_at_confirmation`; never the reason or history (D-047) | — | — |
| UXS-27 Onboarding active | `ux.state.onboarding` | Masa input stok awal aktif | Penjualan dan pencatatan stok berhenti sampai Pemilik mengakhiri masa input stok awal. | For the Owner: Buka Stok awal | — |
| UXS-28 Postings blocked | `ux.state.blocked` | Mutasi diblokir | Produk :produk diblokir sementara karena pemeriksaan stok harian menemukan ketidakcocokan, jadi belum bisa dijual atau dicatat. Tidak ada yang disimpan. Staff: `ux.state.blocked.staff`, Beri tahu Pemilik. | For the Owner: Buka Detail insiden (`ux.state.blocked.open_incident`) | — |
| UXS-29 No OPEN cash session | `ux.state.no_session` | Belum ada sesi kas | Buka sesi kas Anda dulu sebelum menjual. | Buka sesi kas | — |
| UXS-30 Cash session not open, at the till | `ux.state.session_closed.till` | Sesi kas sudah ditutup | Sesi kas ini sudah ditutup, mungkin oleh Pemilik. Tidak ada yang disimpan. Buka sesi kas baru untuk menjual lagi. | Buka sesi kas | — |
| UXS-30 Cash session not open, a close | `ux.state.session_closed.close` | Sesi kas sudah ditutup | Sesi kas ini sudah ditutup, mungkin oleh Pemilik. Hitungan Anda tidak disimpan. | Buka Detail sesi kas (`ux.state.session_closed.open_report`) | NAVIGATION_CONTRACTS UXP-105, UXP-207 |
| UXS-30 Cash session not open, a refund source | `ux.state.session_closed.refund` | Sesi kas sudah ditutup | Sesi kas yang dipilih sudah ditutup. Tidak ada yang disimpan dan tidak ada uang yang boleh diambil dari lacinya. Pilih sumber uang lain. | — (back to the choice of source) | BR-RFD-09 |
| UXS-31 Insufficient stock | `ux.state.insufficient_stock` | Stok tidak cukup | Stok tidak cukup untuk baris yang ditandai. Tidak ada yang disimpan. Line: `ux.state.insufficient_stock.line`, Tersedia :jumlah | — | — |
| UXS-31 Insufficient stock, a reversal | `ux.state.insufficient_stock.reversal` | Stok tidak cukup | Stok tidak cukup untuk membatalkan mutasi ini. Tidak ada yang disimpan. | — | BR-INV-37 |
| UXS-32 Inactive product | `ux.state.inactive.product` | Nonaktif | Produk :produk sudah nonaktif dan tidak bisa dipakai. Tidak ada yang disimpan. Hapus barisnya atau pilih produk lain. | — | — |
| UXS-32 Inactive category | `ux.state.inactive.category` | Nonaktif | Kategori :kategori sudah nonaktif. Tidak ada yang disimpan. Pilih kategori lain. | — | — |
| UXS-32, the mark | `ux.state.inactive.label` | Nonaktif | — | — | — |
| UXS-33 Unknown code | `ux.state.unknown_code` | Kode tidak dikenal | Tidak ada produk dengan kode “:kode”. Cari dengan nama produk. | Where allowed: Tambah produk | The action changed: was "Buat produk baru" (CW-04) |
| UXS-34 Tendered amount too low | `ux.state.tender_low` | Uang diterima kurang | Kurang :uang | Uang Pas | — |
| UXS-35 Refund bound exceeded | `ux.state.refund_bound.money` | Melebihi batas pengembalian | Uang yang dikembalikan melebihi sisa yang bisa dikembalikan, yaitu :uang. Tidak ada yang disimpan. | — | — |
| UXS-35 Return bound exceeded | `ux.state.refund_bound.goods` | Melebihi batas pengembalian | Jumlah barang yang diretur melebihi sisa yang bisa diretur, yaitu :jumlah. Tidak ada yang disimpan. | — | — |
| UXS-36 Drawer cash not enough | `ux.state.drawer_short` | Uang seharusnya di laci tidak cukup | Uang seharusnya di :laci hanya :uang, kurang dari pengembalian ini. Tidak ada yang disimpan. Jangan ambil uang dari laci. Pilih sesi kas lain yang cukup, atau Dana Pemilik. | — | D-045 |
| UXS-37 SKU already used | `ux.state.duplicate_code.sku` | Kode sudah dipakai | SKU ini sudah dipakai produk lain. Tidak ada yang disimpan. | Where the viewer may see it: Buka :produk (`ux.state.duplicate_code.open`) | — |
| UXS-37 Barcode already used | `ux.state.duplicate_code.barcode` | Kode sudah dipakai | Barcode ini sudah dipakai produk lain. Tidak ada yang disimpan. | As above | — |
| UXS-38 Barcode not eligible | `ux.state.barcode_ineligible` | Barcode tidak dapat dipakai | The reason of section 13.2 | — | — |
| UXS-39 CSV file not valid | `ux.state.csv_invalid` | Berkas CSV belum benar | Ada :jumlah baris yang perlu diperbaiki. Tidak ada produk yang dibuat. Perbaiki berkasnya, lalu pilih lagi. | — (row errors: section 16.3) | — |
| UXS-40 Cost correction could not be recalculated | `ux.state.replay_rejected.cost` | Koreksi tidak dapat dihitung ulang | Biaya tidak diubah karena perhitungan ulang tidak bisa dilakukan dengan aman. Laporan biaya produk ini ditandai belum lengkap. Periksa riwayat produk ini, lalu coba lagi nanti. | — | BR-COST-27 |
| UXS-40 Reversal could not be recalculated | `ux.state.replay_rejected.reversal` | Pembatalan tidak dapat dihitung ulang | Mutasi tidak dibatalkan karena perhitungan ulang tidak bisa dilakukan dengan aman. Tidak ada yang berubah. Periksa riwayat produk ini, lalu coba lagi nanti. | — | D-063 |
| UXS-41 Link not valid | `ux.state.link_invalid` | Tautan tidak berlaku | Tautan ini tidak dapat dipakai. Minta tautan baru kepada Pemilik. | — | One text for every failure (SEC-ACC-06) |
| UXS-42 Sign-in failed | `ux.state.signin_failed.sign_in` | Gagal masuk | Periksa email dan kata sandi Anda, lalu coba lagi. | — | One text (SEC-AUTH-08) |
| UXS-42 Password confirmation failed | `ux.state.signin_failed.confirmation` | Konfirmasi gagal | Periksa kata sandi Anda, lalu coba lagi. | — | TR-ERR-04 |
| UXS-42 Current password refused | `ux.state.signin_failed.current_password` | Kata sandi sekarang tidak cocok | Coba lagi. | — | UXP-004 |
| UXS-43 Not verified | `ux.state.unverified.label` | Belum Terverifikasi | — | — | REQ-OPS-04 |
| UXS-44 Print or export failed | `ux.state.print_failed` | Cetak atau unduh gagal | Dokumen tidak dapat dicetak atau diunduh. Data tidak berubah. | Coba lagi | — |
| UXS-45 Unsaved changes | `ux.state.unsaved` | Perubahan belum disimpan | Isian di halaman ini akan hilang jika Anda meninggalkannya. Receiving: `ux.state.unsaved.body_receiving`, Draf barang masuk ini tidak disimpan di mana pun dan akan hilang. CSV import: `ux.state.unsaved.body_import`, Pratinjau impor ini akan dibuang. Tidak ada produk yang dibuat. | Tetap di halaman ini; Tinggalkan | — |
| UXS-46 Method not allowed | `ux.state.method_not_allowed` | Metode pembayaran tidak tersedia | Tidak ada yang disimpan. Pilih Tunai atau Transfer bank. | — | D-077 |
| UXS-47 Similar product found | `ux.state.similar_product` | Produk mirip | Sudah ada produk dengan nama mirip. Periksa dulu agar produk tidak tercatat dua kali. | Tetap buat produk baru | — |
| UXS-48 No cash register available | `ux.state.no_register` | Tidak ada laci kas yang tersedia | Semua laci kas sedang dipakai atau nonaktif. Tunggu sampai salah satu sesi kas ditutup, atau minta bantuan Pemilik. Untuk serah terima, kasir sebelumnya menutup sesinya dulu, lalu Anda membuka sesi baru. | — | BR-CASH-09 |
| UXS-49 Link no longer shown | `ux.state.link_gone` | Tautan tidak ditampilkan lagi | Tautan kata sandi hanya ditampilkan sekali. Jika perlu, buat tautan baru; tautan baru menggantikan yang lama. | Kembali ke Detail pengguna | — |
| UXS-50 Onboarding waiting for restore reconciliation | `ux.state.onboarding_held` | Stok awal menunggu rekonsiliasi pemulihan | Data dipulihkan dari cadangan dan status masa input stok awal belum dapat dipastikan. Memulai, mengisi, dan mengakhiri masa input stok awal ditunda sampai rekonsiliasi pemulihan selesai. | — | D-072 |

States: **50**, each with its wording.

## 13. Error codes

Every refusal carries its code and the key of its message (TECHNICAL_RULES section 4). The message key is a key of the state that UI_STATES section 3 maps the code to; a state that answers several codes has one key per code.

### 13.1 Codes

| Code | State | Message key | Note |
| --- | --- | --- | --- |
| `UNAUTHENTICATED` | UXS-09 | `ux.state.signed_out` | — |
| `CSRF_FAILED` | UXS-10 | `ux.state.reload` | — |
| `RECENT_AUTH_REQUIRED` | UXS-11 | `ux.state.recent_auth` | — |
| `FORBIDDEN` | UXS-07 | `ux.state.forbidden` | — |
| `NOT_FOUND` | UXS-08 | `ux.state.not_found` | — |
| `IDEMPOTENCY_CONFLICT` | UXS-15 | `ux.state.held.idempotency_conflict` | — |
| `EPOCH_STALE` | UXS-15 | `ux.state.held.epoch_stale` | D-073 |
| `INTENT_ABANDONED` | UXS-16 | `ux.state.abandoned` | — |
| `STALE_VERSION` | UXS-17 | `ux.state.stale_version` | — |
| `PRICE_CHANGED` | UXS-18 | `ux.state.price_changed` | — |
| `RECOUNT_REQUIRED` | UXS-19 | `ux.state.recount`; `ux.state.recount.unresolved_checkouts` | The second when the reloaded close preview lists an unresolved checkout of the session's responsible user (G-27, D-088); no new code |
| `VALIDATION_FAILED` | UXS-05 | `ux.state.validation` | Field messages: section 16 |
| `INSUFFICIENT_STOCK` | UXS-31 | `ux.state.insufficient_stock` or `ux.state.insufficient_stock.reversal` | By the page |
| `PRODUCT_INACTIVE` | UXS-32 | `ux.state.inactive.product` | — |
| `CATEGORY_INACTIVE` | UXS-32 | `ux.state.inactive.category` | — |
| `NO_OPEN_SESSION` | UXS-29 | `ux.state.no_session` | — |
| `SESSION_NOT_OPEN` | UXS-30 | `ux.state.session_closed.till`, `ux.state.session_closed.close` or `ux.state.session_closed.refund` | By the page |
| `ONBOARDING_FREEZE` | UXS-27 | `ux.state.onboarding` | — |
| `POSTING_BLOCKED` | UXS-28 | `ux.state.blocked` | — |
| `METHOD_NOT_ALLOWED` | UXS-46 | `ux.state.method_not_allowed` | — |
| `TENDER_INSUFFICIENT` | UXS-34 | `ux.state.tender_low` | — |
| `REFUND_BOUND_EXCEEDED` | UXS-35 | `ux.state.refund_bound.money` | — |
| `RETURN_BOUND_EXCEEDED` | UXS-35 | `ux.state.refund_bound.goods` | — |
| `DRAWER_CASH_INSUFFICIENT` | UXS-36 | `ux.state.drawer_short` | — |
| `BARCODE_NOT_ELIGIBLE` | UXS-38 | `ux.state.barcode_ineligible.*` | The reason of section 13.2 |
| `DUPLICATE_SKU` | UXS-37 | `ux.state.duplicate_code.sku` | — |
| `DUPLICATE_BARCODE` | UXS-37 | `ux.state.duplicate_code.barcode` | — |
| `CSV_INVALID` | UXS-39 | `ux.state.csv_invalid` | — |
| `REPLAY_REJECTED` | UXS-40 | `ux.state.replay_rejected.cost` or `ux.state.replay_rejected.reversal` | By the command |
| `LINK_INVALID` | UXS-41 | `ux.state.link_invalid` | — |
| `THROTTLED` | UXS-12 | `ux.state.throttled` | `:durasi` from the retry time |
| `SERVICE_BUSY` | UXS-14 | `ux.state.uncertain` | Never a failure (TR-ERR-01) |
| Any other 5xx, a timeout or a lost connection | UXS-14 | `ux.state.uncertain` | — |

Codes: **32**, each with a message.

### 13.2 Why a barcode cannot be used

`BARCODE_NOT_ELIGIBLE` names one of these reasons as a safe parameter; how the parameter is carried is set with the command contracts in RF-5.

| Key | Copy | Rule |
| --- | --- | --- |
| `ux.state.barcode_ineligible.retired` | Barcode ini sudah dipensiunkan dan tidak bisa dipakai lagi. Hanya barcode pabrik yang dipensiunkan karena salah pasang yang bisa dipasang Pemilik ke produk yang benar, dengan Koreksi salah pasang barcode. Tidak ada yang disimpan. | BR-CAT-19, BR-CAT-51, D-052 |
| `ux.state.barcode_ineligible.reserved` | Kode yang diawali BK- hanya untuk barcode internal yang dibuat aplikasi. Tidak ada yang disimpan. | BR-CAT-18 |
| `ux.state.barcode_ineligible.check_digit` | Angka pemeriksa barcode ini tidak cocok. Periksa lagi kodenya. Tidak ada yang disimpan. | BR-CAT-17 |
| `ux.state.barcode_ineligible.not_correctable` | Barcode ini tidak bisa dikoreksi. Hanya barcode pabrik yang terpasang pada produk lain, atau yang dipensiunkan karena salah pasang, yang bisa dipasang ke produk yang benar. Tidak ada yang disimpan. | D-051, D-052 |

## 14. Recovery

### 14.1 Uncertain-outcome panels per command family

The body of UXS-14 and UXS-15 is followed by what must not happen meanwhile (UI_STATES section 4). The family label also names the request on UXP-006.

| Family (TECHNICAL_RULES section 7.2) | Label key and label | What must not happen: key and text |
| --- | --- | --- |
| Sale checkout | `commands.family.sale`: Penjualan | `commands.family.sale.must_not`: Jangan minta pelanggan membayar atau mentransfer lagi. Penjualan ini mungkin sudah tersimpan. |
| Open cash session | `commands.family.open_session`: Buka sesi kas | `commands.family.open_session.must_not`: Jangan membuka sesi kas lagi dengan permintaan baru. |
| Close, own or Owner-assisted | `commands.family.close_session`: Tutup sesi kas | `commands.family.close_session.must_not`: Jangan hitung ulang laci atau tutup sesi ini lagi. Sesi kas ini mungkin sudah ditutup. |
| Drawer-funded refund or return | `commands.family.refund_drawer`: Pengembalian dari laci kas | `commands.family.refund_drawer.must_not`: Jangan ambil uang dari laci sampai pengembalian ini tercatat. Jangan buat pengembalian baru. |
| Owner-funded or BANK_TRANSFER refund or return | `commands.family.refund_other`: Pengembalian uang dan retur | `commands.family.refund_other.must_not`: Cek status pengembalian ini dan periksa bukti pembayarannya dulu. Jangan membayar lagi atau mencatat pengembalian baru. |
| Receiving confirmation, manual issue, adjustment | `commands.family.stock_posting`: Barang masuk, barang keluar, atau penyesuaian stok | `commands.family.stock_posting.must_not`: Jangan mencatat barang masuk, barang keluar, atau penyesuaian baru sampai hasil ini diketahui. |
| Opening stock | `commands.family.opening_stock`: Stok awal | `commands.family.opening_stock.must_not`: Jangan memasukkan baris yang sama lagi. |
| Reversal | `commands.family.reversal`: Pembatalan mutasi | `commands.family.reversal.must_not`: Jangan membatalkan mutasi ini lagi. |
| Cost correction or completion | `commands.family.cost_correction`: Koreksi biaya | `commands.family.cost_correction.must_not`: Jangan membuat koreksi baru untuk biaya yang sama sampai hasil ini diketahui. |
| Product creation | `commands.family.product_create`: Tambah produk | `commands.family.product_create.must_not`: Jangan menambahkan produk yang sama lagi. |
| Price change; product edit; product deactivation or reactivation | `commands.family.product_edit`: Perubahan produk | `commands.family.edit.must_not`: Jangan mengirim perubahan lain sampai hasil ini diketahui. |
| Category create, rename, deactivate or reactivate | `commands.family.category`: Kategori | `commands.family.edit.must_not` |
| Product photo upload, replace or remove | `commands.family.photo`: Foto produk | `commands.family.edit.must_not` |
| Barcode add, generate, retire | `commands.family.barcode`: Barcode | `commands.family.edit.must_not` |
| Barcode misassignment correction | `commands.family.barcode_correction`: Koreksi salah pasang barcode | `commands.family.barcode_correction.must_not`: Jangan mengirim koreksi lain sampai hasil ini diketahui. |
| CSV import confirmation | `commands.family.csv_import`: Impor produk (CSV) | `commands.family.csv_import.must_not`: Jangan mengimpor berkas yang sama lagi. |
| Onboarding set or release | `commands.family.onboarding`: Masa input stok awal | `commands.family.onboarding.must_not`: Jangan menjalankan tindakan sebaliknya sampai statusnya diketahui. |
| BusinessProfile change | `commands.family.profile`: Profil usaha | `commands.family.edit.must_not` |
| Cash register configuration | `commands.family.register`: Laci kas | `commands.family.edit.must_not` |
| User management | `commands.family.users`: Pengguna | `commands.family.users.must_not`: Jangan membuat akun atau tautan kata sandi lain sampai hasil ini diketahui. |
| Reconciliation reopening | `commands.family.reopen`: Buka kembali mutasi | `commands.family.reopen.must_not`: Jangan membuka kembali mutasi ini lagi. |
| Owner review note | `commands.family.review_note`: Catatan tinjauan | `commands.family.review_note.must_not`: Jangan menulis catatan ini lagi. |
| Print, reprint or export | — | No panel: the print or export is repeated (UXS-44) |
| Resolution of an uncertain intent | — | `commands.resolution.no_answer`: Cek status belum mendapat jawaban. Statusnya tetap belum pasti; cek lagi sebentar lagi. |

### 14.2 Results of "Cek status"

| Result (ADR-007 section 4) | Key | Copy | Then |
| --- | --- | --- | --- |
| COMMITTED | `commands.resolution.committed` | Sudah tersimpan. | Lihat hasil; a Sale's cart is discarded (NC-13) |
| COMMITTED, a link that was issued but not shown | `commands.resolution.committed_link_hidden` | Sudah tersimpan, tetapi tautannya tidak bisa ditampilkan lagi. Jika perlu, buat tautan baru di Detail pengguna. | SEC-ACC-05; UI_STATES section 4 |
| IN_PROGRESS | `commands.resolution.in_progress` | Masih diproses. Cek lagi sebentar lagi. | UXS-06, then a new check |
| NOT_COMMITTED | `commands.resolution.not_committed` | Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi. | The row disappears; for a Sale, Mulai lagi dari keranjang |
| EPOCH_STALE again | `ux.state.held.epoch_stale` | Section 12 | The reference is kept (D-073) |
| No answer | `commands.resolution.no_answer` | Cek status belum mendapat jawaban. Statusnya tetap belum pasti; cek lagi sebentar lagi. | The request keeps the state it was in before the check, and its reference is kept; UXS-06, shown while a check runs, is not that state. UXS-14 stays UXS-14, with Kirim ulang and Cek status; UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, stays UXS-15, with Cek status only and never Kirim ulang (ADR-007 section 5; UI_STATES section 1; finding F-05) |

## 15. Disabled reasons

A disabled control shows its reason next to it and exposes it to assistive technology (UXS-13). Where the reason is an unresolved request, the reason is followed by "Buka Permintaan belum pasti" (`ux.action.open_unresolved`), which opens UXP-006 (NC-13 (a)).

| Key | Copy | Controls |
| --- | --- | --- |
| `ux.disabled.no_change` | Belum ada perubahan. | UXI-052, UXI-187, UXI-351, UXI-364 |
| `ux.disabled.form_incomplete` | Lengkapi isian yang wajib dulu. | Any form whose own reason below does not apply |
| `cash.open.disabled` | Pilih laci kas dan isi modal awal. | UXI-096 |
| `cash.close.disabled` | Isi uang fisik di laci. | UXI-105 |
| `cash.assisted_close.disabled` | Isi uang fisik di laci dan alasan. | UXI-152 |
| `sales.refund.disabled` | Isi jumlah barang yang diretur atau uang yang dikembalikan, lalu lengkapi isian yang wajib. | UXI-127 |
| `catalog.barcode_correction.disabled` | Isi barcode, produk yang benar, dan alasan. | UXI-193 |
| `inventory.receipt_new.disabled` | Tambahkan minimal satu baris yang lengkap. Paling banyak 500 baris. | UXI-239 |
| `inventory.issue_new.disabled` | Tambahkan minimal satu baris yang lengkap dan pilih alasan. Paling banyak 200 baris. | UXI-259 |
| `inventory.adjustment.disabled` | Lengkapi baris, alasan, referensi hitung, dan referensi. Paling banyak 200 baris. | UXI-271 |
| `inventory.reversal.disabled` | Isi alasan. | UXI-279 |
| `inventory.opening_entry.disabled` | Isi 1 sampai 100 baris yang lengkap. | UXI-295 |
| `costing.correction.disabled` | Isi biaya baru dan alasan. | UXI-318 |
| `access.user_create.disabled` | Lengkapi nama tampilan, email, dan peran. | UXI-337 |
| `cash.register_form.disabled` | Lengkapi kode dan label. | UXI-371 |
| `sales.cart.disabled_empty` | Keranjang masih kosong. | UXI-075 |
| `sales.cart.disabled_price_review` | Setujui harga baru dulu. | UXI-075 (UXS-18) |
| `sales.cart.disabled_onboarding` | Penjualan berhenti selama masa input stok awal. | UXI-075 (UXS-27) |
| `sales.cart.disabled_unresolved` | Ada pembayaran yang statusnya belum pasti. Cek statusnya dulu. | UXI-075 (NC-13) |
| `sales.cart.disabled_invalid` | Perbaiki baris yang ditandai dulu. | UXI-075 |
| `sales.cart.decrease_disabled` | Jumlah tidak bisa dikurangi lagi. Hapus barisnya jika tidak jadi dibeli. | UXI-071 |
| `sales.payment.disabled_tender` | Uang diterima kurang :uang. | UXI-083 (UXS-34) |
| `sales.payment.back_disabled` | Tunggu sampai hasil pembayaran diketahui. | UXI-084 |
| `sales.refund.disabled_fully_refunded` | Sudah dikembalikan seluruhnya. | UXI-118 (BR-RFD-03) |
| `sales.refund.disabled_unresolved` | Ada pengembalian uang yang statusnya belum pasti. Cek statusnya dulu. | UXI-118 |
| `cash.close.disabled_unresolved` | Ada permintaan tutup sesi yang statusnya belum pasti. Cek statusnya dulu. | UXI-077, UXI-139, UXI-140, UXI-340 |
| `ux.disabled.unresolved_checkouts` | Ada penjualan yang statusnya belum pasti. Cek statusnya dulu sebelum menghitung laci. | UXI-103, UXI-150 (BR-CASH-16; INTERACTION_CONTRACTS section 2.5; D-087) |
| `catalog.barcode_correction.disabled_unresolved` | Ada koreksi barcode yang statusnya belum pasti. Cek statusnya dulu. | UXI-174 |
| `catalog.field.unit_lock_help` | Satuan dan jumlah desimal tidak bisa diubah setelah produk punya mutasi stok. | UXI-186 (BR-CAT-07) |
| `catalog.csv_import.disabled_unresolved` | Ada impor yang statusnya belum pasti. Cek statusnya dulu. | UXI-207 |
| `catalog.csv_import.disabled_errors` | Perbaiki semua baris yang salah dulu. | UXI-210 |
| `ux.disabled.onboarding` | Tidak bisa dipakai selama masa input stok awal. | UXI-217, UXI-221, UXI-227, UXI-231, UXI-250, UXI-253, UXI-266 (BR-INV-24) |
| `inventory.disabled_unresolved_posting` | Ada barang masuk, barang keluar, atau penyesuaian yang statusnya belum pasti. Cek statusnya dulu. | UXI-217, UXI-221, UXI-231, UXI-253 |
| `costing.disabled_unresolved` | Ada koreksi biaya yang statusnya belum pasti. Cek statusnya dulu. | UXI-226, UXI-249, UXI-314, UXI-324 |
| `inventory.reversal.disabled_done` | Mutasi ini sudah dibatalkan. | UXI-227, UXI-250, UXI-266 (BR-INV-35) |
| `inventory.reversal.disabled_unresolved` | Ada pembatalan mutasi yang statusnya belum pasti. Cek statusnya dulu. | UXI-227, UXI-228, UXI-250, UXI-266 |
| `costing.field.zero_disabled` | Biaya 0 hanya bisa diisi Pemilik. Pilih Biaya belum diketahui; Pemilik akan melengkapinya. | UXI-236, for Ops and Combined (BR-COST-04) |
| `inventory.onboarding.disabled_unresolved` | Ada permintaan masa input stok awal yang statusnya belum pasti. Cek statusnya dulu. | UXI-285, UXI-289 |
| `inventory.opening_entry.disabled_unresolved` | Ada input stok awal yang statusnya belum pasti. Cek statusnya dulu. | UXI-286 |
| `inventory.onboarding.disabled_held` | Ditunda sampai rekonsiliasi pemulihan selesai. | UXI-286, UXI-289 (UXS-50, D-072) |
| `cash.registers.edit_disabled` | Laci kas ini sedang dipakai sesi kas yang dibuka. | UXI-368 (BR-CASH-02) |
| `ux.disabled.unresolved_family` | Ada permintaan sejenis yang statusnya belum pasti. Cek statusnya dulu. | Every other control that would start a new command of a family with an unresolved request (NC-13 (a)) |

The reasons cover the 49 rows of INTERACTION_INVENTORY section 3 that are disabled with a reason, as TASK-002 re-derived them: UXI-076 is no longer counted (finding F-02), and UXI-150 and UXI-228 are (D-087).

## 16. Validation messages

A field error stands beside its field; the summary at the top of the form uses `ux.state.validation` (UXS-05). The form keeps what was typed, except passwords (SEC-CH-03).

### 16.1 Fields

| Key | Copy | Rule |
| --- | --- | --- |
| `ux.validation.required` | Wajib diisi. | — |
| `ux.validation.text_max` | Paling banyak :maks karakter. | TECHNICAL_RULES section 11.3 |
| `ux.validation.text_min` | Paling sedikit :min karakter. | — |
| `ux.validation.choose_one` | Pilih salah satu. | — |
| `ux.validation.roles_min` | Pilih minimal satu peran. | SEC-ACC-02 |
| `ux.validation.money` | Isi jumlah rupiah yang benar, tanpa angka di belakang koma. | BR-NUM-01 |
| `ux.validation.money_min` | Tidak boleh kurang dari Rp0. | — |
| `ux.validation.money_max` | Paling banyak Rp999.999.999.999. | TECHNICAL_RULES section 11.1 |
| `ux.validation.money_blank` | Isi jumlahnya. Rp0 boleh. | Opening and physical cash (BR-CASH-04) |
| `ux.validation.quantity_positive` | Isi jumlah lebih dari 0. | — |
| `ux.validation.quantity_decimals` | Paling banyak :desimal angka di belakang koma untuk produk ini. | BR-NUM-02 |
| `ux.validation.quantity_whole` | Produk ini dihitung utuh. Isi tanpa koma. | Precision 0 |
| `ux.validation.quantity_max` | Jumlah terlalu besar. Paling banyak 99.999.999,999. | TECHNICAL_RULES section 11.1 |
| `ux.validation.min_stock` | Stok minimum tidak boleh kurang dari 0. | BR-CAT-11 |
| `ux.validation.zero_evidence` | Isi bukti atau alasan untuk biaya nol. | BR-COST-04 |
| `ux.validation.email` | Format email belum benar. | — |
| `ux.validation.password` | Kata sandi belum memenuhi syarat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol. | SEC-AUTH-05 |
| `ux.validation.password_max` | Paling banyak 128 karakter. | TECHNICAL_RULES section 11.3 |
| `ux.validation.password_repeat` | Kata sandi yang diulang tidak sama. | — |
| `ux.validation.sku` | SKU berisi 1 sampai 64 karakter, tanpa karakter kontrol. | BR-CAT-05 |
| `ux.validation.barcode_token` | Barcode berisi 1 sampai 128 huruf, angka, atau tanda baca biasa. | BR-CAT-17 |
| `ux.validation.date_order` | Tanggal awal tidak boleh setelah tanggal akhir. | TECHNICAL_RULES section 11.4 |
| `ux.validation.date_range_max` | Rentang paling lama 366 hari. | — |
| `ux.validation.lines_min` | Tambahkan minimal satu baris. | — |
| `ux.validation.lines_max` | Paling banyak :maks baris. | TECHNICAL_RULES section 11.2 |
| `ux.validation.register_taken` | Laci kas ini baru saja dipakai sesi kas lain. Pilih laci kas lain. | NAVIGATION_CONTRACTS UXP-104 |
| `ux.validation.register_in_use` | Sesi kas baru saja dibuka di laci kas ini, jadi laci kas ini tidak bisa diubah sekarang. | NAVIGATION_CONTRACTS UXP-610 |
| `ux.validation.register_code_taken` | Kode ini sudah dipakai laci kas lain. | SCHEMA section 5.10 |

### 16.2 Uploads

| Key | Copy | Rule (SECURITY_MODEL section 13) |
| --- | --- | --- |
| `ux.validation.upload.image_type` | Berkas harus JPG, PNG, atau WebP. | Product photo and logo |
| `ux.validation.upload.too_large` | Berkas paling besar :ukuran. | 8 MB for a photo, 5 MB for a logo, 2 MB for a CSV file |
| `ux.validation.upload.too_many_pixels` | Gambar paling besar :jumlah megapiksel. | 24 for a photo, 16 for a logo |
| `ux.validation.upload.animated` | Gambar bergerak tidak bisa dipakai. | — |
| `ux.validation.upload.unreadable` | Berkas tidak dapat dibaca. Pilih berkas lain. | — |
| `ux.validation.upload.csv_type` | Berkas harus CSV dengan pengodean UTF-8. Dari aplikasi spreadsheet, simpan sebagai CSV UTF-8. | D-037; CW-13 |
| `ux.validation.upload.csv_rows` | Paling banyak 5.000 baris data. | BR-CAT-39 |

The sizes are shown in MB; the limits themselves are those of SECURITY_MODEL section 13.

### 16.3 CSV preview errors

| Key | Copy | Rule |
| --- | --- | --- |
| `catalog.csv_import.error.row` | Baris :nomor: :masalah | Format of every row error |
| `catalog.csv_import.error.category_missing` | Tidak ada kategori aktif dengan nama ini. | D-069 (h): no active category matches |
| `catalog.csv_import.error.category_ambiguous` | Nama kategori cocok dengan lebih dari satu kategori aktif. | D-069 (h) |
| `catalog.csv_import.error.sku_taken` | SKU sudah dipakai produk lain. | BR-CAT-39 |
| `catalog.csv_import.error.sku_duplicate` | SKU muncul lebih dari sekali di berkas ini. | BR-CAT-39 |
| `catalog.csv_import.error.barcode_taken` | Barcode sudah dipakai. | — |
| `catalog.csv_import.error.barcode_duplicate` | Barcode muncul lebih dari sekali di berkas ini. | — |
| `catalog.csv_import.error.barcode_reserved` | Barcode yang diawali BK- tidak bisa diimpor. | BR-CAT-18 |
| `catalog.csv_import.error.required` | Kolom :kolom wajib diisi. | — |
| `catalog.csv_import.error.invalid_value` | Isi kolom :kolom belum benar. | — |
| `catalog.csv_import.error.formula` | Sel yang diawali = tidak diterima. | SECURITY_MODEL section 13 |
| `catalog.csv_import.error.unknown_column` | Kolom tidak dikenal: :kolom. | BR-CAT-39 |
| `catalog.csv_import.error.missing_column` | Kolom wajib tidak ada: :kolom. | BR-CAT-39 |
| `catalog.csv_import.error.file_changed` | Berkas berubah setelah pratinjau. Pilih berkasnya lagi untuk pratinjau baru. | SEC-UP-04 |

## 17. Documents

The words printed on documents. Which fields a document holds is the rule cited; their order, layout and the document contract are set in RF-5 and their look in RF-4C. Documents never carry cost or profit (BR-COST-08) and never the codename (CW-14).

### 17.1 Receipt

The thermal 58 mm, thermal 80 mm and A4 renderings use the same words, from the same ReceiptSnapshot (BR-DOC-02).

| Key | Copy | Field (BR-DOC-01) |
| --- | --- | --- |
| `documents.invoice.title` | Nota Penjualan | Title of the A4 rendering; not a tax invoice (BR-DOC-03) |
| `documents.receipt.number` | No. :nomor | The Sale number |
| `documents.receipt.time` | Waktu: :waktu | — |
| `documents.receipt.cashier` | Kasir: :nama | The user who made the Sale |
| `documents.receipt.session` | Sesi kas: :nomor | — |
| `documents.receipt.line_quantity` | :jumlah :satuan × :harga | Each line, with its product name and SKU |
| `documents.receipt.sku` | SKU :sku | — |
| `documents.receipt.line_total` | Subtotal | — |
| `documents.receipt.total` | Total | — |
| `documents.receipt.method` | Pembayaran: :metode | Tunai or Transfer bank |
| `documents.receipt.tendered` | Uang diterima | Tunai only |
| `documents.receipt.change` | Kembalian | Tunai only |
| `documents.receipt.customer` | Pelanggan: :nama | Only when entered |
| `documents.receipt.copy_marker` | Salinan | On every reprint (BR-DOC-04); where it is printed is the document contract (RF-5) |

The shop name, logo, address, contact, the text above and below the document and the document label come from the BusinessProfile revision of the Sale (BR-DOC-01, BR-CFG-03).

### 17.2 Session report

Printed from UXP-206 and exported as CSV (BR-CASH-20, BR-CASH-22). The CSV headers use the same keys.

| Key | Copy | Field (BR-CASH-20) |
| --- | --- | --- |
| `documents.session_report.title` | Laporan sesi kas | — |
| `documents.session_report.number` | No. sesi: :nomor | The report carries its session's number |
| `documents.session_report.register` | Laci kas | — |
| `documents.session_report.responsible` | Penanggung jawab | — |
| `documents.session_report.opened_at` | Dibuka | — |
| `documents.session_report.cutoff` | Dihitung sampai | The cutoff |
| `documents.session_report.closed_at` | Ditutup | — |
| `documents.session_report.opening_cash` | Modal awal | — |
| `documents.session_report.sale_count` | Jumlah penjualan | — |
| `documents.session_report.sale_value` | Nilai penjualan | — |
| `documents.session_report.cash_payments` | Pembayaran tunai | Applied CASH |
| `documents.session_report.transfer_payments` | Pembayaran transfer bank | Applied BANK_TRANSFER, separately (BR-PAY-06) |
| `documents.session_report.drawer_refunds` | Pengembalian dari laci kas | Each separately |
| `documents.session_report.drawer_refund_row` | :uang · penjualan :nomor · :waktu · oleh :nama | The Owner as actor (D-035) |
| `documents.session_report.expected_cash` | Uang seharusnya di laci | — |
| `documents.session_report.physical_cash` | Uang fisik di laci | — |
| `documents.session_report.variance` | Selisih | With its word (section 1.4); numeric in the CSV export |
| `documents.session_report.closed_by` | Ditutup oleh | The close actor |
| `documents.session_report.close_reason` | Alasan penutupan | Owner-assisted close |
| `documents.session_report.notes` | Catatan | Safe notes |
| `documents.session_report.time_zone` | Zona waktu: WIB (Asia/Jakarta) | The export keeps the time zone (BR-CASH-22) |
| `documents.session_report.money_header` | :kolom (Rp) | The money columns of the export keep rupiah (BR-CASH-22) |

### 17.3 Barcode label

| Key | Copy | Field (BR-CAT-35) |
| --- | --- | --- |
| `documents.label.sku` | SKU :sku | The code and the product name are printed as they are; never a cost |

## 18. Announcements

Screen-reader announcements (UXA-17). Polite ones wait for the reader; assertive ones interrupt.

| Key | Copy | Kind |
| --- | --- | --- |
| `ux.announce.added` | Ditambahkan: :produk, jumlah :jumlah. | Polite: a scan or a pick added to the draft; never "saved" (BR-CAT-29) |
| `ux.announce.unknown_code` | Kode tidak dikenal: :kode. | Polite |
| `ux.announce.loaded` | Data selesai dimuat. | Polite |
| `ux.announce.saved` | :pesan | Polite: the page's success text |
| `ux.announce.refused` | Tidak disimpan. :pesan | Assertive: a refusal, with its message |
| `ux.announce.uncertain` | Status belum pasti. :pesan | Assertive |
| `ux.announce.session_warning` | Sesi masuk akan berakhir dalam :durasi. | Assertive |

The automatic stock refresh is not announced unless the user asked for it (UXA-17).

## 19. Changes from the working labels

Every working label of the six P7-G1 documents that this deck words differently. TASK-002 replaced these labels in the six documents under change control (finding F-03, section 22); this table stays the record of each change and its reason. The change is wording only; the control, its place, its visibility and its effect are those of the P7-G1 documents.

| Where | Working label | Final copy | Reason |
| --- | --- | --- | --- |
| UXP-410 title | Penyesuaian stok | Sesuaikan stok | CW-04: the controls that open it say "Sesuaikan stok"; a task page has a verb title. The movement type stays "Penyesuaian stok" |
| UXP-411 title | Pembatalan mutasi | Batalkan mutasi | CW-04: the controls that open it say "Batalkan mutasi". The movement type stays "Pembatalan" |
| UXP-504 title | Koreksi biaya | Lengkapi biaya, when the cost is UNKNOWN; Koreksi biaya otherwise | CW-04: the page is opened by "Lengkapi biaya" or "Koreksi biaya" |
| UXP-505 title; UXI-176, UXI-315 | Riwayat biaya produk; Riwayat biaya | Riwayat biaya | CW-04: one name; the product is named under the title |
| UXP-610 title | Form laci kas | Tambah laci kas; Ubah laci kas | CW-01, CW-04: "Form" is not a plain title; the page is opened by "Tambah laci kas" or "Ubah laci kas" |
| UXP-613 title; UXI-331 | Log keamanan | Riwayat keamanan | CW-01: plain word, in line with Riwayat aktivitas and the other histories |
| UXI-027; UXI-029; UXS-13 link | Lihat permintaan; Lihat; Lihat permintaan | Buka Permintaan belum pasti | CW-04: a button in a dialog, a banner or a disabled reason names the page it opens |
| UXI-046 | Ubah nama atau email | Ubah nama dan email | CW-04: the title of UXP-005 |
| UXI-059 | Lihat rekap harian | Rekap harian | CW-04: a link carries its page's title |
| UXI-064 | Status sistem | Status sistem perlu dicek | An attention item names the condition it reports (CW-04); the page keeps its title |
| UXI-076 | Selesaikan pembayaran yang belum pasti | Cek pembayaran yang belum pasti | "Selesaikan" is the final action of D-040, so a cashier could read it as "collect the payment"; the link leads to a status check, and the customer is never asked to pay again (BR-SALE-20) |
| UXI-077, UXI-139 | Tutup sesi | Tutup sesi kas | CW-04: the title of UXP-105; "Tutup sesi" stays the confirm button of the review dialog |
| UXI-102 | Selesaikan permintaan belum pasti | Cek permintaan yang belum pasti | As UXI-076 |
| UXI-091 | Ukuran: 58 mm / 80 mm / A4 | Ukuran struk, with 58 mm, 80 mm and A4 | A label and its options are separate texts |
| UXI-092 | Lihat detail penjualan | Detail penjualan | CW-04 |
| UXI-093, UXI-111 | Kembali ke kasir | Kembali ke Kasir | CW-03: the page title keeps its capital, and the page is not read as the person |
| UXI-118 | Pengembalian uang / retur | Pengembalian uang dan retur | CW-04: the title of UXP-203 |
| UXI-122 | Jumlah barang kembali | Jumlah barang diretur | CW-02: the GLOSSARY term "retur barang" |
| UXI-123 | Cara pembayaran kembali | Cara pengembalian uang | CW-02: the GLOSSARY term "pengembalian uang" |
| UXI-125 | Referensi pembayaran kembali | Referensi pengembalian uang | CW-02 |
| UXI-128 (UXS-14 on UXP-203) | Jangan ambil uang dari laci | Jangan ambil uang dari laci sampai pengembalian ini tercatat. Jangan buat pengembalian baru. | CW-06: the instruction says until when, and that no replacement refund is made (BR-RFD-17) |
| UXI-132, UXI-134 | Kembali ke detail penjualan | Kembali ke Detail penjualan | CW-03, CW-04 |
| UXI-133 | Ambil Rp … dari Laci kas … sekarang dan serahkan ke pelanggan | Ambil :uang dari :laci sekarang dan serahkan kepada pelanggan. | Placeholders; "kepada" for a person |
| UXI-158 | Kembali ke detail sesi | Kembali ke Detail sesi kas | CW-04: the full title |
| UXI-163, UXI-175 | Cetak label | Cetak label barcode | CW-04: the title of UXP-306 |
| UXI-165 | Ubah | Ubah produk | CW-04: the title of UXP-304 |
| UXI-173 | Nonaktifkan barcode | Pensiunkan barcode | CW-02: "Nonaktifkan" is kept for what can be reactivated; a retired barcode cannot (D-052, which the Owner worded "dipensiunkan") |
| UXI-177 | Kembali ke daftar produk | Kembali ke Produk | CW-04 |
| UXI-189, UXI-198, UXI-201 | Kembali ke detail produk | Kembali ke Detail produk | CW-03, CW-04 |
| UXI-208 | Kategori tidak ditemukan | Tidak ada kategori aktif dengan nama ini. | D-069 (h) refuses a name that matches no **active** category; an inactive category of that name exists but cannot be used |
| UXI-214 | Keadaan | Keadaan stok | A filter label names what it filters |
| UXI-230 | Referensi atau produk | Cari referensi atau produk | A search field says what it searches |
| UXI-235, UXI-293; UXS-33 action | Buat produk baru | Tambah produk | CW-04: the title of UXP-303 |
| UXI-245, UXI-251 | Kembali ke daftar barang masuk | Kembali ke Barang masuk | CW-04 |
| UXI-250, UXI-266 | Batalkan baris | Batalkan mutasi | CW-04: the title of UXP-411; "Batal" is the cancel of a form, and the line itself is not removed |
| UXI-264, UXI-267 | Kembali ke daftar barang keluar | Kembali ke Barang keluar | CW-04 |
| UXI-301 | Terbuka / Dibuka kembali | Mutasi diblokir / Mutasi dibuka kembali | An open incident means that postings are blocked; "Terbuka" next to "Dibuka kembali" read as the same thing |
| UXI-303 | Riwayat stok produk | Riwayat stok | CW-04: the title of UXP-402 |
| UXI-305 | Kembali ke daftar insiden | Kembali ke Insiden rekonsiliasi | CW-04 |
| UXI-339, UXI-349 | Kembali ke daftar pengguna | Kembali ke Pengguna | CW-04 |
| UXI-340 | Lihat sesi | Detail sesi kas | CW-04 |
| UXI-341 | Ubah | Ubah pengguna | CW-04: the title of UXP-605 |
| UXI-353; UXS-49 action | Kembali ke detail pengguna | Kembali ke Detail pengguna | CW-03, CW-04 |
| UXI-361 | Kepala dokumen; Kaki dokumen; Identitas dokumen | Teks atas dokumen; Teks bawah dokumen; Label dokumen | CW-01: plain words for what is printed (BR-CFG-01, D-062) |
| UXI-368 | Ubah | Ubah laci kas | CW-04 |
| UXI-373 | Kembali ke daftar laci kas | Kembali ke Laci kas | CW-04 |
| UXI-042, UXI-047 | Ulangi kata sandi | Ulangi kata sandi baru | Says which password is repeated |
| UXS-08 | Data yang Anda cari tidak ditemukan | Title: Tidak ditemukan; body: Data yang Anda cari tidak ditemukan. | A state has a short title and a body |
| UXS-11 | Verifikasi ulang | Konfirmasi kata sandi | CW-01: says what the user does; the GLOSSARY term is changed with it |
| UXS-40, for a reversal | Koreksi tidak dapat dihitung ulang | Pembatalan tidak dapat dihitung ulang | A reversal is not a correction of cost (BR-INV-37); the cost correction keeps the working label |
| UXS-42, for a password confirmation and for the current password | Gagal masuk | Konfirmasi gagal; Kata sandi sekarang tidak cocok | The sign-in keeps "Gagal masuk"; the other two failures are not a sign-in. Each stays one uniform text (TR-ERR-04) |

Changed: **51 rows**. Every other working label of the six documents is kept as written, with the capitalization of CW-03 (section 20). The user-task names of USER_TASKS are not interface text and keep their names.

## 20. Coverage

Checks run on this deck against the six P7-G1 documents as committed in `d4c0594` (unchanged at `cdb8361`), on 17 October 2026.

| Check | Result |
| --- | --- |
| Pages with a title (INFORMATION_ARCHITECTURE section 5) | 61 of 61 |
| Interactions named in sections 4 to 11 (INTERACTION_INVENTORY, UXI-001 to UXI-385) | 385 of 385; the hidden or not provided rows UXI-058, UXI-228, UXI-244, UXI-291 and UXI-348 are named with "No copy", and the hidden UXI-363 keeps its label for RF-4C |
| States with wording (UI_STATES section 2) | 50 of 50 |
| Error codes with a message (UI_STATES section 3; TECHNICAL_RULES section 4) | 32 of 32 |
| Command families and rows with a recovery text (UI_STATES section 4) | 24 of 24 |
| Working labels in quotation marks in the six documents | 371 distinct strings: 274 kept as written, 7 of them as a symbol or in placeholder form (+, −, "Kembali ke …", "Impor [jumlah] produk", "Sesi kas [nomor]", "masih diproses" and the document-title pattern of UXA-13); 48 changed in at least one place (section 19); 49 not interface text: 26 user-task names, the retired UXT-05 among them, 18 English terms or former titles in quotation marks, the codename and a step name, and the names of 3 controls that are hidden or not provided |
| Payment methods named | Tunai and Transfer bank only (D-077); no other method word appears |
| Words of GLOSSARY section 13 in the copy | None, except "harga pokok" inside "Harga pokok penjualan", the expansion of HPP, which names cost of goods sold and not the cost of stock |
| Copy that a staff role sees and that names cost, cost of goods sold, profit, margin or inventory value | None outside the purchase cost on receiving documents (D-033, D-047) |
| "BantuKasir" in the copy | None |

User-task labels of USER_TASKS section 3 name tasks for planning; they are not shown in the interface and have no key.

Rechecked by TASK-002 on 19 October 2026 against the six documents as revised in its package: 385 of 385 interactions are named in sections 4 to 11; UXI-058, UXI-228, UXI-291 and UXI-363 are provided and have copy, and only UXI-244 and UXI-348, not provided, have "No copy"; 50 of 50 states have wording, and the close refusal of D-088 is a second text of UXS-19, not a new state; no working label of section 19 remains in the six documents outside the user-task names of USER_TASKS.

## 21. Findings and later steps

### 21.1 Findings carried forward

Findings about the six P7-G1 documents. F-01 to F-04 are non-blocking findings made while writing this deck; they change nothing in its wording. F-05 was recorded under D-084, which corrected section 14.2 so that this deck does not carry it. The six documents are not edited, because D-082 approved them by their hashes; each finding goes to the step named.

| ID | Finding | Goes to |
| --- | --- | --- |
| F-01 | The gate rows of the six documents still read "artifacts ready for the Owner's review — NOT APPROVED". D-082 approved gate P7-G1 and prevails (SOURCE_OF_TRUTH section 1). | The next authorized revision of each document, which updates its gate row |
| F-02 | INTERACTION_INVENTORY section 3 counts UXI-076 among the 48 rows disabled with a reason, but the row states no disabled condition: it is shown only while a checkout is unresolved. | RF-5, with the full interaction contracts |
| F-03 | The working labels listed in section 19 differ from this deck. | Wording follows this deck (section 1.5); the documents keep them until their next authorized revision |
| F-04 | INFORMATION_ARCHITECTURE section 14 lists "Final wording of every label, message and state" as left to RF-4B. This deck writes it; it becomes binding with gate P7-G2. | Gate P7-G2 (the Owner) |
| F-05 | A status check that gets no answer leads to UXS-14 in INTERACTION_INVENTORY UXI-086, the "Cek status" of the uncertain panel of UXP-102, which lists UXS-14 among its results, and, for UXP-006, in its NAVIGATION_CONTRACTS row ("A resolution that fails stays UNCERTAIN (UXS-14)") and in its row button UXI-055 ("UXS-14 when the check itself gets no answer"). Read literally, these rows move a held request (UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE) into UXS-14, which offers "Kirim ulang"; ADR-007 section 5 and UI_STATES section 1 never repeat a held request. UI_STATES section 4 ("stays UNCERTAIN") agrees when UNCERTAIN includes HELD, as in ADR-007 section 5. This deck keeps the state the request was in (section 14.2, D-084). | RF-5, with the full interaction contracts: a status check without an answer leaves the request in the state it was in, so a held request stays UXS-15 with "Cek status" only; the rows are brought in line at the next authorized revision of INTERACTION_INVENTORY and NAVIGATION_CONTRACTS |

TASK-002 applies F-01, F-02, F-03 and F-05 in the six documents and records F-04 as closed by D-086, which approved this wording (section 22).

### 21.2 Left to later steps

| Item | Step |
| --- | --- |
| Approval of this wording and of the GLOSSARY shop terms | Gate P7-G2, the Owner's decision (D-025) |
| The look of every text: type scale, emphasis, the casing of the copy marker on print, icons | RF-4C (gate P7-G3) |
| The figures shown on the Beranda panel and the series of the trend chart; the 58 mm receipt layout of long names | RF-4C, key screens |
| The names of the accent colours (UXI-363) | RF-4C, with the accent set |
| The keyboard shortcuts named in accessible names (provisional, D-075) | RF-4C and gate DEVICES |
| The CSV column list and dialect, and the column headers that UXI-206 shows | RF-5 (SEC-UP-05); the headers are added here by change control |
| The document contract of the receipt, the session report and its CSV export, including where the copy marker is printed and the order of fields | RF-5 |
| The parameter that tells which reason of section 13.2 applies to `BARCODE_NOT_ELIGIBLE`, and whether the CSV preview tells an inactive category from a missing one | RF-5, command contracts |
| The list of audit action codes, each labelled from section 11.14, and the labels of the outcome codes of Riwayat keamanan (UXI-383) | RF-5 |
| The derivation of `:nomor` in the neutral name of an anonymized account (SEC-ACC-09) | RF-5 |
| Whether the reference of a drawer-funded refund is typed or generated (UXI-125) | RF-5 (SCHEMA section 16) |
| The wording of the Owner's control for another user's uncertain request (UXI-058) and of the lost-reference rule (ADR-007 section 5) | RF-5 |
| How BR-CASH-16 is met in an Owner-assisted close when the responsible user's unresolved checkouts are in that user's browser, and any text it needs | RF-5 |
| The wording of the hidden correction of an onboarding entry (UXI-228, UXI-291) | After the Owner decides T-17 b |
| The evidence behind each system-status item (UXP-611) and the wording of runbooks and user guidance, including the operational rule of D-068 | RF-6, using the terms of GLOSSARY and this deck |
| Any tax or buyer-identity text on documents | Gate G1 |
| Real paper widths, printers and the tablet's on-screen keyboard | Gate DEVICES |
| A text that a Task needs and that this deck does not name | Added here by change control before it ships (section 1.1) |

Done by TASK-002 (section 22): the names of the accent colours; the CSV column headers of UXI-206; the labels of the audit action codes of INTERACTION_CONTRACTS section 8; the wording of UXI-058 and of the lost-reference rule; the wording of the corrections of onboarding entries (UXI-228, UXI-291); the texts of BR-CASH-16 in an Owner-assisted close.

## 22. Change control

Every change of change-control batch 1, [TASK-002](../11-tasks/tasks/TASK-002-p7-change-control-1.md), with its reason and the decision it comes from, so that the Owner reviews one list (PROJECT_RULES section 6). The package is approved by the Git blob, size and SHA-256 of each artifact at gate GATE-CC1 ([TASK_PLAN](../11-tasks/TASK_PLAN.md) section 3); the values are in the Task's [evidence](../11-tasks/evidence/TASK-002.md). Until then the texts added here are proposals and no Task uses them (section 1.1; [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md) T-19, T-20). No page, control, state, navigation path, business rule or visual design changes beyond the rows that the later decisions named below already changed.

### 22.1 Changes

| ID | Change | Where | Reason | Source |
| --- | --- | --- | --- | --- |
| CC-01 | The gate rows name the approving decision | The six P7-G1 documents; this deck; GLOSSARY, at the Owner's request | Finding F-01: the rows still read "NOT APPROVED" | D-082; D-086; the Owner's answer of 19 October 2026 for GLOSSARY |
| CC-02 | The precedence rule states that gate P7-G2 approved this wording | Section 1.5 | Finding F-04, closed: no other change needed | D-086 |
| CC-03 | The percent format `32,0%` | Section 1.3 | OPEN_ITEMS T-19; DESIGN_SYSTEM section 13 | D-087 |
| CC-04 | `ux.action.more_actions` | Section 5 | T-19 | D-087 |
| CC-05 | The five accent names `config.profile.accent_option.*`; UXI-363 provided | Section 11.8; INTERACTION_INVENTORY UXI-363 | T-19; INTERACTION_CONTRACTS section 10 | D-086, D-087 |
| CC-06 | `reporting.period.label` and `reporting.finance.table_date` | Section 10.3 | T-19 | D-087 |
| CC-07 | `commands.requests.other_user`, `commands.requests.other_user_help`, `commands.requests.from_server` and `commands.requests.lost_help`; the note of UXI-025 | Sections 6.1 and 6.7 | T-20; INTERACTION_CONTRACTS sections 2.2, 2.4 and 11 | D-087 (N-07, ADR-009) |
| CC-08 | `ux.disabled.unresolved_checkouts` for UXI-103 and UXI-150, in place of `cash.close.disabled_unresolved_checkout`, which named UXI-103 only and which no Task contract names | Section 15 | T-20; INTERACTION_CONTRACTS sections 2.5 and 11: one reason for both closes | D-087 |
| CC-09 | The responsible user's unresolved checkouts in the Owner-assisted close preview, with existing keys only | Section 7.12, UXI-149 | INTERACTION_CONTRACTS section 2.5 | D-087 |
| CC-10 | The corrections of onboarding entries: `inventory.onboarding.withdraw_zero` with its review dialog, reason and success; `inventory.opening_entry.replace` with its eligibility, reason, review title and success; UXI-228 "Batalkan mutasi" for an OPENING; the note of UXI-292 | Sections 9.4, 9.13 and 9.14 | T-20; INTERACTION_CONTRACTS section 11. The two "Alasan" labels are added by TASK-002 because RC-14 and RC-48 carry a `reason` | D-087 (T-17 b) |
| CC-11 | The labels `audit.action.opening_stock_replace`, `audit.action.zero_count_withdraw`, `audit.action.onboarding_restore_hold`, `audit.action.restore_reconciliation` and `audit.action.projection_rebuilt`; the list of codes is INTERACTION_CONTRACTS section 8 | Section 11.14 | T-20 | D-072, D-073, D-087, D-088 |
| CC-12 | The CSV column headers of DOCUMENT_CONTRACTS DC-04 that UXI-206 shows, `catalog.csv_import.column.*` | Section 8.9 | T-20 | D-087 (DC-04) |
| CC-13 | A dedicated message for the close refused while a checkout is unresolved: `ux.state.recount.unresolved_checkouts`, a second text of UXS-19 with no new state, code or control | Sections 12 and 13.1 | The Owner's answer "A — Pesan khusus", with this wording, to question 1 of TASK-002 | D-088 (G-27); the Owner's answer of 19 October 2026 |
| CC-14 | The backup store's usage as a fifth item of the system status: `config.system_status.backup_store` and `config.system_status.backup_store_usage`, with its evidence | Section 11.11; OPERATIONS_RUNBOOK section 5 | The Owner's answer "A — Tampilkan" to question 2 of TASK-002; ADR-010 item 11 | D-088 (ADR-010); the Owner's answer of 19 October 2026 |
| CC-15 | The disabled reasons cover the 49 rows re-derived in INTERACTION_INVENTORY section 3; UXI-228 joins `inventory.reversal.disabled_unresolved` | Section 15 | Finding F-02 | D-087 |
| CC-16 | The working labels of section 19 replaced by this deck's wording; the user-task names of USER_TASKS kept | The six P7-G1 documents; section 19 | Finding F-03 | D-082, D-086 |
| CC-17 | The rows of INTERACTION_CONTRACTS section 10 applied: UXI-025, UXI-029, UXI-055, UXI-058, UXI-076, UXI-086, UXI-103, UXI-149, UXI-150, UXI-227, UXI-228, UXI-291, UXI-292 and UXI-363, with the decisions applied in INTERACTION_INVENTORY section 5 and UXI-291 in its section 6; the UXP-006 row of NAVIGATION_CONTRACTS. UXI-244 and UXI-348 stay not provided | INTERACTION_INVENTORY; NAVIGATION_CONTRACTS | OPEN_ITEMS T-20 | D-069, D-048, D-084, D-086, D-087 |
| CC-18 | INTERACTION_INVENTORY section 3 re-derived from its rows: 383 working, 49 of them disabled with a reason, 0 hidden by design, 2 not provided, 385 in all | INTERACTION_INVENTORY section 3 | Finding F-02 | D-087 |
| CC-19 | A status check without an answer leaves the request in the state it was in | INTERACTION_INVENTORY UXI-055 and UXI-086; NAVIGATION_CONTRACTS UXP-006 | Finding F-05 | D-084 |
| CC-20 | The coverage rechecked; the findings and later steps marked as applied or done | Sections 20 and 21 | The checks of section 20 kept true after CC-01 to CC-19 | D-082, D-086 |

### 22.2 Left for a later change control

Read with the decisions named, as INTERACTION_CONTRACTS section 10 reads the rows above; not changed here, because neither this Task's contract nor INTERACTION_CONTRACTS section 10 names them:

| Item | Reading | Decision |
| --- | --- | --- |
| INFORMATION_ARCHITECTURE: the capability map lists no UXI-058 (CAP-34), UXI-228 (CAP-15, CAP-17), UXI-291 (CAP-15) or UXI-363 (CAP-30); P7-G07, the T-17 b row of its open items and its list of decisions still before RF-5 describe the correction of onboarding entries as hidden | The rows are provided (CC-17) | D-086, D-087 |
| INTERACTION_INVENTORY section 3: "The block acts only in the browser that holds the request" | The request receipts carry the block across devices (INTERACTION_CONTRACTS section 1.4) | D-087 |
| UI_STATES UXS-19: its trigger names a Sale or a drawer-funded refund after the preview | An unresolved checkout also refuses the close (INTERACTION_CONTRACTS section 2.5), with the text of CC-13 | D-088 |
| The intro of INTERACTION_INVENTORY and INFORMATION_ARCHITECTURE section 14 still call the labels provisional until gate P7-G2 | Gate P7-G2 is approved; this deck prevails (section 1.5) | D-086 |
| RELEASE_PROCESS section 4 item 4 and the contract of TASK-039 name four system-status items | Five items (CC-14) | The Owner's answer of 19 October 2026 |
| DESIGN_SYSTEM section 13, INTERACTION_CONTRACTS sections 8, 11 and 12, DOCUMENT_CONTRACTS DC-04 and the previews' `BK_COPY_PROPOSED` call these texts proposals | After gate GATE-CC1 they are approved copy in this deck; DESIGN_SYSTEM and the previews are approved artifacts and stay byte-identical | D-087 |
