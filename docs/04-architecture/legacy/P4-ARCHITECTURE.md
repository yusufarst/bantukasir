# P4 — Arsitektur aplikasi dan database

**Baseline P0–P11.** Spesifikasi ini memilih stack untuk BantuKasir V1 dari nol. Ini keputusan planning yang direview, bukan bukti runtime, migration, benchmark, hardening atau production deployment. Riwayat Plan 2.0 diserap hanya sejauh requirement-nya tetap relevan; pilihan ini tidak mengikuti stack MULTIPLECORP secara otomatis.

## Konteks dan kriteria pemilihan

Aplikasi privat GOODS-only untuk satu bisnis dan satu stock pool, dengan tiga role tetap, transaksi stok/uang atomik, inventori immutable, MWA, cash accountability, dokumen dan recovery. UI React/shadcn memerlukan ergonomi POS, bukan public SEO atau platform frontend multi-channel. Tidak ada production application dari stack lama yang harus dimigrasi; prototype historis bukan production foundation.

Prioritas: waktu tercepat menuju build produksi yang kredibel; integritas/security yang dapat diuji; PostgreSQL nyata; self-hosting VPS; perawatan sederhana; dan sedikit dependency runtime. Kecepatan scaffold saja tidak cukup. Ledger, costing replay, privacy, races, recovery dan pembuktian perangkat tetap perlu dikerjakan pada kedua alternatif.

## Perbandingan minimum A dan B

| Kriteria | A — Next.js/TypeScript/PostgreSQL/Drizzle/self-hosted auth/Caddy | B — Laravel/Inertia/React/TypeScript/PostgreSQL/Nginx |
| --- | --- | --- |
| Foundation dari nol | Satu bahasa aplikasi; integrasi auth, DAL, validation, migration dan test harness dipilih serta dirangkai | Laravel menyediakan route/controller, validation, session, CSRF, authorization dan HTTP/database testing; starter resmi React memakai Fortify/Inertia/shadcn |
| UI operasional | React/shadcn langsung; RSC/server/client serialization perlu boundary yang disiplin | React/shadcn melalui Inertia; controller mengirim DTO eksplisit dan frontend memakai Vite; shared props/flash/history perlu boundary yang disiplin |
| Integritas PostgreSQL | Drizzle mendukung transactions/isolation; locks/constraints/idempotency tetap desain aplikasi dan SQL | DB transaction/ordered pessimistic locking tersedia; partial constraints/idempotency tetap desain aplikasi dan SQL |
| Auth dan keamanan | Library auth self-hosted, server-only DAL dan reauthorization harus diintegrasikan; Next.js bukan RBAC otomatis | Fortify/session dan middleware framework mengurangi integrasi dasar; policies/object scope/actor guards dan privacy tetap wajib dibuat |
| Angka exact | Decimal library dan canonical string diperlukan; JavaScript number tidak menjadi otoritas uang/qty | Brick Math dan canonical string diperlukan; PHP float/Eloquent float cast tidak menjadi otoritas uang/qty |
| Testing | Unit TS, PostgreSQL nyata, API/browser tests; connection/process harness races tetap perlu | Pest/PHPUnit, HTTP/database tests, PHP static analysis, frontend tests/Playwright; connection/process harness races tetap perlu |
| Deployment | Node application + Caddy + PostgreSQL; Caddy menyediakan certificate automation | PHP-FPM + Nginx + PostgreSQL; Composer/NPM pada build; TLS/ACME renewal harus disiapkan dan diuji eksplisit |
| Maintenance | Satu bahasa dan toolchain aplikasi, tetapi beberapa integrasi library keamanan/data perlu dipantau bersama | Dua bahasa dan dependency lockfiles; framework konvensional backend mengurangi variasi pola, dengan disiplin DTO PHP/TS |
| Infrastruktur tambahan | Queue/search service/Redis tidak dibutuhkan | Queue/search service/Redis/Octane/SSR Node tidak dibutuhkan |

