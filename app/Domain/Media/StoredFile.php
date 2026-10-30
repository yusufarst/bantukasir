<?php

namespace App\Domain\Media;

/**
 * A stored file's record as delivery reads it (SCHEMA section 5.5): its
 * purpose, media type and storage key. The storage key never leaves the
 * server (SEC-CH-06, THR-12).
 */
final readonly class StoredFile
{
    public function __construct(
        public int $id,
        public string $purpose,
        public string $mediaType,
        public string $storageKey,
        public ?int $derivedFromFileId,
    ) {}
}
