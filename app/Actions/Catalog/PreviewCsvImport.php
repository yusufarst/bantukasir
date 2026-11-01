<?php

namespace App\Actions\Catalog;

use App\Domain\Catalog\Import\CsvDialect;
use App\Domain\Catalog\Import\CsvFileRefused;
use App\Domain\Catalog\Import\CsvImportChecks;
use App\Domain\Catalog\Import\CsvImportFiles;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use SplFileInfo;

/**
 * RR-01 (INTERACTION_CONTRACTS section 6; ROUTE_CONTRACTS section 5; WF-18; TASK-019
 * scope item 2): the file judged by its content, parsed as DOCUMENT_CONTRACTS DC-04 and
 * checked against the catalogue. A refused file is CSV_INVALID and is never stored; any
 * other file lies in the temporary area under a new random key, whatever errors its rows
 * hold, until RC-31 or RR-02 ends it. It writes no business fact and opens no transaction
 * (TR-CMD-03; D-054).
 */
final class PreviewCsvImport
{
    /** The path of the request that holds the file. */
    private const string FILE_FIELD = 'file';

    public function __construct(
        private readonly CsvImportChecks $checks,
        private readonly CsvImportFiles $files,
    ) {}

    /**
     * @return array{importId: string, fileSha256: string, dataRowCount: int, readyRows: int, errorRows: int, rows: list<array{row: int, sku: string, name: string, category: string, sellingPrice: string, errors: list<array{key: string, params: array<string, string|int>}>}>}
     *
     * @throws CommandRefused CSV_INVALID, or VALIDATION_FAILED for a file that cannot be read
     */
    public function __invoke(int $actorId, SplFileInfo $upload): array
    {
        try {
            $bytes = $this->bytesOf($upload);
            $file = CsvDialect::parse($bytes);
        } catch (CsvFileRefused $refusal) {
            throw $refusal->asRefusal(self::FILE_FIELD);
        }

        $checked = $this->checks->check($file);
        $errorRows = $checked->errorRowCount();
        $rowCount = count($checked->rows);

        return [
            'importId' => $this->files->store($actorId, $bytes),
            'fileSha256' => $file->sha256,
            'dataRowCount' => $rowCount,
            'readyRows' => $rowCount - $errorRows,
            'errorRows' => $errorRows,
            'rows' => $checked->previewRows(),
        ];
    }

    /**
     * The bytes of the upload; one above the limit is refused without reading it.
     *
     * @throws CsvFileRefused
     * @throws CommandRefused
     */
    private function bytesOf(SplFileInfo $upload): string
    {
        $path = $upload->getRealPath();
        $size = $upload->getSize();

        if ($path === false || $size === false) {
            throw self::unreadable();
        }

        if ($size > CsvDialect::MAX_BYTES) {
            throw CsvFileRefused::of('ux.validation.upload.too_large', ['ukuran' => CsvDialect::SIZE_LABEL]);
        }

        $bytes = file_get_contents($path);

        return is_string($bytes) ? $bytes : throw self::unreadable();
    }

    private static function unreadable(): CommandRefused
    {
        return new CommandRefused(ErrorCode::ValidationFailed, fields: [self::FILE_FIELD => [['key' => 'ux.validation.upload.unreadable', 'params' => []]]]);
    }
}
