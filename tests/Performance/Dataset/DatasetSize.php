<?php

namespace Tests\Performance\Dataset;

use InvalidArgumentException;

/**
 * The counts of a dataset of TECHNICAL_RULES section 13.2 (ASSUMPTION; OPEN_ITEMS T-09) at a scale in
 * millionths: 1,000,000 is the dataset as the section sets it, a smaller scale the same shape with fewer
 * records, for the tests and a quick look; every count keeps the minimum that the shape needs.
 *
 * The base dataset: 5,000 active and 500 inactive products in 50 categories; one barcode on every
 * active product and a second on every other one, 7,500 active tokens, and 500 more added and then
 * retired; one product, the first, with 10,000 movements — its opening receipt and 9,999 documents
 * over 365 days, three in ten of them Sales of nine lines, the rest receivings of ten lines, each with
 * that product — so about 100,000 movement lines in all; three registers and two cashiers.
 *
 * The ten-year growth dataset: the same catalogue, then 600,000 Sales over 3,650 days of one to four
 * lines, 2.5 on average, so 1,500,000 Sale lines and movements, each Sale printed once and again three
 * times, which makes about five audit events a Sale and so about 3,000,000 audit events (section 13.2).
 */
final readonly class DatasetSize
{
    /** The scale of the dataset as section 13.2 sets it. */
    public const int FULL = 1000000;

    public function __construct(
        public int $scale,
        public int $categories,
        public int $activeProducts,
        public int $inactiveProducts,
        public int $secondTokens,
        public int $retiredTokens,
        public int $heavyMovements,
        public int $saleDocuments,
        public int $receivingDocuments,
        public int $linesPerSale,
        public int $linesPerReceiving,
        public int $days,
        public int $printsPerSale,
    ) {}

    public static function base(int $scale): self
    {
        self::checkScale($scale);
        $active = self::of(5000, $scale, 12);
        $heavy = self::of(10000, $scale, 6);
        $sales = intdiv(($heavy - 1) * 3, 10);

        return new self(
            $scale,
            self::of(50, $scale, 2),
            $active,
            self::of(500, $scale, 1),
            intdiv($active, 2),
            self::of(500, $scale, 1),
            $heavy,
            $sales,
            $heavy - 1 - $sales,
            min(9, $active - 1),
            min(10, $active - 1),
            self::of(365, $scale, 1),
            0,
        );
    }

    public static function growth(int $scale): self
    {
        self::checkScale($scale);
        $active = self::of(5000, $scale, 12);

        return new self(
            $scale,
            self::of(50, $scale, 2),
            $active,
            self::of(500, $scale, 1),
            intdiv($active, 2),
            self::of(500, $scale, 1),
            0,
            self::of(600000, $scale, 4),
            0,
            4,
            0,
            self::of(3650, $scale, 1),
            4,
        );
    }

    /**
     * The count of the full dataset at this scale, rounded, and never below the minimum.
     */
    private static function of(int $full, int $scale, int $minimum): int
    {
        return max($minimum, intdiv($full * $scale + intdiv(self::FULL, 2), self::FULL));
    }

    private static function checkScale(int $scale): void
    {
        if ($scale < 1 || $scale > self::FULL) {
            throw new InvalidArgumentException('The scale is 1 to 1,000,000 millionths of the dataset of TECHNICAL_RULES section 13.2.');
        }
    }
}
