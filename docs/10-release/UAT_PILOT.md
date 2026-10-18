# UAT, pilot and user guidance

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta); corrected under D-089, 18 October 2026: the wording of UAT-23, because a drill never runs on the VPS (finding B-1 of the RF-6 review) |
| Phase | P10 — user acceptance testing, the pilot and its acceptance, the shop's operating guidance and training |
| Replaces | The UAT and pilot section of `docs/10-release/legacy/P10-RELEASE-UAT.md` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-032, D-035, D-040, D-045, D-046, D-056, D-068, D-071, D-087, D-088, D-089 |
| Related | [RELEASE_PROCESS](RELEASE_PROCESS.md), [USER_TASKS](../07-ux-design/USER_TASKS.md), [CRITICAL_FLOWS](../03-workflows/CRITICAL_FLOWS.md), [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md), [DEVICE_VERIFICATION](../08-testing/DEVICE_VERIFICATION.md), [GLOSSARY](../02-domain/GLOSSARY.md), [COPY_DECK](../07-ux-design/COPY_DECK.md) |

How the shop's own people accept BantuKasir before it is used for real, how the first period of real use is run and accepted, and what the shop is told. Rules are numbered `UPR-nn`, UAT scripts `UAT-nn`, pilot criteria `PIL-nn` and guidance items `UG-nn`; identifiers are permanent. The guidance of section 5 is the shop's text, in Indonesian, with the terms of GLOSSARY and the labels of COPY_DECK.

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| UPR-01 | UAT runs on staging, with synthetic data and the shop's real devices where a script needs them, never on production (ENVIRONMENTS ENV-01). | PDS §4 |
| UPR-02 | UAT is carried out by the people who will do the work — the Owner, the Admin Operasional and the cashiers — with the operator present; the evidence of each script is recorded by the person who ran it and checked by the operator. | REQ-OPS-03 |
| UPR-03 | The pilot is the first period of real use in production, after the release authorized by the Owner and the cutover (RELEASE_PROCESS RB-24). Nothing in the pilot is done only to test: every Sale, movement and correction in production is real. | PDS §4, REL-07 |
| UPR-04 | The Owner accepts the pilot in writing; the acceptance is recorded in DECISIONS and closes suite RELEASE01 (PRODUCT_BLUEPRINT section 12). | D-025 |
| UPR-05 | A defect found in UAT or the pilot is classified as OPERATIONS_RUNBOOK section 6 does; S1 and S2 stop the affected work until fixed; every fix is a change through change control and a release (RELEASE_PROCESS). | PROJECT_RULES section 6 |

## 2. UAT scripts

Each script names the user tasks it covers (USER_TASKS), so every task of V1 is accepted by the people who do it. "Expected" lists what the person must see; the automated proof of the same behaviour is in the suites named.

