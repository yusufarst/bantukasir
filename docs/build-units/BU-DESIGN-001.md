# BU-DESIGN-001 — Approved DesainPakeAI revision dan repository visual contract

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Sumber BantuKasir Design exact yang dipilih owner, token semantic dan kontrak komponen/state React/shadcn. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I3h / V2h / E1h = **6h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [../DESIGN.md](../../DESIGN.md) → [planning/P7-UX-DESIGN.md](../planning/P7-UX-DESIGN.md) → [01-PRD.md](../01-PRD.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: tidak ada. Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- DESIGN: pemilihan exact revision/rights/approval owner WAJIB untuk COMPLETE

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-030, CORE-034, CORE-070. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: UX01, IDENTITY01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Tanpa write database; source/revision/token/mapping native repository dan evidence approval.

Sumber BantuKasir Design exact yang dipilih owner, token semantic dan kontrak komponen/state React/shadcn. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Baca skill; verifikasi dpai project current hanya pada ID yang diizinkan sebelum context/setiap mutation terotorisasi; simpan revision/provenance/evidence rights non-secret.
- Review/adaptasi GOODS/role/CashSession/branding runtime, hilangkan artefak scope template; petakan typography/color/spacing/radius/elevation/motion sumber → token semantic repository/komposisi native.
- Dapatkan visual approval exact revision dan rights dari owner; freeze mapping source → route/component/state, kriteria responsive/a11y dan exceptions.

Risiko: Context teramati bukan approval; tanpa akses MULTIPLECORP, logo codename sebagai authority, iframe atau shipment prototype mentah.

## VERIFY

- Konsistensi provenance/project/revision/approval rights, mapping komponen/token native, checklist viewport/keyboard/state dan visual review owner.
- Context CLI read-only; preview/parity aktual hanya saat source/target terotorisasi tersedia. Jangan mengklaim UI visual PASS dari planning ini.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.