<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Categories;
use App\Domain\Catalog\ProductFields;
use App\Domain\Catalog\ProductRecord;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Inventory\StockLedger;
use App\Models\User;

/**
 * RC-18 `product.update` (INTERACTION_CONTRACTS RC-18; TECHNICAL_RULES section
 * 7.2, "Price change; product edit"; SCHEMA section 7; BR-CAT-07 to BR-CAT-13;
 * RACE-05, RACE-23, RACE-29; CAP-04, and CAP-05 for a price). The row is read
 * first without a lock, only to choose the locks that step 6 takes in its order:
 * a newly chosen category under its share lock, then the product under its update
 * lock, FOR UPDATE when the unit or the precision changes. Under the locks the
 * version must be the one the user reviewed and the row as it was read, otherwise
 * the choice was made on another row: STALE_VERSION. A newly chosen category must
 * be active; a category the product already has stays assignable to it even when
 * it is inactive (BR-CAT-09, "cannot be chosen"). A changed price appends a revision
 * and moves the pointer (BR-CAT-13); no other route changes a price. A request
 * that changes nothing is refused. PRODUCT_EDIT records each changed field's value
 * before and after it, because `products` keeps only the last (BR-AUD-02); the
 * price's are in PRICE_CHANGE.
 *
 * Under the same lock a product that has a stock movement refuses a change of its unit
 * or precision at the field (BR-CAT-07; TASK-021): a posting holds the same lock, so an
 * edit either commits before the first movement, which then finds a new version, or is
 * refused (RACE-05). The composite key of `stock_movements` is the database's guarantee
 * of it.
 *
 * Write steps (TEST_STRATEGY TS-21): the revision when the price changes; the
 * product; PRODUCT_EDIT; PRICE_CHANGE when the price changes; then the result.
 */
final class UpdateProduct extends CatalogAction
{
    public const string OPERATION = 'product.update';

    public function __construct(
        CommandServices $services,
        Categories $categories,
        Products $products,
        Barcodes $barcodes,
        private readonly StockLedger $ledger,
    ) {
        parent::__construct($services, $categories, $products, $barcodes);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $fields = ProductFields::fromPayload($payload);
        $id = self::whole($payload['productId'] ?? null);
        $seen = $this->products->read($id) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $categoryChanges = $fields->categoryId !== $seen->categoryId;
        $unitChanges = $seen->unitChangedBy($fields);

        $category = $categoryChanges ? $this->assignedCategory($fields->categoryId) : null;
        $product = $this->products->lock($id, $unitChanges) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $this->services->points->afterLock('6');

        if ($product->version !== ($context->envelope->expected['productVersion'] ?? null)
            || $product->categoryId !== $seen->categoryId
            || $product->unitChangedBy($fields) !== $unitChanges) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        if ($unitChanges && $this->ledger->hasMovement($id)) {
            throw self::unitLocked($product, $fields);
        }

        if ($category !== null) {
            self::requireActive($category);
        }

        $changed = $product->changedBy($fields);

        if ($changed === []) {
            throw self::nothingToChange();
        }

        $priceChanges = in_array('sellingPrice', $changed, true);
        $revision = $product->currentPriceRevisionId;

        if ($priceChanges) {
            if (! Capabilities::allows($actor, Capability::Cap05)) {
                throw new ActorNotAllowed;
            }

            $revision = $this->products->insertRevision($id, $fields->sellingPrice, $actor->id, $actor->display_name, $commandId);
            $this->written();
        }

        $this->products->edit($product, $fields, $revision);
        $this->written();

        $subject = ['type' => self::PRODUCT, 'id' => $id];

        $this->services->audit->record(
            AuditCategory::Business,
            'PRODUCT_EDIT',
            self::actorOf($actor),
            ['changed' => $changed, 'from' => (object) $product->auditValues($changed), 'to' => (object) $fields->auditValues($changed)],
            subject: $subject,
            commandId: $commandId,
            productId: $id,
        );
        $this->written();

        if ($priceChanges) {
            $this->services->audit->record(
                AuditCategory::Business,
                'PRICE_CHANGE',
                self::actorOf($actor),
                ['from' => $product->sellingPrice->toCanonical(), 'to' => $fields->sellingPrice->toCanonical(), 'revisionId' => $revision, 'previousRevisionId' => $product->currentPriceRevisionId],
                subject: $subject,
                commandId: $commandId,
                productId: $id,
            );
            $this->written();
        }

        return new StoredResult(self::PRODUCT, $id);
    }

    /**
     * The refusal of BR-CAT-07, at each field that would change (INTERACTION_CONTRACTS RC-18).
     */
    private static function unitLocked(ProductRecord $product, ProductFields $fields): CommandRefused
    {
        $lock = [['key' => 'catalog.field.unit_lock_help', 'params' => []]];
        $refused = [];

        if ($fields->baseUnit !== $product->baseUnit) {
            $refused['payload.baseUnit'] = $lock;
        }

        if ($fields->quantityPrecision !== $product->quantityPrecision) {
            $refused['payload.quantityPrecision'] = $lock;
        }

        return new CommandRefused(ErrorCode::ValidationFailed, fields: $refused);
    }
}
