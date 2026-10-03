# Kontrak visual — P7 actual design V1

**Status: DRAFT kandidat P7 / WAITING OWNER VISUAL REVIEW; OWNER VISUAL APPROVAL belum tercatat; `approvedRevision = null`.** Kandidat aktual sudah dirender dan diperiksa secara bounded; ini belum P7 COMPLETE atau full accessibility PASS. Dokumen repository ini akan menjadi kontrak produksi setelah seluruh exit P7 terpenuhi. P8–P11 belum frozen final. [Workflow P7](docs/planning/P7-UX-DESIGN.md), [coverage](docs/planning/P7-COVERAGE.md) dan [evidence](docs/evidence/p7-owner-r1/REVIEW.md) menetapkan sumber, hasil dan holds.

## Sumber, provenance dan boundary

| Item | Fakta |
| --- | --- |
| Tool | CLI global `dpai 0.2.2`; skill DesainPakeAI tersedia; setup tidak diulang |
| Project | **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef** |
| Prototype/runtime | `c8384f74-cc1b-4335-a385-2d0fac66d668` / `single-html@1` |
| Foundation kandidat | Provenance Thread / alpha.1, hanya dari project di atas |
| Revision intake | `sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241` |
| Artefak kandidat aktual | Page `p7-v1`, route `/p7-v1`, source `src/pages/p7-v1.page.html`; tiga role dan state reviewer |
| Revision kandidat desain aktual | **`sha256-bef01d649ea91b2b`**; preview compiler PASS, browser checks bounded pada [evidence](docs/evidence/p7-owner-r1/REVIEW.md) |
| Revision approved | **null — menunggu approval eksplisit owner** |
| Rights/template/font/aset | PENDING verifikasi entitlement/lisensi; tidak ada klaim izin dari keberadaan token saja |

Input frozen P1–P6 menentukan bisnis. Delapan halaman template CRM/leads/target/tim sumber dipreservasi sebagai sejarah; bukan V1 dan bukan default review kandidat. Kandidat V1 harus dibuka pada artefak P7 yang dicatat evidence. Tidak mengakses/memutasi MULTIPLECORP, memakai aset/token/inspirasinya, memakai MCP, 21st.dev atau Impeccable.

Sebelum context dan **setiap mutation**, cocokkan `dpai project current --pretty` nama/id exact; mismatch STOP. Ambil context revision dan guard mutation terhadap revision aktual. Jangan simpan key/credential dalam repository/evidence/screenshot. Desain dibuat/refine di DPAI, direview/diiterasi owner, approved exact revision dicatat, baru kontrak ini dinormalisasi/frozen. **BU-DESIGN-001 sepenuhnya diserap P7**; tidak ada desain yang ditunda sebagai unit coding.

Pipeline frozen kelak: **P7 DesainPakeAI owner-approved exact revision → repository DESIGN/semantic tokens → shadcn/ui → Laravel/Inertia React/TypeScript native**. Jangan ship prototype HTML, iframe, raw scoped styles, bridge attributes atau fake runtime auth sebagai aplikasi. Native route/component mapping berikut adalah kontrak kandidat untuk consumer, bukan kode yang sudah dibuat.

## Identitas dan semantic token kandidat

BantuKasir codename internal; client name/logo/contact/accent dari BusinessProfile runtime versioned. Fixture netral ATK non-production boleh. Tidak memakai logo codename/aset LATANSA sebagai default client. Original receipt/session document membekukan identity/template/assets saat commit; reprint mempertahankan snapshot.

