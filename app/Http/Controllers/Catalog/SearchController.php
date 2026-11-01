<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\ProductSearchRequest;
use App\Queries\Catalog\ProductSearch;
use Illuminate\Http\JsonResponse;

/**
 * RD-01, `GET /data/products/search` (ROUTE_CONTRACTS section 3): one page of the
 * shared Product Search for the context that the request's authorization opened,
 * with the client's sequence number repeated in the answer's header.
 */
final class SearchController
{
    public function __invoke(ProductSearchRequest $request, ProductSearch $search): JsonResponse
    {
        $answer = new JsonResponse($search($request->filters()), 200);
        $sequence = $request->sequence();

        if ($sequence !== null) {
            $answer->headers->set(ProductSearchRequest::SEQUENCE_HEADER, $sequence);
        }

        return $answer;
    }
}
