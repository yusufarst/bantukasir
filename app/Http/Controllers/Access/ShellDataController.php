<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Models\User;
use App\Queries\Access\ShellIndicators;
use App\Queries\Inventory\StockAttention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * RD-03, `GET /data/shell` (ROUTE_CONTRACTS section 3; signed in, CAP-01):
 * the shell's indicators of the signed-in user. The shell reads it as a
 * background request (RT-10), which never counts as user activity
 * (SEC-SESS-02); it takes no parameter (RT-08, `input.allow`) and writes
 * nothing. The stock-attention count of the badge (UXI-006; TASK-022) is the
 * Inventory module's, of the one definition that RD-02 uses too, so that the
 * badge and the list agree (BR-INV-41, ARC-22); it is read only for a full
 * holder of CAP-14 — never a cashier, whose restricted cell is the stock at
 * checkout.
 */
final class ShellDataController
{
    public function __invoke(Request $request, ShellIndicators $indicators, StockAttention $attention): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RD-03 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        $count = Capabilities::of($user, Capability::Cap14) === Grant::Full ? $attention->count() : null;

        return new JsonResponse($indicators($user)->withStockAttention($count)->toArray(), 200);
    }
}