**Dipilih B: Laravel + Inertia + React + TypeScript + PostgreSQL + Nginx**, sebagai modular monolith. Inference engineering: untuk aplikasi privat transaksi dari nol, pola backend terintegrasi mengurangi pekerjaan penghubung auth/session/validation/policies/forms/testing. Starter resmi mengakomodasi React/shadcn sehingga pemilihan backend tidak mengorbankan pipeline desain BantuKasir. PostgreSQL tetap memegang transaksi, constraints, locking dan fakta immutable.

**A ditolak sebagai baseline aktif, bukan karena tidak aman atau tidak mampu.** Kekuatan satu bahasa dan automatic HTTPS Caddy nyata. Namun B memberi foundation backend yang lebih terpadu untuk ruang lingkup ini; keuntungan SSR/RSC Next.js tidak menjadi requirement produk. Tidak ada investasi production source A yang membenarkan mempertahankannya. Tidak ada klaim bahwa Laravel otomatis mencegah double Sale, kebocoran cost atau oversell.

### Dampak kecepatan dan biaya tradeoff

Estimasi judgement sebelum Build Units: B dapat menghemat sekitar **8–16 jam engineering netto** terhadap A pada foundation dan integrasi konvensional, setelah memperhitungkan sebagian biaya PHP/TypeScript serta deployment. Ini bukan benchmark, komitmen jadwal, jam implementasi yang sudah terjadi, ataupun penghematan pada ledger/recovery. Ketidakbiasaan executor terhadap PHP/Inertia, konflik dependency, atau target VPS dapat menghapus keuntungan tersebut.

P11 menghitung ulang seluruh effort dari specification yang dibekukan, dengan implementation, verification/review/evidence, risk reserve dan owner/external waiting terpisah. Estimasi 40 task Plan 2.0 bukan baseline otomatis. Testing PostgreSQL nyata, seluruh privacy channels, final cross-surface checks, backup/restore, actual scanner/printer dan pilot tidak boleh dihapus untuk mengejar penghematan.

Tradeoff yang diterima: PHP dan TS; Composer serta NPM lockfiles; PHP-FPM/Nginx tuning; certificate renewal runbook; dan kontrak DTO lintas bahasa. Tidak ada biaya SaaS auth/backend/frontend yang diperlukan oleh stack ini. Backup independen, VPS, domain, storage dan entitlement desain tetap mempunyai biaya nyata yang harus diverifikasi, bukan diasumsikan gratis.

## Pembekuan dan pin versi

Stack dibekukan setelah independent review paket planning. Reopening memerlukan evidence incompatibility/security/operasional yang relevan, decision log, impact terhadap specification/Build Units/tests dan review; preferensi executor sendiri tidak cukup.

Sumber resmi Laravel 13.x saat planning mengonfirmasi kombinasi React/Inertia/TypeScript/shadcn/Fortify. **Nomor versi deployment dan dependency patch belum diinstal atau diverifikasi pada VPS.** Build Unit foundation pertama wajib memeriksa support window/security advisories dan compatibility Laravel/PHP/extensions/Fortify/Inertia/React/TypeScript/Vite/Tailwind/shadcn/PostgreSQL/Nginx/Brick Math/testing, kemudian mem-pin versi/image serta `composer.lock` dan lockfile NPM. Tidak menggunakan production `latest`; tidak menyalin lockfile proyek lain. Pin versi yang kompatibel tidak membuka kembali pilihan stack tanpa alasan material.

## Struktur modular minimal

Laravel tetap menggunakan routing/controller/middleware, Eloquent/query builder, migrations, jobs/commands yang relevan dan React pages. Tidak perlu framework DDD tambahan, generic repository abstraction untuk setiap tabel, event bus, CQRS penuh, microservices, broker, distributed lock service atau runtime SSR.

Target struktur:

