# BU-UI-001 — Native shared shell, autentikasi dan account/config UI

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Shell/login/account responsive React/Inertia/shadcn serta form identitas, user dan konfigurasi Pemilik. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I4h / V3h / E1h = **8h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [../DESIGN.md](../../DESIGN.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [planning/P7-UX-DESIGN.md](../planning/P7-UX-DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-IAM-001](BU-IAM-001.md), [BU-CFG-001](BU-CFG-001.md). Provider coding harus mempunyai evidence PASS yang dibekukan pada revision kompatibel.

Provider planning: [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval owner atas exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi. Metadata: `planningProviders.phase: P7`, `requiredStatus: COMPLETE`, `approvedRevision: null` (approval belum tersedia). P7 bukan dependency coding. Paket P8–P11 saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; refinalisasi/FROZEN dan Execute eksplisit tetap wajib sebelum seluruh coding.

- P7 COMPLETE dengan approval owner exact revision dan DESIGN/kontrak token dinormalisasi; paket P8–P11 difinalisasi ulang/FROZEN sebelum Execute; identitas client aktual wajib sebelum pilot.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-001, CORE-002, CORE-003, CORE-030, CORE-034, CORE-038, CORE-070. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: AUTH01, AUTH02, AUTH03, IDENTITY01, UX01, SEC01. Delivery/targeted proof ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tables: Consumer IAM/CFG; tidak mengakses database langsung atau menciptakan fakta bisnis di client.

Shell/login/account responsive React/Inertia/shadcn serta form identitas, user dan konfigurasi Pemilik. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize privileged Eloquent entity. UI consumer memakai native React/Inertia/shadcn approved contract; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu consumer UI setelah seluruh paket planning dibekukan dan BU terotorisasi. Tidak ada capability Later.

## DO

- Tampilkan menu sesuai capability tiga role; desktop rail stabil, drawer mobile menutup melalui outside-click/Escape, account popover/footer bounded dan profil sesuai role.
- Integrasikan login/reset/invite/expired/denied/recent-auth serta form lifecycle user/config/media dengan route nyata; setiap kontrol mempunyai tujuan yang benar.
- Gunakan semantic tokens dan runtime brand yang disetujui; sediakan loading/empty/error/stale/focus. Props/flash/old input/storage browser tidak menyimpan secret atau data privileged untuk staff.
- Integrasikan lifecycle history terenkripsi dari IAM; clear/rotate keys, remembered forms, query cache dan state client pada logout/pergantian actor/revoke yang teramati. Back/Forward/bfcache memvalidasi ulang actor sebelum merender data sensitif. Biaya receiving write-only tidak pernah dipersist; consumer receiving memverifikasinya pada BU-UI-002.

Risiko: History/shared props Inertia tidak boleh mengembalikan entity privileged atau credential.

## VERIFY

- Playwright memeriksa route allowed/denied, guessed ID/direct network, cookies/CSRF, focus reset, error draft dan upload konfigurasi; 375/440/768/1024/1440px, zoom 200%, keyboard dan touch.
- npm run lint; npm run typecheck; npm run test:unit; npm run build; npm run test:e2e; backend checks terarah.
- Uji logout/pergantian actor/Back/Forward/reload/bfcache pada shell dan data akun/config yang sudah tersedia, termasuk pembersihan remembered forms/query cache. Finance belum menjadi dependency unit ini; seluruh consumer finance Pemilik→staff diverifikasi FINAL pada BU-VERIFY-002.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP affected scope pada kontradiksi requirement, integrity/privacy/recovery failure, unmet dependency, unauthorized environment/tool cost/destruction, fundamental business decision atau requested executor model yang tak tersedia. Catat gap/affected acceptance dan kerjakan bagian independen yang masih authorized. Jangan mengubah business meaning demi implementasi.

## UPDATE / COMMIT / NEXT

Catat actual paths/revision/commands/results/reviewer/fixes/holds; update kartu dan11,10 hanya keputusan nyata, **00 LAST**. COMPLETE memerlukan bounded delivery+checks+completion holds; product FINAL mengikuti08.

Stage exact intended paths dan review seluruh index; preserve unrelated work. Local commit hanya sesudah BU explicitly authorized dan koheren. Jangan push/merge/deploy/publish/purchase atau destruction tanpa approval exact. STOP setelah closeout satu BU; tidak otomatis execute berikutnya.
