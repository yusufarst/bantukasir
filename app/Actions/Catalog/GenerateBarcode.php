<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-28 `barcode.generate` (BR-CAT-18, BR-CAT-19): a new internal code, drawn again on
 * a clash, attached to the product under its lock (UXI-172, "Always").
 *
 * Write steps (TEST_STRATEGY TS-21): the token, the mapping, the active row, the audit
 * event, then the result.
 */
final class GenerateBarcode extends BarcodeAction
{
    public const string OPERATION = 'barcode.generate';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $product = $this->lockedProduct(self::whole($context->envelope->payload['productId'] ?? null));

        $token = $this->barcodes->insertInternalToken();
        $this->written();

        $mapping = $this->attachToken($token['id'], Barcodes::INTERNAL, $product->id, $actor, $commandId);

        $this->services->audit->record(
            AuditCategory::Business,
            'BARCODE_GENERATE',
            self::actorOf($actor),
            ['barcode' => $token['code'], 'mappingId' => $mapping],
            subject: ['type' => self::MAPPING, 'id' => $mapping],
            commandId: $commandId,
            productId: $product->id,
        );
        $this->written();

        return self::mappingResult($mapping);
    }
}
