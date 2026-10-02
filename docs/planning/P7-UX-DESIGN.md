# P7 — Desain UI/UX V1 aktual sebelum freeze implementasi

**Status: WAITING OWNER VISUAL REVIEW — kandidat; P7 NOT COMPLETE. OWNER VISUAL APPROVAL belum ada.** Kandidat aktual `sha256-b51d2975aa3a8a92`, page `p7-v1` pada prototype `c8384f74-cc1b-4335-a385-2d0fac66d668`, sudah melalui compile dan browser checks bounded. `approvedRevision = null`; rights dan validation holds pada [coverage](P7-COVERAGE.md) tetap terbuka. P7 adalah pekerjaan desain aktual, bukan sekadar UX specification atau BU implementasi. P0–P6 tetap input frozen; P8–P11 tetap draft sampai P7 COMPLETE. Scope minimum V1 dan stack tidak berubah.

## Urutan dan input wajib

**P0–P6 → P7 ACTUAL DESIGN → P8 Quality/DoD → P9 Operations → P10 Release/UAT → P11 Build Units → implementation.** Review awal P8–P11 tetap dipreservasi, tetapi bukan freeze final. Semua coding belum diotorisasi; setelah seluruh fase COMPLETE/frozen masih perlu instruksi satu `Execute BU-...` eksplisit.

Input desain: [P1 produk](P1-PRODUCT.md), [P2 domain](P2-DOMAIN.md), [P3 workflow](P3-CRITICAL-WORKFLOWS.md), [P4 arsitektur](P4-ARCHITECTURE.md), [P5 security](P5-SECURITY.md), [P6 concurrency/API](P6-CONCURRENCY-API.md). Detail canonical: [01](../01-PRD.md), [02](../02-ARCHITECTURE.md), [04](../04-AUTH-RBAC-SECURITY.md), [06](../06-INVENTORY-SPEC.md), [12](../12-BARCODE-SCANNER.md), [15](../15-FINANCE-PROFITABILITY.md), [17](../17-POS-SALES.md). Ketigabelas input dipertahankan byte-identical pada koreksi ini.

## Workflow aktual P7

1. Baca skill DesainPakeAI dan gunakan CLI global `dpai`, tanpa mengulang setup/login. Sebelum context dan **setiap mutation**, jalankan `dpai project current --pretty`: harus **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef**. Mismatch berarti STOP. Jangan pilih/baca/ekspor/mutasi MULTIPLECORP atau gunakan domain/aset/token/inspirasi/fallback-nya. Tidak memakai MCP DesainPakeAI.
2. Ambil `dpai context --pretty`, simpan revision non-secret dan guidance authenticated relevan. Catat foundation, provenance dan hak template/font/aset yang terbukti atau pending. Template CRM/sales bukan otoritas bisnis.
3. Bangun IA/routes, hierarchy, component/state dan responsive composition dari P1–P6. Buat/refine **desain V1 aktual di DesainPakeAI**, sesuai [coverage G01–G18](P7-COVERAGE.md). Tidak membuat production frontend atau bundle pengganti UI01–UI04.
4. Self-review dan independent domain/visual review; verifikasi compiler lalu render desktop/tablet/mobile dan keyboard/touch/focus/contrast/zoom serta transisi happy/failure/recovery. Compiler PASS tidak membuktikan browser/layout PASS. Catat hasil aktual serta Not verified per revision.
5. Perbaiki kandidat → ajukan review visual konkret → feedback owner → targeted fix → verifikasi → review ulang sampai owner puas. Requirement baru masuk canonical sebelum eksekusi terdampak.
6. Catat **OWNER VISUAL APPROVAL eksplisit atas exact DesainPakeAI revision**, project/prototype/page, waktu, sumber approval dan scope. Approval chat lama/UI01/context hash atau kandidat lain tidak sah. Tanpa approval: **WAITING OWNER VISUAL REVIEW**, `approvedRevision = null`.
7. Normalisasikan hasil approved ke repository-owned [DESIGN](../../DESIGN.md): semantic tokens, typography/license, spacing/density, surfaces/borders/shadows, states, component patterns, responsive rules, accessibility, route/component mapping shadcn/ui. Review adaptasi bila mengubah tampilan/interaction approved. Kandidat boleh didokumentasikan sebagai draft, bukan kontrak approved.
8. Bekukan sumber approved + DESIGN/coverage/evidence pada revision kompatibel. P7 hanya COMPLETE jika seluruh exit di bawah lulus, rights tercatat dan tidak ada blocker visual yang belum diselesaikan/disetujui.
9. Refinalisasi berurutan **P8 → P9 → P10 → P11** berdasarkan P7 frozen: requirement/acceptance mapping, DAG, task count, critical path, estimates, links dan continuity; review/check lalu freeze kembali. Task koreksi ini tidak menjalankan Build Unit pertama.

