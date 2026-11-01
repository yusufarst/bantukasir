<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\RemoveProductPhoto;

/**
 * The FormRequest of RC-26 `product_photo.remove` (INTERACTION_CONTRACTS RC-26):
 * `productId`, against the expected `productVersion`.
 */
final class RemoveProductPhotoRequest extends ProductLifecycleRequest
{
    public function operation(): string
    {
        return RemoveProductPhoto::OPERATION;
    }
}
