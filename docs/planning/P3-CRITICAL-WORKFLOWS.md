# P3 — Workflow kritis dan hasil yang dapat diverifikasi

**Spesifikasi perencanaan V1; belum diimplementasikan atau diuji.** Dokumen ini menetapkan urutan penggunaan, batas konfirmasi dan pemulihan untuk GOODS-only, satu business-wide stock pool dan tiga role tetap. BantuKasir adalah codename internal; identitas client berasal dari BusinessProfile runtime. Tidak ada Order, reservation, DP, split/partial payment, offline posting, warehouse atau workflow supplier/purchasing.

Detail authoritative berada pada [produk](../01-PRD.md), [arsitektur/domain](../02-ARCHITECTURE.md), [auth/RBAC/privasi](../04-AUTH-RBAC-SECURITY.md), [inventory](../06-INVENTORY-SPEC.md), [search/barcode/HID](../12-BARCODE-SCANNER.md), [costing](../15-FINANCE-PROFITABILITY.md), [Sale/session/dokumen](../17-POS-SALES.md) dan [operasi/recovery](../09-DEPLOYMENT-OPS.md). P3 menghubungkan detail tersebut menjadi workflow lengkap; tidak menjadi sumber rumus/schema yang bersaing. [Acceptance](../08-TESTING-ACCEPTANCE.md) memerlukan bukti aktual, termasuk PostgreSQL nyata, bukan mock atau screenshot. Fakta owner/eksternal yang belum tersedia dicatat di [gap dan change control](GAPS-CHANGES.md).

## Pola seluruh workflow

Urutan standar adalah **entry → draft → review → konfirmasi eksplisit → transaksi fakta/result → pembacaan hasil/history/dokumen → pemulihan original jika hasil tidak pasti**. Search, scan, input qty, review, feedback bunyi dan preview tidak mem-posting uang/stok. Human count, konfirmasi dana, jaringan dan printing tidak berlangsung di dalam transaksi database.

Server memeriksa session autentikasi, actor aktif, permission, object scope, versi/config/eligibility yang relevan dan input berbatas. Guard transaksi memakai urutan yang sama dengan writer disable/revoke/config agar permission atau konfigurasi tidak dapat berubah tanpa pemeriksaan yang konsisten. Browser tidak menetapkan harga authoritative, cashier yang bertanggung jawab, balance atau izin melalui field kiriman.

| Keadaan | Makna dan tindakan yang dibolehkan |
| --- | --- |
| DRAFT / REVIEW | Input dapat diedit; belum ada fakta bisnis. Validasi lokal membantu, tetapi server tetap memvalidasi. |
| SUBMITTING | Konfirmasi membekukan operation/source intent/key/envelope. Kontrol posting dan edit dinonaktifkan; double click atau scanner Enter tidak membuat command baru. |
| COMMITTED | Server mengonfirmasi durable original result. Baca DTO sesuai role; buka history atau cetak snapshot setelah commit. |
| REJECTED definitif | Server memberikan penolakan yang diketahui tidak mem-posting fakta. Jelaskan penyebab; review draft, harga, session atau qty dengan revision/intent yang jelas sesuai protokol command. |
| UNCERTAIN | Response hilang atau hasil belum dapat dipastikan. Pertahankan identitas command awal; lakukan status/recovery/retry awal yang aman. Jangan membuat Sale/movement pengganti, pembayaran ulang atau mengubah payload beku. |
| DENIED / EXPIRED | Minta login atau recent authentication bila sesuai; otorisasi ulang sebelum result/dokumen dikembalikan. Mengetahui ID/nomor bukan hak akses. |
| STALE / ERROR / UNKNOWN | Tampilkan jenis ketidakpastian dengan benar. Query gagal bukan nol/kosong; cost UNKNOWN bukan Rp0; hasil command tidak pasti bukan gagal definitif. |

