<?php

// Interface dictionary, file ux (ADR-005; COPY_DECK section 1.1): the shell,
// shared actions, states, formats, validation and announcements. Every text is
// copied verbatim from COPY_DECK; a key joins this file with the first Task
// that uses it. The leaves of ux.format.month and ux.format.day are the English
// names of the months and days in snake_case (COPY_DECK sections 1.1 and 1.3;
// TASK-003 evidence, the readings).

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
    ],
    'validation' => [
        'money' => 'Isi jumlah rupiah yang benar, tanpa angka di belakang koma.',
        'money_min' => 'Tidak boleh kurang dari Rp0.',
        'money_max' => 'Paling banyak Rp999.999.999.999.',
        'quantity_positive' => 'Isi jumlah lebih dari 0.',
        'quantity_decimals' => 'Paling banyak :desimal angka di belakang koma untuk produk ini.',
        'quantity_whole' => 'Produk ini dihitung utuh. Isi tanpa koma.',
        'quantity_max' => 'Jumlah terlalu besar. Paling banyak 99.999.999,999.',
    ],
];
