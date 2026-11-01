<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\BarcodeInput;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\ProductFields;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-17 `product.create` (INTERACTION_CONTRACTS RC-17; TECHNICAL_RULES section 7.2,
 * "Product creation"; BR-CAT-03, BR-CAT-05, BR-CAT-09, BR-CAT-13; CAP-04, CAP-05,
 * and CAP-06 for a barcode). Step 6: the category under its share lock, active.
 * A barcode passes the checks of RC-27 under it. Then step 8: the product, active,
 * with no movement and no stock, whose SKU the unique index judges (SCH-14); its
 * first price revision, the pointer moved to it; the token, its mapping and its
 * active row; and the audit event PRODUCT_CREATE. Its inserts follow the canonical
 * order of SCH-32: the product, then the token, then its active mapping.
 *
 * Write steps (TEST_STRATEGY TS-21): the product, the revision, the pointer; with a
 * barcode the token, the mapping, the active row; the audit event, then the result.
 */
final class CreateProduct extends CatalogAction
{
    public const string OPERATION = 'product.create';

    /** The path of the request that holds the token. */
    private const string TOKEN_FIELD = 'payload.barcode.token';

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
        $barcodeFields = $payload['barcode'] ?? null;
        $barcode = is_array($barcodeFields) ? BarcodeInput::fromPayload($barcodeFields) : null;

        if ($barcode !== null && ! Capabilities::allows($actor, Capability::Cap06)) {
            throw new ActorNotAllowed;
        }

        $category = $this->assignedCategory($fields->categoryId);
        $this->services->points->afterLock('6');
        self::requireActive($category);

        if ($barcode !== null) {
            $this->barcodes->refuseIfNotEligible($barcode, self::TOKEN_FIELD);
        }

        $product = $this->products->insert($sku, $fields, $commandId);
        $this->written();

        $revision = $this->products->insertRevision($product, $fields->sellingPrice, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->products->pointAtFirstRevision($product, $revision);
        $this->written();

        $data = ['sku' => $sku, 'name' => $fields->name, 'categoryId' => $fields->categoryId, 'sellingPrice' => $fields->sellingPrice->toCanonical(), 'revisionId' => $revision];

        if ($barcode !== null) {
            $token = $this->barcodes->insertManufacturerToken($barcode, self::TOKEN_FIELD);
            $this->written();

            $mapping = $this->attachToken($token, Barcodes::MANUFACTURER, $product, $actor, $commandId);
            $data += ['barcode' => $barcode->token, 'symbology' => $barcode->symbology?->value, 'mappingId' => $mapping];
        }

        $this->services->audit->record(
            AuditCategory::Business,
            'PRODUCT_CREATE',
            self::actorOf($actor),
            $data,
            subject: ['type' => self::PRODUCT, 'id' => $product],
            commandId: $commandId,
            productId: $product,
        );
        $this->written();

        return new StoredResult(self::PRODUCT, $product);
    }
}