Command kritis mempunyai source intent stabil, idempotency key/fingerprint dan durable result bersama audit dalam transaksi yang sama. Key sama/payload sama mengembalikan hasil awal; key sama/payload berbeda konflik; key berbeda dengan intent sama tidak mem-posting dua kali. Recovery setelah reload memakai referensi minimal yang scoped ke actor, tanpa secret atau payload biaya privat. Hasil committed tetap recoverable sesudah sesi kas CLOSED. Status/result yang tidak ditemukan setelah restore bukan bukti command awal tidak pernah commit.

## Akun, bootstrap dan konfigurasi

| Tahap | Kontrak |
| --- | --- |
| Entry | Bootstrap Pemilik hanya melalui operator privat pada target terverifikasi, tanpa public signup/default password/demo account. Login, invite/reset single-use dan own-account tersedia menurut auth policy; Operations/Kasir tidak mengelola user lain. |
| Draft dan review | Akun sendiri hanya mengubah field yang dibolehkan. User administration Pemilik menampilkan actor/role/status yang akan berubah; konfigurasi memperlihatkan revision bisnis/register yang dituju. Recent authentication berlaku untuk tindakan sensitif. |
| Konfirmasi/fakta | Bootstrap/lifecycle user, disable/revoke dan BusinessProfile/config memakai server guard, audit dan hasil sesuai kontrak. Disable mempertahankan actor historis; hak mutation dari sesi lama tidak tetap sah. Role tidak berasal dari self-assignment. |
| Hasil/read | Own-account menampilkan identitas/kapabilitas yang sah. Pemilik membaca lifecycle/config/status sistem yang dibolehkan, tanpa credential. BusinessProfile baru memiliki immutable identity/template/logo revision dan pointer aktif; dokumen lama tetap merujuk revision aslinya. |
| Failure/recovery | Link expired/reused, password/login error, stale version, akun disabled, recent-auth expired dan upload invalid memiliki respons aman. Jangan mengungkap keberadaan akun/token/secret. Recovery tetap memeriksa permission saat ini. |

Upload logo resmi client hanya mengikuti batas media privat pada [04](../04-AUTH-RBAC-SECURITY.md); tidak memperkenalkan gambar produk, remote URL fetching atau storage publik. Pemeriksaan contents/size/dimensions/re-encode, authorized media delivery dan retensi asset snapshot wajib. Nama/logo codename bukan default identitas client. **Hold:** identitas runtime nyata, DESIGN untuk surface produksi dan RELEASE untuk bootstrap/config produksi; detail owner yang belum ada tetap di gap register.

## Katalog, search-first dan barcode

| Tahap | Kontrak |
| --- | --- |
| Entry | Pemilik/Operations membuka product list/search/detail; Kasir memperoleh lookup operasional untuk POS saja. Search canonical dipakai ulang, berhalaman dan tanpa full-master fetch. |
| Draft dan review | Cari existing exact SKU/barcode dan similar-name candidates sebelum create. Receiving memakai produk existing bila cocok. Similar match memerlukan review/konfirmasi tetap buat; exact collision tidak dapat diabaikan. Form master mempunyai SKU/nama/category/unit/precision/harga/minimum/active serta brand opsional. |
| Konfirmasi/fakta | Server memvalidasi uniqueness/version/permission. Master baru menghasilkan stock 0 dan tanpa StockMovement. Harga jual memiliki revision/audit; unit/precision tidak dapat berubah setelah movement pertama, termasuk race edit/posting pertama. |
| Hasil/read | List/detail menerima DTO sesuai role. Alias manufacturer/internal terhubung ke identitas produk yang sama; leading zeros/case/punctuation dan retired-token rules dipertahankan. Label memakai token yang sama, tanpa cost. |
| Failure/recovery | Concurrent exact collision ditolak DB dan dikembalikan sebagai actionable error aman. Late lookup tidak mengganti pilihan baru. Inactive/unknown code ditandai jelas; token unknown tetap tersedia untuk search, tanpa automatic create/stock. |

