<?php

namespace App\Domain\Media;

/**
 * Why an uploaded image is refused (SECURITY_MODEL section 13; COPY_DECK
 * section 16.2), each with the message key of its field error.
 */
enum ImageProblem: string
{
    /** Not PNG, JPEG or WebP by its content: SVG, HTML, GIF, anything else. */
    case Type = 'type';

    /** Larger than the bytes of its purpose. */
    case TooLarge = 'too_large';

    /** More pixels than its purpose allows, read from the header before decoding. */
    case TooManyPixels = 'too_many_pixels';

    /** An animated PNG or WebP. */
    case Animated = 'animated';

    /** A header or content that cannot be read or decoded. */
    case Unreadable = 'unreadable';

    public function messageKey(): string
    {
        return match ($this) {
            self::Type => 'ux.validation.upload.image_type',
            self::TooLarge => 'ux.validation.upload.too_large',
            self::TooManyPixels => 'ux.validation.upload.too_many_pixels',
            self::Animated => 'ux.validation.upload.animated',
            self::Unreadable => 'ux.validation.upload.unreadable',
        };
    }
}
