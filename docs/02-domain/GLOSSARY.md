# Glossary

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P2 — domain glossary |
| Map | [DOMAIN_MODEL](DOMAIN_MODEL.md) |

One concept, one canonical English term, one Indonesian shop term. Repository documents, code identifiers and Task contracts use the English term. The interface uses the Indonesian term.

## 1. Rules

- **One concept, one term.** Each row is one concept. Do not introduce a synonym; if a new concept is needed, add a row in the step that needs it.
- **The Indonesian shop terms are provisional.** They become binding only when the Owner approves the terminology and copy at gate P7-G2 (D-025). Until then they are the starting point for RF-4, not approved interface copy.
- **Term source.** The last column says where the Indonesian term comes from: a decision, a legacy document (keys as in DOMAIN_MODEL section 2), or "proposed" when no source used one. A proposed term carries no business meaning.
- **Internal terms.** Some concepts are never shown to shop users. Their Indonesian column says so explicitly.
- **"Owner".** In domain documents "Owner" is a user with role SUPER_ADMIN at an installation. The project authority is also called the Owner in the governance documents; product decisions are cited by `D-…` number to keep the two apart.

## 2. People, roles and access

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Owner (role SUPER_ADMIN) | The highest role at an installation: the shop owner | Pemilik | L-PRD, L-04 |
| OPERATIONS_ADMIN | The role that manages the catalogue, receiving, manual issue and stock | Admin Operasional | L-PRD, L-04 |
| CASHIER | The role that sells at the till in its own cash session | Kasir | L-PRD, L-04 |
| Combined account | One account holding CASHIER and OPERATIONS_ADMIN together (D-032) | Akun Kasir dan Admin Operasional | proposed |
| User | An individual account of one person | Pengguna | L-04 |
| Role | One of the three fixed roles | Peran | proposed |
| Sign-in session | An authenticated browser session; never the same thing as a cash session | Sesi masuk | proposed |
| Recent authentication | Authentication within the last 15 minutes, required before a sensitive Owner action (D-013) | Verifikasi ulang | proposed |
| Deactivate | Take a record out of new use while keeping it and its history (users, categories, products, cash registers) | Nonaktifkan | L-PRD |
| Reactivate | Return a deactivated record to use (D-042) | Aktifkan kembali | proposed |
| Anonymize (account) | The Owner replaces the personal fields of a deactivated account with a neutral label, permanently, without rewriting any historical record (D-043) | Anonimkan akun | proposed |
| Permission denied | The server refused an action for this user | Anda tidak memiliki akses | AICWDF §4B.1 example |

## 3. The shop and its configuration

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Installation | One deployment of BantuKasir for one business with one stock pool (D-038) | — internal term; not shown to shop users | — |
| Codename | "BantuKasir", the internal product name; never the shop's identity (D-038) | — internal term; not shown to shop users | — |
| BusinessProfile | The shop's identity: name, logo, address, contact, document header and footer, accent | Profil Usaha | proposed |
| BusinessProfileRevision | One immutable version of the BusinessProfile | Versi Profil Usaha | proposed |
| Accent | The one constrained accent colour of the shop | Warna aksen | proposed |
| System status | The Owner's view of backup, restore and reconciliation health | Status sistem | L-PRD |
| Unverified | A status that has not been proven by real evidence | Belum Terverifikasi | L-09 |

## 4. Catalogue

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Product | A goods item sold from the one stock pool | Produk | L-12 |
| Category | The grouping every product belongs to | Kategori | L-PRD |
| Brand | Optional product attribute | Merek | proposed |
| SKU | The product's unique text code | SKU | L-12 |
| Base unit | The unit in which a product is stocked, received, issued and sold | Satuan | proposed |
| Quantity precision | Number of decimals (0 to 3) allowed for a product's quantities | Jumlah desimal | proposed |
| Selling price | The current authoritative price of a product, in whole rupiah | Harga jual | L-04 |
| Price change | Changing the selling price in master data (D-036) | Ubah harga | proposed |
| SellingPriceRevision | One recorded selling price and when it applied | Riwayat harga jual | proposed |
| Minimum stock | The threshold at or below which stock is LOW | Stok minimum | proposed |
| Active product / inactive product | Whether a product can be posted and sold | Aktif / Nonaktif | L-PRD |
| Barcode | A token that identifies exactly one product | Barcode | L-12 |
| Manufacturer barcode | A barcode printed by the maker, registered as an alias | Barcode pabrik | proposed |
| Internal code | A barcode generated by the server (`BK-P-…`) | Barcode internal | proposed |
| Retire (barcode) | Take a barcode out of use, recording whether it was retired as a misassignment or for another reason. A retired token is not reused, except through a barcode misassignment correction (D-052) | Nonaktifkan barcode | proposed |
| Barcode misassignment correction | The Owner's audited correction that moves a manufacturer barcode from the wrong product to the correct one, or re-attaches one retired as a misassignment (D-051, D-052) | Koreksi salah pasang barcode | proposed; "salah pasang" from D-051 |
| Unknown token | A scanned code that matches no product | Kode tidak dikenal | proposed |
| Product Search | The one shared search for products | Cari produk | proposed |
| Similar product | A warning that a product with a similar name exists | Produk mirip | proposed |
| Create anyway | The explicit confirmation after a similar-product warning | Tetap buat produk baru | L-12 |
| Label | A printed Code 128 barcode label | Label barcode | L-12 |
| Product photo | The one optional primary photo of a product (D-037) | Foto produk | proposed |
| Thumbnail | The small version of the product photo shown at the till | Foto kecil | proposed |
| Photo placeholder | The image shown when a product has no photo | Gambar pengganti | proposed |
| CSV product import | Creating new products from a CSV file (D-037) | Impor produk (CSV) | proposed |

