<?php

namespace App\Domain\Media;

/**
 * An image decoded, turned upright and re-encoded without metadata, ready to
 * be stored: its bytes, its media type and its dimensions after the
 * orientation was applied.
 */
final readonly class NormalizedImage
{
    public function __construct(
        public string $bytes,
        public string $mediaType,
        public int $width,
        public int $height,
    ) {}
}
