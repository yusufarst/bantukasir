# 13 — Status stok dan notifikasi pemilik

Ini sumber kanonis klasifikasi, episode perhatian, deduplikasi, recipient dan delivery. Inventory memanggil evaluator dalam transaksi [06](06-INVENTORY-SPEC.md); dashboard memakai state yang sama, tidak menyalin rumus sendiri.

## Kebijakan stok

Scope MVP **per produk, agregat semua lokasi STORAGE aktif yang layak dikeluarkan**. Gunakan `tersedia(p)` dari 06, bukan total fisik. Ini memungkinkan reservasi/karantina/transit kelak mengurangi kesiapan stok tanpa mengubah makna peringatan. Stok per lokasi tetap terlihat; minimum per lokasi adalah perluasan dengan policy key terpisah, bukan tambahan alert diam-diam.

`monitoringEnabled`, `minimumQty ≥ 0`, dan optional reorder target disimpan sekali pada Product; precision mengikuti produk. Reorder target bila terisi harus > minimum; hanya informasi perencanaan, bukan trigger kedua atau pesanan otomatis. StockHealth hanya menyimpan hasil evaluasi/episode/versi, tidak menyalin konfigurasi.

```text
tersedia = 0                     → OUT    → Habis
0 < tersedia ≤ minimumQty        → LOW    → Menipis
tersedia > minimumQty            → NORMAL → Normal
```

Minimum 0 berarti tidak ada rentang Menipis, tetapi Habis tetap terdeteksi. Nilai negatif tidak masuk klasifikasi karena dilarang inventory. Produk monitoring off berlabel “Tidak Dipantau” dengan currentState null; jangan menampilkan Normal palsu. Produk nonaktif tidak dipantau. Aktivasi dari null mengevaluasi saldo saat ini, bukan memakai state lama sebelum monitor dimatikan.

Saat setup produk, monitoring awal off agar produk kosong yang belum selesai disiapkan tidak membanjiri inbox. Setelah saldo awal/review kebijakan, owner memilih “Mulai Pantau Stok”; evaluasi dilakukan saat itu, termasuk menghasilkan Habis bila masih nol. **Semua SKU yang memang disimpan harus monitoring on sebelum pilot**; pengecualian produk pesanan khusus harus dicatat alasan oleh owner. Setup status terlihat di dasbor pengaturan, bukan disembunyikan.

## Episode dan transisi

Satu episode adalah satu periode perhatian yang belum kembali Normal. Ada maksimum satu episode OPEN per product. Simpan `currentState`, stateVersion, episode sequence, openedAt, resolvedAt/reason, severity tertinggi dan notification event unik. Lock product dari 06 menyerialkan posting, setting threshold, dan pemeriksaan terjadwal.

| Sebelum | Sesudah | Perubahan episode | Notifikasi baru |
| --- | --- | --- | --- |
| NORMAL | LOW | Buka episode | LOW sekali |
| NORMAL | OUT | Buka episode | OUT sekali; jangan juga mengirim LOW |
| LOW | LOW | Tetap | Tidak |
| LOW | OUT | Tetap; naikkan severity maksimum | OUT sekali jika belum pernah OUT pada episode ini |
| OUT | OUT | Tetap | Tidak |
| OUT | LOW | Tetap; mulai pulih tetapi belum Normal | Tidak |
| LOW atau OUT | NORMAL | Tutup episode sebagai pulih | Tidak ada push pemulihan MVP; inbox dapat menunjukkan sudah pulih |
| NORMAL | NORMAL | Tidak ada | Tidak |

Unique `(episodeId, severity)` menjamin paling banyak satu LOW dan satu OUT. OUT→LOW→OUT dalam episode sama tidak membuat OUT kedua. Pergerakan ke Normal lalu turun lagi membuka episode sequence baru. Tidak ada reminder berkala untuk episode yang sama pada MVP. Baca/unread tidak menutup episode atau mereset deduplikasi.

Contoh min 5: `6→5` LOW#1; `5→4→3` tanpa event; `3→0` OUT#1; `0→2` tetap episode; `2→0` tanpa OUT baru; `0→20` tutup; `20→5` LOW#2. Receipt pertama yang menghasilkan tersedia > minimum saat monitoring baru diaktifkan mulai NORMAL tanpa event. Aktivasi langsung pada low/out diperlakukan masuk ke kondisi tersebut dan membuat episode awal.

Perubahan minimum/monitor menjalankan evaluator ber-lock, actor dan reason konfigurasi diaudit. Threshold baru bisa membuka/menutup episode tanpa movement; payload menyebut perubahan kebijakan. Mematikan monitor/menonaktifkan produk menutup episode dengan alasan administratif, bukan “stok pulih”. Mengaktifkan ulang mengevaluasi sebagai episode baru jika perlu. Kebijakan ini hanya owner supaya staf tidak bisa menghapus perhatian dengan mematikan monitor.

## Batas transaksi dan deduplikasi

Posting inventory selesai menghitung semua kaki dahulu. Untuk setiap product terpengaruh, bandingkan persisted state dengan hasil akhir dalam transaction yang sama; buat state/episode/event, recipient inbox, dan outbox. Transfer STORAGE→STORAGE pada product yang sama tidak menghasilkan low transient karena evaluasi tidak dilakukan per kaki. Jika audit atau event/outbox wajib gagal ditulis, seluruh posting rollback.

