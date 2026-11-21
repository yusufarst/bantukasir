<?php

namespace App\Http\Controllers\Audit;

use App\Http\Requests\Audit\SecurityLogPageRequest;
use App\Queries\Audit\ActivityLog;
use App\Queries\Audit\SecurityLogEntries;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-613, UXP-613 "Riwayat keamanan" (ROUTE_CONTRACTS RP-613; COPY_DECK section 11.13; INTERACTION_INVENTORY
 * UXI-382 to UXI-385; NAVIGATION_CONTRACTS UXP-613; REQ-AUD-02, REQ-NFR-07; SECURITY_MODEL SEC-LOG-03; WF-31;
 * TASK-042 scope item 2), the Owner's alone (CAP-32): the security log with its filters, the accounts of the
 * filter "Pengguna" — the same as Riwayat aktivitas offers — and one page of entries.
 */
final class SecurityLogController
{
    public function index(SecurityLogPageRequest $request, SecurityLogEntries $entries, ActivityLog $accounts): Response
    {
        $filters = $request->filters();

        return Inertia::render('audit/SecurityLog', [
            'filters' => [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'type' => $filters['type'],
                'user' => $filters['user'],
                'perPage' => $filters['perPage'],
            ],
            'users' => $accounts->users(),
            'entries' => $entries->list($filters),
        ]);
    }
}
