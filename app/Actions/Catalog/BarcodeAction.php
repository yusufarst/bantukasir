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
 * product guard's barcode mappings, G-09), and expects no version.
 *
 * The result is the mapping, with its product and its code in the stored detail:
 * the page shows the record in place at RP-302, and tells the code that it generated
 * (UXI-172). The code is the mapping's number in the answer.
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
        if (! Capabilities::allows($viewer, Capability::Cap06)) {
            return null;
        }

        $product = $result->detail['productId'] ?? null;
        $code = $result->detail['code'] ?? null;

        if (! is_int($product) || ! is_string($code)) {
            throw new LogicException('A barcode command stored its product and its code.');
        }

        return ['type' => $result->type, 'id' => $result->ref, 'number' => $code, 'location' => '/products/'.(string) $product];
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

    protected static function mappingResult(int $mapping, int $product, string $code): StoredResult
    {
        return new StoredResult(self::MAPPING, $mapping, ['productId' => $product, 'code' => $code]);
    }
}
