<?php

namespace App\Http\Controllers\Audit;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Http\Requests\Audit\ActivityPageRequest;
use App\Models\User;
use App\Queries\Audit\ActivityLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-612, UXP-612 "Riwayat aktivitas" (ROUTE_CONTRACTS RP-612; COPY_DECK sections 11.12 and 11.14;
 * INTERACTION_INVENTORY UXI-377 to UXI-381; NAVIGATION_CONTRACTS UXP-612; REQ-AUD-01, REQ-AUD-02; WF-31;
 * TASK-042 scope item 1), the Owner's alone (CAP-32): the business audit with its filters, the accounts and
 * the chosen product of the filters, and one page of events. The Owner-only data of an event reaches the page
 * only for a holder of CAP-32, which the gate already requires (BR-AUD-02, AC-042-04).
 */
final class ActivityController
{
    public function index(ActivityPageRequest $request, ActivityLog $log): Response
    {
        $filters = $request->filters();
        $viewer = $request->user();
        $owner = $viewer instanceof User && Capabilities::allows($viewer, Capability::Cap32);

        return Inertia::render('audit/Activity', [
            'filters' => [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'user' => $filters['user'],
                'action' => $filters['action'],
                'product' => $filters['product'],
                'session' => $filters['session'],
                'reference' => $filters['reference'],
                'perPage' => $filters['perPage'],
            ],
            'users' => $log->users(),
            'product' => $log->product($filters['product']),
            'events' => $log->list($filters, $owner),
        ]);
    }
}
