<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Requests\Catalog\TokenLookupRequest;
use App\Queries\Catalog\TokenMapping;
use Illuminate\Http\JsonResponse;

/**
 * RD-05, `GET /data/barcode-tokens/lookup` (ROUTE_CONTRACTS section 3; CAP-07): the token's
 * kind, active product and retirement records, and whether it is correctable (D-051, D-052).
 */
final class TokenLookupController
{
    public function __invoke(TokenLookupRequest $request, TokenMapping $mapping): JsonResponse
    {
        return new JsonResponse($mapping($request->token())->toArray(), 200);
    }
}
