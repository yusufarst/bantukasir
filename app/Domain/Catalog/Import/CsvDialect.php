<?php

namespace App\Domain\Catalog\Import;

/**
 * The file of DOCUMENT_CONTRACTS DC-04 read as data (BR-CAT-39; SEC-UP-05; SECURITY_MODEL
 * section 13): nothing here depends on its name or declared type (SEC-UP-01), and nothing
 * of it reaches a process or a path. A whole file is refused for its size, its encoding,
 * a control character other than a line break, more rows than the limit, or its header;
 * what a row holds is the row's own error, and CsvRows reads it.
 *
 * The reading of a field is this class's own, because PHP's CSV functions have an escape
 * character that the dialect does not: a field may be enclosed in `"`, with an inner `"`
 * doubled; a quote anywhere else, and a line break inside a field, make that cell a row
 * error; a carriage return outside a quoted field is no line end of the dialect, so it
 * counts as the control character it is. Empty lines at the end are ignored; one between
 * rows is a row with too few fields, never skipped (BR-CAT-38).
 */
final class CsvDialect
{
    /** BR-CAT-39: at most 2 MiB (ROUTE_CONTRACTS RT-12). */
    public const int MAX_BYTES = 2097152;

    /** BR-CAT-39: at most 5,000 data rows and one header row. */
    public const int MAX_DATA_ROWS = 5000;

    /** How COPY_DECK section 16.2 names the limit. */
    public const string SIZE_LABEL = '2 MB';

    private const string BOM = "\xEF\xBB\xBF";

