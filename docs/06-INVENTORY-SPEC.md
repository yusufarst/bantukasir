# 06 — Kontrak akuntansi inventaris

Ini sumber kanonis perhitungan, posting, konkurensi, koreksi, reservasi, transfer, dan opname. Jangan mengganti aturan ini dengan manipulasi saldo pada UI/ORM. Algoritme status/peringatan dimiliki [13](13-NOTIFICATIONS.md).

## Besaran dan invariant

Untuk produk `p` dan lokasi `l`:

```text
onHand(p,l)   = SUM(delta kaki ledger yang sudah commit untuk p,l)
reserved(p,l) = SUM(remaining reservasi aktif p,l)   [MVP = 0]
available(p,l)= onHand(p,l) - reserved(p,l)
totalFisik(p) = SUM(onHand semua lokasi p)
tersedia(p)   = SUM(available lokasi STORAGE aktif yang layak dikeluarkan)
```

Tidak ada saldo awal di luar ledger. `OPENING` mencatat hasil hitung awal dengan referensi import/count dan pelaksana. Kuantitas masuk/keluar dari UI selalu positif; server menentukan signed delta. Nilai nol/negatif, precision melebihi satuan, NaN/infinity atau overflow ditolak. Representasi eksak `numeric(18,3)`, maksimal 15 digit integer; angka JSON string. UI menerima format Indonesia kemudian mengubahnya secara eksplisit, tidak mengandalkan parser float atau pemisah ribuan ambigu.

| ID | Invariant yang tidak boleh dilanggar |
| --- | --- |
| I01 | Ledger dan audit posting immutable; tidak UPDATE/DELETE/TRUNCATE dari akun runtime |
| I02 | Saldo projection selalu sama dengan agregasi ledger setelah setiap commit |
| I03 | onHand dan available tidak negatif pada setiap lokasi; tidak ada override owner |
| I04 | Satu serial paling banyak satu unit onHand, satu lokasi; tidak dapat dikeluarkan dua kali |
| I05 | Satu command logical hanya satu hasil posting, termasuk retry/respons hilang |
| I06 | Header, kaki, saldo, posisi serial, audit, state/episode dan outbox commit/rollback bersama |
| I07 | Transfer tidak menciptakan/memusnahkan barang: total delta produk = 0 |
| I08 | Actor/izin/waktu/alasan/referensi dapat ditelusuri; semua validasi dilakukan server |

## Bentuk posting

| Tipe internal / label | Kaki ledger | Prasyarat dan cakupan |
| --- | --- | --- |
| OPENING / Saldo Awal | +q di lokasi | Owner, scope produk-lokasi belum memiliki ledger, batch ID unik, hitung awal. Serial baru REGISTERED atau diterima atomik. Penambahan kemudian memakai receipt/adjustment |
| RECEIPT / Barang Masuk | +q di lokasi | Staf berizin; sumber/pengirim dan alasan kategori wajib, nomor dokumen opsional. Untuk serial: REGISTERED/new; pengembalian ISSUED perlu reason RETURN dan rujukan pengeluaran |
| ISSUE / Barang Keluar | −q di lokasi | Tujuan/penerima atau referensi internal dan alasan wajib; available cukup; setiap serial IN_STOCK di lokasi sumber |
| TRANSFER / Transfer Stok | −q sumber, +q tujuan | Dua lokasi aktif berbeda; produk/satuan sama, jumlah seimbang; available sumber cukup; satu commit |
| ADJUSTMENT / Penyesuaian Stok | ±q sesuai selisih | Owner pada MVP; alasan selisih, hasil hitung dan referensi wajib. UI menerima hasil hitung serta expected balance version, server menghitung delta; bila version berubah, hitung ulang/review |
| REVERSAL / Pembalikan | Invers seluruh kaki original | Owner pada MVP, original tunggal, full reversal saja, kondisi fisik dan prasyarat terkini terpenuhi |

Satu dokumen boleh mencakup banyak produk. Maksimum awal 200 baris logis per finalisasi; transfer menghasilkan maksimal 400 kaki. Kelompokkan scan kuantitas untuk product-location sama; serial satu baris per item. Batas dapat dituning setelah pengukuran; jangan memecah satu konfirmasi menjadi beberapa commit diam-diam.

Status dokumen hanya POSTED di DB. “Dibalik” adalah hasil query hubungan reversal. Jangan membuat draf stock movement atau status pending yang ikut agregasi stok.

## Serial dan perubahan posisi

