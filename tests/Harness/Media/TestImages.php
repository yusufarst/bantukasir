<?php

namespace Tests\Harness\Media;

use GdImage;
use LogicException;

/**
 * Synthetic images for the tests of the media pipeline (SECURITY_MODEL
 * section 13; TASK-010), made at run time with GD or written byte by byte:
 * plain PNG, JPEG and WebP; a JPEG with an EXIF orientation and a comment; an
 * animated PNG and WebP; a PNG whose header claims more pixels than a logo
 * may have; and the refused kinds — GIF, SVG and HTML. None is a real picture
 * of anything.
 */
final class TestImages
{
    public static function png(int $width = 40, int $height = 20): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagepng($image));
    }

    public static function jpeg(int $width = 40, int $height = 20): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagejpeg($image, null, 90));
    }

    public static function webp(int $width = 40, int $height = 20): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagewebp($image, null, 90));
    }

    /**
     * A JPEG with an APP1 EXIF block naming the orientation, and a comment
     * segment holding $secret — metadata that the normalization removes.
     */
    public static function jpegWithMetadata(int $width, int $height, int $orientation, string $secret): string
    {
        // A TIFF structure, little-endian, with one entry: the orientation.
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1)
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0)
            .pack('V', 0).$secret;
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\x00\x00".$tiff;
        $comment = "\xFF\xFE".pack('n', 2 + strlen($secret)).$secret;
        $jpeg = self::jpeg($width, $height);

        return substr($jpeg, 0, 2).$app1.$comment.substr($jpeg, 2);
    }

    /** A PNG with an `acTL` chunk: an animated PNG. */
    public static function animatedPng(): string
    {
        $png = self::png();
        $data = pack('N', 2).pack('N', 0);
        $chunk = pack('N', strlen($data)).'acTL'.$data.pack('N', crc32('acTL'.$data));

        // After the signature (8 bytes) and IHDR (25 bytes).
        return substr($png, 0, 33).$chunk.substr($png, 33);
    }

    /** An extended WebP with the animation flag and an ANIM chunk. */
    public static function animatedWebp(int $width = 40, int $height = 20): string
    {
        $vp8x = 'VP8X'.pack('V', 10)."\x02\x00\x00\x00".self::le24($width - 1).self::le24($height - 1);
        $anim = 'ANIM'.pack('V', 6)."\x00\x00\x00\x00\x00\x00";
        $body = 'WEBP'.$vp8x.$anim;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    /** A PNG whose header claims $width × $height pixels; its content is a small image. */
    public static function pngClaiming(int $width, int $height): string
    {
        $png = self::png(10, 10);
        $ihdr = pack('N', $width).pack('N', $height).substr($png, 24, 5);

        return substr($png, 0, 16).$ihdr.pack('N', crc32('IHDR'.$ihdr)).substr($png, 33);
    }

    public static function gif(): string
    {
        return "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF!\xF9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    }

    public static function svg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';
    }

    public static function html(): string
    {
        return '<!doctype html><html><body><script>alert(1)</script></body></html>';
    }

    /**
     * @param  callable(GdImage): bool  $write
     */
    private static function encode(int $width, int $height, callable $write): string
    {
        if ($width < 1 || $height < 1) {
            throw new LogicException('A test image has at least one pixel each way.');
        }

        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof GdImage) {
            throw new LogicException('GD makes the test image.');
        }

        // Two halves, so that a turn of the image can be seen.
        $left = imagecolorallocate($image, 17, 105, 73);
        $right = imagecolorallocate($image, 240, 240, 240);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height - 1, $left === false ? 0 : $left);
        imagefilledrectangle($image, intdiv($width, 2) + 1, 0, $width - 1, $height - 1, $right === false ? 0 : $right);

        ob_start();

        try {
            $written = $write($image);
        } finally {
            $bytes = ob_get_clean();
        }

        if (! $written || ! is_string($bytes) || $bytes === '') {
            throw new LogicException('GD encodes the test image.');
        }

        return $bytes;
    }

    private static function le24(int $value): string
    {
        return chr($value & 0xFF).chr(($value >> 8) & 0xFF).chr(($value >> 16) & 0xFF);
    }
}
