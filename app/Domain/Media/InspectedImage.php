<?php

namespace App\Domain\Media;

/**
 * What the header of an accepted upload says, before it is decoded: its media
 * type judged by content, its dimensions and the EXIF orientation (1 to 8)
 * that the normalization applies.
 */
final readonly class InspectedImage
{
    public function __construct(
        public string $mediaType,
        public int $width,
        public int $height,
        public int $orientation,
    ) {}
}
