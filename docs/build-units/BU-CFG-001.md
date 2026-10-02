# BU-CFG-001 — BusinessProfile, config guards dan logo revision privat

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Identitas client runtime dan config bounded; codename/logo repository bukan default client. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I4h / V3h / E1h = **8h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [02-ARCHITECTURE.md](../02-ARCHITECTURE.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [../DESIGN.md](../../DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-CMD-001](BU-CMD-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- DESIGN untuk accent final dan UI; identitas client aktual sebelum cutover

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-003, CORE-030, CORE-038, CORE-063, CORE-068. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: AUTH02, AUTH03, IDENTITY01, SEC01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: BusinessProfileRevision/pointer current, revision aset/template immutable dan guard config.

Identitas client runtime dan config bounded; codename/logo repository bukan default client. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Validasi nama/alamat/kontak/header/footer/accent bounded; revision immutable dan lock writer config bersama consumer.
- Upload hanya PNG/JPEG/WebP terverifikasi ≤5 MiB/16MP, re-encode/metadata stripped/key privat immutable; tolak SVG/HTML/executable/fetch URL/path traversal.
- Sediakan DTO/config/download media terotorisasi serta reference aset yang dipertahankan untuk snapshot dokumen; jangan hapus original yang direferensikan.

Risiko: Input profil tidak membuka storage gambar produk atau layanan fetch logo jarak jauh.

## VERIFY

- MIME/content malformed, polyglot, oversize, pixel cap, remote URL, aset tebakan dan path tidak aman; race config melawan command dan read revision yang dipertahankan.
- composer test:postgres; composer test:race; decoding gambar/batas resource dan test penolakan route media.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.