# P7 — revisi empat catatan Owner

**WAITING OWNER VISUAL REVIEW / NOT COMPLETE.** Owner menyatakan kandidat sebelumnya belum approved. Exact kandidat baru **sha256-bef01d649ea91b2b**, page p7-v1 pada **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef**. approvedRevision=null; P8–P11 tetap REOPENED_DRAFT/PENDING_P7_APPROVAL. Bukti non-secret dan batas per revision: [VERIFY](VERIFY.json).

## Empat perubahan dan hasil

| Catatan | Perubahan aktual / verifikasi |
| --- | --- |
| Tanggal | Nama bulan Indonesia lengkap, contoh 02 Oktober 2026; waktu detail 02 Oktober 2026, 09.15.00. Formatter dan fixture auth/shell/period/history/receipt/session/audit/correction diselaraskan. Native input tanggal numerik diganti tombol tanggal bernama lengkap yang membuka calendar. Custom Range tetap draft/Apply/Cancel. Source numerik ambigu0/native date0; UI tanggal observed di dashboard/filter/session/history. |
| Foto produk | Dua foto ATK nyata pada katalog/list/search/POS/cart serta foto utama detail/edit; empat master lain jelas Foto belum tersedia. Error gambar → Foto gagal dimuat, ukuran stabil, identitas teks tetap terbaca. Browser naturalWidth500 untuk kedua foto; error scenario naturalWidth0+fallback PASS. Tidak menambah uploader/storage/API produksi; foto adalah ilustrasi kategori, bukan identitas SKU client. |
| Barang keluar Pemilik | Menu eksplisit di sidebar expanded/rail/drawer menuju form manual issue non-sale. Operations tetap memiliki; Kasir tidak. Reason wajib, draft/review/confirm dan freeze/nonnegative guard dipreservasi. Owner mobile entry/form PASS. |
| Interaksi grafik | Satu dataset7tanggal menghasilkan dua line,14titik dan tabel. Tooltip tanggal+Penjualan/Laba Kotor melalui pointer, klik, fokus, Enter/Space;14click/Enter PASS. Tooltip tidak memblokir titik lain. Tujuh kontrol tanggal mobile44px PASS. Dismiss dan Escape mengembalikan fokus ke point/control asal. |

[Gambar katalog](catalog-desktop.png), [detail foto](product-detail-desktop.png), [grafik](owner-chart-desktop.png), [Barang keluar mobile](owner-issue-mobile.png), [POS mobile](pos-mobile.png) diambil ulang pada kandidat final. Layar SaaS memperkecil preview ke viewport browser; angka1440/820/390 adalah preset iframe, bukan ukuran gambar screenshot.

## Scope dan validasi

CLI global dpai0.2.2 saja; sebelum context/mutation project name/id exact diperiksa dan setiap mutation expectedRevision. Tidak mengakses MULTIPLECORP. Intake b51 → dce66544fc614ae5 → fca956a464596dbd → efc7bc48218ee063 → bef01d649ea91b2b. Finish marker hanya authoring, bukan approval fase.

Final preview compile PASS:792726bytes/9pages/1runtime dependency,0diagnostics/0warnings. Flag CLI browser/layout/responsive tetap not-run; browser evidence aktual dicatat terpisah. Node syntax PASS; static renderer41PASS bukan backend/browser proof. Interaksi14titik/7mobile/control foto/filter/role diuji pada efc7; final hanya memperbaiki Escape focus dan rechecked live. Foto/menu/POS/screenshots final tetap hadir. Preset desktop1440/tablet820/mobile390 tidak menunjukkan body overflow pada changed surfaces. Matriks lengkap375/440/768/1024/1440 sebelumnya tetap [histori b51](../p7-correction/BROWSER.json), bukan rerun seluruh state pada kandidat baru.

D-018 menyupersede **satu kalimat format presentasi P3**; invariant UTC/businessDate/query/stock/payment/RBAC tidak berubah. Dua belas input lain dan85protected source/aset/evidence tetap dipreservasi. P7 coverage18grup dan scope V1 tidak berubah. Metadata downstream diperbarui untuk pointer kandidat saja, tanpa refinalisasi/freeze/eksekusi P8–P11.

## Foto, rights, dan holds

Pulpen: [GelPen.jpg / PiccoloNamek](https://commons.wikimedia.org/wiki/File:GelPen.jpg), [CC BY-SA3.0](https://creativecommons.org/licenses/by-sa/3.0/). Buku: [Spiral notebook / theilr](https://commons.wikimedia.org/wiki/File:Spiral_notebook_with_subject_dividers_-_Flickr_-_theilr.jpg), [CC BY-SA2.0](https://creativecommons.org/licenses/by-sa/2.0/). Halaman sumber/lisensi diperiksa, attribution/link tampil di detail/edit, sumber tidak diedit. Foto dipakai sebagai visual contoh; gambar/hosting client produksi belum ditentukan. Upload/storage produk tetap Later.

Masih required: **OWNER VISUAL APPROVAL exact sha256-bef01d649ea91b2b**; foundation/template/font/native derivative rights; full renderedAA/screenreader/actualzoom200%/softwarekeyboard/longcontent states serta39foundationwarnings dari audit sebelumnya. Tidak mengklaim full P7 exit atau39acceptance runtime PASS. Kandidat siap untuk **review visual ulang**, belum design freeze.

Proses authoring/revisi visual tidak melakukan staging atau commit; flag scope pada VERIFY merekam proses tersebut. Owner kemudian mengotorisasi satu checkpoint lokal untuk kandidat exact ini: **P7 OWNER-REVIEW CANDIDATE — NOT APPROVED**. Checkpoint hanya mengarsipkan dokumentasi dan bukti, tanpa perubahan desain, production code, Build Unit, push/merge/deploy, atau database operation. Unrelated patch dan lineage dipreservasi. docs/00 diperbarui LAST; SHA dan status Git checkpoint dibaca dari laporan penutupan. P7 tetap WAITING OWNER VISUAL REVIEW / NOT COMPLETE dan approvedRevision=null.
