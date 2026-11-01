<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\ProductFields;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-17 `product.create`, without barcodes, which TASK-016 adds
 * (INTERACTION_CONTRACTS RC-17; TECHNICAL_RULES section 7.2, "Product creation";
 * BR-CAT-03, BR-CAT-05, BR-CAT-09, BR-CAT-13; CAP-04, CAP-05). Step 6: the
 * category under its share lock, active. Then step 8: the product, active, with
 * no movement and no stock, whose SKU the unique index judges (SCH-14); its first
 * price revision, the pointer moved to it, and the audit event PRODUCT_CREATE. Its
 * inserts follow the canonical order of SCH-32: one product, whose barcode tokens
 * and mappings come after it.
 *
 * Write steps (TEST_STRATEGY TS-21): the product, the revision, the pointer, the
 * audit event, then the result.
 */
final class CreateProduct extends CatalogAction
{
    public const string OPERATION = 'product.create';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap04, Capability::Cap05];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $fields = ProductFields::fromPayload($payload);
        $sku = self::text($payload['sku'] ?? null);

        $category = $this->assignedCategory($fields->categoryId);
        $this->services->points->afterLock('6');
        self::requireActive($category);

        $product =$this->products->insert($sku, $fields, $commandId);
        $this->written();

        $revision = $this->products->insertRevision($product, $fields->sellingPrice, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->products->pointAtFirstRevision($product, $revision);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'PRODUCT_CREATE',
            self::actorOf($actor),
            ['sku' => $sku, 'name' => $fields->name, 'categoryId' => $fields->categoryId, 'sellingPrice' => $fields->sellingPrice->toCanonical(), 'revisionId' => $revision],
            subject: ['type' => self::PRODUCT, 'id' => $product],
            commandId: $commandId,
            productId: $product,
        );
        $this->written();

        return new StoredResult(self::PRODUCT, $product);
    }
}