Generate internal Code128 dan print/reprint label adalah capability yang diotorisasi; printing tidak membuat stok. Manual/USB/Bluetooth HID adalah input draft, bukan posting command. **Hold:** G2 untuk unit/precision/barcode nyata, DESIGN untuk komposisi UI dan DEVICES untuk keterbacaan label/scanner fisik. Tidak ada import CSV/XLSX atau pack-conversion capability V1.

## Receiving dan manual issue

| Tahap | RECEIPT / Barang Masuk | MANUAL_ISSUE / Barang Keluar non-sale |
| --- | --- | --- |
| Entry | Pemilik/Operations memilih produk melalui search canonical; create master yang sah tetap terpisah dari receipt. | Pemilik/Operations memilih active product/current stock; bukan workflow penjualan pelanggan. |
| Draft | Qty sesuai base-unit precision, source/reference opsional, acquisition evidence baru atau UNKNOWN. Biaya hilang tidak diubah menjadi nol. | Qty dan reason non-sale wajib; alasan sesuai scope rusak/pemakaian internal/hilang/sampel/lainnya. |
| Review | Tampilkan produk/unit/qty/reference dan dampak stock. Operations hanya melihat status evidence, tanpa echo nilai biaya yang baru dikirim atau cost historis. | Tampilkan produk/unit/qty/reason dan before/delta/after stock; warning insufficient stock tidak menjadi override. |
| Konfirmasi/fakta | Atomic RECEIPT/movement + synchronous balance + cost source/projection + audit + durable result. | Atomic MANUAL_ISSUE/movement + balance + cost allocation/projection + audit + durable result; non-sale cost tidak menjadi sales HPP. |
| Hasil/read | History/stock/LOW-OUT diperbarui dari persisted result; Operations DTO tetap tanpa cost. Field sensitif dibersihkan setelah commit terkonfirmasi. | History aman mempertahankan reason/source/actor/waktu asli; tidak ada Sale/Payment/receipt pelanggan. |
| Failure/recovery | Duplicate/retry memulihkan original; payload Operations cost tidak masuk localStorage/IndexedDB/recovery cache/log/response error/status. Setelah reload, gunakan referensi command dan status aman; jangan replay payload privat yang tidak disimpan. | Reject qty/precision/active/version/reason kosong/stock tidak cukup. Last-unit races mempunyai satu hasil sah, tanpa negative stock; uncertain memakai command awal. |

Aggregate baris produk duplikat sebelum stock check. Gagal pada boundary movement/balance/cost/audit/result me-rollback seluruh command, termasuk multi-line command. Guard onboarding/mismatch berlaku. **Hold:** G2 untuk data nyata dan praktik evidence/unit; DESIGN untuk UI. RECEIPT bukan OPENING dan MANUAL_ISSUE bukan cara menyembunyikan penjualan.

## Opening awal Pemilik dan stok/restock

Entry Pemilik dimulai dari cutover/count awal yang terdokumentasi. Persistent onboarding flag memblokir ordinary inventory/Sale posting selama count dimasukkan; menghitung barang tidak menahan DB lock. Draft opening memakai produk tanpa prior ledger, qty/evidence atau UNKNOWN/count reference. Review paling banyak 100 rows per command; qty 0 hanya checklist, tanpa movement palsu. Konfirmasi seluruh command menulis opening/balance/cost/audit/result atomik; invalid row atau 101 rows menolak seluruh command.

OPENING positif hanya sekali per produk yang eligible, termasuk concurrent/different-key retry. Command terpisah mencakup produk berbeda; jangan membelah satu opening produk atau menyamarkannya sebagai receipt. Pemilik merekonsiliasi count, barcode/threshold dan cost completeness sebelum melepas flag melalui guard yang sama. Produk baru setelah onboarding memakai RECEIPT, tanpa reset/reimport atau edit balance.