REGISTERED adalah unit yang sudah diberi label tetapi belum diterima, saldo nol. Penerimaan dapat membuat item + barcode + kaki + posisi dalam transaksi yang sama. Jika barcode internal dicetak sebelumnya, register identitas dahulu (diaudit) lalu receipt terpisah; mencetak tidak membuktikan barang masuk.

- Unique item ID/internal barcode global. Serial produsen opsional; jika terisi, unique `(productId, normalizedManufacturerSerial)`. Simpan raw; normalisasi trim luar dan uppercase, pertahankan tanda baca serta nol awal. Konflik ditangani sebagai investigasi identitas, bukan suffix acak. Pengecualian model yang benar-benar case-sensitive memerlukan revisi keputusan sebelum impor.
- RECEIPT +1 mengubah REGISTERED → IN_STOCK. ISSUE −1 mengubah IN_STOCK → ISSUED, lokasi menjadi null. Transfer pasangan −1/+1 mengganti lokasi tanpa mengganti item ID.
- Receipt dengan reason RETURN boleh mengembalikan item ISSUED yang sama; wajib rujukan pengeluaran terakhir dan verifikasi fisik. Nomor seri yang sudah dikenal tidak dibuat sebagai item baru.
- ADJUSTMENT serial harus memilih identitas spesifik. Kehilangan mengeluarkan item dengan alasan; temuan menempatkan item terverifikasi. Tidak menerima angka “+3 serial” tanpa tiga item.
- lastLineId/versi item diperiksa di bawah lock. Metadata nama tidak menjadi dasar identitas. Koreksi serial produsen oleh owner tetap diaudit dan melewati unique constraint.
- Sebelum QC/transit aktif, seluruh onHand ada di STORAGE. Kelak pindah karantina/transit memakai ledger transfer; total fisik tetap, tersedia turun. Status dan lokasi harus dipetakan bersama, bukan dua sumber fakta independen.

## Idempotensi dan hasil yang belum pasti

Client membekukan envelope final sebelum mengirim: key UUID acak, sourceSessionId UUID, actor terikat sesi, canonical payload (tanpa key/waktu browser/urutan scan yang tidak bermakna). Server menghitung SHA-256 atas JSON kanonis UTF-8 dengan `fingerprintVersion=1`: nama field berurutan stabil, qty desimal ekuivalen dinormalisasi, baris quantity untuk pasangan yang sama digabung/diurutkan, serial diurutkan menurut identitas. Payload mencakup semua konteks yang memengaruhi hasil, termasuk type, sourceSessionId, lokasi, reason/reference dan metadata serial baru. Hash client tidak dipercaya. Versi normalisasi receipt lama dipertahankan saat upgrade agar retry lama tidak berubah makna.

`CommandReceipt` dan movement punya hubungan 1:1, dengan unique `(actor, key)` serta `(actor, sourceSessionId)`. Receipt dibuat pada awal transaksi lalu dilengkapi hasil sebelum commit; tidak ada PROCESSING durable pada MVP.

1. Request kembar saat request pertama masih berjalan menunggu unique-key conflict/commit yang pertama.
2. Jika hasil sudah ada dan hash sama, kembalikan **hasil commit asli**, bukan hitung ulang. Jika hash atau sesi berbeda, 409 `IDEMPOTENCY_CONFLICT`.
3. Source session sama dengan key baru tetap tidak boleh menghasilkan movement kedua. Kembalikan hasil lama jika payload identik, atau konflik bila berbeda.
4. Kesalahan validasi domain sebelum posting mengembalikan penolakan definitif dan rollback, termasuk receipt. Perubahan draf setelah penolakan menggunakan revision/key baru; jangan gunakan key sama dengan payload berbeda.
5. Timeout, disconnect, 5xx, atau status receipt tidak ditemukan berarti **hasil belum diketahui**, bukan gagal definitif. Jangan buat key baru atau membuka sesi untuk diedit. Cek status dan kirim ulang exact envelope/key; bila transaksi sebelumnya rollback, retry aman memulai ulang.
6. Jika autentikasi habis, pengguna masuk kembali sebagai actor yang sama untuk memulihkan. Pergantian akun tidak boleh mengirim ulang envelope akun sebelumnya. Jangan membuka detail command actor lain.

Receipt sukses disimpan sepanjang umur ledger. Tidak ada TTL 24 jam yang memungkinkan replay transaksi lama. Retry bukan jalan melewati izin yang sudah dicabut: autentikasi dan izin baca hasil tetap diperiksa; bila kini tak berizin, arahkan ke pemilik tanpa mengungkap detail atau membuat transaksi baru.

## Transaksi dan urutan lock global

