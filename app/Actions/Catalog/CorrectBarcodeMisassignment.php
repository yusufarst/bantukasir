<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Barcodes\BarcodeToken;
use App\Domain\Catalog\Barcodes\CurrentMapping;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use LogicException;

/**
 * RC-30 `barcode.correct_misassignment` (INTERACTION_CONTRACTS RC-30; TECHNICAL_RULES section
 * 7.2; BR-CAT-19, BR-CAT-50 to BR-CAT-52; D-051, D-052; G-09; RACE-17 to RACE-19): the Owner
 * moves a manufacturer token that is active on the wrong product, or re-attaches one retired
 * as a misassignment, in one transaction.
 *
 * The token's last mapping is read first without a lock, only to choose the locks: its
 * product and the target, ascending (step 6, the update lock of each). Under them it is read
 * again and judged, in the order of RC-30: a token that cannot be corrected is
 * BARCODE_NOT_ELIGIBLE; a mapping that is not the one the Owner reviewed is STALE_VERSION. A
 * mapping other than the one read first stands on a product whose lock this command may not
 * hold, so it too is STALE_VERSION, even when it looks as reviewed: the token moved away and
 * back meanwhile.
 *
 * A move retires the wrong mapping as a misassignment and removes its active row before it
 * adds the correction and its active row, so that a lookup in another session sees the
 * product before or after this transaction, never none and never both (RACE-18). Earlier
 * mappings and retirements stay as they are (BR-AUD-03), and neither product's version
 * changes: a barcode is no field of its product. A target that became inactive still takes the
 * token, as RC-27 lets it: RD-01 offers active products only, and RC-30 has no refusal for one.
 *
 * Write steps (TEST_STRATEGY TS-21). A move: the retirement, the active row, the correction,
 * its active row, the audit event, then the result. A re-attachment: the correction, its
 * active row, the audit event, then the result.
 */
final class CorrectBarcodeMisassignment extends BarcodeAction
{
    public const string OPERATION = 'barcode.correct_misassignment';

    /** The kinds of correction that the audit event names (D-051, D-052). */
    private const string MOVE = 'MOVE';

    private const string REATTACH = 'REATTACH';

    /** The path of the request that holds the token. */
    private const string TOKEN_FIELD = 'payload.token';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap07];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $token = BarcodeToken::normalized(self::text($payload['token'] ?? null));
        $target = self::whole($payload['targetProductId'] ?? null);
        $reason = self::text($payload['reason'] ?? null);

        $seen = $this->barcodes->currentMapping($token);
        $this->lockBoth($seen?->productId, $target);

        $current = $this->barcodes->currentMapping($token);

        if ($current === null || ! $current->correctableTo($target)) {
            throw Barcodes::notCorrectable(self::TOKEN_FIELD);
        }

        if (! self::isAsReviewed($current, $context->envelope->expected) || $current->mappingId !== $seen?->mappingId) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        if ($current->active) {
            $this->barcodes->insertRetirement($current->mappingId, Barcodes::MISASSIGNMENT, $actor->id, $actor->display_name, $commandId);
            $this->written();

            $this->barcodes->deactivate($current->mappingId);
            $this->written();
        }

        $mapping = $this->barcodes->insertCorrection($current->tokenId, $current->mappingId, $target, $reason, $actor->id, $actor->display_name, $commandId, self::TOKEN_FIELD);
        $this->written();

        $this->barcodes->activateCorrection($current->tokenId, $mapping, $target, self::TOKEN_FIELD);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'BARCODE_CORRECTION',
            self::actorOf($actor),
            [
                'barcode' => $token,
                'kind' => $current->active ? self::MOVE : self::REATTACH,
                'fromProductId' => $current->productId,
                'toProductId' => $target,
                'correctedMappingId' => $current->mappingId,
                'mappingId' => $mapping,
            ],
            subject: ['type' => self::MAPPING, 'id' => $mapping],
            reason: $reason,
            commandId: $commandId,
            productId: $target,
        );
        $this->written();

        return self::mappingResult($mapping);
    }

    /**
     * Both products under their update locks, ascending, the same for every command
     * (TR-LOCK-05): two corrections that cross never wait for each other in a circle. A
     * product is never deleted, so only a target that never existed can be missing.
     *
     * @throws CommandRefused NOT_FOUND
     */
    private function lockBoth(?int $wrong, int $target): void
    {
        $products = array_unique($wrong === null ? [$target] : [$wrong, $target]);
        sort($products);

        foreach ($products as $product) {
            if ($this->products->lock($product) === null) {
                throw CommandRefused::of(ErrorCode::NotFound);
            }
        }

        $this->services->points->afterLock('6');
    }

    /**
     * Whether the mapping is what RD-05 showed the Owner: the product the token was on, and
     * whether it was active or retired as a misassignment (RC-30).
     *
     * @param  array<array-key, mixed>  $expected
     */
    private static function isAsReviewed(CurrentMapping $current, array $expected): bool
    {
        $reviewed = $expected['mapping'] ?? null;

        if (! is_array($reviewed)) {
            throw new LogicException('The expected mapping was validated before the Action.');
        }

        return ($reviewed['productId'] ?? null) === $current->productId && ($reviewed['state'] ?? null) === $current->correctableState();
    }
}