- `app/Domain/<Module>`: aturan, value objects dan perhitungan domain murni yang benar-benar diperlukan.
- `app/Actions/<Module>`: command/application service yang menjadi boundary unit kerja.
- `app/Http/Controllers`, `app/Http/Requests`, `app/Policies`: HTTP adapters, allowlist input dan authorization/object scope.
- `app/Models`, `app/Queries`, `app/DTO`: persistence models, query terikat scope, dan response allowlists; model tidak dikirim mentah ke Inertia.
- `resources/js/pages`, `resources/js/components`, `resources/js/layouts`, `resources/js/types`: UI React/TS dan kontrak presentasi.
- `database/migrations`, `tests`, `ops`: forward schema, actual tests dan operational packaging/runbooks.

| Modul | Milik modul dan boundary |
| --- | --- |
| Identity/Access | User individual, server session, role/capability, object scope, bootstrap/reset/revoke, recent authentication dan actor guard |
| Business Configuration | Runtime BusinessProfile/immutable identity revision, assets/templates/accent terbatas, CashRegister configuration; config guard |
| Catalog | Product/category/brand opsional/base unit/selling-price revision/barcode; duplicate safety/search/label; create master tidak membuat stok |
| Inventory | Immutable movement/lines/source/reason/posting sequence; synchronous balance; receiving/opening/manual issue/adjustment/reversal/LOW/OUT |
| Costing | Acquisition evidence, KNOWN/UNKNOWN, MWA valuation, issue allocations, bounded deterministic replay dan publication revision atomik; owner-only reads |
| Sales/Payment | Authoritative sale lines/full payment, command identity, original-result recovery, bounded owner refund/return; memanggil Inventory/Costing dalam transaksi yang sama |
| Cash Accountability | Register availability, satu responsible cashier, opening cash eksplisit, OPEN/CLOSED, activity version, race-safe close dan immutable report |
| Documents | Safe ReceiptSnapshot/session report/linked correction document, identity/template/assets immutable, label/thermal/A4/browser PDF/CSV; render setelah commit |
| Reporting/Audit | Scoped operational history/owner gross-profit read snapshot, period/completeness, append-only audit; session totals tidak menghitung Sale dua kali |
| Operations/Recovery | Privilege-separated migration/backup/reconciliation/isolated restore, recovery epoch/gap review, safe status dan maintenance runbook |

Separation adalah tanggung jawab kode dalam satu proses/aplikasi dan satu PostgreSQL, bukan alasan memecah transaksi lintas layanan. Controller tipis memanggil Action; Action memegang satu transaction context yang diteruskan ke domain services. Tidak ada nested service yang membuka koneksi atau commit independen untuk bagian Sale. Eloquent observer/event tidak menjadi tempat tersembunyi bagi stock/payment/audit atomik.

## Schema, immutability dan constraints

Persyaratan inti tetap berlaku pada PostgreSQL; schema dan aplikasi saling melindungi:

- Unik normalized SKU, global barcode/token yang tidak dipakai ulang, Sale source intent, scoped command key/fingerprint/result, movement source intent, Sale number, satu full Payment dan satu ReceiptSnapshot per completed Sale.
- Partial unique index membatasi satu `OPEN` per CashRegister dan per responsible cashier. Semua completed Sale mereferensikan eligible CashSession milik actor.
- FK/check untuk source references, quantity/amount valid, balance/opening/physical cash nonnegative, immutable unit semantics setelah movement pertama dan status/type sah.
- Tepat satu immutable report untuk sesi CLOSED; sumber/source IDs, cutoff/activity version serta hasil close konsisten secara atomik.
- Cumulative refund money/return qty dibatasi terpisah di bawah lock Sale asli; sumber original tidak ditulis ulang.
- StockMovement/Sale/lines/Payment/dokumen/source cost/audit facts append-only. Revisi biaya dan correction memakai fakta baru serta pointer/projection publication yang eksplisit, bukan historical rewrite.

