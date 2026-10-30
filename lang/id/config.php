<?php

// Interface dictionary, file config (ADR-005; COPY_DECK section 1.1): settings
// index, BusinessProfile, system status. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-010
// adds the settings index (section 11.1, its entry of UXP-607) and the
// BusinessProfile pages (sections 11.7 and 11.8), with the accent names of
// TASK-002 (CC-05).

return [
    'settings' => [
        'title' => 'Pengaturan',
        'profile_desc' => 'Nama toko, logo, dan teks pada struk dan dokumen.',
    ],
    'profile' => [
        'title' => 'Profil usaha',
        'shop_name' => 'Nama toko',
        'address' => 'Alamat',
        'contact' => 'Kontak',
        'email' => 'Email',
        'header_text' => 'Teks atas dokumen',
        'footer_text' => 'Teks bawah dokumen',
        'document_label' => 'Label dokumen',
        'logo' => 'Logo',
        'current_version' => 'Versi yang berlaku',
        'versions' => 'Versi sebelumnya',
        'version_row' => 'Versi :nomor · :waktu · oleh :nama',
        'version_expand' => 'Tampilkan isi versi :nomor',
        'header_text_help' => 'Dicetak di bagian atas struk dan dokumen.',
        'footer_text_help' => 'Dicetak di bagian bawah struk dan dokumen, misalnya ucapan terima kasih.',
        'document_label_help' => 'Awalan atau label yang dicetak bersama nomor dokumen.',
        'choose_logo' => 'Pilih logo',
        'replace_logo' => 'Ganti logo',
        'logo_rules' => 'PNG, JPEG, atau WebP, paling besar 5 MB.',
        'accent' => 'Warna aksen',
        'accent_option' => [
            'green' => 'Hijau',
            'teal' => 'Toska',
            'blue' => 'Biru',
            'purple' => 'Ungu',
            'graphite' => 'Grafit',
        ],
    ],
    'profile_edit' => [
        'title' => 'Ubah profil usaha',
        'submit' => 'Simpan sebagai versi baru',
        'version_help' => 'Struk dan dokumen lama tetap memakai versi saat dibuat.',
        'success' => 'Profil usaha disimpan sebagai versi baru.',
    ],
];
