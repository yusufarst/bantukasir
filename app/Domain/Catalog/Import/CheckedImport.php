<?php

namespace App\Domain\Catalog\Import;

/**
 * A file with every row read and checked against the catalogue (DOCUMENT_CONTRACTS DC-04):
 * what RR-01 previews and what RC-31 refuses or creates.
 */
final readonly class CheckedImport
{
    public const string SKU_TAKEN = 'catalog.csv_import.error.sku_taken';

    public const string BARCODE_TAKEN = 'catalog.csv_import.error.barcode_taken';

    /**
     * @param  list<CsvRow>  $rows
     * @param  list<int>  $categoryIds  the categories that the rows match, ascending
     * @param  string|null  $inactiveCategory  a name that only an inactive category has
     */
    public function __construct(
        public string $sha256,
        public array $rows,
        public array $categoryIds,
        public ?string $inactiveCategory,
    ) {}

    /**
     * A row whose category the command does not hold locked matched another one after the
     * locks were taken: it no longer matches what was locked, and is refused
     * (INTERACTION_CONTRACTS RC-31; RACE-29).
     *
     * @param  list<int>  $locked
     */
    public function refuseRowsOutside(array $locked): void
    {
        foreach ($this->rows as $row) {
            if ($row->categoryId !== null && ! in_array($row->categoryId, $locked, true)) {
                $row->categoryId = null;
                $row->addError('catalog.csv_import.error.category_missing');
            }
        }
    }

    public function errorRowCount(): int
    {
        return count(array_filter($this->rows, static fn (CsvRow $row): bool => $row->errors !== []));
    }

    /**
     * Whether any row holds this key of an error.
     */
    public function holds(string $key): bool
    {
        foreach ($this->rows as $row) {
            foreach ($row->errors as $error) {
                if ($error['key'] === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a row holds an error other than a code that the catalogue already has.
     */
    public function holdsOtherErrors(): bool
    {
        foreach ($this->rows as $row) {
            foreach ($row->errors as $error) {
                if ($error['key'] !== self::SKU_TAKEN && $error['key'] !== self::BARCODE_TAKEN) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The errors by row number, as `lines` of the error body carries them
     * (INTERACTION_CONTRACTS section 1.6).
     *
     * @return array<int, array{errors: list<array{key: string, params: array<string, string|int>}>}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->rows as $row) {
            if ($row->errors !== []) {
                $lines[$row->number] = ['errors' => $row->errors];
            }
        }

        return $lines;
    }

    /**
     * What RR-01 answers of each row: its row number, the texts it names and its errors.
     *
     * @return list<array{row: int, sku: string, name: string, category: string, sellingPrice: string, errors: list<array{key: string, params: array<string, string|int>}>}>
     */
    public function previewRows(): array
    {
        return array_map(static fn (CsvRow $row): array => [
            'row' => $row->number,
            'sku' => $row->sku,
            'name' => $row->name,
            'category' => $row->category,
            'sellingPrice' => $row->sellingPriceText,
            'errors' => $row->errors,
        ], $this->rows);
    }
}