## 5. Stock

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Stock pool | All stock of the business, without locations | — internal term; not shown to shop users | — |
| Stock (on-hand quantity) | The current quantity of a product; StockBalance is its record | Stok | L-06 |
| Stock movement | One immutable physical stock change | Mutasi stok | proposed |
| Stock history | The list of a product's movements | Riwayat stok | L-P3 |
| Posting sequence | The order of movements within a product | — internal term; not shown to shop users | — |
| Opening stock (OPENING) | The Owner's initial count entered as stock | Stok awal | proposed |
| Onboarding | The period while opening stock is entered and ordinary postings are frozen | Masa input stok awal | proposed |
| Onboarding checklist | The list of products counted, including zero counts | Daftar periksa stok awal | proposed |
| Cutover | The documented moment the shop starts using BantuKasir with real data | Peralihan ke sistem | proposed |
| Receiving (RECEIPT) | Goods arriving into stock | Barang masuk | L-06 |
| Receiving document | The record of one receiving, line by line, before and after confirmation (D-033) | Dokumen barang masuk | proposed |
| Sale issue (SALE_ISSUE) | Stock leaving through a completed Sale | Keluar karena penjualan | proposed |
| Manual issue (MANUAL_ISSUE) | Stock leaving for a reason other than a sale | Barang keluar | L-06, D-018 |
| Manual issue reason | Damaged, internal use, lost, sample, other | Rusak, Pemakaian internal, Hilang, Sampel, Lainnya | L-06 |
| Adjustment (ADJUSTMENT) | An Owner-verified signed correction of physical stock | Penyesuaian stok | proposed |
| Reversal (REVERSAL) | The exact inverse of one eligible source movement | Pembatalan | proposed |
| Return to stock (RETURN_RECEIPT) | Saleable returned goods coming back into stock | Retur masuk stok | proposed |
| Count | A physical count of goods by a person | Hitung fisik | proposed |
| Reference | An optional external document number on a movement | Nomor referensi | proposed |
| Stock attention | The in-app list and badge of LOW and OUT products | Perlu restok | proposed |
| NORMAL / LOW / OUT | Stock attention states | Normal / Menipis / Habis | L-06 |
| Reconciliation | The daily comparison of movements with balances and cost projections | Rekonsiliasi | L-06 |
| Posting block | The stop on postings of a product after a reconciliation mismatch | Mutasi diblokir | proposed |

