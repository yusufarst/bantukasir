<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\ReactivateProduct;

/**
 * The FormRequest of RC-20 `product.reactivate`.
 */
final class ReactivateProductRequest extends ProductLifecycleRequest
{
    public function operation(): string
    {
        return ReactivateProduct::OPERATION;
    }
}
