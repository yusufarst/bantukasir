<?php

namespace App\Domain\Catalog\Import;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Every row of a file against the catalogue (DOCUMENT_CONTRACTS DC-04; BR-CAT-39; D-069
 * (h); TECHNICAL_RULES section 7.2, "CSV import confirmation"): a category name that
 * matches exactly one active category, never creating one; SKUs unique without regard to
 * letter case, in the file and among the products; tokens never mapped before and unique
 * in the file. Whether two texts are the same without regard to letter case is the
 * database's alone, through lower() (SCH-14), so that a preview says what the unique index
 * says at the commit. It reads and writes nothing else, so that RR-01 runs it with no
 * transaction and RC-31 runs it again under its locks.
 */
final class CsvImportChecks
{
    public function check(CsvFile $file): CheckedImport
    {
        $rows = array_map(CsvRows::read(...), $file->records);
        $lowered = self::lowered($rows);
        $inactive = $this->matchCategories($rows, $lowered);
        $this->checkSkus($rows, $lowered);
        $this->checkBarcodes($rows);

        $categoryIds = [];

        foreach ($rows as $row) {
            if ($row->categoryId !== null) {
                $categoryIds[$row->categoryId] = $row->categoryId;
            }
        }

        usort($categoryIds, static fn (int $first, int $second): int => $first <=> $second);

        return new CheckedImport($file->sha256, $rows, $categoryIds, $inactive);
    }

    /**
     * The lower() of every SKU and category name of the rows, by its text.
     *
     * @param  list<CsvRow>  $rows
     * @return array<string, string>
     */
    private static function lowered(array $rows): array
    {
        $texts = [];

        foreach ($rows as $row) {
            foreach ([$row->sku, $row->category] as $text) {
                if ($text !== '') {
                    $texts[$text] = true;
                }
            }
        }

        if ($texts === []) {
            return [];
        }

        $lowered = [];

        foreach (DB::select('select k.v as text, lower(k.v) as lowered from jsonb_array_elements_text(cast(? as jsonb)) as k(v)', [self::json(array_keys($texts))]) as $row) {
            $values = (array) $row;
            $text = $values['text'] ?? null;
            $lower = $values['lowered'] ?? null;

            if (! is_string($text) || ! is_string($lower)) {
                throw new LogicException('The database lowers each text it is given.');
            }

            $lowered[$text] = $lower;
        }

        return $lowered;
    }

    /**
     * The category of each row, or its error. Returns a name that only an inactive
     * category has, which RC-31 refuses as a category deactivated after the preview.
     *
     * @param  list<CsvRow>  $rows
     * @param  array<string, string>  $lowered
     */
    private function matchCategories(array $rows, array $lowered): ?string
    {
        $active = [];
        $inactive = [];

        foreach (DB::select('select id, active, lower(name) as lowered from categories') as $category) {
            $values = (array) $category;
            $id = $values['id'] ?? null;
            $name = $values['lowered'] ?? null;

            if (! is_int($id) || ! is_string($name)) {
                throw new LogicException('A category row holds its columns (SCHEMA section 5.6).');
            }

            if (($values['active'] ?? null) === true) {
                $active[$name][] = $id;
            } else {
                $inactive[$name] = true;
            }
        }

        $inactiveName = null;

        foreach ($rows as $row) {
            if ($row->category === '') {
                continue;
            }

            $name = $lowered[$row->category] ?? null;
            $matches = $name === null ? [] : ($active[$name] ?? []);

            if (count($matches) === 1) {
                $row->categoryId = $matches[0];
            } elseif ($matches === []) {
                $row->addError('catalog.csv_import.error.category_missing');

                if ($name !== null && isset($inactive[$name])) {
                    $inactiveName ??= $row->category;
                }
            } else {
                $row->addError('catalog.csv_import.error.category_ambiguous');
            }
        }

        return $inactiveName;
    }

    /**
     * @param  list<CsvRow>  $rows
     * @param  array<string, string>  $lowered
     */
    private function checkSkus(array $rows, array $lowered): void
    {
        $counts = [];

        foreach ($rows as $row) {
            $key = $row->sku === '' ? null : ($lowered[$row->sku] ?? null);

            if ($key !== null) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return;
        }

        $taken = [];

        foreach (DB::select('select lower(p.sku) as lowered from products p where lower(p.sku) in (select k.v from jsonb_array_elements_text(cast(? as jsonb)) as k(v))', [self::json(array_keys($counts))]) as $product) {
            $lower = ((array) $product)['lowered'] ?? null;

            if (is_string($lower)) {
                $taken[$lower] = true;
            }
        }

        foreach ($rows as $row) {
            $key = $row->sku === '' ? null : ($lowered[$row->sku] ?? null);

            if ($key === null) {
                continue;
            }

            if (($counts[$key] ?? 0) > 1) {
                $row->addError('catalog.csv_import.error.sku_duplicate');
            }

            if (isset($taken[$key])) {
                $row->addError(CheckedImport::SKU_TAKEN);
            }
        }
    }

    /**
     * A token that exists was mapped, active or retired (BR-CAT-19): it is taken. A token
     * is compared exactly (BR-CAT-17).
     *
     * @param  list<CsvRow>  $rows
     */
    private function checkBarcodes(array $rows): void
    {
        $counts = [];

        foreach ($rows as $row) {
            if ($row->barcode !== null) {
                $counts[$row->barcode] = ($counts[$row->barcode] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return;
        }

        $taken = [];

        foreach (DB::select('select t.token from barcode_tokens t where t.token in (select k.v from jsonb_array_elements_text(cast(? as jsonb)) as k(v))', [self::json(array_keys($counts))]) as $token) {
            $text = ((array) $token)['token'] ?? null;

            if (is_string($text)) {
                $taken[$text] = true;
            }
        }

        foreach ($rows as $row) {
            if ($row->barcode === null) {
                continue;
            }

            if ($counts[$row->barcode] > 1) {
                $row->addError('catalog.csv_import.error.barcode_duplicate');
            }

            if (isset($taken[$row->barcode])) {
                $row->addError(CheckedImport::BARCODE_TAKEN);
            }
        }
    }

    /**
     * The texts as a JSON array for the database; a key of PHP may have read a text of
     * digits as a whole number.
     *
     * @param  array<array-key, int|string>  $texts
     */
    private static function json(array $texts): string
    {
        return json_encode(array_map(strval(...), array_values($texts)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
