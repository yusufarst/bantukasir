<?php

namespace App\Domain\Catalog\Import;

/**
 * A file that passed the checks of the whole file (DOCUMENT_CONTRACTS DC-04): the SHA-256
 * of its bytes, in lower-case hexadecimal, and its data rows.
 */
final readonly class CsvFile
{
    /**
     * @param  list<CsvRecord>  $records
     */
    public function __construct(
        public string $sha256,
        public array $records,
    ) {}
}
