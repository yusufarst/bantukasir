<?php

namespace App\Support\Numbers;

use RangeException;

/**
 * A money, quantity or cost value outside its range of TECHNICAL_RULES
 * section 11.1. It is refused before any write, never truncated (BR-NUM-04).
 */
final class NumberOutOfRange extends RangeException
{
    public static function for(string $kind): self
    {
        return new self(sprintf('The %s is outside its supported range (TECHNICAL_RULES section 11.1).', $kind));
    }
}
