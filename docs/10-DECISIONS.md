# 10 — Keputusan arsitektur dan asumsi

Baseline desain tanggal **19 September 2026**, disusun dari brief pemilik dan inspeksi repositori kosong. `LOCKED-BASELINE` berarti agen berikutnya harus mengikuti keputusan sampai diganti secara eksplisit; **bukan** klaim pemilik sudah menyetujui review dokumentasi/visual. Review dokumentasi owner masih menunggu. Ketika keputusan diganti, tambahkan ID baru yang menyebut keputusan lama, alasan, dampak dokumen/tugas/data dan persetujuan bila diperlukan; jangan menulis ulang sejarah.

## Register keputusan

| ID / status | Keputusan dan alasan | Alternatif / tradeoff | Spesifikasi kanonis |
| --- | --- | --- | --- |
| D01 / LOCKED-BASELINE | Modular monolith, satu PostgreSQL, stack pilihan brief dipertahankan; worker dari codebase sama | Microservices/Redis/queue eksternal menambah biaya operasi tanpa kebutuhan dua staf | [02](02-ARCHITECTURE.md) |
| D02 / LOCKED-BASELINE | Ledger append-only + balance projection sinkron dalam satu commit | SUM ledger setiap baca sederhana tetapi mahal dan tidak menyediakan row stabil untuk validasi; cache async berisiko stale saat issue | [06](06-INVENTORY-SPEC.md) |
| D03 / LOCKED-BASELINE | READ COMMITTED + guard row per produk dan lock berurutan, receipt durable | SERIALIZABLE dapat menjadi alternatif tetapi tetap butuh retry; lock hanya per lokasi mempersulit agregat alert. Throughput satu SKU dibatasi secara sengaja | [06](06-INVENTORY-SPEC.md) |
| D04 / LOCKED-BASELINE | Satu base unit/SKU, numeric(18,3), precision 0–3, serial integer, tanpa konversi pack MVP | Integer saja tidak cocok kabel per meter; float tidak aman; multi-unit ditunda agar scan tidak memiliki faktor implisit | [06](06-INVENTORY-SPEC.md) |
| D05 / LOCKED-BASELINE | Serial dasar masuk Core bersama quantity; QC/warranty/service tetap lanjutan | Menunda semua serial berisiko merombak identitas, scanner dan ledger setelah dipakai. Core hanya identity/posisi/riwayat, bukan siklus servis | [03](03-DOMAIN-MODEL.md), [12](12-BARCODE-SCANNER.md) |
| D06 / LOCKED-BASELINE | Scan-session browser → review → commit; HID keyboard vendor-neutral; Code 128 internal | Posting tiap scan rawan salah/dobel; vendor SDK menambah lock-in; kamera/PWA offline write bukan prasyarat MVP | [12](12-BARCODE-SCANNER.md) |
| D07 / LOCKED-BASELINE | Low-stock memakai stok tersedia agregat STORAGE per produk, minimum inklusif | Total fisik dapat menyesatkan saat reserved/transit; per-location alert default menciptakan spam. Per-location policy dapat ditambah terpisah | [13](13-NOTIFICATIONS.md) |
| D08 / LOCKED-BASELINE | Satu episode perhatian sampai Normal; LOW/OUT masing-masing sekali, escalation OUT, recovery parsial tidak reset | Check threshold setiap poll mengulang alert; threshold hysteresis ditunda karena transisi episode cukup untuk kebutuhan awal | [13](13-NOTIFICATIONS.md) |
| D09 / LOCKED-BASELINE | Inbox durable + Web Push opt-in, transactional outbox, external delivery at least once | Inbox saja tidak memberi pemberitahuan di luar tab; WhatsApp/SaaS berbayar tidak disetujui. Push tetap bergantung izin/perangkat/provider | [13](13-NOTIFICATIONS.md) |
| D10 / LOCKED-BASELINE | Better Auth self-hosted, sesi DB, fixed roles + permissions, owner TOTP, tanpa signup publik | Auth buatan sendiri memperbesar risiko; managed auth berbayar tidak perlu. Library perlu verifikasi compatibility/security saat P01 | [04](04-AUTH-RBAC-SECURITY.md) |
| D11 / LOCKED-BASELINE | Audit/keamanan/health hook sejak ledger; backup/restore dan ops dasar sebelum pilot | Urutan awal yang menaruh audit/ops di akhir terlalu lambat untuk data gudang. Public website tetap setelah Core terpercaya | [11](11-BUILD-PLAN.md) |
| D12 / LOCKED-BASELINE | Transfer langsung dan koreksi owner minimal di MVP; reversal penuh sekali/original; approval engine kemudian | Tanpa koreksi sistem tak bisa menangani salah catat; approval semua transaksi membuat owner bottleneck; partial reversal lebih rumit | [06](06-INVENTORY-SPEC.md) |
| D13 / LOCKED-BASELINE | Public allowlist projection dari master tunggal, tanpa exact availability default | Database publik terpisah membuat sinkronisasi ganda; serializing internal model lalu hiding UI membocorkan data | [02](02-ARCHITECTURE.md) |
| D14 / LOCKED-BASELINE | HTML/CSS standalone sebagai kontrak; dua approval gate, prototype registry dan revisi | Implementasi React dulu melanggar brief; tes/screenshot bukan persetujuan visual owner | [05](05-DESIGN-SYSTEM.md) |
| D15 / LOCKED-BASELINE | Waktu posting server UTC, hari bisnis WIB, tanpa backdating ledger | Editable timestamp dapat menyembunyikan urutan transaksi. Tanggal dokumen disimpan terpisah | [02](02-ARCHITECTURE.md), [06](06-INVENTORY-SPEC.md) |
| D16 / LOCKED-BASELINE | Backup 6-jam terenkripsi off-host + latihan restore, tanpa hapus otomatis ledger/receipt | Snapshot lokal saja tidak melindungi kehilangan VPS; PITR dapat diperlukan jika RPO 6 jam tak diterima | [09](09-DEPLOYMENT-OPS.md) |
| D17 / LOCKED-BASELINE | Progres berdasarkan jumlah tugas selesai dari 55; baseline dokumen tidak menambah progres aplikasi | Estimasi subjektif/FE [V] dianggap selesai memberi kesan palsu. Task count tidak sama dengan persentase waktu/biaya | [11](11-BUILD-PLAN.md) |
| D18 / LOCKED-BASELINE | Dua dokumen tambahan: barcode/scanner (12), notifikasi (13); 06 menautkan keduanya | Menjejalkan semua detail ke 06 memperberat pemulihan konteks. Tidak ada spesifikasi scanner/alert kedua | [README](../README.md) |
| D19 / LOCKED-BASELINE | Opname lanjutan memakai freeze scope, reservasi terpisah dari ledger fisik, transit dua posting | Rolling count dan mutasi qty saat reservation memperbesar peluang salah hitung. Freeze perlu jadwal operasional yang jelas | [06](06-INVENTORY-SPEC.md) |

