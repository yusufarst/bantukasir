<?php

// Interface dictionary, file access (ADR-005; COPY_DECK section 1.1): sign-in,
// account, users and one-time links. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them. TASK-006 adds those of the
// roles (section 3) and of UXP-001 to UXP-004 (sections 6.2 to 6.5);
// TASK-007 the account menu's "Keluar" (section 6.1); TASK-009 the sign-out
// warning (section 6.1).

return [
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
