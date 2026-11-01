<?php

namespace Tests\Unit\Catalog;

use App\Domain\Catalog\Import\CsvDialect;
use App\Domain\Catalog\Import\CsvFileRefused;
use App\Domain\Catalog\Import\CsvRow;
use App\Domain\Catalog\Import\CsvRows;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The file of DOCUMENT_CONTRACTS DC-04 read as data, L1 of TASK-019 (AC-019-01, AC-019-02;
 * BR-CAT-39; SEC-UP-05; suite IMPORT01): both separators, the byte-order mark, quoting and
 * line ends, the Indonesian number formats, SKU and barcode as text, every refusal of a
 * whole file and every error of a row that needs no database.
 */
final class CsvDialectTest extends TestCase
{
    private const string HEADER = 'SKU;Nama;Kategori;Merek;Satuan;Desimal;Harga jual;Stok minimum;Barcode';

    private const string ROW = 'PEN-01;Pulpen Hitam;Alat Tulis;Merek A;pcs;0;15.000;2;8991234567890';

    private const string INVALID_VALUE = 'catalog.csv_import.error.invalid_value';

    /**
     * @return list<CsvRow>
     */
    private static function rows(string $bytes): array
    {
        return array_map(CsvRows::read(...), CsvDialect::parse($bytes)->records);
    }

