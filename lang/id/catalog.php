<?php

// Interface dictionary, file catalog (ADR-005; COPY_DECK section 1.1):
// products, categories, barcodes, labels, CSV import. Its keys are copied
// verbatim from COPY_DECK by the first Task that uses them. TASK-017 adds those
// of its pages (COPY_DECK sections 8.1 to 8.5 and 8.8).

return [
    'field' => [
        'sku' => 'SKU',
        'name' => 'Nama',
        'category' => 'Kategori',
        'brand' => 'Merek',
        'unit' => 'Satuan',
        'unit_help' => 'Satuan untuk stok dan penjualan, misalnya pcs, rim, atau kg.',
        'precision' => 'Jumlah desimal',
        'precision_help' => '0 untuk barang yang dihitung utuh. 1 sampai 3 untuk barang yang bisa dijual sebagian, misalnya 1,5 kg.',
        'unit_lock_help' => 'Satuan dan jumlah desimal tidak bisa diubah setelah produk punya mutasi stok.',
        'selling_price' => 'Harga jual',
        'min_stock' => 'Stok minimum',
        'min_stock_help' => 'Produk ditandai Menipis bila stoknya lebih dari 0 dan tidak lebih dari angka ini.',
        'barcode' => 'Barcode',
        'manufacturer_barcode' => 'Barcode pabrik',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
    ],
    'photo' => [
        'thumbnail_alt' => 'Foto :produk',
        'placeholder_alt' => 'Belum ada foto',
    ],
    'products' => [
        'title' => 'Produk',
        'search' => 'Cari produk',
        'search_hint' => 'Scan barcode, atau ketik nama atau SKU',
        'empty' => 'Belum ada produk.',
        'empty_action' => 'Tambah produk pertama, atau impor dari CSV.',
    ],
    'product_detail' => [
        'title' => 'Detail produk',
        'subtitle' => ':produk',
        'section_data' => 'Data produk',
        'section_price' => 'Harga jual',
        'section_barcodes' => 'Barcode',
        'price_history' => 'Riwayat harga jual',
        'price_entry' => ':uang · mulai :waktu · oleh :nama',
    ],
    'product' => [
        'deactivate_confirm' => [
            'title' => 'Nonaktifkan :produk?',
            'body' => 'Produk nonaktif tidak bisa dijual, diterima, dikeluarkan, atau disesuaikan, tetapi tetap tampil di riwayat dan dokumen. Anda dapat mengaktifkannya kembali.',
        ],
        'reactivate_confirm' => [
            'title' => 'Aktifkan kembali :produk?',
            'body' => 'Produk ini bisa dijual dan dicatat lagi.',
        ],
        'deactivated' => 'Produk dinonaktifkan.',
        'reactivated' => 'Produk diaktifkan kembali.',
    ],
    'barcode' => [
        'kind_manufacturer' => 'Barcode pabrik',
        'kind_internal' => 'Barcode internal',
        'status_active' => 'Aktif',
        'status_retired' => 'Dipensiunkan',
        'retired_reason' => 'Dipensiunkan · :alasan',
        'add_manufacturer' => 'Tambah barcode pabrik',
        'token_help' => 'Scan atau ketik barcode dari kemasan.',
        'save' => 'Simpan barcode',
        'added' => 'Barcode ditambahkan.',
        'generate_internal' => 'Buat barcode internal',
        'generate_help' => 'Untuk barang tanpa barcode pabrik. Cetak labelnya dengan Cetak label barcode.',
        'generated' => 'Barcode internal :kode dibuat.',
        'retire' => 'Pensiunkan barcode',
        'retire_confirm' => [
            'title' => 'Pensiunkan barcode :kode?',
            'body' => 'Barcode yang dipensiunkan tidak bisa dipakai lagi, kecuali Pemilik memasangnya ke produk yang benar karena salah pasang. Pilih alasannya.',
            'body_internal' => 'Barcode internal yang dipensiunkan tidak pernah dipakai lagi. Pilih alasannya.',
        ],
        'reason_misassignment' => 'Salah pasang',
        'reason_misassignment_help' => 'Barcode ini terpasang pada produk yang salah. Pemilik dapat memasangnya ke produk yang benar dengan Koreksi salah pasang barcode.',
        'reason_other' => 'Alasan lain',
        'reason_other_help' => 'Misalnya kemasan berganti atau barcode tidak dipakai lagi. Barcode ini tidak bisa dipakai lagi.',
        'retired' => 'Barcode dipensiunkan.',
    ],
    'product_create' => [
        'title' => 'Tambah produk',
        'step_search' => 'Cari dulu',
        'search_intro' => 'Periksa dulu apakah produk ini sudah ada, agar tidak tercatat dua kali.',
        'check' => 'Cek',
        'exact_match' => 'Produk dengan barcode atau SKU ini sudah ada:',
        'create_anyway' => 'Tetap buat produk baru',
        'nothing_found' => 'Tidak ada produk yang sama atau mirip.',
        'continue' => 'Lanjut isi data',
        'zero_stock_help' => 'Produk baru mulai dengan stok 0. Stoknya bertambah lewat Barang masuk.',
        'save' => 'Simpan produk',
        'success' => 'Produk :produk ditambahkan.',
    ],
    'product_edit' => [
        'title' => 'Ubah produk',
        'price_change' => 'Harga lama :lama → harga baru :baru',
        'price_help' => 'Harga baru berlaku untuk penjualan berikutnya. Keranjang yang sedang terbuka akan meminta persetujuan harga baru.',
        'success' => 'Perubahan produk disimpan.',
    ],
    'categories' => [
        'title' => 'Kategori',
        'add' => 'Tambah kategori',
        'name' => 'Nama kategori',
        'added' => 'Kategori :kategori ditambahkan.',
        'rename' => 'Ubah nama',
        'rename_named' => 'Ubah nama kategori :kategori',
        'renamed' => 'Nama kategori disimpan.',
        'empty' => 'Belum ada kategori.',
    ],
    'category' => [
        'deactivate_confirm' => [
            'title' => 'Nonaktifkan kategori :kategori?',
            'body' => 'Kategori nonaktif tidak bisa dipilih untuk produk. Produk yang sudah memakainya tidak berubah. Anda dapat mengaktifkannya kembali.',
        ],
        'reactivate_confirm' => [
            'title' => 'Aktifkan kembali kategori :kategori?',
            'body' => 'Kategori ini bisa dipilih lagi untuk produk.',
        ],
        'deactivated' => 'Kategori dinonaktifkan.',
        'reactivated' => 'Kategori diaktifkan kembali.',
    ],
];
