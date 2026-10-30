<?php

// Interface dictionary, file commands (ADR-005; COPY_DECK section 1.1):
// unresolved requests and recovery. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them; TASK-009 adds the page of
// unresolved requests and the shell's item and banner (sections 4.1, 6.1 and
// 6.7), the family labels and panel texts and the results of "Cek status"
// (section 14). A family's panel text is the child of its label, which is a
// text itself, so it is written at the top of the file as one dotted key
// (OPEN_ITEMS T-29; App\Support\Localization\Dictionary).

return [
    'unresolved' => [
        'title' => 'Permintaan belum pasti',
        'menu_item' => 'Permintaan belum pasti (:jumlah)',
        'banner' => 'Ada permintaan yang statusnya belum pasti.',
        'intro' => 'Hasil permintaan berikut belum diketahui. Cek statusnya, dan jangan mengulang pekerjaan yang sama sebelum hasilnya diketahui.',
        'row' => ':jenis · dikirim :waktu',
        'empty' => 'Tidak ada permintaan yang statusnya belum pasti.',
    ],
    'requests' => [
        'other_user' => 'Permintaan :nama',
        'other_user_help' => 'Cek status permintaan ini atas nama Pemilik. Jika belum tersimpan, permintaan ini tidak akan pernah tersimpan.',
        'from_server' => 'Dikirim dari perangkat lain atau sebelum data peramban dihapus.',
        'lost_help' => 'Jika permintaan tidak ada di daftar ini dan tidak ada di riwayat, tunggu :menit menit sejak dikirim. Setelah itu permintaan itu pasti tidak tersimpan dan Anda dapat mulai lagi.',
    ],
    'resolution' => [
        'committed' => 'Sudah tersimpan.',
        'committed_link_hidden' => 'Sudah tersimpan, tetapi tautannya tidak bisa ditampilkan lagi. Jika perlu, buat tautan baru di Detail pengguna.',
        'in_progress' => 'Masih diproses. Cek lagi sebentar lagi.',
        'not_committed' => 'Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.',
        'no_answer' => 'Cek status belum mendapat jawaban. Statusnya tetap belum pasti; cek lagi sebentar lagi.',
    ],
    'family' => [
        'sale' => 'Penjualan',
        'open_session' => 'Buka sesi kas',
        'close_session' => 'Tutup sesi kas',
        'refund_drawer' => 'Pengembalian dari laci kas',
        'refund_other' => 'Pengembalian uang dan retur',
        'stock_posting' => 'Barang masuk, barang keluar, atau penyesuaian stok',
        'opening_stock' => 'Stok awal',
        'reversal' => 'Pembatalan mutasi',
        'cost_correction' => 'Koreksi biaya',
        'product_create' => 'Tambah produk',
        'product_edit' => 'Perubahan produk',
        'category' => 'Kategori',
        'photo' => 'Foto produk',
        'barcode' => 'Barcode',
        'barcode_correction' => 'Koreksi salah pasang barcode',
        'csv_import' => 'Impor produk (CSV)',
        'onboarding' => 'Masa input stok awal',
        'profile' => 'Profil usaha',
        'register' => 'Laci kas',
        'users' => 'Pengguna',
        'reopen' => 'Buka kembali mutasi',
        'review_note' => 'Catatan tinjauan',
        'edit' => [
            'must_not' => 'Jangan mengirim perubahan lain sampai hasil ini diketahui.',
        ],
    ],
    'family.sale.must_not' => 'Jangan minta pelanggan membayar atau mentransfer lagi. Penjualan ini mungkin sudah tersimpan.',
    'family.open_session.must_not' => 'Jangan membuka sesi kas lagi dengan permintaan baru.',
    'family.close_session.must_not' => 'Jangan hitung ulang laci atau tutup sesi ini lagi. Sesi kas ini mungkin sudah ditutup.',
    'family.refund_drawer.must_not' => 'Jangan ambil uang dari laci sampai pengembalian ini tercatat. Jangan buat pengembalian baru.',
    'family.refund_other.must_not' => 'Cek status pengembalian ini dan periksa bukti pembayarannya dulu. Jangan membayar lagi atau mencatat pengembalian baru.',
    'family.stock_posting.must_not' => 'Jangan mencatat barang masuk, barang keluar, atau penyesuaian baru sampai hasil ini diketahui.',
    'family.opening_stock.must_not' => 'Jangan memasukkan baris yang sama lagi.',
    'family.reversal.must_not' => 'Jangan membatalkan mutasi ini lagi.',
    'family.cost_correction.must_not' => 'Jangan membuat koreksi baru untuk biaya yang sama sampai hasil ini diketahui.',
    'family.product_create.must_not' => 'Jangan menambahkan produk yang sama lagi.',
    'family.barcode_correction.must_not' => 'Jangan mengirim koreksi lain sampai hasil ini diketahui.',
    'family.csv_import.must_not' => 'Jangan mengimpor berkas yang sama lagi.',
    'family.onboarding.must_not' => 'Jangan menjalankan tindakan sebaliknya sampai statusnya diketahui.',
    'family.users.must_not' => 'Jangan membuat akun atau tautan kata sandi lain sampai hasil ini diketahui.',
    'family.reopen.must_not' => 'Jangan membuka kembali mutasi ini lagi.',
    'family.review_note.must_not' => 'Jangan menulis catatan ini lagi.',
];
