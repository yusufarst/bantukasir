<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\DeactivateProduct;

/**
 * The FormRequest of RC-19 `product.deactivate`.
 */
final class DeactivateProductRequest extends ProductLifecycleRequest
{
    public function operation(): string
    {
        return DeactivateProduct::OPERATION;
    }
}