Database runtime privilege memisahkan tabel fakta immutable dari projection yang memang dapat berubah. Runtime tidak mempunyai DDL/drop atau update/delete immutable facts. Migration dan backup memakai credential terpisah. Invariant lintas tabel/aggregate dijaga oleh ordered transaction guards serta constraints yang sesuai; tidak mengklaim check constraint tunggal dapat memeriksa seluruh aggregate.

Gunakan migration additive/forward. Raw PostgreSQL SQL dalam migration diperbolehkan untuk partial indexes/constraints/privilege enforcement yang tidak diekspresikan tepat oleh schema builder. Review SQL hasil migration dan test actual PostgreSQL, termasuk rollback/failure injection serta privilege denial. Tidak ada production reset/reseed atau `migrate:fresh` terhadap data bisnis. Populated migration rehearsal harus pada target isolated yang diidentifikasi positif.

## Angka dan waktu exact

Uang payable memakai integer IDR; PostgreSQL integer/bigint dengan batas yang tervalidasi. Quantity memakai PostgreSQL `numeric` dengan base-unit precision 0–3, tunduk pada konfirmasi unit bisnis. Cost memakai `numeric` dengan enam tempat desimal. Schema Build Unit menetapkan storage precision/magnitude maxima yang koheren dengan batas multiplication, aggregation dan overflow; penerimaan tidak cukup hanya memilih nama tipe kolom.

Domain PHP menggunakan Brick Math `BigDecimal`/`BigInteger` atau representasi integer exact yang memenuhi batas. Input/output melintasi HTTP/JSON sebagai canonical decimal/integer strings; tidak melalui PHP float, JS `number`, Eloquent float cast atau parse/display yang menghilangkan precision. UI boleh memformat string untuk tampilan, sementara backend menghitung ulang seluruh hasil otoritatif. Batas length/scale/magnitude diperiksa sebelum operasi decimal mahal.

Payable line = quantity × authoritative unit price, round half-up sekali per line lalu jumlahkan. MWA mempertahankan cost enam desimal, issue terakhir mengambil valuation residual, UNKNOWN bukan zero, dan penambahan evidence mem-publish hasil replay baru hanya setelah lengkap. Refund tanpa physical return tidak memulihkan cost atau stock. Return saleable menggunakan historical matched cost/UNKNOWN, bukan average terbaru secara naif.

Waktu fakta berasal dari server UTC; business date/period boundaries memakai Asia/Jakarta. Report range half-open UTC berasal dari tanggal lokal inklusif yang dipilih. Dokumen/source revision mempertahankan waktu/identity asli. Tanggal input opsional tidak mengotorisasi backdating. Formatting manusia mengikuti kontrak desain; fakta yang tersimpan tidak bergantung pada locale browser.

## Transaksi, locking dan original-result recovery

Default `READ COMMITTED`, dengan urutan guard bersama:

`command identity → actor/config guards terurut → register → CashSession → Sale asli jika correction → product guards terurut ID → balance/cost projection → append facts/audit/result`

Setiap penulis actor disable/revoke/config serta command bisnis menggunakan guard yang kompatibel. Urutan multi-product stabil. Produk/master harga/version/role dan session eligibility diperiksa ulang di bawah guard saat confirm, bukan hanya saat halaman dibuka.

`DB::transaction` dan `lockForUpdate` adalah sarana, bukan keseluruhan solusi. Pembukaan sesi mengunci register; checkout/close mengunci session yang sama. Close memakai activity version dari preview; Sale menang dahulu membuat close meminta recount, close menang dahulu menolak Sale sebelum payment/issue. Tidak ada lock selama user menghitung kas, external bank action, printing atau network call.

Command atomik mengikat actor + operation + stable key + server fingerprint + source intent + recovery epoch dengan hasil durable. Sama key/payload memulihkan hasil awal; key konflik ditolak; source intent melindungi duplikasi ketika key berubah. Sale/lines/full Payment/SALE_ISSUE/balance/cost allocation atau UNKNOWN/ReceiptSnapshot/audit/result commit bersama. Inventory dan close mempunyai atomic facts/projection/result boundary yang sama ketatnya.

