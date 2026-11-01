<?php

namespace App\Support\Validation;

use App\Domain\Catalog\Barcodes\BarcodeToken;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A barcode token in a FormRequest (BR-CAT-17; TR-VAL-05; COPY_DECK section 16.1):
 * once only the scanner terminator and outer ASCII spaces are removed, 1 to 128
 * printable ASCII characters. Whether the field may be blank is the FormRequest's
 * own rule. Its paths are excluded from the global trimming (bootstrap/app.php).
 */
final class BarcodeTokenRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BarcodeToken::isWellFormed(BarcodeToken::normalized($value))) {
            $fail(__('ux.validation.barcode_token'));
        }
    }
}
