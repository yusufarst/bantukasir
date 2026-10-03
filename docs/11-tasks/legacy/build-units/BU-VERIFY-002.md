# BU-VERIFY-002 — Final integrated business, RBAC/privacy, security dan UX acceptance

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Penerimaan final seluruh workflow bisnis, RBAC/privasi, security dan UX pada revision terintegrasi; final operasi/perangkat/performance/release mempunyai unit tersendiri. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I0h / V8h / E1h = **9h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [08-TESTING-ACCEPTANCE.md](../08-TESTING-ACCEPTANCE.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [../DESIGN.md](../../DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-002](BU-UI-002.md), [BU-UI-003](BU-UI-003.md), [BU-UI-004](BU-UI-004.md), [BU-DOC-001](BU-DOC-001.md). Provider harus mempunyai evidence compatible PASS yang dibekukan. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- Approval DESIGN dan seluruh hold bisnis yang berlaku wajib tersedia sebelum penerimaan final produk terkait.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-068. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: AUTH01, AUTH02, AUTH03, SEARCH01, SEARCH02, SEARCH03, INV01, INV02, INV03, OPEN01, POS01, POS02, POS03, RET01, STOCK01, FIN01, FIN02, FIN03, SESSION01, SESSION02, SESSION03, SESSION04, SESSION05, SESSION06, SESSION07, SESSION08, SESSION09, SESSION10, AUDIT01, IDENTITY01, UX01, SEC01, PAYEXT01. Final point hanya untuk suite yang dipetakan di08; seluruh consumer dan applicable holds diperlukan.

## DATA, ACTION DAN UI BOUNDARY

Data/tables: Fakta PostgreSQL serta kanal network/output pada kandidat terintegrasi; hanya target test terisolasi.

Penerimaan final seluruh workflow bisnis, RBAC/privasi, security dan UX pada revision terintegrasi; final operasi/perangkat/performance/release mempunyai unit tersendiri. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize privileged Eloquent entity. UI consumer memakai native React/Inertia/shadcn approved contract; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Jalankan suite terarah yang telah tersedia lintas consumer, ketiga role dan object scope: status/cache/errors/log/props/flash/history/print/PDF/CSV/media/browser storage.
- Uji race last-unit/source/key/close/revoke/config/refund/replay dan browser flow lengkap sampai persisted result; original immutable dan privasi biaya wajib terjaga.
- Periksa threat paths, dependency advisories, static analysis serta responsive 375/440/768/1024/1440px, zoom 200%, keyboard/touch/reduced motion/contrast.

Risiko: Final fan-in memakai suite provider yang nyata; defect signifikan memakai reserve atau memerlukan reestimasi.

## VERIFY

- Jalankan composer lint, composer analyse, composer test:unit, composer test:postgres, composer test:race; npm run lint, npm run typecheck, npm run test:unit, npm run build, npm run test:e2e; seluruh test role/ancaman yang berlaku.
- Laporkan hasil tiap suite dan hold tersisa. Consumer atau jalur yang belum diuji tidak memperoleh FINAL PASS; bukti perangkat fisik tetap milik BU-VERIFY-003.
- Uji seluruh consumer finance Pemilik→logout→staff→Back/Forward/reload/bfcache serta invalidasi session/remembered/query cache dan no-leak seluruh kanal. Ini bukti terintegrasi AUTH02/SEC01 FINAL, berbeda dari HTTP IAM dan shell proof awal.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP affected scope pada kontradiksi requirement, integrity/privacy/recovery failure, unmet dependency, unauthorized environment/tool cost/destruction, fundamental business decision atau requested executor model yang tak tersedia. Catat gap/affected acceptance dan kerjakan bagian independen yang masih authorized. Jangan mengubah business meaning demi implementasi.

## UPDATE / COMMIT / NEXT

Catat actual paths/revision/commands/results/reviewer/fixes/holds; update kartu dan11,10 hanya keputusan nyata, **00 LAST**. COMPLETE memerlukan bounded delivery+checks+completion holds; product FINAL mengikuti08.

Stage exact intended paths dan review seluruh index; preserve unrelated work. Local commit hanya sesudah BU explicitly authorized dan koheren. Jangan push/merge/deploy/publish/purchase atau destruction tanpa approval exact. STOP setelah closeout satu BU; tidak otomatis execute berikutnya.
