<?php

namespace App\Domain\Media;

/**
 * A file written to private storage and not yet referenced (TR-TX-05): its
 * random key, its media type, size and dimensions, and the SHA-256 of its
 * bytes (32 bytes), which backup and restore use to find a missing or corrupt
 * file (SECURITY_MODEL section 13).
 */
final readonly class StoredBlob
{
    public function __construct(
        public string $storageKey,
        public string $mediaType,
        public int $byteSize,
        public int $width,
        public int $height,
        public string $sha256,
    ) {}
}
