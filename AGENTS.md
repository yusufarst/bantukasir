# Aturan agen — LATANSA Platform

Aturan ini berlaku untuk seluruh repositori, termasuk Gemini Antigravity dan agen implementasi lain. Instruksi eksplisit pemilik dalam sesi tetap didahulukan. Jika instruksi tampak mengubah invariant keselamatan, jelaskan dampaknya dan catat perubahan keputusan sebelum bekerja.

## Pemulihan konteks cepat

1. Periksa `git status --short`; pertahankan perubahan milik pengguna.
2. Baca `docs/00-CURRENT-STATE.md`, lalu baris tugas aktif/berikutnya di `docs/11-BUILD-PLAN.md`.
3. Cari topik dengan `rg` sebelum membaca file luas. Baca entri relevan `docs/10-DECISIONS.md` dan spesifikasi kanonis tugas; indeks kepemilikan ada di `README.md`.
4. Jika belum ada izin implementasi setelah baseline, berhenti pada review dokumentasi. **Tugas baseline ini hanya dokumentasi: tidak ada scaffold, aplikasi, migrasi, atau instalasi dependensi.**

## Prioritas dan invariant wajib

- Inventaris operasional adalah inti: staf → scan → masuk/keluar → ledger → saldo → perhatian stok → pemilik. Website publik tidak boleh mendahului integritas inti.
- Semua UI, pesan galat, laporan, audit manusia, dan notifikasi berbahasa Indonesia. Enum internal wajib dipetakan. Ikuti `05-DESIGN-SYSTEM.md`.
- Sumber kebenaran jumlah adalah ledger immutable. Jangan menyediakan edit saldo bebas, menghapus riwayat, membuat stok negatif, atau memperbaiki selisih dengan perubahan SQL diam-diam. Ikuti `06-INVENTORY-SPEC.md`.
- Seluruh mutasi stok melalui satu command service, transaksi PostgreSQL, otorisasi backend, idempotensi, locking, dan audit. UI tersembunyi bukan kontrol akses.
- Scan belum mengubah stok. Sukses hanya sesudah server mengonfirmasi commit. Jangan menambahkan mutasi stok offline.
- Stok menipis/habis menggunakan transisi dan episode perhatian pada `13-NOTIFICATIONS.md`, bukan notifikasi berulang setiap pemeriksaan.
- Jangan memberikan `SUPER_ADMIN` kepada staf biasa. Semua jalur API/action/job harus mengikuti batas izin pada `04-AUTH-RBAC-SECURITY.md`.
- Produk publik berasal dari allowlist proyeksi master yang sama; jangan mengirim entitas internal lengkap ke browser publik.
- Tidak boleh ada rahasia asli, data pelanggan asli, dump DB, atau konfigurasi produksi di Git. `.env.example` hanya nama variabel dan nilai kosong; tidak ada rahasia cadangan.

## Aturan visual yang tidak boleh dilewati

Klasifikasikan setiap pekerjaan `[BE]`, `[FE]`, atau `[FS]` sebelum implementasi.

Frontend signifikan: kebutuhan → HTML/CSS mandiri dengan demo aman → preview browser → review visual manual pemilik → revisi sampai disetujui → Next.js → data nyata → verifikasi otomatis/browser → review visual final pemilik.

- HTML yang disetujui adalah kontrak visual. Prototipe tidak boleh memanggil API atau database produksi.
- Lint, typecheck, test, build, dan screenshot agen tidak menggantikan persetujuan pemilik.
- `[FE]`/`[FS]` yang lolos teknis masih `[V] MENUNGGU REVIEW VISUAL OWNER`; hanya persetujuan eksplisit pemilik atas revisi tertentu memungkinkan `[x]`.
- Backend `[BE]` dapat selesai secara mandiri. Pisahkan baris BE bila ingin menghitung progresnya; jangan menganggap keseluruhan FS selesai.
- Perubahan minor yang benar-benar menggunakan pola kanonis yang sudah disetujui tidak memerlukan HTML baru; tetap verifikasi browser dan review visual hasilnya. Definisi dan bukti review ada di `05-DESIGN-SYSTEM.md`.

## Disiplin eksekusi dan token

- Repositori adalah memori kerja, bukan riwayat chat. Satu dokumen kanonis per topik; tautkan aturan, jangan menyalinnya ke banyak spesifikasi.
- **Satu tugas build aktif** pada satu waktu. Tidak ada refactor tak terkait, fitur spekulatif, atau perluasan MVP diam-diam.
- Jangan membaca ulang dokumen yang tidak berubah dalam sesi. Cari simbol/section dahulu; batasi keluaran command dan diff ke bagian relevan.
- Jangan mengulang penjelasan arsitektur yang sudah diterima. Jika perlu mengubahnya, buat keputusan pengganti dengan alasan dan dampak.
- Jalankan tes terarah saat iterasi dan gerbang penuh yang berlaku saat penyelesaian menurut `08-TESTING-ACCEPTANCE.md`. Efisiensi tidak mengurangi integritas, keamanan, pengujian, audit, atau review visual.
- Jangan menyatakan tes lulus bila tidak dijalankan. Nyatakan `belum dijalankan` beserta alasan, jangan memberi status selesai palsu.
- Jangan menginstal layanan berbayar, mengirim pesan ke pihak lain, menerbitkan situs, atau mendorong commit tanpa cakupan instruksi yang sesuai.
- Gunakan cabang reviewable setelah baseline. Jangan mengubah sejarah Git atau menghapus pekerjaan pengguna.

## Serah-terima dan definisi selesai

Perbarui baris tugas pada `11-BUILD-PLAN.md` dengan status dan bukti yang dapat ditemukan. Perbarui `00-CURRENT-STATE.md` hanya dengan keadaan terverifikasi, tugas aktif/berikutnya, blocker, dan hasil cek. Catat keputusan baru pada `10-DECISIONS.md`; jangan mengubah keputusan terkunci tanpa riwayat pengganti.

Laporan akhir ringkas: hasil, berkas utama, pemeriksaan yang benar-benar dilakukan, keterbatasan/blocker, status visual, dan langkah berikutnya. Jangan menghitung `[V]` sebagai selesai. Jangan menyimpan kredensial, cookie, payload sensitif, atau transkrip panjang sebagai bukti.
