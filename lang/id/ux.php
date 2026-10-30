<?php

// Interface dictionary, file ux (ADR-005; COPY_DECK section 1.1): the shell,
// shared actions, states, formats, validation and announcements. Every text is
// copied verbatim from COPY_DECK; a key joins this file with the first Task
// that uses it. The leaves of ux.format.month and ux.format.day are the English
// names of the months and days in snake_case (COPY_DECK sections 1.1 and 1.3;
// TASK-003 evidence, the readings); ux.format.duration holds the unit words of
// durations (COPY_DECK section 1.3, CC-21).

return [
    'format' => [
        'month' => [
            'january' => 'Januari',
            'february' => 'Februari',
            'march' => 'Maret',
            'april' => 'April',
            'may' => 'Mei',
            'june' => 'Juni',
            'july' => 'Juli',
            'august' => 'Agustus',
            'september' => 'September',
            'october' => 'Oktober',
            'november' => 'November',
            'december' => 'Desember',
        ],
        'day' => [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
        ],
        'variance_short' => 'kurang',
        'variance_over' => 'lebih',
        'variance_even' => 'sesuai',
        'variance' => ':uang (:keterangan)',
        'duration' => [
            'minute' => 'menit',
            'second' => 'detik',
        ],
    ],
    'validation' => [
        'money' => 'Isi jumlah rupiah yang benar, tanpa angka di belakang koma.',
        'money_min' => 'Tidak boleh kurang dari Rp0.',
        'money_max' => 'Paling banyak Rp999.999.999.999.',
        'quantity_positive' => 'Isi jumlah lebih dari 0.',
        'quantity_decimals' => 'Paling banyak :desimal angka di belakang koma untuk produk ini.',
        'quantity_whole' => 'Produk ini dihitung utuh. Isi tanpa koma.',
        'quantity_max' => 'Jumlah terlalu besar. Paling banyak 99.999.999,999.',
        'required' => 'Wajib diisi.',
        // The password fields (TASK-006; COPY_DECK section 16.1).
        'password' => 'Kata sandi belum memenuhi syarat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol.',
        'password_max' => 'Paling banyak 128 karakter.',
        'password_repeat' => 'Kata sandi yang diulang tidak sama.',
        // The text lengths, the email and the uploads (TASK-010; COPY_DECK
        // sections 16.1 and 16.2).
        'text_max' => 'Paling banyak :maks karakter.',
        'email' => 'Format email belum benar.',
        'upload' => [
            'image_type' => 'Berkas harus JPG, PNG, atau WebP.',
            'too_large' => 'Berkas paling besar :ukuran.',
            'too_many_pixels' => 'Gambar paling besar :jumlah megapiksel.',
            'animated' => 'Gambar bergerak tidak bisa dipakai.',
            'unreadable' => 'Berkas tidak dapat dibaca. Pilih berkas lain.',
        ],
    ],
    // The shared actions, lists, navigation, announcements and disabled
    // reasons that the design system's components use (TASK-005; COPY_DECK
    // sections 4.3, 5, 15 and 18).
    'action' => [
        'cancel' => 'Batal',
        'back_to' => 'Kembali ke :halaman',
        'back_home' => 'Kembali ke halaman awal',
        'review' => 'Tinjau',
        'edit_again' => 'Kembali ubah',
        'resend' => 'Kirim ulang',
        'check_status' => 'Cek status',
        'open_unresolved' => 'Buka Permintaan belum pasti',
        'load_more' => 'Muat lebih banyak',
        'clear_filters' => 'Hapus filter',
        'reload' => 'Muat ulang',
        'retry' => 'Coba lagi',
        'done' => 'Selesai',
        'stay' => 'Tetap di halaman ini',
        'leave' => 'Tinggalkan',
        'optional' => '(opsional)',
        'save' => 'Simpan',
        // The user pages (TASK-011; COPY_DECK section 5).
        'save_changes' => 'Simpan perubahan',
        'deactivate' => 'Nonaktifkan',
        'reactivate' => 'Aktifkan kembali',
        'more_actions' => 'Tindakan lain',
        'remove_line' => 'Hapus baris',
        // The password visibility toggle (TASK-006; COPY_DECK section 5).
        'show_password' => 'Tampilkan kata sandi',
        'hide_password' => 'Sembunyikan kata sandi',
    ],
    'list' => [
        'updated_at' => 'Diperbarui :waktu',
        'count' => ':jumlah hasil',
        'search_hint' => 'Ketik untuk mencari',
    ],
    // The document title of the pages without business data (TASK-006;
    // COPY_DECK section 4.2), that of the protected pages with the shop's
    // name (TASK-010) and the account menu of the shell (TASK-007; section
    // 6.1).
    'shell' => [
        'document_title' => ':halaman — :toko',
        'document_title_public' => ':halaman',
        'account_menu' => 'Menu akun :nama',
    ],
    'nav' => [
        'menu_open' => 'Buka menu',
        'menu_close' => 'Tutup menu',
        'main' => 'Menu utama',
        'sub' => 'Submenu :halaman',
    ],
    'announce' => [
        'added' => 'Ditambahkan: :produk, jumlah :jumlah.',
        'unknown_code' => 'Kode tidak dikenal: :kode.',
        'loaded' => 'Data selesai dimuat.',
        'saved' => ':pesan',
        'refused' => 'Tidak disimpan. :pesan',
        'uncertain' => 'Status belum pasti. :pesan',
        'session_warning' => 'Sesi masuk akan berakhir dalam :durasi.',
    ],
    'disabled' => [
        'no_change' => 'Belum ada perubahan.',
        'form_incomplete' => 'Lengkapi isian yang wajib dulu.',
        'unresolved_family' => 'Ada permintaan sejenis yang statusnya belum pasti. Cek statusnya dulu.',
    ],
    // The states of UI_STATES section 2 with their wording (COPY_DECK section
    // 12). A state key is the parent of its texts: the title or label, the
    // body, and the named texts of its row. The accessible text of UXS-01 is
    // its body. Bodies that COPY_DECK leaves to a page ("the page's own empty
    // text", "the reason of section 15") come from the page's keys.
    'state' => [
        'loading' => [
            'title' => 'Memuat',
            'body' => 'Memuat data',
        ],
        'empty' => [
            'title' => 'Belum ada data',
        ],
        'no_results' => [
            'title' => 'Tidak ada hasil',
            'body' => 'Tidak ada yang cocok dengan pencarian atau filter ini.',
        ],
        'committed' => [
            'title' => 'Berhasil disimpan',
        ],
        'validation' => [
            'title' => 'Periksa isian',
            'body' => 'Ada isian yang perlu diperbaiki. Tidak ada yang disimpan.',
        ],
        'submitting' => [
            'title' => 'Sedang diproses',
            'still' => 'Masih diproses. Tunggu sebentar.',
        ],
        'forbidden' => [
            'title' => 'Anda tidak memiliki akses',
            'body' => 'Halaman atau tindakan ini tidak tersedia untuk akun Anda.',
        ],
        'not_found' => [
            'title' => 'Tidak ditemukan',
            'body' => 'Data yang Anda cari tidak ditemukan.',
        ],
        'signed_out' => [
            'title' => 'Sesi masuk berakhir',
            'body' => 'Sesi masuk Anda sudah berakhir. Masuk lagi untuk melanjutkan. Sesi kas yang masih dibuka tidak ikut tertutup.',
        ],
        'reload' => [
            'title' => 'Muat ulang halaman',
            'body' => 'Halaman ini perlu dimuat ulang sebelum dapat dipakai. Tidak ada yang disimpan.',
        ],
        'recent_auth' => [
            'title' => 'Konfirmasi kata sandi',
            'body' => 'Untuk melanjutkan, masukkan kata sandi Anda. Isian dan tinjauan Anda tetap seperti semula.',
            'confirm' => 'Konfirmasi',
            'password' => 'Kata sandi',
        ],
        'throttled' => [
            'title' => 'Terlalu banyak percobaan',
            'body' => 'Belum ada yang diproses. Coba lagi dalam :durasi.',
        ],
        'disabled' => [
            'title' => 'Tidak dapat dipakai sekarang',
        ],
        'uncertain' => [
            'title' => 'Status belum pasti',
            'body' => 'Permintaan sudah dikirim, tetapi hasilnya belum diterima. Mungkin sudah tersimpan.',
        ],
        'held' => [
            'idempotency_conflict' => [
                'title' => 'Status belum pasti',
                'body' => 'Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan. Cek statusnya untuk mengetahui hasilnya.',
            ],
            'epoch_stale' => [
                'title' => 'Status belum pasti',
                'body' => 'Permintaan ini dibuat sebelum data dipulihkan dari cadangan. Hasilnya diketahui setelah rekonsiliasi pemulihan oleh Pemilik. Sampai itu selesai, statusnya tetap belum pasti.',
            ],
        ],
        'abandoned' => [
            'title' => 'Permintaan lama sudah tidak berlaku',
            'body' => 'Permintaan ini tidak tersimpan dan tidak akan pernah tersimpan. Anda dapat mulai lagi.',
        ],
        'stale_version' => [
            'title' => 'Data sudah berubah',
            'body' => 'Data ini berubah setelah Anda mulai. Periksa nilai terbaru di samping nilai yang Anda tinjau, lalu konfirmasi lagi. Tidak ada yang disimpan.',
        ],
        'price_changed' => [
            'title' => 'Harga berubah',
            'body' => 'Harga :jumlah produk berubah sejak dimasukkan ke keranjang. Periksa harga dan total yang baru.',
            'line' => 'Harga lama :lama → harga baru :baru.',
            'total' => 'Total baru :uang',
        ],
        'recount' => [
            'title' => 'Ada transaksi baru; hitung ulang kas',
            'body' => 'Ada penjualan atau pengembalian dari laci setelah pratinjau dibuat. Sesi kas masih dibuka. Hitung ulang uang di laci, lalu tutup lagi.',
            'unresolved_checkouts' => [
                'title' => 'Ada penjualan yang statusnya belum pasti',
                'body' => 'Sesi kas masih dibuka karena ada penjualan yang statusnya belum pasti. Cek status penjualan itu dulu, lalu hitung ulang uang di laci dan tutup lagi.',
            ],
        ],
        'offline' => [
            'title' => 'Tidak ada koneksi',
            'body' => 'Penyimpanan berhenti sampai koneksi kembali. Isian di halaman ini tetap ada selama halaman tidak ditutup.',
        ],
        'stale_read' => [
            'title' => 'Data belum diperbarui',
            'body' => 'Data terakhir diperbarui :waktu dan mungkin sudah berubah.',
        ],
        'load_failed' => [
            'title' => 'Gagal memuat',
            'body' => 'Bagian ini tidak dapat dimuat. Bagian lain tetap bisa dipakai.',
        ],
        'session_warning' => [
            'title' => 'Sesi masuk akan berakhir',
            'body' => 'Anda akan keluar otomatis dalam :durasi karena tidak ada aktivitas.',
            'stay' => 'Tetap masuk',
            'absolute' => [
                'title' => 'Sesi masuk akan berakhir',
                'body' => 'Sesi masuk ini berakhir pukul :jam, batas 12 jam sejak masuk. Batas ini tidak bisa diperpanjang.',
            ],
        ],
        'cost_unknown' => [
            'title' => 'Biaya perlu dilengkapi pemilik',
        ],
        'cost_incomplete' => [
            'title' => 'Data biaya belum lengkap',
            'body' => 'HPP dan laba kotor belum lengkap karena ada biaya yang belum diketahui atau laporan yang ditandai belum lengkap. Margin tidak ditampilkan sampai data biaya lengkap.',
        ],
        'owner_corrected' => [
            'title' => 'Dikoreksi Pemilik',
        ],
        'onboarding' => [
            'title' => 'Masa input stok awal aktif',
            'body' => 'Penjualan dan pencatatan stok berhenti sampai Pemilik mengakhiri masa input stok awal.',
        ],
        'blocked' => [
            'title' => 'Mutasi diblokir',
            'body' => 'Produk :produk diblokir sementara karena pemeriksaan stok harian menemukan ketidakcocokan, jadi belum bisa dijual atau dicatat. Tidak ada yang disimpan.',
            'staff' => 'Beri tahu Pemilik.',
            'open_incident' => 'Buka Detail insiden',
        ],
        'no_session' => [
            'title' => 'Belum ada sesi kas',
            'body' => 'Buka sesi kas Anda dulu sebelum menjual.',
        ],
        'session_closed' => [
            'till' => [
                'title' => 'Sesi kas sudah ditutup',
                'body' => 'Sesi kas ini sudah ditutup, mungkin oleh Pemilik. Tidak ada yang disimpan. Buka sesi kas baru untuk menjual lagi.',
            ],
            'close' => [
                'title' => 'Sesi kas sudah ditutup',
                'body' => 'Sesi kas ini sudah ditutup, mungkin oleh Pemilik. Hitungan Anda tidak disimpan.',
            ],
            'open_report' => 'Buka Detail sesi kas',
            'refund' => [
                'title' => 'Sesi kas sudah ditutup',
                'body' => 'Sesi kas yang dipilih sudah ditutup. Tidak ada yang disimpan dan tidak ada uang yang boleh diambil dari lacinya. Pilih sumber uang lain.',
            ],
        ],
        'insufficient_stock' => [
            'title' => 'Stok tidak cukup',
            'body' => 'Stok tidak cukup untuk baris yang ditandai. Tidak ada yang disimpan.',
            'line' => 'Tersedia :jumlah',
            'reversal' => [
                'title' => 'Stok tidak cukup',
                'body' => 'Stok tidak cukup untuk membatalkan mutasi ini. Tidak ada yang disimpan.',
            ],
        ],
        'inactive' => [
            'product' => [
                'title' => 'Nonaktif',
                'body' => 'Produk :produk sudah nonaktif dan tidak bisa dipakai. Tidak ada yang disimpan. Hapus barisnya atau pilih produk lain.',
            ],
            'category' => [
                'title' => 'Nonaktif',
                'body' => 'Kategori :kategori sudah nonaktif. Tidak ada yang disimpan. Pilih kategori lain.',
            ],
            'label' => 'Nonaktif',
        ],
        'unknown_code' => [
            'title' => 'Kode tidak dikenal',
            'body' => 'Tidak ada produk dengan kode “:kode”. Cari dengan nama produk.',
        ],
        'tender_low' => [
            'title' => 'Uang diterima kurang',
            'body' => 'Kurang :uang',
        ],
        'refund_bound' => [
            'money' => [
                'title' => 'Melebihi batas pengembalian',
                'body' => 'Uang yang dikembalikan melebihi sisa yang bisa dikembalikan, yaitu :uang. Tidak ada yang disimpan.',
            ],
            'goods' => [
                'title' => 'Melebihi batas pengembalian',
                'body' => 'Jumlah barang yang diretur melebihi sisa yang bisa diretur, yaitu :jumlah. Tidak ada yang disimpan.',
            ],
        ],
        'drawer_short' => [
            'title' => 'Uang seharusnya di laci tidak cukup',
            'body' => 'Uang seharusnya di :laci hanya :uang, kurang dari pengembalian ini. Tidak ada yang disimpan. Jangan ambil uang dari laci. Pilih sesi kas lain yang cukup, atau Dana Pemilik.',
        ],
        'duplicate_code' => [
            'sku' => [
                'title' => 'Kode sudah dipakai',
                'body' => 'SKU ini sudah dipakai produk lain. Tidak ada yang disimpan.',
            ],
            'barcode' => [
                'title' => 'Kode sudah dipakai',
                'body' => 'Barcode ini sudah dipakai produk lain. Tidak ada yang disimpan.',
            ],
            'open' => 'Buka :produk',
        ],
        'barcode_ineligible' => [
            'title' => 'Barcode tidak dapat dipakai',
        ],
        'csv_invalid' => [
            'title' => 'Berkas CSV belum benar',
            'body' => 'Ada :jumlah baris yang perlu diperbaiki. Tidak ada produk yang dibuat. Perbaiki berkasnya, lalu pilih lagi.',
        ],
        'replay_rejected' => [
            'cost' => [
                'title' => 'Koreksi tidak dapat dihitung ulang',
                'body' => 'Biaya tidak diubah karena perhitungan ulang tidak bisa dilakukan dengan aman. Laporan biaya produk ini ditandai belum lengkap. Periksa riwayat produk ini, lalu coba lagi nanti.',
            ],
            'reversal' => [
                'title' => 'Pembatalan tidak dapat dihitung ulang',
                'body' => 'Mutasi tidak dibatalkan karena perhitungan ulang tidak bisa dilakukan dengan aman. Tidak ada yang berubah. Periksa riwayat produk ini, lalu coba lagi nanti.',
            ],
        ],
        'link_invalid' => [
            'title' => 'Tautan tidak berlaku',
            'body' => 'Tautan ini tidak dapat dipakai. Minta tautan baru kepada Pemilik.',
        ],
        'signin_failed' => [
            'sign_in' => [
                'title' => 'Gagal masuk',
                'body' => 'Periksa email dan kata sandi Anda, lalu coba lagi.',
            ],
            'confirmation' => [
                'title' => 'Konfirmasi gagal',
                'body' => 'Periksa kata sandi Anda, lalu coba lagi.',
            ],
            'current_password' => [
                'title' => 'Kata sandi sekarang tidak cocok',
                'body' => 'Coba lagi.',
            ],
        ],
        'unverified' => [
            'label' => 'Belum Terverifikasi',
        ],
        'print_failed' => [
            'title' => 'Cetak atau unduh gagal',
            'body' => 'Dokumen tidak dapat dicetak atau diunduh. Data tidak berubah.',
        ],
        'unsaved' => [
            'title' => 'Perubahan belum disimpan',
            'body' => 'Isian di halaman ini akan hilang jika Anda meninggalkannya.',
            'body_receiving' => 'Draf barang masuk ini tidak disimpan di mana pun dan akan hilang.',
            'body_import' => 'Pratinjau impor ini akan dibuang. Tidak ada produk yang dibuat.',
        ],
        'method_not_allowed' => [
            'title' => 'Metode pembayaran tidak tersedia',
            'body' => 'Tidak ada yang disimpan. Pilih Tunai atau Transfer bank.',
        ],
        'similar_product' => [
            'title' => 'Produk mirip',
            'body' => 'Sudah ada produk dengan nama mirip. Periksa dulu agar produk tidak tercatat dua kali.',
        ],
        'no_register' => [
            'title' => 'Tidak ada laci kas yang tersedia',
            'body' => 'Semua laci kas sedang dipakai atau nonaktif. Tunggu sampai salah satu sesi kas ditutup, atau minta bantuan Pemilik. Untuk serah terima, kasir sebelumnya menutup sesinya dulu, lalu Anda membuka sesi baru.',
        ],
        'link_gone' => [
            'title' => 'Tautan tidak ditampilkan lagi',
            'body' => 'Tautan kata sandi hanya ditampilkan sekali. Jika perlu, buat tautan baru; tautan baru menggantikan yang lama.',
        ],
        'onboarding_held' => [
            'title' => 'Stok awal menunggu rekonsiliasi pemulihan',
            'body' => 'Data dipulihkan dari cadangan dan status masa input stok awal belum dapat dipastikan. Memulai, mengisi, dan mengakhiri masa input stok awal ditunda sampai rekonsiliasi pemulihan selesai.',
        ],
    ],
];
