<?php

namespace App\Domain\Cash;

/**
 * A row of `cash_registers` as an Action reads it (SCHEMA section 5.10).
 */
final readonly class RegisterRecord
{
    public function __construct(
        public int $id,
        public string $code,
        public string $label,
        public bool $active,
        public int $version,
    ) {}
}
