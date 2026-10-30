<?php

namespace App\Domain\Media;

/**
 * The inspection of an uploaded image before it is decoded (SECURITY_MODEL
 * section 13, SEC-UP-01, SEC-UP-03; THR-09; BR-CFG-04). Its name and declared
 * type are never read: only its bytes.
 *
 * 1. The size, against the purpose's limit.
 * 2. The type by its signature: PNG, JPEG or WebP; SVG, HTML, GIF and every
 *    other content is refused.
 * 3. The dimensions, read from the header only (getimagesizefromstring, which
 *    parses the header and decodes nothing), of the same type as the
 *    signature, and the pixels against the purpose's limit, so that a
 *    decompression bomb is refused before anything is decoded.
 * 4. Animation: an APNG (`acTL` chunk) or an animated WebP (the animation
 *    flag of `VP8X`, or an `ANIM` or `ANMF` chunk) is refused.
 * 5. The EXIF orientation, from a JPEG's APP1 segment, a PNG's `eXIf` chunk
 *    or a WebP's `EXIF` chunk, for the normalization to apply.
 *
 * It uses whole numbers only, read byte by byte.
 */
final class ImageInspector
{
    public const string PNG = 'image/png';

    public const string JPEG = 'image/jpeg';

    public const string WEBP = 'image/webp';

    /** The EXIF tag of the orientation. */
    private const int ORIENTATION_TAG = 0x0112;

    /**
     * @throws ImageRefused
     */
    public function inspect(string $bytes, ImageLimits $limits): InspectedImage
    {
        $length = strlen($bytes);

        if ($length > $limits->maxBytes) {
            throw new ImageRefused(ImageProblem::TooLarge);
        }

        if ($length === 0) {
            throw new ImageRefused(ImageProblem::Unreadable);
        }

        $mediaType = self::typeOf($bytes);

        if ($mediaType === null) {
            throw new ImageRefused(ImageProblem::Type);
        }

        $header = @getimagesizefromstring($bytes);

        if ($header === false || $header[2] !== self::headerType($mediaType) || $header[0] < 1 || $header[1] < 1) {
            throw new ImageRefused(ImageProblem::Unreadable);
        }

        $width = $header[0];
        $height = $header[1];

        if ($width > intdiv($limits->maxPixels, $height) || $width * $height > $limits->maxPixels) {
            throw new ImageRefused(ImageProblem::TooManyPixels);
        }

        [$animated, $exif] = match ($mediaType) {
            self::PNG => self::pngChunks($bytes),
            self::WEBP => self::webpChunks($bytes),
            default => [false, self::jpegExif($bytes)],
        };

        if ($animated) {
            throw new ImageRefused(ImageProblem::Animated);
        }

        return new InspectedImage($mediaType, $width, $height, $exif === null ? 1 : self::orientation($exif));
    }

    /**
     * The media type by the content's signature, or null.
     */
    public static function typeOf(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return self::PNG;
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return self::JPEG;
        }

        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return self::WEBP;
        }

        return null;
    }

    /**
     * The header type that getimagesizefromstring gives a media type.
     */
    private static function headerType(string $mediaType): int
    {
        return match ($mediaType) {
            self::PNG => IMAGETYPE_PNG,
            self::JPEG => IMAGETYPE_JPEG,
            default => IMAGETYPE_WEBP,
        };
    }

    /**
     * Whether a PNG is animated, and its EXIF block.
     *
     * @return array{bool, string|null}
     */
    private static function pngChunks(string $bytes): array
    {
        $length = strlen($bytes);
        $offset = 8;
        $animated = false;
        $exif = null;

        while ($offset + 8 <= $length) {
            $size = self::u32($bytes, $offset, false);
            $type = substr($bytes, $offset + 4, 4);

            if ($type === 'acTL') {
                $animated = true;
            } elseif ($type === 'eXIf' && $exif === null) {
                $exif = substr($bytes, $offset + 8, $size);
            } elseif ($type === 'IEND') {
                break;
            }

            $offset += 12 + $size;
        }

        return [$animated, $exif];
    }

    /**
     * Whether a WebP is animated, and its EXIF block.
     *
     * @return array{bool, string|null}
     */
    private static function webpChunks(string $bytes): array
    {
        $length = strlen($bytes);
        $offset = 12;
        $animated = false;
        $exif = null;

        while ($offset + 8 <= $length) {
            $type = substr($bytes, $offset, 4);
            $size = self::u32($bytes, $offset + 4, true);

            if ($type === 'VP8X' && $offset + 8 < $length && (self::byte($bytes, $offset + 8) & 0x02) !== 0) {
                $animated = true;
            } elseif ($type === 'ANIM' || $type === 'ANMF') {
                $animated = true;
            } elseif ($type === 'EXIF' && $exif === null) {
                $exif = substr($bytes, $offset + 8, $size);
            }

            $offset += 8 + $size + ($size % 2);
        }

        return [$animated, $exif];
    }

    /**
     * The EXIF block of a JPEG's APP1 segment, or null: the segments up to
     * the start of the scan.
     */
    private static function jpegExif(string $bytes): ?string
    {
        $length = strlen($bytes);
        $offset = 2;

        while ($offset + 4 <= $length && self::byte($bytes, $offset) === 0xFF) {
            $marker = self::byte($bytes, $offset + 1);

            if ($marker === 0xFF) {
                $offset++;

                continue;
            }

            if ($marker === 0xD9 || $marker === 0xDA) {
                return null;
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $offset += 2;

                continue;
            }

            $size = self::u16($bytes, $offset + 2, false);

            if ($size < 2) {
                return null;
            }

            if ($marker === 0xE1 && substr($bytes, $offset + 4, 6) === "Exif\x00\x00") {
                return substr($bytes, $offset + 10, $size - 8);
            }

            $offset += 2 + $size;
        }

        return null;
    }

    /**
     * The orientation of an EXIF block (a TIFF structure): the value of tag
     * 0x0112 in its first directory, 1 when it is absent or not 1 to 8.
     */
    private static function orientation(string $tiff): int
    {
        $length = strlen($tiff);

        if ($length < 8) {
            return 1;
        }

        $order = substr($tiff, 0, 2);

        if ($order !== 'II' && $order !== 'MM') {
            return 1;
        }

        $little = $order === 'II';

        if (self::u16($tiff, 2, $little) !== 42) {
            return 1;
        }

        $directory = self::u32($tiff, 4, $little);

        if ($directory < 8 || $directory + 2 > $length) {
            return 1;
        }

        $count = self::u16($tiff, $directory, $little);

        for ($index = 0; $index < $count; $index++) {
            $entry = $directory + 2 + $index * 12;

            if ($entry + 12 > $length) {
                return 1;
            }

            if (self::u16($tiff, $entry, $little) === self::ORIENTATION_TAG) {
                $value = self::u16($tiff, $entry + 2, $little) === 3 ? self::u16($tiff, $entry + 8, $little) : 0;

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    private static function byte(string $bytes, int $offset): int
    {
        return $offset < strlen($bytes) ? ord($bytes[$offset]) : 0;
    }

    private static function u16(string $bytes, int $offset, bool $little): int
    {
        $first = self::byte($bytes, $offset);
        $second = self::byte($bytes, $offset + 1);

        return $little ? ($second << 8) | $first : ($first << 8) | $second;
    }

    private static function u32(string $bytes, int $offset, bool $little): int
    {
        $high = self::u16($bytes, $offset + ($little ? 2 : 0), $little);
        $low = self::u16($bytes, $offset + ($little ? 0 : 2), $little);

        return ($high << 16) | $low;
    }
}