## Perubahan terhadap urutan/ide awal

1. Identitas serial dasar dimajukan ke Core; fitur lifecycle lanjut tetap ditunda (D05).
2. Audit, keamanan, atomic health/event dan backup dipindah sebelum operasi nyata; tidak menunggu akhir seluruh fitur (D11).
3. Transfer langsung, opening dan koreksi minimal dimasukkan Core, sedangkan reservation/transit/opname/approval formal tetap fase lanjutan (D12/D19).
4. Definisi ambang diperjelas sebagai available aggregate, dengan aturan Habis dan severity escalation dalam episode yang sama (D07/D08).
5. Inbox/push dan durable outbox ditetapkan agar “proaktif” mempunyai jalur teknis nyata tanpa vendor berbayar (D09).
6. Serial/scanner dan notifikasi memiliki kepemilikan dokumen sendiri (D18). Teknologi utama brief tidak diganti; Better Auth ditambahkan sebagai pilihan autentikasi.

## Asumsi eksplisit dan waktu validasi

| ID | Asumsi keputusan awal | Validasi / tindakan jika salah |
| --- | --- | --- |
| A01 | Satu badan usaha; satu owner dan dua staf. Dua staf awal dapat sama-sama inventory admin | Review dokumentasi; ubah assignment tanpa menaikkan staf ke owner |
| A02 | Satu lokasi aktif awal boleh diperluas; seluruh operator inventory boleh mengakses seluruh lokasi usaha | Sebelum pilot; jika scope per gudang wajib, rancang end-to-end authorization dahulu |
| A03 | Tidak ada batch/lot/kedaluwarsa atau consignment wajib pada produk awal | Review dokumentasi dan sebelum import; jika ada, revisi identity/ledger sebelum input stok tersebut |
| A04 | Satuan dasar saja, kabel boleh pecahan hingga tiga desimal; tidak ada pack conversion MVP | Sebelum membuat master; permintaan presisi/konversi lebih luas memerlukan perubahan kontrak |
| A05 | Stok negatif/backorder fisik dilarang; koreksi hanya menggambarkan kenyataan terverifikasi | Review dokumentasi; jangan implementasi bypass untuk mengatasi mismatch |
| A06 | Nomor seri unik per produk setelah trim/uppercase, tidak diasumsikan unik lintas brand/model | Sebelum import serial; jika case-sensitive nyata, revisi normalisasi dan constraint dahulu |
| A07 | Perangkat utama mendukung HID Enter; ukuran label 50×30 mm hanya template uji | Hardware/printer smoke P04/P06; sesuaikan layout/suffix tanpa vendor middleware |
| A08 | Owner memiliki perangkat mendukung Web Push atau bersedia menilai batas kanal aktual | Persiapan P05, uji perangkat di HTTPS staging P06.5; jika gagal, gate proaktif belum selesai, jangan mengarang delivery |
| A09 | RPO 6 jam/RTO 4 jam dapat diterima dan operator teknis punya akses storage off-host | Sebelum pilot; ubah strategi backup/PITR bila owner memerlukan batas lebih ketat |
| A10 | Owner dapat menangani koreksi sensitif yang jarang; staf harian tidak menunggu approval | Pilot; bila volume koreksi tinggi, evaluasi sebab UX/proses sebelum memperluas permission |
| A11 | Skala 5.000 SKU/20.000 serial/100.000 kaki ledger hanya dataset benchmark | Ukur data nyata tanpa menganggap angka ini inventaris perusahaan |
| A12 | Belum ada domain/VPS/destination backup/printer/lisensi font/nomor WhatsApp yang ditetapkan | Pilih saat fase terkait. Tidak memblokir dokumen; dapat memblokir penerimaan deploy atau perangkat |

Tidak ada pertanyaan yang harus dijawab untuk menulis baseline ini. Asumsi yang menyentuh data fisik atau pemulihan harus diverifikasi **sebelum** penggunaan operasional, bukan dibiarkan sebagai default tersembunyi.

## Rujukan teknis dan versi

Rujukan primer diperiksa 19 September 2026; tautan dekat klaim berada di dokumen kanonis: PostgreSQL locking/isolation/constraints/backup, Next.js authentication, Better Auth session/email/2FA, MDN Barcode Detection dan Push API. Keputusan desain LATANSA adalah pertimbangan engineering, bukan klaim dokumentasi vendor menetapkan arsitektur ini. Versi dependency yang benar-benar dipakai belum dipilih; P01 wajib memeriksa versi stabil, adapter kompatibel dan advisory lalu mengunci lockfile. Upgrade major memerlukan evaluasi risiko dan keputusan pengganti bila semantik berubah.