Deadlock retry dibatasi, memakai command/source identity asli dan memulihkan committed result. Retry tidak mengulangi human payment atau printing. Expected UNIQUE conflict ditangani setelah rollback dengan original-result query yang diotorisasi, bukan meneruskan transaksi PostgreSQL yang sudah aborted. UNCERTAIN membekukan intent awal; tidak membuat replacement Sale. Restore epoch menolak blind execution intent lama yang hasilnya tidak ada pada backup.

## Payment boundary dan kesiapan provider kelak

**Owner decision V1:** satu completed Sale mempunyai satu pembayaran penuh, **CASH atau BANK_TRANSFER**. Tidak ada split/mixed/partial payment, installment/credit, pending completed Sale, gateway/QRIS/webhook/callback/async settlement. Transfer dicatat setelah cashier mengonfirmasi operational receipt of funds; aplikasi tidak mengklaim verifikasi bank.

`Sales` memiliki finality, line totals, unique source intent, fully-paid invariant dan stock issue. `Payment` memiliki method/value/tender/change atau operational transfer reference/confirmation. Domain code memakai tipe method yang eksplisit dan allowlist server V1; tidak menyebarkan string label/metode atau branch provider di semua modul.

Future provider seam adalah **batas arsitektur**, bukan kelas stub, tabel kosong, polling state, external call atau fitur V1. Bila gateway kelak diotorisasi, provider adapter/attempt/reference/status/metadata dapat ditambahkan melalui forward extension di luar Sale core. Verification dan external side effects terjadi di luar transaksi bisnis; adapter menyerahkan verified outcome pada command finalization yang tetap mempertahankan full-payment atomic Sale invariant. Schema/constraints/allowlist dan workflow yang perlu berubah direview dalam scope baru.

External provider transaction reference, webhook signatures dan asynchronous attempts tidak dicampur dengan V1 transfer reference atau Payment fact yang sudah completed. Tidak mengendurkan uniqueness satu full Payment demi kesiapan hipotetis. Pilihan string/value object method bukan jaminan gateway siap pakai; hanya mencegah coupling yang memerlukan penulisan ulang integritas Sale.

## Keamanan dan privacy implications

Fortify self-hosted/server session mengikuti library framework; database session driver tidak memerlukan Redis. Disable public registration, teams/tenant, WorkOS/social auth, 2FA dan fitur starter lain yang tidak termasuk V1. Bootstrap owner privat sekali pakai, bukan default password/demo account. Password hashing memakai Argon2id/library tervalidasi dan konfigurasi kapasitas; session regeneration/revocation, expiry, login/reset throttling serta recent-auth owner action benar-benar diuji.

FormRequest memakai allowlist dan validasi server; Policies memeriksa capability/object scope, Action memeriksa actor/config guard di transaksi. Session/cookie `HttpOnly`/`Secure`/`SameSite`, CSRF/origin, secure headers/CSP dan trusted proxy config tidak dianggap lulus hanya karena middleware terpasang. Endpoint history/status/recovery/print/export/media memeriksa authorization ulang.

Data cost/HPP/profit hanya SUPER_ADMIN; Operations boleh mengirim incoming evidence baru write-only. **Laravel/Inertia tidak otomatis memenuhi write-only:** input sensitif tidak masuk flashed old input, session error bags, shared props, redirect flash, serialized models, browser history/recovery storage/cache/telemetry/log. Setelah failure UI meminta input ulang atau memakai opaque server recovery reference sesuai status; sesudah commit field dibersihkan dan response tetap DTO aman. Negative tests harus memeriksa actual payload plus redirect/session/history/cache/export/media/error channels, bukan hanya tampilan layar.

