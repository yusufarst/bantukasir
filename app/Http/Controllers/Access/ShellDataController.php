<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Domain\Inventory\OnboardingState;
use App\Models\User;
use App\Queries\Access\ShellIndicators;
use App\Queries\Inventory\Incidents;
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
 * checkout. The onboarding state is the Inventory module's too, for every role:
 * the banner of UXS-27 tells each one that Sales and ordinary postings stop
 * (UXI-030; TASK-035). The open reconciliation incidents of UXI-017 are the Inventory module's
 * too, for a holder of CAP-18, the Owner, alone (TASK-036).
 */
final class ShellDataController
{
    public function __invoke(Request $request, ShellIndicators $indicators, StockAttention $attention, OnboardingState $onboarding, Incidents $incidents): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RD-03 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        $count = Capabilities::of($user, Capability::Cap14) === Grant::Full ? $attention->count() : null;
        $open = Capabilities::allows($user, Capability::Cap18) ? $incidents->openCount() : null;

        return new JsonResponse($indicators($user)->withStockAttention($count)->withOnboarding($onboarding->current())->withOpenIncidents($open)->toArray(), 200);
    }
}
