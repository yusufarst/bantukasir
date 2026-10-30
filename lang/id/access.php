<?php

// Interface dictionary, file access (ADR-005; COPY_DECK section 1.1): sign-in,
// account, users and one-time links. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them. TASK-006 adds those of the
// roles (section 3) and of UXP-001 to UXP-004 (sections 6.2 to 6.5);
// TASK-007 the account menu's "Keluar" (section 6.1); TASK-009 the sign-out
// warning (section 6.1); TASK-011 the roles' descriptions and the statuses
// (section 3) and the user pages (sections 6.6 and 11.2 to 11.6). A role's
// description is the child of its name, which is a text itself, so it is written
// at the top of the file as one dotted key (OPEN_ITEMS T-29;
// App\Support\Localization\Dictionary).

return [
    'role.super_admin.description' => 'Pemilik toko. Mengatur pengguna, laci kas, profil usaha, laporan, koreksi, dan pengembalian uang.',
    'role.operations_admin.description' => 'Mengelola produk, kategori, barcode, barang masuk, barang keluar, dan stok.',
    'role.cashier.description' => 'Menjual dengan sesi kas miliknya sendiri.',
    'sign_out' => 'Keluar',
    'sign_out_warning' => [
        'title' => 'Masih ada permintaan yang statusnya belum pasti',
        'body' => 'Catatan permintaan itu tetap ada di peramban ini. Masuk lagi di perangkat dan peramban ini untuk mengecek statusnya. Sebaiknya cek dulu sebelum keluar.',
        'leave' => 'Tetap keluar',
    ],
    'role' => [
        'super_admin' => 'Pemilik',
        'operations_admin' => 'Admin Operasional',
        'cashier' => 'Kasir',
        'list' => ':peran',
        'choice_help' => 'Pilih Kasir, Admin Operasional, atau keduanya.',
    ],
    'email_help' => 'Email ini dipakai untuk masuk. Aplikasi tidak pernah mengirim email.',
    'owner_details' => [
        'title' => 'Ubah nama dan email',
        'name' => 'Nama tampilan',
        'email' => 'Email',
        'success' => 'Nama dan email disimpan.',
    ],
    'users' => [
        'title' => 'Pengguna',
        'filter_status' => 'Status',
        'filter_role' => 'Peran',
        'col_name' => 'Nama',
        'col_email' => 'Email',
        'col_roles' => 'Peran',
        'col_status' => 'Status',
        'empty' => 'Belum ada akun staf.',
    ],
    'user' => [
        'status' => [
            'active' => 'Aktif',
            'deactivated' => 'Nonaktif',
            'anonymized' => 'Dianonimkan',
        ],
        'anonymized_name' => 'Pengguna dianonimkan :nomor',
        'anonymized_email' => 'Tidak ada (akun dianonimkan)',
        'deactivate_confirm' => [
            'title' => 'Nonaktifkan :nama?',
            'body' => 'Akun ini tidak bisa masuk sampai diaktifkan kembali. Semua sesi masuk dan tautannya berakhir sekarang.',
        ],
        'deactivated' => 'Akun dinonaktifkan.',
        'reactivate_confirm' => [
            'title' => 'Aktifkan kembali :nama?',
            'body' => 'Akun ini bisa dipakai lagi dengan perannya sekarang.',
        ],
        'reactivated' => 'Akun diaktifkan kembali.',
        'anonymize' => 'Anonimkan akun',
        'anonymize_confirm' => [
            'title' => 'Anonimkan akun :nama?',
            'body' => 'Nama dan email akun ini diganti dengan label netral, dan ini tidak bisa dibatalkan. Akun tetap nonaktif dan tidak bisa dipakai lagi. Catatan lama tetap menyimpan nama saat itu.',
        ],
        'anonymize_reason' => 'Alasan',
        'anonymized' => 'Akun dianonimkan.',
    ],
    'user_create' => [
        'title' => 'Tambah pengguna',
        'submit' => 'Simpan dan buat tautan',
        'submit_help' => 'Akun dibuat, lalu tautan kata sandi pertama ditampilkan sekali untuk Anda serahkan langsung.',
        'success' => 'Pengguna :nama ditambahkan.',
    ],
    'user_form' => [
        'name' => 'Nama tampilan',
        'email' => 'Email',
        'roles' => 'Peran',
        'email_taken' => 'Email ini sudah dipakai akun lain.',
        'email_taken_deactivated' => 'Email ini masih dipakai akun nonaktif. Aktifkan kembali akun itu, atau anonimkan dulu agar emailnya bisa dipakai.',
    ],
    'user_detail' => [
        'title' => 'Detail pengguna',
        'link_status' => 'Tautan kata sandi',
        'password_not_set' => 'Belum mengatur kata sandi',
        'owner_note' => 'Peran akun Pemilik tidak bisa diubah, dan akun ini tidak bisa dinonaktifkan atau dianonimkan dari aplikasi.',
    ],
    'user_edit' => [
        'title' => 'Ubah pengguna',
        'email_change_help' => 'Mengubah email mencabut tautan kata sandi yang masih aktif.',
        'role_change_help' => 'Mengubah peran mengakhiri semua sesi masuk akun ini.',
        'success' => 'Perubahan pengguna disimpan.',
    ],
    'link' => [
        'status_none' => 'Tidak ada tautan aktif',
        'status_active' => 'Aktif sampai :waktu',
        'issue_reset' => 'Buat tautan atur ulang kata sandi',
        'issue_reset_confirm' => [
            'title' => 'Buat tautan atur ulang kata sandi untuk :nama?',
        ],
        'issue_confirm' => [
            'body' => 'Tautan berlaku 60 menit dan hanya bisa dipakai sekali. Tautan lama yang masih aktif tidak berlaku lagi. Tautan ditampilkan sekali saja untuk Anda serahkan langsung.',
        ],
        'issue' => 'Buat tautan',
        'reissue_first' => 'Buat ulang tautan kata sandi pertama',
        'reissue_first_confirm' => [
            'title' => 'Buat ulang tautan kata sandi pertama untuk :nama?',
        ],
        'revoke' => 'Cabut tautan',
        'revoke_confirm' => [
            'title' => 'Cabut tautan kata sandi :nama?',
            'body' => 'Tautan ini tidak bisa dipakai lagi. Anda dapat membuat tautan baru bila perlu.',
        ],
        'revoked' => 'Tautan dicabut.',
        'purpose_first' => 'kata sandi pertama',
        'purpose_reset' => 'atur ulang kata sandi',
    ],
    'link_handover' => [
        'title' => 'Tautan kata sandi',
        'for' => 'Tautan :tujuan untuk :nama',
        'expires' => 'Berlaku sampai :waktu (60 menit).',
        'once' => 'Tautan ini hanya ditampilkan sekali.',
        'qr_alt' => 'Kode QR tautan kata sandi untuk :nama',
        'copy' => 'Salin tautan',
        'copied' => 'Tautan disalin.',
        'guidance' => 'Serahkan tautan ini langsung kepada :nama. Minta :nama segera memberi tahu Anda jika kata sandinya ternyata sudah diatur orang lain.',
    ],
    'sign_in' => [
        'title' => 'Masuk',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'submit' => 'Masuk',
    ],
    'set_password' => [
        'title' => 'Atur kata sandi',
        'intro' => 'Buat kata sandi untuk akun Anda.',
        'new' => 'Kata sandi baru',
        'repeat' => 'Ulangi kata sandi baru',
        'submit' => 'Simpan kata sandi',
        'success' => 'Kata sandi tersimpan. Masuk dengan email dan kata sandi baru Anda.',
        'to_sign_in' => 'Ke halaman masuk',
        'lost_answer' => 'Hasilnya belum diterima. Coba masuk dengan kata sandi baru. Jika gagal, minta tautan baru kepada Pemilik, karena tautan ini mungkin sudah terpakai.',
    ],
    'password_rule' => 'Minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol. Boleh ditempel dari pengelola kata sandi.',
    'account' => [
        'title' => 'Akun saya',
        'name' => 'Nama tampilan',
        'email' => 'Email',
        'roles' => 'Peran',
        'staff_note' => 'Nama dan email Anda hanya dapat diubah oleh Pemilik.',
    ],
    'change_password' => [
        'title' => 'Ubah kata sandi',
        'current' => 'Kata sandi sekarang',
        'success' => 'Kata sandi diubah. Sesi masuk Anda di perangkat dan peramban lain sudah diakhiri.',
        'lost_answer' => 'Hasilnya belum diterima. Ulangi sekali lagi. Jika kata sandi sekarang ditolak, berarti perubahan sudah tersimpan dan kata sandi baru yang berlaku.',
    ],
];
