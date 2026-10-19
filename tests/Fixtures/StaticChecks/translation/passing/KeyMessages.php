<?php

namespace Tests\Fixtures\StaticChecks;

use Illuminate\Validation\ValidationException;

// Compliant sample of the literal check (TASK-003): every message to the user
// comes from a key; a string in a condition is not a message. Never executed.

function keyMessages(string $role, \Closure $fail): void
{
    abort_if($role === 'cashier', 403);
    abort(403, __('sample.greeting.title'));
    $fail(__('sample.greeting.title'));

    throw ValidationException::withMessages(['harga' => __('sample.greeting.title')]);
}