Inertia shared props berisi minimum account/navigation context yang diizinkan. Gunakan DTO/read-model allowlist per role dan object; `$hidden` pada Eloquent bukan satu-satunya privacy boundary. Semua frontend bundle, query/cache key, document/media URL dan rendering output tetap diuji untuk no-leak. Tidak ada data sensitif atau credential di `VITE_*` public variables. Runtime branding/assets mengikuti authorization/versioning/private storage, bukan repository logo codename.

## Testing implications

- PHP unit/Pest atau PHPUnit: value objects/decimal/half-up/residual/UNKNOWN transitions, guard decisions, safe DTO, period calculations dan domain invariants. Type/static analysis PHPStan/Larastan serta Pint sesuai dependency yang dipin.
- Feature/HTTP tests dengan actual PostgreSQL: authenticated permissions, object scope, validation, database constraints/partial uniqueness, raw migration, transaction rollback/atomic results, immutable privilege denial dan safe error serialization.
- Concurrency tests dengan sedikitnya dua independent connections/processes serta committed initial fixture: last-unit sale/issue, same/different key+source intent, session open/close/checkout, actor/config revoke race, refund/return bounds serta replay publication. Test barrier mengontrol interleaving dan menguji seluruh facts/projection/result sebelum/sesudah.
- Jangan menggunakan SQLite atau mock sebagai bukti PG locking/constraints. `RefreshDatabase` dapat membungkus test dalam transaction; fixture yang belum commit tidak menjadi bukti request/process lain melihat kondisi yang sama. Harness race/rollback harus memakai isolation/reset fixture pada database test yang diotorisasi, tanpa outer transaction yang menyamarkan commit boundary.
- Frontend TS/typecheck, targeted Vitest untuk perilaku UI nontrivial, lint/production build dan Playwright terhadap server nyata: tiga role, denied routes/guessed IDs, sensitive channels, scan/cart/confirm/UNCERTAIN/reload, custom periods, responsive/keyboard/touch/a11y dan printed snapshots. Mock UI tidak memperoleh credit transaction proof.
- Final migration/restore/recovery, cross-surface no-leak, actual devices, measured performance dan owner pilot memakai current final revision. Test awal bukan FINAL PASS; perubahan sesudah bukti final memerlukan rerun sesuai risiko.

Tidak perlu dua browser E2E stacks sekaligus; Playwright menjadi satu default lintas role. PHP feature tests menguji server contracts; React tests hanya perilaku UI yang memerlukan verifikasi. P8 dan P11 menetapkan acceptance/evidence ownership, bukan checklist paralel yang saling menggandakan kelulusan.

## Deployment, operasi dan maintenance

VPS target self-hosted memakai Docker Compose atau packaging setara yang dibekukan di Build Unit operasi: PHP-FPM aplikasi non-root, Nginx web root `public`, PostgreSQL privat, scheduled backup/reconciliation commands. Node hanya build tooling; tidak ada runtime SSR Node, websocket service, Redis, generic worker atau queue dalam V1. Jangan menyalin default starter dev processes ke production.

Nginx/TLS memerlukan certificate issuance/renewal automation, expiry monitoring, verified redirect/HTTPS/headers/trusted proxies dan reload procedure. ACME/backup commands adalah bounded scheduled operations, bukan alasan membangun job platform. Public directory saja served; deny source/env/private storage; `APP_DEBUG=false`, secret `APP_KEY` runtime privat dan config cache diperbarui sesuai deployment.

Controlled migration job memakai credential terpisah dan dieksekusi sekali; tidak berlomba pada startup web containers. Versioned immutable image menyertakan dependency/asset build yang cocok. PHP/Nginx memory/process/body/time limits diverifikasi terhadap load dan bounded replay; jangan memperpanjang request tanpa batas agar replay tampak berhasil. Replay oversize menolak publication dengan incomplete status yang jujur.