## Coverage, role dan semantik

[Matriks G01–G18](P7-COVERAGE.md) mencakup auth/akun; shared shell/tiga workspace; catalog/search/duplicate-safe form/barcode/label; receiving/biaya write-only; opening Pemilik; manual issue; stock LOW/OUT/restock/history; CashSession open/identity/close; POS/cart/CASH/BANK_TRANSFER; receipt/reprint/own-history; owner correction/reversal/refund/return; finance/session/audit; BusinessProfile/config/users/register/status; document failure/recovery. Loading/empty/error/denied/stale/UNKNOWN/submitting/disabled/UNCERTAIN/success mengikuti applicability. UNKNOWN biaya berbeda dari UNCERTAIN command.

| Role | Workspace V1 |
| --- | --- |
| Pemilik / SUPER_ADMIN | Katalog/inventori/opening/correction; POS/session sendiri; finance/session/audit; pengguna/config/BusinessProfile/register/status sistem |
| Admin Operasional / OPERATIONS_ADMIN | Katalog/search/barcode, receiving/manual issue, stock/LOW/OUT/history aman, akun sendiri; tanpa POS/session/finance/audit sensitif; biaya incoming write-only |
| Kasir / CASHIER | Own session/open/close, search/scan/cart/full payment/receipt/own Sale history/report, akun sendiri; tanpa product management |

GOODS-only, satu stock pool, tiga role; tanpa CRM/services/booking/reservation/warehouse/HR/import. Product create = master stock0 tanpa movement; posting hanya setelah confirm. History asli immutable; refund uang/return barang memiliki batas terpisah; refund-only tidak menambah stok. Menu tersembunyi bukan keamanan backend.

## Kontrak interaksi dan quality

Desktop logo toggle expanded/icon rail; outside click mempertahankan navigasi. Drawer mobile dismiss outside/Escape dan focus trap/return; bottom navigation role-safe. Account di atas footer dalam bounds tanpa menggeser sidebar. Notifications compact, critical dahulu/recent activity berikut, scroll internal. Utilities relevan tanpa label role/sidebar action ganda.

Kasir: Scan/Cari → draft +/-/remove/undo → review satu pembayaran penuh → confirm → receipt. Cart/action mobile terjangkau; scan dijeda pada form/dialog/count, Enter tidak confirm. UNCERTAIN mempertahankan original command, tanpa bayar ulang/replacement Sale. Opening Rp0 eksplisit; close recount/CLOSED immutable jelas. Operations review/result/error/recovery tidak echo nominal biaya.

Owner report memakai line trend/accessible table, asOf/completeness/revision dan UNKNOWN tanpa Rp0 palsu. Periode draft/applied terpisah, Custom Range Apply/Cancel/validasi. DD MM YYYY, hh:mm:ss, Asia/Jakarta. Identitas client memakai BusinessProfile runtime; BantuKasir codename.

Minimum render375/440/768/1024/1440 px, short landscape, zoom200%, konten/cart/form panjang; touch44×44, keyboard/focus/Escape/return/reduced motion; WCAG AA4.5:1 teks/3:1 UI bermakna. Native React/Inertia/shadcn consumer mengulangi parity/a11y/privacy. Fixture tidak membuktikan PostgreSQL/HTTP/auth/RBAC/device/backup.

## Exit P7 dan estimasi

- G01–G18 memiliki desain aktual/state/applicability/evidence; missing/Not verified diselesaikan sebelum COMPLETE.
- **OWNER VISUAL APPROVAL** exact revision, rights/font/aset dan normalisasi DESIGN/mapping lengkap; review adaptasi selesai.
- Source/coverage/acceptance binding dan responsive/a11y evidence koheren; tanpa production code atau runtime acceptance fiktif.
- Update evidence/keputusan nyata/11, lalu00 LAST; P8–P11 tetap draft sampai exit P7 terpenuhi.

P7 **32h engineering: desain aktual20 + review/iterasi8 + normalisasi/evidence4**; reserve desain8h terpisah. Waiting owner/lisensi dan putaran di luar allowance tidak diketahui, bukan jam yang telah dibukukan. Refinalisasi P8–P11 **8h** terpisah; [11](../11-BUILD-PLAN.md) menghitung total.

BU-DESIGN-001 dihapus dari register aktif karena tanggung jawabnya sepenuhnya diserap P7. UI/DOC mengonsumsi P7 COMPLETE/DESIGN exact-approved langsung; UX01/IDENTITY01 FINAL tetap diuji pada aplikasi terintegrasi. Artefak/revision/check aktual pada [evidence koreksi](../evidence/p7-correction/REVIEW.md). Kandidat pertama bukan P7 COMPLETE.
