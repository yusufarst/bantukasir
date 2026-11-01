<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\BarcodeInput;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Categories;
use App\Domain\Catalog\Import\CheckedImport;
use App\Domain\Catalog\Import\CsvDialect;
use App\Domain\Catalog\Import\CsvFile;
use App\Domain\Catalog\Import\CsvFileRefused;
use App\Domain\Catalog\Import\CsvImportChecks;
use App\Domain\Catalog\Import\CsvImportFiles;
use App\Domain\Catalog\Import\CsvImports;
use App\Domain\Catalog\Import\CsvRow;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use Throwable;

/**
 * RC-31 `csv_import.confirm` (INTERACTION_CONTRACTS RC-31; TECHNICAL_RULES section 7.2,
 * "CSV import confirmation", RACE-22, RACE-29, TR-TIME-02; BR-CAT-37 to BR-CAT-43;
 * DOCUMENT_CONTRACTS DC-04; SEC-UP-04; D-054; D-069 (h); TASK-019 scope item 4). A bounded
 * heavy command: 30 seconds, never retried. After the identity, so that a repeat answers
 * the original although its file is gone, the file is read again and tied to the preview
 * by its checksum, and validated in full. The categories that its rows match take their
 * share locks, ascending, and the whole file is checked again under them: a category
 * deactivated meanwhile, or named only by an inactive one, is CATEGORY_INACTIVE; a row that
 * no longer matches, or any other error, is CSV_INVALID with the errors in `lines`; a code
 * that the catalogue already holds is DUPLICATE_SKU or DUPLICATE_BARCODE, as is one that a
 * race commits first, on the unique index (RACE-22). Then every row through the
 * catalogue's services, all or none (BR-CAT-38): a product with stock 0, its first price
 * revision and its barcode; never a stock movement, a cost or a category (BR-CAT-37). The
 * products carry this command, so the import's list is theirs (`created_command_id`).
 *
 * The temporary file is removed after the commit and after a refusal of the file (ARC-43).
 * An uncertain outcome keeps it, for "Kirim ulang"; housekeeping removes what is left.
 *
 * Write steps (TEST_STRATEGY TS-21): for each row the product, the revision, the pointer
 * and with a barcode the token, the mapping and the active row; then the import record,
 * the audit event and the result.
 */
final class ConfirmCsvImport extends CatalogAction
{
    public const string OPERATION = 'csv_import.confirm';

    /** The result's type and its subject in the audit. */
    private const string IMPORT = 'csv_import';

    /** The path of the request that a refusal of the file names. */
    private const string FILE_FIELD = 'payload.fileSha256';