| Semantic role repository | Nilai kandidat / source role | Penggunaan |
| --- | --- | --- |
| page/background | `#fefdfb` | Canvas hangat, tenang |
| card/surface | `#ffffff` | Panel operasional/overlay |
| muted/inset | `#faf8f5` | Section band/form/subtle depth |
| foreground | `#121715` | Teks utama |
| muted-foreground | `#5e6965` | Label sekunder yang tetap terbaca |
| primary / on-primary | `#08090a` / `#ffffff` | Aksi utama gelap; recipe hover aktual `#27312d`, berbeda dari foundation `#1b211e` |
| brand / active / accent ink | Foundation `#0c8c5e` / `#e8f7f1` / `#006b49` | Accent recipe terbatas; teks penting memakai ink `#006b49` |
| focus-ring | Recipe `#138464`, outline3px | Fokus keyboard kandidat; token foundation tidak diasumsikan parity |
| border / strong / control-border | `#ebe9e6` / `#cfcdca` / `#818b86` | Dua nilai source untuk divider/depth; control-border kandidat untuk batas kontrol bermakna≥3:1, diverifikasi saat render |
| info / surface | `#285d86` / `#eef5fb` | Recipe yang dirender pada kandidat P7; teks/status informasi |
| success / surface | `#006b49` / `#e8f7f1` | Recipe yang dirender; confirmed/committed |
| warning / surface | `#875418` / `#fff5e4` | Recipe yang dirender; LOW/stale/incomplete |
| destructive / surface | `#aa3039` / `#fff0f0` | Recipe yang dirender; OUT/rejected/destructive review |
| chart | dark ink + brand ink, solid/dashed | Penjualan dan Laba Kotor berlabel/accessible table; owner saja |

Tabel membedakan foundation dengan recipe scoped yang benar-benar dirender pada `src/pages/p7-v1.page.html`. Token foundation info `#1878b8/#eaf5fc` dipreservasi; ink baru `#125c8a` tersedia tetapi recipe informasi kandidat memakai `#285d86/#eef5fb`. Control-border `#818b86` adalah tambahan foundation untuk kontrol, bukan divider. Nilai foundation yang belum dipakai tidak boleh dianggap parity kandidat. Mapping ke CSS variables/shadcn/Oklch dilakukan tanpa mengubah peran/state/contrast approved. Vivid mint/faint source tidak dipakai sebagai teks penting. Runtime accent hanya preset yang pasangan contrast/state-nya tervalidasi, bukan hex bebas. Status selalu label/icon/structure juga, bukan warna saja. Private HPP/acquisition cost/profit tidak masuk DTO/card staff.

## Typography, spacing dan surfaces

Kandidat yang dirender memakai **Inter** untuk body serta angka/ID dengan tabular numeric; `.p7-mono` tidak memuat font monospace tersendiri. **Libre Baskerville** terbatas pada cerita entry/auth. Geist Mono hanya tercantum pada foundation dan belum menjadi font kandidat; tidak dibekukan sebagai parity. Loader/fontfile/license dan entitlement harus dibuktikan sebelum freeze/self-host production. Fallback system sans/serif menjaga kegunaan ketika font gagal. Tidak mengunci Source Sans 3 dari UI historis.

| Peran | Recipe kandidat yang dirender |
| --- | --- |
| Page heading | 28px/600/line1.25; mobile25px. Auth25px desktop/24px mobile |
| Section/dialog heading | Section16px/600/line1.4; panel mobile14px. Dialog20px desktop/19px mobile |
| Body/control | Base14px/line1.5; compact form/action12px, sebagian mobile11px; label tetap eksplisit |
| Metadata | 9–12px menurut konteks; caption12px. Keterbacaan ukuran kecil dan zoom200% masih hold review/validasi, bukan AA PASS |
| KPI/total | KPI25px desktop,22px tablet,21px mobile dan19px di bawah400px; total cart berbeda sesuai emphasis |
| Document/command identity | Inter dengan tabular numeric, bukan monospace; wrap/scroll sesuai komposisi |
| Auth story | Libre Baskerville35px/400/line1.5 desktop,29px tablet; story disembunyikan pada mobile |

Scale dasar4/8/12/16/20/24/32/48px dengan penyesuaian recipe. Page gutter32px desktop,24px pada composition tablet,16px mobile; panel body20px desktop/16px mobile; header18×20px desktop/16px mobile. Panel radius13px desktop/11px mobile, control8px, product card11px, account10px/menu12px, dialog15px desktop/12px mobile. Panel shadow `0 2px 4px #2d332902`, account overlay `0 10px 32px #15201b13`, dialog `0 20px 80px #06170f29`; border/divider membentuk hierarchy. Hover160ms, lift restrained pada item yang dapat dipilih; reduced-motion CSS meniadakan transisi. Nilai ini mencatat kandidat aktual, belum approved freeze.

## Pattern dan states

