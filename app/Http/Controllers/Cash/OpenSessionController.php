<?php

namespace App\Http\Controllers\Cash;

use App\Domain\Access\Homes;
use App\Models\User;
use App\Queries\Cash\CashRegisterRows;
use App\Queries\Cash\OpenSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-104, UXP-104 "Buka sesi kas" (ROUTE_CONTRACTS section 2; COPY_DECK section 7.4; KEY_SCREENS
 * KS-06; NAVIGATION_CONTRACTS UXP-104; TASK-027 scope item 4), for every holder of CAP-21: the
 * registers available now — active, without an OPEN session — and none when every one is in use or
 * inactive (UXS-48). A user who is already responsible for an OPEN session is sent with 302 to
 * RP-101, the till (TASK-029).
 */
final class OpenSessionController
{
    /** RP-101, the till (TASK-029). */
    public const string WITH_SESSION = Homes::TILL;

    public function show(Request $request, CashRegisterRows $registers, OpenSessions $sessions): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($sessions->heldBy($user->id)) {
            return redirect()->to(self::WITH_SESSION);
        }

        return Inertia::render('cash/OpenSession', ['registers' => $registers->available()]);
    }
}
