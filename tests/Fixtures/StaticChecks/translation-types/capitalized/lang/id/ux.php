<?php

// A dictionary with capitalized placeholders, which the translation-types
// check refuses (TASK-003 adversarial finding A-15): Laravel title-cases the
// first code point of a :Name value, the client could only approximate it, so
// no text of lang/id may ask for it. Never shipped.
return [
    'format' => [
        'variance' => ':uang (:keterangan)',
        'greeting' => 'Halo, :Nama.',
        'shout' => 'HALO, :NAMA!',
        'time' => 'Zona waktu: WIB (:waktu)',
    ],
];
