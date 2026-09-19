# 12 — Barcode, label, dan sesi pemindaian

Pemilik topik: identitas machine-readable dan interaksi input. Posting dan retry DB mengikuti [06](06-INVENTORY-SPEC.md); persetujuan visual mengikuti [05](05-DESIGN-SYSTEM.md).

## Registry identitas

Barcode adalah **kunci lookup opaque**, bukan perintah, URL eksekusi, harga, jumlah stok, atau pembawa data privat. Satu token normalisasi global menunjuk tepat satu product atau serialized item. Beberapa alias boleh menunjuk target yang sama; satu alias tidak boleh menunjuk beberapa target.

- Pilihan label internal MVP: Code 128 dengan teks ASCII uppercase dan human-readable code di bawahnya; mudah digunakan HID 1D. QR dapat ditambah untuk perangkat 2D setelah kebutuhan nyata, tidak mengganti registry.
- Format: `LT-P-<16 karakter Crockford Base32>` untuk produk dan `LT-I-<16 karakter Crockford Base32>` untuk item. ID acak server dengan unique constraint; collision menghasilkan ulang sebelum identitas dicetak. Format bukan serial bisnis dan bukan token autentikasi.
- Prefix `LT-` dicadangkan internal. Kode produsen tidak boleh didaftarkan ke namespace tersebut. Tidak menyimpan SKU/nomor seri/warehouse dalam payload internal; label teks boleh menampilkan nama/SKU/satuan dan nomor item yang diperlukan, tidak biaya/pemasok.
- Normalisasi barcode: lepaskan terminator HID; trim spasi ASCII **di luar**, pertahankan kapitalisasi, tanda baca dan nol awal untuk alias produsen. Internal code harus sudah uppercase valid; jangan mengubah semua barcode menjadi angka. Panjang token 1–128 karakter ASCII printable; embedded control/newline ditolak. Barcode non-ASCII memerlukan keputusan/dukungan tersendiri.
- Manufacturer EAN/UPC bila digunakan divalidasi format/check digit sesuai jenis, tetapi tidak otomatis dianggap SKU atau serial. Alias global yang ambigu ditolak; operator menggunakan barcode internal sebagai penyelesaiannya.
- Manufacturer serial unik dalam produk menurut [06](06-INVENTORY-SPEC.md). Bare serial tidak otomatis menjadi barcode global. Mode “Cari nomor seri” meminta produk dan nomor seri; pilih item lalu scan internal/konfirmasi identitas. Ini mencegah nomor seri yang sama lintas model salah terpilih.
- Identitas tidak digunakan ulang meskipun produk/item nonaktif. Cetak ulang mempertahankan kode; penggantian label dapat memakai alias baru, kode lama RETIRED dan tetap tercatat. Scan retired memberi pesan khusus, bukan diperlakukan sebagai barang tidak dikenal.

## Label dan printer

Gunakan SVG barcode yang dibuat library tervalidasi, browser print stylesheet, ukuran fisik mm, quiet zone yang benar dan hitam di putih. Template awal 50 × 30 mm adalah asumsi yang perlu uji printer, bukan pemaksaan vendor. Owner dapat memilih ukuran tanpa mengganti identitas. Cetak halaman uji, ukur scaling 100%, scan hasil nyata pada USB/Bluetooth, termasuk label panjang dan permukaan/kontras realistis.

Jangan mengandalkan screenshot barcode, barcode font tanpa verifikasi, print yang terpotong, atau vendor SDK. Printer label maupun A4 didukung lewat driver OS/browser. Dialog cetak selesai tidak membuktikan tinta tercetak; audit mencatat “Permintaan Cetak Label”, bukan klaim jumlah label fisik berhasil. Audit reprint mencatat actor, target, jumlah diminta, alasan bila penggantian.

## Asumsi HID

USB/Bluetooth HID bertindak sebagai keyboard OS tanpa middleware, WebUSB, atau WebBluetooth. Scanner dikonfigurasi layout karakter yang cocok dan suffix Enter; perangkat kadang mengirim CR/LF sehingga event terminator kedua untuk buffer kosong diabaikan. Input manual dengan Enter menghasilkan alur yang sama.