| Pattern | Kontrak kandidat |
| --- | --- |
| Shell | Desktop sidebar232px/icon rail72px; logo kecil toggle; outside click tetap terbuka. Mobile drawer264px dengan backdrop/Escape/focus trap-return; workspace inert saat terbuka, sidebar inert saat tertutup. Bottom nav role-safe; safe-area/cart bar tidak menutup action |
| Header | Topbar padat; judul/aksi utama satu; search per konteks, notification/relevant controls. Tanpa label role ganda/sidebar icon redundant |
| KPI/panel/table | Nilai + konteks/delta fixture berperiode jelas; grid proportional, numeric aligned. Narrow record layout atau intentional internal scroll berlabel; tidak body overflow |
| Form/button | Primary tunggal, secondary/destructive berbeda; label/helper/error dekat field, summary/focus, draft/review/confirm. Stock berubah hanya explicit confirm, bukan create/search/scan |
| Account/notification | Account popover di atas footer dalam bounds tanpa sidebar bergeser; rail memakai ikon compact dengan accessible name/title, expanded/mobile memakai label lengkap. Owner account/config lebih lengkap. Notification compact critical dulu/recent berikut, max-height/internal scroll; bukan inbox engine |
| Period/chart | Preset/custom draft terpisah applied; tombol tanggal Indonesia membuka calendar, Apply/Cancel dan validasi≤366hari/future/ordering. Owner line trend/table dari satu dataset; 14 titik hover/click/focus/Enter/Space, tooltip tanggal+Penjualan/Laba Kotor, Escape/dismiss memulihkan fokus. Overlay tidak memblokir titik; mobile tujuh kontrol tanggal44px. UNKNOWN/incomplete tidak menjadi profit0 |
| Foto barang | Dua foto contoh kategori ATK nyata beratribusi; thumbnail katalog/list/search/POS/cart, foto utama detail/edit. object-fit:contain, alt, ukuran stabil; Foto belum tersedia / Foto gagal dimuat tanpa menutup nama/SKU/barcode/stok. Foto opsional dan bukan identitas SKU client. Visual fixture, tanpa uploader/storage/API; upload produk tetap Later, perlu scope media produksi terpisah |
| POS | Scan/search hanya draft; plus/scan satu base unit, precision edit eksplisit, qty +/-/remove/undo44×44. Mobile cart bar48px men-scroll/fokus ke checkout, bukan membayar. Footer cart mengikuti flow pada≤950px agar tidak menutup quantity. Satu full CASH atau BANK_TRANSFER; Enter bukan confirm |
| CashSession | NO_SESSION/OPEN/CLOSED, register/responsible cashier/nomor/waktu/status; blank opening invalid, Rp0 eksplisit valid; preview/count/recount, immutable CLOSED; no reopen |
| Stock/correction | Pemilik memiliki menu Barang keluar eksplisit menuju manual issue; Operations tetap memiliki jalur, Kasir tidak. Receiving/opening/manual issue berbeda; reason non-sale wajib. Current before/delta/after, original immutable; reversal full sekali tanpa field qty/count bebas. Refund money/physical return bounds terpisah |
| Sensitive/uncertain | Biaya incoming Operations write-only, no nominal echo/persistence; owner finance only. UNCERTAIN original intent/status recovery, tanpa replacement/bayar ulang; UNKNOWN biaya terpisah |
| Feedback | Loading/skeleton; empty dengan next valid; error retry; denied; stale/freshness; UNKNOWN/incomplete; submitting/disabled; definite rejected; uncertain/recovery; success dengan original reference |

## Responsive dan accessibility

Target375/440/768/1024/1440px, short landscape, zoom200% dan long content/cart/form. Lima lebar shell tiga role diuji pada kandidat b51; revisi empat catatan diuji pada preset SaaS desktop1440/tablet820/mobile390, bukan rerun matriks lima lebar seluruh state; short landscape/account diuji pada revision sebelumnya dengan recipe yang tidak berubah. Desktop split POS/cart, tablet adaptable, mobile satu alur dengan bottom navigation dan cart bar. Actual browser zoom200%, software keyboard perangkat nyata, screen reader serta audit AA seluruh rendered states masih NOT VERIFIED; lihat coverage/evidence. Target44×44 berlaku pada action operasional, bukan klaim semua review-only demo controls memenuhi target.

