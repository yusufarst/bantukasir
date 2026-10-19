<?php

namespace Tests\Fixtures\StaticChecks;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Lang as Words;

// Negative samples of the translation-key check from the class-A adversarial
// review of TASK-003, finding A-06: lookups of keys that lang/id lacks, through
// every entry point of Laravel's translator, and lookups whose key is not a
// literal. A line tagged K is reported; a line tagged C is a control and is
// not. Never executed.

function keyBypass(string $key, string $name): void
{
    \__('sample.greeting.nope_backslash'); // K10 a missing key through \__()
    __(key: 'sample.greeting.nope_named'); // K11 a missing key with a named argument
    app('translator')->get('sample.greeting.nope_translator'); // K12 a missing key through the translator
    trans()->get('sample.greeting.nope_trans_get'); // K13 a missing key through trans()->get()
    \Illuminate\Support\Facades\Lang::get('sample.greeting.nope_facade'); // K14 a missing key through the fully qualified facade
    Lang::get('sample.greeting.nope_lang'); // K15 a missing key through Lang::get()
    __('sample.greeting.nope_function'); // K16 a missing key through __()
    trans('sample.greeting.nope_trans'); // K17 a missing key through trans()
    trans_choice('sample.greeting.nope_choice', 2); // K18 a missing key through trans_choice()
    \trans_choice(number: 2, key: 'sample.greeting.nope_choice_named'); // K19 named arguments in another order
    app('translator')->choice('sample.greeting.nope_translator_choice', 2); // K20 a missing key through the translator's choice()
    trans()->choice('sample.greeting.nope_trans_choice', 2); // K21 a missing key through trans()->choice()
    Lang::choice('sample.greeting.nope_lang_choice', 2); // K22 a missing key through Lang::choice()
    Illuminate\Support\Facades\Lang::get('sample.greeting.nope_qualified'); // K23 a missing key through the qualified facade
    Words::get('sample.greeting.nope_alias'); // K24 a missing key through an alias of the facade
    \app('translator')->get('sample.greeting.nope_root_app'); // K25 a missing key through \app()
    __($key); // K26 a key that is not a literal
    __('sample.greeting.'.$name); // K27 a key built by concatenation
    __("sample.greeting.{$name}"); // K28 a key built by interpolation
    Lang::get(key: $key); // K29 a named key that is not a literal
    __( // K30 a missing key on the next line
        'sample.greeting.nope_next_line',
    );
    __('sample.greeting.title'); // C01 a present key through __()
    \__('sample.greeting.title'); // C02 a present key through \__()
    __(key: 'sample.greeting.title', replace: ['nama' => $name]); // C03 a present key with named arguments
    app('translator')->get('sample.greeting.title'); // C04 a present key through the translator
    Words::get('sample.greeting.title'); // C05 a present key through an alias
    trans_choice('sample.greeting.title', 2, ['jumlah' => '2']); // C06 a present key through trans_choice()
    $name->get('not a key'); // C07 another object's get() is not a lookup
    cache()->get('sample.greeting.nope_cache'); // C08 the cache's get() is not a lookup
}
