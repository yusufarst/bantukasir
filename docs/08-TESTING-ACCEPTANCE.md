# 08 — Pengujian dan penerimaan

Ini pemilik definisi bukti dan gerbang selesai. Coverage percentage bukan pengganti pengujian risiko. Saat baseline ini dibuat, tidak ada aplikasi, package script, DB, atau tes aplikasi yang dijalankan.

## Tingkatan dan lingkungan

| Tingkat | Fokus | Sarana target |
| --- | --- | --- |
| Unit | Desimal, normalisasi, reducer scan, transisi health, permission mapping, pesan Indonesia | Vitest, fake clock untuk waktu/retry |
| Integration | Constraint, transaction rollback, auth services, projection publik, outbox, audit | PostgreSQL nyata disposable; bukan SQLite/mock sebagai bukti integritas |
| Concurrency | Interleaving dua koneksi/proses dan unique/lock race | Barrier terkontrol, bukan sleep acak; koneksi DB independen |
| End-to-end | Login → scan → review → commit → stok → inbox/dasbor | Playwright, layanan app/worker/DB uji, assertion hasil persisten |
| Manual perangkat | USB/Bluetooth, printer, ponsel, push, aksesibilitas | Hardware yang benar-benar akan digunakan |
| Visual owner | Kesesuaian HTML dan implementasi | Dua gerbang di [05](05-DESIGN-SYSTEM.md), bukti persetujuan revisi |
| Operasional | Backup/restore, restart, config fail, insiden | Lingkungan terisolasi setara target deploy |

Gunakan database uji terpisah; runner wajib menolak koneksi database produksi/identitas tak dikenal. Tes destruktif hanya database ephemeral yang dibuat runner, tidak menerima URL produksi sebagai parameter bebas. Mock provider boleh untuk retry deterministik, tetapi satu uji perangkat nyata diperlukan sebelum klaim delivery berhasil.

## Matriks integritas inti

| ID / rujukan | Skenario | Hasil wajib |
| --- | --- | --- |
| INV01 / I01–I02 | Opening, receipt, issue, transfer, adjustment, reversal | Agregat ledger = saldo seluruh pasangan; tidak ada edit/hapus runtime yang lolos |
| INV02 / I03 | Stok 1, dua koneksi issue masing-masing 1 | Tepat satu sukses; satu konflik; akhir 0, satu movement issue |
| INV03 / I04 | Dua issue serial sama; dua receipt serial/barcode sama | Maksimal satu posting sah; tidak ada dua item/posisi atau saldo +2 |
| INV04 / I05 | 10 request simultan key/payload sama | Satu receipt/movement, semua sukses replay menunjuk ID yang sama |
| INV05 / I05 | Key sama payload berbeda; key baru sourceSession sama | Konflik atau replay identik sesuai 06; tidak ada movement kedua |
| INV06 / I06 | Inject failure setelah insert kaki, update saldo, audit, health, outbox | Tidak ada perubahan parsial/receipt sukses pada tiap titik gagal |
| INV07 / I07 | Transfer beberapa produk, tujuan gagal; transfer berlawanan | Semua rollback saat gagal; total terjaga dan lock order konsisten |
| INV08 / I03 | Receipt dibalik setelah stok digunakan; adjustment minus terlalu besar | Tolak, tanpa saldo negatif atau inverse parsial |
| INV09 / I04 | Serial dipindah keluar lalu kembali sebelum mencoba reversal lama | Tolak karena last movement bukan original, walau lokasi kebetulan cocok |
| INV10 / I08 | Actor palsu, lokasi nonaktif, qty float/overflow/precision salah | Ditolak backend; tidak memercayai body atau pembulatan client |
| INV11 | Dua posting pada balance yang belum ada | Unique pair tunggal, saldo benar; tidak kehilangan penambahan |
| INV12 | Response commit sengaja diputus, lalu status dan retry | Tidak pernah sukses palsu; satu commit setelah recovery, key tetap |
| INV13 | Role dicabut/inaktivasi produk/lokasi bersamaan posting | Hasil berurutan menurut guard, tidak ada mutasi setelah pencabutan yang sudah commit |
| INV14 | Rebuild projection dan replay rangkaian movement generated | Hasil sama; property test selalu menjaga I01–I08 dan precision |
| INV15 (L) | Reserve vs issue/expire/fulfill bersamaan | Tidak oversell, available tidak dikurangi dua kali, event/reserved cocok |
| INV16 (L) | Opname freeze vs transfer/issue, approval stale/dua konsumsi | Scope benar-benar terkunci operasional; proposal stale/double consume ditolak |