Pilih `READ COMMITTED` dengan pessimistic guard per produk untuk semua penulis stok dan stock-health. Row lock product sengaja menserialkan transaksi produk yang sama lintas lokasi: sederhana untuk dua operator dan mencegah dua lokasi menghasilkan episode low-stock ganda. Validasi stale di browser tidak cukup. Dasar perilaku: [row locks PostgreSQL](https://www.postgresql.org/docs/current/explicit-locking.html) dan [isolation](https://www.postgresql.org/docs/current/transaction-iso.html).

Urutan command server (satu koneksi/transaksi; bukan parallel query lintas connection):

1. Validasi struktur, ukuran payload, origin/CSRF, session dan permission awal; tidak percaya actor dari body.
2. BEGIN; klaim unique receipt. Bila conflict, baca hasil/hash dan keluar tanpa menulis lagi.
3. Ambil shared lock pada user/assignment guard lalu validasi ulang user aktif dan permission. Revokasi role menulis guard yang sama. Request yang telah memperoleh lock boleh selesai sebelum revokasi; setelah revokasi commit, command baru ditolak.
4. Shared lock semua lokasi terkait dalam urutan ID; validasi aktif/jenis/gudang. Penonaktifan lokasi memakai exclusive lock yang sama lalu memastikan kosong. Jangan menonaktifkan lokasi bersaldo.
5. Lock product guard (`SELECT … FOR UPDATE`) semua product ID menaik. Guard selalu ada sejak produk dibuat. Master tracking/threshold/monitor/inactivation dan job health memakai guard yang sama.
6. Buat balance row nol jika belum ada dengan unique pair; lock pasangan `(productId, locationId)` urut. Lock serial ID urut, kemudian dokumen reservasi/approval bila fitur sudah ada. Perintah yang menyentuh beberapa objek selalu memakai urutan kelas dan ID ini; jangan mengambil lock produk setelah serial.
7. Baca saldo/posisi/versi terkini setelah lock, validasi semua baris, izin scope, reason, operasi asal, constraints, dan tersedia. Validasi transfer kedua sisi sebelum menulis. Transaksi tidak menunggu input manusia.
8. Insert header/kaki; update balance dan posisi secara sinkron; catat before/after. Evaluasi health sekali per produk memakai saldo **akhir seluruh dokumen**, tulis episode/event/inbox/outbox dan audit bisnis. Lengkapi receipt hasil.
9. Validasi invariant lintas kaki dengan constraint trigger deferred sebelum commit: bentuk kaki tiap tipe, kesesuaian product-serial, keseimbangan transfer, posisi serial dan perubahan saldo untuk pasangan tersentuh. Runtime tidak dapat menonaktifkan trigger. Test juga memverifikasi agregasi historis penuh.
10. COMMIT, baru response sukses. External delivery dilakukan worker sesudahnya.

Perubahan threshold hanya perlu lock user → product karena tidak memindah stok. Inaktivasi lokasi user → location dan pembacaan saldo; writer stok selalu memegang location shared lock sehingga tidak bisa masuk bersamaan. Inaktivasi product user → product dan cek posisi/reservasi. Kelak opname/approval/reservasi mengikuti urutan ini, tidak membuat urutan lock terbalik.

Contoh dua staf mengeluarkan satu item terakhir: A memegang product lock, commit saldo 0; B kemudian membaca 0 dan ditolak. Contoh dua produk pada dua transfer berlawanan: semua command mengurutkan product ID sama sehingga tidak saling mengunci dalam urutan terbalik.

Target awal `lock_timeout` 3 detik dan batas statement 10 detik untuk command interaktif; ukur dalam P03. Deadlock `40P01`/serialization `40001` yang diketahui rollback boleh retry seluruh transaksi maksimal 3 kali dengan jitter pendek dan key sama. Unique stock/barcode conflict bukan retry buta. Koneksi putus saat commit masuk jalur hasil belum pasti. Jangan menampilkan sukses optimistis.

## Pembalikan dan koreksi

MVP hanya pembalikan penuh satu dokumen, maksimal satu reversal per original (`originalMovementId` unik). Reversal tidak dapat membalik reversal; perbaikan lanjutan memakai adjustment beralasan. Koreksi berantai terlihat sebagai dokumen terpisah dengan referensi silang.

Owner melihat dampak inverse dan stok terkini sebelum konfirmasi. Terapkan inverse pada lokasi asli; tidak boleh mengubah target lokasi/produk/qty supaya lolos. Receipt yang barangnya sudah dipakai dapat gagal dibalik karena available tak cukup. Transfer yang tujuan telah mengeluarkan barang juga dapat gagal; gunakan investigasi dan koreksi yang sesuai keadaan nyata. Tidak ada bypass negatif.

Untuk serial, semua item harus berada pada hasil terakhir original dan movement yang dirujuk `lastLineId` masih original; jika sudah bergerak lagi, pembalikan ditolak walau kebetulan lokasinya sama. Setiap posting serial menyimpan snapshot status/lokasi sebelum dan sesudah, termasuk REGISTERED yang belum memiliki lastLineId. Reversal memulihkan status/lokasi sebelum original (misalnya receipt pertama kembali REGISTERED, receipt RETURN kembali ISSUED), tetapi lastLineId menunjuk kaki reversal terbaru, bukan dikembalikan ke pointer lama. Reversal ISSUE hanya jika pengeluaran sebenarnya keliru/barang kembali terverifikasi, bukan karena pemilik ingin mengubah laporan. Pengembalian bisnis normal memakai RECEIPT/RETURN. Alasan dan referensi wajib; bukti fisik dapat berupa nomor dokumen, lampiran baru fase berikutnya.

## Reservasi — kontrak lanjutan

Reservasi menahan `available`, tidak membuat kaki ledger fisik. Create/ubah/release/expire menghasilkan event reservasi dan update reserved + health + audit dalam transaksi ber-lock yang sama. `remaining = allocated − fulfilled − released`; semua operand nonnegatif. Reservasi serial mengikat unit tertentu dengan unique active allocation.

Pengeluaran untuk reservasi menggunakan reservation ID: kurangi onHand dan reserved bersamaan sebesar q. Dengan demikian tersedia tidak turun dua kali. Pengeluaran biasa hanya dapat memakai unreserved available dan tidak dapat memilih serial terreservasi. Fulfillment partial diperbolehkan sampai remaining nol. Transfer MVP/lanjutan biasa tidak dapat membawa reserved stock; harus release dan reserve ulang secara eksplisit atau command relokasi reservasi yang didesain khusus kemudian.

Expiry worker dan fulfillment berlomba di lock yang sama; worker melepas yang masih aktif/due saja. Pengeluaran dengan reservasi kedaluwarsa ditolak dan direview ulang, tidak otomatis mengambil stok bebas. Perhitungan tersedia memakai proyeksi reserved sampai expiry benar-benar commit; timestamp saja tidak boleh membuat dua definisi available. Health berubah saat reserve/release, sehingga label pemilik menyebut “stok tersedia”.

## Transfer transit dan opname — kontrak lanjutan

Transfer bertahap: dispatch memindah STORAGE sumber → lokasi TRANSIT; receive memindah TRANSIT → STORAGE tujuan. Masing-masing dokumen atomik dan idempoten. Outstanding quantity = dispatched − received − returned; partial receipt tidak boleh melebihi outstanding. Kehilangan tidak diselesaikan dengan mengecilkan dispatch lama: adjustment transit perlu persetujuan. Transit dihitung total fisik, dikecualikan dari tersedia dan reservasi.

Opname awal menggunakan **freeze scope** lokasi selama hitung: aktifkan freeze di bawah lock lokasi, semua writer yang menyentuhnya menolak dengan pesan jelas. Pembekuan tidak menahan transaksi DB sepanjang sesi manusia. Ambil snapshot baseline dan serial set setelah freeze aktif; count, submit, owner review, lalu adjustment dan pelepasan freeze atomik. Watchdog mengingatkan freeze lama, tidak melepas otomatis. Cancel diaudit dan hanya melepas freeze, tidak memposting selisih. Perubahan count sesudah submit membuat revisi proposal dan persetujuan lama tidak berlaku. Ini menghindari rekonsiliasi count bergerak yang rumit; rolling count dapat didesain kelak.

Approval terikat hash proposal, baseline/version, actor, expiry dan satu kali konsumsi. Posting memeriksa ulang izin, saldo dan version; approval tidak menjamin stok masih tersedia. Pada MVP semua adjustment/reversal dijalankan owner langsung, tanpa mesin approval; staf rutin tidak menunggu owner.

## Rekonsiliasi dan pemulihan

Job read-only membandingkan ledger agregat ↔ balance, posisi serial, reserved ↔ event/reservation dan health. Ketidaksesuaian membuat insiden, membekukan posting scope terdampak, serta memberi peringatan; jangan menulis otomatis menutupi korupsi. Ledger benar/proyeksi salah boleh direbuild dalam maintenance dengan backup, audit operasi, validasi sebelum/sesudah dan tanpa mengubah ledger. Ledger salah secara bisnis membutuhkan corrective movement. Catat hasil dan laporkan pada pemilik; prosedur restore ada di [09](09-DEPLOYMENT-OPS.md).
