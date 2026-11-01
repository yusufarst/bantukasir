<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-26 `product_photo.remove` (INTERACTION_CONTRACTS RC-26; TASK-018 scope item 1):
 * under the product's update lock, at the version the user reviewed, the product points
 * at no photo; its file stays and is no longer shown (D-042), and a product without a
 * photo shows the placeholder (BR-CAT-44). A product without a photo has nothing to
 * remove. Audit PHOTO_REMOVE.
 *
 * Write steps (TEST_STRATEGY TS-21): the product, the audit event, the result.
 */
final class RemoveProductPhoto extends ProductPhotoAction
{
    public const string OPERATION = 'product_photo.remove';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $product = $this->productToChange($context->envelope->payload, $context->envelope->expected);

        if ($product->photoFileId === null) {
            throw self::nothingToChange();
        }

        $this->products->setPhoto($product, null);
        $this->written();

        $this->audit('PHOTO_REMOVE', $product, [], $actor, $commandId);

        return new StoredResult(self::PRODUCT, $product->id);
    }
}
