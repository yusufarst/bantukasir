<?php

// Interface dictionary, file cash (ADR-005; COPY_DECK section 1.1): cash
// sessions, review notes, cash registers. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-027
// adds UXP-104 "Buka sesi kas" (section 7.4, with its disabled reason of
// section 15) and the cash register pages UXP-609 and UXP-610 (sections 11.9
// and 11.10, with their disabled reasons of section 15).

return [
    'open' => [
        'title' => 'Buka sesi kas',
        'intro' => 'Hitung uang di laci, lalu buka sesi kas atas nama Anda.',
        'register' => 'Laci kas',
        'opening_cash' => 'Modal awal',
        'opening_cash_help' => 'Jumlah uang di laci saat ini. Rp0 boleh.',
        'review_title' => 'Tinjau buka sesi kas',
        'review_body' => 'Laci kas :laci, modal awal :uang. Anda menjadi penanggung jawab sesi kas ini sampai Anda menutupnya.',
        'confirm' => 'Buka sesi',
        'success' => 'Sesi kas :nomor dibuka.',
        'disabled' => 'Pilih laci kas dan isi modal awal.',
    ],
    'registers' => [
        'title' => 'Laci kas',
        'col_code' => 'Kode',
        'col_label' => 'Label',
        'col_status' => 'Status',
        'col_session' => 'Sesi kas',
        'session_open' => 'Dibuka oleh :nama',
        'session_none' => 'Tidak ada',
        'empty' => 'Belum ada laci kas.',
        'edit_disabled' => 'Laci kas ini sedang dipakai sesi kas yang dibuka.',
    ],
    'register_form' => [
        'title_new' => 'Tambah laci kas',
        'title_edit' => 'Ubah laci kas',
        'edit_named' => 'Ubah laci kas :laci',
        'code' => 'Kode',
        'label' => 'Label',
        'active' => 'Aktif',
        'help' => 'Kode, label, dan status hanya bisa diubah saat tidak ada sesi kas yang dibuka di laci kas ini.',
        'success' => 'Laci kas disimpan.',
        'disabled' => 'Lengkapi kode dan label.',
    ],
];
