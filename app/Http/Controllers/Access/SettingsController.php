<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-601, UXP-601 "Pengaturan" (ROUTE_CONTRACTS section 2; COPY_DECK section
 * 11.1; INTERACTION_INVENTORY UXP-601; TASK-010 scope item 5): the index of
 * the Owner's settings and oversight pages, gated by any of CAP-25, CAP-30,
 * CAP-31 and CAP-32. It lists only the entries whose page exists and whose
 * capability the user holds (SEC-AZ-07; EXECUTION_CONTEXT EXC-52), in the
 * order of COPY_DECK section 11.1; each later Task adds its own: users
 * (UXI-326, TASK-011), cash registers (UXI-328), the system status, the
 * activity and the security histories (UXI-329 to UXI-331).
 */
final class SettingsController
{
    /**
     * The entries that exist, in their order: the page, its path and the
     * capability that opens it.
     *
     * @var list<array{id: string, href: string, capability: Capability}>
     */
    public const array ENTRIES = [
        // UXI-327 "Profil usaha" (TASK-010).
        ['id' => 'profile', 'href' => '/settings/business-profile', 'capability' => Capability::Cap30],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $entries = [];

        foreach (self::ENTRIES as $entry) {
            if (Capabilities::allows($user, $entry['capability'])) {
                $entries[] = ['id' => $entry['id'], 'href' => $entry['href']];
            }
        }

        return Inertia::render('access/Settings', ['entries' => $entries]);
    }
}