Input berdasar nilai text field dan event keyboard/commit, tidak menebak perangkat dari kecepatan. Timing hanya untuk peringatan duplicate/diagnostik, bukan dasar sah-tidaknya barcode. IME composition harus selesai sebelum Enter dianggap terminator. Tidak ada listener global yang menangkap password atau mengetik di form lain.

Jika scanner tidak memiliki suffix, operator memakai tombol “Tambahkan” atau mengatur suffix perangkat. Tab tidak diterima sebagai suffix pada baseline karena mengubah fokus; dukungan perangkat yang membutuhkan Tab harus diuji eksplisit. Buffer tidak otomatis disubmit oleh timeout.

## State machine sesi

```text
CONTEXT → SCANNING ↔ REVIEW → SUBMITTING → COMMITTED
              ↓                  ↓
           CANCELLED          UNCERTAIN → cek/kirim ulang → COMMITTED
                                 ↓ penolakan server definitif
                               REVIEW
```

| State | Perilaku wajib |
| --- | --- |
| CONTEXT | Pilih masuk/keluar/transfer, lokasi, tujuan/sumber dan alasan/referensi. Tidak ada mutasi stok |
| SCANNING | Field scan fokus, buffer input, queue lookup terurut, daftar sesi dan jumlah belum disimpan terlihat |
| REVIEW | Hentikan penerimaan scan; selesaikan semua lookup; tampilkan identitas dan qty/serial/lokasi untuk dikoreksi |
| SUBMITTING | Envelope/key dibekukan; tidak menerima scan/edit/cancel; tombol simpan disabled |
| UNCERTAIN | “Hasil penyimpanan belum dapat dipastikan. Periksa status.” Tidak dianggap sukses/gagal, tidak boleh membuat pengganti |
| COMMITTED | Tampilkan nomor/waktu transaksi server; draf lama dibersihkan; “Transaksi Baru” membuka sesi/key baru dengan konteks aman yang dipilih |
| CANCELLED | Konfirmasi bila ada baris; buang draf tanpa movement. Tidak boleh membatalkan request yang mungkin sudah commit |

`sourceSessionId` per sesi logis, revision draf bertambah saat edit. Confirmation key dibuat saat envelope dibekukan; persis envelope ini disimpan untuk pemulihan. Jangan menghasilkan key baru untuk setiap HTTP attempt.

## Buffer, fokus, dan urutan input

1. Gunakan satu field khusus “Pindai atau ketik barcode”. Enter mengambil buffer nonkosong, menambah lookup queue, lalu mengosongkan field tanpa submit form induk. Buffer >128/control ditolak sebelum jaringan.
2. Queue menjaga urutan feedback walau response lookup datang terbalik. Batasi lookup paralel rendah atau proses serial; tidak boleh menggabungkan response produk yang salah. Selagi queue berisi, review/finalisasi disabled dengan informasi jumlah yang masih diperiksa.
3. Scanner yang terputus di tengah kode meninggalkan buffer **belum ditambahkan**. Tampilkan tombol hapus/ulangi; idle warning setelah dua detik boleh membantu tetapi tidak membuang atau mengirim kode otomatis.
4. Kembali fokus sesudah scan valid/error yang sudah ditangani; jangan mencuri fokus ketika operator mengedit qty/alasan, membuka modal, mencari produk, atau layar browser tidak aktif. Tampilkan “Pemindai Dijeda” dan tombol “Lanjutkan Pemindaian”.
5. Esc menghentikan mode scan atau menutup dialog sesuai hierarki, bukan langsung menghapus sesi. Shortcut ditampilkan dan tidak bertabrakan dengan browser; finalisasi memakai tindakan eksplisit, **Enter scanner tidak pernah berarti final confirm**.
6. Paste diperlakukan satu kode, multi-line ditolak; pengguna manual memiliki pencarian produk dan pemilihan item sebagai alternatif penuh.

## Scan berulang dan koreksi

Kuantitas: barcode product menambah satu satuan dasar. Untuk satuan pecahan (misalnya kabel meter), scan menambah 1 m dan operator dapat mengisi qty desimal secara eksplisit. Tidak ada perkalian pack/box tersembunyi. Setiap scan menampilkan baris terakhir dan delta yang ditambahkan, dengan “Urungkan Terakhir”. Edit qty/remove baris mengubah draf saja dan dapat dilakukan via keyboard.

