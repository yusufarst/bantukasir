# P7 — Evidence koreksi desain sebelum implementasi

Koreksi2–3 Oktober2026, Asia/Jakarta. **WAITING OWNER VISUAL REVIEW; P7 NOT COMPLETE; approvedRevision null.** Intake branch `plan/bantukasir-pos-v1`, HEAD `5e100aaa0ebda5c51fe62f23db5dea7d1d709c7e`;0 staged/0 unstaged/1 untracked. Resume3Oktober mempertahankan45tracked unstaged/0staged dan7untracked sebelum closeout. `plan-p0-p11-review.patch` dipreservasi, SHA256 `a13fe0f2c4f494992ed339292c2c2bcdf7f4fc8ea85606bccd5539204ef7f03e`. Source/aset/evidence85file dan input frozen13file tidak diubah. Tidak menginisialisasi production app/backend/database atau menjalankan BU.

## Keputusan dan pekerjaan nyata

Owner mengoreksi P7 yang sebelumnya berhenti pada UX specification. P7 kini actual design di DesainPakeAI dari frozen P1–P6, review/iterasi/approval visual exact revision dan normalisasi DESIGN; baru P8–P11 refinalisasi/freeze. [P7 workflow](../../planning/P7-UX-DESIGN.md), [coverage18grup](../../planning/P7-COVERAGE.md), [DESIGN](../../../DESIGN.md), [11](../../11-BUILD-PLAN.md) dan D-017 merekam kontrak tersebut.

BU-DESIGN-001 dihapus aktif; semua tanggung jawab desain/approval pertama diserap P7. UI/DOC serta CORE030/034/070 memakai planning provider P7 dengan approvedRevision null. Native implementation/parity dan UX01/IDENTITY01 FINAL tetap di consumer/VERIFY002. 27BU draft/73Core/39suite/0FINAL; P7 desain32h + P8–P11refinalisasi8h + implementation253h=293h; reserve64h/allowance357h. Technical critical128h, planning+path168h lowerbound, waitingowner tidak diketahui. Estimasi bukan jam aktual atau harga layanan.

## DesainPakeAI aktual

- Skill `C:/Users/Yusuf Setiawan/.agents/skills/desainpakeai/SKILL.md` dibaca; CLI sudah tersedia `dpai0.2.2`, tanpa install/login ulang/MCP.
- `dpai project current --pretty` memastikan **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef** sebelum context/export dan mutation. Project lain tidak dibuka/diakses.
- Context intake revision `sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241`, prototype `c8384f74-cc1b-4335-a385-2d0fac66d668`, runtime `single-html@1`, foundation Provenance Thread/alpha.1.8page sales/CRM adalah templatehistory, bukan V1.
- Guidance authenticated yang benar-benar dibaca: workspace-authoring, design-quality, layout-responsive, prototype-interactions, accessibility, color-system, typography, interface-review dan prototype-to-codebase.
- `dpai export --export-output .local/p7-correction/source-before --pretty` berhasil **di server**:552892bytes/8pages; output `/app/workspaces/.../.output/prototype.html`. RemoteCLI tidak mendownload ke path lokal itu. Tidak mengklaim file export tersimpan lokal.
- `dpai preview verify --page dashboard --pretty` compilerPASS, diagnostics0; browser/layout/responsive **not-run**. Ini template intake, bukan candidateV1.
- `dpai file read --path .output/prototype.html --start-line1 --end-line4 --pretty` gagal `FILE_TOO_LARGE`, size552892/max524288. Batas baca CLI, bukan acceptanceFAIL produk; tidak menghapus source untuk mengakalinya.

