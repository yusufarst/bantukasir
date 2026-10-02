# Kontrak visual — Baseline P0–P11

**Status: kandidat sumber teramati; approval visual owner belum tercatat.** Dokumen ini satu-satunya otoritas pipeline/token/pattern produksi. Nilai final dibekukan di BU-DESIGN-001 setelah owner menyetujui revision sumber yang tepat; jangan mengklaim token/visual lama sebagai approved produksi baru.

## Sumber dan provenance

| Item | Fakta terverifikasi ulang 2 Oktober 2026 |
| --- | --- |
| CLI global | `dpai 0.2.2`; gunakan `dpai`, bukan MCP |
| Project | BantuKasir Design |
| Project ID | `eddb0e15-9909-44b3-8517-9239cadc65ef` |
| Foundation | Provenance Thread / alpha.1 |
| Context revision teramati | `sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241` |
| Prototype sumber | `c8384f74-cc1b-4335-a385-2d0fac66d668`, `single-html@1` |
| Context | 32 color tokens, 10 typography tokens, 13 sections, 8 template pages |
| Approval / rights | Approval visual owner dan entitlement/lisensi template/font/aset belum diverifikasi |

Halaman dashboard/transaksi/leads/target/laporan/tim/pengaturan/masuk adalah template visual. Leads, sales targets atau tim bukan capability produk Core. Adaptasi mengikuti role dan [01](docs/01-PRD.md), bukan copy perilaku template. Revision context adalah hash data teramati; bukan approval atau hasil parity browser.

## Workflow CLI dan batas akses

Skill DesainPakeAI terpasang global di `C:/Users/Yusuf Setiawan/.agents/skills/desainpakeai/SKILL.md`; baca ketika menggunakannya. Setup/login user sudah tersedia; jangan install/login ulang atau menyimpan key ke source/docs/log/screenshot. Metadata setup lokal di `.local/desainpakeai/setup.json` sengaja Git-ignored.

1. Cek `dpai --version` dan `dpai project current --pretty`. Cocokkan nama **dan** ID di atas; mismatch berarti STOP sebelum context atau operasi berikutnya.
2. Ambil `dpai context --pretty`, catat revision aktual dan panduan authenticated yang relevan melalui CLI. Jangan mengasumsikan export/compile/preview setara validasi layout.
3. BU-DESIGN-001 mencatat foundation/revision yang dipilih owner, approval visual yang eksplisit, lisensi/entitlement dan mapping sumber ke semantic tokens. Jika belum disetujui, simpan kandidat dan WAITING OWNER REVIEW; UI produksi menunggu.
4. Terjemahkan struktur yang disetujui langsung menjadi komponen native React/Inertia/shadcn; backend DB/tests tetap berjalan terpisah. Tidak ada iframe export, import workspace prototype atau beberapa aplikasi prototype pengganti.
5. Sebelum **setiap mutation DesainPakeAI**, verifikasi lagi project tepat, scope otorisasi dan revision. Sesi rebaseline hanya membaca project/context, tanpa mutation/export.

**MULTIPLECORP dilarang disentuh**: jangan pilih/baca/ekspor/mutasi, gunakan aset/token/inspirasi atau fallback. Tidak ada MCP DesainPakeAI. 21st.dev dan Impeccable sudah keluar dari workflow; evidence historisnya tidak menjadi gate atau dependency.

## Identitas dan semantic tokens

BantuKasir hanya codename. Nama/logo repository maupun aset LATANSA historis bukan visual authority/identitas default produksi. Login/shell/profile/dokumen memakai BusinessProfile runtime; fixture netral non-production boleh. Logo resmi/izin pakai dari owner diperlukan sebelum pilot. Snapshot dokumen memakai revision identitas/template/aset saat transaksi.

BU-DESIGN-001 membekukan mapping sumber → token semantic repository → CSS variables/shadcn. Catat nilai, peran, state, font/license, revision, contrast serta alasan adaptasi. Minimal:

- background/page, surface/panel/inset/overlay; foreground utama/muted/inverse;
- brand/accent yang dibatasi; primary/secondary/destructive; success/warning/danger/info dengan foreground pasangan;
- border/divider/focus-ring; spacing scale/density; type hierarchy/font weights/line-height; radius; elevation; motion/duration;
- breakpoint/layout/sidebar/drawer/navigation; chart series yang jelas dengan label/pattern, tanpa hanya mengandalkan warna.

Jangan mengunci Source Sans 3, warna hijau, old radius atau logo BantuKasir sebagai requirement visual baru. Font final mengikuti sumber approved/lisensi, di-host lokal bila haknya memungkinkan. Accent runtime hanya preset yang contrast/state-nya tervalidasi; bukan hex bebas yang merusak readability. Jangan menyalin semua raw template token tanpa peran, atau mengaktifkan margin/profit untuk staff melalui shared card.

## Pattern produksi yang wajib