Constraint: satu health row/product, satu open episode/product (partial unique), event unik episode-severity, inbox unik event-user, delivery unik event-channel-recipient-device. Jika tidak ada perangkat terdaftar, inbox tetap dibuat; outbox push tidak perlu dibuat. Penerima stok MVP adalah seluruh owner aktif yang punya permission owner dashboard saat event terjadi; staf hanya mendapat pesan feedback transaksinya, bukan salinan seluruh alert owner.

Job rekonsiliasi read-only membandingkan status dengan saldo. Bila stock-health projection melenceng tanpa posting yang sah, laporkan insiden sebagaimana 06; jangan menutupi bug dengan menulis notifikasi baru berulang. Normalisasi state setelah maintenance/rebuild dilakukan terkontrol dengan product lock dan dedupe key yang sama.

## Kanal dan janji pengiriman

1. **Inbox dalam aplikasi adalah kanal durable wajib.** Poll saat tab aktif tiap 15 detik, refresh saat fokus kembali dan setelah command; tampilkan waktu terakhir berhasil. Tidak perlu WebSocket/SSE MVP.
2. **Web Push adalah kanal proaktif di luar tab** dari worker memakai VAPID dan subscription perangkat yang disetujui. Tidak memerlukan SaaS notifikasi berbayar, tetapi browser/vendor push service dan koneksi tetap menjadi dependensi eksternal; pengiriman tepat waktu tidak dijamin.

Push memakai service worker dengan payload minimal: “Stok memerlukan perhatian” dan tautan same-origin ke inbox. Detail SKU/jumlah/lokasi tampil setelah login, bukan layar terkunci. Subscription endpoint/key adalah data sensitif. Registrasi memerlukan izin browser atas tindakan user, validasi owner/user aktif, dan association perangkat; logout, pencabutan izin atau akun nonaktif menghentikan subscription.

Owner onboarding mencakup izin, kirim uji, buka dari notifikasi, dan periksa saat tab tertutup pada perangkat sebenarnya. Browser yang tidak mendukung/izin ditolak mendapat penjelasan “Pemberitahuan perangkat belum aktif; notifikasi tetap tersedia di aplikasi.” Tidak boleh mengklaim owner menerima push ketika hanya provider menerima request. Dukungan perangkat diuji, bukan diasumsikan; lihat [MDN Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API).

Untuk menyatakan tujuan proaktif pilot terpenuhi, Web Push harus terbukti pada perangkat owner, atau owner menyetujui secara eksplisit batas kanal yang tersedia beserta alur pengecekan inbox. Bila tidak, gate notifikasi tetap terbuka. Ini review kemampuan aktual, bukan izin membeli layanan. Email/Telegram/WhatsApp API bukan dependensi MVP; penambahan layanan berbayar memerlukan persetujuan khusus.

## Worker, retry, dan spam

- Worker polling DB, claim due jobs dengan `FOR UPDATE SKIP LOCKED`, simpan lease 60 detik dan claim token; transaksi claim singkat, kirim di luar transaksi. ACK hanya pemegang token yang masih berlaku. Lease expired dapat diklaim ulang.
- Delivery bersifat **at least once**. Crash sesudah provider menerima tetapi sebelum ACK dapat mengirim ulang. Gunakan push tag `eventId` untuk mengganti duplikat pada perangkat sejauh didukung; jangan menjanjikan exactly-once lintas jaringan.
- Retry sementara pada 1, 5, 15, 60, 240 menit, lalu DEAD bila upaya berikutnya tetap gagal. Simpan attempt count, nextAttemptAt dan galat tersanitasi; 429 mengikuti `Retry-After` yang dibatasi, tidak tight loop.
- HTTP 404/410 endpoint mencabut subscription; galat autentikasi/VAPID menandai konfigurasi gagal dan alert operasi. Inbox tetap tersedia. Jangan mengulang tanpa batas.
- Sebelum delivery, periksa recipient masih aktif/berizin dan episode masih OPEN. Episode sudah pulih → SUPPRESSED; event LOW yang sudah meningkat OUT → SUPPRESSED agar alert lama tidak menyusul. Inbox historis tetap ada. Jika episode berubah setelah cek dan sebelum provider, push generik masih aman; aplikasi selalu menampilkan state terkini.
- Batas awal 5 push per menit per recipient; antrekan menunda sisanya, tidak membuang event. Inbox dapat menampilkan beberapa produk dalam satu daftar. Tidak ada heartbeat push yang berulang saat tidak ada perubahan.
- Ukur umur outbox; >5 menit tanpa progres membuat health warning yang terlihat owner/operator. Pengiriman DEAD bisa diulang manual oleh operator berizin memakai delivery yang sama; tidak membuat event bisnis baru.

## Isi dan tindakan

Inbox LOW: “Stok Menipis — Produk Demo tersisa 5 unit. Minimum 5 unit.” OUT: “Stok Habis — Produk Demo tidak memiliki stok tersedia.” Lampirkan event time, snapshot qty, current state, dan aksi “Lihat Stok”/“Lihat Riwayat”. Tautan lokasi/detail hanya setelah izin. Jangan membuat purchase order atau mengubah minimum secara otomatis.

Kondisi bisnis tetap terlihat di dasbor walaupun notifikasi dibaca/dikirim gagal. Detail teknis queue hanya dalam halaman sistem berizin. Kelak approval/QC/RFQ/backup memakai event type dan dedupe key sendiri; jangan mencampurkan episode stok dengan reminder sistem.
