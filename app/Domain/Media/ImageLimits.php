<?php

namespace App\Domain\Media;

/**
 * The limits of an uploaded image of one purpose (SECURITY_MODEL section 13;
 * BR-CFG-04): its bytes and its pixels, with the values that the field
 * errors of COPY_DECK section 16.2 name. TASK-018 adds the product photo's.
 */
final readonly class ImageLimits
{
    private function __construct(
        public int $maxBytes,
        public int $maxPixels,
        public string $sizeLabel,
        public int $megapixels,
    ) {}

    /** A logo: at most 5 MiB and 16 megapixels. */
    public static function logo(): self
    {
        return new self(5 * 1024 * 1024, 16_000_000, '5 MB', 16);
    }

    /**
     * The parameters of a refusal's message key (COPY_DECK section 16.2).
     *
     * @return array<string, string|int>
     */
    public function params(ImageProblem $problem): array
    {
        return match ($problem) {
            ImageProblem::TooLarge => ['ukuran' => $this->sizeLabel],
            ImageProblem::TooManyPixels => ['jumlah' => $this->megapixels],
            default => [],
        };
    }
}
