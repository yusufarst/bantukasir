<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-19 `product.deactivate` (INTERACTION_CONTRACTS RC-19; BR-CAT-08; D-042;
 * RACE-23; CAP-04): under the product lock, at the version the user reviewed, the
 * active flag off, audited. Nothing is deleted: the product stays readable in
 * search results, history and documents. An inactive product is refused.
 *
 * Write steps (TEST_STRATEGY TS-21): the product, the audit event, the result.
 */
final class DeactivateProduct extends CatalogAction
{
    public const string OPERATION = 'product.deactivate';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $product = $this->productToChange($context->envelope->payload, $context->envelope->expected);

        if (! $product->active) {
            throw self::nothingToChange();
        }

        $this->products->setActive($product, false);
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'PRODUCT_DEACTIVATE', self::actorOf($actor), [], subject: ['type' => self::PRODUCT, 'id' => $product->id], commandId: $commandId, productId: $product->id);
        $this->written();

        return new StoredResult(self::PRODUCT, $product->id);
    }
}