Stok/history Pemilik/Operations dibaca berhalaman dari committed ledger/balance. LOW/OUT memakai threshold terkini, tidak mengikuti periode finance: 0 = OUT; positif sampai minimum = LOW; di atas minimum = NORMAL. Minimum 0 tidak mempunyai interval LOW; active master baru stock 0 tetap OUT. Threshold change tervalidasi/audited, tanpa movement. Refresh sesudah mutation/tab focus dan interval aktif paling lama 30 detik. Failed refresh menampilkan stale/error/last-refreshed time; bukan daftar kosong atau status Normal palsu. Kasir hanya menerima stock yang dibolehkan untuk checkout.

**Hold:** G2 untuk unit/volume/count/evidence/barcode/serial reality dan authorized cutover sebelum opening bisnis nyata; DESIGN untuk UI. Bila manual onboarding tidak praktis atau serial/pack/batch benar-benar wajib, hentikan slice onboarding terkait dan lakukan change control; jangan menambah import atau mengabaikan identitas barang.

## Sesi sendiri, Sale pembayaran penuh dan close

| Tahap | Buka CashSession | Sale CASH / BANK_TRANSFER | Tutup CashSession |
| --- | --- | --- | --- |
| Entry | Kasir/Pemilik sebagai dirinya sendiri; eligible register tersedia. Operations tidak mempunyai POS/session grant. | Own eligible OPEN session wajib, termasuk Pemilik dan transfer-only Sale. NO_SESSION menutup jalan checkout. | Own OPEN session; semua UNCERTAIN checkout harus diselesaikan lebih dahulu. Assisted close Pemilik mengikuti permission/recent auth/reason. |
| Draft | Hitung/masukkan openingCash nonnegative secara eksplisit; Rp0 valid, blank ditolak. | Search/scan→cart qty tambah/kurang/hapus. Harga/active/version/stock divalidasi server; scan hanya draft dan tidak reservasi stock. | Preview identitas/totals/activityVersion; hitung physicalCash nonnegative di luar transaksi. Capture scan dipause selama dialog/count. |
| Review | Register, responsible cashier asli dan opening count jelas. | Satu full-payment method: CASH tender/change/applied, atau BANK_TRANSFER full amount + konfirmasi operasional dana diterima/reference opsional. Tidak ada split/partial/credit/discount override. | Tampilkan expected/physical/variance dan source cutoff; user menghentikan checkout sementara. Hitungan memakai activityVersion preview. |
| Konfirmasi/fakta | Kunci register; uniqueOPEN per register dan per responsible cashier. Atomic session/count/audit/result, tanpa Sale atau stock movement. | Atomic Sale/lines/tepat satu full Payment/SALE_ISSUE/balance/cost atau UNKNOWN/ReceiptSnapshot/audit/durable result; activityVersion bertambah sekali. Human bank/customer action di luar DB transaction. | Kunci sesi yang sama dengan checkout. Version cocok: atomic physical count/expected/variance/cutoff/source IDs/immutable report/CLOSED/audit/result. Tidak ada state CLOSING yang persisten. |
| Hasil/read | OPEN identity terlihat pada workspace: session/register/responsible cashier/waktu/status. | Durable success membuka receipt asli, own Sale history dan permitted totals. Print sesudah commit. CASH applied menambah drawer expectedCash; transfer tidak. | Satu as-closed report; snapshot/count/variance/identity/template immutable. Session berikutnya adalah sesi baru, termasuk kembali pada tanggal sama/handover. |
| Failure/recovery | Double open ditolak constraints/guards; retry tidak membuat sesi kedua. | Reject stock/price/session/config sebelum facts. Lost response UNCERTAIN membekukan intent awal; recovery masih tersedia sesudah CLOSED. Jangan minta customer bayar/transfer ulang dari timeout. | Sale menang lebih dahulu→version berubah/recount dan OPEN tetap; close menang lebih dahulu→Sale ditolak sebelum facts. Failed snapshot/audit/result meninggalkan OPEN. Equivalent close retry termasuk key berbeda memakai original result; count konflik tidak menimpa. |

