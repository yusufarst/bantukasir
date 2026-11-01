<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\BarcodeInput;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-27 `barcode.add` (BR-CAT-16 to BR-CAT-19, G-09, RACE-17, RACE-19): a manufacturer
 * token that was never mapped to any product, attached to the product under its lock.
 * A token another command registers at the same time meets it on the unique index,
 * and an inactive product takes one too (UXI-171, "Always").
 *
 * Write steps (TEST_STRATEGY TS-21): the token, the mapping, the active row, the audit
 * event, then the result.
 */
final class AddBarcode extends BarcodeAction
{
    public const string OPERATION = 'barcode.add';

    /** The path of the request that holds the token. */
    private const string TOKEN_FIELD = 'payload.token';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $input = BarcodeInput::fromPayload($payload);
        $product = $this->lockedProduct(self::whole($payload['productId'] ?? null));

        $this->barcodes->refuseIfNotEligible($input, self::TOKEN_FIELD);

        $token = $this->barcodes->insertManufacturerToken($input, self::TOKEN_FIELD);
        $this->written();

        $mapping = $this->attachToken($token, Barcodes::MANUFACTURER, $product->id, $actor, $commandId);

        $this->services->audit->record(
            AuditCategory::Business,
            'BARCODE_ADD',
            self::actorOf($actor),
            ['barcode' => $input->token, 'symbology' => $input->symbology?->value, 'mappingId' => $mapping],
            subject: ['type' => self::MAPPING, 'id' => $mapping],
            commandId: $commandId,
            productId: $product->id,
        );
        $this->written();

        return self::mappingResult($mapping, $product->id, $input->token);
    }
}
