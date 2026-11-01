<?php

namespace App\Domain\Media;

/**
 * A product photo ready to be stored (SECURITY_MODEL section 13): the image of at
 * most 2,048 pixels on its longest side, and its thumbnail for the till.
 */
final readonly class NormalizedPhoto
{
    public function __construct(
        public NormalizedImage $photo,
        public NormalizedImage $thumbnail,
    ) {}
}
