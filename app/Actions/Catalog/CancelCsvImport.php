<?php

namespace App\Actions\Catalog;

use App\Domain\Catalog\Import\CsvImportFiles;

/**
 * RR-02 (INTERACTION_CONTRACTS section 6; ROUTE_CONTRACTS section 5; D-054; TASK-019 scope
 * item 3): removes the temporary file of the actor's own preview, and is the same for a
 * missing or expired import, or another actor's, which it cannot reach. No business fact.
 */
final class CancelCsvImport
{
    public function __construct(private readonly CsvImportFiles $files) {}

    public function __invoke(int $actorId, string $importId): void
    {
        $this->files->remove($actorId, $importId);
    }
}
