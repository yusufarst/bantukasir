<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\SearchFirstRequest;
use App\Queries\Catalog\SearchFirst;
use Illuminate\Http\JsonResponse;

/**
 * RD-06, `GET /data/products/check` (ROUTE_CONTRACTS section 3; CAP-04): the exact
 * SKU's product and the similar names, before a product is created (BR-CAT-04).
 */
final class SearchFirstController
{
    public function __invoke(SearchFirstRequest $request, SearchFirst $check): JsonResponse
    {
        $checks = $request->checks();

        return new JsonResponse($check($checks['sku'], $checks['name']), 200);
    }
}
