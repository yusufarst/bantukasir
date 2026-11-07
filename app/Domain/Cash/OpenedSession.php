<?php

namespace App\Domain\Cash;

/**
 * A session just opened: its internal key and its number (SCHEMA section 6).
 */
final readonly class OpenedSession
{
    public function __construct(
        public int $id,
        public string $number,
    ) {}
}