Kode identik dalam ≤300 ms dari **terminator sebelumnya** dianggap kemungkinan pantulan scanner: tahan scan kedua dalam prompt “Barcode sama baru dipindai. Tambahkan lagi?” Default tidak menambah; Enter kosong tidak menyetujui. Aksi eksplisit “Tambahkan Lagi”/shortcut terpisah menerima bila sengaja; scan berikutnya di luar jendela tetap menambah satu. Parameter diuji pada perangkat nyata, karena kecepatan saja tidak bisa membedakan niat. Inilah pengaman UX, bukan pengganti review.

Serial: barcode item hanya boleh muncul sekali dalam satu sesi; scan kedua selalu ditolak dengan fokus pada baris sebelumnya. Barcode model SERIALIZED memulai pemilihan/registrasi unit, tidak otomatis menaikkan qty. Receipt unit baru meminta identitas minimum dan serial produsen jika tersedia; issue/transfer wajib memilih unit yang sudah ada pada lokasi benar. Validasi client membantu, tetapi server memeriksa ulang saat finalisasi.

Unknown: pesan “Barcode belum terdaftar”; pertahankan token untuk diperiksa, sediakan cari produk/input manual. Owner dapat mendaftarkan alias lewat alur berizin; staf tidak boleh mengaitkan kode arbitrer atau membuat item stok otomatis hanya karena scan tidak dikenal. Mendaftarkan unit serial baru di receipt dilakukan setelah produk dipilih, bukan menebak produk dari serial.

## Koneksi, penyimpanan sementara, dan retry

- Resolve/barcode lookup yang gagal tidak masuk sebagai scan berhasil. Kode tertahan ditampilkan dengan tombol “Periksa Lagi”. Jika offline, hentikan lookup baru dan finalisasi; buffer/draf dapat direview, tidak dibuat antrean mutasi offline.
- Gunakan memory + `sessionStorage` per tab/user untuk draft dan frozen envelope minimal, tanpa credential/token; tidak menggunakan localStorage atau service worker untuk data inventory. Terapkan usia maksimum draf 8 jam, simpan timestamp/context revision dan hapus setelah commit/cancel/logout. Browser shared memerlukan logout disiplin.
- Muat ulang meminta konfirmasi “Pulihkan Sesi” setelah login actor yang sama, lalu resolve ulang semua identitas/versi. Frozen envelope dengan hasil belum pasti tidak boleh diedit atau kedaluwarsa diam-diam; cek hasil lebih dahulu. Jika data per tab hilang, cari riwayat transaksi terbaru/sourceSession bersama owner sebelum mengulang pencatatan.
- Saat logout dengan hasil belum pasti, ingatkan nomor referensi sesi untuk penelusuran; hapus data lokal sensitif, receipt server tetap bertahan. Akun lain tidak boleh melihat draf lama.
- Bila saldo/serial berubah di antara scan dan finalisasi, server menolak seluruh dokumen. Tampilkan baris yang perlu ditinjau; tidak otomatis mengurangi qty agar transaksi lolos.
- Teks “Tersimpan” hanya pada COMMITTED yang dibuktikan response/receipt server, termasuk setelah recovery. Navigasi tab atau beep input tidak cukup.

## Mobile dan perluasan

MVP mendukung browser responsif, input manual dan HID Bluetooth pada ponsel/tablet. Kamera adalah enhancement lanjutan setelah pengujian perangkat; jangan membuat dependensi pada native Barcode Detection API saja karena dukungan tidak merata. Gunakan feature detection, izin kamera, HTTPS, dan fallback manual. Rujukan: [MDN Barcode Detection API](https://developer.mozilla.org/en-US/docs/Web/API/Barcode_Detection_API).

Uji wajib mencakup USB nyata, Bluetooth nyata, input lambat/manual, CR/LF, scan cepat, focus hilang, keyboard mobile, duplicate serial, unknown, lookup out-of-order, disconnect, response commit hilang dan pemulihan sesi. Checklist teknis formal ada di [08](08-TESTING-ACCEPTANCE.md).