    /**
     * The one data row of a file with the whole header.
     */
    private static function row(string $row): CsvRow
    {
        $rows = self::rows(self::HEADER."\n".$row."\n");
        self::assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * @return list<string>
     */
    private static function keys(CsvRow $row): array
    {
        return array_map(static fn (array $error): string => $error['key'], $row->errors);
    }

    /**
     * @return list<array{key: string, params: array<string, string|int>}>
     */
    private static function refusal(string $bytes): array
    {
        try {
            CsvDialect::parse($bytes);
        } catch (CsvFileRefused $refusal) {
            return $refusal->entries;
        }

        self::fail('The file is refused as a whole.');
    }

    public function test_a_semicolon_file_with_every_column_is_read_as_its_values(): void
    {
        $row = self::row(self::ROW);

        $this->assertSame([], $row->errors);
        $this->assertSame(['PEN-01', 'Pulpen Hitam', 'Alat Tulis', 'Merek A', 'pcs', 0, '15000', '2', '8991234567890'], [
            $row->sku, $row->name, $row->category, $row->brand, $row->baseUnit, $row->quantityPrecision, $row->sellingPrice?->toCanonical(), $row->minimumStock?->toCanonical(), $row->barcode,
        ]);
        $this->assertSame(2, $row->number);
    }

    public function test_a_comma_file_with_quotes_and_crlf_is_read_the_same_way(): void
    {
        $rows = self::rows("SKU,Nama,Kategori,Satuan,Desimal,Harga jual,Stok minimum\r\n007,\"Pulpen, \"\"Hitam\"\"\",Alat Tulis,kg,1,\"1.500,00\",\"1,5\"\r\n");

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]->errors);
        $this->assertSame(['007', 'Pulpen, "Hitam"', 'kg', 1, '1500', '1.5', null, null], [
            $rows[0]->sku, $rows[0]->name, $rows[0]->baseUnit, $rows[0]->quantityPrecision, $rows[0]->sellingPrice?->toCanonical(), $rows[0]->minimumStock?->toCanonical(), $rows[0]->barcode, $rows[0]->brand,
        ]);
    }

    public function test_the_byte_order_mark_is_removed_and_the_checksum_is_of_the_bytes_as_they_came(): void
    {
        $bytes = "\xEF\xBB\xBF".self::HEADER."\n".self::ROW."\n";
        $file = CsvDialect::parse($bytes);

        $this->assertSame(hash('sha256', $bytes), $file->sha256);
        $this->assertCount(1, $file->records);
        $this->assertSame([], CsvRows::read($file->records[0])->errors);
    }

    public function test_the_header_is_in_any_order_trimmed_and_without_regard_to_letter_case(): void
    {
        $rows = self::rows(" HARGA JUAL ;nama;kategori;SATUAN;desimal; sku \n15000;Pulpen;Alat Tulis;pcs;0;PEN-01\n");

        $this->assertSame([], $rows[0]->errors);
        $this->assertSame(['PEN-01', 'Pulpen', '15000', '0'], [$rows[0]->sku, $rows[0]->name, $rows[0]->sellingPrice?->toCanonical(), $rows[0]->minimumStock?->toCanonical()]);
    }

    public function test_empty_lines_at_the_end_are_ignored_and_one_between_rows_is_a_row_error(): void
    {
        $this->assertCount(1, self::rows(self::HEADER."\n".self::ROW."\n\n\r\n\n"));

        $rows = self::rows(self::HEADER."\n".self::ROW."\n\n".str_replace('PEN-01', 'PEN-02', self::ROW)."\n");
        $this->assertSame([2, 3, 4], array_map(static fn (CsvRow $row): int => $row->number, $rows));
        $this->assertSame([self::INVALID_VALUE], self::keys($rows[1]));
        $this->assertSame([], $rows[2]->errors);
    }

    public function test_a_row_with_another_number_of_fields_is_a_row_error_naming_a_column(): void
    {
        $few = self::row('PEN-01;Pulpen;Alat Tulis');
        $this->assertSame([['key' => self::INVALID_VALUE, 'params' => ['kolom' => 'Merek']]], $few->errors);

        $many = self::row(self::ROW.';lagi');
        $this->assertSame([['key' => self::INVALID_VALUE, 'params' => ['kolom' => 'Barcode']]], $many->errors);
    }

    public function test_a_line_break_inside_a_field_or_a_quote_out_of_place_is_an_error_of_that_cell(): void
    {
        foreach ([
            "line feed" => "PEN-01;\"Pulpen\nHitam\";Alat Tulis;;pcs;0;15000;;",
            "carriage return" => "PEN-01;\"Pulpen\rHitam\";Alat Tulis;;pcs;0;15000;;",
            "quote inside" => 'PEN-01;Pulpen 5" Hitam;Alat Tulis;;pcs;0;15000;;',
            "text after a quote" => 'PEN-01;"Pulpen" Hitam;Alat Tulis;;pcs;0;15000;;',
        ] as $case => $row) {
            $this->assertSame([['key' => self::INVALID_VALUE, 'params' => ['kolom' => 'Nama']]], self::row($row)->errors, $case);
        }

        // A quote that never ends takes the rest of the file into its field.
        $rows = self::rows(self::HEADER."\nPEN-01;\"Pulpen;Alat Tulis;;pcs;0;15000;;\nPEN-02;Pensil;Alat Tulis;;pcs;0;15000;;\n");
        $this->assertCount(1, $rows);
        $this->assertSame([self::INVALID_VALUE], self::keys($rows[0]));
    }

    public function test_a_cell_that_begins_with_an_equals_sign_is_refused_in_every_column(): void
    {
        foreach (['=1+1;Pulpen;Alat Tulis;;pcs;0;15000;;', ' =SUM(A1);Pulpen;Alat Tulis;;pcs;0;15000;;', 'PEN-01;=cmd|calc;Alat Tulis;;pcs;0;15000;;', 'PEN-01;Pulpen;=A1;;pcs;0;15000;;', 'PEN-01;Pulpen;Alat Tulis;=A1;pcs;0;15000;;', 'PEN-01;Pulpen;Alat Tulis;;=A1;0;15000;;', 'PEN-01;Pulpen;Alat Tulis;;pcs;=1;15000;;', 'PEN-01;Pulpen;Alat Tulis;;pcs;0;=1;;', 'PEN-01;Pulpen;Alat Tulis;;pcs;0;15000;=1;', 'PEN-01;Pulpen;Alat Tulis;;pcs;0;15000;;=1'] as $row) {
            $this->assertSame(['catalog.csv_import.error.formula'], self::keys(self::row($row)), $row);
        }

        // Inside a cell it is text.
        $this->assertSame([], self::row('A=1;B=2;Alat Tulis;;pcs;0;15000;;')->errors);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function prices(): iterable
    {
        yield 'digits' => ['15000', '15000'];
        yield 'groups' => ['15.000', '15000'];
        yield 'rupiah' => ['Rp15.000', '15000'];
        yield 'rupiah and a space' => ['Rp 15.000', '15000'];
        yield 'one zero' => ['15.000,0', '15000'];
        yield 'two zeros' => ['15.000,00', '15000'];
        yield 'zero' => ['0', '0'];
        yield 'leading zeros' => ['0015', '15'];
        yield 'the largest' => ['999.999.999.999', '999999999999'];
        yield 'above the largest' => ['1.000.000.000.000', null];
        yield 'too many digits' => ['9999999999999', null];
        yield 'a decimal' => ['15000,50', null];
        yield 'a point as a decimal' => ['15.5', null];
        yield 'a wrong group' => ['1500.00', null];
        yield 'a short group' => ['15.00', null];
        yield 'negative' => ['-1', null];
        yield 'plus' => ['+1', null];
        yield 'lower-case rupiah' => ['rp15000', null];
        yield 'two spaces' => ['Rp  15000', null];
        yield 'scientific' => ['1E+5', null];
        yield 'letters' => ['lima ribu', null];
        yield 'a trailing rupiah' => ['15000 Rp', null];
    }

    #[DataProvider('prices')]
    public function test_a_selling_price_in_the_indonesian_format(string $cell, ?string $canonical): void
    {
        $row = self::row('PEN-01;Pulpen;Alat Tulis;;pcs;0;'.$cell.';;');

        $this->assertSame($canonical, $row->sellingPrice?->toCanonical());
        $this->assertSame($canonical === null ? [['key' => self::INVALID_VALUE, 'params' => ['kolom' => 'Harga jual']]] : [], $row->errors);
    }

    /**
     * @return iterable<string, array{string, int, string|null}>
     */
    public static function minimumStocks(): iterable
    {
        yield 'empty' => ['', 0, '0'];
        yield 'whole' => ['2', 0, '2'];
        yield 'a decimal comma' => ['"1,5"', 1, '1.5'];
        yield 'groups' => ['1.500', 0, '1500'];
        yield 'groups and decimals' => ['"1.500,125"', 3, '1500.125'];
        yield 'as many decimals as it may' => ['"1,50"', 2, '1.5'];
        yield 'the largest' => ['"9.999.999.999,999"', 3, '9999999999.999'];
        yield 'above the largest' => ['10.000.000.000', 3, null];
        yield 'more decimals than it may' => ['"1,5"', 0, null];
        yield 'a zero decimal it may not have' => ['"2,0"', 0, null];
        yield 'a point as a decimal' => ['1.5', 1, null];
        yield 'negative' => ['-1', 0, null];
        yield 'letters' => ['dua', 0, null];
    }

    #[DataProvider('minimumStocks')]
    public function test_a_minimum_stock_in_the_indonesian_format(string $cell, int $precision, ?string $canonical): void
    {
        $row = self::row('PEN-01;Pulpen;Alat Tulis;;pcs;'.$precision.';15000;'.$cell.';');

        $this->assertSame($canonical, $row->minimumStock?->toCanonical());
        $this->assertSame($canonical === null ? [['key' => self::INVALID_VALUE, 'params' => ['kolom' => 'Stok minimum']]] : [], $row->errors);
    }

    public function test_the_precision_is_a_whole_number_of_zero_to_three(): void
    {
        foreach (['0' => 0, '1' => 1, '2' => 2, '3' => 3] as $cell => $precision) {
            $this->assertSame($precision, self::row('PEN-01;Pulpen;Alat Tulis;;pcs;'.$cell.';15000;;')->quantityPrecision);
        }

        foreach (['4', '-1', '1.0', '"1,0"', 'dua', '01'] as $cell) {
            $row = self::row('PEN-01;Pulpen;Alat Tulis;;pcs;'.$cell.';15000;2;');
            $this->assertNull($row->quantityPrecision, $cell);
            $this->assertSame([['key' => self::INVALID_VALUE, 'params' => ['kolom' => 'Desimal']]], $row->errors, $cell);
        }
    }

    public function test_sku_and_barcode_are_text_with_their_leading_zeros_and_letter_case(): void
    {
        $row = self::row('007-aB;Pulpen;Alat Tulis;;pcs;0;15000;;  00123aB ');

        $this->assertSame([], $row->errors);
        $this->assertSame(['007-aB', '00123aB'], [$row->sku, $row->barcode]);

        // An empty barcode names no token.
        $this->assertNull(self::row('PEN-01;Pulpen;Alat Tulis;;pcs;0;15000;;')->barcode);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedBarcodes(): iterable
    {
        yield 'scientific with a comma' => ['"8,99E+12"', self::INVALID_VALUE];
        yield 'scientific with a point' => ['8.99E+12', self::INVALID_VALUE];
        yield 'scientific in lower case' => ['8e12', self::INVALID_VALUE];
        yield 'not ASCII' => ['É123', self::INVALID_VALUE];
        yield 'too long' => [str_repeat('1', 129), self::INVALID_VALUE];
        yield 'reserved' => ['BK-P-123', 'catalog.csv_import.error.barcode_reserved'];
    }

    #[DataProvider('refusedBarcodes')]
    public function test_a_barcode_that_is_refused(string $cell, string $key): void
    {
        $row = self::row('PEN-01;Pulpen;Alat Tulis;;pcs;0;15000;;'.$cell);

        $this->assertSame([$key], self::keys($row));
        $this->assertNull($row->barcode);
    }

    public function test_the_lengths_of_the_text_columns_are_in_characters_after_trimming(): void
    {
        $this->assertSame([], self::row(str_repeat('é', 64).';'.str_repeat('é', 200).';Alat Tulis;'.str_repeat('é', 100).';'.str_repeat('é', 32).';0;15000;;')->errors);

        foreach (['SKU' => str_repeat('s', 65).';Pulpen;Alat Tulis;;pcs;0;15000;;', 'Nama' => 'PEN-01;'.str_repeat('n', 201).';Alat Tulis;;pcs;0;15000;;', 'Merek' => 'PEN-01;Pulpen;Alat Tulis;'.str_repeat('m', 101).';pcs;0;15000;;', 'Satuan' => 'PEN-01;Pulpen;Alat Tulis;;'.str_repeat('u', 33).';0;15000;;'] as $column => $row) {
            $this->assertSame([['key' => self::INVALID_VALUE, 'params' => ['kolom' => $column]]], self::row($row)->errors, $column);
        }

        $row = self::row('  PEN-01  ;  Pulpen  ;  Alat Tulis  ;  Merek A  ;  pcs  ;0;15000;;');
        $this->assertSame(['PEN-01', 'Pulpen', 'Alat Tulis', 'Merek A', 'pcs'], [$row->sku, $row->name, $row->category, $row->brand, $row->baseUnit]);
    }

    public function test_each_empty_required_column_is_its_own_error(): void
    {
        $row = self::row(';;;;;;;;');

        $this->assertSame([
            ['key' => 'catalog.csv_import.error.required', 'params' => ['kolom' => 'SKU']],
            ['key' => 'catalog.csv_import.error.required', 'params' => ['kolom' => 'Nama']],
            ['key' => 'catalog.csv_import.error.required', 'params' => ['kolom' => 'Kategori']],
            ['key' => 'catalog.csv_import.error.required', 'params' => ['kolom' => 'Satuan']],
            ['key' => 'catalog.csv_import.error.required', 'params' => ['kolom' => 'Desimal']],
            ['key' => 'catalog.csv_import.error.required', 'params' => ['kolom' => 'Harga jual']],
        ], $row->errors);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongContents(): iterable
    {
        yield 'invalid UTF-8' => [self::HEADER."\nPEN-01;\xFF\xFE;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'an overlong form' => [self::HEADER."\nPEN-01;\xC0\xAF;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'UTF-16 with its mark' => ["\xFF\xFE".mb_convert_encoding(self::HEADER, 'UTF-16LE', 'UTF-8')];
        yield 'Latin-1' => [self::HEADER."\nPEN-01;Caf\xE9;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'a workbook' => ["PK\x03\x04\x14\x00\x06\x00\x08\x00\x00\x00!\x00"];
        yield 'NUL' => [self::HEADER."\nPEN-01;Pul\x00pen;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'a tab' => [self::HEADER."\nPEN-01;Pul\tpen;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'a vertical tab inside quotes' => [self::HEADER."\nPEN-01;\"Pul\x0Bpen\";Alat Tulis;;pcs;0;15000;;\n"];
        yield 'delete' => [self::HEADER."\nPEN-01;Pul\x7Fpen;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'a control character of U+0080 and above' => [self::HEADER."\nPEN-01;Pul\u{0085}pen;Alat Tulis;;pcs;0;15000;;\n"];
        yield 'a carriage return that ends no line' => [self::HEADER."\nPEN-01;Pul\rpen;Alat Tulis;;pcs;0;15000;;\n"];
    }

    #[DataProvider('wrongContents')]
    public function test_a_file_that_is_not_text_csv_in_utf_8_is_refused_by_its_content(string $bytes): void
    {
        $this->assertSame([['key' => 'ux.validation.upload.csv_type', 'params' => []]], self::refusal($bytes));
    }

    public function test_a_character_above_the_controls_that_starts_with_the_same_byte_is_text(): void
    {
        // U+00A0 to U+00BF share the first byte of U+0080 to U+009F.
        $this->assertSame([], self::row("PEN-01;Pulpen \u{00B5}m;Alat Tulis;;pcs;0;15000;;")->errors);
    }

    public function test_a_header_that_no_separator_splits_or_both_split_is_refused(): void
    {
        $required = 'SKU, Nama, Kategori, Satuan, Desimal, Harga jual';

        foreach (['', "\n", 'bukan csv', 'A|B|C', 'SKU', 'SKU,Nama;Kategori,Satuan'] as $header) {
            $this->assertSame([['key' => 'catalog.csv_import.error.missing_column', 'params' => ['kolom' => $required]]], self::refusal($header."\n"), $header);
        }
    }

    public function test_a_missing_required_column_an_unknown_one_or_one_twice_refuses_the_file(): void
    {
        $this->assertSame([['key' => 'catalog.csv_import.error.missing_column', 'params' => ['kolom' => 'Kategori, Harga jual']]], self::refusal("SKU;Nama;Satuan;Desimal\n"));
        $this->assertSame([['key' => 'catalog.csv_import.error.unknown_column', 'params' => ['kolom' => 'Warna, Stok']]], self::refusal(self::HEADER.";Warna;Stok\n"));
        $this->assertSame([['key' => 'catalog.csv_import.error.unknown_column', 'params' => ['kolom' => 'sku']]], self::refusal(self::HEADER.";sku\n"));
        $this->assertSame([
            ['key' => 'catalog.csv_import.error.missing_column', 'params' => ['kolom' => 'Satuan, Desimal, Harga jual']],
            ['key' => 'catalog.csv_import.error.unknown_column', 'params' => ['kolom' => 'Warna']],
        ], self::refusal("SKU;Nama;Kategori;Warna\n"));

        // The optional columns may be absent.
        $this->assertCount(1, self::rows("SKU;Nama;Kategori;Satuan;Desimal;Harga jual\nPEN-01;Pulpen;Alat Tulis;pcs;0;15000\n"));
    }

    public function test_the_limits_of_the_file_are_exact(): void
    {
        $line = "PEN-01;Pulpen;Alat Tulis;;pcs;0;15000;;\n";

        $this->assertCount(5000, CsvDialect::parse(self::HEADER."\n".str_repeat($line, 5000))->records);
        $this->assertCount(5000, CsvDialect::parse(self::HEADER."\n".str_repeat($line, 5000)."\n\n")->records);
        $this->assertSame([['key' => 'ux.validation.upload.csv_rows', 'params' => []]], self::refusal(self::HEADER."\n".str_repeat($line, 5001)));

        // Many empty lines before one row count as the rows they are, and cost no memory for it.
        $this->assertSame([['key' => 'ux.validation.upload.csv_rows', 'params' => []]], self::refusal(self::HEADER."\n".str_repeat("\n", 2000000).$line));

        // The size limit is on the bytes, to the byte, whatever they hold.
        $head = self::HEADER."\n".$line;
        $padding = CsvDialect::MAX_BYTES - strlen($head);
        $this->assertCount(1, CsvDialect::parse($head.str_repeat("\n", $padding))->records);
        $this->assertSame([['key' => 'ux.validation.upload.too_large', 'params' => ['ukuran' => '2 MB']]], self::refusal($head.str_repeat("\n", $padding + 1)));
    }
}
