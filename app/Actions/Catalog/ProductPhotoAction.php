<?php

namespace App\Actions\Catalog;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\ProductRecord;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * The base of RC-25 and RC-26 (INTERACTION_CONTRACTS RC-25, RC-26; TECHNICAL_RULES
 * section 7.2, "Product photo upload, replace or remove"; BR-CAT-44 to BR-CAT-48;
 * D-042; CAP-09): the capability of the photo, on every attempt and result; the result
 * is RP-302 in place; the file a product pointed at stays and is no longer shown. Each
 * expects the product's version, read under its update lock. A photo changes no stock,
 * cost, price or field of the catalogue's audit (BR-CAT-48).
 */
abstract class ProductPhotoAction extends CatalogAction
{
    public function capabilities(): array
    {
        return [Capability::Cap09];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap09)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => '/products/'.(string) $result->ref];
    }

    /**
     * The audit event of the change, with the files it moved between.
     *
     * @param  array<string, int|null>  $files
     */
    protected function audit(string $action, ProductRecord $product, array $files, User $actor, int $commandId): void
    {
        $this->services->audit->record(AuditCategory::Business, $action, self::actorOf($actor), [...$files, 'previousFileId' => $product->photoFileId], subject: ['type' => self::PRODUCT, 'id' => $product->id], commandId: $commandId, productId: $product->id);
        $this->written();
    }
}
