<?php

namespace App\Http\Controllers\Access;

use App\Models\User;
use App\Queries\Access\ShellIndicators;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * RD-03, `GET /data/shell` (ROUTE_CONTRACTS section 3; signed in, CAP-01):
 * the shell's indicators of the signed-in user. The shell reads it as a
 * background request (RT-10), which never counts as user activity
 * (SEC-SESS-02); it takes no parameter (RT-08, `input.allow`) and writes
 * nothing.
 */
final class ShellDataController
{
    public function __invoke(Request $request, ShellIndicators $indicators): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RD-03 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        return new JsonResponse($indicators($user)->toArray(), 200);
    }
}
