# P6 — Concurrency, idempotency, API dan performance

[02](../02-ARCHITECTURE.md) adalah otoritas transaksi/lock; klausul domain di 06/15/17 dan [08](../08-TESTING-ACCEPTANCE.md) adalah otoritas evidence. Laravel Actions pada P4 menjalankan seluruh command uang/stok; tidak ada HTTP side effect, queue atau human wait dalam transaksi database.

## Kontrak HTTP dan command

Route Laravel same-origin yang terautentikasi memakai FormRequest → policy/object scope → Action → response DTO aman. Read list/search/history/report/media/print/status selalu dibatasi scope dan memakai pagination. Envelope command minimal memuat operation/version, sourceIntentId, idempotencyKey, recoveryEpoch, expected domain versions dan input bisnis bertipe. Actor/price/total/evidence scope/fingerprint berasal dari server; bukan client yang dipercaya.

CASH dan BANK_TRANSFER adalah implementasi metode eksplisit pada allowlist V1; metode unknown/mixed/partial ditolak. Boundary provider masa depan pada P4 tidak membutuhkan dummy gateway controller, webhook, tabel pending payment atau status asynchronous.

Hasil transport: 200/201 untuk hasil original yang committed; 401 expired; 403 denied; 404 objek tidak dapat diakses dengan error aman sesuai scope; 409 untuk konflik fingerprint/stale/eligibility/source/epoch; 422 untuk domain rejection tervalidasi sebelum fakta ditulis; 429 throttled. 5xx atau koneksi terputus bukan definite failure. Gunakan error code aman dan stabil tanpa field privat. Response 200/redirect/flash tidak boleh memantulkan old input sensitif. Idempotency key/source identity dipertahankan bersama fakta, bukan cache sementara.

POST receiving/opening/manual issue/adjustment/reversal/Sale/open-close/refund-return/config mempunyai consumer review/confirm eksplisit. GET/search/scan/preview/print tidak memposting fakta bisnis. Endpoint status mengembalikan COMMITTED atau status original yang aman; record yang hilang dapat tetap UNCERTAIN/memerlukan review incident dan bukan izin membuat replacement, terutama setelah restore. Retry deadlock/serialization maksimal 3 attempt dengan envelope/key stabil serta backoff bounded, hanya untuk pekerjaan internal transaksi; jangan mengulang payment/printer/efek manusia eksternal.

## Matriks race dan disiplin lock

Urutan lock bersama pada READ COMMITTED: command identity → guard actor/config dalam urutan stabil → register → session → original Sale untuk correction → guard product dalam urutan ID → balance/cost → append fakta/audit/result. Lewati guard yang tidak relevan; jangan membalik urutan. Writer lifecycle/config/product/price/onboarding memakai guard yang sesuai; retry bukan bypass permission.

| Race | Hasil wajib |
| --- | --- |
| Key sama/source sama, key berbeda/source sama | Tepat satu original; fingerprint sama mengembalikan original; payload berubah menghasilkan conflict |
| Dua kasir/issue atas unit terakhir | Satu accepted, lainnya insufficient stock; tidak ada balance negatif |
| Disable/revoke/config/freeze melawan command | Pemenang guard menentukan otoritas aktual yang valid; command revoked tidak menulis fakta |
| Edit unit product melawan movement pertama | Unit/precision tidak mengubah arti fakta yang sudah diposting |
| Opening dengan baris/command product duplikat | Tidak ada ledger sebelumnya; sekali; ≤100 baris all-or-nothing; nilai zero hanya checklist |
| Open register/session | Partial unique OPEN per register DAN per cashier; tidak ada tanggung jawab bersama |
| Sale melawan close | Lock session/activityVersion sama; Sale masuk dan membutuhkan recount, atau close menang dan Sale ditolak |
| Duplicate close/count conflict | Satu CLOSED/snapshot; retry ekuivalen mengembalikan original; count berubah tidak overwrite |
| Refund/return/reversal bersamaan | Lock original Sale/source, batas kumulatif uang/quantity terpisah, reversal sekali |
| Replay melawan posting/revision return cost | Source/allocation stabil dan versioned, busy wait/reject, publikasi all-or-nothing; chronology invalid ditolak |
| Restore melawan stale intent | Epoch baru/session revoke; result yang tidak ada pada restore bukan bukti belum pernah committed |

PostgreSQL nyata memakai dua connection/process dan fixture committed di luar satu wrapper transaksi wajib. Failure injection pada setiap write domain menunjukkan rollback fakta/projection/audit/result. Gunakan constraint dan lock test PostgreSQL, bukan mock checks.

## Batas angka, input dan query

JSON canonical untuk money/cost/quantity memakai decimal string, bukan binary floating point atau coercion JS Number/PHP float. Quantity mengikuti precision unit 0–3; harga/payable memakai IDR integer dengan half-up sekali per baris; biaya memakai enam desimal/residual. BU schema mem-pin precision angka/maksimum storage dan batas validasi decimal yang sesuai; test boundary/maksimum/overflow dilakukan sebelum lock. Request maksimal 100 baris product berbeda dan opening maksimal 100 baris; batas teks/control/body/upload divalidasi. Search default 25/maksimal 100 dengan urutan stabil; index SQL native diukur, bukan seluruh master dikirim ke client.

Custom range laporan memakai tanggal lokal inclusive → awal hari berikutnya exclusive UTC/Jakarta. Validasi start > end dan kebijakan future date ditampilkan; draft Apply/Cancel terpisah. Range maksimal 366 hari per query untuk V1 interaktif; rentang lebih besar harus dipersempit secara eksplisit, bukan fetch seluruh histori yang mahal. Dataset benchmark: 5.000 product/100.000 movement lines/dua cashier; barcode p95 ≤500 ms, search ≤700 ms, Sale 50 baris ≤2 s, laporan periode biasa ≤2 s. Target belum diukur. Referensi replay 10.000 movement/product; cap/waktu/memori dinilai di BU-VERIFY-004. Kegagalan cap menghasilkan laporan incomplete/tanpa publikasi partial. Jangan melemahkan integrity demi target latensi.