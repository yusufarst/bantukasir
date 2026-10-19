<?php

namespace Tests\Fixtures\StaticChecks;

use Illuminate\Validation\ValidationException;

// Negative sample of the literal check (TASK-003): messages to the user
// written as literals instead of translation keys. Never executed.

function literalMessages(bool $hidden, \Closure $fail): void
{
    abort(403, 'Tidak boleh');
    abort_if($hidden, 404, 'Tidak ditemukan');
    $fail('Wajib diisi.');

    throw ValidationException::withMessages(['harga' => 'Harga belum benar']);
}