| ID | Who | Tasks | Steps | Expected | Suites |
| --- | --- | --- | --- | --- | --- |
| UAT-01 | Kasir | UXT-01, UXT-02, UXT-03, UXT-06 | Sign in; open a cash session with a counted opening amount, once with Rp0; sell three products by USB and Bluetooth scanner and by typing; pay cash with "Uang Pas" and with a larger amount; print the receipt at 58 mm and 80 mm; print it again | The session opens with the amount entered; each scan adds one; change is shown before "Selesaikan"; the receipt shows the items, total, cash and change; the reprint carries "Salinan" | POS01, POS02, SESSION03, SEARCH03, SESSION08, DEVICE01 |
| UAT-02 | Kasir | UXT-04 | Sell and pay by bank transfer, pressing "Dana sudah diterima — Selesaikan" only after the funds show | The Sale is saved; expected cash of the session does not change | POS02, SESSION04 |
| UAT-03 | Kasir | UXT-07 | The operator changes a price, empties a product's stock and deactivates a product while the cashier's cart holds them; the cashier pays | Each case is refused at its line; the total never changes without the cashier's review | POS03, SEARCH02 |
| UAT-04 | Kasir | UXT-08 | Disconnect the device's network as "Selesaikan" is pressed; reconnect; use "Cek status"; then open "Permintaan belum pasti" on a second device | No second Sale is possible; "Cek status" shows the saved Sale or that it was not saved; the request is listed on the second device | POS03, CMD01 |
| UAT-05 | Kasir | UXT-09 | Close the session: preview, count, review, "Tutup sesi"; repeat with a Sale made after the preview; repeat while a payment of the same cashier is still "Status belum pasti" | The report shows expected cash, physical cash and the variance in words; a Sale after the preview gives "Ada transaksi baru; hitung ulang kas"; an unresolved payment keeps the close from going ahead until "Cek status" settles it (D-088) | SESSION05, SESSION06, SESSION07 |
| UAT-06 | Kasir | UXT-10 | Find own Sales and session reports; print and download a session report; try to open another cashier's Sale by its address | Own records only; another cashier's record is not found | SESSION08, SESSION10, AUTH01 |
| UAT-07 | Every role | UXT-01, UXT-11, UXT-12 | Set the first password from a link handed over by the Owner; change the password; sign out on a shared device and press Back | The link works once within 60 minutes; after "Keluar" nothing of the previous user is shown | LINK01, SIGNIN01, AUTH02 |
| UAT-08 | Admin Operasional | UXT-13, UXT-16 | Search products by name, SKU and barcode; open the stock list and "Perlu restok" | Results without cost; Normal, Menipis and Habis agree with the minimum stock | SEARCH02, STOCK01 |
| UAT-09 | Admin Operasional | UXT-14 | Record goods in with a known cost and with the cost left to the Owner; open the document after the Owner corrects the cost | The cost is shown on the document; after the Owner's correction the current cost, "Dikoreksi Pemilik" and the cost at confirmation are shown, never the reason | INV01, AUTH02 |
| UAT-10 | Admin Operasional | UXT-15 | Record goods leaving as damaged and as internal use | A reason is required; stock drops; no Sale is created | INV02 |
| UAT-11 | Admin Operasional | UXT-17, UXT-18, UXT-19, UXT-20, UXT-22 | Add a product after the search-first step, also with a similar name; change its price and minimum stock; add a manufacturer barcode, generate an internal one, retire one; set a photo; print labels; deactivate and reactivate a product and a category | "Tetap buat produk baru" is required for a similar name; a duplicate SKU or barcode is refused; labels scan on the shop's scanners; nothing is deleted | SEARCH01, SEARCH02, MEDIA01, SESSION08, DEVICE01 |
| UAT-12 | Admin Operasional | UXT-21 | Import a CSV with errors, correct it, import it; import the same file again | Errors are shown per row in the preview; the corrected file creates all its products or none; the repeat creates nothing new | IMPORT01 |
| UAT-13 | Akun Kasir dan Admin Operasional | — (the union of the two roles) | Sign in without a session; open a session and sell; try an Owner-only action | The home is Stok without an OPEN session; selling works in its own session; every Owner-only action is refused | ACC01, AUTH01 |
| UAT-14 | Pemilik | UXT-23, UXT-24, UXT-25 | Read Beranda, Rekap harian and a closed session; add a review note to a session with a variance | The figures agree with the session reports; the note is added and the report is unchanged | SESSION09, SESSION07 |
| UAT-15 | Pemilik | UXT-26 | Close another user's session with "Tutup sesi oleh Pemilik", once after that user was deactivated and once while that user has a payment "Status belum pasti" | The close needs a count, a reason and the password confirmation; the unresolved payment is listed and must be settled with "Cek status" first (D-088) | SESSION10, SESSION05, ACC01 |
| UAT-16 | Pemilik | UXT-27 | Refund and return: from the drawer of an OPEN session, from "Dana Pemilik" and by transfer; a drawer refund above the session's expected cash; a refund whose cash is still in the drawer when the session is closed | Bounds hold; the over-limit refund is refused before any cash leaves; the cash is taken only after "Berhasil disimpan"; the late handover follows UG-07 (D-068) | RET01, RET02 |
| UAT-17 | Pemilik | UXT-28, UXT-29 | Read Laporan keuangan for each period and a custom range; complete an unknown cost and correct a known one | "Data biaya belum lengkap" while cost is unknown; complete figures after the completion; a custom range in the future is refused | FIN01, FIN02, FIN03 |
| UAT-18 | Pemilik | UXT-30, UXT-31, UXT-32 | Adjust stock to a count; reverse a wrong receiving; move a misassigned manufacturer barcode | Each needs a reason and the password confirmation; history keeps the original; the barcode scans to the right product | INV03, BARCODE01 |
| UAT-19 | Pemilik | UXT-33 | Rehearse the cutover on staging: start onboarding, enter counts, withdraw a zero count, reverse and replace an opening, end onboarding; try to start it again | Sales are refused during onboarding; corrections leave history; after "Akhiri masa input stok awal" it cannot be started again (D-071) | OPEN01 |
| UAT-20 | Pemilik | UXT-34 | On staging only, the operator induces a stock mismatch with a documented statement on the staging database; the reconciliation runs | The product is blocked; after the operator's rebuild (OP-07) and a clean reconciliation, "Buka kembali mutasi" reopens it | INV01, OPPROC01 |
| UAT-21 | Pemilik | UXT-35 | Create a cashier and a combined account and hand over their links; deactivate a cashier who holds an OPEN session; reactivate; anonymize a deactivated account | The session stays OPEN after the deactivation; the anonymized account keeps its history on old receipts | ACC01, LINK01, SESSION10 |
| UAT-22 | Pemilik | UXT-36, UXT-37, UXT-39 | Change the BusinessProfile and its logo; add and change a cash register; change the Owner's own name and email | Old receipts reprint with their old identity; a register with an OPEN session cannot be changed | IDENTITY01, CONFIG01, ACC01 |
| UAT-23 | Pemilik | UXT-38 | Read Status sistem on staging before and after the first staging drill (BACKUP_RECOVERY RB-02); read Riwayat aktivitas and Riwayat keamanan | "Belum Terverifikasi" before any drill; the drill's time after it; staff cannot open these pages | STATUS01, AUDIT01 |
| UAT-24 | Pemilik and operator | — (outside the interface) | On staging: every operator procedure (OP-01 to OP-08) and a full RB-01 rehearsal with the Owner's decisions of phase E | Each procedure behaves as SECURITY_MODEL section 14 says; the Owner's reconciliation time is measured (BACKUP_RECOVERY section 12) | OPPROC01, REC02 |

