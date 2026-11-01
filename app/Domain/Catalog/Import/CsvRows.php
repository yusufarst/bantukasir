<?php

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Barcodes\BarcodeToken;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Limits;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Str;

/**
 * What each cell of a row holds, by the rules of DOCUMENT_CONTRACTS DC-04, without the
 * database: text fields trimmed as the global trimming trims a command's text (SCH-14),
 * the numbers in the Indonesian formats and exact, SKU and barcode as text, and a cell
 * that begins with `=` refused whatever its column (SECURITY_MODEL section 13). The errors
 * that need the database are CsvImportChecks's.
 */
final class CsvRows
{
    /** Digits, optionally with full stops between groups of three. */
    private const string WHOLE = '(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)';

    private const string PRICE = '/^(?:Rp ?)?('.self::WHOLE.')(?:,00?)?$/D';

    private const string STOCK = '/^('.self::WHOLE.')(?:,([0-9]+))?$/D';

    /** A number in scientific notation, as a spreadsheet writes a long barcode. */
    private const string SCIENTIFIC = '/^[0-9]+(?:[.,][0-9]+)?[eE][+-]?[0-9]+$/D';

    private const int SKU_MAX = 64;

    private const int NAME_MAX = 200;

    private const int BRAND_MAX = 100;

    private const int UNIT_MAX = 32;

    /** The most digits of a price, and of its largest value (TECHNICAL_RULES section 11.1). */
    private const int PRICE_DIGITS = 12;

    /** What the column `Desimal` may hold (BR-NUM-02). */
    private const string PRECISIONS = '/^[0-3]$/D';

    public static function read(CsvRecord $record): CsvRow
    {
        if ($record->wrongCountAt !== null) {
            $row = new CsvRow($record->number, '', '', '', null, '', null, '', null, null, null);
            self::invalid($row, $record->wrongCountAt);

            return $row;
        }

        /** @var array<string, string> $texts */
        $texts = [];
        $problems = [];

        foreach (CsvColumn::cases() as $column) {
            $cell = Str::trim($record->cells[$column->value] ?? '');

            if (in_array($column, $record->broken, true)) {
                $problems[$column->value] = ['catalog.csv_import.error.invalid_value', ['kolom' => $column->header()]];
                $texts[$column->value] = '';
            } elseif (str_starts_with($cell, '=')) {
                $problems[$column->value] = ['catalog.csv_import.error.formula', []];
                $texts[$column->value] = '';
            } else {
                $texts[$column->value] = $cell;
            }
        }

        // A barcode keeps everything but its outer ASCII spaces (BR-CAT-17).
        $barcode = BarcodeToken::normalized($record->cells[CsvColumn::Barcode->value] ?? '');
        $precision = self::precision($texts[CsvColumn::QuantityPrecision->value]);
        $price = self::price($texts[CsvColumn::SellingPrice->value]);
        $stock = self::minimumStock($texts[CsvColumn::MinimumStock->value], $precision);
        $sku = self::text($texts[CsvColumn::Sku->value], self::SKU_MAX);
        $name = self::text($texts[CsvColumn::Name->value], self::NAME_MAX);
        $brand = self::text($texts[CsvColumn::Brand->value], self::BRAND_MAX);
        $unit = self::text($texts[CsvColumn::BaseUnit->value], self::UNIT_MAX);
        $barcodeProblem = isset($problems[CsvColumn::Barcode->value]) ? null : self::barcodeProblem($barcode);

        $row = new CsvRow(
            $record->number,
            $sku ?? '',
            $name ?? '',
            $texts[CsvColumn::Category->value],
            $brand === '' ? null : $brand,
            $unit ?? '',
            $precision,
            $texts[CsvColumn::SellingPrice->value],
            $price,
            $stock,
            isset($problems[CsvColumn::Barcode->value]) || $barcodeProblem !== null || $barcode === '' ? null : $barcode,
        );

        foreach (CsvColumn::cases() as $column) {
            $problem = $problems[$column->value] ?? null;

            if ($problem !== null) {
                $row->addError($problem[0], $problem[1]);

                continue;
            }

            $text = $texts[$column->value];

            if ($text === '' && $column->required()) {
                $row->addError('catalog.csv_import.error.required', ['kolom' => $column->header()]);

                continue;
            }

            $valid = match ($column) {
                CsvColumn::Sku => $sku !== null,
                CsvColumn::Name => $name !== null,
                CsvColumn::Category => true,
                CsvColumn::Brand => $brand !== null,
                CsvColumn::BaseUnit => $unit !== null,
                CsvColumn::QuantityPrecision => $precision !== null,
                CsvColumn::SellingPrice => $price !== null,
                CsvColumn::MinimumStock => $stock !== null || $precision === null,
                CsvColumn::Barcode => $barcodeProblem === null,
            };

            if ($valid) {
                continue;
            }

            if ($column === CsvColumn::Barcode && $barcodeProblem === 'reserved') {
                $row->addError('catalog.csv_import.error.barcode_reserved');
            } else {
                self::invalid($row, $column);
            }
        }

        return $row;
    }

    private static function invalid(CsvRow $row, CsvColumn $column): void
    {
        $row->addError('catalog.csv_import.error.invalid_value', ['kolom' => $column->header()]);
    }

    /**
     * The text when it fits its column, or null. Characters, as the database counts them.
     */
    private static function text(string $text, int $maximum): ?string
    {
        return mb_strlen($text, 'UTF-8') <= $maximum ? $text : null;
    }

    private static function precision(string $text): ?int
    {
        return preg_match(self::PRECISIONS, $text) === 1 ? (int) $text : null;
    }

    /**
     * Whole rupiah from 0 to 999.999.999.999: digits, with full stops between groups of
     * three, an optional leading `Rp` or `Rp ` and a trailing `,0` or `,00`.
     */
    private static function price(string $text): ?Money
    {
        if (preg_match(self::PRICE, $text, $match) !== 1) {
            return null;
        }

        $digits = self::withoutLeadingZeros(str_replace('.', '', $match[1]));

        return strlen($digits) > self::PRICE_DIGITS ? null : Money::of($digits)->assertStored();
    }

    /**
     * Zero or more, with at most the row's decimals as written; zero when empty. The
     * number needs the row's precision, so it is null while the precision is not valid.
     */
    private static function minimumStock(string $text, ?int $precision): ?Quantity
    {
        if ($precision === null) {
            return null;
        }

        if ($text === '') {
            return Quantity::of('0', $precision);
        }

        if (preg_match(self::STOCK, $text, $match) !== 1) {
            return null;
        }

        $fraction = $match[2] ?? '';

        if (strlen($fraction) > $precision) {
            return null;
        }

        $canonical = self::withoutLeadingZeros(str_replace('.', '', $match[1])).($fraction === '' ? '' : '.'.$fraction);

        return CanonicalNumber::exceeds($canonical, Limits::STOCK_MAX) ? null : Quantity::of($canonical, $precision)->assertStock();
    }

    private static function withoutLeadingZeros(string $digits): string
    {
        $significant = ltrim($digits, '0');

        return $significant === '' ? '0' : $significant;
    }

    /**
     * What is wrong with a barcode cell, or null: a token of 1 to 128 printable ASCII
     * characters, not in scientific notation and not in the `BK-` namespace.
     *
     * @return 'invalid'|'reserved'|null
     */
    private static function barcodeProblem(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        if (! BarcodeToken::isWellFormed($token) || preg_match(self::SCIENTIFIC, $token) === 1) {
            return 'invalid';
        }

        return BarcodeToken::isReserved($token) ? 'reserved' : null;
    }
}
