<?php

namespace App\Domain\Media;

use GdImage;
use LogicException;
use Throwable;

/**
 * The decoding and re-encoding of an inspected image (SECURITY_MODEL section
 * 13, SEC-UP-03; THR-09, THR-10; BR-CFG-04) with GD, the image library of
 * PHP's `gd` extension (libgd, with libpng, libjpeg-turbo and libwebp; their
 * licences are in TOOLCHAIN). GD has no external delegates: it never starts
 * another program and reads nothing but the bytes it is given, so no
 * delegate has to be disabled. It runs only after ImageInspector passed the
 * header.
 *
 * The image is decoded; its decoded dimensions must equal its header's; it is
 * turned upright by its EXIF orientation; and it is encoded again in its own
 * format — PNG and WebP keeping transparency — which writes no metadata: no
 * EXIF, no location, no comment survives. Anything GD cannot decode is
 * refused as unreadable.
 */
final class ImageNormalizer
{
    /** The quality of a re-encoded JPEG or WebP, and the compression of a PNG. */
    private const int QUALITY = 90;

    private const int PNG_COMPRESSION = 9;

    /**
     * @throws ImageRefused
     */
    public function normalize(string $bytes, InspectedImage $inspected): NormalizedImage
    {
        self::requireSupport($inspected->mediaType);

        try {
            $image = @imagecreatefromstring($bytes);
        } catch (Throwable) {
            $image = false;
        }

        if (! $image instanceof GdImage || imagesx($image) !== $inspected->width || imagesy($image) !== $inspected->height) {
            throw new ImageRefused(ImageProblem::Unreadable);
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $image = self::upright($image, $inspected->orientation);
        $encoded = self::encode($image, $inspected->mediaType);

        if ($encoded === '') {
            throw new ImageRefused(ImageProblem::Unreadable);
        }

        return new NormalizedImage($encoded, $inspected->mediaType, imagesx($image), imagesy($image));
    }

    /**
     * Whether GD can decode and encode this media type here; the application
     * image installs it with PNG, JPEG and WebP (docker/app/Dockerfile).
     */
    public static function supports(string $mediaType): bool
    {
        if (! function_exists('imagetypes')) {
            return false;
        }

        $flag = match ($mediaType) {
            ImageInspector::PNG => IMG_PNG,
            ImageInspector::JPEG => IMG_JPG,
            ImageInspector::WEBP => IMG_WEBP,
            default => 0,
        };

        return $flag !== 0 && (imagetypes() & $flag) === $flag;
    }

    private static function requireSupport(string $mediaType): void
    {
        if (! self::supports($mediaType)) {
            throw new LogicException(sprintf('The gd extension of this PHP cannot decode and encode %s.', $mediaType));
        }
    }

    /**
     * The image turned upright by its EXIF orientation (1 to 8): the
     * mirrorings and quarter turns that orientation names. GD turns
     * counter-clockwise; quarter turns are exact.
     */
    private static function upright(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $turn = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };

        if ($turn === 0) {
            return self::keepAlpha($image);
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $turned = imagerotate($image, $turn, $transparent === false ? 0 : $transparent);

        if (! $turned instanceof GdImage) {
            throw new ImageRefused(ImageProblem::Unreadable);
        }

        return self::keepAlpha($turned);
    }

    private static function keepAlpha(GdImage $image): GdImage
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * The image encoded in its own format, captured from GD's output.
     */
    private static function encode(GdImage $image, string $mediaType): string
    {
        ob_start();

        try {
            $written = match ($mediaType) {
                ImageInspector::PNG => imagepng($image, null, self::PNG_COMPRESSION),
                ImageInspector::JPEG => imagejpeg($image, null, self::QUALITY),
                default => imagewebp($image, null, self::QUALITY),
            };
        } finally {
            $encoded = ob_get_clean();
        }

        return $written && is_string($encoded) ? $encoded : '';
    }
}