Artefak final kandidat: **page `p7-v1` / route `/p7-v1` / P7 BantuKasir V1 — kandidat lengkap**, source `src/pages/p7-v1.page.html`, default artboard1440×960. **Exact candidate revision `sha256-b51d2975aa3a8a92`**, dibaca ulang melalui CLI setelah browser QA; workingPageIds kosong adalah marker authoring DPAI, bukan approval/P7 COMPLETE. [Halaman review](https://desainpakeai.com/project/eddb0e15-9909-44b3-8517-9239cadc65ef/prototype) tetap pada preview page P7, bukan template CRM.

Riwayat mutation aktual dengan project guard dan expectedRevision disimpan non-secret pada [BROWSER](BROWSER.json): pagecreate `sha256-d58e8a7064166bec`; infoink `sha256-879fa14789801b98`; controlborder `sha256-fc7ea77ea81c0228`; Overview `sha256-f1e56c4c5b2987a4`; kandidat awal `sha256-e27cf91f777f0147`; auditfix `sha256-f85390de90722ffd`; sandboxform/scanner `sha256-47794a924350b379`; cartdock `sha256-04bb68b0120d9582`; footerflow `sha256-8e506d1cf9a0a4a2`; stocksummary `sha256-4e2e5c7923dcd2a7`; LOWboundary `sha256-6ab493837579cf88`; authcoverage **`sha256-b51d2975aa3a8a92`**. Revision ini adalah workspace revision, bukan hash source lokal atau nilai token warna.

Mutation terbatas pada pageP7 baru, dua token pendukung dan sectionOverview foundation BantuKasir. Delapan page lama dipreservasi sebagai template history dan tidak menjadi V1 authority. Tidak memakai AI generator berbayar, top-up, publish atau project lain. Tidak mengklaim fee/provider usage berdasarkan engineering hours; invoice/entitlement layanan belum diverifikasi.

`dpai preview verify --page p7-v1 --pretty` terakhir: **compile passed;782972bytes,9pages,1runtimeDependency,0diagnostics,0warnings** pada b51. Flags CLI browser/layout/responsive tetap **not-run**; actual CUA checks dicatat terpisah. `dpai design lint --pretty` final b51: validtrue/0errors/**39warnings** (20unsupported component border sub-tokens,19orphaned foundation tokens). Warning bukan compiler failure dan belum ditandai fixed; actual scoped recipe berbeda dari metadata foundation dan dijelaskan pada DESIGN.

Percobaan `work finish` awal mendapat penolakan automatic approval review karena preview/browser prerequisite belum terpenuhi. Tidak dibypass: dilakukan rendered review dan fixes; percobaan sesudah evidence mengembalikan alreadyFinishedPageIds[p7-v1], workingPageIds[]. Context final b51 juga workingPageIds[]. **Marker tersebut tidak menutup P7, rights atau owner gate.** Tidak diperlukan mutation ulang hanya untuk mengubah label status.

Official browser export memakai **Halaman ini**, dengan pageP7 dipilih1dari9; source download final730792bytes, SHA256 `b6b52472caef5b2de05af5ef9eca418fd3eb5022ba06b4e52fa20c47ad96405c`. Source authoring lokal yang diupload mempunyai SHA256 `147c79b6941ac42c85c251e24976349928f51d2b8f5db580f66138d00f2d5dab`. Perbedaan bytes/hash adalah wrapper runtime export, bukan mutation alternatif. Export itu diuji tanpa perubahan pada server loopback sementara, untuk viewport/AX/browser; source/export/helpers tetap ignored `.local/`, tidak menjadi production frontend atau deliverable code.

## Findings yang diperbaiki dan recheck

| Finding / risiko | Remediation aktual | Hasil dan batas |
| --- | --- | --- |
| HIGH — Rp0 diganti fallback report lama | Nullish handling explicit0; counter/session identity monotonic, closed snapshots retained | Final b51 browser: KasirSK-CONTOH-13 opening0/count0→CLOSED expected0/physical0/variance0; bukan120000/118000 fallback. Earlier477 retained13/14/15. Bukan race/snapshot PostgreSQL proof |
| HIGH — freeze opening tidak konsisten dengan posting normal | Defaultfreeze false, ownerexplicitfreeze/release; receiving/issue/Sale guard, original recovery tetap aman | Final b51 freeze receiving dan checkout ditolak; earlier8e issuefreeze rejected/no movement. Count0 opening checklist bukan movement;101row reject. Native atomic100rows/reconciliation belum terbukti |
| HIGH — focus jatuh ke drawer tersembunyi | Sidebar inert saat tertutup, workspace inert saat terbuka; trap, Escape/backdrop/logo dismiss, focusreturn ke mobile toggle | Final b51 nativeAX375:logo→ShiftTabaccount→Tablogo→EscapeBukanavigasi; closed sidebar hilang dari AX. Backdrop/logo dismiss juga teruji6ab. Tidak full screen-reader audit |
| HIGH — form tidak berjalan dalam preview SaaS | `allow-scripts` tanpa `allow-forms`:22form memakai review-button bridge + native validity; scanner Enter terpisah dari confirm | Final b51 matching resetfixture berhasil di SaaS sandbox; broaderforms tested earlier477/8e. Tidak melonggarkan sandbox/auth; bridge tidak diport sebagai produksi |
| HIGH — cart mobile menutup quantity/action | Cartdock48px di atas bottomnav, reveal scroll/focus checkout; footerstatic≤950px; qty±/remove44×44 | Final b51 375 actual controls reachable, body360/client360; dock bukan payment/commit. Software keyboard/long cart penuh belum terverifikasi |
| MEDIUM — LOW boundary dan stock summaries static | LOW0<stock≤minimum, OUT0, min0tanpaLOW; KPI/restockattention/notification membaca currentfixture |6ab receivingPulpen4→8/min8 tetapMenipis, history/KPI/notification8; final source reviewed. Finance cards jelas static/distinct periodexamples |
| HIGH — coverage auth token success/failure tidak lengkap | Newpassword+confirmation/min12;6invalid/expired/reused reset/invite uniformfailure, reset/requestnextsafe | Final b51 browser mismatchfocusconfirmation, matchingclearsfields;375nooverflow. Tidak mengubah credential atau mengirim email sungguhan |
| MEDIUM — stock correction ambigu/mutable-history impression | Currentbefore/delta/after dan originalsource immutable; reversal exact full tanpa arbitraryquantity/count |6ab dialogMOV0042 current24/inverse−10/after14, refundmoney16000/returnquantity2 terpisah. Costrevision/replay/incident ops review-only, bukan backend implementation |
| MEDIUM — source/document/auth/config completeness | Frozen receiptline name/SKU/unit/BusinessProfile; safeaccount/profile/password/users actions; enabledpayment/config guards; search-firstreceiving; duplicate-safecatalog | Source18groups present; domain reviewer currentb51PASS; staticrenderer41PASS. Tidak mengklaim semua subsections telah diposting atau seluruh role/privacychannel tested |

## Browser, responsive dan visual evidence

Primary memakai CUA Chrome untuk actual SaaS preview sandbox serta official export loopback. **Final b51 shell3role×375/440/768/1024/1440:15checks, tanpa body horizontal overflow**. Kasir NO_SESSION ikut matrix; final375 OPENcart dan0close diperiksa terpisah. Body/client widths sama; viewport minus15px pada sebagian layout adalah vertical scrollbar, bukan overflow. [BROWSER](BROWSER.json) menyimpan hasil ukuran, source/hash/revision, bounded checks14kelompok, compiler/contrast dan holds.

Scope earlier revision yang tidak diubah authpatch diberi revision asal:8e Operations receiving noecho/invalidnegativecost/mandatoryissue/catalogduplicate/master0, fullpayment/CASHchange/receipt, periodApplyCancel/notification;6abLOWcurrentrefresh/reversal/refundbounds/account/shortlandscape/global13states. Tidak mempromosikan hasil tersebut sebagai semua checks diulang pada b51. Peralihan role membersihkan private draft/input/tampilan dalam fixture; source/demo role selector bukan backend privacy control.

- [SaaS P7 dengan konteks project](desainpakeai-review.jpg): finalb51, previewdesktop/owner selected; template history terlihat hanya di daftar page.
- [Pemilik1440](owner-dashboard-1440.jpg): finalb51, line trend/UNKNOWN/KPI/current attention/shell.
- [Kasir375cart](mobile-cart-375.jpg): finalb51, quantity/action/footer/cartdock/bottomnav.
- [Reset password375](auth-reset-375.jpg): finalb51, newpasswordform dan safe review state control.

Account6ab375bounds:sidebar0..264/menu16..247.2,y462.4..752 di atas account764..826. Shortlandscape667×375 menu39.325..283.2 dengan internaloverflowauto/account295.2..357.2. Desktop232→rail72; outside/account click tidak membuka/menggeser sidebar. Notification8e max328×368.4/internal scroll. Final SaaS tab error logs0 saat pemeriksaan terakhir; ini bukan telemetry aplikasi produksi.

Sepuluh pasangan semantic token dihitung memakai WCAGsRGB: PASS teks≥4.5/UImeaningful≥3. Border3.517,focus4.654. Bukti perhitungan bukan full rendered AA; foundation/orphaned token tidak diasumsikan recipe parity. ActualfontInter/tabularnumeric dan LibreBaskervilleauthstory/spacing/surfaces direkonsiliasi ke DESIGN berdasarkan source, bukan typography baseline historis.

## Holds dan langkah Owner

**Owner visual approval exact `sha256-b51d2975aa3a8a92` masih diperlukan**, mencakup tiga workspace, mobile navigation/cart, notification/account, form/modal/date/denied/recovery/document pattern, typography/density dan warna. Izin authoring/review ini bukan approval. Jika owner meminta revisi, ulangi targetedauthoring/checks dan binding exactrevision.

Rights/template/font/aset/entitlement/self-host/native derivative masih **PENDING**. **Actual zoom200% NOT VERIFIED:** percobaan keyboard tidak mengubah innerWidth/devicePixelRatio; viewport resize bukan bukti zoom. Full rendered contrast/AA, screenreader/reduced-motion render, physical softwarekeyboard/longcontent failure coverage dan39foundationwarnings tetap dicatat sebagai holds, tidak diberikan PASS fiktif. P7COMPLETE memerlukan penutupan exit tersebut, rights dan normalisasi approvedcontract.

G1–G4OPEN, devices/targetpending, executormodelavailabilitynotverified, releaseunauthorized,39suite0FINALPASS tetap terpisah. Fixture tidak membuktikan auth/serverRBAC/DTOprivacy, PostgreSQLconstraints/rollback/races, bankpayment, physicalprint/scan, restore/benchmark atau productionacceptance. **P8–P11 tetap REOPENED_DRAFT/PENDING_P7_APPROVAL**, seluruh27BU NOTSTARTED; exactfirst **BU-FOUND-0018h(I4/V3/E1)** setelah globalgate danexplicitExecute, tidak dijalankan.

## Review dan validasi

Primary memperbaiki workflowP7/DESIGN/README/gap/decision dan finalauthoring/browserchecks; agent governance memperbaiki aturan/fase/registry27/cards/maps/acceptance/11/P11 lalu auditread-only; agent domain membuat coverage18grup dan mengaudit semantik/privasi/frozeninputs; agent UI mengerjakan kandidat awal sebelum primary menyelesaikan targetedfixes. Review independen memakai collaboration dalam chat ini, tanpa pesan ke task user lain. Tidak mengklaim12sesi review fase terpisah atau label model executor yang belum tersedia.

Validator lokal correction memeriksa mapping73/suite39/finalowner,27BU/DAG/jam/criticalpath,gate/phase,card/registry/links/encoding/secret patterns,preservasi85+13 dan patch. `node --check .local/p7-correction/v1.script.js` PASS; static renderer41PASS, bukan browser/backend proof. [VALIDATION](VALIDATION.json) adalah pre-CURRENT_STATE671checksPASS; mode itu mengecualikan old00. Setelah00LAST, validator full/read-only serta working/staged whitespace/UTF8/exact-scope/protectedhashes diulang. Independent governance final audit548checksPASS: math/maps/frozeninputs/source dan4screenshot hashes serta metadata revision/status/holds konsisten, tanpa mengubah file;00 sengaja dikecualikan sebelum finalwrite.

Tidak ada commit/push/deploy/app test yang diasumsikan dari teks ini. Satu commit docs/design-planning hanya dilakukan bila checks/scope aman; hash hasil dibaca Git. Source/helpers/exportlokal dalam `.local/` tidak distage. Patch unrelated tetap untracked.
