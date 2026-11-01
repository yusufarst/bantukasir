<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use LogicException;

/**
 * RC-29 `barcode.retire` (BR-CAT-19, BR-CAT-52, G-09, RACE-19): the mapping is read
 * first without a lock, only to choose its product's lock, since a mapping never moves.
 * Under the lock it must still be active, otherwise STALE_VERSION: the user reviewed a
 * mapping that has ended. The retirement records its reason class, and the active row
 * goes in the same transaction, so that a mapping with a retirement never has one.
 *
 * Write steps (TEST_STRATEGY TS-21): the retirement, the active row, the audit event,
 * then the result.
 */
final class RetireBarcode extends BarcodeAction
{
    public const string OPERATION = 'barcode.retire';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $reasonClass = $payload['reasonClass'] ?? null;

        if ($reasonClass !== 'MISASSIGNMENT' && $reasonClass !== 'OTHER') {
            throw new LogicException('A reason class of the payload was validated before the Action.');
        }

        $mapping = $this->barcodes->mapping(self::whole($payload['mappingId'] ?? null)) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $product = $this->lockedProduct($mapping->productId);

        if (! $this->barcodes->isActive($mapping->id)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $this->barcodes->insertRetirement($mapping->id, $reasonClass, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->barcodes->deactivate($mapping->id);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'BARCODE_RETIRE',
            self::actorOf($actor),
            ['barcode' => $mapping->token, 'reasonClass' => $reasonClass, 'mappingId' => $mapping->id],
            subject: ['type' => self::MAPPING, 'id' => $mapping->id],
            commandId: $commandId,
            productId: $product->id,
        );
        $this->written();

        return self::mappingResult($mapping->id, $product->id, $mapping->token);
    }
}
