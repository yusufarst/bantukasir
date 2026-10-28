<?php

namespace App\Http\Controllers\Commands;

use App\Http\Requests\Commands\UnresolvedRequestsRequest;
use App\Models\User;
use App\Queries\Commands\UnresolvedRequests;
use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * RD-04, `GET /data/command-requests` (ROUTE_CONTRACTS section 3; CAP-34):
 * the viewer's unresolved request receipts, or every user's for the Owner
 * with `scope=all`.
 */
final class RequestsDataController
{
    public function __invoke(UnresolvedRequestsRequest $request, UnresolvedRequests $query): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RD-04 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        $page = $request->page();

        return new JsonResponse($query($user, $page['everyUser'], $page['after'], $page['before'], $page['perPage']), 200);
    }
}
