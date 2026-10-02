# BU-CAT-001 — Master barang, harga, Product Search dan registry barcode

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Pengelolaan master search-first, harga authoritative/versioned dan service search paginated yang aman per role. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I5h / V4h / E1h = **10h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [01-PRD.md](../01-PRD.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [12-BARCODE-SCANNER.md](../12-BARCODE-SCANNER.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-CMD-001](BU-CMD-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- G2 sebelum master client nyata

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-003, CORE-004, CORE-005, CORE-015, CORE-036, CORE-040, CORE-042, CORE-046. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: SEARCH01, SEARCH02, STOCK01, AUTH02, AUTH03, PERF01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Product, Category, SellingPriceRevision, Barcode/token retired, guard product serta row StockBalance awal 0; fakta ledger/cost selanjutnya milik BU-INV-001.

Pengelolaan master search-first, harga authoritative/versioned dan service search paginated yang aman per role. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Katalog/category/base unit/brand opsional/harga/minimum/active; pembuatan produk selalu stok 0; precision unit tidak berubah setelah movement pertama.
- Search exact barcode lalu SKU/name/category/brand, default 25/maksimal 100 dengan urutan name/ID stabil; review similar candidates; collision exact SKU/barcode ditolak tegas.
- Token string mempertahankan leading zeros/case/punctuation, check digit yang dinyatakan, namespace BK-P yang dicadangkan/generator code dengan retry unik/token retired tidak dipakai ulang; DTO operasional aman bagi tiga role.
- Sediakan schema balance awal 0 yang kompatibel dengan kontrak numeric/guard INV sebelum product create; test consumer movement pertama dilengkapi BU-INV-001 dan verifikasi final.

Risiko: Search tidak mengambil seluruh master ke browser; tanpa gambar/import/warehouse.

## VERIFY

- Collision master/alias concurrent, product create stok 0, guard unit-lock yang sesuai; race movement pertama pada final bersama consumer INV, revision harga authoritative, precision/overflow, write role terlarang dan tidak ada field privat.
- composer test:postgres; composer test:race; query plan/pagination representatif; consumer UI setelah BU-UI-002/003.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.