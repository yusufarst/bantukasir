<?php

namespace App\Http\Controllers\Operations;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use App\Queries\Operations\SystemStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * RP-611, UXP-611 "Status sistem" (ROUTE_CONTRACTS RP-611; INFORMATION_ARCHITECTURE UXP-611;
 * NAVIGATION_CONTRACTS UXP-611; INTERACTION_INVENTORY UXI-374 to UXI-376; OPERATIONS_RUNBOOK section 5;
 * REQ-OPS-04; CAP-32; TASK-039 scope item 2), the Owner's alone: the five items of the system status read in
 * one request, which "Muat ulang" (UXI-374) reads again by name. A read that fails is `status: null`, which
 * the page shows as UXS-22, never a passing look; its cause goes to the log, never to the page. Whether a
 * reconciliation incident is open reaches the page only for a holder of CAP-18, whose link UXI-375 is.
 */
final class StatusController
{
    /** The prop of the status, reloaded by name (UXI-374). */
    public const string STATUS = 'status';

    public function show(Request $request, SystemStatus $status): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return Inertia::render('operations/Status', [
            self::STATUS => static fn (): ?array => self::read($user, $status),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(User $user, SystemStatus $status): ?array
    {
        try {
            $read = $status->read();
        } catch (Throwable $failure) {
            report($failure);

            return null;
        }

        $read['incidentsOpen'] = $read['incidentsOpen'] && Capabilities::allows($user, Capability::Cap18);

        return $read;
    }
}