ExpectedCash hanya openingCash + applied CASH payments; BANK_TRANSFER, owner-funded payout, opening float dan variance tidak menjadi revenue/profit tambahan. Opening/physical count tidak negatif. Assisted close mempertahankan responsible cashier asli dan mencatat close actor/reason. Count mistake setelah CLOSED menerima linked owner review note, bukan edit snapshot/count/variance, reopen atau responsibility overwrite. Handover menutup A lalu membuka B; tidak ada simultaneous shared drawer, paid-in/out atau cash pickup.

V1 BANK_TRANSFER adalah pencatatan full transfer yang dikonfirmasi manusia, bukan gateway verification atau atomic bank settlement. Boundary payment method/type dan Sale core memungkinkan extension provider kemudian, tetapi V1 tidak mengimplementasikan callback/QRIS/webhook/asynchronous payment.

**Hold:** G3 untuk drawer/float/handover dan sumber dana refund nyata; G1 untuk dokumen/buyer policy; DESIGN dan DEVICES untuk UI/perangkat; RELEASE untuk penggunaan produksi. Beberapa register hanya mewakili drawer fisik berbeda yang dikonfirmasi, bukan multi-outlet/warehouse atau shift engine.

## Koreksi stok, cost evidence/replay, refund dan return

| Workflow Pemilik | Draft/review dan konfirmasi | Fakta/read hasil | Penolakan atau hasil tidak pasti |
| --- | --- | --- | --- |
| Evidence correction | Pilih cost source/version, evidence baru/reason/recent auth; review impact/completeness. | Append evidence revision; bounded deterministic replay di bawah product guards mempublikasikan valuation/allocation versions + projection/audit/result seluruhnya atau tidak sama sekali. Original source/allocation versions dipertahankan. | Missing cost bukan 0. Replay capacity/timeout/invalid dependent history menolak publication aman, tanpa partial result atau tebakan cost. Concurrent posting menunggu atau ditolak aman. |
| Physical adjustment | Verifikasi count/reference/reason, signed physical delta dan before/delta/after; positive delta mempunyai evidence/UNKNOWN. | Append ADJUSTMENT/cost facts/balance/audit/result; original history tetap. | Tidak ada direct balance editing, negatif atau count untuk menyamarkan fakta lama; uncertain recover original. |
| Standalone reversal | Pilih original eligible opening/receipt/manual issue, reason/recent auth dan exact inverse; form tidak meminta count/qty bebas yang tidak relevan. | Satu full linked REVERSAL; original tetap; physical inverse dan replay valuation aman commit bersama. | SALE_ISSUE/RETURN_RECEIPT direct reversal, double reversal, negative stock atau unsafe replay ditolak. Jangan memakai latest average cost secara naif. |
| Refund uang | Pilih Sale/line asli, remaining monetary bound, amount/reason/recent auth dan executed CASH/TRANSFER payout evidence. | Append refund dengan actor/time/method/reference dan original link; total/line monetary bounds cumulative dilindungi locks. Refund-only tidak menambah stock atau membalik HPP. | Duplicate/race melewati bound ditolak; original Sale/Payment/receipt tidak dihapus/diubah. Owner-funded payout tidak menjadi hidden cash adjustment sesi. |
| Return fisik | Verifikasi original SaleLine/issued qty, remaining return qty dan kondisi fisik. Money refund dan returned qty dinilai terpisah. | Saleable RETURN_RECEIPT memulihkan matched historical cost sekali; UNKNOWN tetap incomplete. Damaged/non-saleable evidence tidak menambah usable stock. Return sesudah refund dibolehkan dalam remaining bound. | Cumulative qty bound dan money bound berlaku independen di bawah locks; tidak boleh memulihkan cost dua kali. Failure pada return/balance/cost/audit/result rollback semua. |

Review koreksi menampilkan original facts, eligibility dan hasil current yang akan berubah secara jelas; bukan affordance mengedit history. Refund/return dapat menjadi satu command terhubung yang atomik sesuai [17](../17-POS-SALES.md), tetapi tidak menambah exchange/customer-credit capability. Payout manusia berlangsung di luar transaksi; uncertain recording diperiksa terhadap original intent dan bukti payout sebelum tindakan ulang. Payout dari drawer OPEN tidak diaktifkan oleh template atau asumsi.

