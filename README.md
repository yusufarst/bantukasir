# BantuKasir — Plan 2.0

BantuKasir adalah codename internal POS GOODS-only untuk satu bisnis dan satu stock pool. Identitas resmi client akan dikonfigurasi saat runtime. Repository saat ini berisi spesifikasi, task cards, prototype historis serta evidence; **aplikasi produksi belum dibangun**.

Plan 2.0 menggantikan model delivery lama. Ada **40 task Core, 0/40 COMPLETE**, dengan 236 jam engineering dan cadangan risiko 48 jam terpisah. Backend dan pengujian dapat dimulai sebelum approval visual; produksi UI memakai sumber DesainPakeAI yang disetujui melalui kontrak repository dan shadcn/ui.

Mulai dari [aturan agent](AGENTS.md), [kondisi lokal/handoff](docs/00-CURRENT-STATE.md), [build plan dan graf](docs/11-BUILD-PLAN.md) serta [T01](docs/tasks/T01.md). Task pertama belum diotorisasi untuk dieksekusi oleh sesi rebaseline ini.

| Dokumen canonical | Otoritas |
| --- | --- |
| [01 PRD](docs/01-PRD.md) | Produk, role, Core/Later |
| [02 Arsitektur](docs/02-ARCHITECTURE.md) | Stack, domain, transaksi, precision |
| [04 Keamanan](docs/04-AUTH-RBAC-SECURITY.md) | Auth, RBAC, DTO, media privat |
| [DESIGN](DESIGN.md) | Pipeline visual, approval revision, pattern/token |
| [06 Inventori](docs/06-INVENTORY-SPEC.md) | Receiving, issue, opening, koreksi, LOW/OUT |
| [12 Search/barcode](docs/12-BARCODE-SCANNER.md) | Search canonical, HID dan label |
| [15 Finance](docs/15-FINANCE-PROFITABILITY.md) | MWA, UNKNOWN, replay, laporan |
| [17 POS](docs/17-POS-SALES.md) | Sale, dokumen, CashSession |
| [08 Acceptance](docs/08-TESTING-ACCEPTANCE.md) | Ownership dan bukti final |
| [09 Operasi](docs/09-DEPLOYMENT-OPS.md) | Database protected, packaging, backup/restore |
| [10 Keputusan/gates](docs/10-DECISIONS.md) | Keputusan efektif dan holds owner |
| [11 Tasks](docs/11-BUILD-PLAN.md) | Dependency, estimasi dan tracker |
| [Prototype historis](prototypes/README.md) | Referensi state/fixture, bukan visual produksi |

Target stack: Next.js/TypeScript, PostgreSQL, Drizzle, Zod, Better Auth self-hosted, shadcn/ui, Vitest, real PostgreSQL integration tests, Playwright, Docker Compose dan Caddy. Versi kompatibel/advisory/lisensi diverifikasi serta dipin dalam T01; belum ada claim build/runtime lulus.

Sesi ini tidak melakukan coding, push, merge, deploy atau perubahan database. Source dan staging parsial BK03 dipertahankan terpisah dari commit dokumentasi.