## Notifikasi dan pemindai

| ID | Skenario | Hasil wajib |
| --- | --- | --- |
| NOT01 | Min 5: 6→5→4→3→20→5 | Dua episode LOW, tidak ada event di tiap decrement |
| NOT02 | 6→0→2→0→20 | OUT sekali, recovery parsial tidak mereset episode |
| NOT03 | 6→5→0→2→0 | LOW dan OUT masing-masing satu, satu episode |
| NOT04 | Minimum 0, monitoring on/off, threshold diubah saat issue | State/event benar dan serialisasi kebijakan; audit actor perubahan |
| NOT05 | Dua lokasi produk berubah bersamaan, transfer langsung | Satu state final konsisten; tidak ada episode dari kaki transfer sementara |
| NOT06 | Crash worker setelah send sebelum ACK; lease expired | Delivery dapat dicoba ulang, event/inbox tidak duplikat; tag stabil |
| NOT07 | Provider 410/429/5xx, episode pulih sebelum retry | Subscription dibatalkan/backoff/suppress sesuai kontrak; stok tetap sah |
| NOT08 | Inbox dibaca, owner lain/perangkat lain, akun nonaktif | Read per user, episode tetap; tidak bocor atau dikirim ke user nonaktif |
| SCN01 | USB/Bluetooth Enter/CRLF, buffer kosong/paste/manual/IME | Satu lookup per kode; tidak submit transaksi oleh Enter scanner |
| SCN02 | Identik <300 ms vs scan kuantitas disengaja | Prompt duplicate tidak auto menambah; di luar jendela qty bertambah; undo benar |
| SCN03 | Serial dua kali, model serial, unknown/retired alias | Duplicate dicegah, unit dipilih, error Indonesia, tidak mutasi langsung |
| SCN04 | Lookup out-of-order, 128+ karakter, kontrol, focus modal | Tidak salah produk/menangkap password; review menunggu queue kosong |
| SCN05 | Putus jaringan sebelum lookup, sebelum kirim, saat commit | State dan frozen envelope benar; tidak ada mutasi offline terselubung |
| SCN06 | Reload, logout/login, ganti user, draf kedaluwarsa | Isolasi actor, hasil belum pasti dipulihkan, tidak menampilkan draf user lain |
| SCN07 | Label cetak ukuran nyata, kode panjang dan reprint | Scan kembali ke identitas sama, tidak ada pembuatan item baru saat print |

## Keamanan, data publik, dan audit

- SEC01: positive/negative matrix setiap permission 04 pada endpoint/action/query, termasuk ID milik actor lain dan role gabungan. Staf tidak dapat assign role, posting adjustment, membaca private field melalui export atau parameter tambahan.
- SEC02: signup publik ditutup, rate limit, session expired/revoked, 2FA/recovery, CSRF/origin, cookie flags; uji langsung HTTP, tidak hanya klik menu.
- SEC03: public query/JSON/HTML/metadata/cache tidak berisi biaya, supplier, lokasi, exact qty, serial, catatan, PII, audit. Uji objek punya field rahasia sentinel lalu pastikan semua proyeksi publik tidak memuatnya.
- SEC04: secret scan staged/release, config wajib kosong fail safely, debug response/log tidak mengandung cookie, token atau URL credential. Fixture rahasia hanya string sintetis yang ditandai untuk tes, tidak secret valid.
- AUD01: setiap posting/perubahan master/role memiliki actor/what/when/entity/reason/reference dan before/after relevan; rollback tidak meninggalkan audit sukses. Penolakan auth tetap tercatat terpisah tanpa payload sensitif.
- UI01: semua state termasuk galat library, print, export, empty/loading/offline berbahasa Indonesia; enum mentah tidak terlihat. Pengecualian identitas teknis mengikuti 05.
- UI02: keyboard penuh, screen-reader form/status dasar, zoom 200%, viewport 360/768/1280, kontras, modal focus return dan hardware input.

