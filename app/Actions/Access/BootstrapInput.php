<?php

namespace App\Actions\Access;

use InvalidArgumentException;

/**
 * The typed, validated options of the bootstrap procedure (SECURITY_MODEL
 * SEC-OP-01, section 14; THR-10): the Owner's display name and email, the
 * shop name of the first BusinessProfile revision and the operator's note.
 * The caller trims and validates them (TR-VAL-05; TECHNICAL_RULES section
 * 11.3); the lengths are checked again here.
 */
final readonly class BootstrapInput
{
    public function __construct(
        public string $ownerName,
        public string $ownerEmail,
        public string $shopName,
        public string $operatorNote,
    ) {
        foreach ([[$ownerName, 100], [$ownerEmail, 254], [$shopName, 100], [$operatorNote, 1000]] as [$value, $max]) {
            if ($value === '' || mb_strlen($value) > $max) {
                throw new InvalidArgumentException('An option of the bootstrap is empty or too long (TECHNICAL_RULES section 11.3).');
            }
        }
    }
}
