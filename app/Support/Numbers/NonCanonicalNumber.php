<?php

namespace App\Support\Numbers;

use InvalidArgumentException;

/**
 * A number string that is not canonical under ADR-006 item 2. It is refused,
 * never repaired by guessing.
 */
final class NonCanonicalNumber extends InvalidArgumentException
{
    public static function for(string $kind): self
    {
        return new self(sprintf('The %s is not a canonical number string (ADR-006 item 2).', $kind));
    }

    public static function excessScale(string $kind): self
    {
        return new self(sprintf('The %s has more decimals than its kind allows (ADR-006 item 3).', $kind));
    }
}
