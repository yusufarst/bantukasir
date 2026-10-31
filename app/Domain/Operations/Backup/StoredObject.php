<?php

namespace App\Domain\Operations\Backup;

/**
 * What the store says of one object: its size, the recipient its metadata names,
 * and its Object Lock.
 */
final readonly class StoredObject
{
    public function __construct(
        public int $size,
        public string $recipient,
        public string $lockMode,
        public string $retainUntil,
    ) {}
}