| Pattern | Kontrak fungsional/visual |
| --- | --- |
| Shell / navigasi | Desktop proportional; mobile drawer dengan outside-dismiss/Escape dan focus-return. Collapse/rail bila sumber approved memakainya tidak menggeser account overlay atau kehilangan draft. Mobile navigation punya tujuan jelas dan tidak menutup tindakan utama |
| Topbar / page header | Tinggi padat, judul dan aksi utama jelas; search satu per konteks. Utilities relevan saja, tanpa label role/ikon sidebar ganda atau tombol mati |
| KPI / cards | Label, nilai dan secondary status/delta dengan periode/dasar nyata; bukan filler atau fabricated trend. Finance hanya owner; UNKNOWN tidak menjadi 0 |
| Table / list | Hierarki kolom operasional, stable pagination server, alignment numeric, aksi reachable; narrow viewport memakai record layout atau scroll panel yang disengaja |
| Form / buttons | Primary action tunggal per konteks, secondary/destructive jelas; validasi dekat field, error summary/focus, dirty draft/review/confirm. Tidak ada stock mutation dari search/create/scan |
| Overlay / account | Bounded dalam viewport/shell, internal scroll bila perlu, anchor konsisten, role-appropriate account/settings. Tidak menyebabkan scroll/width sidebar meloncat |
| Perhatian / notifications | Current LOW/OUT priority-first dan recent activity relevan; compact bounded panel. Tidak menciptakan engine delivery/inbox |
| Periode / chart | Preset dan Custom Range draft terpisah dari Applied; endpoint/range validation, Apply/Cancel, keyboard/cross-month/year. Trend owner line chart bila data memadai; empty/error/incomplete jujur |
| POS / cart | Fokus Scan/Search → cart → Bayar → receipt; minus/plus/remove jelas, jumlah/harga readable, draft preserved, total/action mobile tidak terpotong atau tertutup nav |
| CashSession | NO_SESSION / OPEN / CLOSED, drawer/cashier/nomor/waktu/status jelas; opening 0 valid; count/recount/uncertain states berbeda; snapshot historical immutable |
| System / history | Status aktual/freshness/error; original dan linked corrections dengan before/delta/after current stock; tidak menyiratkan history bisa diedit |

Semua consumer mempunyai loading, empty, error, denied, stale, UNKNOWN/incomplete, submitting/disabled, definite rejected dan uncertain/recovery bila relevan. Uncertain membekukan identitas command; UI tidak menggantinya dengan transaksi baru. No private cost echo di receiving Operations. Reversal tidak menawarkan editable qty/count yang tidak relevan.

## Responsiveness, accessibility dan quality gate

Uji browser pada **375, 440, 768, 1024, 1440 px**, short landscape dan cart/form panjang. Target touch tindakan operasional minimal 44×44 px, termasuk calendar/qty/close; label/totals tidak clipped. Zoom 200%, tab order, keyboard submit yang aman, Escape, focus trap/return, screen-reader labels dan reduced motion diuji setelah komposisi shadcn, bukan diasumsikan dari library.

Contrast minimal WCAG AA: 4.5:1 teks biasa, 3:1 teks besar dan elemen UI bermakna. Status bukan warna saja. Microinteraction restrained dan membantu feedback; tanpa animasi menghambat scan/payment/count. Tidak ada body overflow horizontal; intentional table scroll diberi affordance.

Tulisan UI Bahasa Indonesia; amount IDR, tanggal lokal **DD MM YYYY** seperti **23 09 2026**, waktu rinci **hh:mm:ss** seperti **14:25:30**, dan timezone Asia/Jakarta. ISO tetap internal. Date/time harus konsisten antar shell/filter/snapshot; fakta waktu tetap berasal dari server.

Approval sumber BU-DESIGN-001 bukan bukti seluruh workflow produksi. BU-UI-001–004 dan BU-DOC-001 menguji consumer masing-masing; BU-VERIFY-002 memverifikasi responsive/a11y/privacy terintegrasi, sedangkan BU-VERIFY-003 memakai perangkat aktual. Catat parity, adaptasi dan risiko dengan revision yang tepat di [08](docs/08-TESTING-ACCEPTANCE.md) dan [11](docs/11-BUILD-PLAN.md). UI historis disimpan sebagai referensi state/fixture saja; jangan impor CSS/logo atau menganggap screenshot sebelumnya sebagai pass produksi.


## P7 blueprint dan current observation

[P7](docs/planning/P7-UX-DESIGN.md) membekukan IA/interaction/a11y constraints; final visual values hanya dari exact source yang dipilih dan disetujui owner. Read-only ulang 2 Oktober2026 melalui dpai0.2.2 mengonfirmasi BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef, prototype c8384f74-cc1b-4335-a385-2d0fac66d668, revision sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241. Ini context observed, bukan approval/rights. Tidak mutation/export/MCP atau akses MULTIPLECORP.

Panduan DesainPakeAI prototype-to-codebase dibaca via dpai guide get --topic prototype-to-codebase: native target writer, semantic mapping, fixture bukan backend contracts, foundations dahulu dan narrow/wide rendered parity dengan gaps Not verified. Sesi ini hanya specification, tidak handoff implementasi atau visual parity verification. Native target sekarang resources/js React/Inertia/shadcn; jangan ship prototype HTML/iframe/bridge attributes.
