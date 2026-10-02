# 01 — Produk dan batas Core

**Baseline P0–P11.** [P1](planning/P1-PRODUCT.md) dan [CORE-MAP](requirements/CORE-MAP.md) menyediakan phase/traceability; Plan 2.0 adalah input historis, bukan plan execution. BantuKasir adalah codename internal, bukan merek client produksi. Produk adalah POS GOODS-only self-hosted untuk satu bisnis, satu stock pool, penjualan langsung dan dibayar penuh. BusinessProfile runtime menyediakan nama/logo resmi, alamat/kontak, identitas dokumen dan accent yang diizinkan. Identitas final disediakan owner kemudian.

## Hasil yang harus dicapai

Kasir dapat Scan/Cari → Keranjang → Bayar → Struk melalui session sendiri, tanpa oversell atau double charge saat retry. Operations dapat mengelola barang, menerima barang dan mencatat pengeluaran non-sale, dengan stock/history yang benar tanpa akses profit. Pemilik dapat melihat HPP/Laba Kotor yang jujur, accountability kas, koreksi append-only dan bukti audit; bisnis dapat dipulihkan dari backup independen.

Client awal berorientasi ATK. Fixture non-production memakai barang ATK sintetis; bukan seed bisnis produksi atau data client yang diasumsikan benar. Barang tanpa gambar tetap mudah ditemukan. Upload/storage gambar produk bukan Core; upload logo bisnis dibatasi [04](04-AUTH-RBAC-SECURITY.md).

## Role dan menu yang sah

| Role | Workspace/capability Core |
| --- | --- |
| SUPER_ADMIN / Pemilik | Seluruh katalog/inventory; opening, cost evidence/replay/adjustment/reversal; POS dengan sesi sendiri; refund/return; laporan penjualan/HPP/Laba Kotor, semua sesi dan audit; pengguna, profil bisnis/register dan status sistem/backup |
| OPERATIONS_ADMIN / Admin Operasional | Katalog/kategori/brand/unit/harga/minimum/active, barcode/label; receiving dengan bukti biaya baru write-only atau UNKNOWN; manual issue reason wajib; current stock, LOW/OUT dan history operasional aman; akun sendiri |
| CASHIER / Kasir | Search/scan operational; buka/tutup sesi sendiri, cart/pembayaran tunai/transfer, Sale/history/receipt serta laporan sesi sendiri; akun sendiri. Tidak mengelola produk atau stock |

Backend permission dan cakupan objek adalah otoritas [04](04-AUTH-RBAC-SECURITY.md). Pemilik checkout sebagai dirinya sendiri; Admin Operasional tidak mempunyai POS/session. Menu hanya ditampilkan bila capability aktif dan dapat dipakai; template desain bukan pembuka scope.

## Alur dan fakta utama

- Master produk baru stock 0. Search/scan/label tidak membuat movement; duplicate SKU/barcode diblok DB, similar match ditinjau sebelum create.
- Receiving terpisah dari opening awal dan manual issue. Review/confirm eksplisit diperlukan; Operations tidak mendapat echo biaya yang baru dikirim.
- Opening awal manual owner-only memakai global onboarding freeze, batas 100 row/command atomik, produk tanpa ledger, zero checklist tanpa movement; data nyata menunggu G2/cutover.
- Sale GOODS diserahkan langsung, satu full CASH atau BANK_TRANSFER. Satu transaksi mencatat semua Sale/lines/payment/issue/balance/cost/receipt/audit/result; human transfer confirmation tidak mengklaim gateway. Tepat satu full-payment CASH atau BANK_TRANSFER; mixed/split/partial/installment/credit/multiple instruments ditolak. Method/provider boundary dapat diextend kelak tanpa callback/webhook/QRIS/asynchronous payment V1.
- Sesi kas: satu drawer, satu responsible cashier, satu OPEN; openingCash eksplisit termasuk Rp0. Close aman terhadap Sale racing, snapshot immutable, handover tutup A lalu buka B, kembali tanggal sama memakai sesi baru.
- Koreksi/refund/return owner-only, reason/recent auth, original tidak berubah. Refund tanpa return tidak menambah stok. Return saleable memakai cost historis; damaged tidak menambah usable stock.
- MWA dan UNKNOWN biaya; penjualan masih dapat dilaporkan saat HPP/Laba Kotor incomplete. Laba Kotor tidak diklaim sebagai Laba Bersih.
- Thermal/A4 nota komersial/browser PDF, label Code128 dan laporan sesi print/PDF/CSV memakai DTO aman. Reprint snapshot asli/nomor sama, bukan transaksi baru.
- Restock adalah current LOW/OUT list/badge, bukan notifikasi pengiriman. Query gagal/stale bukan nol atau kosong.
- Audit, forward migration, reconciliation, backup DB+aset/template, isolated restore, recoveryEpoch dan post-backup gap review wajib sebelum release.

Detail unik berada di [02](02-ARCHITECTURE.md), [06](06-INVENTORY-SPEC.md), [12](12-BARCODE-SCANNER.md), [15](15-FINANCE-PROFITABILITY.md), [17](17-POS-SALES.md) dan [09](09-DEPLOYMENT-OPS.md). [08](08-TESTING-ACCEPTANCE.md) menetapkan ownership bukti, termasuk browser/mobile/a11y, actual devices dan performance.

## Batas Later / tidak diimplementasikan

Services, booking/Order/DP/deferred fulfillment/reservation; warehouses/location/transfers/transit; HR/attendance/payroll/complex shifts/shared active drawer/paid-in-out/cash pickup; price override/discount engine; unpaid/split payments; AR/AP/Laba Bersih/expenses/tax accounting; serial/pack conversion/batch/expiry/consignment; supplier purchasing otomatis/CRM/leads/targets; CSV/XLSX import; gambar produk upload/storage; Web Push/email notification/outbox/worker umum; kamera scan/offline sale queue; gateway/QRIS/card/e-wallet; public signup/TOTP/dynamic roles/multi-tenant.

Future method codes atau template visual tidak mengaktifkan capability tersebut. Bila kebutuhan owner ternyata mensyaratkan serial/import/tax/shared drawer, hentikan slice terkait dan catat keputusan/replan sebelum coding. Jangan mengurangi integritas untuk mengejar tanggal.

## Kebijakan yang belum disetujui

DESIGN menunggu approval visual revision sumber; G1 menunggu tax/legal nota dan buyer policy; G2 unit/opening volume/barcode/cost/serial; G3 drawer/float/handover/refund funding; G4 independent destination/recovery access/RPO/RTO; DEVICES actual scanner/printer; RELEASE akses/otorisasi dan pilot. Status di [10](10-DECISIONS.md). Owner waiting tidak menghalangi backend/test non-production yang independen, tetapi tidak memberi izin memakai asumsi untuk real cutover.

Biaya software/SaaS tambahan ditargetkan sekitar Rp0 dengan stack open-source/VPS/domain yang ada; biaya backup/storage dan hak DesainPakeAI nyata tetap diverifikasi. Tidak ada pembelian atau penggunaan berbayar yang diotorisasi dalam sesi ini.
