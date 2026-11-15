<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Http\Requests\Catalog\ProductSearchRequest;
use App\Models\User;
use App\Queries\Catalog\ProductSearch;
use App\Queries\Inventory\OpeningEligibility;
use App\Queries\Inventory\ProductStock;
use Illuminate\Http\JsonResponse;

/**
 * RD-01, `GET /data/products/search` (ROUTE_CONTRACTS section 3): one page of the
 * shared Product Search for the context that the request's authorization opened,
 * with the stock that the viewer's cell of CAP-14 allows (TASK-021), in the context
 * `opening` each product's eligibility of D-087 (TASK-035), and the client's
 * sequence number repeated in the answer's header.
 */
final class SearchController
{
    public function __invoke(ProductSearchRequest $request, ProductSearch $search, ProductStock $stock, OpeningEligibility $eligibility): JsonResponse
    {
        $viewer = $request->user();
        $grant = $viewer instanceof User ? Capabilities::of($viewer, Capability::Cap14) : Grant::None;
        $page = $stock->added($search($request->filters()), $grant);

        if ($request->context() === ProductSearchRequest::OPENING) {
            $page = $eligibility->added($page);
        }

        $answer = new JsonResponse($page, 200);
        $sequence = $request->sequence();

        if ($sequence !== null) {
            $answer->headers->set(ProductSearchRequest::SEQUENCE_HEADER, $sequence);
        }

        return $answer;
    }
}