## 3. The pilot

- **Length.** At least 7 consecutive trading days in production after the cutover; the Owner may extend it.
- **Each day.** The operator reads the system status and the heartbeat; the Owner reads Beranda and Rekap harian, reviews every closed session and adds a review note to every variance.
- **Stop.** An S1 incident pauses the work it concerns; the Owner decides whether the pilot continues.

| ID | The pilot is accepted when | Evidence |
| --- | --- | --- |
| PIL-01 | Every session of the pilot was opened and closed with its report, and every non-zero variance has an Owner review note | Session list and notes |
| PIL-02 | The daily reconciliation was clean every day, or every incident was handled by RB-04 and closed | `reconciliation_runs`; incident record |
| PIL-03 | No backup run was missed, every Sunday re-verification passed, and the system status is current | `backup_runs`; Status sistem |
| PIL-04 | A production restore drill with the pilot's real data passed and is recorded (RB-02) | `restore_drills` |
| PIL-05 | A count of at least 20 products, or all products if fewer, at the end of the pilot matches the system, or each difference is explained and adjusted by count | Count sheet; adjustments |
| PIL-06 | Rekap harian and Laporan keuangan agree with the session reports and with the shop's own record of the days | The Owner's check |
| PIL-07 | Receipts, reprints, session reports and labels printed on the shop's real printers | DEVICE_VERIFICATION record |
| PIL-08 | No incident of class S1 or S2 is open | Operations record |
| PIL-09 | Every task the shop needed during the pilot worked; tasks it did not need were accepted in UAT | UAT and pilot records |
| PIL-10 | Training is complete and the guidance of section 5 has been handed over | Training record |
| PIL-11 | The Owner accepts the pilot in writing | DECISIONS |

## 4. Training

| Who | What | When | Evidence |
| --- | --- | --- | --- |
| Cashiers | UAT-01 to UAT-07 and the guidance UG-01 to UG-06 | On staging, before the pilot | The cashier ran each script once |
| Admin Operasional | UAT-07 to UAT-12, UG-01, UG-04 and UG-12 | Before the pilot | As above |
| Owner | Every script, the whole guidance, the restore runbook's Owner decisions (BACKUP_RECOVERY RB-01 phase E) and the sealed copy | Before the pilot | As above; the sealed copy handed over and tested (BKP-20) |

The training record is kept by the operator outside the repository; it names people, so it is never published.

## 5. Guidance for the shop

The text below is what the shop receives at training, as a printed sheet and in person. Its wording is reviewed by the Owner with this checkpoint and finalized at training; it uses the terms of GLOSSARY and the labels of COPY_DECK, and adds no rule.

### UG-01 — Untuk semua pengguna

- Masuk hanya dengan akun Anda sendiri. Jangan memberi tahu kata sandi Anda kepada siapa pun, termasuk Pemilik.
- Di perangkat yang dipakai bersama, selalu tekan "Keluar" sebelum orang lain memakainya.
- Tautan kata sandi diberikan langsung oleh Pemilik, berlaku 60 menit dan hanya sekali. Jika kata sandi Anda berubah padahal Anda tidak mengubahnya, segera beri tahu Pemilik.

### UG-02 — Kasir: sesi kas

- Buka sesi kas di laci kas yang Anda pegang, dengan modal awal yang benar-benar dihitung. Rp0 boleh, asalkan memang kosong.
- Satu laci kas dipegang satu penanggung jawab. Serah terima selalu: Anda tutup sesi dulu, lalu kasir berikutnya buka sesi baru.
- Keluar dari aplikasi tidak menutup sesi kas Anda.

