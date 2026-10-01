# 06 — Stok, opening dan perhatian restock

**Plan 2.0.** Satu stock pool untuk seluruh bisnis; tidak ada Warehouse/StockLocation atau reserved quantity. StockMovement immutable adalah kebenaran fisik. StockBalance diperbarui sinkron dalam transaksi yang sama; onHand sama dengan jumlah signed ledger. Stok negatif dilarang. Dokumen ini adalah spesifikasi, bukan bukti runtime sudah tersedia.

## Command fisik

| Type | Dampak dan bukti yang wajib |
| --- | --- |
| OPENING | Qty positif dari count awal yang diverifikasi Pemilik, hanya produk tanpa ledger sebelumnya; cost evidence atau UNKNOWN |
| RECEIPT | Qty positif; acquisition cost evidence atau UNKNOWN; optional source/reference |
| SALE_ISSUE | Qty negatif, khusus dari completed Sale yang atomik |
| MANUAL_ISSUE | Qty negatif untuk rusak/pemakaian internal/hilang/sampel/lainnya; reason wajib; bukan pengganti penjualan pelanggan |
| ADJUSTMENT | Koreksi fisik bertanda positif/negatif yang diverifikasi Pemilik dengan reason/count/reference; qty positif memerlukan cost evidence |
| REVERSAL | Invers terhubung dari standalone opening/receipt/manual issue yang eligible; hanya Pemilik; original dipertahankan |
| RETURN_RECEIPT | Return fisik saleable yang diverifikasi dan terhubung ke SaleLine/issue asli; qty dibatasi; memulihkan historical cost |

Setiap command memvalidasi active product, unit precision, qty/range, actor/permission, reason, eligibility source dan version di bawah product locks terurut menurut [02](02-ARCHITECTURE.md). Aggregate line produk duplikat sebelum pemeriksaan stok. Append movement/cost facts, balance, audit dan durable result bersifat atomik. Dua request untuk unit terakhir menghasilkan satu sukses dan satu insufficient-stock rejection, tanpa oversell. Waktu server UTC dan urutan posting per produk adalah fakta; tanggal dokumen opsional tidak mengubah urutan atau mengizinkan backdating.

Key/source intent, retry, hasil tidak pasti dan recovery mengikuti kontrak command di [02](02-ARCHITECTURE.md). Response hilang dipulihkan melalui status/retry command awal, bukan movement pengganti. Gagal menulis cost/audit/result juga me-rollback movement dan balance.

## Cari/buat, receiving dan manual issue

Cari nama/SKU/barcode/kategori/brand melalui [Product Search](12-BARCODE-SCANNER.md); pakai produk yang sudah ada. Jika belum ada, Admin Operasional/Pemilik meninjau similar matches sebelum membuat produk/barcode. Exact SKU/barcode collision tetap ditolak database. Kasir tidak membuat master/alias. Product baru mulai pada stock 0; membuat master atau mendengar beep tidak mem-posting stok.

Barang Masuk: scan/cari → pilih produk existing atau pembuatan search-first yang berizin → qty + cost evidence baru atau UNKNOWN → source/reference → review → confirm → satu RECEIPT. Biaya yang hilang adalah UNKNOWN, bukan nol. Cost input Admin Operasional bersifat write-only menurut [04](04-AUTH-RBAC-SECURITY.md); tidak diulang dalam review/result/history/export/cache atau browser recovery. Pemilik dapat melengkapi evidence kemudian menurut [15](15-FINANCE-PROFITABILITY.md).

Barang Keluar: scan/cari → qty → reason non-sale wajib → review → confirm → satu MANUAL_ISSUE. Validasi qty/stok/precision tetap berlaku. Issue non-sale mengurangi inventory value, tetapi tidak dihitung sebagai sales HPP. Tidak ada jalan manual issue untuk menyembunyikan penjualan.

## Opening manual dan cutover

Core menggunakan master manual dan opening manual yang dibatasi. G2 harus mengonfirmasi unit/precision, volume catalog/opening, barcode, ketersediaan cost evidence dan kebutuhan serial sebelum data nyata. Bila manual onboarding tidak praktis atau serial/pack/batch ternyata wajib, hentikan onboarding terkait dan replan; jangan menambahkan import atau mengabaikan identity barang secara diam-diam.

Pemilik menetapkan cutover bisnis yang terdokumentasi dan persistent onboarding flag. Flag memblokir posting stok/Sale biasa selama count awal dimasukkan; pemeriksaan flag dan perubahannya mengikuti guard bersama agar posting yang berlomba tidak melewati freeze. Tidak ada lock database yang ditahan selama manusia menghitung barang. Pembuatan master tetap tidak menciptakan stock.

Review paling banyak 100 product opening rows per command. Setiap opening positif memiliki qty sesuai precision, cost evidence atau UNKNOWN, serta count reference. Count nol dicatat dalam onboarding checklist tanpa movement palsu. OPENING hanya sekali per produk yang belum mempunyai ledger, dijaga lock/unique guard. Seluruh rows dalam satu command commit ledger/balance/cost/audit/result bersama; satu row invalid menolak seluruh command; duplicate/retry mengembalikan hasil awal.

