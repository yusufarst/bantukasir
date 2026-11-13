<?php

// Interface dictionary, file cash (ADR-005; COPY_DECK section 1.1): cash
// sessions, review notes, cash registers. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-027
// adds UXP-104 "Buka sesi kas" (section 7.4, with its disabled reason of
// section 15) and the cash register pages UXP-609 and UXP-610 (sections 11.9
// and 11.10, with their disabled reasons of section 15). TASK-030 adds UXP-105
// "Tutup sesi kas" (section 7.5, with its disabled reasons of section 15),
// UXP-205 "Sesi kas" (section 7.10) and UXP-206 "Detail sesi kas" (sections
// 4.1 and 7.11) without the Owner's notes, print and export, and the drawer-
// funded refunds' lists, which TASK-031, TASK-032 and TASK-034 add. TASK-032
// adds the Owner's review notes of UXP-206 and UXP-207 "Tutup sesi oleh
// Pemilik" (sections 4.1, 7.11, 7.12 and 15).

return [
    'close' => [
        'title' => 'Tutup sesi kas',
        'step_preview' => 'Pratinjau',
        'step_count' => 'Hitung uang',
        'unresolved_note' => 'Ada penjualan di sesi ini yang statusnya belum pasti. Cek statusnya dulu sebelum menghitung laci.',
        'resolve_unresolved' => 'Cek permintaan yang belum pasti',
        'continue_count' => 'Lanjut hitung',
        'physical_cash' => 'Uang fisik di laci',
        'physical_cash_help' => 'Hitung semua uang di laci, termasuk modal awal. Rp0 boleh.',
        'back_to_preview' => 'Kembali ke pratinjau',
        'review_title' => 'Tinjau tutup sesi kas',
        'review_note' => 'Setelah ditutup, sesi kas tidak bisa dibuka kembali dan hitungannya tidak bisa diubah.',
        'confirm' => 'Tutup sesi',
        'success' => 'Sesi kas :nomor ditutup.',
        'disabled' => 'Isi uang fisik di laci.',
        'disabled_unresolved' => 'Ada permintaan tutup sesi yang statusnya belum pasti. Cek statusnya dulu.',
    ],
    'figure' => [
        'opening_cash' => 'Modal awal',
        'sale_count' => 'Jumlah penjualan',
        'cash_payments' => 'Pembayaran tunai',
        'transfer_payments' => 'Pembayaran transfer bank',
        'drawer_refunds' => 'Pengembalian dari laci kas',
        'expected_cash' => 'Uang seharusnya di laci',
        'physical_cash' => 'Uang fisik di laci',
        'variance' => 'Selisih',
    ],
    'sessions' => [
        'title' => 'Sesi kas',
        'own_scope' => 'Menampilkan sesi kas Anda sendiri.',
        'filter_status' => 'Status',
        'filter_date' => 'Tanggal',
        'filter_register' => 'Laci kas',
        'filter_responsible' => 'Penanggung jawab',
        'col_number' => 'Nomor',
        'col_register' => 'Laci kas',
        'col_responsible' => 'Penanggung jawab',
        'col_opened' => 'Dibuka',
        'col_closed' => 'Ditutup',
        'col_variance' => 'Selisih',
        'empty' => 'Belum ada sesi kas.',
    ],
    'session' => [
        'status_open' => 'Dibuka',
        'status_closed' => 'Ditutup',
    ],
    'session_detail' => [
        'title' => 'Detail sesi kas',
        'subtitle' => 'Sesi kas :nomor · :laci',
        // UXI-141 and UXI-142 (COPY_DECK section 7.11; TASK-031).
        'print' => 'Cetak laporan',
        'download_csv' => 'Unduh CSV',
        // UXI-144 to UXI-146, the Owner's review notes (COPY_DECK section 7.11; TASK-032).
        'notes' => 'Catatan tinjauan Pemilik',
        'note_meta' => 'Oleh :nama, :waktu',
        'notes_empty' => 'Belum ada catatan tinjauan.',
        'notes_help' => 'Catatan tidak mengubah hitungan, selisih, atau laporan. Catatan tidak bisa diubah atau dihapus; untuk koreksi, tulis catatan baru.',
        'add_note' => 'Tambah catatan tinjauan',
        'note' => 'Catatan',
        'save_note' => 'Simpan catatan',
        'note_saved' => 'Catatan tinjauan disimpan.',
    ],
    // UXP-207 "Tutup sesi oleh Pemilik" (COPY_DECK sections 4.1, 7.12 and 15; TASK-032).
    'assisted_close' => [
        'title' => 'Tutup sesi oleh Pemilik',
        'responsible' => 'Penanggung jawab',
        'reason' => 'Alasan',
        'review_title' => 'Tinjau tutup sesi oleh Pemilik',
        'review_body' => 'Penanggung jawab sesi kas ini tetap :nama. Anda tercatat sebagai yang menutup.',
        'disabled' => 'Isi uang fisik di laci dan alasan.',
    ],
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