Known cost historis yang valid tidak hilang hanya karena receipt berikutnya UNKNOWN. Saldo mencapai 0 lalu receipt KNOWN dapat memulai pool KNOWN baru, tanpa mengklaim periode lama sudah lengkap. Return/reversal dan revisi biaya harus mempertahankan alokasi cost yang cocok dengan fakta sumber dan revision yang konsisten. Rumus, precision/residual dan batas replay berada di [15](../15-FINANCE-PROFITABILITY.md); perubahan catatan finansial tidak menulis ulang ReceiptSnapshot atau as-closed session report.

**Hold:** G2 untuk evidence/kondisi stok nyata, G3 untuk funding refund dan praktik drawer; DESIGN untuk UI. Perubahan kebijakan refund/funding atau kebutuhan capability tambahan masuk OWNER_DECISION_REQUIRED pada gap register sebelum penggunaan bisnis.

## Laporan, history, audit dan dokumen

| Surface | Entry/draft/review | Hasil yang harus benar | Failure/recovery dan scope |
| --- | --- | --- | --- |
| Owner finance/daily summary | Pilih Hari Ini/7 Hari/Bulan Ini/Bulan Lalu/Custom Range; draft tanggal tidak mengubah report sampai Apply, Cancel mempertahankan periode lama. | Query snapshot konsisten dengan asOf/timezone/evidence revision/completeness/unknown line count. Revenue/refund/net/qty/payment/HPP/Laba Kotor berasal dari source facts; cost incomplete ditampilkan eksplisit. | Invalid/future/range inputs mengikuti query policy tervalidasi. Query error bukan 0. Gross profit bukan net profit; margin hanya jika lengkap dan net sales positif. |
| Session history/report | Kasir own session/Sale; Pemilik semua. Date/window/role pagination dan identitas session jelas. | Snapshot CLOSED as-closed, variance terpisah; related correction/review note sebagai fakta tambahan. Same-day beberapa session/midnight tetap tidak double-count. | Operations ditolak. Own-object authorization berlaku pada list/detail/status/export/reprint dan guessed ID. Query tidak menulis ulang snapshot. |
| Operational stock history | Pemilik/Operations filter product/date/type/reference. | Immutable movement/source/reason/actor/server posting time dengan DTO aman; cost hanya di view Pemilik berizin. | Staff error/search/status/cache tidak memuat evidence/HPP/profit. Stale/current stock dan history dibedakan. |
| Owner audit/system status | Filter actor/date/action/product/session/reference serta kebaruan backup/reconciliation. | Audit successful changes ikut business transaction; identities asli tetap. Health/backup status dari evidence aktual, bukan sukses statis. | Security/recovery details owner-only; logs tanpa tokens/private full payload. Missing/stale status eksplisit, tidak memberi false assurance. |
| Receipt thermal/A4 commercial nota | Pilih committed Sale original; review preview memakai frozen identity/template/asset dan safe document DTO. | Satu nomor/content asli, copy marker untuk reprint. Browser print/PDF sesudah commit, tanpa re-entry. BusinessProfile change tidak mengubah dokumen lama. | Kasir own Sale/Pemilik semua; reauthorization tiap request/media. Printer/export failure tidak rollback Sale atau membuat replacement. Print dialog bukan bukti tinta. |
| Session print/PDF/CSV dan barcode label | Pilih permitted immutable session report/token barcode yang sama; preview/download scoped. | Render ulang snapshot asli. CSV text formula/control prefixes aman, negative numeric variance tetap numerik; tidak ada private cost dalam dokumen staff. | Export gagal diulang dari source yang sama; tidak menciptakan Sale/Payment/movement/session. Physical label/receipt proof wajib di DEVICES. |