    public function __construct(
        CommandServices $services,
        Categories $categories,
        Products $products,
        Barcodes $barcodes,
        private readonly CsvImportFiles $files,
        private readonly CsvImportChecks $checks,
        private readonly CsvImports $imports,
    ) {
        parent::__construct($services, $categories, $products, $barcodes);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap10];
    }

    protected function heavy(): bool
    {
        return true;
    }

    /**
     * RP-301 filtered to the products of the import.
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap10)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => '/products?import='.(string) $result->ref];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $file = $this->previewedFile($actor->id, self::text($payload['importId'] ?? null), self::text($payload['fileSha256'] ?? null));

        $this->lockCategories($this->checks->check($file));
        $this->services->points->afterLock('6');

        $checked = $this->checks->check($file);
        self::refuseProblems($checked);

        $productIds = [];
        $barcodesCreated = 0;
        $categories = [];

        foreach ($checked->rows as $row) {
            $productIds[] = $this->createProduct($row, $actor, $commandId);
            $barcodesCreated += $row->barcode === null ? 0 : 1;
            $category = (string) $row->categoryId;
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        $rowCount = count($checked->rows);
        $import = $this->imports->insert($checked->sha256, $rowCount, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'CSV_IMPORT',
            self::actorOf($actor),
            [
                'fileSha256' => $checked->sha256,
                'dataRowCount' => $rowCount,
                'productsCreated' => $rowCount,
                'barcodesCreated' => $barcodesCreated,
                'rowsByCategory' => $categories,
                'productIds' => $productIds,
            ],
            subject: ['type' => self::IMPORT, 'id' => $import],
            commandId: $commandId,
        );
        $this->written();

        return new StoredResult(self::IMPORT, $import);
    }

    protected function settled(CommandContext $context, ?ErrorCode $refusal): void
    {
        if ($refusal !== null && ! in_array($refusal, [ErrorCode::CsvInvalid, ErrorCode::DuplicateSku, ErrorCode::DuplicateBarcode, ErrorCode::CategoryInactive], true)) {
            return;
        }

        $importId = $context->envelope->payload['importId'] ?? null;

        try {
            if (is_string($importId)) {
                $this->files->remove($context->actorId, $importId);
            }
        } catch (Throwable $failure) {
            report($failure);
        }
    }

    /**
     * The file of this actor's preview, with the checksum that the preview gave.
     *
     * @throws CommandRefused CSV_INVALID
     */
    private function previewedFile(int $actorId, string $importId, string $sha256): CsvFile
    {
        $bytes = $this->files->read($actorId, $importId);

        if ($bytes === null || ! hash_equals(hash('sha256', $bytes), $sha256)) {
            throw new CommandRefused(ErrorCode::CsvInvalid, fields: [self::FILE_FIELD => [['key' => 'catalog.csv_import.error.file_changed', 'params' => []]]]);
        }

        try {
            return CsvDialect::parse($bytes);
        } catch (CsvFileRefused $refusal) {
            throw $refusal->asRefusal(self::FILE_FIELD);
        }
    }

    /**
     * The share locks of step 6, ascending (TR §7.1). A category that its lock finds
     * inactive was deactivated after the match (RACE-29).
     *
     * @throws CommandRefused CATEGORY_INACTIVE
     */
    private function lockCategories(CheckedImport $matched): void
    {
        foreach ($matched->categoryIds as $id) {
            $category = $this->categories->lockShared($id);

            if ($category === null || ! $category->active) {
                throw new CommandRefused(ErrorCode::CategoryInactive, params: ['kategori' => $category === null ? '' : $category->name]);
            }
        }
    }

    /**
     * @throws CommandRefused CATEGORY_INACTIVE, CSV_INVALID, DUPLICATE_SKU or DUPLICATE_BARCODE
     */
    private static function refuseProblems(CheckedImport $checked): void
    {
        $lines = $checked->lines();

        if ($checked->inactiveCategory !== null) {
            throw new CommandRefused(ErrorCode::CategoryInactive, params: ['kategori' => $checked->inactiveCategory], lines: $lines);
        }

        if ($checked->holdsOtherErrors() || $checked->rows === []) {
            throw new CommandRefused(ErrorCode::CsvInvalid, params: ['jumlah' => $checked->errorRowCount()], lines: $lines);
        }

        if ($checked->holds(CheckedImport::SKU_TAKEN)) {
            throw new CommandRefused(ErrorCode::DuplicateSku, lines: $lines);
        }

        if ($checked->holds(CheckedImport::BARCODE_TAKEN)) {
            throw new CommandRefused(ErrorCode::DuplicateBarcode, lines: $lines);
        }
    }

    /**
     * One row through the catalogue's services, in the canonical order of SCH-32. A code
     * that a race committed first ends the whole import at the unique index, and names
     * the row (RACE-22).
     *
     * @throws CommandRefused DUPLICATE_SKU or DUPLICATE_BARCODE
     */
    private function createProduct(CsvRow $row, User $actor, int $commandId): int
    {
        $fields = $row->fields();

        try {
            $product = $this->products->insert($row->sku, $fields, $commandId);
        } catch (CommandRefused $refusal) {
            throw self::naming($refusal, $row->number, CheckedImport::SKU_TAKEN);
        }

        $this->written();

        $revision = $this->products->insertRevision($product, $fields->sellingPrice, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->products->pointAtFirstRevision($product, $revision);
        $this->written();

        if ($row->barcode !== null) {
            try {
                $token = $this->barcodes->insertManufacturerToken(new BarcodeInput($row->barcode, null), self::FILE_FIELD);
            } catch (CommandRefused $refusal) {
                throw self::naming($refusal, $row->number, CheckedImport::BARCODE_TAKEN);
            }

            $this->written();

            $this->attachToken($token, Barcodes::MANUFACTURER, $product, $actor, $commandId);
        }

        return $product;
    }

    private static function naming(CommandRefused $refusal, int $row, string $key): CommandRefused
    {
        return new CommandRefused($refusal->error, lines: [$row => ['errors' => [['key' => $key, 'params' => []]]]]);
    }
}