## 6. Cost and profit

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Acquisition cost | The total cost at which stock entered. D-033 and D-047 call it "purchase cost" when it is entered on, or shown on, a receiving document; "purchase cost" is used only in that sense, and elsewhere new text uses "acquisition cost" | Biaya perolehan | L-04 |
| Acquisition cost evidence | The record of acquisition cost for an OPENING, RECEIPT or positive ADJUSTMENT | Bukti biaya | proposed |
| KNOWN cost | Cost with evidence | Biaya diketahui | proposed |
| UNKNOWN cost | Cost not yet available; never zero (D-033) | Biaya perlu dilengkapi pemilik | D-033 |
| Evidenced zero cost | An explicit zero cost confirmed by the Owner | Biaya nol (berbukti) | proposed |
| Moving weighted average (MWA) | The costing method | — internal term; not shown to shop users | — |
| Average cost | The current average cost per base unit of a product | Biaya rata-rata | proposed |
| Inventory value | The current value of a product's stock, or of all stock | Nilai persediaan | proposed |
| Issue-cost allocation | The cost assigned to an issued quantity (sale issue, manual issue, negative adjustment) | — internal term; not shown to shop users as such ("Barang keluar" means manual issue, D-018) | — |
| Cost of goods sold (COGS) | The cost of what was sold | HPP | L-15 |
| Gross sales | Completed sales before refunds | Penjualan bruto | L-15 |
| Net sales | Gross sales minus refunds | Penjualan neto | L-15 |
| Gross profit | Net sales minus net COGS | Laba Kotor | L-15 |
| Margin | Gross profit as a share of net sales, shown only when cost is complete | Margin | L-15 |
| Incomplete cost | A figure that depends on UNKNOWN cost | Data biaya belum lengkap | L-15 |
| Cost pool | A run of a product's valuation that is entirely known or affected by unknown cost | — internal term; not shown to shop users | — |
| Cost correction | An Owner fact that corrects or completes acquisition cost after posting (D-034) | Koreksi biaya | proposed |
| Owner correction mark | The mark on a confirmed receiving document that tells OPERATIONS_ADMIN the Owner corrected or completed its cost, shown with the current cost and the cost at confirmation (D-047) | Dikoreksi Pemilik | D-047 |
| Replay | The bounded recalculation that follows a cost correction or reversal | — internal term; not shown to shop users | — |
| Corrected projection | The current valuation, COGS and profitability after the latest published replay | — internal term; not shown to shop users | — |

## 7. Sales and payment

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Sale | A completed, fully paid sale of goods handed over directly | Penjualan | proposed |
| Sale line | One product line of a Sale | Baris penjualan | proposed |
| Cart | The editable draft of a Sale before payment; not a fact | Keranjang | L-PRD |
| Payment screen | The one screen where an ordinary Sale is paid and confirmed (D-040) | Layar pembayaran | proposed |
| Final action | The one action that posts the Sale (D-040) | Selesaikan | D-040 |
| Payment | The one full payment of a Sale | Pembayaran | L-17 |
| CASH | Payment method: cash | Tunai | L-04 |
| BANK_TRANSFER | Payment method: bank transfer, confirmed by the cashier | Transfer bank | L-04 |
| STATIC_QRIS | **Retired by D-077.** Formerly a V1 payment method (D-036); not a payment method in V1. The row is kept so the term is not reused with another meaning | None in V1 (formerly "QRIS statis") | D-077 |
| Tendered amount | Cash handed over by the customer | Uang diterima | proposed |
| Change | Tendered amount minus total | Kembalian | proposed |
| Exact cash | The action that sets the tendered amount to the total (D-040) | Uang Pas | D-040 |
| Funds-received confirmation | The cashier's confirmation, in the final button, that a bank transfer payment arrived | Dana sudah diterima — Selesaikan | D-040 |
| Payment reference | An optional reference of a bank transfer payment | Referensi pembayaran | proposed |
| Sale total | The sum of line totals | Total | proposed |
| Line total | Quantity × unit price, rounded once | Subtotal | proposed |
| Stale price | The selling price changed while the cart was open | Harga berubah | proposed |
| Business date | The Asia/Jakarta calendar date of a fact | Tanggal usaha | proposed |
| Customer name | Optional name printed on the receipt | Nama pelanggan | L-17 |

## 8. Documents

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Receipt | The customer document of a Sale, from its ReceiptSnapshot | Struk | L-PRD |
| ReceiptSnapshot | The immutable content of a receipt | — internal term; shown as Struk | — |
| A4 commercial invoice | The A4 rendering of the receipt; not a tax invoice | Nota Penjualan | L-17 |
| Receipt template | Thermal 58 mm, thermal 80 mm or A4 (D-039) | Ukuran struk | proposed |
| Reprint | Printing the same document again | Cetak ulang | L-17 |
| Copy marker | The mark that shows a reprint | Salinan | proposed |

## 9. Cash

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Cash register | The logical identity of one physical cash drawer. Decisions also say "drawer" for the same thing. New text uses "cash register" for the configured identity; "drawer" alone is used only for the physical drawer and the cash in it (counting the drawer, cash leaving the drawer), and in the fixed term "drawer-funded refund" | Laci kas | proposed |
| Cash session | One accountability period of one responsible user at one cash register; not a work shift | Sesi kas | L-PRD |
| Responsible user | The user accountable for a cash session | Penanggung jawab sesi | proposed |
| Opening cash | The counted cash in the drawer when a session opens; Rp0 is valid | Modal awal | proposed |
| Expected cash | What the drawer should hold (D-035 formula) | Uang seharusnya | proposed |
| Physical cash | What was counted in the drawer at close | Uang fisik | proposed |
| Variance | Physical cash minus expected cash | Selisih | proposed |
| Close preview | The figures shown before the cash count at close | Pratinjau tutup sesi | proposed |
| Close (session) | Ending a cash session with a count | Tutup sesi | proposed |
| Owner-assisted close | The Owner closing another user's session | Tutup sesi oleh Pemilik | proposed |
| Activity version | The counter that detects new activity during a close | — internal term; not shown to shop users | — |
| Recount required | A close refused because of new activity | Ada transaksi baru; hitung ulang kas | L-17 |
| Session report | The immutable report of a closed cash session | Laporan sesi kas | L-17 |
| Owner review note | A note the Owner links to a closed session report | Catatan tinjauan Pemilik | proposed |
| Handover | One user closing and another opening a session on the same cash register | Serah terima | proposed |