Target WCAG AA4.5:1 teks normal/3:1 teks besar dan elemen UI bermakna. Sepuluh token pairs terhitung PASS; ini bukan full rendered AA PASS. Semantic heading/landmark/label, status live, error association, focus-visible, keyboard tab order, safe submit/Escape/trap/restore dan reduced motion harus diverifikasi kembali pada native consumer. Data chart tersedia tabel. UI Bahasa Indonesia; IDR; tanggal 01 Oktober 2026 dengan nama bulan lengkap; tanggal+waktu 01 Oktober 2026, 09.15.00 (hh.mm.ss); Asia/Jakarta. ISO hanya nilai internal, bukan label tanggal. Tanggal fixture bukan waktu runtime authoritative.

Sandbox preview DesainPakeAI memakai `allow-scripts` tanpa `allow-forms`; kandidat memakai bridge tombol review berbasis `reportValidity`/FormData. Bridge22form dan state reviewer hanya fasilitas authoring P7. Implementasi native memakai form React/Inertia/server validation yang sebenarnya, tanpa menyalin bridge atau fixture auth.

## Route/component mapping native kandidat

Path berikut logical route contract kandidat; endpoint command/policy tetap canonical P6/backend. Tidak mengotorisasi create route/code pada task ini.

| Route / consumer | Komponen shadcn/ui / komposisi repository |
| --- | --- |
| `/login`, `/password/*`, `/invite/*`, `/account` — UI001/IAM | Card, Form/Label/Input, Button, Alert, Dialog; server-auth account/recent-auth |
| `/` role workspace — UI001 | Sidebar/Sheet, DropdownMenu, navigation/bottom bar, Breadcrumb, Badge, Tooltip; role DTO |
| `/products`, `/products/:id`, `/products/new`, `/products/:id/edit` — UI002/CAT | Table/Card, thumbnail/fallback gambar opsional, Input/search, Select/Combobox, Pagination, Form, Dialog collision/review |
| `/inventory/receiving`, `/inventory/opening`, `/inventory/issues` — UI002/INV | Search-first Form, Tabs, safe review Dialog, Summary, Alert; owner opening/ops write-only |
| `/inventory/stock`, `/inventory/history` — UI002/READ | Table/mobile records, Badge LOW/OUT, filters, Pagination, freshness/unknown Alert |
| `/pos`, `/cash-sessions/open`, `/cash-sessions/:id/close` — UI003/CASH/SALE | Product grid/search, cart rows, qty controls, Sheet/mobile cart, Dialog review/count/recovery, sticky Button |
| `/sales`, `/sales/:id`, `/cash-sessions/:id` — UI003/READ/DOC | Scoped Table/detail, immutable summary/snapshot, receipt Card, reprint Dialog |
| `/owner/corrections`, `/owner/refunds`, `/owner/returns` — UI004/INV/SALE | Source lookup/recent-auth Dialog, reason Form, before/delta/after, full-reversal/replay/UNKNOWN Alert |
| `/owner/reports/*`, `/owner/audit` — UI004/READ | Line Chart + accessible Table, period Popover/Calendar/Form, Tabs, completeness/freshness, scoped exports |
| `/owner/business`, `/owner/settings`, `/owner/system` — UI001/UI004/CFG/OPS | Form/preset Select, bounded logo input, Table/Dialog guard, read-only actual/unverified status; no unauthorized restore/reset |
| `/owner/users` — UI001/IAM | User Table/search, invite/reset/disable/revoke Dialog, actor/role guard/recent-auth; historical identity dipertahankan |
| `/owner/registers` — UI004/CASH001 | Register Table/Form, code/label/active dan OPEN-session edit guard; bukan warehouse/location |
| Label/thermal58/80/A4/sessionPDFCSV — DOC001 | Repository document templates/preview, immutable identity, printer error/retry original |

P7 approval membuktikan sumber desain yang dipilih, bukan consumer runtime FINAL PASS. UI001–004/DOC001 mengimplementasikan kontrak approved dalam React/Inertia; VERIFY002 menguji UX01/IDENTITY01/privacy terintegrasi, VERIFY003 perangkat nyata. Native parity/adaptasi dicatat pada revision tepat. P8–P11 refinalisasi setelah P7 COMPLETE; source/rights approval masih required.