PostgreSQL dump/independent encrypted destination, referenced logo/template/assets manifest, isolated restore, reconciliation dan epoch/gap review mempertahankan seluruh fakta. Same-host backup tidak independen. Restore key/access tetap di luar host sumber; measured RPO/RTO/retention/cost diterima owner sebelum release. Failure/stale status tidak tampil sebagai sukses statis.

Maintenance memantau Laravel/PHP/Fortify/Inertia/React/PostgreSQL/Nginx/decimal/test advisories dan kompatibilitas lockfiles; update terkendali dengan regression serta populated migration rehearsal bila relevan. Migration/data facts tidak dipulihkan dengan reset. Dua bahasa membutuhkan DTO contract review dan fixtures bersama; otoritas uang/stok hanya server, sehingga browser tidak menduplikasi financial ledger engine.

## Sumber primer yang diverifikasi saat planning

Fakta framework berikut berasal dari dokumentasi resmi yang dibaca pada planning. Perbandingan waktu/keputusan suitability di atas adalah inference engineering untuk scope BantuKasir, bukan klaim benchmark dari sumber.

- [Laravel starter kits](https://laravel.com/framework/docs/13.x/starter-kits): React/Inertia/TypeScript/shadcn dan Fortify, konfigurasi fitur auth.
- [Laravel database](https://laravel.com/framework/docs/13.x/database): PostgreSQL, transaksi dan bounded deadlock retry.
- [Laravel query builder](https://laravel.com/framework/docs/13.x/queries): pessimistic `lockForUpdate` dalam transaction.
- [Laravel database testing](https://laravel.com/framework/docs/13.x/database-testing): database testing dan outer-transaction behavior `RefreshDatabase`.
- [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment): Nginx/server requirements/debug/config deployment.
- [Next.js data security](https://nextjs.org/docs/app/guides/data-security): server-only DAL, safe DTO dan authorization untuk mutations.
- [Next.js self-hosting](https://nextjs.org/docs/app/guides/self-hosting): reverse proxy dan self-hosted operational boundaries.
- [Drizzle transactions](https://orm.drizzle.team/docs/transactions): atomic transaction APIs dan PostgreSQL isolation configuration.
- [Caddy automatic HTTPS](https://caddyserver.com/docs/automatic-https): automatic certificate management.
- [PostgreSQL numeric types](https://www.postgresql.org/docs/current/datatype-numeric.html): exact numeric dibanding floating point.
- [Brick Math](https://github.com/brick/math): arbitrary-precision immutable decimal/integer arithmetic dan explicit rounding.

Tidak ada instalasi dependency, akses database/infrastructure, akses project MULTIPLECORP atau perubahan desain yang dilakukan untuk menyusun P4. Spesifikasi ini belum mengotorisasi coding Build Unit pertama atau deployment.


## History Inertia, pergantian actor dan privasi browser

Enkripsi history autentikasi Inertia wajib dengan mekanisme versi yang dipin. Clear/rotate history keys pada logout, pergantian actor dan revoke yang teramati; bersihkan remembered forms/query cache/client state serta gunakan response sensitif private/no-store. Halaman sensitif yang kembali melalui Back/Forward/bfcache harus memvalidasi ulang actor/permission sebelum menampilkan cached data, sehingga akun staff tidak melihat finance Pemilik sebelumnya. Server tetap mengotorisasi semua request; client history protection bukan pengganti policies. Incoming receiving cost write-only tetap NEVER persisted, walaupun history lain dienkripsi. AUTH02/SEC01 dan IAM/UI-001/VERIFY-002 menguji Pemilik→logout→staff→Back/Forward/reload/bfcache. [Official Inertia history encryption](https://inertiajs.com/docs/v3/security/history-encryption) mendasari kontrol ini; API versi aktual diverifikasi saat foundation.

Cache Laravel database-backed digunakan hanya untuk rate limiter/config yang aman dan scope-nya diuji; bukan penyimpanan sensitive response/shared staff cost. Tidak memerlukan Redis default.
