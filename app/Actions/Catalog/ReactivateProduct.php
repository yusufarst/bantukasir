<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-20 `product.reactivate` (INTERACTION_CONTRACTS RC-20; BR-CAT-08; D-042;
 * CAP-04): under the product lock, at the version the user reviewed, the active
 * flag on, audited. An active product is refused. Its category may be inactive:
 * this assigns none (BR-CAT-09).
 *
 * Write steps (TEST_STRATEGY TS-21): the product, the audit event, the result.
 */
final class ReactivateProduct extends CatalogAction
{
    public const string OPERATION = 'product.reactivate';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $product = $this->productToChange($context->envelope->payload, $context->envelope->expected);

        if ($product->active) {
            throw self::nothingToChange();
        }

        $this->products->setActive($product, true);
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'PRODUCT_REACTIVATE', self::actorOf($actor), [], subject: ['type' => self::PRODUCT, 'id' => $product->id], commandId: $commandId, productId: $product->id);
        $this->written();

        return new StoredResult(self::PRODUCT, $product->id);
    }
}
