# BantuKasir — Baseline P0–P11

Codename internal untuk GOODS-only POS satu bisnis/satu stock pool; branding client final berasal dari BusinessProfile runtime. Repository berisi specification dan prototype historis; **aplikasi produksi belum dibangun**.

Planning mengikuti P0–P11: governance, scope, domain, workflow kritis, architecture, security, concurrency/API/performance, UX/design, quality/DoD, operations/recovery, release/UAT, lalu Build Units. Kerangka diadaptasi dari diskusi owner; domain MULTIPLECORP tidak disalin.

Mulai [AGENTS](AGENTS.md) → [CURRENT_STATE](docs/00-CURRENT-STATE.md) → [build plan](docs/11-BUILD-PLAN.md). Handoff coding di [CLAUDE](CLAUDE.md); unit pertama [BU-FOUND-001](docs/build-units/BU-FOUND-001.md) **belum diotorisasi/dijalankan**. Primary executor yang diminta: Claude Opus 5.5/xhigh; Fable 5.1/xhigh untuk eskalasi bounded secara selektif. Availability belum diuji.

**28 Build Units, 73 requirement terpetakan, 39 acceptance suite, 0 FINAL PASS.** Engineering dari nol 259h (I113/V118/E28), reserve 56h terpisah; total jika seluruh reserve terpakai 315h. Waiting owner/eksternal bukan jam engineering. [11](docs/11-BUILD-PLAN.md) memuat DAG/critical path exact dan asumsi.

| Canonical | Otoritas |
| --- | --- |
| [Paket P0–P11](docs/planning/P11-BUILD-UNITS.md) | Register fase/review/freeze dan delivery |
| [01 PRD](docs/01-PRD.md) | Core minimum/Later/role/workflow |
| [02 architecture/domain](docs/02-ARCHITECTURE.md), [P4 evaluasi stack](docs/planning/P4-ARCHITECTURE.md) | Laravel/Inertia/React/TypeScript/PostgreSQL/Nginx terpilih; invariant schema/transaksi |
| [04 security](docs/04-AUTH-RBAC-SECURITY.md), [P5 threat model](docs/planning/P5-SECURITY.md) | Auth/policies/object scope, DTO dan jalur ancaman |
| [06 inventory](docs/06-INVENTORY-SPEC.md), [12 search/barcode](docs/12-BARCODE-SCANNER.md) | Ledger fisik/stock/search/HID |
| [15 costing](docs/15-FINANCE-PROFITABILITY.md), [17 Sale/session](docs/17-POS-SALES.md) | MWA/UNKNOWN, pembayaran penuh/session atomik, correction/dokumen |
| [DESIGN](DESIGN.md) | Exact approved DPAI revision → token semantic → UI React/shadcn native |
| [08 acceptance/DoD](docs/08-TESTING-ACCEPTANCE.md), [CORE-MAP](docs/requirements/CORE-MAP.md) | Setiap requirement Core → BU + acceptance final |
| [09 ops](docs/09-DEPLOYMENT-OPS.md), [P10 release/UAT](docs/planning/P10-RELEASE-UAT.md) | Database dilindungi, independent backup/restore dan cutover terotorisasi |
| [10 keputusan](docs/10-DECISIONS.md), [gap/change control](docs/planning/GAPS-CHANGES.md) | Keputusan nyata, hold eksternal dan scope terdampak |
| [Archive Plan 2.0](docs/archive/plan2/README.md), [register prototype](prototypes/README.md) | Input historis saja |

Stack dipilih dari perbandingan A/B khusus produk ini, bukan preseden project lain. Tanpa Redis/queue/SSR runtime secara default; version/advisory/license dipin saat foundation. Blueprint freeze tidak menutup DESIGN/G1–G4/DEVICES/RELEASE atau membuktikan implementasi/testing.

Otorisasi saat ini: planning dan satu commit dokumentasi lokal; tidak menjalankan BU pertama. Lineage Git dipertahankan, source/aset/evidence historis dilindungi; keadaan fisik terkini tercatat pada 00.