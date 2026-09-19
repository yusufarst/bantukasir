# LATANSA Platform

Sistem operasional inventaris untuk **CV. Latansa Jogjakarta**. Staf mencatat barang masuk dan keluar melalui pemindaian; pemilik memperoleh stok yang dapat dipercaya, pemberitahuan stok menipis/habis, dan dasbor tindakan. Situs perusahaan dan penjualan adalah perluasan berikutnya dengan satu master produk.

**Status: baseline dokumentasi, 19 September 2026. Belum ada aplikasi, migrasi, atau lingkungan produksi.** Dokumentasi siap ditinjau pemilik; keputusan desain belum berarti fitur sudah tersedia.

## Mulai di sini

1. Baca [AGENTS.md](AGENTS.md) untuk aturan kerja.
2. Baca [kondisi saat ini](docs/00-CURRENT-STATE.md) untuk serah-terima sesi.
3. Pilih satu pekerjaan dari [rencana pembangunan](docs/11-BUILD-PLAN.md), setelah pemilik mengizinkan implementasi.
4. Baca hanya dokumen topik yang diperlukan dan [keputusan terkunci](docs/10-DECISIONS.md).

## Peta sumber kebenaran

| Dokumen | Pemilik topik |
| --- | --- |
| [01 — PRD](docs/01-PRD.md) | Hasil bisnis, pengguna, batas MVP, ukuran keberhasilan |
| [02 — Arsitektur](docs/02-ARCHITECTURE.md) | Modul, aliran dependensi, kontrak layanan, batas publik |
| [03 — Model domain](docs/03-DOMAIN-MODEL.md) | Identitas, relasi, siklus hidup, batas integritas entitas |
| [04 — Autentikasi dan keamanan](docs/04-AUTH-RBAC-SECURITY.md) | Izin, akun, ancaman, rahasia, audit keamanan |
| [05 — Sistem desain](docs/05-DESIGN-SYSTEM.md) | Bahasa UI, prototipe, kontrak visual, review pemilik |
| [06 — Spesifikasi inventaris](docs/06-INVENTORY-SPEC.md) | Akuntansi stok, transaksi, locking, pembalikan, reservasi |
| [07 — Alur bisnis](docs/07-BUSINESS-FLOWS.md) | Perjalanan pengguna lintas modul |
| [08 — Pengujian](docs/08-TESTING-ACCEPTANCE.md) | Bukti penerimaan, matriks risiko, definisi selesai |
| [09 — Operasi](docs/09-DEPLOYMENT-OPS.md) | Konfigurasi, deploy, backup, restore, insiden |
| [10 — Keputusan](docs/10-DECISIONS.md) | Pilihan arsitektur, alasan, asumsi, perubahan keputusan |
| [11 — Rencana pembangunan](docs/11-BUILD-PLAN.md) | Satu pelacak tugas dan progres resmi |
| [12 — Barcode dan pemindai](docs/12-BARCODE-SCANNER.md) | Identitas kode, label, sesi scan, input HID |
| [13 — Notifikasi](docs/13-NOTIFICATIONS.md) | Status stok, episode perhatian, deduplikasi, pengiriman |

## Arah teknologi

Modular monolith: Next.js, TypeScript, PostgreSQL, Drizzle, Zod, Tailwind CSS, shadcn/ui, Better Auth, Vitest, Playwright, Docker Compose, dan Caddy. Versi akan diverifikasi dan dikunci saat fondasi implementasi dimulai. Tidak ada perintah instalasi atau menjalankan aplikasi yang sudah tersedia saat ini.

Repositori publik: jangan memasukkan rahasia atau data operasional nyata. [.env.example](.env.example) hanya berisi nama variabel dengan nilai kosong; kebutuhan dan tahap aktivasinya ada di dokumen operasi.

Remote: [yusufarst/latansa-inventory](https://github.com/yusufarst/latansa-inventory). Baseline awal menggunakan `main`; perubahan substansial berikutnya menggunakan cabang `codex/` atau cabang yang diminta pemilik dan review yang sesuai.