Command berbeda menangani produk yang berbeda. Jangan memecah satu product opening atau menyamarkannya sebagai receipt. Pemilik memverifikasi seluruh count, setup barcode/LOW/OUT dan unresolved cost coverage sebelum melepas onboarding flag. Data nyata memerlukan G2 dan cutover pilot yang diotorisasi; fixture sintetik hanya pada non-production terisolasi. Produk baru setelah onboarding memakai RECEIPT. Kesalahan ditangani dengan koreksi berizin, bukan reset/reimport atau edit balance.

## Koreksi, reversal dan return

Hanya satu full reversal untuk original standalone yang eligible. Direct reversal SALE_ISSUE/RETURN_RECEIPT dilarang; gunakan koreksi komersial di [17](17-POS-SALES.md). Tolak reversal yang membuat stok negatif atau dependent valuation belum dapat direplay aman menurut [15](15-FINANCE-PROFITABILITY.md). Jangan mengganti historical cost dengan latest cost. ADJUSTMENT adalah bukti koreksi fisik terpisah dengan reason/count/reference, bukan cara menghapus original.

Return saleable memerlukan barang fisik yang diverifikasi, source SaleLine/issue dan batas qty kumulatif; pulihkan matched historical cost sekali. Refund tanpa return atau damaged/non-saleable return tidak menambah usable stock. Batas money/qty dan transaction correction mengikuti [17](17-POS-SALES.md).

## Perhatian stok dan restock

Perhatian Core adalah dashboard/badge/list di aplikasi berdasarkan stok terkini, tanpa engine pengiriman notifikasi.

| Kondisi active product | State / label |
| --- | --- |
| onHand = 0 | OUT / Habis |
| 0 < onHand <= minimumStock | LOW / Menipis |
| onHand > minimumStock | NORMAL / Normal |

minimumStock nonnegative mengikuti unit precision. Minimum nol tidak mempunyai interval LOW. Produk active baru dengan stok nol adalah OUT; filter pending setup boleh membedakannya tanpa menyebut stoknya Normal. Tidak ada aggregate reservation/location.

Pemilik dan Admin Operasional melihat produk/SKU/current stock/minimum/state dengan filter, pagination server, last-refreshed time dan aksi stok/history/receiving. Kasir hanya melihat permitted stock untuk checkout. Threshold edit tervalidasi/audited dan langsung memengaruhi classification tanpa membuat movement.

Baca committed balance/minimum dalam satu query. Refresh setelah mutation, tab focus dan interval paling lama 30 detik saat halaman aktif. Badge dan list harus konsisten. Refresh gagal menampilkan stale/error; jangan menampilkan daftar restock kosong atau nol seolah-olah query berhasil. Tidak ada sumber perhatian terpisah atau kewajiban acknowledgment. Contoh minimum 5: 6 NORMAL, 5 dan 1 LOW, 0 OUT; receipt menjadi 6 mengembalikan NORMAL. Verifikasi threshold edit dan inactive filter.

## Pembacaan, rekonsiliasi dan insiden

Stok/history dibaca server-side dengan pagination. History Admin Operasional memakai allowlist tanpa private cost; Pemilik dapat membuka evidence yang berizin. Rekonsiliasi read-only terjadwal setiap hari membandingkan ledger dengan balance/cost projections. Mismatch memblokir posting produk terkait dan menimbulkan incident Pemilik.

Incident: hentikan posting terkait → pulihkan durable original result atau verifikasi fakta/restore → rekonsiliasi → pembukaan kembali berizin. Repair hanya melalui controlled replay/rebuild dari fakta immutable dan audit; tidak pernah direct business balance editing atau penimpaan historical facts. Runbook dan larangan destructive production mengikuti [09](09-DEPLOYMENT-OPS.md); acceptance mengikuti [08](08-TESTING-ACCEPTANCE.md).

## Batas Later

CSV/XLSX import, import worker/staging/job framework, warehouse/location/transfers/transit, reservation, advanced opname, serial/pack conversion/batch/expiry/consignment dan purchasing otomatis berada di Later. Web Push, inbox episodes, outbox/worker, provider retry/delivery guarantees dan camera notifications juga Later. Current LOW/OUT tidak membuat purchase order atau notifikasi perangkat.

Jika CSV dipromosikan melalui replan, boundary keselamatan awal adalah UTF-8 create-only product import yang terpisah dari opening: header ketat, barcode sebagai teks, preview/duplicate errors, size/row caps, per-file atomic posting, idempotency dan revalidation. Jangan melakukan silent upsert/skip, partial business commits, mengabaikan unknown column, menerima spreadsheet formula atau menciptakan stock dari master import. XLSX, enterprise queues dan migration tool besar tetap belum direncanakan. Boundary ini bukan capability aktif atau otorisasi implementasi.