### UG-03 — Kasir: menjual

- Setiap scan menambah satu. Enter dari scanner tidak pernah menyelesaikan pembayaran.
- Periksa total dan metode pembayaran sebelum menekan "Selesaikan".
- Untuk transfer bank, tekan "Dana sudah diterima — Selesaikan" hanya setelah dana benar-benar masuk.

### UG-04 — Status belum pasti

- Jika muncul "Status belum pasti", jangan membuat penjualan baru untuk pembeli yang sama dan jangan meminta pembeli membayar lagi.
- Tekan "Cek status", atau "Kirim ulang" untuk mengirim permintaan yang sama. Permintaan itu juga tampil di "Permintaan belum pasti" di perangkat lain milik Anda.
- Jika pembeli sudah membayar dan statusnya belum jelas, panggil Pemilik.

### UG-05 — Struk

- Jika struk gagal tercetak, penjualan tetap tersimpan. Cetak ulang dari halaman struk; cetakan ulang bertanda "Salinan".

### UG-06 — Kasir: tutup sesi kas

- Hitung uang fisik di laci, lalu masukkan jumlahnya.
- Jika muncul "Ada transaksi baru; hitung ulang kas", hitung ulang laci lalu kirim lagi.
- Sesi tidak bisa ditutup selama masih ada pembayaran Anda yang berstatus belum pasti. Selesaikan dulu dengan "Cek status".

### UG-07 — Pemilik: pengembalian uang dari laci kas

- Ambil uang dari laci hanya setelah pengembalian "Berhasil disimpan", lalu langsung berikan kepada pembeli.
- Uang itu harus keluar dari laci sebelum laci dihitung untuk tutup sesi.
- Jika uangnya ternyata masih di laci saat sesi sudah ditutup: sesi tetap tertutup dengan selisih lebih. Tambahkan catatan tinjauan Pemilik pada sesi itu, lalu serahkan uang itu kepada pembeli **sebelum modal awal sesi berikutnya di laci kas yang sama dihitung**. Aturan ini wajib (D-068).

### UG-08 — Pemilik: transaksi ganda

- Jika satu penjualan ternyata tercatat dua kali — misalnya karena permintaan dari dua perangkat — koreksi penjualan kedua dengan "Pengembalian uang dan retur", dengan alasan yang jelas.
- Barang masuk atau barang keluar yang tercatat dua kali dibatalkan dengan "Pembatalan mutasi".
- Riwayat tetap menyimpan keduanya beserta koreksinya (D-088).

### UG-09 — Pemilik: masa input stok awal

- "Mulai masa input stok awal" hanya bisa dilakukan sekali untuk data usaha ini. Selama masa ini penjualan berhenti.
- Kesalahan input sebelum masa diakhiri dikoreksi dengan menarik hitungan nol atau membatalkan stok awal lalu memasukkan penggantinya.
- Setelah "Akhiri masa input stok awal", koreksi hanya lewat penyesuaian stok, pembatalan mutasi atau koreksi biaya.

### UG-10 — Pemilik: status sistem dan cadangan data

- "Belum Terverifikasi" artinya belum terbukti, bukan berhasil. Jika "Status sistem perlu dicek" muncul di Beranda, hubungi operator.
- Salinan tersegel kunci pemulihan dan akses cadangan data disimpan terpisah dari perangkat toko. Jangan dibuka, kecuali operator tidak bisa dihubungi dan data harus dipulihkan.

### UG-11 — Pemilik: setelah pemulihan data

- Setiap orang harus masuk lagi. Nomor penjualan berikutnya melompat; itu disengaja.
- Penjualan yang terjadi setelah titik cadangan data tidak ada di sistem. Kumpulkan struk dan bukti transfernya untuk catatan toko; jangan memasukkannya lagi sebagai penjualan.
- Permintaan dari sebelum pemulihan tertahan sampai Anda memutuskannya bersama operator.

### UG-12 — Admin Operasional: biaya barang masuk

- Isi biaya jika diketahui. Jika belum diketahui, biarkan kosong: Pemilik yang melengkapinya.
- Isi nol hanya jika barang itu memang tanpa biaya dan ada buktinya.

### UG-13 — Pemilik: lupa kata sandi

- Hubungi operator. Operator memastikan bahwa itu Anda — langsung, atau lewat telepon ke nomor yang tercatat saat pemasangan, dengan frasa pemulihan Anda — lalu memberikan tautan kata sandi langsung kepada Anda.

## 6. Open items

| Item | Subject | Step |
| --- | --- | --- |
| Gate G1 to G3 | The shop's tax position, product facts and counter practice, which shape the cutover and the pilot | Before the pilot |
| Gate RELEASE | The Owner names the operator and the pilot's start | Before the pilot |
