<?php

namespace App\Domain\Catalog\Import;

/**
 * One data row of the file as text: its row number in the file, the header being row 1,
 * and its cells by column; or, for a row with a different number of fields than the
 * header, only the column that shows it.
 */
final readonly class CsvRecord
{
    /**
     * @param  array<string, string>  $cells  by the value of its CsvColumn
     * @param  list<CsvColumn>  $broken  a cell with a line break, or a quote out of place
     */
    public function __construct(
        public int $number,
        public array $cells,
        public array $broken,
        public ?CsvColumn $wrongCountAt,
    ) {}
}