    /** Every control character of one byte but the line feed and the carriage return. */
    private const string SINGLE_BYTE_CONTROLS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    /**
     * @throws CsvFileRefused
     */
    public static function parse(string $bytes): CsvFile
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw CsvFileRefused::of('ux.validation.upload.too_large', ['ukuran' => self::SIZE_LABEL]);
        }

        $text = str_starts_with($bytes, self::BOM) ? substr($bytes, strlen(self::BOM)) : $bytes;

        if (! mb_check_encoding($text, 'UTF-8') || self::holdsControl($text)) {
            throw self::wrongType();
        }

        $at = 0;
        [$separator, $columns] = self::header($text, $at);

        return new CsvFile(hash('sha256', $bytes), self::records($text, $separator, $columns, $at));
    }

    /**
     * Whether valid UTF-8 holds a control character other than a line break: searched byte
     * by byte, since a pattern over 2 MiB could reach PCRE's limits and answer nothing. A
     * control character of U+0080 to U+009F is 0xC2 and a continuation byte up to 0x9F.
     */
    private static function holdsControl(string $text): bool
    {
        if (strcspn($text, self::SINGLE_BYTE_CONTROLS) !== strlen($text)) {
            return true;
        }

        for ($at = strpos($text, "\xC2"); $at !== false; $at = strpos($text, "\xC2", $at + 1)) {
            if (ord($text[$at + 1]) <= 0x9F) {
                return true;
            }
        }

        return false;
    }

    private static function wrongType(): CsvFileRefused
    {
        return CsvFileRefused::of('ux.validation.upload.csv_type');
    }

    /**
     * The separator that splits the header into known column names and the columns in
     * their order; a name is compared after trimming outer spaces and without regard to
     * letter case. A separator splits the header when it yields a known name and the other
     * does not; with neither or both the file is refused, and so is a file with a missing
     * required column, an unknown column, a column twice, which is unexpected, or a name
     * with a quote out of place (BR-CAT-39).
     *
     * @return array{0: string, 1: list<CsvColumn>}
     *
     * @throws CsvFileRefused
     */
    private static function header(string $text, int &$at): array
    {
        $known = self::knownColumns();
        $atSemicolon = 0;
        $atComma = 0;
        $bySemicolon = self::record($text, ';', $atSemicolon);
        $byComma = self::record($text, ',', $atComma);
        $semicolonKnown = self::knownCount($bySemicolon['cells'], $known);
        $commaKnown = self::knownCount($byComma['cells'], $known);

        if (($semicolonKnown > 0) === ($commaKnown > 0)) {
            throw CsvFileRefused::of('catalog.csv_import.error.missing_column', ['kolom' => self::names(self::missing([]))]);
        }

        [$separator, $header, $at] = $semicolonKnown > 0 ? [';', $bySemicolon, $atSemicolon] : [',', $byComma, $atComma];
        $columns = [];
        $unknown = [];

        foreach ($header['cells'] as $index => $name) {
            $column = isset($header['broken'][$index]) ? null : ($known[self::comparable($name)] ?? null);

            if ($column === null || in_array($column, $columns, true)) {
                $unknown[] = trim($name, ' ');
            } else {
                $columns[] = $column;
            }
        }

        $missing = self::missing($columns);
        $entries = [];

        if ($missing !== []) {
            $entries[] = ['key' => 'catalog.csv_import.error.missing_column', 'params' => ['kolom' => self::names($missing)]];
        }

        if ($unknown !== []) {
            $entries[] = ['key' => 'catalog.csv_import.error.unknown_column', 'params' => ['kolom' => implode(', ', $unknown)]];
        }

        if ($entries !== []) {
            throw new CsvFileRefused($entries);
        }

        return [$separator, $columns];
    }

    /**
     * @return array<string, CsvColumn>
     */
    private static function knownColumns(): array
    {
        $known = [];

        foreach (CsvColumn::cases() as $column) {
            $known[self::comparable($column->header())] = $column;
        }

        return $known;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, CsvColumn>  $known
     */
    private static function knownCount(array $cells, array $known): int
    {
        return count(array_filter($cells, static fn (string $cell): bool => array_key_exists(self::comparable($cell), $known)));
    }

    private static function comparable(string $name): string
    {
        return mb_strtolower(trim($name, ' '), 'UTF-8');
    }

    /**
     * The required columns that the header does not hold.
     *
     * @param  list<CsvColumn>  $columns
     * @return list<CsvColumn>
     */
    private static function missing(array $columns): array
    {
        return array_values(array_filter(CsvColumn::cases(), static fn (CsvColumn $column): bool => $column->required() && ! in_array($column, $columns, true)));
    }

    /**
     * @param  list<CsvColumn>  $columns
     */
    private static function names(array $columns): string
    {
        return implode(', ', array_map(static fn (CsvColumn $column): string => $column->header(), $columns));
    }

    /**
     * The data rows, bounded by the limit as they are read.
     *
     * @param  list<CsvColumn>  $columns
     * @return list<CsvRecord>
     *
     * @throws CsvFileRefused
     */
    private static function records(string $text, string $separator, array $columns, int $at): array
    {
        $records = [];
        $length = strlen($text);
        $number = 1;
        $emptyRun = 0;

        while ($at < $length) {
            $record = self::record($text, $separator, $at);
            $number++;

            if ($record['empty']) {
                $emptyRun++;

                continue;
            }

            if (count($records) + $emptyRun >= self::MAX_DATA_ROWS) {
                throw CsvFileRefused::of('ux.validation.upload.csv_rows');
            }

            for (; $emptyRun > 0; $emptyRun--) {
                $records[] = self::recordOf($number - $emptyRun, [''], [], $columns);
            }

            $records[] = self::recordOf($number, $record['cells'], $record['broken'], $columns);
        }

        return $records;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, true>  $broken
     * @param  list<CsvColumn>  $columns
     */
    private static function recordOf(int $number, array $cells, array $broken, array $columns): CsvRecord
    {
        $count = count($cells);

        if ($count !== count($columns)) {
            return new CsvRecord($number, [], [], $count < count($columns) ? $columns[$count] : $columns[count($columns) - 1]);
        }

        $mapped = [];
        $brokenColumns = [];

        foreach ($columns as $index => $column) {
            $mapped[$column->value] = $cells[$index];

            if (isset($broken[$index])) {
                $brokenColumns[] = $column;
            }
        }

        return new CsvRecord($number, $mapped, $brokenColumns, null);
    }

    /**
     * One record from the position $at, which moves past its line end.
     *
     * @return array{cells: list<string>, broken: array<int, true>, empty: bool}
     *
     * @throws CsvFileRefused a carriage return outside a quoted field that ends no line
     */
    private static function record(string $text, string $separator, int &$at): array
    {
        $length = strlen($text);
        $stops = $separator."\r\n";
        $cells = [];
        $broken = [];
        $quoted = false;

        while (true) {
            $index = count($cells);

            if ($at < $length && $text[$at] === '"') {
                $quoted = true;
                $at++;
                $value = '';

                while (true) {
                    $end = strpos($text, '"', $at);

                    if ($end === false) {
                        $value .= substr($text, $at);
                        $at = $length;
                        $broken[$index] = true;

                        break;
                    }

                    $value .= substr($text, $at, $end - $at);
                    $at = $end + 1;

                    if ($at < $length && $text[$at] === '"') {
                        $value .= '"';
                        $at++;

                        continue;
                    }

                    break;
                }

                if (strpbrk($value, "\r\n") !== false) {
                    $broken[$index] = true;
                }

                $run = strcspn($text, $stops, $at);

                if ($run > 0) {
                    $value .= substr($text, $at, $run);
                    $at += $run;
                    $broken[$index] = true;
                }
            } else {
                $run = strcspn($text, $stops, $at);
                $value = substr($text, $at, $run);
                $at += $run;

                if (str_contains($value, '"')) {
                    $broken[$index] = true;
                }
            }

            $cells[] = $value;

            if ($at >= $length) {
                break;
            }

            $stop = $text[$at];

            if ($stop === $separator) {
                $at++;

                if ($at >= $length) {
                    $cells[] = '';

                    break;
                }

                continue;
            }

            if ($stop === "\r") {
                if (substr($text, $at + 1, 1) !== "\n") {
                    throw self::wrongType();
                }

                $at++;
            }

            $at++;

            break;
        }

        return ['cells' => $cells, 'broken' => $broken, 'empty' => ! $quoted && $cells === ['']];
    }
}
