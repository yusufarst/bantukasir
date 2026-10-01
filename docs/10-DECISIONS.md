# 10 — Keputusan efektif dan gates owner

**Plan 2.0, 1–2 Oktober 2026.** Register ini menggantikan keputusan delivery/visual Plan 1.1, proposal R01–R18 dan BK-Dxx yang tercatat di Git. Invariant produk yang masih aktif dipindahkan ke spesifikasi canonical; histori tidak memperoleh authority untuk scope atau approval visual baru. Ini keputusan perencanaan, bukan bukti aplikasi sudah diimplementasikan.

## Keputusan efektif

| ID | Keputusan |
| --- | --- |
| P2-D01 | GOODS-only, satu bisnis/satu stock pool, tepat tiga role; full instant Sale CASH/BANK_TRANSFER. Scope dan Later mengikuti [01](01-PRD.md) |
| P2-D02 | Modular monolith Next.js/TypeScript/PostgreSQL/Drizzle/Zod, Better Auth self-hosted, shadcn/ui, Vitest/real PostgreSQL/Playwright, Compose/Caddy. Verify/pin versi/advisory saat T01, tanpa worker/queue/search eksternal/enterprise framework |
| P2-D03 | StockMovement immutable + balance sinkron nonnegative, atomic Sale lengkap, source intent/original recovery, actor/config guards. DB constraints/races/rollback wajib; [02](02-ARCHITECTURE.md) |
| P2-D04 | MWA/UNKNOWN/append-only cost revision + bounded synchronous replay; refund/return/reversal menjaga original dan historical cost. Biaya/HPP/profit hanya owner; Operations bukti baru write-only; [04](04-AUTH-RBAC-SECURITY.md), [15](15-FINANCE-PROFITABILITY.md) |
| P2-D05 | CashSession lightweight single responsible cashier/drawer, explicit opening float termasuk 0, OPEN→CLOSED, race-safe cutoff/snapshot; refund dana owner terpisah. Physical practice tetap menunggu G3; [17](17-POS-SALES.md) |
| P2-D06 | BantuKasir adalah codename, bukan branding produksi. BusinessProfile runtime versioned dan private official logo; receipt/session snapshot mempertahankan revision/aset/template. Logo repository tidak menjadi default client |
| P2-D07 | Visual lama sepenuhnya superseded. Pipeline DesainPakeAI → sumber yang dipilih/disetujui owner → kontrak/token repo → shadcn/ui → Next.js native. Tidak ada Gate A/B, mandatory 21st.dev/Impeccable atau executor Antigravity |
| P2-D08 | Hanya project DesainPakeAI BantuKasir Design / `eddb0e15-9909-44b3-8517-9239cadc65ef`; CLI `dpai`, no MCP. MULTIPLECORP tidak disentuh. Context terbaru teramati, approval/rights belum dianggap selesai; [DESIGN](../DESIGN.md) |
| P2-D09 | Backend/tests/ops non-production independen dari visual approval. T23 approval contract prerequisite frontend T24. Satu task executor aktif, tidak otomatis lanjut. Wait owner bukan jam engineering |
| P2-D10 | Manual opening owner-only batas 100 row/command, global onboarding freeze dan once/no prior ledger; zero checklist tanpa movement. CSV/XLSX/serial Later. G2 menilai kecocokan client sebelum cutover |
| P2-D11 | 40 coding task baru, 236h engineering, critical path 93h dengan resource independen, reserve 48h terpisah; bukan target 154/172h. Ownership acceptance dan final point di [08](08-TESTING-ACCEPTANCE.md)/[11](11-BUILD-PLAN.md) |
| P2-D12 | Production DB protected, forward populated migration, reconciliation, independent encrypted DB+media/template backup, isolated restore, revoke/recoveryEpoch/gap review. RPO6h/RTO4h adalah proposal, belum diterima owner; [09](09-DEPLOYMENT-OPS.md) |
| P2-D13 | Hapus metadata visual aktif lama serta merge dokumen duplikat sesudah audit kebutuhan; simpan source/fixtures/evidence prototype untuk referensi perilaku saja. Staging BK03 existing dipertahankan terpisah melalui snapshot dan docs-only path commit |
| P2-D14 | Rebaseline mengotorisasi satu local documentation commit saja. T01 belum dimulai. Push/merge/deploy/purchase/production infra/DB destruction tidak diotorisasi |

## Gates — seluruhnya belum ditutup

| Gate | Kondisi aktual / yang harus owner berikan | Menahan |
| --- | --- | --- |
| DESIGN | CLI/project/context READY. Foundation Provenance Thread alpha.1 dan revision teramati di DESIGN; final selection/visual approval exact revision serta entitlement/font/assets rights **PENDING** | T23 COMPLETE dan T24/UI produksi; backend tidak tertahan |
| G1 | **OPEN**: tax/legal invoice, buyer identity policy, penggunaan nota komersial tanpa klaim faktur pajak | Penerimaan dokumen client T32/T38 dan pilot T39 |
| G2 | **OPEN**: unit/precision, volume master/opening manual, barcode nyata, bukti cost/UNKNOWN dan serial requirements | Data client nyata/opening cutover; fixture terisolasi tetap boleh |
| G3 | **OPEN**: jumlah/identitas drawer fisik, float termasuk 0, satu responsible cashier, handover tutup→buka dan dana refund owner | Penerimaan physical practice T14/T17/T29/T38; bukan prototype-mock pass |
| G4 | **OPEN**: destination independen, biaya/akses backup dan recovery key/operator; measured/accepted RPO/RTO | T33 destination actual, T34 FINAL recovery, T38/T39 |
| DEVICES | **PENDING**: USB HID, Bluetooth HID, label printer dan receipt printer beserta driver/media aktual | T36 FINAL perangkat; emulator tidak menggantikan |
| RELEASE | **NOT AUTHORIZED**: VPS/domain/secrets/target, deployment approval exact revision/operation dan owner pilot | T37 target benchmark bila akses diperlukan; T39 deployment/pilot |

Source readiness tidak sama dengan visual approval. Owner approval visual tidak menggantikan correctness/RBAC/device/recovery. G1–G4 yang berubah menjadi layanan/serial/shared drawer/tax accounting membutuhkan keputusan/replan scope, bukan bypass. Tunggu hanya pekerjaan yang betul-betul bergantung pada jawaban; technical dependencies di [11](11-BUILD-PLAN.md).

## Bukti penutupan gate

Catat tanggal/owner, jawaban, target/revision non-secret, link evidence, requirement yang berubah dan affected tasks. Jangan menempel credential atau private recovery contact ke docs publik. FINAL PASS suite teknis tidak otomatis menutup gate owner. RELEASE01 baru final sesudah actual authorized pilot T39.

Keputusan berikutnya memakai ID P2-D15 dan seterusnya hanya jika mengubah kontrak nyata. Jangan mencatat “no commit/staging only” sebagai keputusan produk atau mengubah approval pending menjadi approved berdasarkan report executor.
