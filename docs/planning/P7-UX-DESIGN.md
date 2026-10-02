# P7 — UX, information architecture dan design system

[DESIGN](../../DESIGN.md) menetapkan boundary kontrak visual; [01](../01-PRD.md)/[04](../04-AUTH-RBAC-SECURITY.md) adalah otoritas produk. Planning membekukan aturan IA/interaction/a11y, **tanpa mengarang token visual yang disetujui owner**. BU-DESIGN-001 tetap harus menyelesaikan exact revision/rights/owner approval sebelum unit UI native.

## Information architecture per role/route

| Role | Workspace V1 |
| --- | --- |
| Pemilik | Katalog/inventori/opening/correction, POS/session sendiri, finance/session audit, pengguna/config/profile/business/register/status sistem |
| Admin Operasional | Katalog/search/barcode, receipt/manual issue, stock/LOW/OUT/history aman, akun sendiri; tanpa POS/session/akses baca biaya |
| Kasir | Session sendiri/open/close, search/scan/cart/pembayaran penuh/receipt/history Sale sendiri/report, akun sendiri; tanpa pengelolaan master |

Tidak menampilkan menu Later atau kontrol dummy. Setiap route/action mempunyai capability backend nyata dan policy untuk request langsung. BantuKasir bukan default brand client; identitas BusinessProfile runtime dipakai pada shell/dokumen dengan accent bounded.

## Kontrak interaksi operasional

Navigasi desktop stabil; logo melakukan toggle expanded/icon rail dan klik di luar tidak menutup sidebar. Drawer mobile mempunyai backdrop/dismiss di luar/Escape, focus trap/restoration, pengelompokan terbaca dan navigasi yang terjangkau. Bottom navigation hanya untuk tujuan role-safe yang sering digunakan, tanpa overlay duplikat yang bertentangan. Popover akun berada di atas footer dan di dalam batas shell, tanpa menggeser sidebar; profile/password/account sendiri lengkap serta business/users/config Pemilik mengikuti permission.

Topbar padat memakai search/utilities bermanfaat/filter waktu/aksi laporan hanya untuk capability yang benar. Pattern notification memuat perhatian kritis aktual/LOW-OUT dan recent activity terbatas dari data sesuai scope, bukan capability messaging/outbox. Tinggi panel bounded dan padat dengan scroll internal; tidak berupa overlay besar. Kontrol tanggal benar-benar mendukung harian/7 hari/bulan/custom, Apply/Cancel dan validasi koheren, dengan DD MM YYYY; waktu rinci hh:mm:ss Jakarta. Laporan memakai line trend dan nilai yang accessible, asOf/cost completeness, serta konteks/delta KPI dari fakta aktual.

Kasir berfokus pada Scan/Cari → cart → satu pembayaran penuh → receipt. Kontrol +/-/remove/undo ergonomis, quantity/stock cap presisi, serta tindakan cart/payment mobile sticky dan terjangkau; cart panjang maupun state keyboard tidak terpotong. Scan dijeda pada form/dialog/count; Enter tidak confirm. NO_SESSION/opening float 0, identitas/tanggung jawab session OPEN dan original recovery UNCERTAIN jelas. Pemilik lebih kaya informasi dengan keluarga visual yang sama; Operations membedakan receiving/manual issue dan biaya write-only dengan jelas; opening tetap khusus Pemilik.

Peran semantic typography/surface/color/spacing/radius/border/elevation/motion disimpan dari **sumber approved**. Visual premium, tenang, proporsional dan operasional, dengan accent terkendali serta komposisi warna yang hidup. Gunakan React/Inertia/shadcn native, tanpa iframe/raw prototype CSS/modules. Fixture sumber/leads/team/targets bukan scope produk.

## Acceptance visual

Uji 375/440/768/1024/1440, zoom 200%, keyboard/touch 44 px/focus/contrast/reduced motion; loading/empty/error/denied/stale/UNKNOWN/success/disabled; teks/cart/table/form panjang dan input failure. Context teramati dan source readiness bukan UI visual PASS. Candidate approved yang tepat, parity hasil render native dan adaptasi eksplisit harus tercatat. [08](../08-TESTING-ACCEPTANCE.md) UX01 final setelah consumer workflow nyata tersedia; disiplin review/freeze [P0](P0-GOVERNANCE.md) tetap berlaku.