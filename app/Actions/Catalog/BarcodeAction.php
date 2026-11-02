<?php

namespace App\Actions\Catalog;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Catalog\ProductRecord;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use LogicException;

/**
 * The base of RC-27 to RC-29 (INTERACTION_CONTRACTS, "RC-27 to RC-29 barcodes";
 * TECHNICAL_RULES section 7.2, "Barcode add, generate, retire"; BR-CAT-22; CAP-06).
 * Each locks the product of the mapping it changes (step 6, the update lock: the
 * product guard's barcode mappings, G-09), and expects no version. RC-30 shares its
 * answer, under its own capability (TASK-020).
 *
 * The result stores the mapping's key alone, since a token is neither an internal key
 * nor a document number (SCHEMA section 5.3). The answer is rebuilt from the mapping,
 * which never changes: the page shows the record in place at RP-302, and tells the
 * code that it generated (UXI-172). The code is the mapping's number in the answer.
 */
abstract class BarcodeAction extends CatalogAction
{
    protected const string MAPPING = 'barcode_mapping';

    public function capabilities(): array
    {
        return [Capability::Cap06];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, $this->capabilities()[0])) {
            return null;
        }

        $mapping = $result->ref === null ? null : $this->barcodes->mapping($result->ref);

        if ($mapping === null) {
            throw new LogicException('A barcode command stored the mapping that it wrote or retired.');
        }

        return ['type' => $result->type, 'id' => $mapping->id, 'number' => $mapping->token, 'location' => '/products/'.(string) $mapping->productId];
    }

    /**
     * The product that the command names, under its update lock.
     *
     * @throws CommandRefused NOT_FOUND
     */
    protected function lockedProduct(int $id): ProductRecord
    {
        $product = $this->products->lock($id);
        $this->services->points->afterLock('6');

        return $product ?? throw CommandRefused::of(ErrorCode::NotFound);
    }

    protected static function mappingResult(int $mapping): StoredResult
    {
        return new StoredResult(self::MAPPING, $mapping);
    }
}