## 10. Refunds and returns

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Refund | Money paid back against an original Sale | Pengembalian uang | proposed |
| Return | Goods physically brought back against an original Sale | Retur barang | proposed |
| Saleable return | Returned goods fit to sell again | Retur layak jual | proposed |
| Non-saleable return | Returned goods that are damaged or otherwise unfit to sell | Retur tidak layak jual | proposed |
| Drawer-funded refund | A CASH refund paid from the drawer of an OPEN cash session (D-035) | Pengembalian dari laci kas | proposed |
| Owner-funded refund | A CASH refund paid from the Owner's own money (D-035) | Pengembalian dari dana Pemilik | proposed |

## 11. Reporting and audit

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Finance report | The Owner's report of sales, refunds, COGS and gross profit | Laporan keuangan | proposed |
| Daily summary | The Owner's per-day summary | Rekap harian | L-17 |
| Report periods | Today, 7 days, this month, last month, custom range | Hari Ini, 7 Hari, Bulan Ini, Bulan Lalu, Rentang Kustom | L-15 |
| As-of time | The moment a report's data reflects | Data per | proposed |
| Audit log | The Owner's list of audited changes | Riwayat aktivitas | proposed |
| Audit event | One audited change | — internal term; shown in Riwayat aktivitas | — |
| Backup | An independent copy of the data | Cadangan data | proposed |
| Restore | Bringing data back from a backup | Pemulihan data | proposed |
| Business record | A book, record, document or electronic data that forms the basis of bookkeeping or of business and tax records (D-044) | Catatan usaha | proposed |
| Minimum retention period | The at least 10 years a business record is kept; not a deletion deadline (D-044) | Masa simpan minimum | proposed |

## 12. Commands and states

| English term | Meaning | Indonesian shop term | Term source |
| --- | --- | --- | --- |
| Draft | Editable input that is not yet a fact | Draf | proposed |
| Review | The step that shows what a command will do | Tinjau | proposed |
| Confirm | The explicit action that posts a command | Konfirmasi | proposed |
| Committed | The server confirmed the durable result | Berhasil disimpan | proposed |
| Definite rejection | The server refused, and nothing was posted | Ditolak | proposed |
| Uncertain | The result is not yet known; the original request is kept | Status belum pasti | proposed |
| Held | An uncertain result that the client must not repeat, because the server reported an identity conflict, which its actor or the Owner resolves, or an intent from before a restore, which the Owner checks under the restore runbook (ADR-007 section 5) | — internal term; shown to shop users as "Status belum pasti" | — |
| Stale | Data that may be out of date after a failed refresh | Data belum diperbarui | proposed |
| Source intent, idempotency key, fingerprint, command result, recovery epoch | The identity and durable result of a critical command (DOMAIN_MODEL section 7) | — internal terms; not shown to shop users | — |

## 13. Words not to use

| Do not use | Use instead | Why |
| --- | --- | --- |
| shift, shift kasir | cash session (Sesi kas) | A cash session is not a work shift (L-17) |
| purchase cost (except for the cost on a receiving document, as D-033 and D-047 use it), harga modal, harga pokok (for stock) | acquisition cost (Biaya perolehan) | One term per concept |
| laba (alone), laba bersih | gross profit (Laba Kotor) | V1 reports gross profit only (L-15) |
| drawer (alone, for the configured identity) | cash register (Laci kas) | One term per concept; "drawer" alone names only the physical drawer and its cash |
| stok opname | count, adjustment | Advanced stock-taking is outside V1 |
| diskon, potongan harga | — | No discounts in V1 (D-036) |
| pending payment, menunggu pembayaran | — | No pending payment state in V1 (D-036) |
| order, pesanan, DP | — | Outside V1 (D-002) |
| evidence revision, valuation version | cost correction, corrected projection | Superseded wording (D-034) |
| write-only cost | — | Rule superseded by D-033 |
| session (alone) for a sign-in session | sign-in session (Sesi masuk) | Two different concepts. In the P1 and P2 documents "session" alone means a cash session (DOMAIN_MODEL section 1); a sign-in session is always named in full |