## Demo yang deterministik

Kelak seed demo opt-in untuk environment `development/test` saja dan ditolak saat production. Dataset berlabel Demo dengan ID/data deterministik: satu produk kuantitas min 5 stok 20, satu min 5 stok 5, satu min 0 stok 0, satu produk SERIALIZED dengan dua unit unik; dua lokasi STORAGE untuk transfer; tiga role akun tanpa password yang dikomit. Credential demo diperoleh dari input lokal yang aman saat setup, tidak muncul dalam snapshot publik.

Saldo demo dibuat melalui command/ledger, bukan menulis saldo langsung. Uji dapat menyiapkan fixture DB melalui helper khusus test dengan invariant diverifikasi. Reset hanya database demo yang teridentifikasi; data produksi tidak pernah dihapus untuk mengembalikan demo. Jam test fixed, nomor identitas tetap dan state notifikasi predictable. Tidak memasukkan nama/kontak/serial operasional asli.

## Sasaran kinerja yang harus diukur

Dengan dataset acuan 02, dua operator dan jaringan stabil: feedback input lokal ≤100 ms; resolve barcode p95 ≤500 ms; finalisasi dokumen 100 baris p95 ≤2 detik; dasbor p95 ≤2 detik. Ukur server dan end-to-end terpisah, catat spesifikasi host, jaringan, sampel dan variasi. Kegagalan performa tidak dibenahi dengan menghapus lock/validasi.

Inbox tercipta dalam commit; tab aktif melihat perubahan dalam ≤20 detik pada jaringan sehat; worker mencoba push pertama ≤60 detik. Ini target upaya pengiriman, bukan jaminan delivery perangkat. RPO/RTO dan restore dimiliki 09. Target belum terverifikasi pada baseline ini.

## Gerbang tiap tugas

Selama iterasi: tes terarah untuk risiko yang berubah. Saat menyelesaikan tugas implementasi: review diff, secret check, lint, typecheck, unit suite, integration suite yang sudah tersedia, build dan E2E alur kritis yang terdampak. Bila mengubah inventory/auth/health, seluruh suite risiko inti modul terkait wajib dijalankan, bukan satu happy path. Saat rilis Core: seluruh suite aplikasi, concurrency, rollback, security matrix, critical E2E, restore dan hardware smoke.

P01 akan menyediakan scripts bernama konsisten (misalnya `lint`, `typecheck`, `test:unit`, `test:integration`, `test:e2e`, `build`) dan dokumentasi environment. **Nama tersebut rencana; jangan mengklaim command ada atau lulus sebelum package dibuat.** Jika fasilitas belum dibangun dalam fase awal, laporkan subset yang tersedia dan dependensi gate berikutnya; tidak boleh melewati acceptance tugas yang sedang dikerjakan.

- `[BE]`: bukti teknis sesuai risiko, migrasi/rollback plan bila relevan, izin/audit benar → dapat `[x]`.
- `[FE]` prototipe: browser check + owner Gerbang A untuk revisi itu → `[x]` hanya tugas prototipe.
- `[FE]/[FS]` implementasi: bukti teknis/browser + owner Gerbang B → `[x]`. Tanpa persetujuan tetap `[V]`.
- Bukti disimpan pada baris tugas dengan commit/tes ringkas dan rujukan artefak, serta `REVIEW.md` untuk visual. Hindari log panjang atau screenshot produksi dalam repo publik.

## Gerbang baseline dokumentasi (tugas saat ini)

Cek kelengkapan 00–13, README/AGENTS/config-example/prototype index; tautan lokal; kesesuaian ownership; hard constraints; tabel transisi dan contoh; urutan dependensi build; perhitungan 55 tugas; semua status implementasi belum dimulai; tidak ada file aplikasi/migrasi/dependensi; `.env.example` tanpa nilai; diff/secret scan dan commit hanya baseline. Review silang kontrak receipt/serial/lock/alert/izin dilakukan sebelum commit. Ini validasi dokumentasi, **bukan** bukti tes aplikasi atau persetujuan owner.
