<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as FrameworkValidator;

/**
 * The date filter of the stock lists (UXI-219, UXI-230; TECHNICAL_RULES section 11.4; COPY_DECK
 * section 16.1): when both days are given, `from` is never after `to`, refused at `to` with
 * `ux.validation.date_order`, as the failed rule `prohibited`, whose message the requests leave
 * empty and which has no placeholder to replace. Two days of the form YYYY-MM-DD compare as
 * their texts.
 */
final class DateRange
{
    public static function check(Validator $validator, mixed $from, mixed $to): void
    {
        $day = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

        if (! is_string($from) || ! is_string($to) || preg_match($day, $from) !== 1 || preg_match($day, $to) !== 1 || strcmp($from, $to) <= 0) {
            return;
        }

        if ($validator instanceof FrameworkValidator) {
            $validator->addFailure('to', 'prohibited');

            return;
        }

        $validator->errors()->add('', '');
    }
}