Waktu posting selalu server UTC; businessDate Asia/Jakarta dihitung server. Dokumen source date opsional bukan backdating facts. Date filters Jakarta menggunakan UTC half-open; CustomRange inclusive dates menjadi exclusive next-day end. Perubahan refund/correction mengikuti server posting date, bukan menimpa laporan historis diam-diam. Formatting UI memakai DD MM YYYY dan hh:mm:ss untuk detail waktu sesuai kontrak desain.

Owner daily totals dihitung sekali dari Sale/payment/cost facts; session totals tidak dijumlahkan lagi di atas transaksi yang sama. Midnight session tetap satu accountability document dengan full time span dan attribution tiap Sale berdasarkan businessDate. Current LOW/OUT tidak disamakan dengan trend periode.

**Hold:** G1 untuk legal/tax/buyer policy commercial nota; DESIGN untuk revision/kontrak visual; DEVICES untuk tinta/scan/driver/media nyata. Backend query dan tests independen tidak perlu menunggu approval visual. Production acceptance/printing nyata tidak diakui dari mock atau approval prototype lama.

## Incident, backup dan recovery

Entry incident dimulai dari lost response, mismatch reconciliation, backup/host failure atau restore requirement. Bedakan hasil command UNCERTAIN dari kebutuhan restore operasional. Reconciliation read-only membandingkan ledger/balance/cost/sources/Sale/payment/session/report; mismatch memblokir posting terdampak dan memberi incident Pemilik. UNKNOWN cost yang valid bukan mismatch atau nol.

Review recovery mencatat target/source/revision/checksum, consistent DB backup bersama asset/template manifest, cutoff dan fakta yang mungkin hilang. Gunakan independent encrypted backup dengan recovery key/access yang benar-benar tersedia. Restore drill memakai isolated target baru dan populated facts; production source tidak dihapus/reset. Forward migration memakai expand→backfill→verify→contract serta backup/recovery readiness dan target/credential guards.

Setelah restore, revoke auth sessions dan majukan recoveryEpoch. Stale browser intents tidak boleh diposting sebagai pekerjaan baru. Missing restored result tidak membuktikan original belum commit: periksa kemungkinan gap sesudah cutoff dengan evidence cash/transfer/printed receipt/physical stock dan original sources. Jangan blind replay, meminta pembayaran ulang, direct balance editing atau menulis ulang history. Controlled replay/rebuild tetap mempertahankan immutable source, permission, audit dan rekonsiliasi sebelum posting dibuka kembali.

Hasil recovery harus memverifikasi linked facts, balance/cost completeness, source intents/results, setiap CLOSED tepat satu report, OPEN uniqueness/attribution dan reproduksi snapshot/media/template lama. Catat measured RPO/RTO dan independent recovery access; proposal target atau dummy backup bukan PASS. **Hold:** G4 untuk destination/access/key/cost dan penerimaan hasil terukur; RELEASE untuk target/operation produksi yang tepat. Operasi destruktif produksi memerlukan approval owner untuk operasi EXACT, terpisah dari izin deploy/migration/fix.

## Batas penerimaan workflow

Untuk setiap row di atas, evidence harus menghubungkan role/entry state→input→explicit action→persisted facts→safe read/history/document→failure/original recovery pada revision yang sama. Real PostgreSQL membuktikan constraints, rollback tiap boundary dan races; direct route/API membuktikan RBAC/object scope/DTO/privacy. Browser checks membuktikan mobile/keyboard/touch/focus/dialog/scan dan states loading/empty/error/denied/stale/UNKNOWN/UNCERTAIN/success. Perangkat, performance, backup/restore dan authorized pilot memberi bukti aktualnya masing-masing.

Hold owner/eksternal tidak memberi kredit completion atau FINAL PASS. Workflow teknis independen boleh dispesifikasikan/dibangun ketika hold bisnis/visual belum selesai sesuai Build Unit dan change control, tetapi data nyata, operasi produksi dan acceptance terkait tetap diblokir. Rebaseline P0–P11 tidak mengotorisasi application code atau eksekusi Build Unit pertama.
