<?php

namespace App\Domain\Catalog\Barcodes;

use LogicException;

/**
 * A manufacturer barcode as RC-17 and RC-27 carry it (INTERACTION_CONTRACTS RC-17,
 * RC-27), read from a payload that its FormRequest validated: the token normalized,
 * the symbology null when none is declared.
 */
final readonly class BarcodeInput
{
    public function __construct(
        public string $token,
        public ?Symbology $symbology,
    ) {}

    /**
     * @param  array<array-key, mixed>  $fields  `token` and an optional `symbology`
     */
    public static function fromPayload(array $fields): self
    {
        $token = $fields['token'] ?? null;
        $symbology = $fields['symbology'] ?? null;

        if (! is_string($token) || ($symbology !== null && ! is_string($symbology))) {
            throw new LogicException('A barcode of the payload was validated before the Action.');
        }

        return new self(BarcodeToken::normalized($token), $symbology === null ? null : Symbology::from($symbology));
    }
}
